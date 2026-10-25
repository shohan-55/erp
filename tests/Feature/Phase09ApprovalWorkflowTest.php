<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ApprovalFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Phase09ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function team(string $slug='approval-firm'): array
    {
        $c=DB::table('companies')->insertGetId([
            'name'=>'Test Firm','slug'=>$slug,'active'=>true,
            'timezone'=>'Asia/Dhaka','office_start'=>'10:00:00',
            'office_end'=>'18:00:00','grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]','created_at'=>now(),'updated_at'=>now(),
        ]);
        $w=DB::table('workspaces')->insertGetId([
            'company_id'=>$c,'slug'=>'unit','name'=>'Practice Unit',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $members=[];
        foreach(['partner','manager','student'] as $role){
            $user=User::factory()->create();
            $id=DB::table('memberships')->insertGetId([
                'company_id'=>$c,'workspace_id'=>$w,'user_id'=>$user->id,
                'role'=>$role,'active'=>true,'monthly_allowance'=>'8000.00',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $members[$role]=(object)['id'=>$id,'user'=>$user];
        }
        DB::table('memberships')->where('id',$members['student']->id)
            ->update(['manager_membership_id'=>$members['manager']->id]);
        return compact('c','w','members','slug');
    }

    private function change(array $team,string $module,string $mode)
    {
        return $this->post('/c/'.$team['slug'].'/app/settings/approval-workflows',[
            'module'=>$module,'mode'=>$mode,
            'reason'=>'Workspace Partner authorized this approval routing.',
        ]);
    }

    private function leave(array $team,?string $mode): int
    {
        return DB::table('leave_requests')->insertGetId([
            'company_id'=>$team['c'],'workspace_id'=>$team['w'],
            'membership_id'=>$team['members']['student']->id,
            'leave_type'=>'annual','date_from'=>'2026-11-23',
            'date_to'=>'2026-11-23','reason'=>'An authorized absence request',
            'status'=>'pending','approval_mode'=>$mode,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function test_partner_only_approves_directly_but_manager_cannot(): void
    {
        $t=$this->team();
        $this->actingAs($t['members']['partner']->user)
            ->get('/c/approval-firm/app/settings/approval-workflows')
            ->assertOk()->assertSeeText('Approval Workflow Builder');
        $this->change($t,'leave','partner_only')->assertRedirect()->assertSessionHasNoErrors();
        $student=DB::table('memberships')->find($t['members']['student']->id);
        $this->assertSame('partner_only',ApprovalFlow::forSubmission($student,'leave'));
        $id=$this->leave($t,'partner_only');
        $this->actingAs($t['members']['manager']->user)
            ->post("/c/approval-firm/app/leave/{$id}/decision",['decision'=>'approve'])
            ->assertSessionHasErrors('decision');
        $this->actingAs($t['members']['partner']->user)
            ->post("/c/approval-firm/app/leave/{$id}/decision",['decision'=>'approve'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests',['id'=>$id,'status'=>'approved']);
        $this->assertDatabaseHas('workspace_approval_events',[
            'module'=>'leave','request_id'=>$id,
            'previous_status'=>'pending','new_status'=>'approved',
        ]);
        $this->assertDatabaseCount('workspace_approval_events',1);
    }

    public function test_legacy_two_step_review_remains_required(): void
    {
        $t=$this->team();
        $id=$this->leave($t,null);
        $url="/c/approval-firm/app/leave/{$id}/decision";
        $this->actingAs($t['members']['partner']->user)->post($url,['decision'=>'approve'])
            ->assertSessionHasErrors('decision');
        $this->actingAs($t['members']['manager']->user)->post($url,['decision'=>'approve'])
            ->assertRedirect();
        $this->assertDatabaseHas('leave_requests',['id'=>$id,'status'=>'manager_reviewed']);
        $this->actingAs($t['members']['partner']->user)->post($url,['decision'=>'approve'])
            ->assertRedirect();
        $this->assertDatabaseHas('leave_requests',['id'=>$id,'status'=>'approved']);
        $this->assertDatabaseCount('workspace_approval_events',2);
    }
    public function test_assigned_manager_may_final_approve_claim_only_when_policy_authorizes_it(): void
    {
        $t=$this->team();
        $this->actingAs($t['members']['partner']->user)
            ->change($t,'conveyance','manager_final')
            ->assertRedirect()->assertSessionHasNoErrors();
        $student=DB::table('memberships')->find($t['members']['student']->id);
        $this->assertSame('manager_final',ApprovalFlow::forSubmission($student,'conveyance'));
        $id=DB::table('conveyance_claims')->insertGetId([
            'company_id'=>$t['c'],'workspace_id'=>$t['w'],
            'membership_id'=>$t['members']['student']->id,
            'journey_date'=>'2026-10-10','journey_route'=>'Office to client site',
            'purpose'=>'Financial audit client fieldwork','amount'=>'150.00',
            'status'=>'pending','approval_mode'=>'manager_final',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $url="/c/approval-firm/app/claims/{$id}/decision";
        $this->post($url,[
            'decision'=>'approve','approved_amount'=>'100.00',
            'decision_note'=>'Partner cannot override assigned Manager.',
        ])->assertSessionHasErrors('decision');
        $this->actingAs($t['members']['manager']->user)
            ->post($url,['decision'=>'approve','approved_amount'=>'151.00'])
            ->assertSessionHasErrors('approved_amount');
        $this->post($url,[
            'decision'=>'approve','approved_amount'=>'120.00',
            'decision_note'=>'Reviewed against actual client travel costs.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('conveyance_claims',[
            'id'=>$id,'status'=>'approved','approved_amount'=>120,
            'amount'=>150,'manager_recommended_amount'=>null,
        ]);
        $this->assertDatabaseHas('workspace_approval_events',[
            'module'=>'conveyance','request_id'=>$id,
            'actor_membership_id'=>$t['members']['manager']->id,
            'new_status'=>'approved',
        ]);
    }

    public function test_existing_request_keeps_snapshot_when_partner_changes_mode(): void
    {
        $t=$this->team();
        $partner=$t['members']['partner'];
        $this->actingAs($partner->user)
            ->change($t,'leave','partner_only')->assertRedirect();
        $id=$this->leave($t,'partner_only');
        $this->change($t,'leave','manager_final')->assertRedirect();
        $student=DB::table('memberships')->find($t['members']['student']->id);
        $this->assertSame('manager_final',ApprovalFlow::forSubmission($student,'leave'));
        $this->actingAs($t['members']['manager']->user)
            ->post("/c/approval-firm/app/leave/{$id}/decision",['decision'=>'approve'])
            ->assertSessionHasErrors('decision');
        $this->actingAs($partner->user)
            ->post("/c/approval-firm/app/leave/{$id}/decision",['decision'=>'approve'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests',[
            'id'=>$id,'status'=>'approved','approval_mode'=>'partner_only',
        ]);
    }

    public function test_changes_are_partner_only_and_cross_firm_leave_is_not_visible(): void
    {
        $a=$this->team('alpha-firm');
        $b=$this->team('bravo-firm');
        $this->actingAs($a['members']['manager']->user)
            ->change($a,'leave','partner_only')->assertForbidden();
        $this->actingAs($a['members']['student']->user)
            ->get('/c/alpha-firm/app/settings/approval-workflows')->assertForbidden();
        $id=$this->leave($b,'partner_only');
        $this->actingAs($a['members']['partner']->user)
            ->post("/c/alpha-firm/app/leave/{$id}/decision",['decision'=>'approve'])
            ->assertNotFound();
        $this->change($a,'invalid','manager_final')->assertSessionHasErrors('module');
        $this->change($a,'leave','all_roles_admin')->assertSessionHasErrors('mode');
        $this->assertDatabaseCount('workspace_approval_policies',0);
    }

    public function test_manager_final_falls_back_to_partner_if_no_manager_on_submission(): void
    {
        $t=$this->team();
        $this->actingAs($t['members']['partner']->user)
            ->change($t,'leave','manager_final')->assertRedirect();
        DB::table('memberships')->where('id',$t['members']['manager']->id)
            ->update(['active'=>false]);
        $student=DB::table('memberships')->find($t['members']['student']->id);
        $this->assertSame('partner_only',ApprovalFlow::forSubmission($student,'leave'));
        $id=$this->leave($t,'partner_only');
        $this->post("/c/approval-firm/app/leave/{$id}/decision",[
            'decision'=>'approve',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests',['id'=>$id,'status'=>'approved']);
    }

}
