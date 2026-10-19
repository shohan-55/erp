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

final class ProxyAttendanceController extends Controller
{
    public function save(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        abort_unless(WorkspaceAccess::allows($actor,'attendance.proxy'),403);
        $input=$request->validate([
            'membership_id'=>['required','integer','min:1'],
            'action'=>['required','in:check_in,check_out'],
            'work_mode'=>['nullable','in:office,client'],
            'client_site'=>['nullable','string','max:180'],
            'reason'=>['required','string','min:20','max:1000'],
        ]);
        if ($input['action']==='check_in' && ($input['work_mode']??'office')==='client'
            && blank($input['client_site']??null)) {
            throw ValidationException::withMessages(['client_site'=>'Specify the verified client site.']);
        }
        DB::transaction(function()use($company,$workspace,$actor,$input) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $fresh=DB::table('memberships')->where('id',$actor->id)
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('active',true)->whereNull('platform_suspended_at')->first();
            abort_unless($fresh && WorkspaceAccess::allows($fresh,'attendance.proxy'),403);
            $student=WorkspaceAccess::subject($fresh,(int)$input['membership_id'],'attendance.proxy');
            DB::table('memberships')->where('id',$student->id)->lockForUpdate()->first();
            $now=CarbonImmutable::now($company->timezone);
            $day=$now->toDateString();
            if (DB::table('payroll_runs')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)
                ->where('period_month',$now->format('Y-m').'-01')
                ->whereIn('status',['reviewed','approved','paid','locked'])->exists()) {
                throw ValidationException::withMessages(['action'=>'Payroll is frozen; contact the Partner for an audited exception.']);
            }
            $open=DB::table('attendance_sessions')
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('membership_id',$student->id)->whereNull('ended_at')->lockForUpdate()->first();
            if ($input['action']==='check_in') {
                if ($open) throw ValidationException::withMessages(['action'=>'Student already has an open attendance session.']);
                $first=!DB::table('attendance_sessions')->where('company_id',$company->id)
                    ->where('workspace_id',$workspace->id)->where('membership_id',$student->id)
                    ->where('work_date',$day)->exists();
                $clock=AttendanceClock::schedule($company,$workspace,$student,$now);
                $arrival=AttendanceClock::arrival($clock,$now,$first);
                $id=DB::table('attendance_sessions')->insertGetId([
                    'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                    'membership_id'=>$student->id,'work_date'=>$day,
                    'work_mode'=>$input['work_mode']??'office',
                    'client_site'=>($input['work_mode']??'office')==='client'?trim($input['client_site']):null,
                    'started_at'=>$now->utc()->toDateTimeString(),
                    'late'=>$arrival['late'],'late_within_grace'=>$arrival['late_within_grace'],
                    'holiday'=>$clock['holiday'],
                    'proxy_checked_in_by'=>$actor->id,
                    'correction_reason'=>trim($input['reason']),
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                $event='attendance.proxy_check_in';
            } else {
                if (!$open) throw ValidationException::withMessages(['action'=>'Student has no open attendance session.']);
                $id=$open->id;
                DB::table('attendance_sessions')->where('id',$id)->whereNull('ended_at')->update([
                    'ended_at'=>$now->utc()->toDateTimeString(),
                    'proxy_checked_out_by'=>$actor->id,'updated_at'=>now(),
                ]);
                $event='attendance.proxy_check_out';
            }
            TenantOps::audit($actor,$event,'attendance_session',$id,[
                'student_membership_id'=>$student->id,'reason'=>trim($input['reason']),
                'server_timestamp'=>$now->toIso8601String(),
                'presence_not_independently_verified'=>true,
                'proxy_location_not_collected'=>true,
            ]);
            TenantOps::notify($actor,$student->id,'Attendance entered by authorized staff',
                'An authorized staff member recorded '.$input['action'].' for you at the server time. Verify your attendance history.');
        },3);
        return back()->with('success','Proxy attendance recorded at actual server time, with verified operator identity and an audit trail.');
    }
}
