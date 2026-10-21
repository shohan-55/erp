<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase10PartnerPayrollEngineTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $firm=DB::table('companies')->insertGetId([
            'slug'=>'pay-firm','name'=>'Independent CA Firm',
            'timezone'=>'Asia/Dhaka','office_start'=>'10:00:00',
            'office_end'=>'18:00:00','grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $people=[];
        foreach(['a','b'] as $slug){
            $ws=DB::table('workspaces')->insertGetId([
                'company_id'=>$firm,'slug'=>'practice-'.$slug,'name'=>'Practice '.$slug,
                'active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach(['partner','manager','student'] as $role){
                $user=User::factory()->create(['name'=>$slug.'-'.$role]);
                $id=DB::table('memberships')->insertGetId([
                    'company_id'=>$firm,'workspace_id'=>$ws,'user_id'=>$user->id,
                    'role'=>$role,'monthly_allowance'=>$role==='student'?'8000.00':'0.00',
                    'active'=>true,
                    'manager_membership_id'=>$role==='student'?$people[$slug]['manager']->id:null,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                $people[$slug][$role]=(object)['user'=>$user,'id'=>$id,'ws'=>$ws];
            }
        }
        return [$firm,$people];
    }

    private function draft(object $partner,string $month='2026-10'): int
    {
        $this->actingAs($partner->user)
            ->post('/c/pay-firm/app/payroll',['month'=>$month])
            ->assertRedirect()->assertSessionHasNoErrors();
        return (int)DB::table('payroll_runs')->where('workspace_id',$partner->ws)
            ->where('period_month',$month.'-01')->value('id');
    }

    private function adjust(int $run,int $student,string $kind,string $amount,
        string $reason='Partner-authorized discretionary payroll correction.'): \Illuminate\Testing\TestResponse
    {
        return $this->post("/c/pay-firm/app/payroll/{$run}/adjustments",[
            'membership_id'=>$student,'kind'=>$kind,'amount'=>$amount,
            'reason'=>$reason,'confirm_authorized'=>'1',
        ]);
    }

    public function test_manual_and_scheduled_drafts_share_effective_salary_and_approved_expenses(): void
    {
        CarbonImmutable::setTestNow('2026-11-01 03:35:00 Asia/Dhaka');
        try{
            [$firm,$p]=$this->fixture();
            foreach(['a','b'] as $slug){
                DB::table('staff_compensation_changes')->insert([
                    'company_id'=>$firm,'workspace_id'=>$p[$slug]['partner']->ws,
                    'membership_id'=>$p[$slug]['student']->id,
                    'effective_month'=>'2026-10-01','old_amount'=>'8000.00',
                    'new_amount'=>'9500.00','reason'=>'Approved salary progression.',
                    'authorized_by'=>$p[$slug]['partner']->id,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                DB::table('conveyance_claims')->insert([
                    'company_id'=>$firm,'workspace_id'=>$p[$slug]['partner']->ws,
                    'membership_id'=>$p[$slug]['student']->id,
                    'journey_date'=>'2026-10-13','journey_route'=>'Client site',
                    'purpose'=>'Audit visit','amount'=>'300.00','approved_amount'=>'225.50',
                    'status'=>'approved','created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            $manual=$this->draft($p['a']['partner']);
            $this->artisan('erp:payroll:auto-draft')
                ->expectsOutputToContain('Monthly payroll drafts created: 1')->assertExitCode(0);
            $auto=DB::table('payroll_runs')->where('workspace_id',$p['b']['student']->ws)->first()->id;
            foreach([[$manual,$p['a']['student']->id],[$auto,$p['b']['student']->id]] as [$run,$member]){
                $this->assertDatabaseHas('payroll_lines',[
                    'payroll_run_id'=>$run,'membership_id'=>$member,
                    'base_allowance'=>'9500.00','approved_claims'=>'225.50',
                    'adjustment'=>'0.00','total_amount'=>'9725.50',
                ]);
            }
            $this->assertDatabaseCount('payroll_runs',2);
            $this->assertDatabaseMissing('payroll_runs',['status'=>'paid']);
        } finally {CarbonImmutable::setTestNow();}
    }

    public function test_explicit_adjustments_are_audited_exactly_including_negative_50_cents_and_can_be_voided(): void
    {
        [$firm,$p]=$this->fixture();
        $run=$this->draft($p['a']['partner']);
        $student=$p['a']['student']->id;
        $this->adjust($run,$student,'bonus','25.75')->assertRedirect()->assertSessionHasNoErrors();
        $this->adjust($run,$student,'deduction','26.25')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payroll_lines',[
            'payroll_run_id'=>$run,'membership_id'=>$student,
            'adjustment'=>'-0.50','total_amount'=>'7999.50',
        ]);
        $id=DB::table('payroll_line_adjustments')->where('kind','deduction')->value('id');
        $this->assertDatabaseHas('tenant_audit_events',['action'=>'payroll.adjustment.authorized']);
        $this->post("/c/pay-firm/app/payroll/{$run}/adjustments/{$id}/void",[
            'reason'=>'Duplicate authorization cancelled after Partner reconciliation.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payroll_line_adjustments',['id'=>$id,'voided_by'=>$p['a']['partner']->id]);
        $this->assertDatabaseHas('payroll_lines',[
            'payroll_run_id'=>$run,'membership_id'=>$student,
            'adjustment'=>'25.75','total_amount'=>'8025.75',
        ]);
        $this->post("/c/pay-firm/app/payroll/{$run}/adjustments/{$id}/void",[
            'reason'=>'Repeat cancellation must be refused and audited.',
        ])->assertSessionHasErrors('reason');
    }

    public function test_other_partner_manager_and_student_cannot_change_payroll_or_read_private_register(): void
    {
        [, $p]=$this->fixture();
        $run=$this->draft($p['a']['partner']);
        $member=$p['a']['student']->id;
        $this->actingAs($p['b']['partner']->user)
            ->post("/c/pay-firm/app/payroll/{$run}/adjustments",[
                'membership_id'=>$member,'kind'=>'bonus','amount'=>'150.00',
                'reason'=>'Forbidden cross-partner financial operation.',
                'confirm_authorized'=>'1',
            ])->assertNotFound();
        $this->actingAs($p['a']['manager']->user)->post("/c/pay-firm/app/payroll/{$run}/adjustments",[
            'membership_id'=>$member,'kind'=>'bonus','amount'=>'150.00',
            'reason'=>'Managers have no implicit financial privileges.',
            'confirm_authorized'=>'1',
        ])->assertForbidden();
        $this->actingAs($p['a']['student']->user)
            ->get("/c/pay-firm/app/payroll/{$run}/register")->assertForbidden();
        $this->actingAs($p['b']['partner']->user)
            ->get("/c/pay-firm/app/payroll/{$run}/register")->assertNotFound();
        $this->assertDatabaseCount('payroll_line_adjustments',0);
    }

    public function test_review_freezes_adjustments_and_negative_net_is_forbidden(): void
    {
        [, $p]=$this->fixture();
        $run=$this->draft($p['a']['partner']);
        $member=$p['a']['student']->id;
        $this->adjust($run,$member,'deduction','9000.00')->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payroll_line_adjustments',0);
        $this->adjust($run,$member,'bonus','125.00')->assertRedirect();
        $this->post("/c/pay-firm/app/payroll/{$run}/transition",['action'=>'review'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payroll_runs',['id'=>$run,'status'=>'reviewed']);
        $this->assertDatabaseHas('payroll_lines',[
            'payroll_run_id'=>$run,'adjustment'=>'125.00','total_amount'=>'8125.00',
        ]);
        $this->adjust($run,$member,'bonus','100')->assertSessionHasErrors('amount');
        $id=DB::table('payroll_line_adjustments')->first()->id;
        $this->post("/c/pay-firm/app/payroll/{$run}/adjustments/{$id}/void",[
            'reason'=>'Attempted after review; immutable financial snapshot.',
        ])->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('payroll_lines',['payroll_run_id'=>$run,'total_amount'=>'8125.00']);
    }

    public function test_attendance_lateness_and_approved_leave_produce_review_flags_not_automatic_deductions(): void
    {
        [$firm,$p]=$this->fixture();
        $student=$p['a']['student'];$ws=$student->ws;
        DB::table('attendance_sessions')->insert([
            'company_id'=>$firm,'workspace_id'=>$ws,'membership_id'=>$student->id,
            'work_date'=>'2026-10-12','work_mode'=>'office',
            'started_at'=>'2026-10-12 05:00:00','ended_at'=>null,
            'late'=>true,'holiday'=>false,'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('leave_requests')->insert([
            'company_id'=>$firm,'workspace_id'=>$ws,'membership_id'=>$student->id,
            'leave_type'=>'sick','date_from'=>'2026-10-14','date_to'=>'2026-10-14',
            'reason'=>'Documented leave','status'=>'approved',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $run=$this->draft($p['a']['partner']);
        $this->get('/c/pay-firm/app/payroll')->assertOk()
            ->assertSeeText('Partner Payroll Review')
            ->assertSeeText('Open Sessions · Review')
            ->assertSeeText('Approved Leave');
        $this->assertDatabaseHas('payroll_lines',[
            'payroll_run_id'=>$run,'membership_id'=>$student->id,
            'adjustment'=>'0.00','total_amount'=>'8000.00',
        ]);
    }

    public function test_only_locked_statement_is_visible_to_own_student_and_payment_has_actual_evidence(): void
    {
        Storage::fake('local');
        [$firm,$p]=$this->fixture();
        $partner=$p['a']['partner'];$student=$p['a']['student'];
        $run=$this->draft($partner);
        $payslip="/c/pay-firm/app/payroll/{$run}/payslips/{$student->id}";
        $this->actingAs($student->user)->get($payslip)->assertNotFound();
        $this->actingAs($p['b']['student']->user)->get($payslip)->assertNotFound();
        $this->actingAs($p['a']['manager']->user)->get($payslip)->assertForbidden();
        $this->actingAs($partner->user)->get($payslip)->assertOk()
            ->assertSeeText('PREVIEW ONLY');
        $this->post("/c/pay-firm/app/payroll/{$run}/transition",['action'=>'review'])->assertRedirect();
        $this->post("/c/pay-firm/app/payroll/{$run}/transition",['action'=>'approve'])->assertRedirect();
        $this->post("/c/pay-firm/app/payroll/{$run}/transition",[
            'action'=>'record_paid','payment_reference'=>'PAY-UNIQUE-2026-334',
            'payment_channel'=>'bank_transfer','payment_date'=>now('Asia/Dhaka')->toDateString(),
            'reconciled_total'=>'8000.00',
            'payment_evidence'=>UploadedFile::fake()->create('confirmation.pdf',45,'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($student->user)->get($payslip)->assertNotFound();
        $this->actingAs($partner->user)
            ->post("/c/pay-firm/app/payroll/{$run}/transition",['action'=>'lock'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($student->user)->get($payslip)->assertOk()
            ->assertSeeText('Monthly Allowance / Salary')
            ->assertSeeText('8,000.00')->assertDontSeeText('PREVIEW ONLY');
        $this->actingAs($p['b']['partner']->user)->get($payslip)->assertNotFound();
        $this->assertDatabaseHas('payroll_runs',['id'=>$run,'status'=>'locked']);
    }

    public function test_partner_csv_is_workspace_scoped_and_formula_safe(): void
    {
        [, $p]=$this->fixture();
        $run=$this->draft($p['a']['partner']);
        $this->actingAs($p['a']['manager']->user)
            ->get("/c/pay-firm/app/payroll/{$run}/register")->assertForbidden();
        $this->actingAs($p['b']['partner']->user)
            ->get("/c/pay-firm/app/payroll/{$run}/register")->assertNotFound();
        $p['a']['student']->user->update(['name'=>'=HYPERLINK("private")']);
        $response=$this->actingAs($p['a']['partner']->user)
            ->get("/c/pay-firm/app/payroll/{$run}/register")->assertOk();
        $csv=$response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK",$csv);
        $this->assertStringNotContainsString('b-student',$csv);
        $this->assertDatabaseHas('tenant_audit_events',['action'=>'payroll.register.exported']);
    }
}
