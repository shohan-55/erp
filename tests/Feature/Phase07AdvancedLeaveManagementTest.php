<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LeaveBalance;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase07AdvancedLeaveManagementTest extends TestCase
{
    use RefreshDatabase;

    private function frozenClock(): void
    {
        $now=CarbonImmutable::parse('2026-10-18 09:30:00','Asia/Dhaka');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }

    private function restoreClock(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    private function setupFirm(): array
    {
        $company=DB::table('companies')->insertGetId([
            'slug'=>'practice','name'=>'Independent Chartered Accountants',
            'timezone'=>'Asia/Dhaka','office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]','active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $users=[];
        foreach(['a','b'] as $slug) {
            $workspace=DB::table('workspaces')->insertGetId([
                'company_id'=>$company,'slug'=>'partner-'.$slug,
                'name'=>'Practice Unit '.$slug,'active'=>true,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach(['partner','manager','student'] as $role) {
                $user=User::factory()->create();
                $member=DB::table('memberships')->insertGetId([
                    'company_id'=>$company,'workspace_id'=>$workspace,
                    'user_id'=>$user->id,'role'=>$role,'active'=>true,
                    'monthly_allowance'=>0,
                    'manager_membership_id'=>$role==='student'?$users[$slug]['manager']->id:null,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                $users[$slug][$role]=(object)['user'=>$user,'id'=>$member,'workspace'=>$workspace];
            }
        }
        return [$company,$users];
    }

    private function policyPayload(int $year=2026,array $extra=[]): array
    {
        return [
            'leave_year'=>$year,'annual_days'=>'3.0','sick_days'=>'2.0',
            'casual_days'=>'2.0','emergency_days'=>'1.0',
            'carry_forward_limit'=>'2.0','annual_notice_days'=>0,
            'count_holidays'=>0,...$extra,
        ];
    }

    private function addPolicy(object $partner,int $year=2026,array $extra=[]): void
    {
        $this->actingAs($partner->user)->post('/c/practice/app/leave/policies',
            $this->policyPayload($year,$extra))->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    private function request(object $student,string $from='2026-11-02',
        string $to='2026-11-02',string $type='annual',string $portion='full'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($student->user)->post('/c/practice/app/leave',[
            'leave_type'=>$type,'day_portion'=>$portion,
            'date_from'=>$from,'date_to'=>$to,
            'reason'=>'A properly documented request for a personal appointment.',
        ]);
    }

    public function test_partner_year_policy_is_tenant_isolated_and_half_day_reserves_balance(): void
    {
        $this->frozenClock();
        try {
            [$firm,$m]=$this->setupFirm();
            $this->addPolicy($m['a']['partner']);
            $this->request($m['a']['student'],'2026-11-02','2026-11-02','annual','morning')
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('leave_requests',[
                'membership_id'=>$m['a']['student']->id,'day_portion'=>'morning','charge_days'=>0.5,
                'status'=>'pending',
            ]);
            $student=DB::table('memberships')->find($m['a']['student']->id);
            $balance=LeaveBalance::allowance($student,2026,'annual','2026-11-02');
            $this->assertSame(3.0,$balance['entitled']);
            $this->assertSame(0.5,$balance['reserved']);
            $this->assertSame(2.5,$balance['remaining']);
            $this->actingAs($m['a']['student']->user)->get('/c/practice/app/leave')
                ->assertOk()->assertSeeText('My 2026 Leave Balance')
                ->assertSeeText('Morning')->assertSeeText('2.5')
                ->assertDontSeeText('Practice Unit b');
            $this->actingAs($m['b']['partner']->user)
                ->get('/c/practice/app/leave')->assertOk()
                ->assertDontSeeText('A properly documented request for a personal appointment.');
            $this->actingAs($m['b']['partner']->user)
                ->post('/c/practice/app/leave/credits',[
                    'membership_id'=>$m['a']['student']->id,'leave_year'=>2026,
                    'leave_type'=>'compensatory','days'=>1,
                    'reason'=>'An out-of-team credit must be denied.',
                ])->assertNotFound();
        } finally { $this->restoreClock(); }
    }

    public function test_complementary_halves_are_allowed_but_duplicate_or_full_day_overlap_is_rejected(): void
    {
        $this->frozenClock();
        try {
            [, $m]=$this->setupFirm();
            $this->addPolicy($m['a']['partner']);
            $this->request($m['a']['student'],'2026-11-02','2026-11-02','annual','morning')->assertRedirect();
            $this->request($m['a']['student'],'2026-11-02','2026-11-02','casual','afternoon')
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->request($m['a']['student'],'2026-11-02','2026-11-02','annual','morning')
                ->assertSessionHasErrors('date_from');
            $this->request($m['a']['student'],'2026-11-02','2026-11-02','annual','full')
                ->assertSessionHasErrors('date_from');
            $this->request($m['a']['student'],'2026-11-02','2026-11-03','annual','afternoon')
                ->assertSessionHasErrors('day_portion');
            $this->assertDatabaseCount('leave_requests',2);
        } finally { $this->restoreClock(); }
    }

    public function test_reservations_prevent_overallocation_and_student_withdrawal_releases_days(): void
    {
        $this->frozenClock();
        try {
            [, $m]=$this->setupFirm();
            $this->addPolicy($m['a']['partner'],2026,['annual_days'=>1]);
            $this->request($m['a']['student'],'2026-11-02')->assertRedirect();
            $this->request($m['a']['student'],'2026-11-03')->assertSessionHasErrors('date_from');
            $id=(int)DB::table('leave_requests')->first()->id;
            $this->actingAs($m['a']['student']->user)
                ->post("/c/practice/app/leave/$id/withdraw")->assertRedirect();
            $this->assertDatabaseHas('leave_requests',['id'=>$id,'status'=>'withdrawn']);
            $this->request($m['a']['student'],'2026-11-03')->assertRedirect()
                ->assertSessionHasNoErrors();
            $this->actingAs($m['a']['student']->user)
                ->post("/c/practice/app/leave/$id/withdraw")->assertSessionHasErrors('leave');
        } finally { $this->restoreClock(); }
    }

    public function test_manager_then_partner_decides_finally_and_failed_approval_does_not_change_balance(): void
    {
        $this->frozenClock();
        try {
            [, $m]=$this->setupFirm();
            $this->addPolicy($m['a']['partner']);
            $this->request($m['a']['student'],'2026-11-02')->assertRedirect();
            $id=(int)DB::table('leave_requests')->first()->id;
            $url="/c/practice/app/leave/$id/decision";
            $this->actingAs($m['a']['partner']->user)
                ->post($url,['decision'=>'approve'])->assertSessionHasErrors('decision');
            $this->actingAs($m['a']['manager']->user)
                ->post($url,['decision'=>'approve'])->assertRedirect();
            $this->assertDatabaseHas('leave_requests',['id'=>$id,'status'=>'manager_reviewed']);
            $this->actingAs($m['b']['partner']->user)
                ->post($url,['decision'=>'approve'])->assertNotFound();
            $this->actingAs($m['a']['partner']->user)
                ->post($url,['decision'=>'approve'])->assertRedirect();
            $this->assertDatabaseHas('leave_requests',['id'=>$id,'status'=>'approved']);
            $this->post($url,['decision'=>'approve'])->assertSessionHasErrors('decision');
            $student=DB::table('memberships')->find($m['a']['student']->id);
            $balance=LeaveBalance::allowance($student,2026,'annual','2026-11-02');
            $this->assertSame(1.0,$balance['used']);
            $this->assertSame(0.0,$balance['reserved']);
        } finally { $this->restoreClock(); }
    }

    public function test_compensatory_credit_is_only_issued_by_partner_and_audited(): void
    {
        $this->frozenClock();
        try {
            [, $m]=$this->setupFirm();
            $this->addPolicy($m['a']['partner']);
            $form=[
                'membership_id'=>$m['a']['student']->id,'leave_year'=>2026,
                'leave_type'=>'compensatory','days'=>'1.5',
                'expires_on'=>'2026-12-31',
                'reason'=>'Approved extra client assignment compensatory off.',
            ];
            $this->actingAs($m['a']['manager']->user)
                ->post('/c/practice/app/leave/credits',$form)->assertForbidden();
            $this->actingAs($m['a']['partner']->user)
                ->post('/c/practice/app/leave/credits',$form)
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('leave_balance_adjustments',[
                'membership_id'=>$m['a']['student']->id,'leave_type'=>'compensatory',
                'days'=>1.5,'authorized_by'=>$m['a']['partner']->id,
            ]);
            $this->assertDatabaseHas('tenant_audit_events',['action'=>'leave.credit.granted']);
            $this->request($m['a']['student'],'2026-11-02','2026-11-02','compensatory','full')
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->request($m['a']['student'],'2026-11-03','2026-11-03','compensatory','full')
                ->assertSessionHasErrors('date_from');
        } finally { $this->restoreClock(); }
    }

    public function test_annual_notice_holidays_and_year_boundary_are_enforced(): void
    {
        $this->frozenClock();
        try {
            [, $m]=$this->setupFirm();
            $this->addPolicy($m['a']['partner'],2026,[
                'annual_notice_days'=>10,'annual_days'=>8,
            ]);
            $this->request($m['a']['student'],'2026-10-20')->assertSessionHasErrors('date_from');
            $this->request($m['a']['student'],'2026-11-06')->assertSessionHasErrors('date_from'); // Friday holiday
            $this->request($m['a']['student'],'2026-12-31','2027-01-01')->assertSessionHasErrors('date_to');
            $this->request($m['a']['student'],'2026-11-02','2026-11-03')->assertRedirect();
            $this->assertDatabaseHas('leave_requests',[
                'membership_id'=>$m['a']['student']->id,'charge_days'=>2.0,
            ]);
        } finally { $this->restoreClock(); }
    }

    public function test_carry_forward_is_capped_by_new_partner_year_policy_and_prior_usage(): void
    {
        $this->frozenClock();
        try {
            [$company,$m]=$this->setupFirm();
            $partner=$m['a']['partner'];
            $this->addPolicy($partner,2026,['annual_days'=>5]);
            $this->addPolicy($partner,2027,[
                'annual_days'=>3,'carry_forward_limit'=>2,
            ]);
            DB::table('leave_requests')->insert([
                'company_id'=>$company,'workspace_id'=>$partner->workspace,
                'membership_id'=>$m['a']['student']->id,'leave_type'=>'annual',
                'date_from'=>'2026-11-02','date_to'=>'2026-11-04',
                'day_portion'=>'full','charge_days'=>3,
                'reason'=>'Historical approved annual leave',
                'status'=>'approved','created_at'=>now(),'updated_at'=>now(),
            ]);
            $student=DB::table('memberships')->find($m['a']['student']->id);
            $next=LeaveBalance::allowance($student,2027,'annual','2027-01-04');
            $this->assertSame(2.0,$next['carry']);
            $this->assertSame(5.0,$next['entitled']);
            $this->assertSame(5.0,$next['remaining']);
        } finally { $this->restoreClock(); }
    }

    public function test_frozen_payroll_and_policy_revision_history_cannot_be_mutated(): void
    {
        $this->frozenClock();
        try {
            [$company,$m]=$this->setupFirm();
            $this->addPolicy($m['a']['partner']);
            $this->request($m['a']['student'],'2026-11-02')->assertRedirect();
            $this->actingAs($m['a']['partner']->user)->post('/c/practice/app/leave/policies',
                $this->policyPayload(2026,['annual_days'=>100]))->assertSessionHasErrors('leave_year');
            $this->assertDatabaseHas('workspace_leave_policies',[
                'workspace_id'=>$m['a']['partner']->workspace,'annual_days'=>3,
            ]);
            DB::table('payroll_runs')->insert([
                'company_id'=>$company,'workspace_id'=>$m['a']['partner']->workspace,
                'period_month'=>'2026-11-01','status'=>'locked','policy_snapshot'=>'{}',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->request($m['a']['student'],'2026-11-03')->assertSessionHasErrors('date_from');
            $id=(int)DB::table('leave_requests')->first()->id;
            $this->actingAs($m['a']['manager']->user)
                ->post("/c/practice/app/leave/$id/decision",['decision'=>'approve'])->assertRedirect();
            $this->actingAs($m['a']['partner']->user)
                ->post("/c/practice/app/leave/$id/decision",['decision'=>'approve'])
                ->assertSessionHasErrors('date_from');
            $this->assertDatabaseHas('leave_requests',['id'=>$id,'status'=>'manager_reviewed']);
        } finally { $this->restoreClock(); }
    }

    public function test_legacy_full_day_requests_still_work_without_new_policy_or_portion(): void
    {
        $this->frozenClock();
        try {
            [, $m]=$this->setupFirm();
            $this->actingAs($m['a']['student']->user)->post('/c/practice/app/leave',[
                'leave_type'=>'annual','date_from'=>'2026-11-02',
                'date_to'=>'2026-11-02','reason'=>'Legacy full-day application.',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('leave_requests',[
                'membership_id'=>$m['a']['student']->id,'day_portion'=>'full',
                'charge_days'=>1,
            ]);
        } finally { $this->restoreClock(); }
    }
}
