<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CorporateWorkspaceExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $company=DB::table('companies')->insertGetId([
            'slug'=>'corp-ux','name'=>'Corporate QA Studio','timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $workspace=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'slug'=>'unit','name'=>'Unit A',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $people=[];
        foreach (['partner','manager','manager2','student','student2'] as $key) {
            $role=str_starts_with($key,'manager')?'manager':(str_starts_with($key,'student')?'student':$key);
            $user=User::factory()->create();
            $person=DB::table('memberships')->insertGetId([
                'company_id'=>$company,'workspace_id'=>$workspace,
                'user_id'=>$user->id,'role'=>$role,'active'=>true,'monthly_allowance'=>0,
                'manager_membership_id'=>$role==='student'
                    ? ($key==='student2'?$people['manager2']['id']:$people['manager']['id'])
                    : null,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $people[$key]=['user'=>$user,'id'=>$person];
        }
        return [$company,$workspace,$people];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00:00','Asia/Dhaka'));
    }
    protected function tearDown(): void
    {
        $this->travelBack(); parent::tearDown();
    }

    private function leave(int $company,int $workspace,int $member): void
    {
        DB::table('leave_requests')->insert([
            'company_id'=>$company,'workspace_id'=>$workspace,
            'membership_id'=>$member,'leave_type'=>'casual',
            'date_from'=>'2026-11-04','date_to'=>'2026-11-04',
            'reason'=>'Scheduled family appointment','status'=>'pending',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function test_role_specific_work_pulse_and_action_finder_respect_private_portals(): void
    {
        [$company,$workspace,$p]=$this->fixture();
        $this->leave($company,$workspace,$p['student']['id']);
        foreach (['partner','manager','student'] as $role) {
            $this->actingAs($p[$role]['user'])->get('/c/corp-ux/app')->assertOk()
                ->assertSee('data-corporate-action-center',false)
                ->assertSee('data-corporate-action-search',false)
                ->assertSee('data-corporate-pin',false)
                ->assertSee('erp-work-pulse__grid',false)
                ->assertSeeText('Workforce Calendar')
                ->assertSee('id="erp-corporate-workspace-script"',false)
                ->assertSee('id="erp-corporate-workspace-style"',false);
        }
        $this->actingAs($p['manager']['user'])->get('/c/corp-ux/app')->assertOk()
            ->assertSeeText('Leave approvals')->assertSeeText('Conveyance reviews')
            ->assertDontSeeText('Payroll Control');
        $this->actingAs($p['student']['user'])->get('/c/corp-ux/app')->assertOk()
            ->assertSeeText('My leave requests')
            ->assertDontSeeText('HR policy approvals');
        $this->actingAs($p['partner']['user'])->get('/c/corp-ux/app')->assertOk()
            ->assertSeeText('HR policy approvals')
            ->assertSeeText('Annual holiday approvals');
    }

    public function test_manager_sees_only_assigned_review_count_and_student_sees_only_their_request(): void
    {
        [$company,$workspace,$p]=$this->fixture();
        $this->leave($company,$workspace,$p['student2']['id']);
        $queue=\App\Support\DashboardOverview::queues(
            DB::table('memberships')->find($p['manager']['id'])
        );
        $other=\App\Support\DashboardOverview::queues(
            DB::table('memberships')->find($p['manager2']['id'])
        );
        $this->assertSame(0,$queue['leave']);
        $this->assertSame(1,$other['leave']);

        $manager=\App\Support\WorkspacePulse::forActor(
            DB::table('memberships')->find($p['manager']['id']),$queue,null
        );
        $this->assertSame(0,collect($manager['items'])->firstWhere('route','erp.leaves')['count']);
        $student=\App\Support\WorkspacePulse::forActor(
            DB::table('memberships')->find($p['student']['id']),
            ['leave'=>0,'claims'=>0,'payroll'=>0],null
        );
        $this->assertSame(0,collect($student['items'])->firstWhere('route','erp.leaves')['count']);
        $this->actingAs($p['student']['user'])->get('/c/corp-ux/app')->assertOk()
            ->assertDontSeeText('1 in progress');
    }

    public function test_calendar_month_navigation_is_read_only_and_previous_records_are_personal(): void
    {
        [$company,$workspace,$p]=$this->fixture();
        DB::table('attendance_sessions')->insert([
            'company_id'=>$company,'workspace_id'=>$workspace,
            'membership_id'=>$p['student2']['id'],'work_date'=>'2026-09-14',
            'work_mode'=>'office','started_at'=>'2026-09-14 03:55:00',
            'ended_at'=>'2026-09-14 12:00:00','late'=>false,'holiday'=>false,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->actingAs($p['student']['user'])
            ->get('/c/corp-ux/app/calendar?month=2026-09')->assertOk()
            ->assertSeeText('September 2026')
            ->assertSeeText('October 2026')
            ->assertSeeText('Jump to month')
            ->assertSee('data-calendar-day',false)
            ->assertSee('erp-month-calendar__watermark',false)
            ->assertSee('data-corporate-confirm-dialog',false);
        $download=$this->get('/c/corp-ux/app/calendar/export?month=2026-09')->assertOk()
            ->assertHeader('Content-Type','text/csv; charset=utf-8');
        $this->assertStringContainsString('Date,Day,Status,Details', $download->streamedContent());
        $this->assertStringNotContainsString('Client Secret', $download->streamedContent());
        $this->get('/c/corp-ux/app/calendar?month=2020-01')
            ->assertSessionHasErrors('month');
        $this->get('/c/corp-ux/app/calendar?month=2026-13')
            ->assertSessionHasErrors('month');
        $this->assertDatabaseCount('payroll_line_adjustments',0);
        $this->assertDatabaseCount('attendance_review_requests',0);
    }

    public function test_csv_prevents_excel_formula_injection_from_company_branding(): void
    {
        [$company,,$p]=$this->fixture();
        DB::table('companies')->where('id',$company)
            ->update(['brand_name'=>'=HYPERLINK("https://invalid.test","unsafe")']);
        $this->actingAs($p['student']['user']);
        $response=$this->get('/c/corp-ux/app/calendar/export');
        $response->assertOk();
        $csv=$response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('Date,Day,Status,Details,Team Check-ins', $csv);
    }

    public function test_student_feature_gates_hide_personal_attendance_in_calendar_when_disabled(): void
    {
        [$company,$workspace,$p]=$this->fixture();
        DB::table('student_feature_controls')->insert([
            'company_id'=>$company,'workspace_id'=>$workspace,
            'membership_id'=>$p['student']['id'],
            'feature_key'=>'attendance','partner_enabled'=>false,
            'manager_enabled'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->actingAs($p['student']['user'])->get('/c/corp-ux/app')
            ->assertOk()->assertDontSeeText('QUICK ATTENDANCE');
        $this->get('/c/corp-ux/app/calendar')->assertOk()
            ->assertSeeText('Approved workspace schedule')
            ->assertDontSeeText('Check-in:');
    }
}
