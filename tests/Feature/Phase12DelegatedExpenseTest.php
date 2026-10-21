<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class Phase12DelegatedExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function firm(string $slug='expense-phase12'): array
    {
        $company=DB::table('companies')->insertGetId([
            'name'=>'Independent Accounting Firm','slug'=>$slug,
            'timezone'=>'Asia/Dhaka','active'=>true,'office_start'=>'10:00:00',
            'office_end'=>'18:00:00','grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]','created_at'=>now(),'updated_at'=>now(),
        ]);
        $ws=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'name'=>'Practice','slug'=>'practice',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $people=[];
        foreach (['partner','manager','student','other'] as $role) {
            $user=User::factory()->create();
            $id=DB::table('memberships')->insertGetId([
                'company_id'=>$company,'workspace_id'=>$ws,
                'user_id'=>$user->id,'role'=>$role==='other'?'student':$role,
                'active'=>true,'monthly_allowance'=>'8000.00',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $people[$role]=(object)['id'=>$id,'user'=>$user];
        }
        DB::table('memberships')->where('id',$people['student']->id)->update([
            'manager_membership_id'=>$people['manager']->id,
        ]);
        return compact('company','ws','people','slug');
    }

    private function grant(array $f,array $rights): int
    {
        return DB::table('workspace_access_grants')->insertGetId([
            'company_id'=>$f['company'],'workspace_id'=>$f['ws'],
            'membership_id'=>$f['people']['manager']->id,
            'granted_by'=>$f['people']['partner']->id,
            'access_role'=>'moderator','delegation_mode'=>'limited',
            'permissions'=>json_encode($rights,JSON_THROW_ON_ERROR),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function submit(array $f,string $receipt='original-001',array $extra=[])
    {
        return $this->post('/c/'.$f['slug'].'/app/claims/delegated',[
            'membership_id'=>$f['people']['student']->id,
            'journey_date'=>now('Asia/Dhaka')->subDay()->toDateString(),
            'journey_route'=>'Office to statutory audit client',
            'purpose'=>'Attended fieldwork to verify client accounting records.',
            'expense_category'=>'transport','amount'=>'350.00',
            'delegation_reason'=>'Student confirmed the journey and provided a receipt for submission.',
            'receipt'=>UploadedFile::fake()->createWithContent('receipt.pdf',
                '%PDF-1.4 Audit receipt '.$receipt),
            ...$extra,
        ]);
    }

    public function test_explicit_right_and_student_identity_are_required_and_receipt_is_private(): void
    {
        $f=$this->firm();
        $this->actingAs($f['people']['manager']->user)->submit($f)->assertForbidden();
        $this->actingAs($f['people']['student']->user)->submit($f)->assertForbidden();
        $this->actingAs($f['people']['partner']->user)->submit($f)->assertForbidden();
        $this->assertDatabaseCount('conveyance_claims',0);
        $this->grant($f,['claim.submit_for']);
        $this->actingAs($f['people']['manager']->user)
            ->get('/c/expense-phase12/app/claims')
            ->assertOk()->assertSee('Submit Expense for an Assigned Student');
        $this->submit($f)->assertRedirect()->assertSessionHasNoErrors();
        $row=DB::table('conveyance_claims')->firstOrFail();
        $this->assertSame((int)$f['people']['student']->id,(int)$row->membership_id);
        $this->assertSame((int)$f['people']['manager']->id,(int)$row->initiated_by);
        $this->assertSame('partner_only',$row->approval_mode);
        $this->assertSame('pending',$row->status);
        $this->assertNotEmpty($row->delegation_reason);
        $this->assertStringContainsString('/'.$f['people']['student']->id.'/',$row->receipt_path);
        Storage::disk('local')->assertExists($row->receipt_path);
        $this->actingAs($f['people']['student']->user)
            ->get("/c/expense-phase12/app/claims/{$row->id}/receipt")->assertOk();
        $this->assertDatabaseHas('tenant_audit_events',[
            'entity_id'=>$row->id,'action'=>'claim.submit_for',
        ]);
        $this->assertDatabaseHas('tenant_notifications',[
            'recipient_membership_id'=>$f['people']['student']->id,
            'title'=>'Conveyance claim submitted on your behalf',
        ]);
    }

    public function test_manager_cannot_review_own_delegated_claim_even_under_manager_final_policy(): void
    {
        $f=$this->firm();
        $this->grant($f,['claim.submit_for']);
        DB::table('workspace_approval_policies')->insert([
            'company_id'=>$f['company'],'workspace_id'=>$f['ws'],
            'module'=>'conveyance','mode'=>'manager_final','active'=>true,
            'reason'=>'Regular direct claims use assigned Manager final.',
            'changed_by'=>$f['people']['partner']->id,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->actingAs($f['people']['manager']->user)
            ->submit($f)->assertRedirect()->assertSessionHasNoErrors();
        $row=DB::table('conveyance_claims')->firstOrFail();
        $this->assertSame('partner_only',$row->approval_mode);
        $url="/c/expense-phase12/app/claims/{$row->id}/decision";
        $this->post($url,['decision'=>'approve'])->assertSessionHasErrors('decision');
        $this->post($url,['decision'=>'reject'])->assertSessionHasErrors('decision');
        $this->assertDatabaseHas('conveyance_claims',['id'=>$row->id,'status'=>'pending']);
        $this->actingAs($f['people']['partner']->user)->post($url,[
            'decision'=>'approve','approved_amount'=>'320.00',
            'decision_note'=>'Reviewed receipt and reduced amount to documented cost.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('conveyance_claims',[
            'id'=>$row->id,'status'=>'approved','approved_amount'=>'320.00',
            'amount'=>'350.00','reviewed_by'=>$f['people']['partner']->id,
        ]);
        $this->assertDatabaseHas('workspace_approval_events',[
            'module'=>'conveyance','request_id'=>$row->id,
            'actor_membership_id'=>$f['people']['partner']->id,
            'new_status'=>'approved',
        ]);
    }

    public function test_assigned_students_only_and_foreign_workspace_receipts_are_private(): void
    {
        $a=$this->firm('expense-a');
        $b=$this->firm('expense-b');
        $this->grant($a,['claim.submit_for']);
        $this->actingAs($a['people']['manager']->user)
            ->submit($a,'not-my-student',[
                'membership_id'=>$a['people']['other']->id,
            ])->assertNotFound();
        $this->submit($a,'foreign-student',[
            'membership_id'=>$b['people']['student']->id,
        ])->assertNotFound();
        $this->assertDatabaseCount('conveyance_claims',0);
        $this->actingAs($b['people']['manager']->user)
            ->get('/c/expense-a/app/claims')->assertForbidden();
    }

    public function test_duplication_limit_and_frozen_payroll_apply_to_actual_student(): void
    {
        $f=$this->firm();
        $this->grant($f,['claim.submit_for']);
        $this->actingAs($f['people']['partner']->user)->post(
            '/c/expense-phase12/app/claims/policy',[
                'expense_year'=>(int)now('Asia/Dhaka')->year,
                'max_single_claim'=>'400.00','monthly_student_limit'=>'500.00',
                'submission_window_days'=>90,
            ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($f['people']['manager']->user)
            ->submit($f,'budget-receipt')->assertRedirect()->assertSessionHasNoErrors();
        $this->submit($f,'budget-receipt')->assertSessionHasErrors('receipt');
        $this->submit($f,'another-receipt')->assertSessionHasErrors('amount');
        $this->submit($f,'oversize',['amount'=>'450.00'])->assertSessionHasErrors('amount');
        $this->submit($f,'future',['journey_date'=>now('Asia/Dhaka')->addDay()->toDateString()])
            ->assertSessionHasErrors('journey_date');
        $this->assertDatabaseCount('conveyance_claims',1);
        $this->assertCount(1,Storage::disk('local')->allFiles('claim-evidence'));
        $period=now('Asia/Dhaka')->subDay()->format('Y-m-01');
        DB::table('payroll_runs')->insert([
            'company_id'=>$f['company'],'workspace_id'=>$f['ws'],
            'period_month'=>$period,'status'=>'locked',
            'policy_snapshot'=>'{}','created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->submit($f,'payroll-frozen')->assertSessionHasErrors('journey_date');
        $this->assertDatabaseCount('conveyance_claims',1);
    }

    public function test_owned_pending_withdrawal_needs_independent_live_permission_and_reason(): void
    {
        $f=$this->firm();
        $grant=$this->grant($f,['claim.submit_for']);
        $this->actingAs($f['people']['manager']->user)
            ->submit($f)->assertRedirect()->assertSessionHasNoErrors();
        $row=DB::table('conveyance_claims')->firstOrFail();
        $url="/c/expense-phase12/app/claims/{$row->id}/delegated-withdraw";
        $this->post($url,['reason'=>'The Student cancelled the client travel.'])
            ->assertForbidden();
        DB::table('workspace_access_grants')->where('id',$grant)->update([
            'permissions'=>json_encode(['claim.submit_for','claim.withdraw_for']),
        ]);
        $this->post($url,['reason'=>'too short'])->assertSessionHasErrors('reason');
        $this->post($url,[
            'reason'=>'The Student cancelled the client journey and notified the Manager.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('conveyance_claims',[
            'id'=>$row->id,'status'=>'withdrawn',
            'withdrawn_by'=>$f['people']['manager']->id,
        ]);
        Storage::disk('local')->assertExists($row->receipt_path);
        $this->assertDatabaseHas('tenant_audit_events',[
            'entity_id'=>$row->id,'action'=>'claim.withdrawn_for',
        ]);
        $this->post($url,['reason'=>'Trying to withdraw an already processed claim.'])
            ->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('conveyance_claims',1);
    }

    public function test_revocation_is_enforced_before_new_submission_or_withdrawal(): void
    {
        $f=$this->firm();
        $grant=$this->grant($f,['claim.submit_for','claim.withdraw_for']);
        $this->actingAs($f['people']['manager']->user)
            ->submit($f)->assertRedirect()->assertSessionHasNoErrors();
        $id=DB::table('conveyance_claims')->value('id');
        DB::table('workspace_access_grants')->where('id',$grant)->update([
            'revoked_at'=>now(),'revoked_by'=>$f['people']['partner']->id,
        ]);
        $this->submit($f,'after-revocation')->assertForbidden();
        $this->post("/c/expense-phase12/app/claims/{$id}/delegated-withdraw",[
            'reason'=>'Trying after the delegation was revoked by Partner.',
        ])->assertForbidden();
        $this->assertDatabaseCount('conveyance_claims',1);
        $this->assertDatabaseHas('conveyance_claims',['id'=>$id,'status'=>'pending']);
    }

    public function test_student_cannot_spoof_beneficiary_and_self_service_stays_operational(): void
    {
        $f=$this->firm();
        $student=$f['people']['student'];
        $url='/c/expense-phase12/app/claims';
        $input=[
            'journey_date'=>now('Asia/Dhaka')->subDay()->toDateString(),
            'journey_route'=>'Office to client site',
            'purpose'=>'Authorized work travel with receipt supplied',
            'amount'=>'125.00','receipt'=>UploadedFile::fake()->createWithContent(
                'student.pdf','%PDF-1.4 Student original receipt',
            ),
        ];
        $this->actingAs($student->user)->post($url,[
            ...$input,'membership_id'=>$f['people']['other']->id,
        ])->assertSessionHasErrors('membership_id');
        $this->post($url,$input)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('conveyance_claims',[
            'membership_id'=>$student->id,
            'initiated_by'=>$student->id,'approval_mode'=>'manager_partner',
            'amount'=>'125.00','status'=>'pending',
        ]);
    }
}
