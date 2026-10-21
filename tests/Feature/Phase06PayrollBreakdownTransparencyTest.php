<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase06PayrollBreakdownTransparencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_draft_shows_approved_claims_and_explicit_zero_adjustment_despite_late_check_in(): void
    {
        $company = DB::table('companies')->insertGetId([
            'slug' => 'qa-firm', 'name' => 'Synthetic QA Company',
            'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00',
            'office_end' => '18:00:00', 'grace_minutes' => 10,
            'weekly_holidays' => '[5,6]', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => 'partner-one',
            'name' => 'Synthetic Partner Workspace', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $partnerUser = User::factory()->create();
        DB::table('memberships')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $partnerUser->id, 'role' => 'partner',
            'monthly_allowance' => '0.00', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $studentUser = User::factory()->create();
        $studentId = DB::table('memberships')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $studentUser->id, 'role' => 'student',
            'monthly_allowance' => '8000.00', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('attendance_sessions')->insert([
            'company_id' => $company, 'workspace_id' => $workspace,
            'membership_id' => $studentId, 'work_date' => '2026-10-12',
            'work_mode' => 'office', 'started_at' => '2026-10-12 05:00:00',
            'ended_at' => '2026-10-12 10:00:00',
            'late' => true, 'holiday' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('conveyance_claims')->insert([
            'company_id' => $company, 'workspace_id' => $workspace,
            'membership_id' => $studentId, 'journey_date' => '2026-10-14',
            'journey_route' => 'Office to QA site',
            'purpose' => 'Synthetic audit travel',
            'amount' => '130.00', 'approved_amount' => '100.00',
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($partnerUser)
            ->post('/c/qa-firm/app/payroll', ['month' => '2026-10'])->assertRedirect();

        $this->assertDatabaseHas('payroll_lines', [
            'membership_id' => $studentId,
            'base_allowance' => 8000, 'approved_claims' => 100,
            'adjustment' => 0, 'total_amount' => 8100,
        ]);

        $this->get('/c/qa-firm/app/payroll')->assertOk()
            ->assertSee('Approved Claims')
            ->assertSee('Adjustment')
            ->assertSee('8,000.00')
            ->assertSee('100.00')
            ->assertSee('0.00')
            ->assertSee('8,100.00')
            ->assertSee('Drafts require manual Partner review.');
    }

    public function test_manager_still_cannot_view_draft_financial_breakdown(): void
    {
        $company = DB::table('companies')->insertGetId([
            'slug' => 'qa-firm', 'name' => 'Synthetic QA Company',
            'timezone' => 'Asia/Dhaka', 'weekly_holidays' => '[5,6]',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => 'partner-one', 'name' => 'Partner One',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $managerUser = User::factory()->create();
        DB::table('memberships')->insert([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $managerUser->id, 'role' => 'manager',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($managerUser)->get('/c/qa-firm/app/payroll')->assertForbidden();
    }
}
