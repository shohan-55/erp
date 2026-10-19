<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\LeaveBalance;
use App\Support\TenantOps;
use App\Support\WorkspaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        $rows=DB::table('leave_requests as r')
            ->join('memberships as m','m.id','=','r.membership_id')
            ->join('users as u','u.id','=','m.user_id')
            ->where('r.company_id',$company->id)->where('r.workspace_id',$workspace->id)
            ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
            ->when($actor->role==='student',fn($q)=>$q->where('m.id',$actor->id))
            ->when($actor->role==='manager' && !WorkspaceAccess::allows($actor,'scope.workspace'),
                fn($q)=>$q->where('m.manager_membership_id',$actor->id))
            ->select('r.*','u.name as member_name')->orderByDesc('r.id')->limit(100)->get();

        $year=(int)CarbonImmutable::now($company->timezone)->year;
        $today=CarbonImmutable::now($company->timezone)->toDateString();
        $policy=LeaveBalance::policy($actor,$year);
        $balances=[];
        if ($actor->role==='student') {
            foreach (LeaveBalance::CREDIT_TYPES as $type) {
                $balances[$type]=LeaveBalance::allowance($actor,$year,$type,$today);
            }
        }
        $members=($actor->role==='partner' || WorkspaceAccess::allows($actor,'leave.submit_for_student'))
            ? DB::table('memberships as m')->join('users as u','u.id','=','m.user_id')
                ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
                ->where('m.role','student')->where('m.active',true)
                ->select('m.id','u.name','m.manager_membership_id')->orderBy('u.name')->get()
            : collect();

        $calendar=$rows->filter(fn($r)=>$r->status==='approved'
            && $r->date_to >= $today && $r->date_from <= CarbonImmutable::now($company->timezone)->addDays(60)->toDateString())
            ->sortBy('date_from')->values();

        return response()->view('erp.leaves',compact(
            'company','workspace','actor','firmSlug','rows','year',
            'policy','balances','members','calendar'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function submit(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        $onBehalf=$actor->role!=='student';
        if ($onBehalf) abort_unless(WorkspaceAccess::allows($actor,'leave.submit_for_student'),403);
        $input=$request->validate([
            'membership_id'=>$onBehalf ? ['required','integer','min:1'] : ['prohibited'],
            'leave_type'=>['required','in:annual,sick,casual,emergency,compensatory,unpaid'],
            'day_portion'=>['nullable','in:full,morning,afternoon'],
            'date_from'=>['required','date_format:Y-m-d'],
            'date_to'=>['required','date_format:Y-m-d','after_or_equal:date_from'],
            'reason'=>['required','string','min:10','max:2000'],
        ]);
        $input['day_portion']=$input['day_portion']??'full';
        $year=(int)substr($input['date_from'],0,4);
        $today=CarbonImmutable::now($company->timezone)->toDateString();
        if ($year < (int)substr($today,0,4)-1 || $year > (int)substr($today,0,4)+1) {
            throw ValidationException::withMessages(['date_from'=>'Leave must be for the current or next calendar year (recent sick/emergency exceptions only).']);
        }
        if ($input['date_from'] < $today && !in_array($input['leave_type'],['sick','emergency'],true)) {
            throw ValidationException::withMessages(['date_from'=>'Ordinary leave cannot be requested retroactively.']);
        }
        if ($input['date_from'] < CarbonImmutable::now($company->timezone)->subDays(30)->toDateString()) {
            throw ValidationException::withMessages(['date_from'=>'Historical sickness or emergency leave requests are limited to 30 days.']);
        }

        DB::transaction(function()use($company,$workspace,$actor,$input,$year,$today,$onBehalf) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $student=$onBehalf
                ? WorkspaceAccess::subject($actor,(int)$input['membership_id'],'leave.submit_for_student')
                : DB::table('memberships')->where('company_id',$company->id)
                    ->where('workspace_id',$workspace->id)->where('id',$actor->id)
                    ->where('active',true)->lockForUpdate()->first();
            abort_unless($student && $student->role==='student',403);
            DB::table('memberships')->where('id',$student->id)->lockForUpdate()->first();

            $policy=LeaveBalance::policy($student,$year);
            if (!$policy && in_array($input['leave_type'],['casual','compensatory','unpaid'],true)) {
                throw ValidationException::withMessages(['leave_type'=>'Your Partner must configure a yearly leave policy for this category.']);
            }
            if ($policy && $input['leave_type']==='annual'
                && $input['date_from'] < CarbonImmutable::now($company->timezone)
                    ->addDays((int)$policy->annual_notice_days)->toDateString()) {
                throw ValidationException::withMessages(['date_from'=>'Annual leave must meet your Partner notice period of '.$policy->annual_notice_days.' days.']);
            }
            $days=LeaveBalance::charge($company,$workspace,$student,
                $input['date_from'],$input['date_to'],$input['day_portion'],$policy);
            if (LeaveBalance::overlaps($student,$input['date_from'],$input['date_to'],$input['day_portion'])) {
                throw ValidationException::withMessages(['date_from'=>'An open or approved leave overlaps this date and half-day portion.']);
            }
            $this->assertPayrollEditable($student,$input['date_from'],$input['date_to']);
            LeaveBalance::assertAffordable($student,$input['leave_type'],$input['date_to'],$days);
            $id=DB::table('leave_requests')->insertGetId([
                'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                'membership_id'=>$student->id,'initiated_by'=>$actor->id,'leave_type'=>$input['leave_type'],
                'day_portion'=>$input['day_portion'],'charge_days'=>$days,
                'policy_id'=>$policy?->id,
                'date_from'=>$input['date_from'],'date_to'=>$input['date_to'],
                'reason'=>trim($input['reason']),'status'=>'pending',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,$onBehalf?'leave.submit_on_behalf':'leave.submit','leave_request',$id,[
                'student_membership_id'=>$student->id,
                'type'=>$input['leave_type'],'charge_days'=>$days,
                'day_portion'=>$input['day_portion'],'policy_id'=>$policy?->id,
            ]);
            if ($onBehalf) TenantOps::notify($actor,$student->id,'Leave submitted on your behalf',
                'An authorized staff member submitted a leave request for you. Two independent approvals are required.');
            foreach (TenantOps::teamRecipients($actor,$student) as $recipientId) {
                TenantOps::notify($actor,(int)$recipientId,'New leave request',
                    $input['leave_type'].' leave ('.$days.' day(s)) is pending review.');
            }
        },3);
        return back()->with('success','Leave request submitted for review.');
    }

    public function decide(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        abort_unless(in_array($actor->role,['partner','manager'],true),403);
        $input=$request->validate([
            'decision'=>['required','in:approve,reject'],
            'decision_note'=>['nullable','string','max:1000'],
        ]);
        DB::transaction(function()use($company,$workspace,$actor,$id,$input) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $row=DB::table('leave_requests')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('id',$id)->lockForUpdate()->first();
            abort_unless($row,404);
            $subject=DB::table('memberships')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('id',$row->membership_id)
                ->where('role','student')->where('active',true)
                ->whereNull('platform_suspended_at')->first();
            abort_unless($subject,404);
            if (!in_array($row->status,['pending','manager_reviewed'],true)) {
                throw ValidationException::withMessages(['decision'=>'Leave request already decided.']);
            }
            $first=$row->status==='pending';
            $allowed=$first
                ? WorkspaceAccess::reviewer($actor,$subject,'leave')
                : WorkspaceAccess::finalApprover($actor,$subject,'leave');
            abort_unless($allowed,403);
            if ($first && (int)($row->initiated_by??0)===(int)$actor->id) {
                throw ValidationException::withMessages(['decision'=>'Initiator cannot perform first approval on their own submitted leave.']);
            }
            if (!$first && (int)($row->first_reviewed_by ?: $row->reviewed_by)===(int)$actor->id) {
                throw ValidationException::withMessages(['decision'=>'First and final approval must be made by different authorized people.']);
            }
            if (!$first && $input['decision']==='approve') {
                $this->assertPayrollEditable($subject,$row->date_from,$row->date_to);
                LeaveBalance::assertAffordable($subject,$row->leave_type,$row->date_to,
                    (float)$row->charge_days,$row->id);
                if (LeaveBalance::overlaps($subject,$row->date_from,$row->date_to,
                    $row->day_portion,$row->id)) {
                    throw ValidationException::withMessages(['decision'=>'An open leave overlaps these dates.']);
                }
            }
            $status=$input['decision']==='reject'?'rejected'
                :($first?'manager_reviewed':'approved');
            DB::table('leave_requests')->where('id',$row->id)->update([
                'status'=>$status,'reviewed_by'=>$actor->id,
                'first_reviewed_by'=>$first?$actor->id:($row->first_reviewed_by?:$row->reviewed_by),
                'reviewed_at'=>now(),'decision_note'=>$input['decision_note']??null,
                'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'leave.'.$status,'leave_request',$id,[
                'charge_days'=>$row->charge_days,
                'portion'=>$row->day_portion,
            ]);
            TenantOps::notify($actor,(int)$subject->id,'Leave request update',
                'Your '.$row->leave_type.' leave is '.$status.'.');
            if ($status==='manager_reviewed') {
                foreach(TenantOps::teamRecipients($actor,$subject) as $recipient) {
                    TenantOps::notify($actor,(int)$recipient,'Leave awaiting Partner review',
                        'First-stage recommendation is ready for independent final approval.');
                }
            }
        },3);
        return back()->with('success','Leave approval stage recorded; independent two-level workflow enforced.');
    }

    public function withdraw(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['student']);
        DB::transaction(function()use($company,$workspace,$actor,$id) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $row=DB::table('leave_requests')->where('id',$id)
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('membership_id',$actor->id)->lockForUpdate()->first();
            abort_unless($row,404);
            if (!in_array($row->status,['pending','manager_reviewed'],true)) {
                throw ValidationException::withMessages(['leave'=>'Only pending requests can be withdrawn.']);
            }
            DB::table('leave_requests')->where('id',$row->id)->update([
                'status'=>'withdrawn','updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'leave.withdrawn','leave_request',$row->id);
            foreach(TenantOps::teamRecipients($actor,$actor) as $memberId) {
                TenantOps::notify($actor,(int)$memberId,'Leave request withdrawn',
                    'A student withdrew a pending leave request.');
            }
        },3);
        return back()->with('success','Pending leave withdrawn; its reserved days were released.');
    }

    /** The owning Partner may void a future approved leave, never erase it. */
    public function cancel(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $input=$request->validate([
            'reason'=>['required','string','min:15','max:1000'],
        ]);
        DB::transaction(function()use($company,$workspace,$actor,$id,$input) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $row=DB::table('leave_requests')
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('id',$id)->lockForUpdate()->first();
            abort_unless($row,404);
            $subject=TenantOps::subject($actor,(int)$row->membership_id);
            abort_unless($subject->role==='student',404);
            if ($row->status!=='approved') {
                throw ValidationException::withMessages(['reason'=>'Only final approved leave can be cancelled by a Partner.']);
            }
            $today=CarbonImmutable::now($company->timezone)->toDateString();
            if ($row->date_from<=$today) {
                throw ValidationException::withMessages(['reason'=>'Only future approved leave can be cancelled.']);
            }
            $this->assertPayrollEditable($subject,$row->date_from,$row->date_to);
            DB::table('leave_requests')->where('id',$row->id)->update([
                'status'=>'cancelled','decision_note'=>trim($input['reason']),
                'reviewed_by'=>$actor->id,'reviewed_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'leave.cancelled','leave_request',$row->id,[
                'prior_status'=>'approved','reason'=>trim($input['reason']),
                'restored_days'=>$row->charge_days,
            ]);
            TenantOps::notify($actor,(int)$subject->id,'Approved leave cancelled',
                'Your approved leave was cancelled by your Partner; the days are restored.');
        },3);
        return back()->with('success','Future approved leave cancelled; balance restored.');
    }

    private function assignedManagerActive(object $student): bool
    {
        return $student->manager_membership_id
            && DB::table('memberships')->where('company_id',$student->company_id)
                ->where('workspace_id',$student->workspace_id)
                ->where('id',$student->manager_membership_id)->where('role','manager')
                ->where('active',true)->exists();
    }

    private function assertPayrollEditable(object $subject,string $from,string $to): void
    {
        $first=substr($from,0,7).'-01';
        $last=substr($to,0,7).'-01';
        if (DB::table('payroll_runs')->where('company_id',$subject->company_id)
            ->where('workspace_id',$subject->workspace_id)
            ->whereBetween('period_month',[$first,$last])
            ->whereIn('status',['reviewed','approved','paid','locked'])->exists()) {
            throw ValidationException::withMessages(['date_from'=>'Payroll has been frozen for this leave period.']);
        }
    }
}
