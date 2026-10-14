<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RealErpAutomaticPayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_monthly_draft_is_idempotent_and_never_marks_payroll_paid(): void
    {
        CarbonImmutable::setTestNow('2026-11-01 03:35:00 Asia/Dhaka');
        try {
            $companyId = DB::table('companies')->insertGetId([
                'slug'=>'rac','name'=>'Example Firm','timezone'=>'Asia/Dhaka',
                'office_start'=>'10:00:00','office_end'=>'18:00:00',
                'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
                'active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $w = DB::table('workspaces')->insertGetId([
                'company_id'=>$companyId,'slug'=>'partner-1','name'=>'Partner Unit 01',
                'active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);

            foreach (['partner','student'] as $role) {
                $user = User::factory()->create();
                $memberId = DB::table('memberships')->insertGetId([
                    'company_id'=>$companyId,'workspace_id'=>$w,
                    'user_id'=>$user->id,'role'=>$role,'active'=>true,
                    'monthly_allowance'=>$role==='student' ? '8000.00':'0.00',
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                if ($role==='student') $student=$memberId;
            }

            DB::table('conveyance_claims')->insert([
                'company_id'=>$companyId,'workspace_id'=>$w,'membership_id'=>$student,
                'journey_date'=>'2026-10-15','journey_route'=>'Office to Client',
                'purpose'=>'Sample official audit travel expenses',
                'amount'=>'140.50','status'=>'approved',
                'created_at'=>now(),'updated_at'=>now(),
            ]);

            $this->artisan('erp:payroll:auto-draft')
                ->expectsOutputToContain('Monthly payroll drafts created: 1')
                ->assertExitCode(0);

            $this->artisan('erp:payroll:auto-draft')
                ->expectsOutputToContain('Monthly payroll drafts created: 0')
                ->assertExitCode(0);

            $this->assertDatabaseCount('payroll_runs',1);
            $this->assertDatabaseCount('payroll_lines',1);
            $this->assertDatabaseHas('payroll_runs',[
                'company_id'=>$companyId,'workspace_id'=>$w,
                'period_month'=>'2026-10-01','status'=>'draft',
                'payment_reference'=>null,'paid_at'=>null,
            ]);
            $this->assertDatabaseHas('payroll_lines',[
                'membership_id'=>$student,'base_allowance'=>8000,
                'approved_claims'=>140.5,'total_amount'=>8140.5,
            ]);
            $this->assertDatabaseHas('tenant_audit_events',[
                'action'=>'payroll.auto_draft','actor_membership_id'=>null,
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}