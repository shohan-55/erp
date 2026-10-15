<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase08FinancialIntegrityCheckTest extends TestCase
{
    use RefreshDatabase;

    private function team(): array
    {
        $company = DB::table('companies')->insertGetId([
            'slug' => 'test-financial-integrity',
            'name' => 'Confidential QA Only',
            'timezone' => 'Asia/Dhaka',
            'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => 'test-team',
            'name' => 'Synthetic Test Team',
            'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create(['name' => 'Hidden Sensitive Test Member']);
        $membership = DB::table('memberships')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $user->id, 'role' => 'student',
            'monthly_allowance' => '8000.00',
            'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$company, $workspace, $membership];
    }

    public function test_clean_schema_reports_no_anomalies_without_writing_data(): void
    {
        $this->artisan('erp:integrity:check')
            ->expectsOutputToContain('Decision: NO_ANOMALIES_DETECTED')
            ->assertExitCode(0);
        $this->assertDatabaseCount('tenant_audit_events', 0);
        $this->assertDatabaseCount('payroll_runs', 0);
        $this->assertDatabaseCount('payroll_lines', 0);
    }

    public function test_legacy_approval_and_mismatched_payroll_are_flagged_without_disclosing_or_mutating_records(): void
    {
        [$company, $workspace, $membership] = $this->team();

        DB::table('conveyance_claims')->insert([
            [
                'company_id' => $company, 'workspace_id' => $workspace,
                'membership_id' => $membership, 'journey_date' => '2026-10-12',
                'journey_route' => 'Sensitive QA Route',
                'purpose' => 'Synthetic sample requested expense',
                'amount' => '130.00', 'approved_amount' => null,
                'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'company_id' => $company, 'workspace_id' => $workspace,
                'membership_id' => $membership, 'journey_date' => '2026-10-13',
                'journey_route' => 'Sensitive QA Route',
                'purpose' => 'Synthetic sample invalid approval',
                'amount' => '100.00', 'approved_amount' => '110.00',
                'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'company_id' => $company, 'workspace_id' => $workspace,
                'membership_id' => $membership, 'journey_date' => '2026-09-13',
                'journey_route' => 'Sensitive QA Route',
                'purpose' => 'Synthetic paid with missing link',
                'amount' => '90.00', 'approved_amount' => '90.00',
                'status' => 'paid', 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $paidRun = DB::table('payroll_runs')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'period_month' => '2026-10-01', 'status' => 'paid',
            'policy_snapshot' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payroll_lines')->insert([
            'payroll_run_id' => $paidRun, 'membership_id' => $membership,
            'base_allowance' => '8000.00', 'approved_claims' => '100.00',
            'adjustment' => '0.00', 'total_amount' => '8200.00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payroll_runs')->insert([
            'company_id' => $company, 'workspace_id' => $workspace,
            'period_month' => '2026-09-01', 'status' => 'draft',
            'policy_snapshot' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['2026-10-12 04:00:00', '2026-10-12 06:00:00'] as $startedAt) {
            DB::table('attendance_sessions')->insert([
                'company_id' => $company, 'workspace_id' => $workspace,
                'membership_id' => $membership, 'work_date' => '2026-10-12',
                'work_mode' => 'office', 'started_at' => $startedAt,
                'ended_at' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $exit = Artisan::call('erp:integrity:check', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('REVIEW_REQUIRED', $output);
        foreach ([
            'approved_claims_without_final_amount',
            'approved_claims_with_invalid_final_amount',
            'payroll_line_totals_mismatch',
            'paid_claims_without_paid_run_link',
            'settled_runs_missing_evidence',
            'payroll_runs_without_lines',
            'members_with_duplicate_open_sessions',
        ] as $finding) {
            $this->assertStringContainsString('"'.$finding.'": 1', $output);
        }

        $this->assertStringNotContainsString('Hidden Sensitive Test Member', $output);
        $this->assertStringNotContainsString('Sensitive QA Route', $output);
        $this->assertStringNotContainsString('8200.00', $output);

        $this->assertDatabaseCount('conveyance_claims', 3);
        $this->assertDatabaseCount('payroll_runs', 2);
        $this->assertDatabaseCount('payroll_lines', 1);
        $this->assertDatabaseCount('attendance_sessions', 2);
        $this->assertDatabaseHas('payroll_lines', ['payroll_run_id' => $paidRun, 'total_amount' => '8200.00']);
        $this->assertDatabaseCount('tenant_audit_events', 0);
    }
}
