<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\AttendanceSession;
use App\Models\Company;
use App\Models\CompanyMembership;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalCorrectionController extends Controller
{
    private function scope(Request $request, string $slug): array
    {
        $company = Company::query()->where('slug',$slug)->where('is_active',true)->firstOrFail();
        $actor = CompanyMembership::query()->where('company_id',$company->id)
            ->where('user_id',$request->user()->id)->where('is_active',true)
            ->whereNotNull('workspace_id')->firstOrFail();
        abort_unless(in_array($actor->role,['partner','manager'],true),403);
        return [$company,$actor];
    }

    private function studentIds(Company $company, CompanyMembership $actor): array
    {
        return CompanyMembership::query()->where('company_id',$company->id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('role','student')
            ->when($actor->role==='manager',fn($q)=>$q->where('manager_membership_id',$actor->id))
            ->pluck('id')->all();
    }

    public function index(Request $request, string $companySlug)
    {
        [$company,$actor]=$this->scope($request,$companySlug);
        $sessions = AttendanceSession::query()
            ->where('company_id',$company->id)
            ->whereIn('membership_id',$this->studentIds($company,$actor))
            ->whereIn('attendance_day_id', AttendanceDay::query()->where('company_id',$company->id)
                ->where('workspace_id',$actor->workspace_id)->select('id'))
            ->orderByDesc('id')->limit(35)->get();

        return view('operational.corrections',compact('company','actor','sessions'));
    }

    public function correct(Request $request, string $companySlug, int $sessionId)
    {
        [$company,$actor]=$this->scope($request,$companySlug);
        $data=$request->validate([
            'checked_in_local'=>['required','date_format:Y-m-d\TH:i'],
            'checked_out_local'=>['required','date_format:Y-m-d\TH:i'],
            'reason'=>['required','string','min:10','max:2000'],
        ]);

        $newIn=CarbonImmutable::createFromFormat('!Y-m-d\TH:i',$data['checked_in_local'],$company->timezone)->utc();
        $newOut=CarbonImmutable::createFromFormat('!Y-m-d\TH:i',$data['checked_out_local'],$company->timezone)->utc();

        if ($newOut->lessThanOrEqualTo($newIn) || $newIn->diffInMinutes($newOut)>1440 || $newOut->greaterThan(CarbonImmutable::now('UTC')->addMinute())) {
            throw ValidationException::withMessages(['checked_out_local'=>'End time must follow start time, be no more than 24 hours later, and not be in the future.']);
        }

        DB::transaction(function () use ($company,$actor,$sessionId,$data,$newIn,$newOut) {
            // Acquire the same student-membership lock as check-in and check-out operations.
            $session=AttendanceSession::query()->where('company_id',$company->id)
                ->whereKey($sessionId)->firstOrFail();
            CompanyMembership::query()->whereKey($session->membership_id)->lockForUpdate()->firstOrFail();
            $session=AttendanceSession::query()->where('company_id',$company->id)
                ->whereKey($sessionId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($session->membership_id,$this->studentIds($company,$actor),true),403);
            $day=AttendanceDay::query()->whereKey($session->attendance_day_id)
                ->where('company_id',$company->id)->where('workspace_id',$actor->workspace_id)
                ->where('membership_id',$session->membership_id)->firstOrFail();

            if ($newIn->setTimezone($company->timezone)->toDateString() !== $day->work_date) {
                throw ValidationException::withMessages(['checked_in_local'=>'The corrected check-in must remain on the original attendance date.']);
            }

            $overlap=AttendanceSession::query()->where('company_id',$company->id)
                ->where('membership_id',$session->membership_id)->where('id','!=',$session->id)
                ->where('checked_in_at','<',$newOut)
                ->where(fn($q)=>$q->whereNull('checked_out_at')->orWhere('checked_out_at','>',$newIn))
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['checked_in_local'=>'Correction overlaps another attendance session.']);
            }

            DB::table('attendance_corrections')->insert([
                'company_id'=>$company->id,'workspace_id'=>$actor->workspace_id,
                'attendance_session_id'=>$session->id,'actor_membership_id'=>$actor->id,
                'old_checked_in_at'=>$session->checked_in_at,
                'old_checked_out_at'=>$session->checked_out_at,
                'new_checked_in_at'=>$newIn,'new_checked_out_at'=>$newOut,
                'reason'=>trim($data['reason']),'created_at'=>now(),
            ]);

            $session->update(['checked_in_at'=>$newIn,'checked_out_at'=>$newOut,'is_open'=>false]);

            $all=AttendanceSession::query()->where('attendance_day_id',$day->id)->orderBy('checked_in_at')->get();
            if ($all->contains(fn($s)=>$s->is_open)) {
                $status='incomplete';
            } else {
                $earliest=CarbonImmutable::instance($all->firstOrFail()->checked_in_at)->setTimezone($company->timezone);
                $status=in_array($earliest->dayOfWeekIso,[5,6],true)
                    ? 'holiday_work' : ($earliest->greaterThan($earliest->setTime(10,10,59)) ? 'late' : 'on_time');
            }
            $day->update(['status'=>$status]);
        },3);

        return back()->with('status','Attendance correction saved with an immutable change history.');
    }
}
