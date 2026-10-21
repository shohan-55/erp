<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\StaffExitClearance;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    private function member(object $actor,int $id,bool $lock=false): object
    {
        $q=DB::table('memberships')->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)->where('id',$id)
            ->whereIn('role',['student','manager']);
        $member=($lock?$q->lockForUpdate():$q)->first();
        abort_unless($member,404);
        return $member;
    }

    private function frozenMonth(object $actor,string $month): bool
    {
        return DB::table('payroll_runs')->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('period_month',$month)->whereIn('status',
                ['reviewed','approved','paid','locked'])->exists();
    }

    private function history(object $actor,int $membership,string $event,array $details=[]): void
    {
        DB::table('staff_lifecycle_events')->insert([
            'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,
            'membership_id'=>$membership,'event_type'=>$event,
            'details'=>json_encode($details,JSON_THROW_ON_ERROR),
            'performed_by'=>$actor->id,'created_at'=>now(),'updated_at'=>now(),
        ]);
        TenantOps::audit($actor,'staff.'.$event,'membership',$membership,$details);
    }

    public function index(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $members=DB::table('memberships as m')->join('users as u','u.id','=','m.user_id')
            ->leftJoin('staff_profiles as p',function($j){
                $j->on('p.membership_id','=','m.id')->on('p.company_id','=','m.company_id')
                    ->on('p.workspace_id','=','m.workspace_id');
            })->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
            ->whereIn('m.role',['student','manager'])
            ->select('m.*','u.name','p.designation','p.joined_on','p.articleship_registration',
                'p.articleship_started_on','p.articleship_ends_on','p.staff_code',
                'p.employment_status','p.employment_ended_on')->orderBy('u.name')->get();
        $events=DB::table('staff_lifecycle_events as e')
            ->join('memberships as m','m.id','=','e.membership_id')
            ->join('users as u','u.id','=','m.user_id')
            ->where('e.company_id',$company->id)->where('e.workspace_id',$workspace->id)
            ->select('e.*','u.name')->latest('e.id')->limit(60)->get();
        $documents=DB::table('staff_documents')->where('company_id',$company->id)
            ->where('workspace_id',$workspace->id)->latest('id')->limit(50)->get();
        $salaryChanges=DB::table('staff_compensation_changes')
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->orderByDesc('effective_month')->orderByDesc('id')->get()
            ->unique('membership_id')->keyBy('membership_id');
        $managers=$members->where('role','manager')->where('active',true);
        $workspaces=DB::table('workspaces')->where('company_id',$company->id)
            ->where('id','!=',$workspace->id)->where('active',true)
            ->get(['id','name']);
        $outbound=DB::table('staff_transfer_requests')->where('company_id',$company->id)
            ->where('source_workspace_id',$workspace->id)->latest('id')->limit(30)->get();
        $inbound=DB::table('staff_transfer_requests')->where('company_id',$company->id)
            ->where('target_workspace_id',$workspace->id)->latest('id')->limit(30)->get();
        return response()->view('erp.staff',compact('company','workspace','actor','firmSlug',
            'members','events','documents','managers','workspaces','outbound','inbound','salaryChanges'))
            ->header('Cache-Control','private, no-store, max-age=0');
    }

    public function myRecords(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['manager','student']);
        $profile=DB::table('staff_profiles')->where('company_id',$company->id)
            ->where('workspace_id',$workspace->id)->where('membership_id',$actor->id)->first();
        $documents=DB::table('staff_documents')->where('company_id',$company->id)
            ->where('workspace_id',$workspace->id)->where('membership_id',$actor->id)
            ->latest('id')->get();
        $latestSalary=$actor->role==='student'
            ? DB::table('staff_compensation_changes')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('membership_id',$actor->id)
                ->orderByDesc('effective_month')->first() : null;
        return response()->view('erp.my-staff-records',compact(
            'company','workspace','actor','firmSlug','profile','documents','latestSalary'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function profile(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate([
            'staff_code'=>['nullable','string','max:50'],
            'joined_on'=>['required','date_format:Y-m-d'],
            'designation'=>['required','string','max:100'],
            'articleship_registration'=>['nullable','string','max:100'],
            'articleship_started_on'=>['nullable','date_format:Y-m-d'],
            'articleship_ends_on'=>['nullable','date_format:Y-m-d','after_or_equal:articleship_started_on'],
        ]);
        DB::transaction(function()use($actor,$id,$data) {
            DB::table('workspaces')->where('id',$actor->workspace_id)
                ->where('company_id',$actor->company_id)->lockForUpdate()->first();
            $member=$this->member($actor,$id,true);
            $duplicate=DB::table('staff_profiles')
                ->where('company_id',$actor->company_id)->where('workspace_id',$actor->workspace_id)
                ->where('staff_code',$data['staff_code']??null)
                ->where('membership_id','!=',$id)->exists();
            if (!empty($data['staff_code']) && $duplicate) {
                throw ValidationException::withMessages(['staff_code'=>'Staff code must be unique within your Partner workspace.']);
            }
            $previous=DB::table('staff_profiles')->where('membership_id',$id)
                ->where('company_id',$actor->company_id)->where('workspace_id',$actor->workspace_id)->first();
            DB::table('staff_profiles')->updateOrInsert([
                'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,'membership_id'=>$id,
            ],[
                'joined_on'=>$data['joined_on'],'staff_code'=>$data['staff_code']??null,
                'designation'=>$data['designation'],
                'articleship_registration'=>$data['articleship_registration']??null,
                'articleship_started_on'=>$data['articleship_started_on']??null,
                'articleship_ends_on'=>$data['articleship_ends_on']??null,
                'employment_status'=>$previous?->employment_status??'active',
                'employment_ended_on'=>$previous?->employment_ended_on,
                'updated_at'=>now(),
                'created_at'=>$previous?->created_at??now(),
            ]);
            $this->history($actor,$id,'profile_updated',[
                'previous_designation'=>$previous?->designation,
                'new_designation'=>$data['designation'],
                'articleship_registration'=>$data['articleship_registration']??null,
            ]);
        },3);
        return back()->with('success','Staff/Articleship profile saved with audit history.');
    }

    /** Salary changes take effect on the first of a future month, preserving old payroll. */
    public function compensation(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate([
            'effective_month'=>['required','date_format:Y-m'],
            'new_amount'=>['required','numeric','between:0,500000','decimal:0,2'],
            'reason'=>['required','string','min:15','max:1000'],
        ]);
        $month=$data['effective_month'].'-01';
        if ($month <= now('Asia/Dhaka')->startOfMonth()->toDateString()) {
            throw ValidationException::withMessages(['effective_month'=>'Salary changes must start in a future month.']);
        }
        DB::transaction(function()use($actor,$id,$month,$data) {
            DB::table('workspaces')->where('id',$actor->workspace_id)
                ->where('company_id',$actor->company_id)->lockForUpdate()->first();
            $member=$this->member($actor,$id,true);
            abort_unless($member->role==='student' && $member->active,422);
            if ($this->frozenMonth($actor,$month) || DB::table('payroll_runs')
                ->where('company_id',$actor->company_id)->where('workspace_id',$actor->workspace_id)
                ->where('period_month',$month)->exists()) {
                throw ValidationException::withMessages(['effective_month'=>'Payroll exists for this period.']);
            }
            $latest=DB::table('staff_compensation_changes')
                ->where('company_id',$actor->company_id)->where('workspace_id',$actor->workspace_id)
                ->where('membership_id',$id)->latest('effective_month')->latest('id')->first();
            if ($latest && $month <= $latest->effective_month) {
                throw ValidationException::withMessages(['effective_month'=>'Schedule after the latest existing salary decision.']);
            }
            $old=(string)($latest?->new_amount??$member->monthly_allowance);
            $changeId=DB::table('staff_compensation_changes')->insertGetId([
                'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,
                'membership_id'=>$id,'effective_month'=>$month,
                'old_amount'=>$old,'new_amount'=>$data['new_amount'],
                'reason'=>trim($data['reason']),'authorized_by'=>$actor->id,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->history($actor,$id,'salary_scheduled',[
                'change_id'=>$changeId,'effective_month'=>$month,
                'old_amount'=>$old,'new_amount'=>$data['new_amount'],
            ]);
            TenantOps::notify($actor,$id,'Salary change scheduled',
                'A new monthly allowance is scheduled from '.$month.'.');
        },3);
        return back()->with('success','Salary increment scheduled for a future payroll month.');
    }

    public function exitClearance(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $member=$this->member($actor,$id);
        $profile=DB::table('staff_profiles')
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->where('membership_id',$id)->first();
        $issues=StaffExitClearance::issues($actor,$member);
        $transferred=StaffExitClearance::wasTransferred($actor,$member);
        $grantCount=DB::table('workspace_access_grants')
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->where('membership_id',$id)->whereNull('revoked_at')->count();
        $hasCustomRole=DB::table('workspace_role_assignments')
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->where('membership_id',$id)->exists();
        return response()->view('erp.staff-exit-clearance',compact(
            'company','workspace','actor','firmSlug','member','profile',
            'issues','transferred','grantCount','hasCustomRole'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function lifecycle(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate([
            'action'=>['required','in:exit,rejoin'],
            'reason'=>['required','string','min:15','max:1000'],
        ]);
        DB::transaction(function()use($actor,$id,$data){
            // Serialize against StaffTransferController::lockFirm to prevent
            // concurrent transfer approval and Partner exit/rejoin decisions.
            DB::table('companies')->where('id',$actor->company_id)->lockForUpdate()->first();
            DB::table('workspaces')->where('id',$actor->workspace_id)
                ->where('company_id',$actor->company_id)->lockForUpdate()->first();
            $member=$this->member($actor,$id,true);
            if($data['action']==='exit'){
                if (!$member->active || $member->platform_suspended_at) {
                    throw ValidationException::withMessages(['action'=>'Only active, unsuspended staff can exit.']);
                }
                $issues=StaffExitClearance::issues($actor,$member);
                if ($issues) {
                    throw ValidationException::withMessages([
                        'action'=>'Staff exit clearance incomplete: '.implode(' ',array_values($issues)),
                    ]);
                }

                // Rejoining must require fresh authorization. An old custom
                // role assignment must NEVER spring back to life automatically.
                $roleAssignment=DB::table('workspace_role_assignments')
                    ->where('company_id',$actor->company_id)
                    ->where('workspace_id',$actor->workspace_id)
                    ->where('membership_id',$id)->first();
                $grantIds=DB::table('workspace_access_grants')
                    ->where('company_id',$actor->company_id)
                    ->where('workspace_id',$actor->workspace_id)
                    ->where('membership_id',$id)->pluck('id')->all();
                $revokedGrants=DB::table('workspace_access_grants')
                    ->where('company_id',$actor->company_id)
                    ->where('workspace_id',$actor->workspace_id)
                    ->whereNull('revoked_at')
                    ->where(function($q)use($id,$grantIds){
                        $q->where('membership_id',$id);
                        if($grantIds) $q->orWhereIn('parent_grant_id',$grantIds);
                    })
                    ->update(['revoked_at'=>now(),'revoked_by'=>$actor->id,'updated_at'=>now()]);
                if ($roleAssignment) {
                    // Original assignment identity is referenced in immutable
                    // audit history; delete the live permission edge only.
                    DB::table('workspace_role_assignments')
                        ->where('id',$roleAssignment->id)->delete();
                }

                DB::table('memberships')->where('id',$id)
                    ->where('company_id',$actor->company_id)
                    ->where('workspace_id',$actor->workspace_id)
                    ->update(['active'=>false,'updated_at'=>now()]);
                DB::table('staff_profiles')->updateOrInsert([
                    'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,'membership_id'=>$id,
                ],[
                    'employment_status'=>'exited',
                    'employment_ended_on'=>now('Asia/Dhaka')->toDateString(),
                    'updated_at'=>now(),
                ]);
                $this->history($actor,$id,'exit',[
                    'reason'=>trim($data['reason']),
                    'clearance'=>'all_checks_passed',
                    'clearance_checks'=>array_keys(StaffExitClearance::issues($actor,$member)),
                    'revoked_workspace_grants'=>$revokedGrants,
                    'removed_custom_role_assignment_id'=>$roleAssignment?->id,
                    'removed_custom_role_id'=>$roleAssignment?->role_id,
                ]);
            }else{
                $profile=DB::table('staff_profiles')->where('company_id',$actor->company_id)
                    ->where('workspace_id',$actor->workspace_id)
                    ->where('membership_id',$id)->first();
                if($member->active || $member->platform_suspended_at ||
                    (!$member->activated_at && $member->invited_at)
                    || !$profile || $profile->employment_status!=='exited'
                    || StaffExitClearance::wasTransferred($actor,$member)) {
                    throw ValidationException::withMessages([
                        'action'=>'Only previously exited, unsuspended, activated staff without a completed transfer may rejoin.',
                    ]);
                }
                DB::table('memberships')->where('id',$id)->update([
                    'active'=>true,'updated_at'=>now(),
                ]);
                DB::table('staff_profiles')->where('membership_id',$id)
                    ->where('company_id',$actor->company_id)->where('workspace_id',$actor->workspace_id)
                    ->update([
                        'employment_status'=>'active','employment_ended_on'=>null,
                        'updated_at'=>now(),
                    ]);
                $this->history($actor,$id,'rejoin',[
                    'reason'=>trim($data['reason']),
                    'delegation_reset'=>true,
                    'new_access_requires_partner_reauthorization'=>true,
                ]);
            }

        },3);
        return back()->with('success','Staff lifecycle decision saved.');
    }

    public function upload(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate([
            'document_type'=>['required','in:appointment,articleship,certificate,identity,other'],
            'title'=>['required','string','min:3','max:160'],
            'document'=>['required','file','mimes:pdf,jpg,jpeg,png','max:3072'],
        ]);
        $this->member($actor,$id);
        $file=$request->file('document');
        $path=$file->store('staff-documents/'.$actor->company_id.'/'.$actor->workspace_id.'/'.$id,'local');
        try{
            DB::transaction(function()use($actor,$id,$data,$path,$file){
                DB::table('workspaces')->where('id',$actor->workspace_id)
                    ->where('company_id',$actor->company_id)->lockForUpdate()->first();
                $this->member($actor,$id,true);
                $doc=DB::table('staff_documents')->insertGetId([
                    'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,
                    'membership_id'=>$id,'document_type'=>$data['document_type'],
                    'title'=>$data['title'],'storage_path'=>$path,
                    'original_filename'=>basename($file->getClientOriginalName()),
                    'uploaded_by'=>$actor->id,'created_at'=>now(),'updated_at'=>now(),
                ]);
                $this->history($actor,$id,'document_uploaded',['document_id'=>$doc,'type'=>$data['document_type']]);
            },3);
        }catch(\Throwable $e){
            Storage::disk('local')->delete($path);
            throw $e;
        }
        return back()->with('success','Document saved privately with an audit record.');
    }

    public function download(Request $request,string $firmSlug,int $documentId)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner','manager','student']);
        $doc=DB::table('staff_documents')->where('id',$documentId)
            ->where('company_id',$actor->company_id)->where('workspace_id',$actor->workspace_id)->first();
        abort_unless($doc,404);
        $subject=TenantOps::subject($actor,(int)$doc->membership_id);
        abort_unless($subject->id===$doc->membership_id || (int)$subject->id===(int)$doc->membership_id,404);
        abort_unless(Storage::disk('local')->exists($doc->storage_path),404);
        return Storage::disk('local')->download($doc->storage_path,basename($doc->original_filename),[
            'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff',
        ]);
    }
}
