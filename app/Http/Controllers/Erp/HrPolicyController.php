<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\PolicyResolver;
use App\Support\TenantOps;
use App\Support\WorkspaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Flexible, tenant-scoped policy governance. Manager delegation applies only
 * to schedule fields, never leave/payroll-sensitive policy changes.
 * No automatic deduction or leave decisions are performed here.
 */
class HrPolicyController extends Controller
{
    private const SENSITIVE = [
        'weekly_holidays', 'half_day_warning_time', 'late_review_days', 'grace_late_exempt',
    ];

    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner', 'manager']);

        $today = CarbonImmutable::now($company->timezone)->toDateString();
        $effective = PolicyResolver::forDate($company, $workspace, $today);
        $permission = DB::table('workspace_hr_policy_permissions')
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)->first();
        $managerMode = $permission?->manager_mode ?? 'approval_required';
        $revisions = DB::table('workspace_hr_policy_revisions as r')
            ->join('memberships as m', 'm.id', '=', 'r.requested_by')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('r.company_id', $company->id)
            ->where('r.workspace_id', $workspace->id)
            ->where('m.company_id', $company->id)
            ->where('m.workspace_id', $workspace->id)
            ->select('r.*', 'u.name as author_name')
            ->orderByDesc('r.id')->limit(40)->get();

        return view('erp.hr-policies', compact(
            'company', 'workspace', 'actor', 'firmSlug', 'effective', 'managerMode', 'revisions'
        ));
    }

    public function delegate(Request $request, string $firmSlug)
    {
        [, $workspace, $actor] = TenantOps::context($request);
        abort_unless(WorkspaceAccess::partnerAction($actor,'hr.manage'),403);
        $data = $request->validate(['manager_mode' => ['required', 'in:approval_required,direct']]);

        DB::transaction(function () use ($workspace, $actor, $data) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $actor->company_id)->lockForUpdate()->first();
            DB::table('workspace_hr_policy_permissions')->updateOrInsert([
                'company_id' => $actor->company_id, 'workspace_id' => $workspace->id,
            ], [
                'manager_mode' => $data['manager_mode'],
                'updated_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'hr_policy.manager_permission', 'workspace', $workspace->id, [
                'manager_mode' => $data['manager_mode'],
            ]);
        }, 3);

        return back()->with('success', 'Manager HR policy permission updated. Sensitive rules still require Partner approval.');
    }

    public function propose(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner', 'manager']);
        $rules = $request->validate([
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'office_start' => ['required', 'date_format:H:i'],
            'office_end' => ['required', 'date_format:H:i', 'after:office_start'],
            'grace_minutes' => ['required', 'integer', 'between:0,180'],
            'weekly_holidays' => ['sometimes', 'array', 'max:7'],
            'weekly_holidays.*' => ['integer', 'between:1,7', 'distinct'],
            'half_day_warning_time' => ['nullable', 'date_format:H:i'],
            'late_review_days' => ['required', 'integer', 'between:1,31'],
            'grace_late_exempt' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $date = $rules['effective_date'];
        if ($date <= CarbonImmutable::now($company->timezone)->toDateString()) {
            throw ValidationException::withMessages(['effective_date' => 'Policy changes must begin on a future local date.']);
        }

        $start = CarbonImmutable::parse($date.' '.$rules['office_start'], $company->timezone);
        $end = CarbonImmutable::parse($date.' '.$rules['office_end'], $company->timezone);
        $graceEnd = $start->addMinutes((int) $rules['grace_minutes']);
        $half = $rules['half_day_warning_time'] ?? null;
        if ($half !== null && $half !== '') {
            $threshold = CarbonImmutable::parse($date.' '.$half, $company->timezone);
            if (! $threshold->greaterThan($graceEnd) || ! $threshold->lessThan($end)) {
                throw ValidationException::withMessages([
                    'half_day_warning_time' => 'Half-day warning must be after grace and before office end.',
                ]);
            }
        }

        $snapshot = [
            'office_start' => $rules['office_start'].':00',
            'office_end' => $rules['office_end'].':00',
            'grace_minutes' => (int) $rules['grace_minutes'],
            'weekly_holidays' => array_values(array_unique(array_map('intval', $rules['weekly_holidays'] ?? []))),
            'half_day_warning_time' => $half !== null && $half !== '' ? $half.':00' : null,
            'late_review_days' => (int) $rules['late_review_days'],
            'grace_late_exempt' => (bool) $rules['grace_late_exempt'],
        ];

        $status = DB::transaction(function () use ($company, $workspace, $actor, $date, $snapshot, $rules) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            if (DB::table('workspace_hr_policy_revisions')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('effective_date', $date)->where('status', 'approved')->exists()) {
                throw ValidationException::withMessages([
                    'effective_date' => 'An approved HR policy already begins on this date. Choose a later date.',
                ]);
            }
            if (DB::table('payroll_runs')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('period_month', substr($date, 0, 7).'-01')
                ->whereIn('status', ['reviewed', 'approved', 'paid', 'locked'])->exists()) {
                throw ValidationException::withMessages([
                    'effective_date' => 'Cannot change a payroll-reviewed period.',
                ]);
            }
            $base = PolicyResolver::forDate($company, $workspace, $date);
            $sensitive = false;
            foreach (self::SENSITIVE as $key) {
                $expected = $base[$key] ?? null;
                if ($key === 'weekly_holidays') {
                    $x = $snapshot[$key]; $y = $expected ?? [];
                    sort($x); sort($y);
                    if ($x !== $y) $sensitive = true;
                } elseif ($snapshot[$key] !== $expected) {
                    $sensitive = true;
                }
            }

            $permission = DB::table('workspace_hr_policy_permissions')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)->first();
            $mode = $permission?->manager_mode ?? 'approval_required';
            $status = WorkspaceAccess::partnerAction($actor,'hr.manage') || ($actor->role === 'manager'
                && $mode === 'direct' && ! $sensitive) ? 'approved' : 'pending';
            $id = DB::table('workspace_hr_policy_revisions')->insertGetId([
                'company_id' => $company->id,
                'workspace_id' => $workspace->id,
                'requested_by' => $actor->id,
                'decided_by' => $status === 'approved' ? $actor->id : null,
                'effective_date' => $date,
                'rules_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'status' => $status,
                'sensitive' => $sensitive,
                'manager_mode_snapshot' => $mode,
                'reason' => trim($rules['reason']),
                'decided_at' => $status === 'approved' ? now() : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'hr_policy.'.$status, 'workspace_hr_policy_revision', $id, [
                'effective_date' => $date,
                'sensitive' => $sensitive,
                'manager_mode' => $mode,
            ]);
            if ($status === 'pending') {
                $partners = DB::table('memberships')->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)->where('role', 'partner')
                    ->where('active', true)->pluck('id');
                foreach ($partners as $partnerId) {
                    TenantOps::notify($actor, $partnerId,
                        'HR policy approval requested', 'A dated HR policy proposal needs Partner review.');
                }
            }
            return $status;
        }, 3);

        return back()->with('success', $status === 'approved'
            ? 'Approved HR policy version saved for its future effective date.'
            : 'Policy proposal submitted for Partner review.');
    }

    public function decide(Request $request, string $firmSlug, int $revisionId)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        abort_unless(WorkspaceAccess::partnerAction($actor,'hr.manage'),403);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject']]);

        DB::transaction(function () use ($company, $workspace, $actor, $revisionId, $data) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            $revision = DB::table('workspace_hr_policy_revisions')
                ->where('id', $revisionId)->where('company_id', $company->id)
                ->where('workspace_id', $workspace->id)->lockForUpdate()->first();
            abort_unless($revision, 404);
            if ((int)$revision->requested_by===(int)$actor->id) {
                throw ValidationException::withMessages(['decision'=>'Your own pending HR proposal must be reviewed by another authorized person.']);
            }
            if ($revision->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This proposal is no longer pending.']);
            }
            $today = CarbonImmutable::now($company->timezone)->toDateString();
            if ($data['decision'] === 'approve') {
                if ($revision->effective_date <= $today) {
                    throw ValidationException::withMessages(['decision' => 'The effective date has already passed.']);
                }
                if (DB::table('workspace_hr_policy_revisions')
                    ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                    ->where('effective_date', $revision->effective_date)
                    ->where('status', 'approved')->exists()) {
                    throw ValidationException::withMessages(['decision' => 'Another version is already approved for this date.']);
                }
                if (DB::table('payroll_runs')->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)
                    ->where('period_month', substr($revision->effective_date, 0, 7).'-01')
                    ->whereIn('status', ['reviewed', 'approved', 'paid', 'locked'])->exists()) {
                    throw ValidationException::withMessages(['decision' => 'Payroll review has frozen this effective period.']);
                }
            }
            DB::table('workspace_hr_policy_revisions')->where('id', $revision->id)->update([
                'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'hr_policy.'.$data['decision'], 'workspace_hr_policy_revision', $revisionId);
            TenantOps::notify($actor, $revision->requested_by, 'HR policy proposal decided',
                'Your HR policy proposal was '.$data['decision'].'d.');
        }, 3);

        return back()->with('success', 'HR policy decision recorded. Existing payroll and history are untouched.');
    }
}
