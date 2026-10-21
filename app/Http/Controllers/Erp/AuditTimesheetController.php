<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuditTimesheetController extends Controller
{
    public function index(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner','manager','student']);
        $month=$request->validate(['month'=>['nullable','date_format:Y-m']])['month']
            ?? CarbonImmutable::now($company->timezone)->format('Y-m');
        $start=$month.'-01';
        $end=CarbonImmutable::parse($start)->endOfMonth()->toDateString();
        $query=DB::table('audit_timesheets as t')
            ->join('audit_engagements as e','e.id','=','t.engagement_id')
            ->join('memberships as m','m.id','=','t.membership_id')
            ->join('users as u','u.id','=','m.user_id')
            ->where('t.company_id',$company->id)->where('t.workspace_id',$workspace->id)
            ->where('e.company_id',$company->id)->where('e.workspace_id',$workspace->id)
            ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
            ->whereBetween('t.work_date',[$start,$end])
            ->when($actor->role==='student',fn($q)=>$q->where('m.id',$actor->id))
            ->when($actor->role==='manager',fn($q)=>$q->where(function($s)use($actor){
                $s->where('m.id',$actor->id)->orWhere('m.manager_membership_id',$actor->id);
            }))
            ->select('t.*','u.name as staff_name','e.title as engagement_title','e.code as engagement_code');
        $rows=$query->orderByDesc('t.work_date')->orderByDesc('t.id')->limit(300)->get();
        $summary=[
            'hours_submitted'=>$rows->filter(fn($r)=>in_array($r->status,['submitted','manager_reviewed']))->sum('hours'),
            'hours_approved'=>$rows->where('status','approved')->sum('hours'),
            'hours_billable'=>$rows->filter(fn($r)=>$r->status==='approved' && $r->billable)->sum('hours'),
            'pending_count'=>$rows->whereIn('status',['submitted','manager_reviewed'])->count(),
        ];
        $options=DB::table('audit_engagement_assignments as a')
            ->join('audit_engagements as e','e.id','=','a.engagement_id')
            ->join('audit_clients as c','c.id','=','e.audit_client_id')
            ->where('a.company_id',$company->id)->where('a.workspace_id',$workspace->id)
            ->where('a.membership_id',$actor->id)->where('a.active',true)
            ->where('e.company_id',$company->id)->where('e.workspace_id',$workspace->id)
            ->where('e.status','open')->where('c.active',true)
            ->select('e.id','e.code','e.title','e.starts_on','e.ends_on')->orderBy('e.code')->get();
        return response()->view('erp.audit-timesheets',compact(
            'company','workspace','actor','firmSlug','rows','month','summary','options'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function submit(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['manager','student']);
        $data=$request->validate([
            'engagement_id'=>['required','integer','min:1'],
            'work_date'=>['required','date_format:Y-m-d'],
            'hours'=>['required','numeric','between:0.25,16','decimal:0,2'],
            'billable'=>['required','boolean'],
            'work_description'=>['required','string','min:15','max:2000'],
        ]);
        $today=CarbonImmutable::now($company->timezone)->startOfDay();
        $day=CarbonImmutable::createFromFormat('!Y-m-d',$data['work_date'],$company->timezone);
        if(!$day || $day->toDateString()!==$data['work_date']
            || $day->greaterThan($today) || $day->lessThan($today->subDays(60))){
            throw ValidationException::withMessages(['work_date'=>'Work date must be within the past 60 days, up to today.']);
        }
        $minutes=(int)round((float)$data['hours']*60);
        if($minutes%15!==0 || abs($minutes/60-(float)$data['hours'])>0.001){
            throw ValidationException::withMessages(['hours'=>'Record time in quarter-hour increments.']);
        }
        DB::transaction(function()use($actor,$data,$minutes){
            $this->lock($actor);
            $staff=DB::table('memberships')->where('id',$actor->id)
                ->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)
                ->where('active',true)->first();
            abort_unless($staff && in_array($staff->role,['student','manager'],true),403);
            $eng=DB::table('audit_engagements as e')
                ->join('audit_clients as c','c.id','=','e.audit_client_id')
                ->where('e.company_id',$actor->company_id)->where('e.workspace_id',$actor->workspace_id)
                ->where('e.id',$data['engagement_id'])->where('e.status','open')
                ->where('c.company_id',$actor->company_id)->where('c.workspace_id',$actor->workspace_id)
                ->where('c.active',true)->select('e.*')->first();
            abort_unless($eng,404);
            if($data['work_date']<$eng->starts_on || $data['work_date']>$eng->ends_on){
                throw ValidationException::withMessages(['work_date'=>'Date must fall within the open engagement period.']);
            }
            $assigned=DB::table('audit_engagement_assignments')
                ->where('company_id',$actor->company_id)->where('workspace_id',$actor->workspace_id)
                ->where('engagement_id',$eng->id)->where('membership_id',$staff->id)
                ->where('active',true)->exists();
            abort_unless($assigned,404);
            $already=DB::table('audit_timesheets')
                ->where('company_id',$actor->company_id)->where('workspace_id',$actor->workspace_id)
                ->where('membership_id',$staff->id)->where('work_date',$data['work_date'])
                ->whereIn('status',['submitted','manager_reviewed','approved'])
                ->sum('hours');
            if((int)round((float)$already*60)+$minutes>960){
                throw ValidationException::withMessages(['hours'=>'Daily submitted/approved hours cannot exceed 16 hours.']);
            }
            $id=DB::table('audit_timesheets')->insertGetId([
                'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,
                'engagement_id'=>$eng->id,'membership_id'=>$staff->id,
                'work_date'=>$data['work_date'],'hours'=>number_format($minutes/60,2,'.',''),
                'billable'=>(bool)$data['billable'],
                'work_description'=>trim($data['work_description']),'status'=>'submitted',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'audit.timesheet.submitted','audit_timesheet',$id,[
                'engagement_id'=>$eng->id,'work_date'=>$data['work_date'],
                'hours'=>$data['hours'],'billable'=>(bool)$data['billable'],
            ]);
            foreach(TenantOps::teamRecipients($actor,$staff) as $recipient){
                TenantOps::notify($actor,(int)$recipient,'Timesheet submitted for review',
                    'An engagement timesheet awaits review for '.$data['work_date'].'.');
            }
        },3);
        return back()->with('success','Working hours submitted for review; payroll and attendance were not modified.');
    }

    public function decide(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['manager','partner']);
        $data=$request->validate([
            'decision'=>['required','in:approve,reject'],
            'decision_note'=>['nullable','string','max:1000'],
        ]);
        DB::transaction(function()use($actor,$id,$data){
            $this->lock($actor);
            $row=DB::table('audit_timesheets')->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)
                ->where('id',$id)->lockForUpdate()->first();
            abort_unless($row,404);
            $member=DB::table('memberships')->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)
                ->where('id',$row->membership_id)->first();
            abort_unless($member,404);
            if(!in_array($row->status,['submitted','manager_reviewed'],true)){
                throw ValidationException::withMessages(['decision'=>'Timesheet was already finalized or withdrawn.']);
            }
            if($actor->role==='manager'){
                if($member->role!=='student' || $member->manager_membership_id!=$actor->id){
                    abort(404);
                }
                if($row->status!=='submitted'){
                    throw ValidationException::withMessages(['decision'=>'Manager recommendation already recorded.']);
                }
                DB::table('audit_timesheets')->where('id',$id)->update([
                    'status'=>'manager_reviewed',
                    'manager_recommendation'=>$data['decision'],
                    'manager_reviewed_by'=>$actor->id,
                    'decision_note'=>$data['decision_note']??null,'updated_at'=>now(),
                ]);
                TenantOps::audit($actor,'audit.timesheet.recommended','audit_timesheet',$id,[
                    'recommendation'=>$data['decision'],
                ]);
                $partners=DB::table('memberships')->where('company_id',$actor->company_id)
                    ->where('workspace_id',$actor->workspace_id)->where('role','partner')
                    ->where('active',true)->pluck('id');
                foreach($partners as $recipient){
                    TenantOps::notify($actor,(int)$recipient,'Engagement time awaiting Partner approval',
                        'Manager has recommended a timesheet decision.');
                }
                return;
            }
            if($data['decision']==='approve' && $row->status==='submitted'
                && $member->role==='student' && $member->manager_membership_id
                && DB::table('memberships')->where('company_id',$actor->company_id)
                    ->where('workspace_id',$actor->workspace_id)
                    ->where('id',$member->manager_membership_id)->where('role','manager')
                    ->where('active',true)->exists()){
                throw ValidationException::withMessages(['decision'=>'Assigned Manager must review this Student timesheet first.']);
            }
            $status=$data['decision']==='approve'?'approved':'rejected';
            DB::table('audit_timesheets')->where('id',$id)->update([
                'status'=>$status,'decided_by'=>$actor->id,'decided_at'=>now(),
                'decision_note'=>$data['decision_note']??$row->decision_note,'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'audit.timesheet.'.$status,'audit_timesheet',$id,[
                'engagement_id'=>$row->engagement_id,'hours'=>$row->hours,
            ]);
            if($member->active){
                TenantOps::notify($actor,$member->id,'Engagement timesheet '.$status,
                    'Your timesheet for '.$row->work_date.' was '.$status.'.');
            }
        },3);
        return back()->with('success','Timesheet review decision saved.');
    }

    public function withdraw(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['student','manager']);
        DB::transaction(function()use($actor,$id){
            $this->lock($actor);
            $row=DB::table('audit_timesheets')->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)
                ->where('membership_id',$actor->id)->where('id',$id)
                ->lockForUpdate()->first();
            abort_unless($row,404);
            if(!in_array($row->status,['submitted','manager_reviewed'],true)){
                throw ValidationException::withMessages(['timesheet'=>'Only unapproved timesheets may be withdrawn.']);
            }
            DB::table('audit_timesheets')->where('id',$id)->update([
                'status'=>'withdrawn','updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'audit.timesheet.withdrawn','audit_timesheet',$id);
        },3);
        return back()->with('success','Unapproved timesheet withdrawn; audit history retained.');
    }

    public function export(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate(['month'=>['required','date_format:Y-m']]);
        $start=$data['month'].'-01';
        $end=CarbonImmutable::parse($start)->endOfMonth()->toDateString();
        $rows=DB::table('audit_timesheets as t')
            ->join('audit_engagements as e','e.id','=','t.engagement_id')
            ->join('audit_clients as c','c.id','=','e.audit_client_id')
            ->join('memberships as m','m.id','=','t.membership_id')
            ->join('users as u','u.id','=','m.user_id')
            ->where('t.company_id',$company->id)->where('t.workspace_id',$workspace->id)
            ->where('e.company_id',$company->id)->where('e.workspace_id',$workspace->id)
            ->where('c.company_id',$company->id)->where('c.workspace_id',$workspace->id)
            ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
            ->whereBetween('t.work_date',[$start,$end])
            ->select('t.id','t.work_date','t.hours','t.billable','t.status',
                't.work_description','u.name as staff_name',
                'e.code as engagement_code','c.name as client_name')
            ->orderBy('t.work_date')->orderBy('t.id')->get();
        TenantOps::audit($actor,'audit.timesheet.exported','workspace',$workspace->id,[
            'month'=>$data['month'],'rows'=>$rows->count(),
        ]);
        $escape=static function($value):string{
            $s=(string)($value??'');
            return preg_match('/^\s*[=+\-@]/u',$s)?"'".$s:$s;
        };
        return response()->streamDownload(function()use($rows,$escape){
            $out=fopen('php://output','w');
            fputcsv($out,['ID','Date','Staff','Client','Engagement','Hours',
                'Billable (reporting only)','Status','Work Description']);
            foreach($rows as $r){
                fputcsv($out,[$r->id,$r->work_date,$escape($r->staff_name),
                    $escape($r->client_name),$escape($r->engagement_code),
                    $r->hours,$r->billable?'Yes':'No',$r->status,$escape($r->work_description)]);
            }
            fclose($out);
        },'partner-timesheets-'.$data['month'].'.csv',[
            'Content-Type'=>'text/csv; charset=UTF-8',
            'Cache-Control'=>'private, no-store, max-age=0',
        ]);
    }

    private function lock(object $actor):void
    {
        DB::table('workspaces')->where('company_id',$actor->company_id)
            ->where('id',$actor->workspace_id)->lockForUpdate()->first();
    }
}
