<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\PolicyResolver;
use App\Support\DashboardOverview;
use App\Support\WorkspacePulse;
use App\Support\WorkCalendar;
use App\Support\AttendanceClock;
use App\Support\DashboardMonthCalendar;
use App\Support\StudentFeatureAccess;
use App\Support\ErpFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalController extends Controller
{
    public function dashboard(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        $members = DB::table('memberships')
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)
            ->where('active', true)
            ->when($actor->role === 'manager', fn ($q) => $q->where('manager_membership_id', $actor->id))
            ->when($actor->role === 'student', fn ($q) => $q->where('id', $actor->id));

        $ids = $members->pluck('id');
        $today = now($company->timezone)->toDateString();
        $effectivePolicy = PolicyResolver::forDate($company, $workspace, $today);
        if (in_array($actor->role, ['student', 'manager'], true)) {
            $resolved = $actor->role === 'student'
                ? WorkCalendar::forMemberDate($company, $workspace, $actor, $today)
                : WorkCalendar::forDate(
                    $company, $workspace, $today,
                    is_array($effectivePolicy['weekly_holidays']) ? $effectivePolicy['weekly_holidays'] : [5, 6],
                    $effectivePolicy
                );
            $effectivePolicy = [...$effectivePolicy,
                'office_start' => $resolved['office_start'], 'office_end' => $resolved['office_end'],
                'grace_minutes' => $resolved['grace_minutes']];
        }
        // Only the authenticated member's own open session is shown here.
        $activeAttendance = in_array($actor->role, ['student', 'manager'], true)
            ? DB::table('attendance_sessions')
                ->where('company_id', $company->id)
                ->where('workspace_id', $workspace->id)
                ->where('membership_id', $actor->id)
                ->whereNull('ended_at')
                ->first()
            : null;

        // Server-authoritative schedule for the clock UI; no client clock
        // or frontend status is accepted for actual attendance records.
        $attendanceClock = in_array($actor->role, ['student', 'manager'], true)
            ? AttendanceClock::schedule($company, $workspace, $actor) : null;
        $qualifyingLateDays = $attendanceClock
            ? AttendanceClock::qualifyingLateDays($company, $workspace, $actor, $today) : 0;

        $showPersonalCalendar = ErpFeatures::enabled($actor, 'attendance');
        $monthCalendar = DashboardMonthCalendar::forActor(
            $company,$workspace,$actor,$showPersonalCalendar
        );

        $stats = [
            'members' => $ids->count(),
            'checked_in_today' => DB::table('attendance_sessions')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->whereIn('membership_id', $ids)->where('work_date', $today)
                ->distinct()->count('membership_id'),
            'pending_leaves' => DB::table('leave_requests')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->whereIn('membership_id', $ids)
                ->whereIn('status', ['pending', 'manager_reviewed'])->count(),
            'pending_claims' => DB::table('conveyance_claims')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->whereIn('membership_id', $ids)->where('status', 'pending')->count(),
            'unread_notifications' => DB::table('tenant_notifications')
                ->where('company_id', $company->id)
                ->where('recipient_membership_id', $actor->id)->whereNull('read_at')->count(),
        ];

        $recent = DB::table('attendance_sessions as a')
            ->join('memberships as m', 'm.id', '=', 'a.membership_id')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('a.company_id', $company->id)->where('a.workspace_id', $workspace->id)
            ->whereIn('a.membership_id', $actor->role === 'manager' ? array_values(array_unique([...$ids->all(), $actor->id])) : $ids)
            ->select('a.*', 'u.name as member_name')
            ->orderByDesc('a.started_at')->limit(8)->get();

        $queues = DashboardOverview::queues($actor);
        $quickActions = DashboardOverview::actions($actor, $queues);
        // Dashboard action links are filtered by the same backend gate that
        // protects GET/POST/download routes, not by role label alone.
        $featureMap = ErpFeatures::enabledMap($actor);
        $quickActions = array_values(array_filter($quickActions, static function (array $item) use ($featureMap): bool {
            $module = ErpFeatures::forRoute($item['route']);
            return $module === null || ($featureMap[$module] ?? false);
        }));
        $workPulse = WorkspacePulse::forActor($actor, $queues, $activeAttendance);

        return view('erp.dashboard', compact(
            'company', 'workspace', 'actor', 'firmSlug',
            'stats', 'recent', 'effectivePolicy', 'activeAttendance',
            'attendanceClock', 'qualifyingLateDays',
            'queues', 'quickActions', 'monthCalendar', 'workPulse'
        ));
    }
}