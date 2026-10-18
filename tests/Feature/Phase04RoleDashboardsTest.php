<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase04RoleDashboardsTest extends TestCase
{
    use RefreshDatabase;

    private function firm(string $slug = 'audit-firm'): int
    {
        return DB::table('companies')->insertGetId([
            'slug' => $slug, 'name' => 'CA Firm '.$slug,
            'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00',
            'office_end' => '18:00:00', 'grace_minutes' => 10,
            'weekly_holidays' => '[5,6]', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function workspace(int $firm, string $name): int
    {
        return DB::table('workspaces')->insertGetId([
            'company_id' => $firm, 'slug' => $name, 'name' => $name,
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function member(int $firm, int $ws, string $role, ?int $manager = null): array
    {
        $user = User::factory()->create();
        $id = DB::table('memberships')->insertGetId([
            'company_id' => $firm, 'workspace_id' => $ws,
            'user_id' => $user->id, 'role' => $role,
            'manager_membership_id' => $manager,
            'active' => true, 'monthly_allowance' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$user, $id];
    }

    private function leave(int $firm, int $ws, int $student, string $status): void
    {
        DB::table('leave_requests')->insert([
            'company_id' => $firm, 'workspace_id' => $ws, 'membership_id' => $student,
            'leave_type' => 'annual', 'date_from' => '2026-11-12',
            'date_to' => '2026-11-13', 'reason' => 'Necessary leave for an appointment.',
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function claim(int $firm, int $ws, int $student, string $status): void
    {
        DB::table('conveyance_claims')->insert([
            'company_id' => $firm, 'workspace_id' => $ws, 'membership_id' => $student,
            'journey_date' => '2026-10-14', 'journey_route' => 'Office to Client',
            'purpose' => 'Audit assignment transport', 'amount' => '499.00',
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_partner_dashboard_shows_only_own_partner_queue_and_working_admin_links(): void
    {
        $firm = $this->firm();
        $own = $this->workspace($firm, 'partner-a');
        $other = $this->workspace($firm, 'partner-b');
        [$partner] = $this->member($firm, $own, 'partner');
        [, $ownStudent] = $this->member($firm, $own, 'student');
        [, $otherStudent] = $this->member($firm, $other, 'student');
        $this->leave($firm, $own, $ownStudent, 'manager_reviewed');
        $this->claim($firm, $own, $ownStudent, 'pending');
        for ($i = 0; $i < 4; $i++) {
            $this->leave($firm, $other, $otherStudent, 'pending');
            $this->claim($firm, $other, $otherStudent, 'pending');
        }

        $this->actingAs($partner)->get('/c/audit-firm/app')->assertOk()
            ->assertSeeText('PARTNER CONTROL CENTER')
            ->assertSeeText('Your practice. Your decisions.')
            ->assertSeeText('Review Leave')
            ->assertSeeText('Review Conveyance')
            ->assertSeeText('Team & Accounts')
            ->assertSeeText('Student Permissions')
            ->assertSeeText('Payroll Control')
            ->assertSeeText('1 open')
            ->assertDontSeeText('4 open')
            ->assertSee('/c/audit-firm/app/payroll')
            ->assertSee('/c/audit-firm/app/team')
            ->assertDontSee('499.00');
    }

    public function test_manager_dashboard_only_counts_assigned_students_and_never_advertises_financial_controls(): void
    {
        $firm = $this->firm();
        $w = $this->workspace($firm, 'partner-one');
        [$manager, $managerId] = $this->member($firm, $w, 'manager');
        [, $mine] = $this->member($firm, $w, 'student', $managerId);
        [, $notMine] = $this->member($firm, $w, 'student');
        $this->leave($firm, $w, $mine, 'pending');
        $this->leave($firm, $w, $notMine, 'pending');
        $this->claim($firm, $w, $mine, 'pending');
        $this->claim($firm, $w, $notMine, 'pending');

        $this->actingAs($manager)->get('/c/audit-firm/app')->assertOk()
            ->assertSeeText('MANAGER WORKSPACE')
            ->assertSeeText('Review Team Leave')
            ->assertSeeText('Review Team Claims')
            ->assertSeeText('Team Attendance')
            ->assertSeeText('1 open')
            ->assertDontSeeText('2 open')
            ->assertDontSeeText('Payroll Control')
            ->assertDontSeeText('Student Permissions')
            ->assertDontSee('/c/audit-firm/app/payroll');

        DB::table('memberships')->where('id', $managerId)
            ->update(['can_manage_student_features' => true]);
        $this->get('/c/audit-firm/app')->assertOk()->assertSeeText('Student Permissions');
    }

    public function test_student_dashboard_hides_unavailable_modules_even_in_quick_actions(): void
    {
        $firm = $this->firm();
        $w = $this->workspace($firm, 'partner-one');
        [$student, $studentId] = $this->member($firm, $w, 'student');
        DB::table('student_feature_controls')->insert([
            'company_id' => $firm, 'workspace_id' => $w,
            'membership_id' => $studentId, 'feature_key' => 'attendance',
            'partner_enabled' => false, 'manager_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('student_feature_controls')->insert([
            'company_id' => $firm, 'workspace_id' => $w,
            'membership_id' => $studentId, 'feature_key' => 'payslip',
            'partner_enabled' => false, 'manager_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($student)->get('/c/audit-firm/app')->assertOk()
            ->assertSeeText('STUDENT WORKSPACE')
            ->assertSeeText('Your workday, simplified.')
            ->assertSeeText('Apply for Leave')
            ->assertSeeText('Conveyance Claim')
            ->assertSeeText('Account Security')
            ->assertDontSeeText('My Attendance')
            ->assertDontSeeText('My Payslips')
            ->assertDontSeeText('QUICK ATTENDANCE')
            ->assertDontSee('/c/audit-firm/app/payroll');
        $this->get('/c/audit-firm/app/attendance')->assertForbidden();
    }

    public function test_dashboard_governance_does_not_expose_other_firms_to_partner_manager_student(): void
    {
        $first = $this->firm('one-ca');
        $second = $this->firm('other-ca');
        $w1 = $this->workspace($first, 'partner-a');
        $w2 = $this->workspace($second, 'secret-partner');
        [$partner] = $this->member($first, $w1, 'partner');
        [$manager] = $this->member($first, $w1, 'manager');
        [$student] = $this->member($first, $w1, 'student');
        [, $foreignStudent] = $this->member($second, $w2, 'student');
        $this->leave($second, $w2, $foreignStudent, 'pending');
        foreach ([$partner, $manager, $student] as $user) {
            $this->actingAs($user)->get('/c/one-ca/app')->assertOk()
                ->assertDontSee('secret-partner')
                ->assertDontSee('CA Firm other-ca');
            $this->get('/c/other-ca/app')->assertForbidden();
        }
    }

    public function test_dhakawala_platform_operator_dashboard_has_navigation_but_never_private_data(): void
    {
        $firm = $this->firm();
        $w = $this->workspace($firm, 'partner-one');
        [, $studentId] = $this->member($firm, $w, 'student');
        $this->leave($firm, $w, $studentId, 'pending');
        $this->claim($firm, $w, $studentId, 'pending');

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin)->get('/platform/app')->assertOk()
            ->assertSeeText('DHAKAWALA CONTROL CENTER')
            ->assertSeeText('Create CA Firm')
            ->assertSeeText('Manage Organizations')
            ->assertSeeText('Review Admin Activity')
            ->assertSee('id="create-firm"', false)
            ->assertSee('id="company-directory"', false)
            ->assertDontSeeText('Office to Client')
            ->assertDontSeeText('Necessary leave for an appointment.')
            ->assertDontSeeText('499.00');
    }
}
