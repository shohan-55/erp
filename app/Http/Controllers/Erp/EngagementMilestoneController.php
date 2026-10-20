<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EngagementMilestoneController extends Controller
{
    private function lockWorkspace(object $actor): void
    {
        DB::table('workspaces')->where('company_id',$actor->company_id)
            ->where('id',$actor->workspace_id)->lockForUpdate()->first();
    }

    private function milestone(object $actor,int $id): object
    {
        $row=DB::table('audit_engagement_milestones')->where('id',$id)
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)->lockForUpdate()->first();
        abort_unless($row,404);
        return $row;
    }

    private function openEngagement(object $actor,int $id): object
    {
        $eng=DB::table('audit_engagements as e')
            ->join('audit_clients as c','c.id','=','e.audit_client_id')
            ->where('e.company_id',$actor->company_id)
            ->where('e.workspace_id',$actor->workspace_id)
            ->where('e.id',$id)->where('e.status','open')
            ->where('c.company_id',$actor->company_id)
            ->where('c.workspace_id',$actor->workspace_id)
            ->where('c.active',true)->select('e.*')->first();
        abort_unless($eng,404);
        return $eng;
    }

    private function assignedMember(object $actor,object $eng,int $memberId): object
    {
        $member=DB::table('memberships')->where('id',$memberId)
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->whereIn('role',['manager','student'])->where('active',true)
            ->whereNull('platform_suspended_at')->first();
        abort_unless($member,404);
        $assignment=DB::table('audit_engagement_assignments')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('engagement_id',$eng->id)
            ->where('membership_id',$member->id)->where('active',true)->exists();
        abort_unless($assignment,404);
        return $member;
    }

    public function store(Request $request,string $firmSlug,int $engagementId)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate([
            'membership_id'=>['required','integer','min:1'],
            'title'=>['required','string','min:3','max:180'],
            'description'=>['nullable','string','max:2000'],
            'due_on'=>['required','date_format:Y-m-d'],
        ]);
        DB::transaction(function()use($actor,$engagementId,$data){
            $this->lockWorkspace($actor);
            $eng=$this->openEngagement($actor,$engagementId);
            $staff=$this->assignedMember($actor,$eng,(int)$data['membership_id']);
            if($data['due_on']<$eng->starts_on || $data['due_on']>$eng->ends_on){
                throw ValidationException::withMessages([
                    'due_on'=>'Milestone due date must be inside the engagement period.',
                ]);
            }
            $id=DB::table('audit_engagement_milestones')->insertGetId([
                'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,
                'engagement_id'=>$eng->id,'membership_id'=>$staff->id,
                'title'=>trim($data['title']),
                'description'=>isset($data['description'])?trim($data['description']):null,
                'due_on'=>$data['due_on'],'status'=>'open',
                'created_by'=>$actor->id,'created_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'audit.milestone.created','audit_engagement_milestone',$id,[
                'engagement_id'=>$eng->id,'assigned_member_id'=>$staff->id,
                'due_on'=>$data['due_on'],
            ]);
            TenantOps::notify($actor,$staff->id,'New engagement milestone',
                'A Partner assigned a milestone under engagement '.$eng->code.'.');
        },3);
        return back()->with('success','Engagement milestone assigned with an audit record.');
    }

    public function update(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate([
            'membership_id'=>['required','integer','min:1'],
            'title'=>['required','string','min:3','max:180'],
            'description'=>['nullable','string','max:2000'],
            'due_on'=>['required','date_format:Y-m-d'],
            'reason'=>['required','string','min:15','max:1000'],
        ]);
        DB::transaction(function()use($actor,$id,$data){
            $this->lockWorkspace($actor);
            $row=$this->milestone($actor,$id);
            if(!in_array($row->status,['open','rejected'],true)){
                throw ValidationException::withMessages(['title'=>'Submitted and completed milestones cannot be reassigned or rewritten.']);
            }
            $eng=$this->openEngagement($actor,(int)$row->engagement_id);
            $new=$this->assignedMember($actor,$eng,(int)$data['membership_id']);
            if($data['due_on']<$eng->starts_on || $data['due_on']>$eng->ends_on){
                throw ValidationException::withMessages(['due_on'=>'Due date must remain inside engagement dates.']);
            }
            DB::table('audit_engagement_milestones')->where('id',$id)->update([
                'membership_id'=>$new->id,'title'=>trim($data['title']),
                'description'=>isset($data['description'])?trim($data['description']):null,
                'due_on'=>$data['due_on'],'status'=>'open',
                'submitted_at'=>null,'submission_note'=>null,
                'manager_reviewed_by'=>null,'manager_recommendation'=>null,
                'decided_by'=>null,'decided_at'=>null,'decision_note'=>null,
                'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'audit.milestone.updated','audit_engagement_milestone',$id,[
                'previous_member_id'=>$row->membership_id,'new_member_id'=>$new->id,
                'previous_due_on'=>$row->due_on,'new_due_on'=>$data['due_on'],
                'previous_status'=>$row->status,'reason'=>trim($data['reason']),
            ]);
            if((int)$row->membership_id!==(int)$new->id){
                TenantOps::notify($actor,$new->id,'Engagement milestone reassigned',
                    'A Partner assigned you a milestone under '.$eng->code.'.');
            }
        },3);
        return back()->with('success','Milestone updated with the previous assignment retained in audit history.');
    }

    public function submit(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['manager','student']);
        $data=$request->validate([
            'submission_note'=>['required','string','min:15','max:2000'],
        ]);
        DB::transaction(function()use($actor,$id,$data){
            $this->lockWorkspace($actor);
            $row=$this->milestone($actor,$id);
            // A manager cannot impersonate an assigned student.
            abort_unless((int)$row->membership_id===(int)$actor->id,404);
            $eng=$this->openEngagement($actor,(int)$row->engagement_id);
            $this->assignedMember($actor,$eng,(int)$actor->id);
            if(!in_array($row->status,['open','rejected'],true)){
                throw ValidationException::withMessages([
                    'submission_note'=>'Only open or rejected milestones may be submitted again.',
                ]);
            }
            DB::table('audit_engagement_milestones')->where('id',$id)->update([
                'status'=>'submitted','submission_note'=>trim($data['submission_note']),
                'submitted_at'=>now(),'manager_reviewed_by'=>null,
                'manager_recommendation'=>null,'decided_by'=>null,
                'decided_at'=>null,'decision_note'=>null,'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'audit.milestone.submitted','audit_engagement_milestone',$id,[
                'engagement_id'=>$eng->id,'staff_id'=>$actor->id,
                'previous_status'=>$row->status,'note'=>trim($data['submission_note']),
            ]);
            foreach(TenantOps::teamRecipients($actor,$actor) as $recipient){
                TenantOps::notify($actor,(int)$recipient,'Milestone awaits review',
                    'An engagement milestone was submitted for review.');
            }
        },3);
        return back()->with('success','Milestone submitted for independent review.');
    }

    public function decide(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['manager','partner']);
        $data=$request->validate([
            'decision'=>['required','in:approve,reject'],
            'decision_note'=>['required','string','min:15','max:1000'],
        ]);
        DB::transaction(function()use($actor,$id,$data){
            $this->lockWorkspace($actor);
            $row=$this->milestone($actor,$id);
            $eng=$this->openEngagement($actor,(int)$row->engagement_id);
            $member=DB::table('memberships')->where('id',$row->membership_id)
                ->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)->first();
            abort_unless($member,404);
            if(!in_array($row->status,['submitted','manager_reviewed'],true)){
                throw ValidationException::withMessages(['decision'=>'Milestone is not awaiting review.']);
            }
            if($actor->role==='manager'){
                abort_unless($member->role==='student'
                    && (int)$member->manager_membership_id===(int)$actor->id,404);
                if($row->status!=='submitted'){
                    throw ValidationException::withMessages(['decision'=>'Manager recommendation already recorded.']);
                }
                DB::table('audit_engagement_milestones')->where('id',$id)->update([
                    'status'=>'manager_reviewed',
                    'manager_reviewed_by'=>$actor->id,
                    'manager_recommendation'=>$data['decision'],
                    'decision_note'=>trim($data['decision_note']),'updated_at'=>now(),
                ]);
                TenantOps::audit($actor,'audit.milestone.recommended','audit_engagement_milestone',$id,[
                    'decision'=>$data['decision'],'note'=>trim($data['decision_note']),
                ]);
                foreach(DB::table('memberships')->where('company_id',$actor->company_id)
                    ->where('workspace_id',$actor->workspace_id)->where('role','partner')
                    ->where('active',true)->pluck('id') as $partnerId){
                    TenantOps::notify($actor,(int)$partnerId,'Milestone awaiting Partner decision',
                        'A Manager recommended a decision on '.$eng->code.'.');
                }
                return;
            }
            if($data['decision']==='approve' && $row->status==='submitted'
                && $member->role==='student' && $member->manager_membership_id
                && DB::table('memberships')->where('company_id',$actor->company_id)
                    ->where('workspace_id',$actor->workspace_id)
                    ->where('id',$member->manager_membership_id)
                    ->where('role','manager')->where('active',true)
                    ->whereNull('platform_suspended_at')->exists()){
                throw ValidationException::withMessages([
                    'decision'=>'The active assigned Manager must review this Student milestone first.',
                ]);
            }
            $new=$data['decision']==='approve'?'completed':'rejected';
            DB::table('audit_engagement_milestones')->where('id',$id)->update([
                'status'=>$new,'decided_by'=>$actor->id,
                'decided_at'=>now(),'decision_note'=>trim($data['decision_note']),
                'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'audit.milestone.'.$new,'audit_engagement_milestone',$id,[
                'engagement_id'=>$eng->id,'member_id'=>$member->id,
                'prior_status'=>$row->status,'decision_note'=>trim($data['decision_note']),
            ]);
            if($member->active){
                TenantOps::notify($actor,$member->id,'Engagement milestone '.$new,
                    'Partner reviewed your milestone on engagement '.$eng->code.'.');
            }
        },3);
        return back()->with('success','Independent milestone review decision recorded.');
    }
}
