<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\WorkCalendar;
use App\Support\WorkspaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceReviewController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        $rows = DB::table('attendance_review_requests as r')
            ->join('memberships as m','m.id','=','r.membership_id')
            ->join('users as u','u.id','=','m.user_id')
            ->where('r.company_id',$company->id)->where('r.workspace_id',$workspace->id)
            ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
            ->when($actor->role === 'student', fn($q)=>$q->where('m.id',$actor->id))
            ->when($actor->role === 'manager' && !WorkspaceAccess::allows($actor,'scope.workspace'),
                fn($q)=>$q->where('m.manager_membership_id',$actor->id))
            ->select('r.*','u.name as student_name')->orderByDesc('r.id')->limit(100)->get();
        $canInitiateTeam = $actor->role === 'manager' && WorkspaceAccess::allows($actor,'attendance.propose');
        $students = $canInitiateTeam
            ? DB::table('memberships as m')->join('users as u','u.id','=','m.user_id')
                ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
                ->when(!WorkspaceAccess::allows($actor,'scope.workspace'),fn($q)=>$q->where('m.manager_membership_id',$actor->id))->where('m.active',true)
                ->whereNull('m.platform_suspended_at')->where('m.role','student')
                ->select('m.id','u.name')->orderBy('u.name')->get()
            : collect();
        $sessions = $actor->role === 'student'
            ? DB::table('attendance_sessions')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('membership_id',$actor->id)
                ->where('started_at','>=',now()->subDays(60)->utc()->toDateTimeString())
                ->orderByDesc('id')->limit(30)->get()
            : ($canInitiateTeam
                ? DB::table('attendance_sessions as s')
                    ->join('memberships as m','m.id','=','s.membership_id')
                    ->where('s.company_id',$company->id)->where('s.workspace_id',$workspace->id)
                    ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
                    ->when(!WorkspaceAccess::allows($actor,'scope.workspace'),fn($q)=>$q->where('m.manager_membership_id',$actor->id))->where('m.role','student')
                    ->where('m.active',true)->whereNull('m.platform_suspended_at')
                    ->where('s.started_at','>=',now()->subDays(60)->utc()->toDateTimeString())
                    ->select('s.*')->orderByDesc('s.id')->limit(100)->get()
                : collect());
        return response()->view('erp.attendance-review',compact(
            'company','workspace','actor','firmSlug','rows','sessions','students','canInitiateTeam'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function submit(Request $request, string $firmSlug)
    {
        return $this->saveRequest($request,false);
    }

    // An assigned Manager can propose a correction, NOT impersonate a punch.
    // Their proposal is distinct from Partner's final decision in audit history.
    public function submitForStudent(Request $request,string $firmSlug)
    {
        return $this->saveRequest($request,true);
    }

    private function saveRequest(Request $request,bool $teamInitiated)
    {
        [$company,$workspace,$actor] = TenantOps::context($request);
        if ($teamInitiated) {
            abort_unless($actor->role==='manager'
                && WorkspaceAccess::allows($actor,'attendance.propose'),403);
        } else {
            TenantOps::requireRole($actor,['student']);
        }
        $input=$request->validate([
            'membership_id'=>$teamInitiated ? ['required','integer','min:1'] : ['prohibited'],
            'kind'=>['required','in:missed_checkout,missed_checkin,incorrect_checkin,client_duty'],
            'work_date'=>['required','date_format:Y-m-d'],
            'attendance_session_id'=>['nullable','integer','min:1'],
            'start_time'=>['nullable','date_format:H:i'],
            'end_time'=>['nullable','date_format:H:i'],
            'client_site'=>['nullable','string','min:3','max:180'],
            'reason'=>['required','string','min:15','max:1000'],
        ]);
        $kind=$input['kind'];
        if (in_array($kind,['missed_checkout','incorrect_checkin'],true)
            && !isset($input['attendance_session_id'])) {
            throw ValidationException::withMessages(['attendance_session_id'=>'Select an existing attendance session.']);
        }
        if ($kind==='missed_checkin' && (!isset($input['start_time']) || !isset($input['end_time']))) {
            throw ValidationException::withMessages(['start_time'=>'Provide both missed check-in and check-out times.']);
        }
        if ($kind==='incorrect_checkin' && !isset($input['start_time'])) {
            throw ValidationException::withMessages(['start_time'=>'Provide the corrected check-in time.']);
        }
        if ($kind==='missed_checkout' && !isset($input['end_time'])) {
            throw ValidationException::withMessages(['end_time'=>'Provide the corrected check-out time.']);
        }
        if ($kind==='client_duty' && blank($input['client_site']??null)) {
            throw ValidationException::withMessages(['client_site'=>'Name the assigned audit client/site.']);
        }
        $date=$this->assertDate($company,$input['work_date']);
        $start=isset($input['start_time']) ? $this->localTime($company,$date,$input['start_time']) : null;
        $end=isset($input['end_time']) ? $this->localTime($company,$date,$input['end_time']) : null;
        if ($start && $start->gt(CarbonImmutable::now('UTC'))) {
            throw ValidationException::withMessages(['start_time'=>'Check-in cannot be in the future.']);
        }
        if (in_array($kind,['missed_checkout','missed_checkin'],true)
            && ($end===null || ($start && $start->gte($end)) || $end->gt(CarbonImmutable::now('UTC')))) {
            throw ValidationException::withMessages(['end_time'=>'Times must be ordered and cannot be in the future.']);
        }
        if ($kind==='incorrect_checkin' && $end!==null) {
            throw ValidationException::withMessages(['end_time'=>'Edit a check-in and check-out in separate audited requests.']);
        }

        DB::transaction(function () use ($company,$workspace,$actor,$input,$kind,$date,$start,$end,$teamInitiated) {
            $this->workspaceLock($company,$workspace);
            $this->assertPayrollEditable($company,$workspace,$date);
            $student=$teamInitiated
                ? WorkspaceAccess::subject($actor,(int)$input['membership_id'],'attendance.propose')
                : DB::table('memberships')->where('id',$actor->id)
                    ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                    ->where('active',true)->whereNull('platform_suspended_at')->first();
            abort_unless($student && $student->role==='student',404);
            // Permission revocation must take effect during any in-flight request.
            if ($teamInitiated) {
                $fresh=DB::table('memberships')->where('id',$actor->id)
                    ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                    ->where('active',true)->whereNull('platform_suspended_at')->first();
                abort_unless($fresh && WorkspaceAccess::allows($fresh,'attendance.propose'),403);
            }
            DB::table('memberships')->where('id',$student->id)->lockForUpdate()->first();
            if (DB::table('attendance_review_requests')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('membership_id',$student->id)
                ->where('work_date',$date)->where('kind',$kind)
                ->whereIn('status',['pending','manager_reviewed'])->exists()) {
                throw ValidationException::withMessages(['work_date'=>'An unresolved request of this kind exists for this student and date.']);
            }

            $sessionId=null;
            if (in_array($kind,['missed_checkout','incorrect_checkin'],true)) {
                $session=DB::table('attendance_sessions')
                    ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                    ->where('membership_id',$student->id)->where('id',$input['attendance_session_id'])->first();
                abort_unless($session && $session->work_date===$date,404);
                if ($kind==='missed_checkout' && $end->lte(CarbonImmutable::parse($session->started_at,'UTC'))) {
                    throw ValidationException::withMessages(['end_time'=>'Corrected checkout must follow original check-in.']);
                }
                if ($kind==='incorrect_checkin' && $session->ended_at
                    && $start->gte(CarbonImmutable::parse($session->ended_at,'UTC'))) {
                    throw ValidationException::withMessages(['start_time'=>'Corrected check-in must precede check-out.']);
                }
                $sessionId=$session->id;
            }
            $id=DB::table('attendance_review_requests')->insertGetId([
                'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                'membership_id'=>$student->id,'initiated_by'=>$actor->id,
                'kind'=>$kind,'work_date'=>$date,'attendance_session_id'=>$sessionId,
                'proposed_start_at'=>in_array($kind,['missed_checkin','incorrect_checkin'],true)
                    ? $start?->utc()->toDateTimeString() : null,
                'proposed_end_at'=>in_array($kind,['missed_checkin','missed_checkout'],true)
                    ? $end?->utc()->toDateTimeString() : null,
                'client_site'=>$kind==='client_duty'?trim($input['client_site']):null,
                'reason'=>trim($input['reason']),
                'status'=>$teamInitiated?'manager_reviewed':'pending',
                'manager_recommendation'=>$teamInitiated?'approve':null,
                'manager_reviewed_by'=>$teamInitiated?$actor->id:null,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,$teamInitiated?'attendance.review.manager_initiated':'attendance.review.requested',
                'attendance_review_request',$id,[
                    'kind'=>$kind,'date'=>$date,'student_id'=>$student->id,'session_id'=>$sessionId,
                ]);
            if ($teamInitiated) {
                TenantOps::notify($actor,$student->id,'Manager proposed an attendance correction',
                    'A correction was submitted for '.$date.'. Partner verification is required.');
                $partners=DB::table('memberships')->where('company_id',$company->id)
                    ->where('workspace_id',$workspace->id)->where('role','partner')
                    ->where('active',true)->pluck('id');
                foreach ($partners as $recipient) TenantOps::notify($actor,(int)$recipient,
                    'Team attendance exception pending final decision','A Manager proposed a correction for '.$date.'.');
            } else {
                foreach (TenantOps::teamRecipients($actor,$student) as $recipient) {
                    TenantOps::notify($actor,(int)$recipient,'Attendance review requested',
                        'A student submitted a '.$kind.' request for '.$date.'.');
                }
            }
        },3);
        return back()->with('success',$teamInitiated
            ? 'Team exception proposed; only Partner final approval can modify attendance.'
            : 'Attendance review request submitted; no record was changed.');
    }

    public function decide(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor] = TenantOps::context($request);
        TenantOps::requireRole($actor,['manager','partner']);
        $input=$request->validate([
            'decision'=>['required','in:approve,reject'],
            'decision_note'=>['nullable','string','max:1000'],
        ]);

        DB::transaction(function () use ($company,$workspace,$actor,$id,$input) {
            $this->workspaceLock($company,$workspace);

            $row = DB::table('attendance_review_requests')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('id',$id)->lockForUpdate()->first();
            abort_unless($row,404);
            $student=DB::table('memberships')->where('id',$row->membership_id)
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('role','student')->where('active',true)->whereNull('platform_suspended_at')->first();
            abort_unless($student,404);
            if ($actor->role==='manager' && !WorkspaceAccess::allows($actor,'scope.workspace')
                && $student->manager_membership_id!==$actor->id) abort(404);
            if (! in_array($row->status,['pending','manager_reviewed'],true)) {
                throw ValidationException::withMessages(['decision'=>'This request is already resolved.']);
            }
            $this->assertPayrollEditable($company,$workspace,$row->work_date);

            if ($actor->role==='manager' && $row->status==='pending') {
                if ($row->status !== 'pending') {
                    throw ValidationException::withMessages(['decision'=>'A Manager has already reviewed the request.']);
                }
                DB::table('attendance_review_requests')->where('id',$row->id)->update([
                    'status'=>'manager_reviewed','manager_recommendation'=>$input['decision'],
                    'manager_reviewed_by'=>$actor->id,'decision_note'=>$input['decision_note']??null,
                    'updated_at'=>now(),
                ]);
                TenantOps::audit($actor,'attendance.review.recommended','attendance_review_request',$row->id,[
                    'recommendation'=>$input['decision'],
                ]);
                $partnerIds=DB::table('memberships')->where('company_id',$company->id)
                    ->where('workspace_id',$workspace->id)->where('role','partner')
                    ->where('active',true)->pluck('id');
                foreach ($partnerIds as $recipient) {
                    TenantOps::notify($actor,(int)$recipient,'Attendance request ready for Partner',
                        'A Manager has reviewed an attendance request for '.$row->work_date.'.');
                }
                return;
            }

            if ($actor->role==='manager') {
                abort_unless(WorkspaceAccess::allows($actor,'attendance.final'),403);
                if ($row->status!=='manager_reviewed'
                    || (int)$row->manager_reviewed_by===(int)$actor->id
                    || (int)($row->initiated_by??0)===(int)$actor->id) {
                    throw ValidationException::withMessages(['decision'=>'An independent reviewer must recommend first; initiator cannot final-approve.']);
                }
            }
            if ($input['decision']==='approve' && $row->status==='pending'
                && $this->activeAssignedManager($student)) {
                throw ValidationException::withMessages(['decision'=>'Assigned Manager recommendation is required before Partner approval.']);
            }

            if ($input['decision']==='approve') {
                if ($row->kind === 'missed_checkout') {
                    $this->approveCheckout($company,$workspace,$row,$actor);
                } elseif ($row->kind === 'missed_checkin') {
                    $this->approveMissingSession($company,$workspace,$row,$actor);
                } elseif ($row->kind === 'incorrect_checkin') {
                    $this->approveCheckInCorrection($company,$workspace,$row,$actor);
                }
                // client_duty approvals are authorization, never a fake check-in.
            }

            DB::table('attendance_review_requests')->where('id',$row->id)->update([
                'status'=>$input['decision']==='approve'?'approved':'rejected',
                'decided_by'=>$actor->id,'decided_at'=>now(),
                'decision_note'=>$input['decision_note']??$row->decision_note,
                'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'attendance.review.'.$input['decision'],
                'attendance_review_request',$row->id,[
                    'kind'=>$row->kind,'work_date'=>$row->work_date,
                    'session_id'=>$row->attendance_session_id,
                ]);
            TenantOps::notify($actor,(int)$student->id,'Attendance request '.$input['decision'],
                'Your '.$row->kind.' request for '.$row->work_date.' was '.$input['decision'].'.');
        },3);

        return back()->with('success','Attendance review decision saved.');
    }

    private function approveCheckout(object $company,object $workspace,object $row,object $actor): void
    {
        $session=DB::table('attendance_sessions')->where('id',$row->attendance_session_id)
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->where('membership_id',$row->membership_id)->lockForUpdate()->first();
        abort_unless($session && $session->work_date === $row->work_date,404);
        $end=CarbonImmutable::parse($row->proposed_end_at,'UTC');
        if ($end->lte(CarbonImmutable::parse($session->started_at,'UTC'))
            || $end->gt(CarbonImmutable::now('UTC'))) {
            throw ValidationException::withMessages(['decision'=>'Checkout date is invalid.']);
        }
        $this->assertNoOverlap($row,$session->started_at,$end->toDateTimeString(),$session->id);
        DB::table('attendance_sessions')->where('id',$session->id)->update([
            'ended_at'=>$end->toDateTimeString(),
            'correction_reason'=>trim($row->reason),
            'corrected_by'=>$actor->id,'updated_at'=>now(),
        ]);
        TenantOps::audit($actor,'attendance.correct','attendance_session',$session->id,[
            'prior_checkout'=>$session->ended_at,'new_checkout'=>$end->toDateTimeString(),
            'review_request_id'=>$row->id,
        ]);
    }


    private function approveCheckInCorrection(object $company,object $workspace,object $row,object $actor): void
    {
        $session=DB::table('attendance_sessions')->where('id',$row->attendance_session_id)
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->where('membership_id',$row->membership_id)->lockForUpdate()->first();
        abort_unless($session && $session->work_date===$row->work_date,404);
        $start=CarbonImmutable::parse($row->proposed_start_at,'UTC');
        if ($start->gt(CarbonImmutable::now('UTC'))
            || $start->setTimezone($company->timezone)->toDateString()!==$row->work_date
            || ($session->ended_at && $start->gte(CarbonImmutable::parse($session->ended_at,'UTC')))) {
            throw ValidationException::withMessages(['decision'=>'Corrected check-in is invalid or after the recorded checkout.']);
        }
        $this->assertNoOverlap($row,$start->toDateTimeString(),
            $session->ended_at??CarbonImmutable::now('UTC')->toDateTimeString(),$session->id);
        $student=DB::table('memberships')->where('id',$row->membership_id)
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->where('role','student')->where('active',true)->first();
        abort_unless($student,404);
        $rule=WorkCalendar::forMemberDate($company,$workspace,$student,$row->work_date);
        $scheduled=CarbonImmutable::parse($row->work_date.' '.$rule['office_start'],$company->timezone);
        $graceEnd=$scheduled->addMinutes((int)$rule['grace_minutes'])->endOfMinute();
        $localStart=$start->setTimezone($company->timezone);
        $first=!DB::table('attendance_sessions')->where('company_id',$company->id)
            ->where('workspace_id',$workspace->id)->where('membership_id',$student->id)
            ->where('work_date',$row->work_date)->where('id','!=',$session->id)
            ->where('started_at','<',$session->started_at)->exists();
        $late=$first && !$rule['holiday'] && $localStart->gt($scheduled);
        $withinGrace=$late && $localStart->lte($graceEnd);
        DB::table('attendance_sessions')->where('id',$session->id)->update([
            'started_at'=>$start->toDateTimeString(),'late'=>$late,
            'late_within_grace'=>$withinGrace,
            'correction_reason'=>trim($row->reason),'corrected_by'=>$actor->id,'updated_at'=>now(),
        ]);
        TenantOps::audit($actor,'attendance.checkin.corrected','attendance_session',$session->id,[
            'prior_checkin'=>$session->started_at,'new_checkin'=>$start->toDateTimeString(),
            'review_request_id'=>$row->id,'late'=>$late,'late_within_grace'=>$withinGrace,
        ]);
    }

    private function approveMissingSession(object $company,object $workspace,object $row,object $actor): void
    {
        $start=CarbonImmutable::parse($row->proposed_start_at,'UTC');
        $end=CarbonImmutable::parse($row->proposed_end_at,'UTC');
        if ($start->gte($end) || $end->gt(CarbonImmutable::now('UTC'))
            || $start->setTimezone($company->timezone)->toDateString() !== $row->work_date
            || $end->setTimezone($company->timezone)->toDateString() !== $row->work_date) {
            throw ValidationException::withMessages(['decision'=>'Proposed punch times are invalid.']);
        }
        $this->assertNoOverlap($row,$start->toDateTimeString(),$end->toDateTimeString(),null);
        $student=DB::table('memberships')->where('company_id',$company->id)
            ->where('workspace_id',$workspace->id)->where('id',$row->membership_id)->first();
        abort_unless($student && $student->active && $student->role==='student',404);
        $rule=WorkCalendar::forMemberDate($company,$workspace,$student,$row->work_date);
        $officeStart=CarbonImmutable::parse($row->work_date.' '.$rule['office_start'],$company->timezone);
        $cutoff=$officeStart->addMinutes((int)$rule['grace_minutes'])->endOfMinute();
        $previous=DB::table('attendance_sessions')->where('company_id',$company->id)
            ->where('workspace_id',$workspace->id)->where('membership_id',$row->membership_id)
            ->where('work_date',$row->work_date)->exists();
        $id=DB::table('attendance_sessions')->insertGetId([
            'company_id'=>$company->id,'workspace_id'=>$workspace->id,
            'membership_id'=>$row->membership_id,'work_date'=>$row->work_date,
            'work_mode'=>'office','started_at'=>$start->toDateTimeString(),
            'ended_at'=>$end->toDateTimeString(),
            'late'=>!$previous && !$rule['holiday']
                && $start->setTimezone($company->timezone)->gt($officeStart),
            'late_within_grace'=>!$previous && !$rule['holiday']
                && $start->setTimezone($company->timezone)->gt($officeStart)
                && $start->setTimezone($company->timezone)->lte($cutoff),
            'holiday'=>$rule['holiday'],
            'correction_reason'=>trim($row->reason),'corrected_by'=>$actor->id,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        TenantOps::audit($actor,'attendance.missed_checkin.corrected',
            'attendance_session',$id,['review_request_id'=>$row->id]);
    }

    private function assertNoOverlap(object $row,string $start,string $end,?int $except): void
    {
        if (DB::table('attendance_sessions')->where('company_id',$row->company_id)
            ->where('workspace_id',$row->workspace_id)
            ->where('membership_id',$row->membership_id)
            ->when($except!==null,fn($q)=>$q->where('id','!=',$except))
            ->where('started_at','<',$end)
            ->where(fn($q)=>$q->whereNull('ended_at')->orWhere('ended_at','>',$start))
            ->exists()) {
            throw ValidationException::withMessages(['decision'=>'Proposed time overlaps an existing attendance session.']);
        }
    }

    private function activeAssignedManager(object $student): bool
    {
        return $student->manager_membership_id
            && DB::table('memberships')->where('id',$student->manager_membership_id)
                ->where('company_id',$student->company_id)
                ->where('workspace_id',$student->workspace_id)
                ->where('role','manager')->where('active',true)->exists();
    }

    private function assertDate(object $company,string $date): string
    {
        $day=CarbonImmutable::createFromFormat('!Y-m-d',$date,$company->timezone);
        $today=CarbonImmutable::now($company->timezone)->startOfDay();
        if (!$day || $day->toDateString()!==$date || $day->gt($today)
            || $day->lt($today->subDays(60))) {
            throw ValidationException::withMessages(['work_date'=>'Only valid dates from the past 60 days up to today are accepted.']);
        }
        return $date;
    }

    private function localTime(object $company,string $date,string $time): CarbonImmutable
    {
        $parsed=CarbonImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$time,$company->timezone);
        if (!$parsed || $parsed->toDateString()!==$date || $parsed->format('H:i')!==$time) {
            throw ValidationException::withMessages(['end_time'=>'Invalid local punch time.']);
        }
        return $parsed;
    }

    private function workspaceLock(object $company,object $workspace): void
    {
        DB::table('workspaces')->where('company_id',$company->id)->where('id',$workspace->id)
            ->lockForUpdate()->first();
    }

    private function assertPayrollEditable(object $company,object $workspace,string $date): void
    {
        if (DB::table('payroll_runs')->where('company_id',$company->id)
            ->where('workspace_id',$workspace->id)
            ->where('period_month',substr($date,0,7).'-01')
            ->whereIn('status',['reviewed','approved','paid','locked'])->exists()) {
            throw ValidationException::withMessages(['decision'=>'This payroll month has already been frozen.']);
        }
    }
}
