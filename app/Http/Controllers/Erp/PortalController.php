<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\PolicyResolver;
use App\Support\DashboardOverview;
use App\Support\WorkCalendar;
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
        if ($actor->role === 'student') {
            $resolved = WorkCalendar::forMemberDate($company, $workspace, $actor, $today);
            $effectivePolicy = [...$effectivePolicy,
                'office_start' => $resolved['office_start'], 'office_end' => $resolved['office_end'],
                'grace_minutes' => $resolved['grace_minutes']];
        }
        // Show the actual student's open session only, never another team
        // member's session or client-provided attendance state.
        $activeAttendance = $actor->role === 'student'
            ? DB::table('attendance_sessions')
                ->where('company_id', $company->id)
                ->where('workspace_id', $workspace->id)
                ->where('membership_id', $actor->id)
                ->whereNull('ended_at')
                ->first()
            : null;

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
            ->whereIn('a.membership_id', $ids)
            ->select('a.*', 'u.name as member_name')
            ->orderByDesc('a.started_at')->limit(8)->get();

        $queues = DashboardOverview::queues($actor);
        $quickActions = DashboardOverview::actions($actor, $queues);

        return view('erp.dashboard', compact(
            'company', 'workspace', 'actor', 'firmSlug',
            'stats', 'recent', 'effectivePolicy', 'activeAttendance',
            'queues', 'quickActions'
        ));
    }
}