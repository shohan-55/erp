<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);

        $records = DB::table('attendance_sessions as s')
            ->join('memberships as m', 'm.id', '=', 's.membership_id')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('s.company_id', $company->id)
            ->where('s.workspace_id', $workspace->id)
            ->when($actor->role === 'manager', fn ($q) => $q->where('m.manager_membership_id', $actor->id))
            ->when($actor->role === 'student', fn ($q) => $q->where('m.id', $actor->id))
            ->select('s.*', 'u.name as member_name')
            ->orderByDesc('s.started_at')
            ->limit(100)->get();

        $openSession = $actor->role === 'student'
            ? DB::table('attendance_sessions')->where('membership_id', $actor->id)
                ->whereNull('ended_at')->first() : null;

        return view('erp.attendance', compact('company', 'workspace', 'actor', 'firmSlug', 'records', 'openSession'));
    }

    public function checkIn(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['student']);

        $data = $request->validate([
            'work_mode' => ['required', 'in:office,client'],
            'client_site' => ['nullable', 'string', 'max:180', 'required_if:work_mode,client'],
        ]);

        DB::transaction(function () use ($company, $actor, $data) {
            // Every check-in/out for a member acquires the same row lock so concurrent
            // requests cannot create overlapping active sessions.
            DB::table('memberships')->where('id', $actor->id)->lockForUpdate()->first();

            if (DB::table('attendance_sessions')->where('membership_id', $actor->id)
                ->whereNull('ended_at')->exists()) {
                throw ValidationException::withMessages(['work_mode' => 'Please check out of your current session first.']);
            }

            $localNow = CarbonImmutable::now($company->timezone);
            $day = $localNow->toDateString();
            $firstSession = ! DB::table('attendance_sessions')->where('membership_id', $actor->id)
                ->where('work_date', $day)->exists();

            $cutoff = CarbonImmutable::parse($day.' '.$company->office_start, $company->timezone)
                ->addMinutes($company->grace_minutes)->endOfMinute();
            $holidays = json_decode($company->weekly_holidays ?? '[5,6]', true);
            $isHoliday = in_array($localNow->isoWeekday(), is_array($holidays) ? $holidays : [5, 6], true);

            $id = DB::table('attendance_sessions')->insertGetId([
                'company_id' => $actor->company_id,
                'workspace_id' => $actor->workspace_id,
                'membership_id' => $actor->id,
                'work_date' => $day,
                'work_mode' => $data['work_mode'],
                'client_site' => $data['work_mode'] === 'client' ? trim($data['client_site']) : null,
                'started_at' => $localNow->utc()->toDateTimeString(),
                'late' => $firstSession && ! $isHoliday && $localNow->greaterThan($cutoff),
                'holiday' => $isHoliday,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            TenantOps::audit($actor, 'attendance.check_in', 'attendance_session', $id, [
                'work_mode' => $data['work_mode'], 'local_date' => $day,
            ]);
        }, 3);

        return back()->with('success', 'Check-in saved using the server clock.');
    }

    public function checkOut(Request $request, string $firmSlug)
    {
        [, , $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['student']);

        DB::transaction(function () use ($actor) {
            DB::table('memberships')->where('id', $actor->id)->lockForUpdate()->first();

            $session = DB::table('attendance_sessions')
                ->where('company_id', $actor->company_id)
                ->where('membership_id', $actor->id)
                ->whereNull('ended_at')->lockForUpdate()->first();

            if (! $session) {
                throw ValidationException::withMessages(['work_mode' => 'No active check-in was found.']);
            }

            $ended = now()->utc();
            DB::table('attendance_sessions')->where('id', $session->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => $ended, 'updated_at' => now()]);

            TenantOps::audit($actor, 'attendance.check_out', 'attendance_session', $session->id);
        }, 3);

        return back()->with('success', 'Check-out recorded.');
    }

    public function correction(Request $request, string $firmSlug, int $sessionId)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);

        $data = $request->validate([
            'ended_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        DB::transaction(function () use ($company, $workspace, $actor, $sessionId, $data) {
            $session = DB::table('attendance_sessions')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('id', $sessionId)->lockForUpdate()->first();
            abort_unless($session, 404);

            $localTime = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['ended_at'], $company->timezone);
            if (! $localTime || $localTime->utc()->lessThan(CarbonImmutable::parse($session->started_at, 'UTC'))
                || $localTime->utc()->greaterThan(CarbonImmutable::now('UTC'))
                || $localTime->toDateString() !== $session->work_date) {
                throw ValidationException::withMessages(['ended_at' => 'The corrected checkout must be on the same date, after check-in, and not in the future.']);
            }

            $period = substr($session->work_date, 0, 7).'-01';
            if (DB::table('payroll_runs')->where('workspace_id', $workspace->id)->where('period_month', $period)
                ->whereIn('status', ['reviewed', 'approved', 'paid', 'locked'])->exists()) {
                throw ValidationException::withMessages(['ended_at' => 'This attendance period is frozen by payroll review.']);
            }

            DB::table('attendance_sessions')->where('id', $session->id)->update([
                'ended_at' => $localTime->utc()->toDateTimeString(),
                'correction_reason' => trim($data['reason']),
                'corrected_by' => $actor->id, 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'attendance.correct', 'attendance_session', $session->id, [
                'prior_checkout' => $session->ended_at,
                'new_checkout' => $localTime->utc()->toDateTimeString(),
                'reason' => $data['reason'],
            ]);
        }, 3);

        return back()->with('success', 'Attendance correction recorded with audit details.');
    }
}