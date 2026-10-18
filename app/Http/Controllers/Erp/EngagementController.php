<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EngagementController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner','manager','student']);
        $engagements=DB::table('audit_engagements as e')
            ->join('audit_clients as c','c.id','=','e.audit_client_id')
            ->where('e.company_id',$company->id)->where('e.workspace_id',$workspace->id)
            ->where('c.company_id',$company->id)->where('c.workspace_id',$workspace->id)
            ->when($actor->role!=='partner',function($q)use($actor){
                $q->whereExists(function($s)use($actor){
                    $s->selectRaw('1')->from('audit_engagement_assignments as a')
                        ->join('memberships as m','m.id','=','a.membership_id')
                        ->whereColumn('a.engagement_id','e.id')
                        ->whereColumn('a.company_id','e.company_id')
                        ->whereColumn('a.workspace_id','e.workspace_id')
                        ->whereColumn('m.workspace_id','e.workspace_id')
                        ->whereColumn('m.company_id','e.company_id')
                        ->where('a.active',true)
                        ->where(function($x)use($actor){
                            $x->where('m.id',$actor->id);
                            if($actor->role==='manager'){
                                $x->orWhere('m.manager_membership_id',$actor->id);
                            }
                        });
                });
            })
            ->select('e.*','c.name as client_name','c.code as client_code')
            ->orderByDesc('e.id')->limit(150)->get();
        $clients=$actor->role==='partner'
            ? DB::table('audit_clients')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->orderBy('name')->get()
            : collect();
        $people=$actor->role==='partner'
            ? DB::table('memberships as m')->join('users as u','u.id','=','m.user_id')
                ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
                ->whereIn('m.role',['manager','student'])->where('m.active',true)
                ->select('m.id','m.role','u.name')->orderBy('u.name')->get()
            : collect();
        $ids=$engagements->pluck('id')->all();
        $assignments=empty($ids)?collect():DB::table('audit_engagement_assignments as a')
            ->join('memberships as m','m.id','=','a.membership_id')
            ->join('users as u','u.id','=','m.user_id')
            ->where('a.company_id',$company->id)->where('a.workspace_id',$workspace->id)
            ->whereIn('a.engagement_id',$ids)->where('a.active',true)
            ->when($actor->role==='student',fn($q)=>$q->where('m.id',$actor->id))
            ->when($actor->role==='manager',fn($q)=>$q->where(function($x)use($actor){
                $x->where('m.id',$actor->id)->orWhere('m.manager_membership_id',$actor->id);
            }))
            ->select('a.engagement_id','a.membership_id','u.name','m.role')->get()->groupBy('engagement_id');
        $hours=empty($ids)?collect():DB::table('audit_timesheets')
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->whereIn('engagement_id',$ids)
            ->where('status','approved')
            ->when($actor->role==='student',fn($q)=>$q->where('membership_id',$actor->id))
            ->when($actor->role==='manager',fn($q)=>$q->whereIn('membership_id',
                DB::table('memberships')->where('company_id',$company->id)
                    ->where('workspace_id',$workspace->id)
                    ->where(function($x)use($actor){$x->where('id',$actor->id)
                        ->orWhere('manager_membership_id',$actor->id);})
                    ->select('id')))
            ->selectRaw('engagement_id, SUM(hours) as approved_hours')->groupBy('engagement_id')
            ->get()->keyBy('engagement_id');
        return response()->view('erp.engagements',compact(
            'company','workspace','actor','firmSlug','engagements','clients','people','assignments','hours'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function client(Request $request,string $firmSlug,?int $id=null)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate([
            'code'=>['required','string','regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/','max:40'],
            'name'=>['required','string','min:3','max:180'],
            'industry'=>['nullable','string','max:100'],
            'contact_name'=>['nullable','string','max:120'],
            'contact_email'=>['nullable','email','max:180'],
        ]);
        DB::transaction(function()use($actor,$company,$workspace,$data,$id){
            $this->workspaceLock($actor);
            $old=$id?DB::table('audit_clients')->where('id',$id)
                ->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->lockForUpdate()->first():null;
            if($id)abort_unless($old,404);
            if($old && !$old->active){
                throw ValidationException::withMessages(['code'=>'Archived clients cannot be edited.']);
            }
            $code=strtoupper(trim($data['code']));
            if(DB::table('audit_clients')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('code',$code)
                ->when($id,fn($q)=>$q->where('id','!=',$id))->exists()){
                throw ValidationException::withMessages(['code'=>'Client code already exists in your Partner practice.']);
            }
            $values=[
                'code'=>$code,'name'=>trim($data['name']),
                'industry'=>$data['industry']??null,'contact_name'=>$data['contact_name']??null,
                'contact_email'=>$data['contact_email']??null,
                'updated_at'=>now(),
            ];
            if($old){
                DB::table('audit_clients')->where('id',$id)->update($values);
                $key=$id;
            }else{
                $key=DB::table('audit_clients')->insertGetId([
                    'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                    ...$values,'active'=>true,'created_by'=>$actor->id,'created_at'=>now(),
                ]);
            }
            TenantOps::audit($actor,$old?'audit.client.updated':'audit.client.created',
                'audit_client',$key,['code'=>$code]);
        },3);
        return back()->with('success','Practice client saved within your Partner workspace.');
    }

    public function archiveClient(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        DB::transaction(function()use($actor,$id){
            $this->workspaceLock($actor);
            $client=DB::table('audit_clients')->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)->where('id',$id)
                ->lockForUpdate()->first();
            abort_unless($client,404);
            if(DB::table('audit_engagements')->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)
                ->where('audit_client_id',$client->id)->where('status','open')->exists()){
                throw ValidationException::withMessages(['client'=>'Close active engagements before archiving the client.']);
            }
            if($client->active){
                DB::table('audit_clients')->where('id',$id)->update(['active'=>false,'updated_at'=>now()]);
                TenantOps::audit($actor,'audit.client.archived','audit_client',$id);
            }
        },3);
        return back()->with('success','Client archived; historical audit work retained.');
    }

    public function engagement(Request $request,string $firmSlug,?int $id=null)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate([
            'audit_client_id'=>['required','integer','min:1'],
            'code'=>['required','string','regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/','max:50'],
            'title'=>['required','string','min:3','max:180'],
            'engagement_type'=>['required','in:statutory,internal,tax,advisory,other'],
            'period_end'=>['nullable','date_format:Y-m-d'],
            'starts_on'=>['required','date_format:Y-m-d'],
            'ends_on'=>['required','date_format:Y-m-d','after_or_equal:starts_on'],
            'budget_hours'=>['required','numeric','between:0,100000','decimal:0,2'],
        ]);
        DB::transaction(function()use($company,$workspace,$actor,$data,$id){
            $this->workspaceLock($actor);
            $client=DB::table('audit_clients')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)
                ->where('id',$data['audit_client_id'])->where('active',true)->first();
            abort_unless($client,404);
            $old=$id?DB::table('audit_engagements')->where('id',$id)
                ->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->lockForUpdate()->first():null;
            if($id)abort_unless($old,404);
            if($old && $old->status!=='open'){
                throw ValidationException::withMessages(['title'=>'Closed engagements are immutable.']);
            }
            $code=strtoupper(trim($data['code']));
            if(DB::table('audit_engagements')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('code',$code)
                ->when($id,fn($q)=>$q->where('id','!=',$id))->exists()){
                throw ValidationException::withMessages(['code'=>'Engagement code already exists in your Partner workspace.']);
            }
            if($old && DB::table('audit_timesheets')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('engagement_id',$old->id)
                ->exists() && ($old->audit_client_id != $client->id
                    || $old->starts_on !== $data['starts_on']
                    || $old->ends_on !== $data['ends_on'])){
                throw ValidationException::withMessages(['starts_on'=>
                    'Cannot change client or engagement dates after timesheet activity.']);
            }
            $values=[
                'audit_client_id'=>$client->id,'code'=>$code,'title'=>trim($data['title']),
                'engagement_type'=>$data['engagement_type'],
                'period_end'=>$data['period_end']??null,'starts_on'=>$data['starts_on'],
                'ends_on'=>$data['ends_on'],'budget_hours'=>$data['budget_hours'],
                'updated_at'=>now(),
            ];
            if($old){
                DB::table('audit_engagements')->where('id',$id)->update($values);
                $key=$id;
            }else{
                $key=DB::table('audit_engagements')->insertGetId([
                    'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                    ...$values,'status'=>'open','created_by'=>$actor->id,'created_at'=>now(),
                ]);
            }
            TenantOps::audit($actor,$old?'audit.engagement.updated':'audit.engagement.created',
                'audit_engagement',$key,['code'=>$code,'client_id'=>$client->id,
                    'budget_hours'=>$data['budget_hours']]);
        },3);
        return back()->with('success','Engagement saved with Partner-scoped audit history.');
    }

    public function close(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        DB::transaction(function()use($actor,$id){
            $this->workspaceLock($actor);
            $eng=DB::table('audit_engagements')->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)->where('id',$id)->lockForUpdate()->first();
            abort_unless($eng,404);
            if($eng->status==='closed')return;
            if(DB::table('audit_timesheets')->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)->where('engagement_id',$id)
                ->whereIn('status',['submitted','manager_reviewed'])->exists()){
                throw ValidationException::withMessages(['engagement'=>'Resolve outstanding timesheets before closing.']);
            }
            DB::table('audit_engagements')->where('id',$id)->update([
                'status'=>'closed','updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'audit.engagement.closed','audit_engagement',$id);
        },3);
        return back()->with('success','Engagement closed without deleting assignments or work history.');
    }

    public function assign(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate([
            'membership_id'=>['required','integer','min:1'],
            'active'=>['required','boolean'],
        ]);
        DB::transaction(function()use($actor,$id,$data){
            $this->workspaceLock($actor);
            $eng=DB::table('audit_engagements')->where('id',$id)
                ->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)->where('status','open')->first();
            abort_unless($eng,404);
            $member=DB::table('memberships')->where('id',$data['membership_id'])
                ->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)
                ->whereIn('role',['manager','student'])
                ->where('active',true)->first();
            abort_unless($member,404);
            $old=DB::table('audit_engagement_assignments')
                ->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)
                ->where('engagement_id',$id)->where('membership_id',$member->id)->first();
            $active=(bool)$data['active'];
            if($old){
                if(!$active && DB::table('audit_timesheets')
                    ->where('company_id',$actor->company_id)->where('workspace_id',$actor->workspace_id)
                    ->where('engagement_id',$id)->where('membership_id',$member->id)
                    ->whereIn('status',['submitted','manager_reviewed'])->exists()){
                    throw ValidationException::withMessages(['membership_id'=>
                        'Resolve submitted timesheets before unassigning a staff member.']);
                }
                DB::table('audit_engagement_assignments')->where('id',$old->id)->update([
                    'active'=>$active,'assigned_by'=>$actor->id,'updated_at'=>now(),
                ]);
                $key=$old->id;
            }elseif($active){
                $key=DB::table('audit_engagement_assignments')->insertGetId([
                    'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,
                    'engagement_id'=>$id,'membership_id'=>$member->id,
                    'assigned_by'=>$actor->id,'active'=>true,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }else{
                throw ValidationException::withMessages(['active'=>'Cannot deactivate an assignment that does not exist.']);
            }
            TenantOps::audit($actor,$active?'audit.engagement.assigned':'audit.engagement.unassigned',
                'audit_engagement_assignment',$key,['engagement_id'=>$id,
                    'membership_id'=>$member->id]);
            if($active){
                TenantOps::notify($actor,$member->id,'Audit engagement assignment',
                    'You were assigned to engagement '.$eng->code.'.');
            }
        },3);
        return back()->with('success','Engagement assignment updated.');
    }

    private function workspaceLock(object $actor): void
    {
        DB::table('workspaces')->where('company_id',$actor->company_id)
            ->where('id',$actor->workspace_id)->lockForUpdate()->first();
    }
}
