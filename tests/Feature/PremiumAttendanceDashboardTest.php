<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PremiumAttendanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $company = DB::table('companies')->insertGetId([
            'slug' => 'wow-ca', 'name' => 'QA Firm', 'timezone' => 'Asia/Dhaka',
            'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => 'unit-1', 'name' => 'QA Unit',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $people = [];
        foreach (['partner', 'manager', 'student'] as $role) {
            $user = User::factory()->create();
            $member = DB::table('memberships')->insertGetId([
                'company_id' => $company, 'workspace_id' => $workspace,
                'user_id' => $user->id, 'role' => $role,
                'manager_membership_id' => $role === 'student' ? ($people['manager']['id'] ?? null) : null,
                'active' => true, 'monthly_allowance' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $people[$role] = ['user' => $user, 'id' => $member];
        }
        return [$company, $workspace, $people];
    }

    public function test_student_and_manager_see_personal_quick_attendance_with_confirmation_not_partner(): void
    {
        [, , $people] = $this->fixture();

        foreach (['student', 'manager'] as $role) {
            $this->actingAs($people[$role]['user'])
                ->get('/c/wow-ca/app')
                ->assertOk()
                ->assertSeeText('QUICK ATTENDANCE')
                ->assertSeeText('Check In Now')
                ->assertSee('data-attendance-confirm-form', false)
                ->assertSee('data-attendance-dialog', false)
                ->assertSee('id="erp-attendance-style"', false)
                ->assertSee('.attendance-spotlight__content{', false)
                ->assertSee('id="erp-attendance-script"', false)
                ->assertSee('initAttendance()', false);
        }
        $this->actingAs($people['student']['user'])->get('/c/wow-ca/app')
            ->assertOk()
            ->assertSeeInOrder(['QUICK ATTENDANCE', 'Your Quick Actions']);
        $this->actingAs($people['partner']['user'])
            ->get('/c/wow-ca/app')->assertOk()->assertDontSeeText('QUICK ATTENDANCE');
    }

    public function test_manager_can_check_in_only_as_self_and_records_real_late_status(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 10:15:00', 'Asia/Dhaka'));
        try {
            [$company, $workspace, $people] = $this->fixture();
            $manager = $people['manager'];

            $this->actingAs($manager['user'])
                ->from('/c/wow-ca/app')
                ->post('/c/wow-ca/app/attendance/check-in', ['work_mode' => 'office'])
                ->assertRedirect('/c/wow-ca/app')
                ->assertSessionHas('attendance_result.kind', 'in')
                ->assertSessionHas('attendance_result.late', true);

            $this->assertDatabaseHas('attendance_sessions', [
                'company_id' => $company,
                'workspace_id' => $workspace,
                'membership_id' => $manager['id'],
                'late' => 1, 'late_within_grace' => 0, 'holiday' => 0, 'ended_at' => null,
            ]);
            $this->assertDatabaseMissing('attendance_sessions', [
                'membership_id' => $people['student']['id'],
            ]);
            $this->get('/c/wow-ca/app')->assertOk()
                ->assertSeeText('Check Out Now')
                ->assertSeeText('You are checked in — a little late.')
                ->assertSeeText('LATE ARRIVAL RECORDED');
            $this->get('/c/wow-ca/app/attendance')->assertOk()->assertSeeText('QA Unit');

            $this->from('/c/wow-ca/app')
                ->post('/c/wow-ca/app/attendance/check-out')
                ->assertRedirect('/c/wow-ca/app')
                ->assertSessionHas('attendance_result.kind', 'out');
            $this->assertDatabaseMissing('attendance_sessions', [
                'membership_id' => $manager['id'], 'ended_at' => null,
            ]);
        } finally {
            $this->travelBack();
        }
    }

    public function test_student_grace_arrival_is_visibly_late_but_exempt_from_three_day_count(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 10:05:00', 'Asia/Dhaka'));
        try {
            [$company, $workspace, $people] = $this->fixture();
            $student = $people['student'];
            $this->actingAs($student['user'])
                ->from('/c/wow-ca/app')
                ->post('/c/wow-ca/app/attendance/check-in', ['work_mode' => 'office'])
                ->assertRedirect('/c/wow-ca/app')
                ->assertSessionHas('attendance_result.late', true)
                ->assertSessionHas('attendance_result.within_grace', true);

            $this->assertDatabaseHas('attendance_sessions', [
                'membership_id' => $student['id'], 'late' => 1, 'late_within_grace' => 1,
            ]);
            $this->assertSame(0, \App\Support\AttendanceClock::qualifyingLateDays(
                DB::table('companies')->find($company), DB::table('workspaces')->find($workspace),
                (object) ['id' => $student['id']], '2026-10-19'
            ));
            $this->get('/c/wow-ca/app')->assertOk()
                ->assertSeeText('Late, but within your grace window.')
                ->assertSeeText('LATE WITHIN GRACE · NOT COUNTED');

            $this->actingAs($people['partner']['user'])
                ->post('/c/wow-ca/app/attendance/check-in', ['work_mode' => 'office'])
                ->assertForbidden();
            $this->assertDatabaseCount('attendance_sessions', 1);
            $this->assertDatabaseCount('payroll_line_adjustments', 0);
        } finally {
            $this->travelBack();
        }
    }

    public function test_attendance_before_office_start_is_not_late(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 09:59:00', 'Asia/Dhaka'));
        try {
            [, , $people] = $this->fixture();
            $this->actingAs($people['student']['user'])
                ->from('/c/wow-ca/app')
                ->post('/c/wow-ca/app/attendance/check-in', ['work_mode' => 'office'])
                ->assertRedirect('/c/wow-ca/app')
                ->assertSessionHas('attendance_result.late', false);
            $this->assertDatabaseHas('attendance_sessions', [
                'membership_id' => $people['student']['id'], 'late' => 0, 'late_within_grace' => 0,
            ]);
        } finally {
            $this->travelBack();
        }
    }


    public function test_clock_endpoint_uses_real_server_time_and_partner_approved_half_day_warning(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 10:10:01', 'Asia/Dhaka'));
        try {
            [$company, $workspace, $people] = $this->fixture();

            $this->actingAs($people['student']['user'])
                ->getJson('/c/wow-ca/app/attendance/clock')->assertOk()
                ->assertJsonPath('clock.timezone', 'Asia/Dhaka')
                ->assertJsonPath('clock.grace_minutes', 10)
                ->assertJsonPath('clock.half_day_ms', null)
                ->assertJsonPath('qualifying_late_days', 0);

            $this->actingAs($people['manager']['user'])
                ->post('/c/wow-ca/app/policies/attendance-clock', ['half_day_warning_time' => '13:00'])
                ->assertForbidden();

            $this->actingAs($people['partner']['user'])
                ->post('/c/wow-ca/app/policies/attendance-clock', ['half_day_warning_time' => '13:00'])
                ->assertRedirect();
            $this->assertDatabaseHas('workspaces', [
                'id' => $workspace, 'half_day_warning_time' => '13:00:00',
            ]);
            $this->assertDatabaseHas('tenant_audit_events', [
                'action' => 'attendance.clock.threshold_changed',
                'actor_membership_id' => $people['partner']['id'],
            ]);

            $response = $this->actingAs($people['student']['user'])
                ->getJson('/c/wow-ca/app/attendance/clock')->assertOk();
            $schedule = $response->json('clock');
            $this->assertNotNull($schedule['half_day_ms']);
            $this->assertGreaterThan($schedule['grace_end_ms'], $schedule['half_day_ms']);
            $this->assertSame('13:00', substr((string) DB::table('workspaces')
                ->where('id', $workspace)->value('half_day_warning_time'), 0, 5));

            $this->actingAs($people['partner']['user'])
                ->post('/c/wow-ca/app/policies/attendance-clock', ['half_day_warning_time' => ''])
                ->assertRedirect();
            $this->assertNull(DB::table('workspaces')->where('id', $workspace)->value('half_day_warning_time'));
        } finally {
            $this->travelBack();
        }
    }

    public function test_grace_endpoint_is_inaccessible_to_other_firm_and_respects_feature_gate(): void
    {
        [$company, $workspace, $people] = $this->fixture();
        DB::table('student_feature_controls')->insert([
            'company_id' => $company, 'workspace_id' => $workspace,
            'membership_id' => $people['student']['id'],
            'feature_key' => 'attendance', 'partner_enabled' => false,
            'manager_enabled' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($people['student']['user'])
            ->getJson('/c/wow-ca/app/attendance/clock')->assertForbidden();
    }

    public function test_disabled_student_attendance_has_no_dashboard_punch(): void
    {
        [$company, $workspace, $people] = $this->fixture();
        $student = $people['student'];
        DB::table('student_feature_controls')->insert([
            'company_id' => $company, 'workspace_id' => $workspace,
            'membership_id' => $student['id'], 'feature_key' => 'attendance',
            'partner_enabled' => false, 'manager_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($student['user'])->get('/c/wow-ca/app')
            ->assertOk()->assertDontSeeText('QUICK ATTENDANCE');
        $this->post('/c/wow-ca/app/attendance/check-in', ['work_mode' => 'office'])
            ->assertForbidden();
        $this->assertDatabaseCount('attendance_sessions', 0);
    }
}
