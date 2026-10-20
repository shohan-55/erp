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
}
