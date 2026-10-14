<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\PolicyResolver;
use App\Support\WorkCalendar;
use App\Support\AttendanceLocation;
use Illuminate\Support\Facades\Crypt;
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

        $locationSessionIds = $actor->role === 'student'
            ? collect()
            : DB::table('attendance_locations')
                ->where('company_id', $company->id)
                ->where('workspace_id', $workspace->id)
                ->whereIn('attendance_session_id', $records->pluck('id'))
                ->distinct()->pluck('attendance_session_id');

        return view('erp.attendance', compact('company', 'workspace', 'actor', 'firmSlug', 'records', 'openSession', 'locationSessionIds'));
    }

    public function checkIn(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['student']);

        $data = $request->validate([
            'work_mode' => ['required', 'in:office,client'],
            'client_site' => ['nullable', 'string', 'max:180', 'required_if:work_mode,client'],
        ]);

        $location = AttendanceLocation::fromRequest($request, $workspace);

        DB::transaction(function () use ($company, $workspace, $actor, $data, $location) {
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

            $policy = PolicyResolver::forDate($company, $workspace, $day);
            $cutoff = CarbonImmutable::parse($day.' '.$policy['office_start'], $company->timezone)
                ->addMinutes($policy['grace_minutes'])->endOfMinute();
            $holidays = is_array($policy['weekly_holidays']) ? $policy['weekly_holidays'] : [5, 6];
            $calendar = WorkCalendar::forDate($company, $workspace, $day, $holidays);
            $isHoliday = $calendar['holiday'];

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

            $captured = AttendanceLocation::record($actor, $id, 'check_in', $location);
            TenantOps::audit($actor, 'attendance.check_in', 'attendance_session', $id, [
                'work_mode' => $data['work_mode'], 'local_date' => $day,
                'calendar_source' => $calendar['source'], 'calendar_exception_id' => $calendar['exception_id'],
                'browser_location_captured' => $captured,
            ]);
        }, 3);

        return back()->with('success', 'Check-in saved using the server clock.');
    }

    public function checkOut(Request $request, string $firmSlug)
    {
        [, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['student']);
        $location = AttendanceLocation::fromRequest($request, $workspace);

        DB::transaction(function () use ($actor, $location) {
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

            $captured = AttendanceLocation::record($actor, $session->id, 'check_out', $location);
            TenantOps::audit($actor, 'attendance.check_out', 'attendance_session', $session->id, [
                'browser_location_captured' => $captured,
            ]);
        }, 3);

        return back()->with('success', 'Check-out recorded.');
    }

    /**
     * Show browser-reported GPS to the owning Partner or the assigned Manager.
     * Do not expose a public map/link: query-string coordinates leak to third parties.
     */
    public function location(Request $request, string $firmSlug, int $sessionId)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner', 'manager']);

        $session = DB::table('attendance_sessions')
            ->where('id', $sessionId)
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)->first();
        abort_unless($session, 404);
        TenantOps::subject($actor, $session->membership_id);

        $locations = DB::table('attendance_locations')
            ->where('attendance_session_id', $session->id)
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)
            ->where('membership_id', $session->membership_id)
            ->orderBy('captured_at')->get();

        abort_if($locations->isEmpty(), 404);

        $readings = $locations->map(fn ($location) => [
            'event' => $location->event,
            'latitude' => Crypt::decryptString($location->latitude_encrypted),
            'longitude' => Crypt::decryptString($location->longitude_encrypted),
            'accuracy_meters' => $location->accuracy_meters,
            'captured_at' => $location->captured_at,
        ]);

        return response()->view('erp.attendance-location', compact(
            'company', 'workspace', 'actor', 'firmSlug', 'session', 'readings'
        ))->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function updateLocationPolicy(Request $request, string $firmSlug)
    {
        [, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);

        $input = $request->validate([
            'attendance_location_enabled' => ['required', 'boolean'],
            'attendance_location_retention_days' => ['required', 'integer', 'between:7,90'],
            'notice_acknowledged' => ['required_if:attendance_location_enabled,1', 'accepted'],
        ]);

        DB::transaction(function () use ($actor, $workspace, $input) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $actor->company_id)->lockForUpdate()->first();
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $actor->company_id)->update([
                    'attendance_location_enabled' => (bool) $input['attendance_location_enabled'],
                    'attendance_location_retention_days' => (int) $input['attendance_location_retention_days'],
                    'updated_at' => now(),
                ]);
            TenantOps::audit($actor, 'attendance.location_policy.updated', 'workspace', $workspace->id, [
                'enabled' => (bool) $input['attendance_location_enabled'],
                'retention_days' => (int) $input['attendance_location_retention_days'],
            ]);
        }, 3);

        return back()->with('success', 'Attendance location settings updated for your Partner workspace.');
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
            // Serialize with payroll review so a late correction cannot slip
            // into a period while its snapshot is being finalized.
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();

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

            $newCheckout = $localTime->utc()->toDateTimeString();
            $overlapExists = DB::table('attendance_sessions')
                ->where('company_id', $company->id)
                ->where('workspace_id', $workspace->id)
                ->where('membership_id', $session->membership_id)
                ->where('id', '!=', $session->id)
                ->where('started_at', '<', $newCheckout)
                ->where(function ($query) use ($session) {
                    $query->whereNull('ended_at')
                        ->orWhere('ended_at', '>', $session->started_at);
                })
                ->exists();

            if ($overlapExists) {
                throw ValidationException::withMessages([
                    'ended_at' => 'The corrected checkout would overlap another attendance session.',
                ]);
            }

            DB::table('attendance_sessions')->where('id', $session->id)->update([
                'ended_at' => $newCheckout,
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