<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Phase11DelegatedLeaveTest extends TestCase
{
    use RefreshDatabase;

    private function firm(string $slug = 'delegated-leave'): array
    {
        $company = DB::table('companies')->insertGetId([
            'slug'=>$slug, 'name'=>'Scoped Practice',
            'active'=>true, 'timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00', 'office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'slug'=>'team','name'=>'Practice Team',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $members = [];
        foreach (['partner','manager','student','other_student'] as $role) {
            $user = User::factory()->create();
            $id = DB::table('memberships')->insertGetId([
                'company_id'=>$company,'workspace_id'=>$workspace,
                'user_id'=>$user->id,
                'role'=>$role==='other_student'?'student':$role,
                'monthly_allowance'=>0,'active'=>true,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $members[$role]=(object)['id'=>$id,'user'=>$user];
        }
        DB::table('memberships')->where('id',$members['student']->id)->update([
            'manager_membership_id'=>$members['manager']->id,
        ]);
        return compact('company','workspace','members','slug');
    }

    private function grant(array $firm,array $rights): int
    {
        return DB::table('workspace_access_grants')->insertGetId([
            'company_id'=>$firm['company'],
            'workspace_id'=>$firm['workspace'],
            'membership_id'=>$firm['members']['manager']->id,
            'access_role'=>'moderator',
            'delegation_mode'=>'limited',
            'permissions'=>json_encode($rights, JSON_THROW_ON_ERROR),
            'granted_by'=>$firm['members']['partner']->id,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function futureWorkday(): string
    {
        $day=CarbonImmutable::now('Asia/Dhaka')->addDays(12);
        while (in_array($day->dayOfWeekIso,[5,6],true)) $day=$day->addDay();
        return $day->toDateString();
    }

    private function submit(array $firm,array $override=[])
    {
        $day=$this->futureWorkday();
        return $this->post('/c/'.$firm['slug'].'/app/leave/delegated',[
            'membership_id'=>$firm['members']['student']->id,
            'leave_type'=>'annual','day_portion'=>'full',
            'date_from'=>$day,'date_to'=>$day,
            'reason'=>'Student requires approved personal leave for an appointment.',
            'delegation_reason'=>'The student confirmed their request to the assigned Manager.',
            ...$override,
        ]);
    }

    public function test_manager_must_be_explicitly_delegated_and_student_cannot_impersonate(): void
    {
        $f=$this->firm();
        $this->actingAs($f['members']['manager']->user)
            ->submit($f)->assertForbidden();
        $this->actingAs($f['members']['student']->user)
            ->submit($f)->assertForbidden();
        $this->actingAs($f['members']['partner']->user)
            ->submit($f)->assertForbidden();
        $this->assertDatabaseCount('leave_requests',0);
        $this->grant($f,['leave.submit_for']);
        $this->actingAs($f['members']['manager']->user)->get('/c/delegated-leave/app/leave')
            ->assertOk()->assertSee('Submit Leave for an Assigned Student');
        $this->submit($f)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests',[
            'company_id'=>$f['company'],'workspace_id'=>$f['workspace'],
            'membership_id'=>$f['members']['student']->id,
            'initiated_by'=>$f['members']['manager']->id,
            'status'=>'pending','approval_mode'=>'partner_only',
        ]);
        $this->assertDatabaseHas('tenant_audit_events',[
            'company_id'=>$f['company'],'action'=>'leave.submit_for',
        ]);
        $this->assertDatabaseHas('tenant_notifications',[
            'recipient_membership_id'=>$f['members']['student']->id,
            'title'=>'Leave submitted on your behalf',
        ]);
    }

    public function test_manager_created_leave_requires_independent_partner_even_under_manager_final_policy(): void
    {
        $f=$this->firm();
        $this->grant($f,['leave.submit_for']);
        DB::table('workspace_approval_policies')->insert([
            'company_id'=>$f['company'],'workspace_id'=>$f['workspace'],
            'module'=>'leave','mode'=>'manager_final','active'=>true,
            'reason'=>'Regular self-submitted leave is manager-final.',
            'changed_by'=>$f['members']['partner']->id,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->actingAs($f['members']['manager']->user)->submit($f)
            ->assertRedirect()->assertSessionHasNoErrors();
        $request=DB::table('leave_requests')->firstOrFail();
        $this->post("/c/delegated-leave/app/leave/{$request->id}/decision",[
            'decision'=>'approve',
        ])->assertSessionHasErrors('decision');
        $this->assertDatabaseHas('leave_requests',[
            'id'=>$request->id,'status'=>'pending',
        ]);
        $this->actingAs($f['members']['partner']->user)
            ->post("/c/delegated-leave/app/leave/{$request->id}/decision",[
                'decision'=>'approve',
            ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests',[
            'id'=>$request->id,'status'=>'approved',
        ]);
        $this->assertDatabaseHas('workspace_approval_events',[
            'module'=>'leave','request_id'=>$request->id,'new_status'=>'approved',
            'actor_membership_id'=>$f['members']['partner']->id,
        ]);
    }

    public function test_unassigned_and_cross_company_students_are_not_targetable(): void
    {
        $a=$this->firm('team-a');
        $b=$this->firm('team-b');
        $this->grant($a,['leave.submit_for']);
        $this->actingAs($a['members']['manager']->user)
            ->submit($a,['membership_id'=>$a['members']['other_student']->id])->assertNotFound();
        $this->submit($a,['membership_id'=>$b['members']['student']->id])->assertNotFound();
        $this->assertDatabaseCount('leave_requests',0);
        $this->actingAs($b['members']['manager']->user)->get('/c/team-a/app/leave')->assertForbidden();
    }

    public function test_duplicate_overlap_and_invalid_dates_still_block_delegates(): void
    {
        $f=$this->firm();
        $this->grant($f,['leave.submit_for']);
        $this->actingAs($f['members']['manager']->user)->submit($f)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->submit($f)->assertSessionHasErrors('date_from');
        $this->submit($f,['date_from'=>'2025-01-01','date_to'=>'2025-01-01'])
            ->assertSessionHasErrors('date_from');
        $this->submit($f,['day_portion'=>'morning','date_to'=>$this->futureWorkday(),
            'date_from'=>CarbonImmutable::parse($this->futureWorkday())->subDay()->toDateString()])
            ->assertSessionHasErrors('day_portion');
        $this->submit($f,['delegation_reason'=>'too short'])
            ->assertSessionHasErrors('delegation_reason');
        $this->assertDatabaseCount('leave_requests',1);
    }

    public function test_payroll_frozen_month_blocks_delegated_submission(): void
    {
        $f=$this->firm();
        $this->grant($f,['leave.submit_for']);
        DB::table('payroll_runs')->insert([
            'company_id'=>$f['company'],'workspace_id'=>$f['workspace'],
            'period_month'=>substr($this->futureWorkday(),0,7).'-01',
            'status'=>'locked','policy_snapshot'=>'{}',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->actingAs($f['members']['manager']->user)
            ->submit($f)->assertSessionHasErrors('date_from');
        $this->assertDatabaseCount('leave_requests',0);
    }

    public function test_withdrawal_requires_ownership_and_separate_live_permission(): void
    {
        $f=$this->firm();
        $grant=$this->grant($f,['leave.submit_for']);
        $this->actingAs($f['members']['manager']->user)->submit($f)
            ->assertRedirect()->assertSessionHasNoErrors();
        $id=DB::table('leave_requests')->value('id');
        $url="/c/delegated-leave/app/leave/{$id}/delegated-withdraw";
        $this->post($url,['reason'=>'The student cancelled the travel appointment.'])
            ->assertForbidden();
        DB::table('workspace_access_grants')->where('id',$grant)->update([
            'permissions'=>json_encode(['leave.submit_for','leave.withdraw_for']),
        ]);
        $this->post($url,['reason'=>'The student cancelled the appointment and informed our team.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests',[
            'id'=>$id,'status'=>'withdrawn',
            'withdrawn_by'=>$f['members']['manager']->id,
        ]);
        $this->assertDatabaseHas('tenant_audit_events',[
            'action'=>'leave.withdrawn_for','entity_id'=>$id,
        ]);
        $this->post($url,['reason'=>'Attempt to withdraw a request twice must be blocked.'])
            ->assertSessionHasErrors('reason');
    }

    public function test_revoked_rights_block_new_submission_and_withdrawal(): void
    {
        $f=$this->firm();
        $grant=$this->grant($f,['leave.submit_for','leave.withdraw_for']);
        $this->actingAs($f['members']['manager']->user)->submit($f)
            ->assertRedirect()->assertSessionHasNoErrors();
        $id=DB::table('leave_requests')->value('id');
        DB::table('workspace_access_grants')->where('id',$grant)->update([
            'revoked_at'=>now(),'revoked_by'=>$f['members']['partner']->id,
        ]);
        $this->submit($f)->assertForbidden();
        $this->post("/c/delegated-leave/app/leave/{$id}/delegated-withdraw",[
            'reason'=>'Permission revoked prior to further Manager intervention.',
        ])->assertForbidden();
        $this->assertDatabaseHas('leave_requests',['id'=>$id,'status'=>'pending']);
    }

    public function test_student_self_request_still_keeps_existing_workflow_and_cannot_spoof_initiator(): void
    {
        $f=$this->firm();
        $input=[
            'leave_type'=>'annual','date_from'=>$this->futureWorkday(),
            'date_to'=>$this->futureWorkday(),
            'reason'=>'Regular annual leave requested directly by student.',
        ];
        $this->actingAs($f['members']['student']->user)->post('/c/delegated-leave/app/leave',[
            ...$input,'membership_id'=>$f['members']['other_student']->id,
        ])->assertSessionHasErrors('membership_id');
        $this->post('/c/delegated-leave/app/leave',$input)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests',[
            'membership_id'=>$f['members']['student']->id,
            'initiated_by'=>$f['members']['student']->id,
            'approval_mode'=>'manager_partner',
        ]);
    }
}
