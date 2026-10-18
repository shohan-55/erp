<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase11AuditEngagementTimesheetsTest extends TestCase
{
    use RefreshDatabase;

    private function clock(): void
    {
        $now=CarbonImmutable::parse('2026-10-18 13:00:00','Asia/Dhaka');
        CarbonImmutable::setTestNow($now);
        Carbon::setTestNow($now);
    }

    private function resetClock(): void
    {
        CarbonImmutable::setTestNow();
        Carbon::setTestNow();
    }

    private function fixture(): array
    {
        $firm=DB::table('companies')->insertGetId([
            'slug'=>'client-practice','name'=>'Independent CA Firm',
            'timezone'=>'Asia/Dhaka','office_start'=>'10:00:00','office_end'=>'18:00:00',
            'weekly_holidays'=>'[5,6]','grace_minutes'=>10,
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $people=[];
        foreach(['a','b'] as $slug){
            $ws=DB::table('workspaces')->insertGetId([
                'company_id'=>$firm,'slug'=>'partner-'.$slug,'name'=>'Partner '.$slug,
                'active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach(['partner','manager','student'] as $role){
                $user=User::factory()->create(['name'=>$slug.'-'.$role]);
                $id=DB::table('memberships')->insertGetId([
                    'company_id'=>$firm,'workspace_id'=>$ws,'user_id'=>$user->id,
                    'role'=>$role,'monthly_allowance'=>0,'active'=>true,
                    'manager_membership_id'=>$role==='student'?$people[$slug]['manager']->id:null,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                $people[$slug][$role]=(object)['id'=>$id,'user'=>$user,'ws'=>$ws];
            }
        }
        return [$firm,$people];
    }

    private function makeClient(object $partner,string $code='ABC',string $name='Test Audit Client'): int
    {
        $this->actingAs($partner->user)->post('/c/client-practice/app/engagements/clients',[
            'code'=>$code,'name'=>$name,
            'industry'=>'Industrial Manufacturing',
            'contact_name'=>'Private Contact',
            'contact_email'=>'private@example.test',
        ])->assertRedirect()->assertSessionHasNoErrors();
        return (int)DB::table('audit_clients')->where('workspace_id',$partner->ws)
            ->where('code',$code)->value('id');
    }

    private function makeEngagement(object $partner,int $client,string $code='AUD2026'): int
    {
        $this->actingAs($partner->user)->post('/c/client-practice/app/engagements',[
            'audit_client_id'=>$client,'code'=>$code,
            'title'=>'Annual Statutory Financial Audit',
            'engagement_type'=>'statutory','period_end'=>'2026-06-30',
            'starts_on'=>'2026-09-01','ends_on'=>'2026-10-31',
            'budget_hours'=>'50.00',
        ])->assertRedirect()->assertSessionHasNoErrors();
        return (int)DB::table('audit_engagements')
            ->where('workspace_id',$partner->ws)->where('code',$code)->value('id');
    }

    private function assign(object $partner,int $engagement,int $staff,bool $active=true): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($partner->user)->post(
            "/c/client-practice/app/engagements/$engagement/assign",[
                'membership_id'=>$staff,'active'=>$active?1:0,
            ]);
    }

    private function submit(object $staff,int $engagement,string $hours='2.50',
        string $date='2026-10-15',string $description='Audit working paper testing and supporting procedures.'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($staff->user)->post('/c/client-practice/app/timesheets',[
            'engagement_id'=>$engagement,'work_date'=>$date,
            'hours'=>$hours,'billable'=>1,'work_description'=>$description,
        ]);
    }

    public function test_partner_registers_client_engagement_and_assigned_staff_only(): void
    {
        $this->clock();
        try {
            [, $p]=$this->fixture();
            $client=$this->makeClient($p['a']['partner']);
            $engagement=$this->makeEngagement($p['a']['partner'],$client);
            $this->assign($p['a']['partner'],$engagement,$p['a']['student']->id)
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('audit_clients',[
                'id'=>$client,'workspace_id'=>$p['a']['partner']->ws,'code'=>'ABC',
            ]);
            $this->assertDatabaseHas('audit_engagements',[
                'id'=>$engagement,'audit_client_id'=>$client,'status'=>'open',
            ]);
            $this->actingAs($p['a']['student']->user)
                ->get('/c/client-practice/app/engagements')->assertOk()
                ->assertSeeText('Annual Statutory Financial Audit')
                ->assertDontSeeText('Private Contact');
            $this->actingAs($p['b']['partner']->user)
                ->get('/c/client-practice/app/engagements')->assertOk()
                ->assertDontSeeText('Test Audit Client');
            $this->actingAs($p['a']['manager']->user)
                ->get('/c/client-practice/app/engagements')->assertOk()
                ->assertSeeText('Annual Statutory Financial Audit');
        } finally {$this->resetClock();}
    }

    public function test_client_engagement_and_assignment_cannot_cross_partner_boundaries(): void
    {
        $this->clock();
        try {
            [, $p]=$this->fixture();
            $client=$this->makeClient($p['a']['partner']);
            $engagement=$this->makeEngagement($p['a']['partner'],$client);
            $this->actingAs($p['b']['partner']->user)
                ->post('/c/client-practice/app/engagements',[
                    'audit_client_id'=>$client,'code'=>'B-AUD',
                    'title'=>'Attempt to use foreign Partner confidential client',
                    'engagement_type'=>'statutory','starts_on'=>'2026-10-01',
                    'ends_on'=>'2026-10-31','budget_hours'=>5,
                ])->assertNotFound();
            $this->actingAs($p['a']['partner']->user)
                ->post("/c/client-practice/app/engagements/$engagement/assign",[
                    'membership_id'=>$p['b']['student']->id,'active'=>1,
                ])->assertNotFound();
            $this->actingAs($p['b']['partner']->user)
                ->post("/c/client-practice/app/engagements/$engagement/close")->assertNotFound();
            $this->actingAs($p['a']['manager']->user)
                ->post('/c/client-practice/app/engagements/clients',[
                    'code'=>'NO','name'=>'Manager Cannot Create Client',
                ])->assertForbidden();
        } finally {$this->resetClock();}
    }

    public function test_students_cannot_log_unassigned_or_archived_engagement_and_dates_and_quarters_are_validated(): void
    {
        $this->clock();
        try {
            [, $p]=$this->fixture();
            $client=$this->makeClient($p['a']['partner']);
            $engagement=$this->makeEngagement($p['a']['partner'],$client);
            $this->submit($p['a']['student'],$engagement)->assertNotFound();
            $this->assign($p['a']['partner'],$engagement,$p['a']['student']->id)->assertRedirect();
            $this->submit($p['a']['student'],$engagement,'1.33')->assertSessionHasErrors('hours');
            $this->submit($p['a']['student'],$engagement,'1.00','2026-11-02')
                ->assertSessionHasErrors('work_date');
            $this->submit($p['a']['student'],$engagement,'1.00','2026-08-01')
                ->assertSessionHasErrors('work_date');
            $this->submit($p['a']['student'],$engagement,'2.25')->assertRedirect()
                ->assertSessionHasNoErrors();
            $this->assertDatabaseHas('audit_timesheets',[
                'engagement_id'=>$engagement,'hours'=>'2.25','status'=>'submitted',
            ]);
            $this->actingAs($p['a']['partner']->user)
                ->post("/c/client-practice/app/engagements/$engagement/close")
                ->assertSessionHasErrors('engagement');
            $this->assign($p['a']['partner'],$engagement,$p['a']['student']->id,false)
                ->assertSessionHasErrors('membership_id');
        } finally {$this->resetClock();}
    }

    public function test_multi_engagement_daily_hours_cap_is_enforced_atomically(): void
    {
        $this->clock();
        try {
            [, $p]=$this->fixture();
            $client=$this->makeClient($p['a']['partner']);
            $one=$this->makeEngagement($p['a']['partner'],$client);
            $two=$this->makeEngagement($p['a']['partner'],$client,'AUD2026-B');
            $this->assign($p['a']['partner'],$one,$p['a']['student']->id)->assertRedirect();
            $this->assign($p['a']['partner'],$two,$p['a']['student']->id)->assertRedirect();
            $this->submit($p['a']['student'],$one,'10.00')->assertRedirect();
            $this->submit($p['a']['student'],$two,'6.00')->assertRedirect()
                ->assertSessionHasNoErrors();
            $this->submit($p['a']['student'],$one,'0.25')
                ->assertSessionHasErrors('hours');
            $this->assertDatabaseCount('audit_timesheets',2);
            $this->assertDatabaseMissing('payroll_runs',['status'=>'paid']);
            $this->assertDatabaseCount('attendance_sessions',0);
        } finally {$this->resetClock();}
    }

    public function test_student_manager_partner_review_flow_and_nonrepeatable_final_approval(): void
    {
        $this->clock();
        try {
            [, $p]=$this->fixture();
            $client=$this->makeClient($p['a']['partner']);
            $engagement=$this->makeEngagement($p['a']['partner'],$client);
            $this->assign($p['a']['partner'],$engagement,$p['a']['student']->id)->assertRedirect();
            $this->submit($p['a']['student'],$engagement)->assertRedirect();
            $ts=DB::table('audit_timesheets')->first()->id;
            $url="/c/client-practice/app/timesheets/{$ts}/decision";
            $this->actingAs($p['a']['partner']->user)
                ->post($url,['decision'=>'approve'])->assertSessionHasErrors('decision');
            $this->actingAs($p['b']['manager']->user)
                ->post($url,['decision'=>'approve'])->assertNotFound();
            $this->actingAs($p['a']['manager']->user)
                ->post($url,['decision'=>'approve'])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('audit_timesheets',[
                'id'=>$ts,'status'=>'manager_reviewed',
                'manager_reviewed_by'=>$p['a']['manager']->id,
            ]);
            $this->actingAs($p['a']['partner']->user)
                ->post($url,['decision'=>'approve'])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('audit_timesheets',[
                'id'=>$ts,'status'=>'approved','decided_by'=>$p['a']['partner']->id,
            ]);
            $this->post($url,['decision'=>'approve'])->assertSessionHasErrors('decision');
            $this->assertDatabaseHas('tenant_audit_events',[
                'action'=>'audit.timesheet.approved','entity_id'=>$ts,
            ]);
            $this->assertDatabaseCount('payroll_runs',0);
        } finally {$this->resetClock();}
    }

    public function test_manager_own_hours_require_partner_and_manager_cannot_self_approve(): void
    {
        $this->clock();
        try {
            [, $p]=$this->fixture();
            $client=$this->makeClient($p['a']['partner']);
            $engagement=$this->makeEngagement($p['a']['partner'],$client);
            $this->assign($p['a']['partner'],$engagement,$p['a']['manager']->id)->assertRedirect();
            $this->submit($p['a']['manager'],$engagement,'3.00')->assertRedirect();
            $id=DB::table('audit_timesheets')->first()->id;
            $url="/c/client-practice/app/timesheets/{$id}/decision";
            $this->actingAs($p['a']['manager']->user)
                ->post($url,['decision'=>'approve'])->assertNotFound();
            $this->actingAs($p['a']['partner']->user)
                ->post($url,['decision'=>'approve'])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('audit_timesheets',['id'=>$id,'status'=>'approved']);
        } finally {$this->resetClock();}
    }

    public function test_withdrawal_releases_daily_hours_and_partner_closes_engagement_after_resolution(): void
    {
        $this->clock();
        try {
            [, $p]=$this->fixture();
            $client=$this->makeClient($p['a']['partner']);
            $engagement=$this->makeEngagement($p['a']['partner'],$client);
            $this->assign($p['a']['partner'],$engagement,$p['a']['student']->id)->assertRedirect();
            $this->submit($p['a']['student'],$engagement,'16.00')->assertRedirect();
            $ts=DB::table('audit_timesheets')->first()->id;
            $this->actingAs($p['b']['student']->user)
                ->post("/c/client-practice/app/timesheets/{$ts}/withdraw")->assertNotFound();
            $this->actingAs($p['a']['student']->user)
                ->post("/c/client-practice/app/timesheets/{$ts}/withdraw")->assertRedirect();
            $this->assertDatabaseHas('audit_timesheets',['id'=>$ts,'status'=>'withdrawn']);
            $this->submit($p['a']['student'],$engagement,'16.00')->assertRedirect()
                ->assertSessionHasNoErrors();
            $last=DB::table('audit_timesheets')->orderByDesc('id')->first()->id;
            $this->actingAs($p['a']['partner']->user)
                ->post("/c/client-practice/app/timesheets/{$last}/decision",[
                    'decision'=>'reject',
                ])->assertRedirect();
            $this->post("/c/client-practice/app/engagements/$engagement/close")->assertRedirect();
            $this->assertDatabaseHas('audit_engagements',['id'=>$engagement,'status'=>'closed']);
            $this->actingAs($p['a']['student']->user)
                ->post('/c/client-practice/app/timesheets',[
                    'engagement_id'=>$engagement,'work_date'=>'2026-10-15','hours'=>1,
                    'billable'=>1,'work_description'=>'Work cannot be logged to a closed engagement.',
                ])->assertNotFound();
            $this->actingAs($p['a']['partner']->user)
                ->post("/c/client-practice/app/engagements/clients/$client/archive")
                ->assertRedirect();
            $this->assertDatabaseHas('audit_clients',['id'=>$client,'active'=>false]);
        } finally {$this->resetClock();}
    }

    public function test_partner_only_csv_export_is_private_and_formula_injection_safe(): void
    {
        $this->clock();
        try {
            [, $p]=$this->fixture();
            $client=$this->makeClient($p['a']['partner'],'=X', 'Formula Unsafe Client');
            $engagement=$this->makeEngagement($p['a']['partner'],$client);
            $this->assign($p['a']['partner'],$engagement,$p['a']['student']->id)->assertRedirect();
            $this->submit($p['a']['student'],$engagement,'1.25','2026-10-15',
                '=HYPERLINK("private","audit") audit testing.')->assertRedirect();
            $url='/c/client-practice/app/timesheets/export?month=2026-10';
            $this->actingAs($p['a']['student']->user)->get($url)->assertForbidden();
            $this->actingAs($p['a']['manager']->user)->get($url)->assertForbidden();
            $result=$this->actingAs($p['a']['partner']->user)->get($url)->assertOk();
            $csv=$result->streamedContent();
            $this->assertStringContainsString("'=HYPERLINK",$csv);
            $this->assertStringNotContainsString('b-student',$csv);
            $this->assertDatabaseHas('tenant_audit_events',['action'=>'audit.timesheet.exported']);
        } finally {$this->resetClock();}
    }

    public function test_student_permission_gate_hides_engagements_and_direct_timesheets(): void
    {
        $this->clock();
        try {
            [$firm,$p]=$this->fixture();
            $student=$p['a']['student'];
            DB::table('student_feature_controls')->insert([
                'company_id'=>$firm,'workspace_id'=>$student->ws,
                'membership_id'=>$student->id,'feature_key'=>'timesheets',
                'partner_enabled'=>false,'manager_enabled'=>true,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->actingAs($student->user)
                ->get('/c/client-practice/app/engagements')->assertForbidden();
            $this->get('/c/client-practice/app/timesheets')->assertForbidden();
        } finally {$this->resetClock();}
    }
}
