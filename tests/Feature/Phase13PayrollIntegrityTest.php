<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PayrollIntegrity;
use App\Support\PayrollReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class Phase13PayrollIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function firm(string $slug='phase13'): array
    {
        $company=DB::table('companies')->insertGetId([
            'slug'=>$slug,'name'=>'Partner Payroll QA',
            'timezone'=>'Asia/Dhaka','active'=>true,
            'office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $workspace=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'name'=>'Own Practice','slug'=>'practice',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $users=[];
        foreach(['partner','manager','student'] as $role) {
            $user=User::factory()->create();
            $id=DB::table('memberships')->insertGetId([
                'company_id'=>$company,'workspace_id'=>$workspace,
                'user_id'=>$user->id,'role'=>$role,'active'=>true,
                'monthly_allowance'=>$role==='student'?'8000.00':'0.00',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $users[$role]=(object)['id'=>$id,'user'=>$user];
        }
        DB::table('memberships')->where('id',$users['student']->id)
            ->update(['manager_membership_id'=>$users['manager']->id]);
        return compact('company','workspace','users','slug');
    }

    private function draft(array $f): int
    {
        $this->actingAs($f['users']['partner']->user)
            ->post('/c/'.$f['slug'].'/app/payroll',['month'=>'2026-10'])
            ->assertRedirect()->assertSessionHasNoErrors();
        return (int)DB::table('payroll_runs')->where('workspace_id',$f['workspace'])
            ->value('id');
    }

    private function transition(array $f,int $run,string $action,array $extra=[])
    {
        return $this->post('/c/'.$f['slug'].'/app/payroll/'.$run.'/transition',[
            'action'=>$action,...$extra,
        ]);
    }

    private function attendance(array $f,?string $proxyStatus,bool $late=false): void
    {
        DB::table('attendance_sessions')->insert([
            'company_id'=>$f['company'],'workspace_id'=>$f['workspace'],
            'membership_id'=>$f['users']['student']->id,
            'work_date'=>'2026-10-12','work_mode'=>'office',
            'started_at'=>'2026-10-12 05:00:00',
            'ended_at'=>'2026-10-12 11:00:00',
            'late'=>$late,'holiday'=>false,
            'proxy_status'=>$proxyStatus,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function test_review_snapshot_freezes_exact_financial_components_and_real_actor(): void
    {
        $f=$this->firm();
        DB::table('conveyance_claims')->insert([
            'company_id'=>$f['company'],'workspace_id'=>$f['workspace'],
            'membership_id'=>$f['users']['student']->id,
            'journey_date'=>'2026-10-13','journey_route'=>'Office to client',
            'purpose'=>'Statutory audit','amount'=>'300.00','approved_amount'=>'225.50',
            'status'=>'approved','created_at'=>now(),'updated_at'=>now(),
        ]);
        $run=$this->draft($f);
        $this->transition($f,$run,'review',[
            'review_note'=>'Reviewed expense receipts and payroll adjustments against audit records.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $r=DB::table('payroll_runs')->find($run);
        $this->assertSame('reviewed',$r->status);
        $this->assertSame((int)$f['users']['partner']->id,(int)$r->reviewed_by);
        $this->assertNotNull($r->reviewed_at);
        $digest=json_decode($r->review_snapshot,true);
        $this->assertSame(1,$digest['line_count']);
        $this->assertSame(800000,$digest['base_cents']);
        $this->assertSame(22550,$digest['claims_cents']);
        $this->assertSame(822550,$digest['net_cents']);
        $this->assertCount(64,str_split($digest['sha256']));
        $this->assertSame($digest['sha256'],PayrollIntegrity::capture($r)['sha256']);
        $this->assertDatabaseHas('tenant_audit_events',[
            'action'=>'payroll.reviewed','entity_id'=>$run,
        ]);
        $this->get('/c/phase13/app/payroll')->assertOk()
            ->assertSeeText('Reviewed Financial Snapshot')
            ->assertSeeText('8,225.50');
        $this->transition($f,$run,'approve')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payroll_runs',[
            'id'=>$run,'status'=>'approved','approved_by'=>$f['users']['partner']->id,
        ]);
    }

    public function test_tampered_after_review_amounts_block_approval_without_changing_state(): void
    {
        $f=$this->firm();
        $run=$this->draft($f);
        $this->transition($f,$run,'review')->assertRedirect();
        DB::table('payroll_lines')->where('payroll_run_id',$run)->update([
            'base_allowance'=>'8500.00','total_amount'=>'8500.00',
        ]);
        $this->transition($f,$run,'approve')->assertSessionHasErrors('action');
        $this->assertDatabaseHas('payroll_runs',['id'=>$run,'status'=>'reviewed']);
        $this->assertDatabaseHas('payroll_lines',['payroll_run_id'=>$run,'total_amount'=>'8500.00']);
        DB::table('payroll_lines')->where('payroll_run_id',$run)->update([
            'base_allowance'=>'8000.00','total_amount'=>'8000.00',
        ]);
        $this->transition($f,$run,'approve')->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_drift_blocks_record_paid_and_does_not_leave_payment_evidence(): void
    {
        Storage::fake('local');
        $f=$this->firm();
        $run=$this->draft($f);
        $this->transition($f,$run,'review')->assertRedirect();
        $this->transition($f,$run,'approve')->assertRedirect();
        DB::table('payroll_lines')->where('payroll_run_id',$run)
            ->update(['base_allowance'=>'9000.00','total_amount'=>'9000.00']);
        $this->transition($f,$run,'record_paid',[
            'payment_reference'=>'EXTERNAL-13345',
            'payment_channel'=>'bank_transfer',
            'payment_date'=>now('Asia/Dhaka')->toDateString(),
            'reconciled_total'=>'9000.00',
            'payment_evidence'=>UploadedFile::fake()->create('bank.pdf',20,'application/pdf'),
        ])->assertSessionHasErrors('action');
        $this->assertDatabaseHas('payroll_runs',['id'=>$run,'status'=>'approved','paid_at'=>null]);
        $this->assertCount(0,Storage::disk('local')->allFiles('private-payroll-evidence'));
    }

    public function test_pending_and_rejected_proxy_are_separate_not_recorded_or_late(): void
    {
        $f=$this->firm();
        $this->attendance($f,'pending',true);
        $this->attendance($f,'rejected',true);
        $this->attendance($f,'verified',true);
        $this->attendance($f,null,false);
        $overview=PayrollReview::overview(
            DB::table('companies')->find($f['company']),
            DB::table('workspaces')->find($f['workspace']),'2026-10-01',
        );
        $this->assertSame(2,$overview['recorded_sessions']);
        $this->assertSame(1,$overview['late_sessions']);
        $this->assertSame(1,$overview['pending_proxy_sessions']);
        $this->assertSame(1,$overview['rejected_proxy_sessions']);
        $run=$this->draft($f);
        $this->get('/c/phase13/app/payroll')->assertOk()
            ->assertSeeText('Pending Proxy · Verify')
            ->assertSeeText('Rejected Proxy · Excluded');
        $this->transition($f,$run,'review')->assertRedirect();
        $snapshot=json_decode(DB::table('payroll_runs')->where('id',$run)
            ->value('review_snapshot'),true);
        $this->assertSame(1,$snapshot['review_signals']['pending_proxy_sessions']);
        $this->assertDatabaseHas('payroll_lines',[
            'payroll_run_id'=>$run,'adjustment'=>'0.00','total_amount'=>'8000.00',
        ]);
    }

    public function test_review_digest_preserves_negative_fractional_adjustments(): void
    {
        $this->assertSame(-50, \App\Support\PayrollCalculator::cents('-0.50'));
        $this->assertSame(-101, \App\Support\PayrollCalculator::cents('-1.01'));
        $this->assertSame(50, \App\Support\PayrollCalculator::cents('0.50'));
        $f=$this->firm();
        $run=$this->draft($f);
        $partner=$f['users']['partner']->user;
        $student=$f['users']['student']->id;
        $this->actingAs($partner)->post("/c/phase13/app/payroll/{$run}/adjustments",[
            'membership_id'=>$student,'kind'=>'bonus','amount'=>'25.75',
            'reason'=>'Approved balancing correction to this staff payroll.',
            'confirm_authorized'=>'1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post("/c/phase13/app/payroll/{$run}/adjustments",[
            'membership_id'=>$student,'kind'=>'deduction','amount'=>'26.25',
            'reason'=>'Authorized explicit deduction with supporting evidence.',
            'confirm_authorized'=>'1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payroll_lines',[
            'payroll_run_id'=>$run,'adjustment'=>'-0.50',
            'total_amount'=>'7999.50',
        ]);
        $this->transition($f,$run,'review')->assertRedirect()->assertSessionHasNoErrors();
        $snapshot=json_decode(DB::table('payroll_runs')->where('id',$run)
            ->value('review_snapshot'),true);
        $this->assertSame(-50,$snapshot['adjustment_cents']);
        $this->assertSame(799950,$snapshot['net_cents']);
        $this->transition($f,$run,'approve')->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_cross_workspace_manager_and_student_never_read_financial_snapshots(): void
    {
        $a=$this->firm('firm-a');
        $b=$this->firm('firm-b');
        $run=$this->draft($a);
        $this->transition($a,$run,'review')->assertRedirect();
        $this->actingAs($a['users']['manager']->user)
            ->get('/c/firm-a/app/payroll')->assertForbidden();
        $this->actingAs($a['users']['student']->user)
            ->get('/c/firm-a/app/payroll')->assertOk()
            ->assertDontSee('Reviewed Financial Snapshot');
        $this->actingAs($b['users']['partner']->user)
            ->post('/c/firm-b/app/payroll/'.$run.'/transition',['action'=>'approve'])
            ->assertNotFound();
        $this->assertDatabaseHas('payroll_runs',['id'=>$run,'status'=>'reviewed']);
    }
}
