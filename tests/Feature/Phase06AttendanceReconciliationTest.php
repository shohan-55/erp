<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase06AttendanceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function clock(): void
    {
        $now = CarbonImmutable::parse('2026-10-20 12:00:00', 'Asia/Dhaka');
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
        $company = DB::table('companies')->insertGetId([
            'slug'=>'audit-co', 'name'=>'CA Firm for Testing', 'timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00', 'office_end'=>'18:00:00', 'grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]', 'active'=>true, 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $people=[];
        foreach (['partner-a','partner-b'] as $partner) {
            $w = DB::table('workspaces')->insertGetId([
                'company_id'=>$company, 'slug'=>$partner, 'name'=>$partner,
                'active'=>true, 'created_at'=>now(), 'updated_at'=>now(),
            ]);
            foreach (['partner','manager','student-a','student-b','student-c'] as $name) {
                $role = str_starts_with($name,'student') ? 'student' : $name;
                $u = User::factory()->create(['name'=>$partner.'-'.$name]);
                $id = DB::table('memberships')->insertGetId([
                    'company_id'=>$company, 'workspace_id'=>$w,
                    'user_id'=>$u->id, 'role'=>$role,
                    'manager_membership_id'=>$role==='student' ? $people[$partner]['manager']->id : null,
                    'active'=>true, 'monthly_allowance'=>0,
                    'created_at'=>now(), 'updated_at'=>now(),
                ]);
                $people[$partner][$name] = (object) ['user'=>$u,'id'=>$id,'workspace'=>$w];
            }
        }
        return [$company,$people];
    }

    private function session(int $company,object $person,string $mode='office',bool $ended=true,bool $late=false): void
    {
        DB::table('attendance_sessions')->insert([
            'company_id'=>$company, 'workspace_id'=>$person->workspace,
            'membership_id'=>$person->id, 'work_date'=>'2026-10-19',
            'work_mode'=>$mode, 'client_site'=>$mode==='client' ? 'Client Site' : null,
            'started_at'=>'2026-10-19 04:00:00', // 10:00 Dhaka
            'ended_at'=>$ended ? '2026-10-19 12:00:00' : null,
            'late'=>$late, 'holiday'=>false,
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }

    public function test_reconciliation_distinguishes_recorded_presence_client_duty_and_review_flags_without_payroll_changes(): void
    {
        $this->clock();
        try {
            [$company,$p] = $this->fixture();
            $this->session($company,$p['partner-a']['student-a'],'client');
            $this->session($company,$p['partner-a']['student-b'],'office',false);
            $this->actingAs($p['partner-a']['partner']->user)
                ->get('/c/audit-co/app/attendance/reconcile?date=2026-10-19')
                ->assertOk()
                ->assertSeeText('Daily Attendance Reconciliation')
                ->assertSeeText('Client Duty')
                ->assertSeeText('Missing Punch · Review')
                ->assertSeeText('No Check-in · Review')
                ->assertDontSeeText('partner-b-student-a')
                ->assertDontSeeText('BDT');
            $this->assertDatabaseCount('payroll_runs',0);
            $this->assertDatabaseCount('tenant_audit_events',0);
        } finally {
            $this->resetClock();
        }
    }

    public function test_reconciliation_respects_approved_leave_and_student_specific_off_day(): void
    {
        $this->clock();
        try {
            [$company,$p] = $this->fixture();
            DB::table('leave_requests')->insert([
                'company_id'=>$company,'workspace_id'=>$p['partner-a']['student-a']->workspace,
                'membership_id'=>$p['partner-a']['student-a']->id,
                'leave_type'=>'annual','date_from'=>'2026-10-19','date_to'=>'2026-10-19',
                'reason'=>'Approved personal leave for medical appointment.',
                'status'=>'approved','created_at'=>now(),'updated_at'=>now(),
            ]);
            DB::table('student_calendar_shifts')->insert([
                'company_id'=>$company,'workspace_id'=>$p['partner-a']['student-b']->workspace,
                'student_membership_id'=>$p['partner-a']['student-b']->id,
                'batch_ref'=>'1efbe8e6-80c8-4e4c-8fd1-615062035999','work_date'=>'2026-10-19',
                'day_type'=>'holiday','reason'=>'Student approved off day',
                'status'=>'approved','proposed_by'=>$p['partner-a']['partner']->id,
                'approved_by'=>$p['partner-a']['partner']->id, 'decided_at'=>now(),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->actingAs($p['partner-a']['partner']->user)
                ->get('/c/audit-co/app/attendance/reconcile?date=2026-10-19')
                ->assertOk()->assertSeeText('Approved Leave')
                ->assertSeeText('Holiday')
                ->assertSeeText('No Check-in · Review');
        } finally {
            $this->resetClock();
        }
    }

    public function test_manager_and_student_can_see_only_their_own_authorized_scope_and_gate(): void
    {
        $this->clock();
        try {
            [$company,$p] = $this->fixture();
            $this->actingAs($p['partner-a']['manager']->user)
                ->get('/c/audit-co/app/attendance/reconcile?date=2026-10-19')
                ->assertOk()->assertSeeText('partner-a-student-a')
                ->assertDontSeeText('partner-b-student-a');

            $this->actingAs($p['partner-a']['student-a']->user)
                ->get('/c/audit-co/app/attendance/reconcile?date=2026-10-19')
                ->assertOk()->assertSeeText('partner-a-student-a')
                ->assertDontSeeText('partner-a-student-b')
                ->assertDontSeeText('partner-b-student-a');

            DB::table('student_feature_controls')->insert([
                'company_id'=>$company,'workspace_id'=>$p['partner-a']['student-a']->workspace,
                'membership_id'=>$p['partner-a']['student-a']->id,
                'feature_key'=>'attendance_report',
                'partner_enabled'=>false,'manager_enabled'=>true,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->get('/c/audit-co/app/attendance/reconcile?date=2026-10-19')->assertForbidden();
        } finally {
            $this->resetClock();
        }
    }

    public function test_early_exit_uses_actual_shift_and_today_is_not_assumed_absent(): void
    {
        $this->clock();
        try {
            [$company,$p] = $this->fixture();
            DB::table('attendance_sessions')->insert([
                'company_id'=>$company,'workspace_id'=>$p['partner-a']['student-a']->workspace,
                'membership_id'=>$p['partner-a']['student-a']->id,
                'work_date'=>'2026-10-19','work_mode'=>'office',
                'started_at'=>'2026-10-19 04:00:00',
                'ended_at'=>'2026-10-19 09:30:00', // 3:30PM, before 6PM
                'late'=>false,'holiday'=>false,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->actingAs($p['partner-a']['partner']->user)
                ->get('/c/audit-co/app/attendance/reconcile?date=2026-10-19')
                ->assertOk()->assertSeeText('Early Exit · Review');
            $this->get('/c/audit-co/app/attendance/reconcile?date=2026-10-20')
                ->assertOk()->assertSeeText('Not Checked In Yet')
                ->assertDontSeeText('No Check-in · Review');
        } finally {
            $this->resetClock();
        }
    }

    public function test_reconciliation_refuses_future_and_unbounded_historical_dates(): void
    {
        $this->clock();
        try {
            [, $p]=$this->fixture();
            $this->actingAs($p['partner-a']['partner']->user)
                ->get('/c/audit-co/app/attendance/reconcile?date=2026-10-21')
                ->assertSessionHasErrors('date');
            $this->get('/c/audit-co/app/attendance/reconcile?date=2026-01-01')
                ->assertSessionHasErrors('date');
        } finally {
            $this->resetClock();
        }
    }
}
