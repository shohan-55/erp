<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Phase15EngagementMilestonesTest extends TestCase
{
    use RefreshDatabase;

    private function clock(): void
    {
        $fixed=CarbonImmutable::parse('2026-10-20 13:00:00','Asia/Dhaka');
        CarbonImmutable::setTestNow($fixed);
        Carbon::setTestNow($fixed);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function firm(string $slug='project-test'): array
    {
        $company=DB::table('companies')->insertGetId([
            'slug'=>$slug,'name'=>'Isolated CA Firm','timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $ws=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'slug'=>'partner-one','name'=>'Partner One',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $m=[];
        foreach(['partner','manager','student','other'] as $role){
            $u=User::factory()->create();
            $id=DB::table('memberships')->insertGetId([
                'company_id'=>$company,'workspace_id'=>$ws,
                'user_id'=>$u->id,'role'=>$role==='other'?'student':$role,
                'active'=>true,'monthly_allowance'=>'5000.00',
                'activated_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            $m[$role]=(object)['id'=>$id,'user'=>$u];
        }
        DB::table('memberships')->where('id',$m['student']->id)
            ->update(['manager_membership_id'=>$m['manager']->id]);
        return compact('company','ws','m','slug');
    }

    private function project(array $f,string $code='AUD2026'): int
    {
        $actor=$f['m']['partner']->id;
        $client=DB::table('audit_clients')->insertGetId([
            'company_id'=>$f['company'],'workspace_id'=>$f['ws'],
            'code'=>'CLIENT'.$code,'name'=>'Client for '.$code,
            'active'=>true,'created_by'=>$actor,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        return DB::table('audit_engagements')->insertGetId([
            'company_id'=>$f['company'],'workspace_id'=>$f['ws'],
            'audit_client_id'=>$client,'code'=>$code,
            'title'=>'Statutory Assurance Engagement '.$code,
            'engagement_type'=>'statutory','starts_on'=>'2026-10-01',
            'ends_on'=>'2026-11-30','budget_hours'=>'10.00',
            'status'=>'open','created_by'=>$actor,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function assign(array $f,int $eng,int $member): void
    {
        DB::table('audit_engagement_assignments')->insert([
            'company_id'=>$f['company'],'workspace_id'=>$f['ws'],
            'engagement_id'=>$eng,'membership_id'=>$member,
            'active'=>true,'assigned_by'=>$f['m']['partner']->id,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function createTask(array $f,int $eng,int $member,array $extra=[])
    {
        return $this->post('/c/'.$f['slug'].'/app/engagements/'.$eng.'/milestones',[
            'membership_id'=>$member,
            'title'=>'Complete audit sample testing',
            'description'=>'Check the vouchers against the engagement plan.',
            'due_on'=>'2026-11-15',...$extra,
        ]);
    }

    private function submit(array $f,int $task)
    {
        return $this->post('/c/'.$f['slug'].'/app/engagements/milestones/'.$task.'/submit',[
            'submission_note'=>'Completed sample testing and indexed supporting working papers.',
        ]);
    }

    private function decide(array $f,int $task,string $decision)
    {
        return $this->post('/c/'.$f['slug'].'/app/engagements/milestones/'.$task.'/decision',[
            'decision'=>$decision,
            'decision_note'=>'Reviewed the recorded audit procedures and evidence for this task.',
        ]);
    }

    public function test_only_partner_can_assign_and_requires_active_engagement_team_and_dates(): void
    {
        $this->clock();$f=$this->firm();$eng=$this->project($f);
        $student=$f['m']['student']->id;
        $this->actingAs($f['m']['manager']->user)
            ->createTask($f,$eng,$student)->assertForbidden();
        $this->actingAs($f['m']['partner']->user)
            ->createTask($f,$eng,$student)->assertNotFound();
        $this->assign($f,$eng,$student);
        $this->createTask($f,$eng,$student,['due_on'=>'2027-01-01'])
            ->assertSessionHasErrors('due_on');
        $this->createTask($f,$eng,$student)->assertRedirect()->assertSessionHasNoErrors();
        $task=DB::table('audit_engagement_milestones')->firstOrFail();
        $this->assertSame('open',$task->status);
        $this->assertSame($student,(int)$task->membership_id);
        $this->assertDatabaseHas('tenant_audit_events',[
            'action'=>'audit.milestone.created','entity_id'=>$task->id,
        ]);
        $this->assertDatabaseHas('tenant_notifications',[
            'recipient_membership_id'=>$student,'title'=>'New engagement milestone',
        ]);
    }

    public function test_staff_submission_requires_own_task_and_manager_partner_review_without_self_approval(): void
    {
        $this->clock();$f=$this->firm();$eng=$this->project($f);
        $student=$f['m']['student']->id;$manager=$f['m']['manager']->id;
        $this->assign($f,$eng,$student);
        $this->actingAs($f['m']['partner']->user)->createTask($f,$eng,$student)
            ->assertRedirect()->assertSessionHasNoErrors();
        $task=(int)DB::table('audit_engagement_milestones')->value('id');
        $this->actingAs($f['m']['manager']->user)->submit($f,$task)->assertNotFound();
        $this->actingAs($f['m']['student']->user)->submit($f,$task)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->submit($f,$task)->assertSessionHasErrors('submission_note');
        $this->actingAs($f['m']['partner']->user)
            ->decide($f,$task,'approve')->assertSessionHasErrors('decision');
        $this->actingAs($f['m']['manager']->user)
            ->decide($f,$task,'approve')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_engagement_milestones',[
            'id'=>$task,'status'=>'manager_reviewed',
            'manager_reviewed_by'=>$manager,
        ]);
        $this->decide($f,$task,'approve')->assertSessionHasErrors('decision');
        $this->actingAs($f['m']['partner']->user)
            ->decide($f,$task,'approve')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_engagement_milestones',[
            'id'=>$task,'status'=>'completed',
            'decided_by'=>$f['m']['partner']->id,
        ]);
        $this->decide($f,$task,'approve')->assertSessionHasErrors('decision');
        $this->assertDatabaseHas('tenant_audit_events',[
            'action'=>'audit.milestone.completed','entity_id'=>$task,
        ]);
        $this->get('/c/project-test/app/engagements')->assertOk()
            ->assertSeeText('1 / 1')->assertSeeText('completed');
    }

    public function test_manager_owned_milestone_can_only_be_completed_by_partner(): void
    {
        $this->clock();$f=$this->firm();$eng=$this->project($f);
        $manager=$f['m']['manager']->id;
        $this->assign($f,$eng,$manager);
        $this->actingAs($f['m']['partner']->user)->createTask($f,$eng,$manager)->assertRedirect();
        $task=(int)DB::table('audit_engagement_milestones')->value('id');
        $this->actingAs($f['m']['manager']->user)->submit($f,$task)->assertRedirect();
        $this->decide($f,$task,'approve')->assertNotFound();
        $this->actingAs($f['m']['partner']->user)
            ->decide($f,$task,'approve')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_engagement_milestones',[
            'id'=>$task,'status'=>'completed',
        ]);
    }

    public function test_partner_rejection_allows_same_assignee_resubmission_and_logs_provenance(): void
    {
        $this->clock();$f=$this->firm();$eng=$this->project($f);
        $student=$f['m']['student']->id;
        $this->assign($f,$eng,$student);
        $this->actingAs($f['m']['partner']->user)->createTask($f,$eng,$student)->assertRedirect();
        $task=(int)DB::table('audit_engagement_milestones')->value('id');
        $this->actingAs($f['m']['student']->user)->submit($f,$task)->assertRedirect();
        $this->actingAs($f['m']['partner']->user)->decide($f,$task,'reject')
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_engagement_milestones',['id'=>$task,'status'=>'rejected']);
        $this->actingAs($f['m']['student']->user)->submit($f,$task)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tenant_audit_events',[
            'action'=>'audit.milestone.submitted','entity_id'=>$task,
        ]);
        $this->assertDatabaseHas('audit_engagement_milestones',[
            'id'=>$task,'status'=>'submitted','manager_reviewed_by'=>null,'decided_by'=>null,
        ]);
    }

    public function test_pending_milestone_prevents_engagement_close_unassign_and_staff_exit(): void
    {
        $this->clock();$f=$this->firm();$eng=$this->project($f);
        $student=$f['m']['student']->id;
        $this->assign($f,$eng,$student);
        $this->actingAs($f['m']['partner']->user)->createTask($f,$eng,$student)->assertRedirect();
        $task=(int)DB::table('audit_engagement_milestones')->value('id');
        $this->post("/c/project-test/app/engagements/{$eng}/close")
            ->assertSessionHasErrors('engagement');
        $this->post("/c/project-test/app/engagements/{$eng}/assign",[
            'membership_id'=>$student,'active'=>0,
        ])->assertSessionHasErrors('membership_id');
        $this->post("/c/project-test/app/staff/{$student}/lifecycle",[
            'action'=>'exit',
            'reason'=>'Completed employment contract after verifying all open tasks.',
        ])->assertSessionHasErrors('action');

        $manager=$f['m']['manager']->id;
        $this->assign($f,$eng,$manager);
        $this->post("/c/project-test/app/engagements/milestones/{$task}/update",[
            'title'=>'Finish documented sample testing',
            'membership_id'=>$manager,'due_on'=>'2026-11-20',
            'reason'=>'Partner reassigned ownership after the employee work handoff.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post("/c/project-test/app/engagements/{$eng}/assign",[
            'membership_id'=>$student,'active'=>0,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post("/c/project-test/app/staff/{$student}/lifecycle",[
            'action'=>'exit','reason'=>'All engagement assignments and obligations are cleared.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('memberships',['id'=>$student,'active'=>false]);
        $this->assertDatabaseHas('audit_engagement_milestones',[
            'id'=>$task,'membership_id'=>$manager,'status'=>'open',
        ]);
    }

    public function test_foreign_membership_and_cross_partner_milestone_routes_are_not_accessible(): void
    {
        $this->clock();$a=$this->firm('project-a');$b=$this->firm('project-b');
        $eng=$this->project($a);$student=$a['m']['student']->id;
        $this->assign($a,$eng,$student);
        $this->actingAs($a['m']['partner']->user)
            ->createTask($a,$eng,$b['m']['student']->id)->assertNotFound();
        $this->createTask($a,$eng,$student)->assertRedirect();
        $task=(int)DB::table('audit_engagement_milestones')->value('id');
        $this->actingAs($b['m']['partner']->user)
            ->decide($b,$task,'approve')->assertNotFound();
        $this->actingAs($b['m']['student']->user)
            ->submit($b,$task)->assertNotFound();
        $this->actingAs($b['m']['partner']->user)
            ->get('/c/project-b/app/engagements')->assertOk()
            ->assertDontSeeText('Complete audit sample testing');
        $this->actingAs($a['m']['other']->user)
            ->get('/c/project-a/app/engagements')->assertOk()
            ->assertDontSeeText('Complete audit sample testing');
        $this->assertDatabaseCount('audit_engagement_milestones',1);
    }

    public function test_unfinished_milestone_cannot_be_mutated_after_submission(): void
    {
        $this->clock();$f=$this->firm();$eng=$this->project($f);
        $student=$f['m']['student']->id;$manager=$f['m']['manager']->id;
        $this->assign($f,$eng,$student);$this->assign($f,$eng,$manager);
        $this->actingAs($f['m']['partner']->user)->createTask($f,$eng,$student)->assertRedirect();
        $task=(int)DB::table('audit_engagement_milestones')->value('id');
        $this->actingAs($f['m']['student']->user)->submit($f,$task)->assertRedirect();
        $this->actingAs($f['m']['partner']->user)
            ->post("/c/project-test/app/engagements/milestones/{$task}/update",[
                'membership_id'=>$manager,'title'=>'Unauthorized change',
                'due_on'=>'2026-11-20','reason'=>'Attempted update after work was submitted for review.',
            ])->assertSessionHasErrors('title');
        $this->assertDatabaseHas('audit_engagement_milestones',[
            'id'=>$task,'membership_id'=>$student,'status'=>'submitted',
        ]);
    }
}
