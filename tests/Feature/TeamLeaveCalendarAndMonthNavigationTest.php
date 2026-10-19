<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DashboardMonthCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TeamLeaveCalendarAndMonthNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00:00','Asia/Dhaka'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function fixture(): array
    {
        $company=DB::table('companies')->insertGetId([
            'slug'=>'team-cal','name'=>'Scoped Calendar Studio','timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $workspace=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'slug'=>'main','name'=>'Partner Main',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $otherWorkspace=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'slug'=>'other','name'=>'Other Partner',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $members=[];
        foreach ([
            'partner'=>['partner',$workspace,null],
            'managerA'=>['manager',$workspace,null],
            'managerB'=>['manager',$workspace,null],
            'studentA'=>['student',$workspace,'managerA'],
            'studentB'=>['student',$workspace,'managerB'],
            'otherStudent'=>['student',$otherWorkspace,null],
        ] as $key=>[$role,$home,$managerKey]) {
            $user=User::factory()->create(['name'=>'Calendar '.$key]);
            $id=DB::table('memberships')->insertGetId([
                'company_id'=>$company,'workspace_id'=>$home,
                'user_id'=>$user->id,'role'=>$role,'active'=>true,'monthly_allowance'=>0,
                'manager_membership_id'=>$managerKey ? $members[$managerKey]['id'] : null,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $members[$key]=['id'=>$id,'user'=>$user,'home'=>$home];
        }
        return [DB::table('companies')->find($company),
            DB::table('workspaces')->find($workspace),$members];
    }

    private function leave(object $company,array $people,string $memberKey,string $date,
        string $status='approved',string $portion='full'): int
    {
        $subject=$people[$memberKey];
        return DB::table('leave_requests')->insertGetId([
            'company_id'=>$company->id,'workspace_id'=>$subject['home'],
            'membership_id'=>$subject['id'],'leave_type'=>'casual',
            'day_portion'=>$portion,'date_from'=>$date,'date_to'=>$date,
            'charge_days'=>$portion==='full'?1:0.5,
            'status'=>$status,'reason'=>'Confidential employee medical details must never leak into team calendars',
            'reviewed_by'=>$status==='approved'?$people['partner']['id']:
                ($status==='manager_reviewed'?$people['managerA']['id']:null),
            'reviewed_at'=>$status==='approved'?now():null,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function month(object $company,object $workspace,array $actor,
        string $month='2026-11'):array
    {
        return DashboardMonthCalendar::forActor($company,$workspace,
            DB::table('memberships')->find($actor['id']),true,
            CarbonImmutable::createFromFormat('!Y-m',$month,$company->timezone));
    }

    public function test_selected_month_has_correct_lengths_and_exact_dates_including_leap_february(): void
    {
        [$company,$workspace,$p]=$this->fixture();
        foreach(['2026-09'=>30,'2026-11'=>30,'2027-02'=>28,'2028-02'=>29] as $month=>$length) {
            $calendar=$this->month($company,$workspace,$p['studentA'],$month);
            $this->assertCount($length,$calendar['days']);
            $this->assertSame($month.'-01',$calendar['days'][0]['date']);
            $this->assertSame($month.'-'.str_pad((string)$length,2,'0',STR_PAD_LEFT),
                $calendar['days'][$length-1]['date']);
            $this->assertSame($month,$calendar['month_key']);
        }
        $this->actingAs($p['studentA']['user'])
            ->get('/c/team-cal/app/calendar?month=2026-11')->assertOk()
            ->assertSeeText('November 2026')
            ->assertSee('data-date="2026-11-30"',false)
            ->assertDontSee('data-date="2026-10-31"',false);
        $this->get('/c/team-cal/app/calendar/export?month=2026-11')->assertOk()
            ->assertHeader('Content-Type','text/csv; charset=utf-8');
    }

    public function test_manager_can_only_see_assigned_active_students_and_partner_is_workspace_scoped(): void
    {
        [$company,$workspace,$p]=$this->fixture();
        $this->leave($company,$p,'studentA','2026-11-18');
        $this->leave($company,$p,'studentB','2026-11-18');
        $this->leave($company,$p,'otherStudent','2026-11-18');

        $a=$this->month($company,$workspace,$p['managerA']);
        $b=$this->month($company,$workspace,$p['managerB']);
        $partner=$this->month($company,$workspace,$p['partner']);
        $student=$this->month($company,$workspace,$p['studentA']);

        $this->assertSame(1,collect($a['days'])->firstWhere('date','2026-11-18')['team_approved_count']);
        $this->assertSame(1,collect($b['days'])->firstWhere('date','2026-11-18')['team_approved_count']);
        $this->assertSame(2,collect($partner['days'])->firstWhere('date','2026-11-18')['team_approved_count']);
        $this->assertSame(0,collect($student['days'])->firstWhere('date','2026-11-18')['team_approved_count']);
        $this->assertSame('leave',collect($student['days'])->firstWhere('date','2026-11-18')['stage']);
        $this->assertSame('team_leave',collect($a['days'])->firstWhere('date','2026-11-18')['stage']);
        $this->assertSame('team_leave',collect($partner['days'])->firstWhere('date','2026-11-18')['stage']);

        $this->actingAs($p['managerA']['user'])
            ->get('/c/team-cal/app/calendar?month=2026-11')->assertOk()
            ->assertSeeText('Calendar studentA')
            ->assertDontSee('Calendar studentB')
            ->assertDontSee('Calendar otherStudent')
            ->assertDontSee('Confidential employee medical details');
        $this->actingAs($p['partner']['user'])
            ->get('/c/team-cal/app/calendar?month=2026-11')->assertOk()
            ->assertSeeText('Calendar studentA')->assertSeeText('Calendar studentB')
            ->assertDontSee('Calendar otherStudent');
        $this->actingAs($p['studentA']['user'])
            ->get('/c/team-cal/app/calendar?month=2026-11')->assertOk()
            ->assertDontSee('Calendar studentB')->assertDontSee('Calendar otherStudent')
            ->assertSee('View approved letter');
    }

    public function test_pending_and_manager_reviewed_never_masquerade_as_approved_leave(): void
    {
        [$company,$workspace,$p]=$this->fixture();
        $pending=$this->leave($company,$p,'studentA','2026-11-19','pending');
        $review=$this->leave($company,$p,'studentA','2026-11-20','manager_reviewed');
        $manager=$this->month($company,$workspace,$p['managerA']);
        $this->assertSame('team_pending',collect($manager['days'])->firstWhere('date','2026-11-19')['stage']);
        $this->assertSame('team_pending',collect($manager['days'])->firstWhere('date','2026-11-20')['stage']);
        $student=$this->month($company,$workspace,$p['studentA']);
        foreach(['2026-11-19','2026-11-20'] as $date) {
            $this->assertSame('planned',collect($student['days'])->firstWhere('date',$date)['stage']);
        }
        $this->actingAs($p['studentA']['user'])->get('/c/team-cal/app/calendar?month=2026-11')
            ->assertOk()->assertSee('Pending review')->assertSee('Manager reviewed · Partner pending',false);
        DB::table('leave_requests')->where('id',$pending)
            ->update(['status'=>'approved','reviewed_by'=>$p['partner']['id'],'reviewed_at'=>now()]);
        $student=$this->month($company,$workspace,$p['studentA']);
        $this->assertSame('leave',collect($student['days'])->firstWhere('date','2026-11-19')['stage']);
        $this->assertSame('planned',collect($student['days'])->firstWhere('date','2026-11-20')['stage']);
        $this->assertDatabaseHas('leave_requests',['id'=>$review,'status'=>'manager_reviewed']);
        $this->assertDatabaseCount('payroll_line_adjustments',0);
    }

    public function test_half_day_and_holiday_exceptions_and_personal_privacy(): void
    {
        [$company,$workspace,$p]=$this->fixture();
        $this->leave($company,$p,'studentA','2026-11-03','approved','morning');
        $this->leave($company,$p,'studentA','2026-11-06','approved');
        $this->leave($company,$p,'studentB','2026-11-03','approved');
        $student=$this->month($company,$workspace,$p['studentA']);
        $half=collect($student['days'])->firstWhere('date','2026-11-03');
        $this->assertSame('half',$half['stage']);
        $this->assertSame('morning',$half['leave_items'][0]['day_portion']);
        $holiday=collect($student['days'])->firstWhere('date','2026-11-06');
        $this->assertSame('holiday',$holiday['stage']);
        $this->assertCount(1,$holiday['leave_items']);
        $this->actingAs($p['studentA']['user'])->get('/c/team-cal/app/calendar?month=2026-11')
            ->assertOk()->assertSee('View approved letter')
            ->assertDontSee('Calendar studentB')
            ->assertDontSee('Confidential employee medical details');
    }
}
