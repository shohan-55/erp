<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\WorkCalendar;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase05SpecialCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function companyAndPartners(): array
    {
        $company = DB::table('companies')->insertGetId([
            'slug'=>'qa-pilot', 'name'=>'QA Calendar Company',
            'timezone'=>'Asia/Dhaka', 'office_start'=>'10:00:00',
            'office_end'=>'18:00:00','grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]','active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $workspaces=[]; $partners=[]; $students=[];
        foreach ([1,2] as $n) {
            $workspace=DB::table('workspaces')->insertGetId([
                'company_id'=>$company, 'slug'=>"partner-{$n}",
                'name'=>"Partner {$n}",'active'=>true,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $workspaces[]=$workspace;
            foreach (['partner','student'] as $role) {
                $user=User::factory()->create();
                $id=DB::table('memberships')->insertGetId([
                    'company_id'=>$company,'workspace_id'=>$workspace,
                    'user_id'=>$user->id,'role'=>$role,
                    'monthly_allowance'=>'0','active'=>true,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                if ($role==='partner') $partners[]=$user;
                else $students[]=$user;
            }
        }
        return [$company,$workspaces,$partners,$students];
    }

    private function nextFriday(): string
    {
        return CarbonImmutable::now('Asia/Dhaka')->next(Carbon::FRIDAY)->toDateString();
    }

    public function test_partner_working_day_overrides_holiday_only_in_own_workspace(): void
    {
        [$company,$workspaces,$partners,$students]=$this->companyAndPartners();
        $date=$this->nextFriday();

        $this->actingAs($partners[0])->post('/c/qa-pilot/app/policies/calendar/day', [
            'work_date'=>$date,
            'day_type'=>'working',
            'reason'=>'Approved special client filing support day',
        ])->assertRedirect();
        $this->assertDatabaseHas('workspace_day_overrides', [
            'company_id'=>$company,'workspace_id'=>$workspaces[0],
            'work_date'=>$date,'day_type'=>'working',
        ]);

        $firm=DB::table('companies')->where('id',$company)->first();
        $one=DB::table('workspaces')->where('id',$workspaces[0])->first();
        $two=DB::table('workspaces')->where('id',$workspaces[1])->first();
        $this->assertFalse(WorkCalendar::forDate($firm,$one,$date,[5,6])['holiday']);
        $this->assertTrue(WorkCalendar::forDate($firm,$two,$date,[5,6])['holiday']);

        $clock=CarbonImmutable::parse($date.' 10:20:00','Asia/Dhaka');
        CarbonImmutable::setTestNow($clock);
        Carbon::setTestNow($clock);
        try {
            $this->actingAs($students[0])
                ->post('/c/qa-pilot/app/attendance/check-in',['work_mode'=>'office'])
                ->assertRedirect();
            $this->actingAs($students[1])
                ->post('/c/qa-pilot/app/attendance/check-in',['work_mode'=>'office'])
                ->assertRedirect();
            $this->assertDatabaseHas('attendance_sessions',[
                'workspace_id'=>$workspaces[0], 'work_date'=>$date, 'holiday'=>0,
            ]);
            $this->assertDatabaseHas('attendance_sessions',[
                'workspace_id'=>$workspaces[1], 'work_date'=>$date, 'holiday'=>1,
            ]);
        } finally {
            CarbonImmutable::setTestNow();
            Carbon::setTestNow();
        }
    }

    public function test_manager_cannot_change_day_and_past_dates_are_denied(): void
    {
        [$company,$workspaces,$partners,$students]=$this->companyAndPartners();
        $date=$this->nextFriday();

        $this->actingAs($students[0])->post('/c/qa-pilot/app/policies/calendar/day', [
            'work_date'=>$date, 'day_type'=>'working',
            'reason'=>'Invalid unauthorized schedule change',
        ])->assertForbidden();

        $this->actingAs($partners[0])->post('/c/qa-pilot/app/policies/calendar/day', [
            'work_date'=>CarbonImmutable::now('Asia/Dhaka')->subDay()->toDateString(),
            'day_type'=>'working', 'reason'=>'Attempted past calendar rewrite',
        ])->assertSessionHasErrors('work_date');
        $this->assertDatabaseCount('workspace_day_overrides',0);
    }

    public function test_revisions_remain_in_history_and_latest_decision_wins(): void
    {
        [$company,$workspaces,$partners]=$this->companyAndPartners();
        $date=$this->nextFriday();
        $this->actingAs($partners[0]);
        $url='/c/qa-pilot/app/policies/calendar/day';

        $this->post($url, [
            'work_date'=>$date,'day_type'=>'working',
            'reason'=>'Client assignment officially requires attendance',
        ])->assertRedirect();

        $this->post($url, [
            'work_date'=>$date,'day_type'=>'working',
            'reason'=>'This duplicates the approved current day decision',
        ])->assertSessionHasErrors('day_type');

        $this->post($url, [
            'work_date'=>$date,'day_type'=>'holiday',
            'reason'=>'The planned client assignment has been cancelled',
        ])->assertRedirect();

        $this->assertDatabaseCount('workspace_day_overrides',2);
        $this->assertDatabaseHas('tenant_audit_events',[
            'company_id'=>$company,'action'=>'calendar.day_override',
        ]);
        $firm=DB::table('companies')->find($company);
        $workspace=DB::table('workspaces')->find($workspaces[0]);
        $this->assertTrue(WorkCalendar::forDate($firm,$workspace,$date,[5,6])['holiday']);
    }

    public function test_frozen_payroll_rejects_late_calendar_changes(): void
    {
        [$company,$workspaces,$partners]=$this->companyAndPartners();
        $date=$this->nextFriday();

        DB::table('payroll_runs')->insert([
            'company_id'=>$company, 'workspace_id'=>$workspaces[0],
            'period_month'=>substr($date,0,7).'-01',
            'status'=>'reviewed', 'policy_snapshot'=>'{}',
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->actingAs($partners[0])
            ->post('/c/qa-pilot/app/policies/calendar/day', [
                'work_date'=>$date,
                'day_type'=>'working',
                'reason'=>'Attempted change in frozen payroll period',
            ])->assertSessionHasErrors('work_date');
        $this->assertDatabaseCount('workspace_day_overrides',0);
    }
}
