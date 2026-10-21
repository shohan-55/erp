<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionPrivacyGateTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): array
    {
        $companyId = DB::table('companies')->insertGetId([
            'slug' => 'rac', 'name' => 'Sample CA Firm',
            'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00',
            'office_end' => '18:00:00', 'grace_minutes' => 10,
            'weekly_holidays' => '[5,6]', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspaceId = DB::table('workspaces')->insertGetId([
            'company_id' => $companyId, 'slug' => 'partner-01',
            'name' => 'Partner Unit 01', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$companyId, $workspaceId];
    }

    private function makeMember(int $companyId, int $workspaceId, string $role): array
    {
        $user = User::factory()->create();
        $memberId = DB::table('memberships')->insertGetId([
            'company_id' => $companyId, 'workspace_id' => $workspaceId,
            'user_id' => $user->id, 'role' => $role, 'active' => true,
            'monthly_allowance' => $role === 'student' ? '7500.00' : '0.00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$user, $memberId];
    }

    private function makeRun(int $companyId, int $workspaceId, string $status, string $period, int $studentId): int
    {
        $id = DB::table('payroll_runs')->insertGetId([
            'company_id' => $companyId, 'workspace_id' => $workspaceId,
            'period_month' => $period, 'status' => $status,
            'policy_snapshot' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payroll_lines')->insert([
            'payroll_run_id' => $id, 'membership_id' => $studentId,
            'base_allowance' => '7500.00', 'approved_claims' => '125.00',
            'adjustment' => '0.00', 'total_amount' => '7625.00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }

    public function test_student_cannot_view_draft_reviewed_approved_or_paid_payroll(): void
    {
        [$companyId, $workspaceId] = $this->makeCompany();
        [$user, $studentId] = $this->makeMember($companyId, $workspaceId, 'student');
        foreach (['draft', 'reviewed', 'approved', 'paid'] as $index => $status) {
            $this->makeRun($companyId, $workspaceId, $status, sprintf('2026-%02d-01', $index + 4), $studentId);
        }
        $this->actingAs($user)->get('/c/rac/app/payroll')
            ->assertOk()->assertSee('No payroll runs yet.')
            ->assertDontSee('7625.00')
            ->assertDontSee('2026-04-01');
    }

    public function test_student_sees_only_own_locked_statement(): void
    {
        [$companyId, $workspaceId] = $this->makeCompany();
        [$student, $studentId] = $this->makeMember($companyId, $workspaceId, 'student');
        [, $otherId] = $this->makeMember($companyId, $workspaceId, 'student');

        $draftId = $this->makeRun($companyId, $workspaceId, 'draft', '2026-08-01', $studentId);
        $lockedId = $this->makeRun($companyId, $workspaceId, 'locked', '2026-09-01', $studentId);
        DB::table('payroll_lines')->insert([
            'payroll_run_id' => $lockedId, 'membership_id' => $otherId,
            'base_allowance' => '999999.00', 'approved_claims' => '0.00',
            'adjustment' => '0.00', 'total_amount' => '999999.00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($student)->get('/c/rac/app/payroll?run='.$draftId)
            ->assertOk()->assertSee('2026-09-01')
            ->assertDontSee('2026-08-01')
            ->assertDontSee('999,999.00')
            ->assertSee('7,625.00');
    }

    public function test_managers_have_no_implicit_payroll_salary_permission(): void
    {
        [$companyId, $workspaceId] = $this->makeCompany();
        [$manager] = $this->makeMember($companyId, $workspaceId, 'manager');
        $this->actingAs($manager)->get('/c/rac/app/payroll')->assertForbidden();
    }

    public function test_partner_sees_own_workspace_payroll_draft(): void
    {
        [$companyId, $workspaceId] = $this->makeCompany();
        [$partner] = $this->makeMember($companyId, $workspaceId, 'partner');
        [, $studentId] = $this->makeMember($companyId, $workspaceId, 'student');
        $this->makeRun($companyId, $workspaceId, 'draft', '2026-08-01', $studentId);
        $this->actingAs($partner)->get('/c/rac/app/payroll')
            ->assertOk()->assertSee('2026-08-01')->assertSee('7,625.00');
    }
}
