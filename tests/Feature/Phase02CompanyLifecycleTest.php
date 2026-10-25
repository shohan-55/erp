<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase02CompanyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function platform(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_platform_admin' => true])->save();

        return $user;
    }

    private function firm(string $slug): array
    {
        $company = DB::table('companies')->insertGetId([
            'slug' => $slug, 'name' => strtoupper($slug).' Chartered Accountants',
            'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00',
            'office_end' => '18:00:00', 'grace_minutes' => 10,
            'weekly_holidays' => '[5,6]', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => 'partner-one',
            'name' => 'Partner One', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create();
        $membership = DB::table('memberships')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $user->id, 'role' => 'student', 'active' => true,
            'monthly_allowance' => '0.00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$company, $workspace, $user, $membership];
    }

    public function test_existing_firms_are_customers_and_platform_classification_is_explicitly_audited(): void
    {
        [$company] = $this->firm('demo-first');
        $this->assertDatabaseHas('companies', [
            'id' => $company, 'environment_kind' => 'customer', 'deleted_at' => null,
        ]);

        $url = "/platform/app/companies/{$company}/classification";
        $this->actingAs($this->platform());
        $this->post($url, [
            'environment_kind' => 'demo', 'confirmation_slug' => 'incorrect',
            'reason' => 'Confirmed test-only sandbox.',
        ])->assertSessionHasErrors('confirmation_slug');
        $this->post($url, [
            'environment_kind' => 'demo', 'confirmation_slug' => 'demo-first',
            'reason' => 'Confirmed test-only sandbox.',
        ])->assertRedirect();

        $this->assertDatabaseHas('companies', ['id' => $company, 'environment_kind' => 'demo']);
        $this->assertDatabaseHas('platform_control_events', [
            'company_id' => $company, 'action' => 'company.environment.changed',
        ]);
        $this->get("/platform/app/companies/{$company}")->assertOk()
            ->assertSee('Customer vs Demo Classification');
    }

    public function test_archived_company_removal_is_recoverable_and_never_deletes_other_tenants_or_memberships(): void
    {
        [$company, $workspace, $memberUser, $member] = $this->firm('demo-first');
        [$other, , $otherUser, $otherMember] = $this->firm('other-firm');
        $url = "/platform/app/companies/{$company}/lifecycle";
        $admin = $this->platform();

        $this->actingAs($admin)->post($url, [
            'action' => 'remove', 'confirmation_slug' => 'demo-first',
            'remove_confirmation' => 'REMOVE', 'reason' => 'Sandbox test tenant retired.',
        ])->assertSessionHasErrors('action');

        $this->post($url, ['action' => 'suspend', 'confirmation_slug' => 'demo-first'])->assertRedirect();
        $this->post($url, ['action' => 'archive', 'confirmation_slug' => 'demo-first'])->assertRedirect();

        $this->post($url, [
            'action' => 'remove', 'confirmation_slug' => 'wrong',
            'remove_confirmation' => 'REMOVE', 'reason' => 'Sandbox test tenant retired.',
        ])->assertSessionHasErrors('confirmation_slug');
        $this->post($url, [
            'action' => 'remove', 'confirmation_slug' => 'demo-first',
            'remove_confirmation' => 'WRONG', 'reason' => 'Sandbox test tenant retired.',
        ])->assertSessionHasErrors('remove_confirmation');

        $this->post($url, [
            'action' => 'remove', 'confirmation_slug' => 'demo-first',
            'remove_confirmation' => 'REMOVE', 'reason' => 'Sandbox test tenant retired.',
        ])->assertRedirect();

        $this->assertNotNull(DB::table('companies')->where('id', $company)->value('deleted_at'));
        $this->assertDatabaseHas('companies', ['id' => $company, 'active' => false]);
        $this->assertDatabaseHas('memberships', ['id' => $member, 'company_id' => $company]);
        $this->assertDatabaseHas('memberships', ['id' => $otherMember, 'company_id' => $other]);
        $this->assertDatabaseHas('companies', ['id' => $other, 'active' => true, 'deleted_at' => null]);
        $this->assertDatabaseHas('platform_control_events', [
            'company_id' => $company, 'action' => 'company.remove',
        ]);

        $this->get('/platform/app')->assertOk()->assertDontSee('Slug: demo-first');
        $this->get('/platform/app?status=removed')->assertOk()->assertSee('demo-first');
        $this->actingAs($memberUser)->get('/c/demo-first/app')->assertForbidden();
        $this->actingAs($otherUser)->get('/c/other-firm/app')->assertOk();

        $this->actingAs($admin);
        $this->post($url, ['action' => 'reactivate', 'confirmation_slug' => 'demo-first'])
            ->assertSessionHasErrors('action');
        $this->post($url, ['action' => 'restore', 'confirmation_slug' => 'demo-first'])
            ->assertRedirect();

        $this->assertDatabaseHas('companies', [
            'id' => $company, 'active' => false, 'deleted_at' => null, 'archived_at' => null,
        ]);
        $this->actingAs($memberUser)->get('/c/demo-first/app')->assertForbidden();
        $this->actingAs($admin)->post($url, [
            'action' => 'reactivate', 'confirmation_slug' => 'demo-first',
        ])->assertRedirect();
        $this->actingAs($memberUser)->get('/c/demo-first/app')->assertOk();
        $this->assertDatabaseHas('memberships', ['id' => $member, 'workspace_id' => $workspace]);
    }

    public function test_non_admin_cannot_classify_or_remove_a_company(): void
    {
        [$company, , $memberUser] = $this->firm('demo-first');
        $this->actingAs($memberUser)->post("/platform/app/companies/{$company}/classification", [
            'environment_kind' => 'demo', 'confirmation_slug' => 'demo-first',
            'reason' => 'This must not be allowed.',
        ])->assertForbidden();

        $this->post("/platform/app/companies/{$company}/lifecycle", [
            'action' => 'remove', 'confirmation_slug' => 'demo-first',
            'remove_confirmation' => 'REMOVE', 'reason' => 'This must not be allowed.',
        ])->assertForbidden();

        $this->assertDatabaseHas('companies', [
            'id' => $company, 'environment_kind' => 'customer', 'deleted_at' => null,
        ]);
    }

    public function test_unfinished_company_exit_blocks_archive_and_soft_remove_but_not_other_tenants(): void
    {
        [$company, , , $member] = $this->firm('exit-in-progress');
        [$otherCompany] = $this->firm('unrelated-customer');
        $admin = $this->platform();
        $url = "/platform/app/companies/{$company}/lifecycle";

        $case = DB::table('tenant_exit_cases')->insertGetId([
            'company_id' => $company,
            'requested_by' => $member,
            'state' => 'awaiting_consent',
            'reason' => 'Customer is arranging an independent export prior to exit.',
            'retention_until' => now()->addDays(45)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin);
        // Suspension is still possible so an administrator can stop new access.
        $this->post($url, [
            'action' => 'suspend', 'confirmation_slug' => 'exit-in-progress',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Archiving now would prevent the approved export worker from preparing.
        $this->post($url, [
            'action' => 'archive', 'confirmation_slug' => 'exit-in-progress',
        ])->assertSessionHasErrors('action');
        $this->assertDatabaseHas('companies', [
            'id' => $company, 'active' => false, 'archived_at' => null, 'deleted_at' => null,
        ]);
        $this->assertDatabaseMissing('platform_control_events', [
            'company_id' => $company, 'action' => 'company.archive',
        ]);
        $this->get("/platform/app/companies/{$company}")
            ->assertOk()->assertSee('unfinished company data-exit request(s)')
            ->assertDontSee('Archive (Retain Records)');


        // A different customer never inherits the first tenant's export lock.
        $otherUrl = "/platform/app/companies/{$otherCompany}/lifecycle";
        $this->post($otherUrl, [
            'action' => 'suspend', 'confirmation_slug' => 'unrelated-customer',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($otherUrl, [
            'action' => 'archive', 'confirmation_slug' => 'unrelated-customer',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // A rejected case is terminal; it cannot accidentally block the
        // reversible lifecycle forever. Its records still stay untouched.
        DB::table('tenant_exit_cases')->where('id', $case)->update(['state' => 'rejected']);
        $this->get("/platform/app/companies/{$company}")
            ->assertOk()->assertDontSee('unfinished company data-exit request(s)')
            ->assertSee('Archive (Retain Records)');

        $this->post($url, [
            'action' => 'archive', 'confirmation_slug' => 'exit-in-progress',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // An awaiting verification case also blocks directory removal.
        DB::table('tenant_exit_cases')->where('id', $case)->update(['state' => 'integrity_verified']);
        $this->post($url, [
            'action' => 'remove', 'confirmation_slug' => 'exit-in-progress',
            'remove_confirmation' => 'REMOVE',
            'reason' => 'Administrative soft removal of an archived company.',
        ])->assertSessionHasErrors('action');
        $this->assertDatabaseHas('companies', [
            'id' => $company, 'deleted_at' => null,
        ]);

        // Once an independent restore is recorded, reversible removal is
        // possible. This never constitutes permission to physically purge.
        DB::table('tenant_exit_cases')->where('id', $case)->update(['state' => 'restore_attested']);
        $this->post($url, [
            'action' => 'remove', 'confirmation_slug' => 'exit-in-progress',
            'remove_confirmation' => 'REMOVE',
            'reason' => 'Administrative soft removal after customer export.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('companies')->where('id', $company)->value('deleted_at'));
        $this->assertDatabaseHas('memberships', ['id' => $member, 'company_id' => $company]);
        $this->assertDatabaseHas('tenant_exit_cases', ['id' => $case, 'company_id' => $company]);
        $this->assertDatabaseHas('companies', ['id' => $otherCompany, 'deleted_at' => null]);
    }
}
