<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase03StudentPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function firm(string $slug = 'firm-a'): int
    {
        return DB::table('companies')->insertGetId([
            'slug' => $slug, 'name' => 'Independent CA Firm '.$slug, 'active' => true,
            'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00',
            'office_end' => '18:00:00', 'grace_minutes' => 10,
            'weekly_holidays' => '[5,6]', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function workspace(int $firm, string $slug): int
    {
        return DB::table('workspaces')->insertGetId([
            'company_id' => $firm, 'slug' => $slug,
            'name' => $slug, 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function member(int $firm, int $workspace, string $role, ?int $managerId = null): array
    {
        $user = User::factory()->create();
        $id = DB::table('memberships')->insertGetId([
            'company_id' => $firm, 'workspace_id' => $workspace,
            'user_id' => $user->id, 'role' => $role,
            'active' => true, 'manager_membership_id' => $managerId,
            'monthly_allowance' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$user, $id];
    }

    private function toggle(string $slug, int $studentId, string $key, bool $on): \Illuminate\Testing\TestResponse
    {
        return $this->post("/c/$slug/app/permissions/students/$studentId", [
            'feature_key' => $key, 'enabled' => $on ? '1' : '0',
        ]);
    }

    public function test_partner_can_revoke_grid_and_direct_student_urls_and_actions(): void
    {
        $firm = $this->firm();
        $workspace = $this->workspace($firm, 'partner-one');
        [$partner] = $this->member($firm, $workspace, 'partner');
        [$student, $studentId] = $this->member($firm, $workspace, 'student');

        $this->actingAs($partner)->get('/c/firm-a/app/permissions/students')->assertOk()
            ->assertSeeText('Student Dashboard & Feature Permissions');
        $this->toggle('firm-a', $studentId, 'attendance', false)->assertRedirect();
        $this->toggle('firm-a', $studentId, 'leave', false)->assertRedirect();
        $this->assertDatabaseHas('tenant_audit_events', ['action' => 'student.feature.updated',
            'entity_id' => $studentId, 'workspace_id' => $workspace]);

        $this->actingAs($student)->get('/c/firm-a/app')->assertOk()
            ->assertDontSee('QUICK ATTENDANCE')->assertDontSee('Checked in Today')
            ->assertDontSee('Leave for Review');
        $this->get('/c/firm-a/app/attendance')->assertForbidden();
        $this->get('/c/firm-a/app/attendance/report')->assertOk(); // Separate permission.
        $this->post('/c/firm-a/app/attendance/check-in', [
            'work_mode' => 'office',
        ])->assertForbidden();
        $this->get('/c/firm-a/app/leave')->assertForbidden();
        $this->post('/c/firm-a/app/leave', [
            'leave_type' => 'sick', 'date_from' => '2026-11-01',
            'date_to' => '2026-11-01', 'reason' => 'I need an approved sick leave.',
        ])->assertForbidden();
        $this->get('/c/firm-a/app/claims')->assertOk();
        $this->assertDatabaseCount('attendance_sessions', 0);
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_partner_can_restore_student_permission_and_keep_existing_data_intact(): void
    {
        $firm = $this->firm();
        $w = $this->workspace($firm, 'partner-one');
        [$partner] = $this->member($firm, $w, 'partner');
        [$student, $studentId] = $this->member($firm, $w, 'student');
        $this->actingAs($partner);
        $this->toggle('firm-a', $studentId, 'conveyance', false)->assertRedirect();
        $this->actingAs($student)->get('/c/firm-a/app/claims')->assertForbidden();
        $this->actingAs($partner);
        $this->toggle('firm-a', $studentId, 'conveyance', true)->assertRedirect();
        $this->actingAs($student)->get('/c/firm-a/app/claims')->assertOk();
        $this->assertDatabaseHas('student_feature_controls', [
            'membership_id' => $studentId, 'feature_key' => 'conveyance',
            'partner_enabled' => 1, 'manager_enabled' => 1,
        ]);
    }

    public function test_managers_require_explicit_partner_delegation_and_can_only_restrict_assigned_students(): void
    {
        $firm = $this->firm();
        $w = $this->workspace($firm, 'partner-one');
        [$partner] = $this->member($firm, $w, 'partner');
        [$manager, $managerId] = $this->member($firm, $w, 'manager');
        [$student, $studentId] = $this->member($firm, $w, 'student', $managerId);
        [, $unassignedId] = $this->member($firm, $w, 'student');

        $this->actingAs($manager)->get('/c/firm-a/app/permissions/students')->assertForbidden();
        $this->toggle('firm-a', $studentId, 'notifications', false)->assertForbidden();

        $this->actingAs($partner)->post("/c/firm-a/app/permissions/managers/$managerId/delegation", [
            'enabled' => 1,
        ])->assertRedirect();

        $this->actingAs($manager)->get('/c/firm-a/app/permissions/students')->assertOk()
            ->assertSee($student->email);
        $this->toggle('firm-a', $unassignedId, 'notifications', false)->assertNotFound();
        $this->toggle('firm-a', $studentId, 'notifications', false)->assertRedirect();
        $this->actingAs($student)->get('/c/firm-a/app/notifications')->assertForbidden();

        $this->actingAs($partner)->post("/c/firm-a/app/permissions/managers/$managerId/delegation", [
            'enabled' => 0,
        ])->assertRedirect();
        $this->actingAs($manager)->get('/c/firm-a/app/permissions/students')->assertForbidden();
        $this->toggle('firm-a', $studentId, 'notifications', true)->assertForbidden();
    }

    public function test_delegated_manager_cannot_enable_a_partner_disabled_feature(): void
    {
        $firm = $this->firm();
        $w = $this->workspace($firm, 'partner-one');
        [$partner] = $this->member($firm, $w, 'partner');
        [$manager, $managerId] = $this->member($firm, $w, 'manager');
        [$student, $studentId] = $this->member($firm, $w, 'student', $managerId);

        $this->actingAs($partner);
        $this->post("/c/firm-a/app/permissions/managers/$managerId/delegation", ['enabled' => 1])
            ->assertRedirect();
        $this->toggle('firm-a', $studentId, 'payslip', false)->assertRedirect();

        $this->actingAs($manager);
        $this->toggle('firm-a', $studentId, 'payslip', true)
            ->assertSessionHasErrors('enabled');
        $this->actingAs($student)->get('/c/firm-a/app/payroll')->assertForbidden();

        $this->actingAs($manager);
        $this->toggle('firm-a', $studentId, 'attendance_report', false)->assertRedirect();
        $this->actingAs($student)->get('/c/firm-a/app/attendance/report')->assertForbidden();
        $this->get('/c/firm-a/app/attendance')->assertOk();
    }

    public function test_partner_reenabling_permission_does_not_override_manager_restriction(): void
    {
        $firm = $this->firm();
        $w = $this->workspace($firm, 'partner-one');
        [$partner] = $this->member($firm, $w, 'partner');
        [$manager, $managerId] = $this->member($firm, $w, 'manager');
        [$student, $studentId] = $this->member($firm, $w, 'student', $managerId);

        $this->actingAs($partner);
        $this->post("/c/firm-a/app/permissions/managers/$managerId/delegation", ['enabled' => 1])->assertRedirect();
        $this->actingAs($manager);
        $this->toggle('firm-a', $studentId, 'leave', false)->assertRedirect();
        $this->actingAs($partner);
        $this->toggle('firm-a', $studentId, 'leave', false)->assertRedirect();
        $this->toggle('firm-a', $studentId, 'leave', true)->assertRedirect();
        $this->assertDatabaseHas('student_feature_controls', [
            'membership_id' => $studentId, 'feature_key' => 'leave',
            'partner_enabled' => 1, 'manager_enabled' => 0,
        ]);
        $this->actingAs($student)->get('/c/firm-a/app/leave')->assertForbidden();
    }

    public function test_cross_partner_and_cross_firm_permission_changes_are_rejected(): void
    {
        $one = $this->firm('firm-a');
        $other = $this->firm('firm-b');
        $w1 = $this->workspace($one, 'first');
        $w2 = $this->workspace($one, 'second');
        $w3 = $this->workspace($other, 'foreign');
        [$partner] = $this->member($one, $w1, 'partner');
        [$unrelatedPartner] = $this->member($one, $w2, 'partner');
        [$foreignPartner] = $this->member($other, $w3, 'partner');
        [, $otherMember] = $this->member($one, $w2, 'student');
        [, $foreignMember] = $this->member($other, $w3, 'student');

        $this->actingAs($partner);
        $this->toggle('firm-a', $otherMember, 'attendance', false)->assertNotFound();
        $this->toggle('firm-a', $foreignMember, 'attendance', false)->assertNotFound();
        $this->actingAs($unrelatedPartner)
            ->get('/c/firm-a/app/permissions/students')->assertOk();
        $this->actingAs($foreignPartner)
            ->post("/c/firm-a/app/permissions/students/$otherMember", [
                'feature_key' => 'attendance', 'enabled' => 0,
            ])->assertForbidden();
        $this->assertDatabaseCount('student_feature_controls', 0);
    }

    public function test_students_and_managers_cannot_delegate_and_invalid_feature_keys_are_rejected(): void
    {
        $firm = $this->firm();
        $w = $this->workspace($firm, 'first');
        [$partner] = $this->member($firm, $w, 'partner');
        [$manager, $managerId] = $this->member($firm, $w, 'manager');
        [$student, $studentId] = $this->member($firm, $w, 'student', $managerId);

        $this->actingAs($student);
        $this->toggle('firm-a', $studentId, 'attendance', false)->assertForbidden();
        $this->post("/c/firm-a/app/permissions/managers/$managerId/delegation", [
            'enabled' => 1,
        ])->assertForbidden();
        $this->actingAs($manager);
        $this->post("/c/firm-a/app/permissions/managers/$managerId/delegation", [
            'enabled' => 1,
        ])->assertForbidden();

        $this->actingAs($partner);
        $this->toggle('firm-a', $studentId, 'is_platform_admin', true)
            ->assertSessionHasErrors('feature_key');
        $this->assertDatabaseCount('student_feature_controls', 0);
    }
}
