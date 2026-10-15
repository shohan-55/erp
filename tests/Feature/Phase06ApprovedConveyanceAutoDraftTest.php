<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase06ApprovedConveyanceAutoDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_draft_uses_final_partner_approved_claim_without_salary_penalties_or_payment(): void
    {
        CarbonImmutable::setTestNow('2026-11-01 03:35:00 Asia/Dhaka');
        try {
            $company = DB::table('companies')->insertGetId([
                'slug' => 'synthetic-qa', 'name' => 'Synthetic QA Only',
                'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00',
                'office_end' => '18:00:00', 'grace_minutes' => 10,
                'weekly_holidays' => '[5,6]', 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $workspace = DB::table('workspaces')->insertGetId([
                'company_id' => $company, 'slug' => 'partner-qa',
                'name' => 'QA Partner Workspace', 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (['partner', 'student'] as $role) {
                $user = User::factory()->create();
                $memberId = DB::table('memberships')->insertGetId([
                    'company_id' => $company, 'workspace_id' => $workspace,
                    'user_id' => $user->id, 'role' => $role, 'active' => true,
                    'monthly_allowance' => $role === 'student' ? '8000.00' : '0.00',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($role === 'student') {
                    $studentId = $memberId;
                }
            }
            DB::table('conveyance_claims')->insert([
                'company_id' => $company, 'workspace_id' => $workspace,
                'membership_id' => $studentId, 'journey_date' => '2026-10-14',
                'journey_route' => 'Synthetic office to QA client',
                'purpose' => 'Sample travel test',
                'amount' => '130.00', 'approved_amount' => '100.00',
                'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->artisan('erp:payroll:auto-draft')->assertExitCode(0);
            $this->artisan('erp:payroll:auto-draft')->assertExitCode(0);
            $this->assertDatabaseCount('payroll_runs', 1);
            $this->assertDatabaseCount('payroll_lines', 1);
            $this->assertDatabaseHas('payroll_lines', [
                'membership_id' => $studentId,
                'base_allowance' => 8000, 'approved_claims' => 100,
                'adjustment' => 0, 'total_amount' => 8100,
            ]);
            $this->assertDatabaseHas('payroll_runs', [
                'workspace_id' => $workspace, 'status' => 'draft',
                'paid_at' => null, 'payment_reference' => null,
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
