<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AnnualHolidayCalendar;
use App\Support\DashboardMonthCalendar;
use App\Support\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnnualHolidayAndPremiumCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $slug='calendar-firm'): array
    {
        $id=DB::table('companies')->insertGetId([
            'slug'=>$slug,'name'=>'Test Company '.$slug,'timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $w=DB::table('workspaces')->insertGetId([
            'company_id'=>$id,'slug'=>'unit','name'=>'Partner Unit',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $people=[];
        foreach (['partner','manager','student'] as $role) {
            $user=User::factory()->create();
            $membership=DB::table('memberships')->insertGetId([
                'company_id'=>$id,'workspace_id'=>$w,'user_id'=>$user->id,
                'role'=>$role,'active'=>true,'monthly_allowance'=>'0.00',
                'manager_membership_id'=>$role==='student'?$people['manager']['id']:null,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $people[$role]=['id'=>$membership,'user'=>$user];
        }
        return [DB::table('companies')->find($id),DB::table('workspaces')->find($w),$people];
    }

    private function annual(string $csv='date,title,category'."\n".'2027-01-03,Annual Holiday,government'): array
    {
        return ['year'=>'2027','holidays_csv'=>$csv,
            'source_note'=>'Practice-verified holiday source document for the annual plan'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 11:00:00','Asia/Dhaka'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_manager_submission_is_pending_until_partner_approves_and_work_calendar_uses_same_holidays(): void
    {
        [$company,$workspace,$p]=$this->fixture();
        $this->actingAs($p['manager']['user'])->post('/c/calendar-firm/app/settings/annual-holidays',$this->annual())
            ->assertRedirect();
        $plan=DB::table('annual_holiday_plans')->first();
        $this->assertSame('pending',$plan->status);
        $this->assertFalse(WorkCalendar::forDate($company,$workspace,'2027-01-03',[5,6])['holiday']);

        $this->actingAs($p['partner']['user'])->post('/c/calendar-firm/app/settings/annual-holidays/'.$plan->id.'/decision',[
            'decision'=>'approve',
        ])->assertRedirect();
        $resolved=WorkCalendar::forDate($company,$workspace,'2027-01-03',[5,6]);
        $this->assertTrue($resolved['holiday']);
        $this->assertSame('annual_holiday_plan',$resolved['source']);
        $this->assertSame('Annual Holiday',$resolved['holiday_title']);
        $this->assertDatabaseHas('tenant_audit_events',[
            'action'=>'calendar.annual_approved','actor_membership_id'=>$p['partner']['id'],
        ]);
        $this->assertDatabaseCount('attendance_sessions',0);
        $this->assertDatabaseCount('payroll_line_adjustments',0);
    }

    public function test_one_day_approved_working_exception_overrides_annual_holiday(): void
    {
        [$company,$workspace,$p]=$this->fixture();
        $this->actingAs($p['partner']['user'])
            ->post('/c/calendar-firm/app/settings/annual-holidays',$this->annual())->assertRedirect();
        $this->assertTrue(WorkCalendar::forDate($company,$workspace,'2027-01-03',[5,6])['holiday']);

        DB::table('workspace_day_overrides')->insert([
            'company_id'=>$company->id,'workspace_id'=>$workspace->id,
            'work_date'=>'2027-01-03','day_type'=>'working','reason'=>'Special audit duty',
            'status'=>'approved','holiday_category'=>'client_duty',
            'authorized_by'=>$p['partner']['id'],'created_at'=>now(),'updated_at'=>now(),
        ]);
        $day=WorkCalendar::forDate($company,$workspace,'2027-01-03',[5,6]);
        $this->assertFalse($day['holiday']);
        $this->assertSame('workspace_override',$day['source']);
    }

    public function test_annual_revisions_do_not_retroactively_rewrite_past_approved_dates(): void
    {
        [$company,$workspace,$p]=$this->fixture();
        $this->actingAs($p['partner']['user'])
            ->post('/c/calendar-firm/app/settings/annual-holidays',$this->annual())->assertRedirect();
        $this->travelTo(CarbonImmutable::parse('2027-01-06 11:00','Asia/Dhaka'));
        $this->post('/c/calendar-firm/app/settings/annual-holidays',$this->annual(
            'date,title,category'."\n".'2027-02-02,Revised Holiday,partner_decision'))->assertRedirect();

        $old=AnnualHolidayCalendar::forDate($company,$workspace,'2027-01-03');
        $this->assertSame('Annual Holiday',$old['title']);
        $new=AnnualHolidayCalendar::forDate($company,$workspace,'2027-02-02');
        $this->assertSame('Revised Holiday',$new['title']);
        $this->assertDatabaseCount('annual_holiday_plans',2);
    }

    public function test_invalid_duplicates_past_dates_and_wrong_year_are_rejected(): void
    {
        [,,$p]=$this->fixture();
        $this->actingAs($p['manager']['user'])->post('/c/calendar-firm/app/settings/annual-holidays',$this->annual(
            '2027-03-01,Holiday 1,government'."\n".'2027-03-01,Holiday 2,government'))
            ->assertSessionHasErrors('holidays_csv');
        $this->post('/c/calendar-firm/app/settings/annual-holidays',$this->annual(
            '2026-10-10,Past date,government'))
            ->assertSessionHasErrors('holidays_csv');
        $this->post('/c/calendar-firm/app/settings/annual-holidays',$this->annual(
            '2028-04-01,Wrong year,government'))
            ->assertSessionHasErrors('holidays_csv');
        $this->assertDatabaseCount('annual_holiday_plans',0);
    }

    public function test_partner_only_can_change_watermark_and_it_uses_company_branding(): void
    {
        [$company,$workspace,$p]=$this->fixture();
        $this->actingAs($p['manager']['user'])
            ->post('/c/calendar-firm/app/settings/annual-holidays/branding',['mode'=>'off','opacity'=>9])
            ->assertForbidden();
        $this->actingAs($p['student']['user'])
            ->get('/c/calendar-firm/app/settings/annual-holidays')->assertForbidden();

        $this->actingAs($p['partner']['user'])
            ->post('/c/calendar-firm/app/settings/annual-holidays/branding',['mode'=>'name','opacity'=>5])
            ->assertRedirect();
        $this->assertDatabaseHas('workspaces',[
            'id'=>$workspace->id,'calendar_watermark_mode'=>'name','calendar_watermark_opacity'=>5,
        ]);
        $this->get('/c/calendar-firm/app')->assertOk()
            ->assertSee('erp-month-calendar__watermark',false)
            ->assertSeeText('Test Company calendar-firm')
            ->assertSee('data-calendar-day',false)
            ->assertSee('erp-month-calendar-style',false);
    }

    public function test_monthly_calendar_statuses_preserve_review_not_absence(): void
    {
        [$company,$workspace,$p]=$this->fixture();
        $actor=DB::table('memberships')->find($p['student']['id']);
        DB::table('attendance_sessions')->insert([
            'company_id'=>$company->id,'workspace_id'=>$workspace->id,
            'membership_id'=>$actor->id,'work_date'=>'2026-10-14',
            'work_mode'=>'office','started_at'=>'2026-10-14 04:05:00',
            'ended_at'=>'2026-10-14 10:00:00','late'=>true,'late_within_grace'=>true,
            'holiday'=>false,'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('leave_requests')->insert([
            'company_id'=>$company->id,'workspace_id'=>$workspace->id,
            'membership_id'=>$actor->id,'leave_type'=>'casual',
            'date_from'=>'2026-10-15','date_to'=>'2026-10-15',
            'reason'=>'Annual leave test','status'=>'approved',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $month=DashboardMonthCalendar::forActor($company,$workspace,$actor,true);
        $indexed=collect($month['days'])->keyBy('date');
        $this->assertSame('grace',$indexed['2026-10-14']['stage']);
        $this->assertSame('leave',$indexed['2026-10-15']['stage']);
        $this->assertSame('review',$indexed['2026-10-18']['stage']);
        $this->assertSame('today',$indexed['2026-10-19']['stage']);
        $this->assertSame('planned',$indexed['2026-10-20']['stage']);
        $this->assertNotContains('absent',collect($month['days'])->pluck('stage')->all());
    }

    public function test_tenant_isolation_and_payroll_frozen_dates_block_annual_approval(): void
    {
        [$company,$workspace,$p]=$this->fixture();
        [,,$other]=$this->fixture('other-firm');
        $this->actingAs($other['manager']['user'])
            ->post('/c/other-firm/app/settings/annual-holidays',$this->annual())->assertRedirect();
        $planId=DB::table('annual_holiday_plans')->first()->id;
        $this->actingAs($p['partner']['user'])
            ->post('/c/calendar-firm/app/settings/annual-holidays/'.$planId.'/decision',['decision'=>'approve'])
            ->assertNotFound();

        DB::table('payroll_runs')->insert([
            'company_id'=>$company->id,'workspace_id'=>$workspace->id,
            'period_month'=>'2027-01-01','status'=>'locked',
            'policy_snapshot'=>'{}','created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->post('/c/calendar-firm/app/settings/annual-holidays',$this->annual())
            ->assertSessionHasErrors('holidays_csv');
    }
}
