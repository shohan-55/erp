<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase06AttendanceReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $company = DB::table('companies')->insertGetId([
            'name'=>'Demo CA Firm','slug'=>'demo-ca','timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $people = [];
        foreach (['partner-a','partner-b'] as $slug) {
            $w = DB::table('workspaces')->insertGetId([
                'company_id'=>$company,'slug'=>$slug,'name'=>$slug,'active'=>true,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach (['partner','manager','student'] as $role) {
                $user = User::factory()->create(['name'=>$slug.' '.$role]);
                $member = DB::table('memberships')->insertGetId([
                    'company_id'=>$company,'workspace_id'=>$w,'user_id'=>$user->id,'role'=>$role,
                    'manager_membership_id'=>$role==='student' ? $people[$slug]['manager']->id : null,
                    'monthly_allowance'=>'0.00','active'=>true,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                $people[$slug][$role]=(object)[
                    'user'=>$user,'id'=>$member,'workspace'=>$w,
                ];
            }
        }
        return [$company,$people];
    }

    private function setClock(): void
    {
        $clock=CarbonImmutable::parse('2026-10-20 16:00:00','Asia/Dhaka');
        CarbonImmutable::setTestNow($clock);
        Carbon::setTestNow($clock);
    }

    private function resetClock(): void
    {
        CarbonImmutable::setTestNow();
        Carbon::setTestNow();
    }

    private function submitCheckout(object $student,int $session): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($student->user)->post('/c/demo-ca/app/attendance/reviews',[
            'kind'=>'missed_checkout','work_date'=>'2026-10-19','attendance_session_id'=>$session,
            'end_time'=>'17:00','reason'=>'The browser failed to record the final checkout.',
        ]);
    }

    private function seedAttendanceSession(int $company,object $student,?string $end=null): int
    {
        return DB::table('attendance_sessions')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$student->workspace,'membership_id'=>$student->id,
            'work_date'=>'2026-10-19','work_mode'=>'office','started_at'=>'2026-10-19 04:00:00',
            'ended_at'=>$end,'late'=>false,'holiday'=>false,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function test_student_manager_partner_checkout_workflow_changes_record_only_on_final_approval(): void
    {
        $this->setClock();
        try {
            [$company,$p]=$this->fixture();
            $session=$this->seedAttendanceSession($company,$p['partner-a']['student']);
            $this->submitCheckout($p['partner-a']['student'],$session)->assertRedirect();
            $request=DB::table('attendance_review_requests')->first();
            $this->assertSame('pending',$request->status);
            $this->assertDatabaseHas('attendance_sessions',['id'=>$session,'ended_at'=>null]);

            $url="/c/demo-ca/app/attendance/reviews/{$request->id}/decision";
            $this->actingAs($p['partner-a']['partner']->user)
                ->post($url,['decision'=>'approve'])->assertSessionHasErrors('decision');

            $this->actingAs($p['partner-a']['manager']->user)
                ->post($url,['decision'=>'approve'])->assertRedirect();
            $this->assertDatabaseHas('attendance_review_requests',[
                'id'=>$request->id,'status'=>'manager_reviewed',
                'manager_recommendation'=>'approve',
            ]);
            $this->assertDatabaseHas('attendance_sessions',['id'=>$session,'ended_at'=>null]);

            $this->actingAs($p['partner-a']['partner']->user)
                ->post($url,['decision'=>'approve'])->assertRedirect();
            $this->assertDatabaseHas('attendance_review_requests',[
                'id'=>$request->id,'status'=>'approved',
                'decided_by'=>$p['partner-a']['partner']->id,
            ]);
            $this->assertDatabaseHas('attendance_sessions',[
                'id'=>$session,'ended_at'=>'2026-10-19 11:00:00',
                'corrected_by'=>$p['partner-a']['partner']->id,
            ]);
            $this->assertDatabaseHas('tenant_audit_events',[
                'action'=>'attendance.correct','entity_id'=>$session,
            ]);
            $this->post($url,['decision'=>'approve'])->assertSessionHasErrors('decision');
        } finally {
            $this->resetClock();
        }
    }

    public function test_missing_checkin_recovery_is_audited_and_never_creates_overlapping_punches(): void
    {
        $this->setClock();
        try {
            [$company,$p]=$this->fixture();
            $student=$p['partner-a']['student'];
            $this->actingAs($student->user)->post('/c/demo-ca/app/attendance/reviews',[
                'kind'=>'missed_checkin','work_date'=>'2026-10-19',
                'start_time'=>'10:30','end_time'=>'17:30',
                'reason'=>'Both punches were missed during an approved office duty.',
            ])->assertRedirect();
            $row=DB::table('attendance_review_requests')->first();
            $url="/c/demo-ca/app/attendance/reviews/{$row->id}/decision";
            $this->actingAs($p['partner-a']['manager']->user)
                ->post($url,['decision'=>'approve'])->assertRedirect();
            $this->actingAs($p['partner-a']['partner']->user)
                ->post($url,['decision'=>'approve'])->assertRedirect();
            $this->assertDatabaseHas('attendance_sessions',[
                'membership_id'=>$student->id,'work_date'=>'2026-10-19','late'=>1,
                'started_at'=>'2026-10-19 04:30:00','ended_at'=>'2026-10-19 11:30:00',
                'corrected_by'=>$p['partner-a']['partner']->id,
            ]);

            $this->actingAs($student->user)->post('/c/demo-ca/app/attendance/reviews',[
                'kind'=>'missed_checkin','work_date'=>'2026-10-19',
                'start_time'=>'11:00','end_time'=>'16:30',
                'reason'=>'Another request should not change the prior approved record.',
            ])->assertRedirect();
            $later=DB::table('attendance_review_requests')->latest('id')->first();
            $laterUrl="/c/demo-ca/app/attendance/reviews/{$later->id}/decision";
            $this->actingAs($p['partner-a']['manager']->user)
                ->post($laterUrl,['decision'=>'approve'])->assertRedirect();
            $this->actingAs($p['partner-a']['partner']->user)
                ->post($laterUrl,['decision'=>'approve'])->assertSessionHasErrors('decision');
            $this->assertDatabaseCount('attendance_sessions',1);
        } finally {
            $this->resetClock();
        }
    }

    public function test_partner_can_reject_without_changing_attendance_and_cross_partner_access_denied(): void
    {
        $this->setClock();
        try {
            [$company,$p]=$this->fixture();
            $session=$this->seedAttendanceSession($company,$p['partner-a']['student']);
            $this->submitCheckout($p['partner-a']['student'],$session)->assertRedirect();
            $row=DB::table('attendance_review_requests')->first();
            $url="/c/demo-ca/app/attendance/reviews/{$row->id}/decision";
            $this->actingAs($p['partner-b']['partner']->user)
                ->post($url,['decision'=>'approve'])->assertNotFound();
            $this->actingAs($p['partner-b']['manager']->user)
                ->post($url,['decision'=>'approve'])->assertNotFound();
            $this->actingAs($p['partner-a']['student']->user)
                ->post($url,['decision'=>'approve'])->assertForbidden();
            $this->actingAs($p['partner-a']['partner']->user)
                ->post($url,['decision'=>'reject','decision_note'=>'Insufficient verification'])
                ->assertRedirect();
            $this->assertDatabaseHas('attendance_review_requests',[
                'id'=>$row->id,'status'=>'rejected',
            ]);
            $this->assertDatabaseHas('attendance_sessions',['id'=>$session,'ended_at'=>null]);
            $this->actingAs($p['partner-b']['partner']->user)
                ->get('/c/demo-ca/app/attendance/reviews')->assertOk()
                ->assertDontSeeText('The browser failed to record');
        } finally {
            $this->resetClock();
        }
    }

    public function test_client_duty_approval_is_not_faked_presence_and_unverified_duty_is_flagged(): void
    {
        $this->setClock();
        try {
            [$company,$p]=$this->fixture();
            $student=$p['partner-a']['student'];
            $this->actingAs($student->user)->post('/c/demo-ca/app/attendance/reviews',[
                'kind'=>'client_duty','work_date'=>'2026-10-19',
                'client_site'=>'Verified Audit Client',
                'reason'=>'A full day audit inventory observation assignment.',
            ])->assertRedirect();
            $row=DB::table('attendance_review_requests')->first();
            $url="/c/demo-ca/app/attendance/reviews/{$row->id}/decision";
            $this->actingAs($p['partner-a']['manager']->user)
                ->post($url,['decision'=>'approve'])->assertRedirect();
            $this->actingAs($p['partner-a']['partner']->user)
                ->post($url,['decision'=>'approve'])->assertRedirect();
            $this->assertDatabaseCount('attendance_sessions',0);
            $this->get('/c/demo-ca/app/attendance/reconcile?date=2026-10-19')
                ->assertOk()->assertSeeText('Client Duty Approved · No Check-in');
            $this->assertDatabaseHas('attendance_review_requests',[
                'kind'=>'client_duty','status'=>'approved',
            ]);

            DB::table('attendance_sessions')->insert([
                'company_id'=>$company,'workspace_id'=>$student->workspace,'membership_id'=>$student->id,
                'work_date'=>'2026-10-19','work_mode'=>'client','client_site'=>'Verified Audit Client',
                'started_at'=>'2026-10-19 04:00:00','ended_at'=>'2026-10-19 12:00:00',
                'late'=>false,'holiday'=>false,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->get('/c/demo-ca/app/attendance/reconcile?date=2026-10-19')
                ->assertOk()->assertSeeText('Client Duty · Authorized');
        } finally {
            $this->resetClock();
        }
    }

    public function test_payroll_lock_rejects_final_attendance_changes(): void
    {
        $this->setClock();
        try {
            [$company,$p]=$this->fixture();
            $session=$this->seedAttendanceSession($company,$p['partner-a']['student']);
            $this->submitCheckout($p['partner-a']['student'],$session)->assertRedirect();
            $row=DB::table('attendance_review_requests')->first();
            DB::table('payroll_runs')->insert([
                'company_id'=>$company,'workspace_id'=>$p['partner-a']['partner']->workspace,
                'period_month'=>'2026-10-01','status'=>'locked','policy_snapshot'=>'{}',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->actingAs($p['partner-a']['partner']->user)
                ->post("/c/demo-ca/app/attendance/reviews/{$row->id}/decision",[
                    'decision'=>'approve',
                ])->assertSessionHasErrors('decision');
            $this->assertDatabaseHas('attendance_sessions',['id'=>$session,'ended_at'=>null]);
        } finally {
            $this->resetClock();
        }
    }
}
