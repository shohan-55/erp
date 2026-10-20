<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase03OrganizationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private function platform(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_platform_admin' => true])->save();

        return $user;
    }

    private function tenant(string $slug, string $type = 'ca_firm'): array
    {
        $company = DB::table('companies')->insertGetId([
            'name' => $slug.' firm', 'slug' => $slug,
            'organization_type' => $type, 'timezone' => 'Asia/Dhaka',
            'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => 'partner-a', 'name' => 'Workspace A',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $partner = User::factory()->create();
        $partnerMember = DB::table('memberships')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $partner->id, 'role' => 'partner', 'active' => true,
            'monthly_allowance' => '0.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $student = User::factory()->create();
        $studentMember = DB::table('memberships')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $student->id, 'role' => 'student', 'active' => true,
            'monthly_allowance' => '0.00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact('company', 'workspace', 'partner', 'partnerMember', 'student', 'studentMember');
    }

    public function test_platform_can_onboard_a_corporate_template_without_creating_cross_tenant_data(): void
    {
        Mail::fake();
        $this->actingAs($this->platform())->post('/platform/app/companies', [
            'company_slug' => 'sample-corporate',
            'company_name' => 'Sample Corporate Limited',
            'organization_type' => 'corporate',
            'partner_name' => 'Initial Organization Owner',
            'partner_email' => 'corporate-owner@example.test',
        ])->assertRedirect();

        $company = DB::table('companies')->where('slug', 'sample-corporate')->firstOrFail();
        $this->assertSame('corporate', $company->organization_type);
        $this->assertDatabaseHas('workspaces', [
            'company_id' => $company->id, 'slug' => 'partner-01', 'name' => 'Head Office',
        ]);
        $this->assertDatabaseHas('platform_control_events', [
            'company_id' => $company->id, 'action' => 'company.onboarded',
        ]);
        $this->assertDatabaseCount('organization_units', 0);

        $this->get("/platform/app/companies/{$company->id}")
            ->assertOk()->assertSee('Corporate Company');
    }

    public function test_invalid_organization_type_rejected_and_legacy_ca_default_preserved(): void
    {
        Mail::fake();
        $this->actingAs($this->platform())->post('/platform/app/companies', [
            'company_slug' => 'invalid-type',
            'company_name' => 'An Invalid Type Company',
            'organization_type' => 'privileged_system',
            'partner_name' => 'New Partner Person',
            'partner_email' => 'invalid-type@example.test',
        ])->assertSessionHasErrors('organization_type');

        $this->assertDatabaseMissing('companies', ['slug' => 'invalid-type']);
        $this->post('/platform/app/companies', [
            'company_slug' => 'legacy-ca',
            'company_name' => 'Legacy CA Firm',
            'partner_name' => 'Legacy First Partner',
            'partner_email' => 'legacy-ca@example.test',
        ])->assertRedirect();

        $this->assertDatabaseHas('companies', [
            'slug' => 'legacy-ca', 'organization_type' => 'ca_firm',
        ]);
        $company = DB::table('companies')->where('slug', 'legacy-ca')->firstOrFail();
        $this->assertDatabaseHas('workspaces', [
            'company_id' => $company->id, 'slug' => 'partner-01',
            'name' => 'Partner Unit 01',
        ]);
    }

    public function test_partner_can_create_hierarchical_units_and_assign_a_workspace_member(): void
    {
        $t = $this->tenant('rac-test');
        $base = '/c/rac-test/app/organization/units';
        $this->actingAs($t['partner'])->get($base)->assertOk()
            ->assertSee('Organization Structure');

        $this->post($base, [
            'name' => 'Dhaka Office', 'slug' => 'dhaka-office', 'kind' => 'office',
        ])->assertRedirect();
        $parent = DB::table('organization_units')->where('slug', 'dhaka-office')->firstOrFail();
        $this->post($base, [
            'name' => 'Audit Practice', 'slug' => 'audit-practice',
            'kind' => 'practice', 'parent_id' => $parent->id,
        ])->assertRedirect();

        $child = DB::table('organization_units')->where('slug', 'audit-practice')->firstOrFail();
        $this->assertSame((int) $parent->id, (int) $child->parent_id);

        $this->post("$base/{$child->id}/members", [
            'membership_id' => $t['studentMember'],
        ])->assertRedirect();
        $this->assertDatabaseHas('organization_unit_assignments', [
            'company_id' => $t['company'], 'workspace_id' => $t['workspace'],
            'organization_unit_id' => $child->id, 'membership_id' => $t['studentMember'],
        ]);
        $this->assertDatabaseHas('tenant_audit_events', [
            'workspace_id' => $t['workspace'], 'action' => 'organization.assignment.created',
        ]);

        $this->post("$base/{$child->id}/rename", ['name' => 'Assurance Practice'])
            ->assertRedirect();
        $this->assertDatabaseHas('organization_units', [
            'id' => $child->id, 'name' => 'Assurance Practice',
        ]);

        $this->post("$base/{$child->id}/status", [
            'action' => 'deactivate', 'reason' => 'Closing this team after project work.',
        ])->assertSessionHasErrors('action');

        $this->post("$base/{$child->id}/members/{$t['studentMember']}/remove")
            ->assertRedirect();
        $this->post("$base/{$child->id}/status", [
            'action' => 'deactivate', 'reason' => 'Closing this team after project work.',
        ])->assertRedirect();
        $this->assertDatabaseHas('organization_units', ['id' => $child->id, 'active' => false]);

        $this->post("$base/{$child->id}/status", [
            'action' => 'reactivate', 'reason' => 'Reopening this team for the next audit.',
        ])->assertRedirect();
        $this->assertDatabaseHas('organization_units', ['id' => $child->id, 'active' => true]);
        // Membership, identity and operational tables are not changed by units.
        $this->assertDatabaseHas('memberships', [
            'id' => $t['studentMember'], 'workspace_id' => $t['workspace'],
        ]);
        $this->assertDatabaseCount('attendance_sessions', 0);
    }

    public function test_foreign_workspace_parent_and_staff_assignment_are_rejected(): void
    {
        $a = $this->tenant('first-firm');
        $b = $this->tenant('second-firm');
        $otherUnit = DB::table('organization_units')->insertGetId([
            'company_id' => $b['company'], 'workspace_id' => $b['workspace'],
            'slug' => 'secret-division', 'name' => 'Private Division',
            'kind' => 'division', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $base = '/c/first-firm/app/organization/units';
        $this->actingAs($a['partner'])->post($base, [
            'name' => 'Invalid Nested Unit', 'slug' => 'invalid-nested',
            'kind' => 'team', 'parent_id' => $otherUnit,
        ])->assertNotFound();
        $this->assertDatabaseMissing('organization_units', [
            'company_id' => $a['company'], 'slug' => 'invalid-nested',
        ]);

        $this->post($base, [
            'name' => 'Safe Team', 'slug' => 'safe-team', 'kind' => 'team',
        ])->assertRedirect();
        $local = DB::table('organization_units')->where('company_id', $a['company'])->firstOrFail();

        $this->post("$base/{$local->id}/members", [
            'membership_id' => $b['studentMember'],
        ])->assertNotFound();
        $this->post("$base/{$otherUnit}/members", [
            'membership_id' => $a['studentMember'],
        ])->assertNotFound();
        $this->assertDatabaseCount('organization_unit_assignments', 0);
        $this->get($base)->assertOk()->assertDontSee('Private Division');
    }

    public function test_students_managers_and_unrelated_platform_owner_cannot_manage_units(): void
    {
        $t = $this->tenant('client-firm');
        $base = '/c/client-firm/app/organization/units';

        $this->actingAs($t['student'])->get($base)->assertForbidden();
        $this->post($base, [
            'name' => 'Unapproved Team', 'slug' => 'unapproved-team', 'kind' => 'team',
        ])->assertForbidden();

        $platform = $this->platform();
        $this->actingAs($platform)->get($base)->assertForbidden();
        $this->post($base, [
            'name' => 'Platform Team', 'slug' => 'platform-team', 'kind' => 'team',
        ])->assertForbidden();
        $this->assertDatabaseCount('organization_units', 0);
    }
}
