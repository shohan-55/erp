<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CompanyAccessAndManagerAttendanceRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function setupClock(): void
    {
        $time=CarbonImmutable::parse('2026-10-20 16:00:00','Asia/Dhaka');
        CarbonImmutable::setTestNow($time);
        Carbon::setTestNow($time);
    }

    private function clearClock(): void
    {
        CarbonImmutable::setTestNow();
        Carbon::setTestNow();
    }

    private function fixture(): array
    {
        $company=DB::table('companies')->insertGetId([
            'name'=>'Privilege Guard CA Firm','slug'=>'guarded-practice','timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00','office_end'=>'18:00:00','grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]','active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $team=[];
        foreach(['unit-a','unit-b'] as $slug) {
            $workspace=DB::table('workspaces')->insertGetId([
                'company_id'=>$company,'slug'=>$slug,'name'=>$slug,'active'=>true,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach(['partner','manager','manager2','student','student2'] as $role) {
                $user=User::factory()->create(['name'=>$slug.' '.$role]);
                $manager=$role==='student'?$team[$slug]['manager']->id
                    : ($role==='student2'?$team[$slug]['manager2']->id:null);
                $id=DB::table('memberships')->insertGetId([
                    'company_id'=>$company,'workspace_id'=>$workspace,'user_id'=>$user->id,
                    'role'=>in_array($role,['manager','manager2'],true)?'manager'
                        :(in_array($role,['student','student2'],true)?'student':'partner'),
                    'manager_membership_id'=>$manager,
                    'monthly_allowance'=>0,'active'=>true,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                $team[$slug][$role]=(object)['id'=>$id,'user'=>$user,'workspace'=>$workspace];
            }
        }
        return [$company,$team];
    }

    private function givePermission(object $partner,object $manager,
        string $role='moderator',array $permissions=['attendance.propose']): void
    {
        $this->actingAs($partner->user)
            ->post('/c/guarded-practice/app/settings/company-access',[
                'membership_id'=>$manager->id,
                'access_role'=>$role,
                'permissions'=>$permissions,
            ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function propose(object $manager,object $student,string $kind,
        array $extra=[]): \Illuminate\Testing\TestResponse
    {
        $fields=[
            'membership_id'=>$student->id,
            'kind'=>$kind,'work_date'=>'2026-10-19',
            'reason'=>'The office internet was unavailable; supervisor verified the workday.',
            ...$extra,
        ];
        return $this->actingAs($manager->user)
            ->post('/c/guarded-practice/app/attendance/reviews/team',$fields);
    }

    private function seedSession(int $company,object $student,string $start='2026-10-19 04:15:00'):int
    {
        return DB::table('attendance_sessions')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$student->workspace,
            'membership_id'=>$student->id,'work_date'=>'2026-10-19',
            'work_mode'=>'office','started_at'=>$start,'ended_at'=>'2026-10-19 12:00:00',
            'late'=>true,'holiday'=>false,'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function test_partner_can_delegate_company_admin_and_admin_can_add_revoke_workspace_moderator():void
    {
        $this->setupClock();
        try {
            [, $t]=$this->fixture();
            $a=$t['unit-a'];
            $this->actingAs($a['manager']->user)
                ->get('/c/guarded-practice/app/settings/company-access')->assertForbidden();
            $this->givePermission($a['partner'],$a['manager'],'company_admin',['attendance.propose','access.manage','access.delegate_admins']);
            $this->actingAs($a['manager']->user)
                ->get('/c/guarded-practice/app/settings/company-access')->assertOk()
                ->assertSeeText('Company Access Control Center')
                ->assertDontSeeText('unit-b manager');
            $this->actingAs($a['manager']->user)
                ->post('/c/guarded-practice/app/settings/company-access',[
                    'membership_id'=>$a['manager2']->id,'access_role'=>'moderator',
                    'permissions'=>['attendance.propose'],
                ])->assertRedirect()->assertSessionHasNoErrors();
            $grant=DB::table('workspace_access_grants')
                ->where('membership_id',$a['manager2']->id)->first();
            $this->assertSame('moderator',$grant->access_role);
            $this->assertDatabaseHas('tenant_audit_events',[
                'action'=>'workspace_access.granted','entity_id'=>$grant->id,
            ]);
            $this->actingAs($a['manager']->user)
                ->post("/c/guarded-practice/app/settings/company-access/{$grant->id}/revoke",[
                    'reason'=>'Manager has completed the temporary assignment.',
                ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertNotNull(DB::table('workspace_access_grants')->find($grant->id)->revoked_at);
            $this->actingAs($a['manager2']->user)
                ->post('/c/guarded-practice/app/attendance/reviews/team',[
                    'membership_id'=>$a['student2']->id,'kind'=>'missed_checkin',
                    'work_date'=>'2026-10-19','start_time'=>'10:00','end_time'=>'17:30',
                    'reason'=>'This grant was revoked and must no longer be honored.',
                ])->assertForbidden();
            $this->assertDatabaseMissing('workspace_access_grants',[
                'membership_id'=>$a['partner']->id,
            ]);
            $this->assertFalse($a['manager']->user->fresh()->is_platform_admin);
        } finally {$this->clearClock();}
    }

    public function test_manager_proposes_missed_punch_and_only_partner_can_record_it():void
    {
        $this->setupClock();
        try {
            [$company,$t]=$this->fixture();
            $a=$t['unit-a'];
            $this->givePermission($a['partner'],$a['manager']);
            $this->propose($a['manager'],$a['student'],'missed_checkin',[
                'start_time'=>'10:05','end_time'=>'17:30',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $row=DB::table('attendance_review_requests')->first();
            $this->assertSame('manager_reviewed',$row->status);
            $this->assertSame($a['manager']->id,$row->initiated_by);
            $this->assertSame($a['student']->id,$row->membership_id);
            $this->assertDatabaseCount('attendance_sessions',0);
            $url="/c/guarded-practice/app/attendance/reviews/{$row->id}/decision";
            $this->actingAs($a['manager']->user)
                ->post($url,['decision'=>'approve'])->assertForbidden();
            $this->actingAs($t['unit-b']['partner']->user)
                ->post($url,['decision'=>'approve'])->assertNotFound();
            $this->actingAs($a['partner']->user)
                ->post($url,['decision'=>'approve'])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('attendance_sessions',[
                'company_id'=>$company,'membership_id'=>$a['student']->id,
                'work_date'=>'2026-10-19','started_at'=>'2026-10-19 04:05:00',
                'late'=>1,'late_within_grace'=>1,
                'corrected_by'=>$a['partner']->id,
            ]);
            $this->assertDatabaseHas('tenant_audit_events',[
                'action'=>'attendance.review.manager_initiated','entity_id'=>$row->id,
            ]);
            $this->assertDatabaseCount('payroll_line_adjustments',0);
        } finally {$this->clearClock();}
    }

    public function test_manager_cannot_propose_for_other_managers_student_or_other_partner_or_fake_clock():void
    {
        $this->setupClock();
        try {
            [, $t]=$this->fixture();
            $a=$t['unit-a'];
            $this->givePermission($a['partner'],$a['manager']);
            $this->propose($a['manager'],$a['student2'],'missed_checkin',[
                'start_time'=>'10:00','end_time'=>'17:30',
            ])->assertNotFound();
            $this->propose($a['manager'],$t['unit-b']['student'],'missed_checkin',[
                'start_time'=>'10:00','end_time'=>'17:30',
            ])->assertNotFound();
            $this->actingAs($a['student']->user)
                ->post('/c/guarded-practice/app/attendance/reviews/team',[
                    'membership_id'=>$a['student']->id,'kind'=>'missed_checkin',
                    'work_date'=>'2026-10-19','start_time'=>'10:00','end_time'=>'17:30',
                    'reason'=>'Student may not submit a Manager-initiated request.',
                ])->assertForbidden();
            $this->assertDatabaseCount('attendance_review_requests',0);
            $this->actingAs($a['manager']->user)
                ->post('/c/guarded-practice/app/settings/company-access',[
                    'membership_id'=>$t['unit-b']['manager']->id,
                    'access_role'=>'company_admin',
                    'permissions'=>['attendance.propose'],
                ])->assertForbidden();
        } finally {$this->clearClock();}
    }

    public function test_partner_final_approval_edits_checkin_time_and_preserves_original_in_audit():void
    {
        $this->setupClock();
        try {
            [$company,$t]=$this->fixture();$a=$t['unit-a'];
            $id=$this->seedSession($company,$a['student']);
            $this->givePermission($a['partner'],$a['manager']);
            $this->propose($a['manager'],$a['student'],'incorrect_checkin',[
                'attendance_session_id'=>$id,'start_time'=>'10:05',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('attendance_sessions',[
                'id'=>$id,'started_at'=>'2026-10-19 04:15:00',
            ]);
            $row=DB::table('attendance_review_requests')->first();
            $this->actingAs($a['partner']->user)
                ->post("/c/guarded-practice/app/attendance/reviews/{$row->id}/decision",[
                    'decision'=>'approve',
                ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('attendance_sessions',[
                'id'=>$id,'started_at'=>'2026-10-19 04:05:00',
                'late'=>1,'late_within_grace'=>1,'corrected_by'=>$a['partner']->id,
            ]);
            $logs=DB::table('tenant_audit_events')->where('action','attendance.checkin.corrected')->first();
            $this->assertNotNull($logs);
            $audit=json_decode($logs->details,true);
            $this->assertSame('2026-10-19 04:15:00',$audit['prior_checkin']);
            $this->assertSame('2026-10-19 04:05:00',$audit['new_checkin']);
            $this->assertDatabaseCount('payroll_line_adjustments',0);
        } finally {$this->clearClock();}
    }

    public function test_payroll_freeze_blocks_manager_proposal_and_cross_partner_grant():void
    {
        $this->setupClock();
        try {
            [$company,$t]=$this->fixture();$a=$t['unit-a'];
            $this->givePermission($a['partner'],$a['manager']);
            DB::table('payroll_runs')->insert([
                'company_id'=>$company,'workspace_id'=>$a['partner']->workspace,
                'period_month'=>'2026-10-01','status'=>'locked','policy_snapshot'=>'{}',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->propose($a['manager'],$a['student'],'missed_checkin',[
                'start_time'=>'10:00','end_time'=>'17:30',
            ])->assertSessionHasErrors('decision');
            $this->assertDatabaseCount('attendance_review_requests',0);
            $this->actingAs($a['partner']->user)
                ->post('/c/guarded-practice/app/settings/company-access',[
                    'membership_id'=>$t['unit-b']['manager']->id,
                    'access_role'=>'company_admin','permissions'=>['attendance.propose'],
                ])->assertNotFound();
        } finally {$this->clearClock();}
    }
    public function test_explicit_proxy_attendance_uses_server_time_and_can_be_revoked(): void
    {
        $this->setupClock();
        try {
            [$company,$t]=$this->fixture();$a=$t['unit-a'];
            $this->givePermission($a['partner'],$a['manager'],'moderator',['attendance.proxy']);
            $fields=['membership_id'=>$a['student']->id,'action'=>'check_in',
                'work_mode'=>'office','reason'=>'Office internet offline; supervisor confirmed student presence at current time.'];
            $this->actingAs($a['manager']->user)->post('/c/guarded-practice/app/attendance/proxy',$fields)
                ->assertRedirect()->assertSessionHasNoErrors();
            $session=DB::table('attendance_sessions')->first();
            $this->assertSame($a['student']->id,$session->membership_id);
            $this->assertSame($a['manager']->id,$session->proxy_checked_in_by);
            $this->assertSame('2026-10-20 10:00:00',$session->started_at);
            $this->assertDatabaseHas('tenant_audit_events',[
                'action'=>'attendance.proxy_check_in','entity_id'=>$session->id,
            ]);
            $this->actingAs($a['manager']->user)->post('/c/guarded-practice/app/attendance/proxy',$fields)
                ->assertSessionHasErrors('action');
            $this->actingAs($a['manager']->user)->post('/c/guarded-practice/app/attendance/proxy',[
                ...$fields,'membership_id'=>$a['student2']->id,
            ])->assertNotFound();
            $grant=DB::table('workspace_access_grants')->where('membership_id',$a['manager']->id)->first();
            $this->actingAs($a['partner']->user)
                ->post("/c/guarded-practice/app/settings/company-access/{$grant->id}/revoke",[
                    'reason'=>'Temporary emergency permission has been discontinued.',
                ])->assertRedirect();
            $this->actingAs($a['manager']->user)
                ->post('/c/guarded-practice/app/attendance/proxy',[
                    ...$fields,'action'=>'check_out',
                ])->assertForbidden();
            $this->assertDatabaseCount('attendance_sessions',1);
            $this->assertNull(DB::table('attendance_sessions')->first()->ended_at);
            $this->assertDatabaseCount('payroll_line_adjustments',0);
        } finally {$this->clearClock();}
    }

    public function test_delegated_leave_submission_requires_two_distinct_approvers(): void
    {
        $this->setupClock();
        try {
            [, $t]=$this->fixture();$a=$t['unit-a'];
            $this->givePermission($a['partner'],$a['manager'],'moderator',['leave.submit_for_student','leave.final']);
            $this->givePermission($a['partner'],$a['manager2'],'moderator',['leave.review','scope.workspace']);
            $this->actingAs($a['manager']->user)->post('/c/guarded-practice/app/leave',[
                'membership_id'=>$a['student']->id,'leave_type'=>'sick',
                'date_from'=>'2026-10-21','date_to'=>'2026-10-21',
                'reason'=>'Student requested an emergency doctor visit and authorized submission.',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $row=DB::table('leave_requests')->first();
            $this->assertSame($a['manager']->id,$row->initiated_by);
            $this->assertSame('pending',$row->status);
            $url="/c/guarded-practice/app/leave/{$row->id}/decision";
            $this->actingAs($a['manager']->user)->post($url,['decision'=>'approve'])->assertForbidden();
            $this->actingAs($a['partner']->user)->post($url,['decision'=>'approve'])
                ->assertSessionHasErrors('decision');
            $this->actingAs($a['manager2']->user)->post($url,['decision'=>'approve'])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('leave_requests',[
                'id'=>$row->id,'status'=>'manager_reviewed','first_reviewed_by'=>$a['manager2']->id,
            ]);
            $this->actingAs($a['manager2']->user)->post($url,['decision'=>'approve'])->assertForbidden();
            $this->actingAs($a['manager']->user)->post($url,['decision'=>'approve'])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('leave_requests',['id'=>$row->id,'status'=>'approved',
                'first_reviewed_by'=>$a['manager2']->id,'reviewed_by'=>$a['manager']->id]);
            $this->assertDatabaseCount('payroll_line_adjustments',0);
        } finally {$this->clearClock();}
    }

    public function test_delegated_payroll_requires_workspace_scope_and_explicit_financial_grant():void
    {
        $this->setupClock();
        try {
            [, $t]=$this->fixture();$a=$t['unit-a'];
            $this->givePermission($a['partner'],$a['manager'],'moderator',['payroll.manage']);
            $this->actingAs($a['manager']->user)
                ->get('/c/guarded-practice/app/payroll')->assertForbidden();
            $this->givePermission($a['partner'],$a['manager'],'moderator',['payroll.manage','scope.workspace']);
            $this->actingAs($a['manager']->user)->get('/c/guarded-practice/app/payroll')->assertOk();
            $this->actingAs($t['unit-b']['manager']->user)->get('/c/guarded-practice/app/payroll')->assertForbidden();
        } finally {$this->clearClock();}
    }

    public function test_delegated_expense_requires_separate_first_and_final_approval(): void
    {
        $this->setupClock();
        try {
            [$company,$t]=$this->fixture();$a=$t['unit-a'];
            DB::table('workspace_expense_policies')->insert([
                'company_id'=>$company,'workspace_id'=>$a['partner']->workspace,
                'expense_year'=>2026,'max_single_claim'=>50000,'monthly_student_limit'=>100000,
                'submission_window_days'=>90,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->givePermission($a['partner'],$a['manager'],'moderator',['claims.submit_for_student','claims.final']);
            $this->givePermission($a['partner'],$a['manager2'],'moderator',['claims.review','scope.workspace']);
            $this->actingAs($a['manager']->user)->post('/c/guarded-practice/app/claims',[
                'membership_id'=>$a['student']->id,'journey_date'=>'2026-10-19',
                'expense_category'=>'transport','journey_route'=>'Office to audit client',
                'purpose'=>'Client site stock verification on authorized official duty.',
                'amount'=>'150.00',
                'receipt'=>\Illuminate\Http\UploadedFile::fake()->create('receipt.pdf',25,'application/pdf'),
            ])->assertRedirect()->assertSessionHasNoErrors();
            $row=DB::table('conveyance_claims')->first();
            $this->assertSame($a['student']->id,$row->membership_id);
            $this->assertSame($a['manager']->id,$row->initiated_by);
            $this->assertNotNull($row->receipt_path);
            $url="/c/guarded-practice/app/claims/{$row->id}/decision";
            $this->actingAs($a['manager2']->user)->post($url,[
                'decision'=>'approve','approved_amount'=>'150',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('conveyance_claims',[
                'id'=>$row->id,'status'=>'manager_reviewed','first_reviewed_by'=>$a['manager2']->id,
            ]);
            $this->actingAs($a['manager2']->user)->post($url,['decision'=>'approve'])
                ->assertForbidden();
            $this->actingAs($a['manager']->user)->post($url,[
                'decision'=>'approve','approved_amount'=>'150',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('conveyance_claims',[
                'id'=>$row->id,'status'=>'approved','reviewed_by'=>$a['manager']->id,
                'approved_amount'=>150,
            ]);
            $this->assertDatabaseCount('payroll_line_adjustments',0);
        } finally {$this->clearClock();}
    }

}
