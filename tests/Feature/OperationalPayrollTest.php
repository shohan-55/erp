<?php

namespace Tests\Feature;

use App\Models\AllowanceRate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\ConveyanceClaim;
use App\Models\PayrollClaimAllocation;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalPayrollTest extends TestCase
{
    use RefreshDatabase;

    private function firm(): array
    {
        $company = Company::create(['name'=>'Sample Firm','slug'=>'rac','timezone'=>'Asia/Dhaka']);
        $workspace = Workspace::create(['company_id'=>$company->id,'name'=>'Partner 1']);
        $other = Workspace::create(['company_id'=>$company->id,'name'=>'Partner 2']);
        return [$company,$workspace,$other];
    }

    private function member(Company $company, Workspace $workspace, string $role): array
    {
        $user=User::factory()->create();
        $m=CompanyMembership::create(['company_id'=>$company->id,'workspace_id'=>$workspace->id,
            'user_id'=>$user->id,'role'=>$role]);
        return [$user,$m];
    }

    private function rate(Company $company, Workspace $workspace, CompanyMembership $student, string $month='2026-10', string $amount='8000.00'): AllowanceRate
    {
        return AllowanceRate::create(['company_id'=>$company->id,'workspace_id'=>$workspace->id,
            'membership_id'=>$student->id,'effective_month'=>$month,'base_amount'=>$amount]);
    }

    private function claim(Company $company, Workspace $workspace, CompanyMembership $student, string $amount='225.50'): ConveyanceClaim
    {
        return ConveyanceClaim::create(['company_id'=>$company->id,'workspace_id'=>$workspace->id,
            'membership_id'=>$student->id,'journey_date'=>'2026-10-10','amount'=>$amount,
            'journey_route'=>'Office to Client','purpose'=>'Audit field travel','status'=>'approved']);
    }

    public function test_student_and_manager_cannot_generate_payroll(): void
    {
        [$company,$workspace]=$this->firm();
        [$student]=$this->member($company,$workspace,'student');
        [$manager]=$this->member($company,$workspace,'manager');
        foreach([$student,$manager] as $actor) {
            $this->actingAs($actor)->post('/app/c/rac/payroll/generate',['month'=>'2026-10'])->assertForbidden();
        }
        $this->assertDatabaseCount('payroll_runs',0);
        $this->actingAs($manager)->get('/app/c/rac/payroll')->assertForbidden();
    }

    public function test_missing_authorized_rate_blocks_entire_payroll_generation(): void
    {
        [$company,$workspace]=$this->firm();
        [$partner]=$this->member($company,$workspace,'partner');
        [$student]=$this->member($company,$workspace,'student');
        $this->actingAs($partner)->post('/app/c/rac/payroll/generate',['month'=>'2026-10'])->assertSessionHasErrors('month');
        $this->assertDatabaseCount('payroll_runs',0);
        $this->assertDatabaseCount('payroll_items',0);
    }

    public function test_monthly_draft_snapshots_allowances_and_allocates_claim_once(): void
    {
        [$company,$workspace]=$this->firm();
        [$partner]=$this->member($company,$workspace,'partner');
        [$student,$studentMembership]=$this->member($company,$workspace,'student');
        $this->rate($company,$workspace,$studentMembership,'2026-09','8000.00');
        $claim=$this->claim($company,$workspace,$studentMembership,'225.50');

        $this->actingAs($partner)->post('/app/c/rac/payroll/generate',['month'=>'2026-10'])->assertRedirect();
        $run=PayrollRun::firstOrFail();
        $item=PayrollItem::firstOrFail();
        $this->assertSame('draft',$run->status);
        $this->assertSame('8000.00',(string)$item->base_amount);
        $this->assertSame('225.50',(string)$item->approved_claim_total);
        $this->assertSame('8225.50',(string)$item->payable_amount);
        $this->assertSame('2026-09',$item->snapshot['rate_effective_month']);
        $this->assertSame(0,$item->snapshot['financial_lateness_deduction']);
        $this->assertSame($claim->id,PayrollClaimAllocation::firstOrFail()->conveyance_claim_id);
        $this->assertDatabaseCount('payroll_claim_allocations',1);
        $this->assertNull($claim->fresh()->paid_at);

        $this->actingAs($partner)->post('/app/c/rac/payroll/generate',['month'=>'2026-10'])->assertSessionHasErrors('month');
        $this->assertDatabaseCount('payroll_runs',1);
        $this->assertDatabaseCount('payroll_items',1);
    }

    public function test_payroll_status_machine_blocks_unauthorized_repeats_and_requires_payment_reference(): void
    {
        [$company,$workspace]=$this->firm();
        [$partner]=$this->member($company,$workspace,'partner');
        [$student,$membership]=$this->member($company,$workspace,'student');
        $this->rate($company,$workspace,$membership);
        $claim=$this->claim($company,$workspace,$membership);
        $this->actingAs($partner)->post('/app/c/rac/payroll/generate',['month'=>'2026-10']);
        $run=PayrollRun::firstOrFail();
        $url='/app/c/rac/payroll/'.$run->id.'/advance';

        $this->actingAs($partner)->post($url,['action'=>'mark_paid','payment_reference'=>'BANK-112'])->assertSessionHasErrors('action');
        $this->actingAs($partner)->post($url,['action'=>'review'])->assertRedirect();
        $this->actingAs($partner)->post($url,['action'=>'review'])->assertSessionHasErrors('action');
        $this->actingAs($partner)->post($url,['action'=>'approve'])->assertRedirect();
        $this->actingAs($partner)->post($url,['action'=>'mark_paid','payment_reference'=>'x'])->assertSessionHasErrors('payment_reference');
        $this->assertNull($run->fresh()->paid_at);
        $this->assertNull($claim->fresh()->paid_at);

        $this->actingAs($partner)->post($url,['action'=>'mark_paid','payment_reference'=>'BANK-202610-123'])->assertRedirect();
        $this->assertSame('paid',$run->fresh()->status);
        $this->assertSame('paid',$claim->fresh()->status);
        $this->assertNotNull($claim->fresh()->paid_at);
        $this->assertSame('BANK-202610-123',$run->fresh()->payment_reference);

        $this->actingAs($partner)->post($url,['action'=>'lock'])->assertRedirect();
        $this->assertSame('locked',$run->fresh()->status);
        $this->actingAs($partner)->post($url,['action'=>'mark_paid','payment_reference'=>'BANK-222'])->assertSessionHasErrors('action');
        $this->assertDatabaseCount('payroll_claim_allocations',1);
    }

    public function test_student_sees_only_personal_locked_items_not_drafts_or_other_students(): void
    {
        [$company,$workspace]=$this->firm();
        [$partner]=$this->member($company,$workspace,'partner');
        [$first,$firstMembership]=$this->member($company,$workspace,'student');
        [$second,$secondMembership]=$this->member($company,$workspace,'student');
        $this->rate($company,$workspace,$firstMembership,'2026-10','7000');
        $this->rate($company,$workspace,$secondMembership,'2026-10','9000');
        $this->actingAs($partner)->post('/app/c/rac/payroll/generate',['month'=>'2026-10'])->assertRedirect();
        $this->actingAs($first)->get('/app/c/rac/payroll')->assertOk()->assertDontSee('7000.00');

        $run=PayrollRun::firstOrFail();$url='/app/c/rac/payroll/'.$run->id.'/advance';
        $this->actingAs($partner)->post($url,['action'=>'review']);
        $this->actingAs($partner)->post($url,['action'=>'approve']);
        $this->actingAs($partner)->post($url,['action'=>'mark_paid','payment_reference'=>'VERIFIED-987']);
        $this->actingAs($partner)->post($url,['action'=>'lock']);

        $this->actingAs($first)->get('/app/c/rac/payroll')->assertOk()
            ->assertSee('7,000.00')->assertDontSee('9,000.00');
        $this->actingAs($second)->get('/app/c/rac/payroll')->assertOk()
            ->assertSee('9,000.00')->assertDontSee('7,000.00');
    }

    public function test_other_partner_cannot_read_or_advance_payroll(): void
    {
        [$company,$workspace,$otherWorkspace]=$this->firm();
        [$partner]=$this->member($company,$workspace,'partner');
        [$other]=$this->member($company,$otherWorkspace,'partner');
        [$student,$membership]=$this->member($company,$workspace,'student');
        $this->rate($company,$workspace,$membership);
        $this->actingAs($partner)->post('/app/c/rac/payroll/generate',['month'=>'2026-10']);
        $run=PayrollRun::firstOrFail();

        $this->actingAs($other)->get('/app/c/rac/payroll')->assertOk()->assertDontSee('8,000.00');
        $this->actingAs($other)->post('/app/c/rac/payroll/'.$run->id.'/advance',['action'=>'review'])->assertNotFound();
        $this->assertSame('draft',$run->fresh()->status);
    }
}
