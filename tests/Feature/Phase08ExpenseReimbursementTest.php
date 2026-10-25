<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase08ExpenseReimbursementTest extends TestCase
{
    use RefreshDatabase;

    private function begin(): void
    {
        Storage::fake('local');
        $clock=CarbonImmutable::parse('2026-10-18 14:00:00','Asia/Dhaka');
        Carbon::setTestNow($clock);
        CarbonImmutable::setTestNow($clock);
    }

    private function endClock(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    private function firm(): array
    {
        $company=DB::table('companies')->insertGetId([
            'slug'=>'example-ca','name'=>'An Independent CA Firm',
            'timezone'=>'Asia/Dhaka','office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $m=[];
        foreach(['a','b'] as $slug){
            $w=DB::table('workspaces')->insertGetId([
                'company_id'=>$company,'slug'=>'partner-'.$slug,
                'name'=>'Partner '.$slug,'active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach(['partner','manager','student'] as $role){
                $user=User::factory()->create(['name'=>$slug.'-'.$role]);
                $id=DB::table('memberships')->insertGetId([
                    'company_id'=>$company,'workspace_id'=>$w,
                    'user_id'=>$user->id,'role'=>$role,'active'=>true,
                    'manager_membership_id'=>$role==='student'?$m[$slug]['manager']->id:null,
                    'monthly_allowance'=>'8000.00','created_at'=>now(),'updated_at'=>now(),
                ]);
                $m[$slug][$role]=(object)['id'=>$id,'user'=>$user,'workspace'=>$w];
            }
        }
        return [$company,$m];
    }

    private function policy(array $overrides=[]): array
    {
        return [
            'expense_year'=>2026,'max_single_claim'=>'500.00',
            'monthly_student_limit'=>'700.00','submission_window_days'=>90,...$overrides,
        ];
    }

    private function submit(object $student,string $amount='250.00',string $category='transport',
        string $journey='2026-10-12',string $route='Office to Client A'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($student->user)->post('/c/example-ca/app/claims',[
            'expense_category'=>$category,'journey_date'=>$journey,'journey_route'=>$route,
            'purpose'=>'Transport and approved audit engagement work',
            'amount'=>$amount,
            'receipt'=>UploadedFile::fake()->createWithContent(
                'receipt.pdf','%PDF-1.4 unique amount '.$amount.' category '.$category.' route '.$route.' '.$journey),
        ]);
    }

    public function test_partner_specific_expense_policy_limits_and_budget_reservation(): void
    {
        $this->begin();
        try{
            [$company,$m]=$this->firm();
            $this->actingAs($m['a']['partner']->user)
                ->post('/c/example-ca/app/claims/policy',$this->policy())
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->submit($m['a']['student'],'600.00')->assertSessionHasErrors('amount');
            $this->submit($m['a']['student'],'450.00')->assertRedirect()->assertSessionHasNoErrors();
            $this->submit($m['a']['student'],'300.00','meals')->assertSessionHasErrors('amount');
            $this->submit($m['a']['student'],'250.00','meals')->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseCount('conveyance_claims',2);
            $this->assertDatabaseHas('conveyance_claims',[
                'workspace_id'=>$m['a']['student']->workspace,'expense_category'=>'meals',
                'amount'=>'250.00','status'=>'pending',
            ]);
            $this->actingAs($m['b']['partner']->user)
                ->get('/c/example-ca/app/claims')->assertOk()
                ->assertDontSeeText('Transport and approved audit engagement work');
            $this->assertDatabaseHas('workspace_expense_policies',[
                'workspace_id'=>$m['a']['partner']->workspace,'monthly_student_limit'=>'700.00',
            ]);
        }finally{$this->endClock();}
    }

    public function test_identical_receipt_cannot_be_submitted_twice_and_file_cleanup_is_safe(): void
    {
        $this->begin();
        try{
            [, $m]=$this->firm();
            $this->submit($m['a']['student'])->assertRedirect()->assertSessionHasNoErrors();
            $file=DB::table('conveyance_claims')->first()->receipt_path;
            Storage::disk('local')->assertExists($file);
            $this->submit($m['a']['student'])->assertSessionHasErrors('receipt');
            $this->assertDatabaseCount('conveyance_claims',1);
            Storage::disk('local')->assertExists($file);
            $this->assertSame(1,count(Storage::disk('local')->allFiles('claim-evidence')));
        }finally{$this->endClock();}
    }

    public function test_withdrawal_releases_budget_preserves_evidence_and_denies_other_user(): void
    {
        $this->begin();
        try{
            [, $m]=$this->firm();
            $this->actingAs($m['a']['partner']->user)
                ->post('/c/example-ca/app/claims/policy',$this->policy())->assertRedirect();
            $this->submit($m['a']['student'],'450.00')->assertRedirect();
            $claim=DB::table('conveyance_claims')->first();
            $url="/c/example-ca/app/claims/{$claim->id}/withdraw";
            $this->actingAs($m['b']['student']->user)->post($url)->assertNotFound();
            $this->actingAs($m['a']['manager']->user)->post($url)->assertForbidden();
            $this->actingAs($m['a']['student']->user)->post($url)->assertRedirect();
            $this->assertDatabaseHas('conveyance_claims',['id'=>$claim->id,'status'=>'withdrawn']);
            Storage::disk('local')->assertExists($claim->receipt_path);
            $this->submit($m['a']['student'],'450.00')->assertRedirect()->assertSessionHasNoErrors();
            $this->post($url)->assertSessionHasErrors('claim');
            $this->assertDatabaseHas('tenant_audit_events',['action'=>'expense.withdrawn','entity_id'=>$claim->id]);
        }finally{$this->endClock();}
    }

    public function test_manager_recommendation_and_partner_final_amount_are_separate_and_private(): void
    {
        $this->begin();
        try{
            [, $m]=$this->firm();
            $this->submit($m['a']['student'],'350.00')->assertRedirect();
            $claim=DB::table('conveyance_claims')->first();
            $route="/c/example-ca/app/claims/{$claim->id}/decision";
            $this->actingAs($m['b']['partner']->user)
                ->post($route,['decision'=>'approve'])->assertNotFound();
            $this->actingAs($m['a']['partner']->user)
                ->post($route,['decision'=>'approve'])->assertSessionHasErrors('decision');
            $this->actingAs($m['a']['manager']->user)
                ->post($route,['decision'=>'approve','approved_amount'=>'300.00',
                    'decision_note'=>'Original amount exceeds supported route.'])
                ->assertRedirect();
            $this->assertDatabaseHas('conveyance_claims',[
                'id'=>$claim->id,'status'=>'manager_reviewed',
                'manager_recommended_amount'=>'300.00','approved_amount'=>null,
            ]);
            $this->actingAs($m['a']['partner']->user)
                ->post($route,['decision'=>'approve','approved_amount'=>'280.00',
                    'decision_note'=>'Partner reduced claim after receipt verification.'])
                ->assertRedirect();
            $this->assertDatabaseHas('conveyance_claims',[
                'id'=>$claim->id,'status'=>'approved','approved_amount'=>'280.00',
            ]);
            $this->post($route,['decision'=>'approve'])->assertSessionHasErrors('decision');
        }finally{$this->endClock();}
    }

    public function test_expense_policy_is_partner_owned_and_cannot_rewrite_existing_claim_year(): void
    {
        $this->begin();
        try{
            [, $m]=$this->firm();
            $this->actingAs($m['a']['manager']->user)
                ->post('/c/example-ca/app/claims/policy',$this->policy())->assertForbidden();
            $this->actingAs($m['a']['partner']->user)
                ->post('/c/example-ca/app/claims/policy',$this->policy())->assertRedirect();
            $this->submit($m['a']['student'])->assertRedirect();
            $this->actingAs($m['a']['partner']->user)
                ->post('/c/example-ca/app/claims/policy',
                    $this->policy(['monthly_student_limit'=>'100000']))
                ->assertSessionHasErrors('expense_year');
            $this->assertDatabaseHas('workspace_expense_policies',[
                'workspace_id'=>$m['a']['partner']->workspace,'monthly_student_limit'=>'700.00',
            ]);
        }finally{$this->endClock();}
    }

    public function test_partner_only_csv_export_never_leaks_other_partner_records(): void
    {
        $this->begin();
        try{
            [, $m]=$this->firm();
            $this->submit($m['a']['student'],'200.00','transport','2026-10-12','Private Partner A Client')->assertRedirect();
            $this->submit($m['b']['student'],'250.00','lodging','2026-10-12','Confidential Partner B Client')->assertRedirect();
            $url='/c/example-ca/app/claims/export?month=2026-10';
            $this->actingAs($m['a']['manager']->user)->get($url)->assertForbidden();
            $this->actingAs($m['a']['student']->user)->get($url)->assertForbidden();
            $response=$this->actingAs($m['a']['partner']->user)->get($url)->assertOk();
            $content=$response->streamedContent();
            $this->assertStringContainsString('Private Partner A Client',$content);
            $this->assertStringNotContainsString('Confidential Partner B Client',$content);
            $this->assertDatabaseHas('tenant_audit_events',['action'=>'expense.export']);
        }finally{$this->endClock();}
    }

    public function test_payroll_uses_only_partner_authorized_claim_and_never_reserves_twice(): void
    {
        $this->begin();
        try{
            [$firm,$m]=$this->firm();
            $this->submit($m['a']['student'],'125.00')->assertRedirect();
            $claim=DB::table('conveyance_claims')->first();
            $url="/c/example-ca/app/claims/{$claim->id}/decision";
            $this->actingAs($m['a']['manager']->user)
                ->post($url,['decision'=>'approve'])->assertRedirect();
            $this->actingAs($m['a']['partner']->user)
                ->post($url,['decision'=>'approve'])->assertRedirect();
            $this->post('/c/example-ca/app/payroll',['month'=>'2026-10'])->assertRedirect();
            $run=DB::table('payroll_runs')->where('workspace_id',$m['a']['partner']->workspace)->first();
            $this->post("/c/example-ca/app/payroll/{$run->id}/transition",['action'=>'review'])
                ->assertRedirect();
            $this->assertDatabaseHas('conveyance_claims',[
                'id'=>$claim->id,'reserved_payroll_run_id'=>$run->id,
                'paid_payroll_run_id'=>null,'status'=>'approved',
            ]);
            $this->assertDatabaseHas('payroll_lines',[
                'payroll_run_id'=>$run->id,'membership_id'=>$m['a']['student']->id,
                'approved_claims'=>'125.00',
            ]);
            $this->post("/c/example-ca/app/payroll/{$run->id}/transition",['action'=>'review'])
                ->assertSessionHasErrors('action');
        }finally{$this->endClock();}
    }

    public function test_legacy_no_policy_claim_submission_remains_operational(): void
    {
        $this->begin();
        try{
            [, $m]=$this->firm();
            $this->actingAs($m['a']['student']->user)->post('/c/example-ca/app/claims',[
                'journey_date'=>'2026-10-12',
                'journey_route'=>'Firm office to statutory audit client',
                'purpose'=>'Audit fieldwork travel with supporting invoice',
                'amount'=>'325.25',
                'receipt'=>UploadedFile::fake()->create('legacy.pdf',20,'application/pdf'),
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('conveyance_claims',[
                'membership_id'=>$m['a']['student']->id,
                'expense_category'=>'transport','amount'=>'325.25',
            ]);
        }finally{$this->endClock();}
    }
}
