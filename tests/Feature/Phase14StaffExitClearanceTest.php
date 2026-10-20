<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StaffExitClearance;
use App\Support\WorkspaceAccess;
use App\Support\WorkspaceRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Phase14StaffExitClearanceTest extends TestCase
{
    use RefreshDatabase;

    private function firm(string $slug='phase14'): array
    {
        $company=DB::table('companies')->insertGetId([
            'slug'=>$slug,'name'=>'Professional Workspace',
            'active'=>true,'timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $workspace=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'name'=>'Partner Practice','slug'=>'practice',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $members=[];
        foreach(['partner','manager','student'] as $role){
            $user=User::factory()->create();
            $id=DB::table('memberships')->insertGetId([
                'company_id'=>$company,'workspace_id'=>$workspace,
                'user_id'=>$user->id,'role'=>$role,
                'monthly_allowance'=>$role==='student'?'8000.00':'0.00',
                'active'=>true,'activated_at'=>now(),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $members[$role]=(object)['id'=>$id,'user'=>$user];
        }
        return compact('company','workspace','members','slug');
    }

    private function action(array $f,int $member,string $action='exit')
    {
        return $this->post('/c/'.$f['slug'].'/app/staff/'.$member.'/lifecycle',[
            'action'=>$action,
            'reason'=>'Partner verified the full exit or rejoin documents and approvals.',
        ]);
    }

    private function grant(array $f): int
    {
        return DB::table('workspace_access_grants')->insertGetId([
            'company_id'=>$f['company'],'workspace_id'=>$f['workspace'],
            'membership_id'=>$f['members']['manager']->id,
            'access_role'=>'moderator','delegation_mode'=>'limited',
            'permissions'=>json_encode(['attendance.proxy','leave.submit_for'],JSON_THROW_ON_ERROR),
            'granted_by'=>$f['members']['partner']->id,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function role(array $f): int
    {
        $id=DB::table('workspace_custom_roles')->insertGetId([
            'company_id'=>$f['company'],'workspace_id'=>$f['workspace'],
            'name'=>'Assigned Team Operations','slug'=>'team-operations',
            'permissions'=>json_encode(['organization.manage'],JSON_THROW_ON_ERROR),
            'active'=>true,'created_by'=>$f['members']['partner']->id,
            'updated_by'=>$f['members']['partner']->id,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('workspace_role_assignments')->insert([
            'company_id'=>$f['company'],'workspace_id'=>$f['workspace'],
            'membership_id'=>$f['members']['manager']->id,
            'role_id'=>$id,'assigned_by'=>$f['members']['partner']->id,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $id;
    }

    public function test_exit_preview_is_private_and_manager_exit_revokes_entitlements_before_rejoin(): void
    {
        $f=$this->firm();$other=$this->firm('other-firm');
        $manager=$f['members']['manager'];
        $grant=$this->grant($f);$role=$this->role($f);
        $actor=DB::table('memberships')->find($manager->id);
        $this->assertTrue(WorkspaceAccess::allows($actor,'attendance.proxy'));
        $this->assertTrue(WorkspaceRoles::allows($actor,'organization.manage'));

        $url='/c/phase14/app/staff/'.$manager->id.'/exit-clearance';
        $this->actingAs($manager->user)->get($url)->assertForbidden();
        $this->actingAs($other['members']['partner']->user)
            ->get('/c/other-firm/app/staff/'.$manager->id.'/exit-clearance')
            ->assertNotFound();
        $this->actingAs($f['members']['partner']->user)->get($url)
            ->assertOk()->assertSeeText('Staff Exit Clearance')
            ->assertSeeText('No outstanding workflow blocker');

        $this->action($f,$manager->id)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('memberships',['id'=>$manager->id,'active'=>false]);
        $this->assertDatabaseHas('staff_profiles',[
            'membership_id'=>$manager->id,'employment_status'=>'exited',
        ]);
        $this->assertDatabaseHas('workspace_access_grants',[
            'id'=>$grant,'revoked_by'=>$f['members']['partner']->id,
        ]);
        $this->assertNotNull(DB::table('workspace_access_grants')->where('id',$grant)
            ->value('revoked_at'));
        $this->assertDatabaseMissing('workspace_role_assignments',[
            'membership_id'=>$manager->id,'role_id'=>$role,
        ]);
        $this->assertDatabaseHas('staff_lifecycle_events',[
            'membership_id'=>$manager->id,'event_type'=>'exit',
        ]);
        $this->assertDatabaseHas('tenant_audit_events',[
            'action'=>'staff.exit','entity_id'=>$manager->id,
        ]);

        $this->action($f,$manager->id,'rejoin')->assertRedirect()->assertSessionHasNoErrors();
        $rejoined=DB::table('memberships')->find($manager->id);
        $this->assertTrue((bool)$rejoined->active);
        $this->assertFalse(WorkspaceAccess::allows($rejoined,'attendance.proxy'));
        $this->assertFalse(WorkspaceRoles::allows($rejoined,'organization.manage'));
        $this->assertDatabaseHas('staff_lifecycle_events',[
            'membership_id'=>$manager->id,'event_type'=>'rejoin',
        ]);
        $this->assertDatabaseCount('staff_lifecycle_events',2);
        $this->assertDatabaseCount('workspace_role_assignments',0);
        $this->assertDatabaseCount('workspace_access_grants',1);
    }

    public function test_outstanding_manager_delegated_leave_blocks_exit_until_resolved(): void
    {
        $f=$this->firm();
        $this->actingAs($f['members']['partner']->user);
        $manager=$f['members']['manager']->id;
        DB::table('leave_requests')->insert([
            'company_id'=>$f['company'],'workspace_id'=>$f['workspace'],
            'membership_id'=>$f['members']['student']->id,
            'initiated_by'=>$manager,'delegation_reason'=>'Manager submitted request for Student.',
            'leave_type'=>'annual','date_from'=>'2026-12-01','date_to'=>'2026-12-01',
            'reason'=>'Student confirmed planned leave.',
            'status'=>'pending','created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->get('/c/phase14/app/staff/'.$manager.'/exit-clearance')
            ->assertOk()->assertSeeText('Manager-initiated pending leave/claims');
        $this->action($f,$manager)->assertSessionHasErrors('action');
        $this->assertDatabaseHas('memberships',['id'=>$manager,'active'=>true]);
        DB::table('leave_requests')->update(['status'=>'withdrawn']);
        $this->action($f,$manager)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests',['initiated_by'=>$manager,'status'=>'withdrawn']);
    }

    public function test_pending_proxy_even_after_checkout_blocks_exit_until_partner_verifies(): void
    {
        $f=$this->firm();
        $manager=$f['members']['manager']->id;
        DB::table('attendance_sessions')->insert([
            'company_id'=>$f['company'],'workspace_id'=>$f['workspace'],
            'membership_id'=>$f['members']['student']->id,
            'work_date'=>'2026-10-15','work_mode'=>'office',
            'started_at'=>'2026-10-15 04:00:00','ended_at'=>'2026-10-15 11:00:00',
            'late'=>false,'holiday'=>false,
            'proxy_status'=>'pending','proxy_started_by'=>$manager,
            'proxy_start_reason'=>'Manual proxy attendance after outage.',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->actingAs($f['members']['partner']->user);
        $this->action($f,$manager)->assertSessionHasErrors('action');
        DB::table('attendance_sessions')->update(['proxy_status'=>'verified']);
        $this->action($f,$manager)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_sessions',[
            'proxy_started_by'=>$manager,'proxy_status'=>'verified',
        ]);
    }

    public function test_future_salary_and_pending_transfer_block_student_exit(): void
    {
        $f=$this->firm();
        $student=$f['members']['student']->id;$partner=$f['members']['partner']->id;
        $this->actingAs($f['members']['partner']->user);
        $month=now('Asia/Dhaka')->addMonths(2)->startOfMonth()->toDateString();
        DB::table('staff_compensation_changes')->insert([
            'company_id'=>$f['company'],'workspace_id'=>$f['workspace'],
            'membership_id'=>$student,'effective_month'=>$month,
            'old_amount'=>'8000.00','new_amount'=>'8500.00',
            'reason'=>'Approved future salary decision that needs resolution.',
            'authorized_by'=>$partner,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->action($f,$student)->assertSessionHasErrors('action');
        DB::table('staff_compensation_changes')->delete();
        $destination=DB::table('workspaces')->insertGetId([
            'company_id'=>$f['company'],'name'=>'Other Partner','slug'=>'other',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $id=DB::table('staff_transfer_requests')->insertGetId([
            'company_id'=>$f['company'],'source_workspace_id'=>$f['workspace'],
            'target_workspace_id'=>$destination,'source_membership_id'=>$student,
            'requested_by'=>$partner,'status'=>'pending',
            'reason'=>'Pending cross-practice transfer must be resolved.',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->action($f,$student)->assertSessionHasErrors('action');
        DB::table('staff_transfer_requests')->where('id',$id)->update(['status'=>'rejected']);
        $this->action($f,$student)->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_completed_transfer_and_non_exit_suspension_cannot_rejoin(): void
    {
        $f=$this->firm();$student=$f['members']['student']->id;
        $this->actingAs($f['members']['partner']->user);
        $this->action($f,$student)->assertRedirect()->assertSessionHasNoErrors();
        $target=DB::table('workspaces')->insertGetId([
            'company_id'=>$f['company'],'name'=>'Receiving Partner','slug'=>'other',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('staff_transfer_requests')->insert([
            'company_id'=>$f['company'],'source_workspace_id'=>$f['workspace'],
            'target_workspace_id'=>$target,'source_membership_id'=>$student,
            'requested_by'=>$f['members']['partner']->id,
            'status'=>'approved','reason'=>'Already transferred to destination identity.',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->action($f,$student,'rejoin')->assertSessionHasErrors('action');
        $this->assertDatabaseHas('memberships',['id'=>$student,'active'=>false]);
        DB::table('staff_transfer_requests')->delete();
        DB::table('staff_profiles')->where('membership_id',$student)
            ->update(['employment_status'=>'active']);
        $this->action($f,$student,'rejoin')->assertSessionHasErrors('action');
    }

    public function test_cross_partner_exit_denied_and_payroll_history_not_deleted(): void
    {
        $a=$this->firm('partner-a');$b=$this->firm('partner-b');
        $staff=$a['members']['student']->id;
        $this->actingAs($b['members']['partner']->user)
            ->post('/c/partner-b/app/staff/'.$staff.'/lifecycle',[
                'action'=>'exit','reason'=>'Attempted offboarding of foreign Partner staff member.',
            ])->assertNotFound();
        $this->actingAs($a['members']['partner']->user);
        $this->action($a,$staff)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('memberships',['id'=>$staff,'active'=>false]);
        $this->assertDatabaseCount('staff_lifecycle_events',1);
        $this->assertDatabaseCount('memberships',6);
    }
}
