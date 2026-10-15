<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceCorrectionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function seedWorkspace(): array
    {
        $companyId = DB::table('companies')->insertGetId([
            'slug' => 'rac', 'name' => 'Sample CA Firm', 'timezone' => 'Asia/Dhaka',
            'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspaceId = DB::table('workspaces')->insertGetId([
            'company_id' => $companyId, 'slug' => 'partner-01',
            'name' => 'Partner Unit 01', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $partner = User::factory()->create();
        $partnerId = DB::table('memberships')->insertGetId([
            'company_id' => $companyId, 'workspace_id' => $workspaceId,
            'user_id' => $partner->id, 'role' => 'partner', 'active' => true,
            'monthly_allowance' => '0.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $student = User::factory()->create();
        $studentId = DB::table('memberships')->insertGetId([
            'company_id' => $companyId, 'workspace_id' => $workspaceId,
            'user_id' => $student->id, 'role' => 'student', 'active' => true,
            'monthly_allowance' => '7000.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$companyId, $workspaceId, $partner, $partnerId, $studentId];
    }

    private function insertAttendanceSession(int $companyId, int $workspaceId, int $studentId, string $start, ?string $end): int
    {
        return DB::table('attendance_sessions')->insertGetId([
            'company_id' => $companyId, 'workspace_id' => $workspaceId,
            'membership_id' => $studentId, 'work_date' => '2026-10-14',
            'work_mode' => 'office', 'started_at' => $start, 'ended_at' => $end,
            'late' => false, 'holiday' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_partner_cannot_correct_checkout_to_overlap_a_second_session(): void
    {
        CarbonImmutable::setTestNow('2026-10-14 16:00:00 Asia/Dhaka');
        try {
            [$company, $workspace, $partner, , $studentId] = $this->seedWorkspace();
            $first = $this->insertAttendanceSession($company, $workspace, $studentId, '2026-10-14 04:00:00', '2026-10-14 04:30:00');
            $this->insertAttendanceSession($company, $workspace, $studentId, '2026-10-14 05:00:00', '2026-10-14 07:00:00');

            $this->actingAs($partner)->post("/c/rac/app/attendance/{$first}/correct", [
                'ended_at' => '2026-10-14T11:15',
                'reason' => 'A correction of the previous missing check-out.',
            ])->assertSessionHasErrors('ended_at');

            $this->assertDatabaseHas('attendance_sessions', [
                'id' => $first, 'ended_at' => '2026-10-14 04:30:00',
            ]);
            $this->assertDatabaseCount('tenant_audit_events', 0);

            $this->post("/c/rac/app/attendance/{$first}/correct", [
                'ended_at' => '2026-10-14T10:45',
                'reason' => 'Partner verified against the paper register.',
            ])->assertRedirect();

            $this->assertDatabaseHas('attendance_sessions', [
                'id' => $first, 'ended_at' => '2026-10-14 04:45:00',
            ]);
            $this->assertDatabaseHas('tenant_audit_events', [
                'entity_type' => 'attendance_session', 'entity_id' => $first,
                'action' => 'attendance.correct',
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_reviewed_payroll_period_blocks_attendance_corrections(): void
    {
        CarbonImmutable::setTestNow('2026-10-14 16:00:00 Asia/Dhaka');
        try {
            [$company, $workspace, $partner, , $studentId] = $this->seedWorkspace();
            $first = $this->insertAttendanceSession($company, $workspace, $studentId, '2026-10-14 04:00:00', null);
            DB::table('payroll_runs')->insert([
                'company_id' => $company, 'workspace_id' => $workspace,
                'period_month' => '2026-10-01', 'status' => 'reviewed',
                'policy_snapshot' => '{}', 'created_at' => now(), 'updated_at' => now(),
            ]);

            $this->actingAs($partner)->post("/c/rac/app/attendance/{$first}/correct", [
                'ended_at' => '2026-10-14T12:30',
                'reason' => 'Partner verified against recorded office schedule.',
            ])->assertSessionHasErrors('ended_at');

            $this->assertDatabaseHas('attendance_sessions', ['id' => $first, 'ended_at' => null]);
            $this->assertDatabaseCount('tenant_audit_events', 0);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
    public function test_attendance_correction_rejects_cross_partner_and_deactivated_membership_links(): void
    {
        CarbonImmutable::setTestNow('2026-10-14 16:00:00 Asia/Dhaka');
        try {
            [$company, $workspace, $partner, , $studentId] = $this->seedWorkspace();
            $otherWorkspace = DB::table('workspaces')->insertGetId([
                'company_id' => $company, 'slug' => 'partner-02',
                'name' => 'Other Partner Unit', 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $foreignUser = User::factory()->create();
            $foreignMember = DB::table('memberships')->insertGetId([
                'company_id' => $company, 'workspace_id' => $otherWorkspace,
                'user_id' => $foreignUser->id, 'role' => 'student',
                'monthly_allowance' => '0.00', 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // Malformed imported session claims Partner A but belongs to
            // a student assigned to Partner B.
            $malformed = $this->insertAttendanceSession(
                $company, $workspace, $foreignMember,
                '2026-10-14 04:00:00', null
            );

            $this->actingAs($partner)->post("/c/rac/app/attendance/{$malformed}/correct", [
                'ended_at' => '2026-10-14T11:00',
                'reason' => 'Only for synthetic regression verification.',
            ])->assertNotFound();

            $this->assertDatabaseHas('attendance_sessions', [
                'id' => $malformed, 'ended_at' => null, 'corrected_by' => null,
            ]);

            $inactive = $this->insertAttendanceSession(
                $company, $workspace, $studentId,
                '2026-10-14 05:00:00', null
            );
            DB::table('memberships')->where('id', $studentId)->update(['active' => false]);

            $this->post("/c/rac/app/attendance/{$inactive}/correct", [
                'ended_at' => '2026-10-14T12:00',
                'reason' => 'Inactive member must require access review.',
            ])->assertNotFound();

            $this->assertDatabaseHas('attendance_sessions', [
                'id' => $inactive, 'ended_at' => null, 'corrected_by' => null,
            ]);
            $this->assertDatabaseCount('tenant_audit_events', 0);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

}
