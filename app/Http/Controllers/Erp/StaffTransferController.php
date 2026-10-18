<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffTransferController extends Controller
{
    /** Source Partner requests, but cannot unilaterally enter another Partner's practice. */
    public function request(Request $request,string $firmSlug,int $memberId)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate([
            'target_workspace_id'=>['required','integer','min:1'],
            'reason'=>['required','string','min:15','max:1000'],
        ]);
        DB::transaction(function()use($actor,$memberId,$data){
            $this->lockFirm($actor);
            $member=DB::table('memberships')->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)->where('id',$memberId)
                ->where('role','student')->where('active',true)->lockForUpdate()->first();
            abort_unless($member,404);
            $target=DB::table('workspaces')->where('company_id',$actor->company_id)
                ->where('id',$data['target_workspace_id'])->where('active',true)->first();
            abort_unless($target && $target->id!==$actor->workspace_id,404);
            $existing=DB::table('memberships')->where('company_id',$actor->company_id)
                ->where('workspace_id',$target->id)->where('user_id',$member->user_id)->exists();
            if($existing){
                throw ValidationException::withMessages(['target_workspace_id'=>'This identity already belongs to the destination Partner workspace.']);
            }
            if(DB::table('staff_transfer_requests')->where('company_id',$actor->company_id)
                ->where('source_membership_id',$memberId)->where('status','pending')->exists()){
                throw ValidationException::withMessages(['target_workspace_id'=>'Transfer is already awaiting a decision.']);
            }
            $id=DB::table('staff_transfer_requests')->insertGetId([
                'company_id'=>$actor->company_id,'source_workspace_id'=>$actor->workspace_id,
                'target_workspace_id'=>$target->id,'source_membership_id'=>$memberId,
                'requested_by'=>$actor->id,'status'=>'pending',
                'reason'=>trim($data['reason']),'created_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'staff.transfer.requested','staff_transfer_request',$id,[
                'member_id'=>$memberId,'target_workspace_id'=>$target->id,
            ]);
        },3);
        return back()->with('success','Transfer proposed; the receiving Partner must accept or reject.');
    }

    public function decide(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate(['decision'=>['required','in:approve,reject']]);
        DB::transaction(function()use($actor,$id,$data){
            $this->lockFirm($actor);
            $row=DB::table('staff_transfer_requests')
                ->where('company_id',$actor->company_id)
                ->where('target_workspace_id',$actor->workspace_id)
                ->where('id',$id)->lockForUpdate()->first();
            abort_unless($row,404);
            if($row->status!=='pending'){
                throw ValidationException::withMessages(['decision'=>'This transfer has already been reviewed.']);
            }
            $source=DB::table('memberships')->where('company_id',$actor->company_id)
                ->where('workspace_id',$row->source_workspace_id)
                ->where('id',$row->source_membership_id)
                ->where('role','student')->where('active',true)->lockForUpdate()->first();
            if($data['decision']==='approve') {
                if(!$source || $source->platform_suspended_at) {
                    throw ValidationException::withMessages(['decision'=>'Source Student is no longer available for transfer.']);
                }
                $destination=DB::table('workspaces')->where('company_id',$actor->company_id)
                    ->where('id',$actor->workspace_id)->where('active',true)->first();
                abort_unless($destination,404);
                $duplicate=DB::table('memberships')->where('company_id',$actor->company_id)
                    ->where('workspace_id',$actor->workspace_id)->where('user_id',$source->user_id)->exists();
                if($duplicate){
                    throw ValidationException::withMessages(['decision'=>'This Student already has a destination membership.']);
                }
                $this->ensureCleanHandoff($source,$actor);
                $latestSalary=DB::table('staff_compensation_changes')
                    ->where('company_id',$actor->company_id)
                    ->where('workspace_id',$source->workspace_id)
                    ->where('membership_id',$source->id)
                    ->where('effective_month','<=',now('Asia/Dhaka')->startOfMonth()->toDateString())
                    ->orderByDesc('effective_month')->latest('id')->first();
                $newId=DB::table('memberships')->insertGetId([
                    'company_id'=>$actor->company_id,
                    'workspace_id'=>$actor->workspace_id,'user_id'=>$source->user_id,
                    'role'=>'student','manager_membership_id'=>null,
                    'monthly_allowance'=>$latestSalary?->new_amount??$source->monthly_allowance,
                    'active'=>true,
                    'activated_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
                ]);
                $profile=DB::table('staff_profiles')
                    ->where('company_id',$actor->company_id)
                    ->where('workspace_id',$source->workspace_id)
                    ->where('membership_id',$source->id)->first();
                if($profile){
                    DB::table('staff_profiles')->insert([
                        'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,
                        'membership_id'=>$newId,'joined_on'=>now('Asia/Dhaka')->toDateString(),
                        'staff_code'=>null,'designation'=>$profile->designation,
                        'articleship_registration'=>$profile->articleship_registration,
                        'articleship_started_on'=>$profile->articleship_started_on,
                        'articleship_ends_on'=>$profile->articleship_ends_on,
                        'employment_status'=>'active',
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                }
                DB::table('memberships')->where('id',$source->id)->update([
                    'active'=>false,'updated_at'=>now(),
                ]);
                DB::table('staff_profiles')->where('company_id',$actor->company_id)
                    ->where('workspace_id',$source->workspace_id)
                    ->where('membership_id',$source->id)->update([
                        'employment_status'=>'exited',
                        'employment_ended_on'=>now('Asia/Dhaka')->toDateString(),
                        'updated_at'=>now(),
                    ]);
                DB::table('staff_transfer_requests')->where('id',$row->id)->update([
                    'status'=>'approved','reviewed_by'=>$actor->id,
                    'new_membership_id'=>$newId,'decided_at'=>now(),'updated_at'=>now(),
                ]);
                DB::table('staff_lifecycle_events')->insert([
                    'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,
                    'membership_id'=>$newId,'event_type'=>'transferred_in',
                    'details'=>json_encode(['prior_membership_id'=>$source->id,
                        'source_workspace_id'=>$source->workspace_id],JSON_THROW_ON_ERROR),
                    'performed_by'=>$actor->id,'created_at'=>now(),'updated_at'=>now(),
                ]);
                TenantOps::audit($actor,'staff.transfer.approved','staff_transfer_request',$row->id,[
                    'old_membership'=>$source->id,'new_membership'=>$newId,
                ]);
            } else {
                DB::table('staff_transfer_requests')->where('id',$row->id)->update([
                    'status'=>'rejected','reviewed_by'=>$actor->id,
                    'decided_at'=>now(),'updated_at'=>now(),
                ]);
                TenantOps::audit($actor,'staff.transfer.rejected','staff_transfer_request',$row->id);
            }
        },3);
        return back()->with('success','Transfer decision recorded for the destination workspace.');
    }

    private function lockFirm(object $actor): void
    {
        DB::table('companies')->where('id',$actor->company_id)->lockForUpdate()->first();
    }

    private function ensureCleanHandoff(object $member,object $actor): void
    {
        $month=now('Asia/Dhaka')->startOfMonth()->toDateString();
        if(DB::table('staff_compensation_changes')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$member->workspace_id)
            ->where('membership_id',$member->id)
            ->where('effective_month','>',now('Asia/Dhaka')->startOfMonth()->toDateString())
            ->exists()){
            throw ValidationException::withMessages(['decision'=>
                'Resolve future-dated salary decisions in the original Partner before transfer.']);
        }
        $claims=DB::table('conveyance_claims')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$member->workspace_id)
            ->where('membership_id',$member->id)
            ->whereIn('status',['pending','manager_reviewed','approved'])->exists();
        $leave=DB::table('leave_requests')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$member->workspace_id)
            ->where('membership_id',$member->id)
            ->where(fn($q)=>$q->whereIn('status',['pending','manager_reviewed'])
                ->orWhere(fn($s)=>$s->where('status','approved')
                    ->where('date_to','>=',now('Asia/Dhaka')->toDateString())))->exists();
        $punches=DB::table('attendance_sessions')->where('company_id',$actor->company_id)
            ->where('workspace_id',$member->workspace_id)->where('membership_id',$member->id)
            ->where(fn($q)=>$q->whereNull('ended_at')
                ->orWhere('work_date','>=',$month))->exists();
        $runs=DB::table('payroll_runs')->where('company_id',$actor->company_id)
            ->whereIn('workspace_id',[$member->workspace_id,$actor->workspace_id])
            ->where('period_month',$month)->exists();
        $reviews=DB::table('attendance_review_requests')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$member->workspace_id)
            ->where('membership_id',$member->id)
            ->whereIn('status',['pending','manager_reviewed'])->exists();
        if($claims||$leave||$punches||$runs||$reviews){
            throw ValidationException::withMessages(['decision'=>
                'Resolve pending claims/leave/attendance, current-month duties and payroll before transferring.']);
        }
    }
}
