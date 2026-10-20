<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ErpFeatures;
use App\Support\StudentFeatureAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Phase07FeatureStudioTest extends TestCase
{
    use RefreshDatabase;

    private function firm(string $slug): array
    {
        $company = DB::table('companies')->insertGetId([
            'name' => $slug.' Firm', 'slug' => $slug, 'active' => true,
            'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00',
            'office_end' => '18:00:00', 'grace_minutes' => 10,
            'weekly_holidays' => '[5,6]', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => 'partner-one',
            'name' => 'Partner One', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $people = [];
        foreach (['partner', 'manager', 'student'] as $role) {
            $user = User::factory()->create(['name' => $slug.' '.$role]);
            $id = DB::table('memberships')->insertGetId([
                'company_id' => $company, 'workspace_id' => $workspace,
                'user_id' => $user->id, 'role' => $role, 'active' => true,
                'monthly_allowance' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $people[$role] = (object) ['id' => $id, 'user' => $user];
        }
        return compact('company', 'workspace', 'people');
    }

    private function update(array $f, string $module, string $scope, string $operation, array $rest = [])
    {
        return $this->post('/c/'.DB::table('companies')->where('id', $f['company'])->value('slug')
            .'/app/settings/features', [
                'feature_key' => $module, 'scope' => $scope,
                'operation' => $operation,
                'reason' => 'Workspace Partner approved this module change.',
                ...$rest,
            ]);
    }

    public function test_company_and_workspace_scopes_enforce_all_http_methods_and_inherit_denials(): void
    {
        $f = $this->firm('example-one');
        $partner = $f['people']['partner'];
        $student = $f['people']['student'];
        $base = '/c/example-one/app';

        $this->actingAs($partner->user)->get("$base/settings/features")
            ->assertOk()->assertSee('Feature Settings Studio');

        $this->update($f, 'attendance', 'company', 'disable')
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('erp_feature_controls', [
            'company_id' => $f['company'], 'scope_key' => 'company',
            'feature_key' => 'attendance', 'enabled' => false,
        ]);
        $this->actingAs($student->user)->get("$base/attendance")->assertForbidden();
        $this->post("$base/attendance/check-in", [])->assertForbidden();
        $this->get("$base/attendance/report")->assertForbidden();
        $this->actingAs($partner->user)->get("$base/attendance")->assertForbidden();
        $this->get("$base/settings/features")->assertOk();

        $this->update($f, 'attendance', 'workspace', 'enable')
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($student->user)->get("$base/attendance")->assertForbidden();

        $this->actingAs($partner->user)
            ->update($f, 'attendance', 'company', 'reset')
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($student->user)->get("$base/attendance")->assertOk();

        $actor = DB::table('memberships')->find($student->id);
        $this->assertTrue(ErpFeatures::enabled($actor, 'attendance'));
        $this->assertTrue(ErpFeatures::enabled($actor, 'attendance_report'));
        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $f['company'], 'action' => 'feature.setting.changed',
        ]);
    }

    public function test_workspace_role_and_user_restrictions_cannot_be_overridden_below_parent(): void
    {
        $f = $this->firm('example-two');
        $p = $f['people']['partner'];
        $m = $f['people']['manager'];
        $s = $f['people']['student'];
        $base = '/c/example-two/app';

        $this->actingAs($p->user)->update($f, 'leave', 'role', 'disable', [
            'role' => 'student',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($s->user)->get("$base/leave")->assertForbidden();
        $this->actingAs($m->user)->get("$base/leave")->assertOk();
        $this->actingAs($p->user)->get("$base/leave")->assertOk();

        $this->update($f, 'leave', 'user', 'enable', [
            'membership_id' => $s->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($s->user)->get("$base/leave")->assertForbidden();

        $this->actingAs($p->user)->update($f, 'leave', 'role', 'reset', [
            'role' => 'student',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($s->user)->get("$base/leave")->assertOk();

        $this->actingAs($p->user)->update($f, 'conveyance', 'workspace', 'disable')
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->update($f, 'conveyance', 'user', 'enable', [
            'membership_id' => $s->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($s->user)->get("$base/claims")->assertForbidden();
        $this->actingAs($p->user)->get("$base/claims")->assertForbidden();
    }

    public function test_student_legacy_controls_still_apply_after_partner_enables_module(): void
    {
        $f = $this->firm('example-three');
        $p = $f['people']['partner'];
        $s = $f['people']['student'];
        $base = '/c/example-three/app';
        DB::table('student_feature_controls')->insert([
            'company_id' => $f['company'], 'workspace_id' => $f['workspace'],
            'membership_id' => $s->id, 'feature_key' => 'leave',
            'partner_enabled' => false, 'manager_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($p->user)->update($f, 'leave', 'workspace', 'enable')
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($s->user)->get("$base/leave")->assertForbidden();
        $student = DB::table('memberships')->find($s->id);
        $this->assertFalse(StudentFeatureAccess::enabled($student, 'leave'));
        $this->assertFalse(StudentFeatureAccess::enabledMap($student)['leave']);

        $this->actingAs($p->user)->post("$base/permissions/students/{$s->id}", [
            'feature_key' => 'leave', 'enabled' => true,
            'reset_manager_restriction' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($s->user)->get("$base/leave")->assertOk();
    }

    public function test_two_partner_company_cannot_make_unilateral_company_wide_changes(): void
    {
        $f = $this->firm('many-partners');
        $otherWorkspace = DB::table('workspaces')->insertGetId([
            'company_id' => $f['company'], 'slug' => 'partner-two',
            'name' => 'Partner Two', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $second = User::factory()->create();
        DB::table('memberships')->insert([
            'company_id' => $f['company'], 'workspace_id' => $otherWorkspace,
            'user_id' => $second->id, 'role' => 'partner', 'active' => true,
            'monthly_allowance' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($f['people']['partner']->user)
            ->update($f, 'leave', 'company', 'disable')
            ->assertSessionHasErrors('scope');
        $this->assertDatabaseCount('erp_feature_controls', 0);

        $this->update($f, 'leave', 'workspace', 'disable')
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('erp_feature_controls', [
            'company_id' => $f['company'],
            'scope_key' => 'workspace:'.$f['workspace'],
            'feature_key' => 'leave', 'enabled' => false,
        ]);
        $this->actingAs($second)->get('/c/many-partners/app/leave')->assertOk();
    }

    public function test_student_manager_and_external_company_cannot_change_module_or_target_foreign_member(): void
    {
        $a = $this->firm('firm-a');
        $b = $this->firm('firm-b');
        $base = '/c/firm-a/app';

        $this->actingAs($a['people']['manager']->user)->get("$base/settings/features")->assertForbidden();
        $this->update($a, 'leave', 'workspace', 'disable')->assertForbidden();
        $this->actingAs($a['people']['student']->user)->get("$base/settings/features")->assertForbidden();
        $this->actingAs($a['people']['partner']->user)
            ->update($a, 'leave', 'user', 'disable', [
                'membership_id' => $b['people']['student']->id,
            ])->assertNotFound();

        $this->update($a, 'secret_superadmin', 'workspace', 'disable')
            ->assertSessionHasErrors('feature_key');
        $this->get("$base/settings/security")->assertOk();
        $this->assertDatabaseCount('erp_feature_controls', 0);

        $this->actingAs($b['people']['partner']->user)->get("$base/settings/features")->assertForbidden();
    }

    public function test_engagement_dependency_disables_timesheets_and_revoked_nav_links(): void
    {
        $f = $this->firm('dependency-firm');
        $p = $f['people']['partner'];
        $base = '/c/dependency-firm/app';

        $this->actingAs($p->user)->update($f, 'engagements', 'workspace', 'disable')
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get("$base/engagements")->assertForbidden();
        $this->get("$base/timesheets")->assertForbidden();
        $this->post("$base/timesheets", [])->assertForbidden();

        $actor = DB::table('memberships')->find($p->id);
        $this->assertFalse(ErpFeatures::enabled($actor, 'timesheets'));
        $this->assertTrue(ErpFeatures::enabled($actor, 'payslip'));
        $this->get("$base")->assertOk()->assertDontSee('href="'.$base.'/engagements"');
    }
}
