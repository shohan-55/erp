<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\PolicyResolver;
use App\Support\WorkspaceRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkspaceOperationsController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner', 'manager']);

        $rules = WorkspaceRules::effective($company, $workspace, now($company->timezone)->toDateString());
        $attendanceRules = PolicyResolver::forDate($company, $workspace, now($company->timezone)->toDateString());
        $attendanceRequests = DB::table('workspace_attendance_policy_requests as r')
            ->join('memberships as m', 'm.id', '=', 'r.proposed_by')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('r.company_id', $company->id)->where('r.workspace_id', $workspace->id)
            ->select('r.*', 'u.name as proposer_name')
            ->orderByDesc('r.id')->limit(20)->get();

        $policyRequests = DB::table('workspace_operational_policy_requests as r')
            ->join('memberships as m', 'm.id', '=', 'r.proposed_by')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('r.company_id', $company->id)->where('r.workspace_id', $workspace->id)
            ->select('r.*', 'u.name as proposer_name')
            ->orderByDesc('r.id')->limit(20)->get();

        $holidayRequests = DB::table('workspace_holiday_requests as r')
            ->join('memberships as m', 'm.id', '=', 'r.proposed_by')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('r.company_id', $company->id)->where('r.workspace_id', $workspace->id)
            ->select('r.*', 'u.name as proposer_name')
            ->orderByDesc('r.id')->limit(20)->get();

        $calendar = DB::table('workspace_day_overrides')
            ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
            ->orderByDesc('work_date')->orderByDesc('id')->limit(20)->get();

        return view('erp.workspace-operations', compact(
            'company', 'workspace', 'actor', 'firmSlug', 'rules', 'attendanceRules', 'attendanceRequests', 'policyRequests', 'holidayRequests', 'calendar'
        ));
    }

    public function proposeAttendance(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner', 'manager']);

        $data = $request->validate([
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'office_start' => ['required', 'date_format:H:i'],
            'office_end' => ['required', 'date_format:H:i', 'after:office_start'],
            'grace_minutes' => ['required', 'integer', 'between:0,30'],
            'weekly_holidays' => ['required', 'array', 'min:1', 'max:3'],
            'weekly_holidays.*' => ['integer', 'between:1,7', 'distinct'],
            'reason' => ['required', 'string', 'min:15', 'max:1000'],
        ]);
        $firstNextMonth = CarbonImmutable::now($company->timezone)->startOfMonth()->addMonth()->toDateString();
        if ($data['effective_date'] < $firstNextMonth
            || ! str_ends_with($data['effective_date'], '-01')) {
            throw ValidationException::withMessages(['effective_date' => 'Choose the first day of a future month.']);
        }

        // Partner direct overrides use the established versioned, audited path.
        if ($actor->role === 'partner') {
            return app(PolicyController::class)->override($request, $firmSlug);
        }

        DB::transaction(function () use ($company, $workspace, $actor, $data) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            if (DB::table('workspace_attendance_policy_requests')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('effective_date', $data['effective_date'])->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['effective_date' =>
                    'There is already a pending attendance policy for this month.']);
            }

            $id = DB::table('workspace_attendance_policy_requests')->insertGetId([
                'company_id' => $company->id, 'workspace_id' => $workspace->id,
                'proposed_by' => $actor->id, 'effective_date' => $data['effective_date'],
                'office_start' => $data['office_start'], 'office_end' => $data['office_end'],
                'grace_minutes' => $data['grace_minutes'],
                'weekly_holidays' => json_encode(array_map('intval', $data['weekly_holidays']), JSON_THROW_ON_ERROR),
                'reason' => trim($data['reason']), 'status' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'workspace.attendance.requested', 'workspace_attendance_policy_request', $id);
            $this->notifyPartners($actor, 'Attendance rules need Partner approval',
                'A Manager has requested a change in office schedule, grace time or weekly holidays.');
        }, 3);

        return back()->with('success', 'Attendance policy request sent for Partner approval.');
    }

    public function decideAttendance(Request $request, string $firmSlug, int $id)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject']]);

        DB::transaction(function () use ($company, $workspace, $actor, $id, $data) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            $row = DB::table('workspace_attendance_policy_requests')
                ->where('id', $id)->where('company_id', $company->id)
                ->where('workspace_id', $workspace->id)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($row->status !== 'pending' ||
                $row->effective_date < CarbonImmutable::now($company->timezone)
                    ->startOfMonth()->addMonth()->toDateString()) {
                throw ValidationException::withMessages(['decision' => 'This attendance request is no longer pending for a future month.']);
            }

            if ($data['decision'] === 'approve') {
                if (DB::table('workspace_policy_overrides')
                    ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                    ->where('effective_date', $row->effective_date)->exists()) {
                    throw ValidationException::withMessages(['decision' => 'An override already exists for that month.']);
                }

                $versionId = DB::table('workspace_policy_overrides')->insertGetId([
                    'company_id' => $company->id, 'workspace_id' => $workspace->id,
                    'authorized_by' => $actor->id, 'effective_date' => $row->effective_date,
                    'office_start' => $row->office_start, 'office_end' => $row->office_end,
                    'grace_minutes' => $row->grace_minutes, 'weekly_holidays' => $row->weekly_holidays,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                TenantOps::audit($actor, 'policy.override_workspace', 'workspace_policy_override', $versionId,
                    ['request_id' => $id, 'effective_date' => $row->effective_date]);
            }
            DB::table('workspace_attendance_policy_requests')->where('id', $id)->update([
                'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
                'decided_by' => $actor->id, 'decided_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'workspace.attendance.'.$data['decision'], 'workspace_attendance_policy_request', $id);
            $proposerActive = DB::table('memberships')->where('id', $row->proposed_by)
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('active', true)->exists();
            if ($proposerActive) {
                TenantOps::notify($actor, $row->proposed_by, 'Attendance policy decision',
                    'Your attendance policy request was '.$data['decision'].'d.');
            }
        }, 3);

        return back()->with('success', 'Attendance rule decision recorded.');
    }

    public function proposeRules(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner', 'manager']);
        $data = $request->validate([
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'leave_max_days' => ['required', 'integer', 'between:1,61'],
            'annual_leave_notice_days' => ['required', 'integer', 'between:0,30'],
            'conveyance_max_per_claim' => ['required', 'numeric', 'between:1,50000', 'decimal:0,2'],
            'reason' => ['required', 'string', 'min:15', 'max:1000'],
        ]);
        $firstNextMonth = CarbonImmutable::now($company->timezone)->startOfMonth()->addMonth()->toDateString();
        if ($data['effective_date'] < $firstNextMonth
            || ! str_ends_with($data['effective_date'], '-01')) {
            throw ValidationException::withMessages(['effective_date' =>
                'Operational policy must begin on the first day of a future month.']);
        }

        DB::transaction(function () use ($company, $workspace, $actor, $data) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();

            if (DB::table('workspace_operational_policy_requests')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('effective_date', $data['effective_date'])
                ->whereIn('status', ['pending', 'approved'])->exists()) {
                throw ValidationException::withMessages(['effective_date' =>
                    'A pending or approved policy already exists for that effective month.']);
            }

            $id = DB::table('workspace_operational_policy_requests')->insertGetId([
                'company_id' => $company->id,
                'workspace_id' => $workspace->id,
                'proposed_by' => $actor->id,
                'decided_by' => $actor->role === 'partner' ? $actor->id : null,
                'effective_date' => $data['effective_date'],
                'leave_max_days' => $data['leave_max_days'],
                'annual_leave_notice_days' => $data['annual_leave_notice_days'],
                'conveyance_max_per_claim' => $data['conveyance_max_per_claim'],
                'reason' => trim($data['reason']),
                'status' => $actor->role === 'partner' ? 'approved' : 'pending',
                'decided_at' => $actor->role === 'partner' ? now() : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'workspace.rules.'.($actor->role === 'partner' ? 'approved' : 'requested'),
                'workspace_operational_policy_request', $id, ['effective_date' => $data['effective_date']]);
            if ($actor->role === 'manager') {
                $this->notifyPartners($actor, 'Workspace policy approval required',
                    'A Manager submitted future leave and conveyance rules for your review.');
            }
        }, 3);

        return back()->with('success', $actor->role === 'partner'
            ? 'New workspace rules approved; they will apply on their future effective date.'
            : 'Policy change requested. The Partner must approve before it applies.');
    }

    public function decideRules(Request $request, string $firmSlug, int $id)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject']]);

        DB::transaction(function () use ($company, $workspace, $actor, $id, $data) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            $row = DB::table('workspace_operational_policy_requests')
                ->where('id', $id)->where('company_id', $company->id)
                ->where('workspace_id', $workspace->id)->lockForUpdate()->first();
            abort_unless($row, 404);

            if ($row->status !== 'pending'
                || $row->effective_date < CarbonImmutable::now($company->timezone)
                    ->startOfMonth()->addMonth()->toDateString()) {
                throw ValidationException::withMessages(['decision' =>
                    'Policy request is no longer pending for a future month.']);
            }
            if ($data['decision'] === 'approve'
                && DB::table('workspace_operational_policy_requests')
                    ->where('workspace_id', $workspace->id)->where('effective_date', $row->effective_date)
                    ->where('status', 'approved')->exists()) {
                throw ValidationException::withMessages(['decision' => 'Another policy is already approved for this month.']);
            }

            DB::table('workspace_operational_policy_requests')->where('id', $id)->update([
                'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
                'decided_by' => $actor->id, 'decided_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'workspace.rules.'.$data['decision'],
                'workspace_operational_policy_request', $id);
            TenantOps::notify($actor, $row->proposed_by, 'Workspace policy decision',
                'Your workspace policy request was '.$data['decision'].'d.');
        }, 3);

        return back()->with('success', 'Workspace rule decision saved.');
    }

    public function proposeHoliday(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner', 'manager']);
        $data = $request->validate([
            'work_date' => ['required', 'date_format:Y-m-d'],
            'day_type' => ['required', 'in:working,holiday'],
            'reason' => ['required', 'string', 'min:15', 'max:1000'],
        ]);
        if ($data['work_date'] <= now($company->timezone)->toDateString()) {
            throw ValidationException::withMessages(['work_date' => 'Select a future date.']);
        }

        // Existing Partner quick-calendar changes have their own audited path.
        // This review flow permits Managers to propose, never silently approve.
        if ($actor->role === 'partner') {
            return app(PolicyController::class)->dayOverride($request, $firmSlug);
        }

        DB::transaction(function () use ($company, $workspace, $actor, $data) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            if ($data['work_date'] <= now($company->timezone)->toDateString()) {
                throw ValidationException::withMessages(['work_date' => 'Select a future date.']);
            }
            if (DB::table('workspace_holiday_requests')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('work_date', $data['work_date'])->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['work_date' => 'A holiday request is already pending for this date.']);
            }

            $id = DB::table('workspace_holiday_requests')->insertGetId([
                'company_id' => $company->id, 'workspace_id' => $workspace->id,
                'proposed_by' => $actor->id, 'work_date' => $data['work_date'],
                'day_type' => $data['day_type'], 'reason' => trim($data['reason']),
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'workspace.holiday.requested', 'workspace_holiday_request', $id);
            $this->notifyPartners($actor, 'Holiday request awaits approval',
                'A Manager requested a special working day or holiday.');
        }, 3);

        return back()->with('success', 'Holiday request submitted for Partner approval.');
    }

    public function decideHoliday(Request $request, string $firmSlug, int $id)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject']]);

        DB::transaction(function () use ($company, $workspace, $actor, $id, $data) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            $row = DB::table('workspace_holiday_requests')
                ->where('id', $id)->where('company_id', $company->id)
                ->where('workspace_id', $workspace->id)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($row->status !== 'pending' || $row->work_date <= now($company->timezone)->toDateString()) {
                throw ValidationException::withMessages(['decision' => 'This request is not pending for a future date.']);
            }

            if ($data['decision'] === 'approve') {
                $month = substr($row->work_date, 0, 7).'-01';
                if (DB::table('payroll_runs')->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)->where('period_month', $month)
                    ->whereIn('status', ['reviewed', 'approved', 'paid', 'locked'])->exists()) {
                    throw ValidationException::withMessages(['decision' => 'Payroll period is frozen.']);
                }
                $last = DB::table('workspace_day_overrides')->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)->where('work_date', $row->work_date)
                    ->orderByDesc('id')->first();
                if ($last && $last->day_type === $row->day_type) {
                    throw ValidationException::withMessages(['decision' => 'This day type is already in effect.']);
                }
                $created = DB::table('workspace_day_overrides')->insertGetId([
                    'company_id' => $company->id, 'workspace_id' => $workspace->id,
                    'work_date' => $row->work_date, 'day_type' => $row->day_type,
                    'reason' => $row->reason, 'authorized_by' => $actor->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                TenantOps::audit($actor, 'calendar.day_override', 'workspace_day_override', $created,
                    ['source_request_id' => $id, 'prior_decision_id' => $last?->id]);
            }
            DB::table('workspace_holiday_requests')->where('id', $id)->update([
                'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
                'decided_by' => $actor->id, 'decided_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'workspace.holiday.'.$data['decision'], 'workspace_holiday_request', $id);
            TenantOps::notify($actor, $row->proposed_by, 'Holiday request decision',
                'Your requested holiday adjustment was '.$data['decision'].'d.');
        }, 3);

        return back()->with('success', 'Holiday request decision saved.');
    }

    private function notifyPartners(object $actor, string $title, string $message): void
    {
        $partners = DB::table('memberships')
            ->where('company_id', $actor->company_id)->where('workspace_id', $actor->workspace_id)
            ->where('role', 'partner')->where('active', true)->pluck('id');
        foreach ($partners as $id) {
            TenantOps::notify($actor, $id, $title, $message);
        }
    }
}
