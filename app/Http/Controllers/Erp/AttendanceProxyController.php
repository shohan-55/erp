<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\AttendanceClock;
use App\Support\TenantOps;
use App\Support\WorkspaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttendanceProxyController extends Controller
{
    private function authorizedManager(object $actor): void
    {
        abort_unless($actor->role === 'manager' && WorkspaceAccess::allows($actor, 'attendance.proxy'), 403);
    }

    private function prohibitLocation(Request $request): void
    {
        // Manager's browser/device can NEVER become the student's GPS evidence.
        if ($request->hasAny(['location_latitude', 'location_longitude', 'location_accuracy', 'location_consent'])) {
            throw ValidationException::withMessages([
                'location' => 'Proxy attendance does not collect or impersonate student GPS.',
            ]);
        }
    }

    private function assertPayrollNotFrozen(object $actor, string $date): void
    {
        $period = substr($date, 0, 7).'-01';
        if (DB::table('payroll_runs')->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)->where('period_month', $period)
            ->whereIn('status', ['reviewed','approved','paid','locked'])->exists()) {
            throw ValidationException::withMessages(['membership_id'=>'Payroll is frozen for this attendance month.']);
        }
    }

    public function checkIn(Request $request, string $firmSlug)
    {
        [$company,$workspace,$actor] = TenantOps::context($request);
        $this->authorizedManager($actor);
        $this->prohibitLocation($request);
        $data = $request->validate([
            'membership_id'=>['required','integer','min:1'],
            'work_mode'=>['required','in:office,client'],
            'client_site'=>['nullable','string','max:180','required_if:work_mode,client'],
            'reason'=>['required','string','min:15','max:1000'],
        ]);

        DB::transaction(function () use ($company,$workspace,$actor,$data) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $fresh = DB::table('memberships')->where('id',$actor->id)
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('role','manager')->where('active',true)->whereNull('platform_suspended_at')->first();
            abort_unless($fresh && WorkspaceAccess::allows($fresh,'attendance.proxy'),403);
            $student = TenantOps::subject($fresh,(int)$data['membership_id']);
            abort_unless($student->role==='student' && $student->active
                && $student->platform_suspended_at===null,404);
            DB::table('memberships')->where('id',$student->id)->lockForUpdate()->first();

            $instant=CarbonImmutable::now($company->timezone);
            $date=$instant->toDateString();
            $this->assertPayrollNotFrozen($fresh,$date);
            if (DB::table('attendance_sessions')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('membership_id',$student->id)
                ->whereNull('ended_at')->exists()) {
                throw ValidationException::withMessages(['membership_id'=>'Student already has an active attendance session.']);
            }
            $first=!DB::table('attendance_sessions')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('membership_id',$student->id)
                ->where('work_date',$date)
                ->where(fn($q)=>$q->whereNull('proxy_status')->orWhere('proxy_status','verified')->orWhere('proxy_status','pending'))
                ->exists();
            $clock=AttendanceClock::schedule($company,$workspace,$student,$instant);
            $arrival=AttendanceClock::arrival($clock,$instant,$first);
            $id=DB::table('attendance_sessions')->insertGetId([
                'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                'membership_id'=>$student->id,'work_date'=>$date,
                'work_mode'=>$data['work_mode'],
                'client_site'=>$data['work_mode']==='client'?trim($data['client_site']):null,
                'started_at'=>$instant->utc()->toDateTimeString(),
                'ended_at'=>null,
                'late'=>$arrival['late'],'late_within_grace'=>$arrival['late_within_grace'],
                'holiday'=>$clock['holiday'],
                'proxy_status'=>'pending','proxy_started_by'=>$fresh->id,
                'proxy_start_reason'=>trim($data['reason']),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($fresh,'attendance.proxy.check_in','attendance_session',$id,[
                'student_id'=>$student->id,'reason'=>trim($data['reason']),
                'server_time'=>$instant->utc()->toDateTimeString(),
                'verification'=>'pending','location_captured'=>false,
            ]);
            TenantOps::notify($fresh,$student->id,'Proxy check-in awaiting verification',
                'Your Manager recorded a proxy check-in. Partner verification is required before reporting.');
            foreach (TenantOps::teamRecipients($fresh,$student) as $recipient) {
                TenantOps::notify($fresh,(int)$recipient,'Proxy attendance pending',
                    'A proxy check-in for an assigned student needs independent Partner verification.');
            }
        },3);
        return back()->with('success','Proxy check-in recorded using server time; awaiting Partner verification.');
    }

    public function checkOut(Request $request, string $firmSlug)
    {
        [$company,$workspace,$actor] = TenantOps::context($request);
        $this->authorizedManager($actor);
        $this->prohibitLocation($request);
        $data=$request->validate([
            'membership_id'=>['required','integer','min:1'],
            'reason'=>['required','string','min:15','max:1000'],
        ]);

        DB::transaction(function () use ($company,$workspace,$actor,$data) {
            DB::table('workspaces')->where('id',$workspace->id)
                ->where('company_id',$company->id)->lockForUpdate()->first();
            $fresh=DB::table('memberships')->where('id',$actor->id)
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('role','manager')->where('active',true)->whereNull('platform_suspended_at')->first();
            abort_unless($fresh && WorkspaceAccess::allows($fresh,'attendance.proxy'),403);
            $student=TenantOps::subject($fresh,(int)$data['membership_id']);
            abort_unless($student->role==='student' && $student->active
                && $student->platform_suspended_at===null,404);
            DB::table('memberships')->where('id',$student->id)->lockForUpdate()->first();
            $session=DB::table('attendance_sessions')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('membership_id',$student->id)
                ->where('proxy_status','pending')->whereNull('ended_at')
                ->lockForUpdate()->first();
            if (!$session) throw ValidationException::withMessages([
                'membership_id'=>'Student has no open pending proxy attendance session.',
            ]);
            $this->assertPayrollNotFrozen($fresh,$session->work_date);
            $end=CarbonImmutable::now('UTC');
            DB::table('attendance_sessions')->where('id',$session->id)
                ->whereNull('ended_at')->where('proxy_status','pending')->update([
                    'ended_at'=>$end->toDateTimeString(),
                    'proxy_ended_by'=>$fresh->id,
                    'proxy_end_reason'=>trim($data['reason']),
                    'updated_at'=>now(),
                ]);
            TenantOps::audit($fresh,'attendance.proxy.check_out','attendance_session',$session->id,[
                'student_id'=>$student->id,'reason'=>trim($data['reason']),
                'server_time'=>$end->toDateTimeString(),'verification'=>'pending','location_captured'=>false,
            ]);
            TenantOps::notify($fresh,$student->id,'Proxy check-out awaiting verification',
                'Your Manager recorded a proxy check-out. It remains subject to Partner verification.');
        },3);
        return back()->with('success','Proxy check-out saved with audit evidence; still pending verification.');
    }

    public function decide(Request $request, string $firmSlug, int $sessionId)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $data=$request->validate([
            'decision'=>['required','in:verify,reject'],
            'reason'=>['required','string','min:15','max:1000'],
        ]);
        DB::transaction(function () use ($company,$workspace,$actor,$sessionId,$data) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $fresh=DB::table('memberships')->where('id',$actor->id)
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('role','partner')->where('active',true)->whereNull('platform_suspended_at')->first();
            abort_unless($fresh,403);
            $session=DB::table('attendance_sessions')->where('id',$sessionId)
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('proxy_status','pending')->lockForUpdate()->first();
            abort_unless($session,404);
            $student=TenantOps::subject($fresh,(int)$session->membership_id);
            abort_unless($student->role==='student',404);
            if (!$session->ended_at && $data['decision']==='verify') {
                throw ValidationException::withMessages([
                    'decision'=>'Check-out is required before final proxy attendance verification.',
                ]);
            }
            $this->assertPayrollNotFrozen($fresh,$session->work_date);
            $new=$data['decision']==='verify'?'verified':'rejected';
            // An open erroneous proxy may be rejected and closed using the
            // real server time, never a fabricated student punch.
            $change=[
                'proxy_status'=>$new,'proxy_verified_by'=>$fresh->id,
                'proxy_verified_at'=>now(),'proxy_verification_note'=>trim($data['reason']),
                'updated_at'=>now(),
            ];
            if (!$session->ended_at && $new==='rejected') {
                $change['ended_at']=now()->utc()->toDateTimeString();
                $change['proxy_end_reason']='Partner rejected and closed the unverified session';
            }
            DB::table('attendance_sessions')->where('id',$session->id)
                ->where('proxy_status','pending')->update($change);
            TenantOps::audit($fresh,'attendance.proxy.'.$new,'attendance_session',$session->id,[
                'student_id'=>$student->id,'proxy_started_by'=>$session->proxy_started_by,
                'proxy_ended_by'=>$session->proxy_ended_by,'reason'=>trim($data['reason']),
                'previous_status'=>'pending','new_status'=>$new,
            ]);
            TenantOps::notify($fresh,$student->id,'Proxy attendance '.$new,
                'Partner reviewed your proxy attendance on '.$session->work_date.' ('.$new.').');
        },3);
        return back()->with('success','Proxy attendance decision recorded with immutable audit history.');
    }
}
