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
                'late' => 1, 'holiday' => 0, 'ended_at' => null,
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

    public function test_student_on_time_result_and_partner_denied_self_punch(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 10:05:00', 'Asia/Dhaka'));
        try {
            [, , $people] = $this->fixture();
            $this->actingAs($people['student']['user'])
                ->from('/c/wow-ca/app')
                ->post('/c/wow-ca/app/attendance/check-in', ['work_mode' => 'office'])
                ->assertRedirect('/c/wow-ca/app')
                ->assertSessionHas('attendance_result.late', false);
            $this->get('/c/wow-ca/app')->assertOk()
                ->assertSeeText('You are officially checked in!')
                ->assertDontSeeText('LATE ARRIVAL RECORDED');

            $this->actingAs($people['partner']['user'])
                ->post('/c/wow-ca/app/attendance/check-in', ['work_mode' => 'office'])
                ->assertForbidden();
            $this->assertDatabaseCount('attendance_sessions', 1);
        } finally {
            $this->travelBack();
        }
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
