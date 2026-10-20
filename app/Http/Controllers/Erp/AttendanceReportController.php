<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceReportController extends Controller
{
    /**
     * Report recorded attendance, not assumed absences or payroll penalties.
     * No salary or browser location is read.
     */
    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner', 'manager', 'student']);

        $input = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = $input['month'] ?? CarbonImmutable::now($company->timezone)->format('Y-m');
        $first = $month.'-01';
        $last = CarbonImmutable::parse($first)->endOfMonth()->toDateString();

        $rows = DB::table('attendance_sessions as s')
            ->join('memberships as m', 'm.id', '=', 's.membership_id')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('s.company_id', $company->id)
            ->where('s.workspace_id', $workspace->id)
            ->where(fn($q)=>$q->whereNull('s.proxy_status')->orWhere('s.proxy_status','verified'))
            ->where('m.company_id', $company->id)
            ->where('m.workspace_id', $workspace->id)
            ->where('m.role', 'student')
            ->whereBetween('s.work_date', [$first, $last])
            ->when($actor->role === 'manager', fn ($q) => $q->where('m.manager_membership_id', $actor->id))
            ->when($actor->role === 'student', fn ($q) => $q->where('m.id', $actor->id))
            ->select('m.id as membership_id', 'u.name as member_name')
            ->selectRaw('COUNT(*) as session_count')
            ->selectRaw('COUNT(DISTINCT s.work_date) as attended_days')
            ->selectRaw('SUM(CASE WHEN s.late = 1 THEN 1 ELSE 0 END) as late_sessions')
            ->selectRaw('SUM(CASE WHEN s.ended_at IS NULL THEN 1 ELSE 0 END) as incomplete_sessions')
            ->selectRaw('COUNT(DISTINCT CASE WHEN s.holiday = 1 THEN s.work_date ELSE NULL END) as holiday_days')
            ->groupBy('m.id', 'u.name')
            ->orderBy('u.name')
            ->orderBy('m.id')
            ->paginate(100)->withQueryString();

        return response()->view('erp.attendance-report', compact(
            'company', 'workspace', 'actor', 'firmSlug', 'month', 'rows'
        ))->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
