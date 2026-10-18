<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\PolicyResolver;
use App\Support\WorkCalendar;
use App\Support\TenantOps;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PolicyController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        $effectivePolicy = PolicyResolver::forDate($company, $workspace, now($company->timezone)->toDateString());

        $proposals = DB::table('company_policy_proposals as p')
            ->join('memberships as author', 'author.id', '=', 'p.proposed_by')
            ->join('users as u', 'u.id', '=', 'author.user_id')
            ->where('p.company_id', $company->id)
            ->select('p.*', 'u.name as author_name')
            ->orderByDesc('p.created_at')->limit(30)->get();

        $votes = DB::table('company_policy_votes as v')
            ->join('company_policy_proposals as p', 'p.id', '=', 'v.proposal_id')
            ->where('p.company_id', $company->id)
            ->select('v.proposal_id', 'v.partner_membership_id', 'v.decision')->get()->groupBy('proposal_id');

        $overrides = DB::table('workspace_policy_overrides')
            ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
            ->orderByDesc('effective_date')->limit(20)->get();

        $dayOverrides = DB::table('workspace_day_overrides')
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('work_date')->orderByDesc('id')->limit(30)->get();

        // The upcoming schedule is computed server-side from approved decisions
        // only. Pending Manager proposals never change these published hours.
        $calendarPreview = collect(range(1, 14))->map(function (int $offset) use ($company, $workspace) {
            $date = CarbonImmutable::now($company->timezone)->startOfDay()
                ->addDays($offset)->toDateString();
            $base = PolicyResolver::forDate($company, $workspace, $date);
            $weekly = is_array($base['weekly_holidays']) ? $base['weekly_holidays'] : [5, 6];
            $day = WorkCalendar::forDate($company, $workspace, $date, $weekly, $base);
            return ['date' => $date, 'day' => CarbonImmutable::parse($date)->format('D'), ...$day];
        });

        return view('erp.policies', compact(
            'company', 'workspace', 'actor', 'firmSlug', 'effectivePolicy',
            'proposals', 'votes', 'overrides', 'dayOverrides', 'calendarPreview'
        ));
    }

    public function propose(Request $request, string $firmSlug)
    {
        [$company, , $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $data = $this->validatePolicy($request, true);
        $date = $this->requireFutureMonth($data['effective_date'], $company);

        DB::transaction(function () use ($company, $actor, $data, $date) {
            $firm = DB::table('companies')->where('id', $company->id)->lockForUpdate()->first();
            abort_unless($firm && $firm->active && $firm->archived_at === null, 404);
            $partners = $this->activePartnerIds($company->id);

            $minimum = (int) $firm->min_active_partners_for_company_policy;
            if (count($partners) < $minimum || count($partners) < 1) {
                throw ValidationException::withMessages(['effective_date' =>
                    'This firm requires at least '.$minimum.' active Partners before a firm-wide policy vote.']);
            }

            if (DB::table('company_policy_proposals')->where('company_id', $company->id)
                ->where('effective_date', $date)->whereIn('status', ['pending', 'approved'])->exists()) {
                throw ValidationException::withMessages(['effective_date' => 'A policy proposal already exists for this effective month.']);
            }

            $id = DB::table('company_policy_proposals')->insertGetId([
                'company_id' => $company->id,
                'proposed_by' => $actor->id,
                'effective_date' => $date,
                'office_start' => $data['office_start'],
                'office_end' => $data['office_end'],
                'grace_minutes' => $data['grace_minutes'],
                'weekly_holidays' => json_encode($data['weekly_holidays'], JSON_THROW_ON_ERROR),
                'eligible_partner_ids' => json_encode($partners, JSON_THROW_ON_ERROR),
                'justification' => $data['justification'],
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);

            TenantOps::audit($actor, 'policy.propose_company', 'company_policy_proposal', $id, [
                'effective_date' => $date, 'partner_count' => count($partners),
            ]);
            $this->notifyPartners($company->id, 'Company-wide policy proposal', 'A new company policy proposal needs your individual vote.');
        }, 3);

        return back()->with('success', 'Policy proposal created; all eligible Partners must independently approve.');
    }

    public function vote(Request $request, string $firmSlug, int $id)
    {
        [$company, , $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject']]);

        $result = DB::transaction(function () use ($company, $actor, $id, $data) {
            DB::table('companies')->where('id', $company->id)->lockForUpdate()->first();
            $proposal = DB::table('company_policy_proposals')
                ->where('id', $id)->where('company_id', $company->id)->lockForUpdate()->first();
            abort_unless($proposal, 404);

            if ($proposal->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'The proposal is no longer open for voting.']);
            }

            $eligible = json_decode($proposal->eligible_partner_ids, true);
            $current = $this->activePartnerIds($company->id);
            $future = $this->nextMonth($company)->toDateString();

            if ($eligible !== $current || $proposal->effective_date < $future) {
                DB::table('company_policy_proposals')->where('id', $id)->update([
                    'status' => 'suspended', 'decided_at' => now(), 'updated_at' => now(),
                ]);
                TenantOps::audit($actor, 'policy.suspend_roster_or_date', 'company_policy_proposal', $id);
                return 'suspended';
            }

            if (! in_array($actor->id, $eligible, true)) {
                abort(403, 'You are not an eligible voter for this proposal.');
            }
            if (DB::table('company_policy_votes')->where('proposal_id', $id)
                ->where('partner_membership_id', $actor->id)->exists()) {
                throw ValidationException::withMessages(['decision' => 'You have already voted on this proposal.']);
            }

            DB::table('company_policy_votes')->insert([
                'proposal_id' => $id, 'partner_membership_id' => $actor->id,
                'decision' => $data['decision'], 'created_at' => now(),
            ]);
            TenantOps::audit($actor, 'policy.vote_'.$data['decision'], 'company_policy_proposal', $id);

            if ($data['decision'] === 'reject') {
                DB::table('company_policy_proposals')->where('id', $id)->update([
                    'status' => 'rejected', 'decided_at' => now(), 'updated_at' => now(),
                ]);
                return 'rejected';
            }

            $approvals = DB::table('company_policy_votes')
                ->where('proposal_id', $id)->where('decision', 'approve')->count();

            if ($approvals === count($eligible)) {
                DB::table('company_policy_proposals')->where('id', $id)->update([
                    'status' => 'approved', 'decided_at' => now(), 'updated_at' => now(),
                ]);
                $this->notifyPartners($company->id, 'Policy approved unanimously', 'The approved company default takes effect on its scheduled date.');
                return 'approved';
            }

            return 'pending';
        }, 3);

        return back()->with('success', 'Your individual vote was recorded. Proposal status: '.$result.'.');
    }

    public function override(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $data = $this->validatePolicy($request, false);
        $date = $this->requireFutureMonth($data['effective_date'], $company);

        DB::transaction(function () use ($actor, $workspace, $data, $date) {
            DB::table('workspaces')->where('id', $workspace->id)->lockForUpdate()->first();
            if (DB::table('workspace_policy_overrides')->where('workspace_id', $workspace->id)
                ->where('effective_date', $date)->exists()) {
                throw ValidationException::withMessages(['effective_date' => 'A workspace policy version already exists for this month.']);
            }

            $id = DB::table('workspace_policy_overrides')->insertGetId([
                'company_id' => $actor->company_id,
                'workspace_id' => $workspace->id,
                'authorized_by' => $actor->id,
                'effective_date' => $date,
                'office_start' => $data['office_start'],
                'office_end' => $data['office_end'],
                'grace_minutes' => $data['grace_minutes'],
                'weekly_holidays' => json_encode($data['weekly_holidays'], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'policy.override_workspace', 'workspace_policy_override', $id, [
                'effective_date' => $date,
            ]);
        }, 3);

        return back()->with('success', 'Partner policy override scheduled. Other Partners are unaffected.');
    }

    /**
     * Authorize a prospective special working day or holiday for exactly one
     * Partner workspace. Revisions are append-only; no old decision disappears.
     * This never calculates wage deductions or rewrites finalized payroll.
     */
    /**
     * A Partner may authorize a future calendar decision immediately.
     * A Manager may only PROPOSE one for Partner approval. Past dates,
     * frozen payroll, cross-workspace changes and implicit approvals fail closed.
     */
    public function dayOverride(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner', 'manager']);

        $input = $request->validate([
            'work_date' => ['required', 'date_format:Y-m-d'],
            'day_type' => ['required', 'in:working,holiday'],
            'holiday_category' => ['nullable', 'in:partner_decision,government,optional,client_duty,emergency'],
            'office_start' => ['nullable', 'required_with:office_end', 'date_format:H:i'],
            'office_end' => ['nullable', 'required_with:office_start', 'date_format:H:i', 'after:office_start'],
            'grace_minutes' => ['nullable', 'integer', 'between:0,60'],
            'reason' => ['required', 'string', 'min:15', 'max:1000'],
        ]);

        if ($input['day_type'] === 'holiday' && (
            ! empty($input['office_start']) || ! empty($input['office_end']) || isset($input['grace_minutes'])
        )) {
            throw ValidationException::withMessages(['office_start' => 'Holiday decisions cannot include working hours.']);
        }
        if (isset($input['grace_minutes']) && (empty($input['office_start']) || empty($input['office_end']))) {
            throw ValidationException::withMessages(['grace_minutes' => 'Provide both start and end times with a special grace period.']);
        }

        $this->validateProspectiveWorkDate($company, $input['work_date']);

        DB::transaction(function () use ($company, $workspace, $actor, $input) {
            DB::table('workspaces')->where('id', $workspace->id)
                ->where('company_id', $company->id)->lockForUpdate()->first();

            $this->assertCalendarDateEditable($company, $workspace, $input['work_date']);

            if (DB::table('workspace_day_overrides')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('work_date', $input['work_date'])->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['work_date' => 'An outstanding day decision needs Partner review first.']);
            }

            $last = DB::table('workspace_day_overrides')
                ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
                ->where('work_date', $input['work_date'])
                ->where('status', 'approved')->latest('id')->first();

            $start = isset($input['office_start']) ? $input['office_start'].':00' : null;
            $end = isset($input['office_end']) ? $input['office_end'].':00' : null;
            $grace = $input['grace_minutes'] ?? null;
            $category = $input['holiday_category'] ?? 'partner_decision';

            if ($last && $last->day_type === $input['day_type']
                && substr((string) $last->office_start, 0, 5) === ($start ? substr($start, 0, 5) : '')
                && substr((string) $last->office_end, 0, 5) === ($end ? substr($end, 0, 5) : '')
                && ($last->grace_minutes === null ? null : (int) $last->grace_minutes)
                    === ($grace === null ? null : (int) $grace)
                && $last->holiday_category === $category) {
                throw ValidationException::withMessages(['day_type' => 'This calendar decision is already approved for that date.']);
            }

            $approved = $actor->role === 'partner';
            $id = DB::table('workspace_day_overrides')->insertGetId([
                'company_id' => $company->id,
                'workspace_id' => $workspace->id,
                'work_date' => $input['work_date'],
                'day_type' => $input['day_type'],
                'holiday_category' => $category,
                'office_start' => $start,
                'office_end' => $end,
                'grace_minutes' => $grace,
                'reason' => trim($input['reason']),
                'status' => $approved ? 'approved' : 'pending',
                'proposed_by' => $actor->id,
                'authorized_by' => $approved ? $actor->id : null,
                'reviewed_by' => $approved ? $actor->id : null,
                'reviewed_at' => $approved ? now() : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            TenantOps::audit($actor, $approved ? 'calendar.day_override' : 'calendar.day_proposed',
                'workspace_day_override', $id, [
                    'work_date' => $input['work_date'],
                    'prior_decision_id' => $last?->id,
                    'day_type' => $input['day_type'], 'holiday_category' => $category,
                    'office_start' => $start, 'office_end' => $end,
                    'grace_minutes' => $grace,
                ]);

            if (! $approved) {
                $partnerIds = DB::table('memberships')
                    ->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)
                    ->where('role', 'partner')->where('active', true)->pluck('id');
                foreach ($partnerIds as $partnerId) {
                    TenantOps::notify($actor, (int) $partnerId, 'Calendar approval requested',
                        'A Manager has proposed a special day change for '.$input['work_date'].'.');
                }
            }
        }, 3);

        return back()->with('success', $actor->role === 'partner'
            ? 'Your Partner calendar decision is approved and scheduled.'
            : 'Calendar proposal sent to your Partner for review. It does not change attendance until approved.');
    }

    /** A Manager request can only become effective through a Partner decision. */
    public function reviewDayOverride(Request $request, string $firmSlug, int $id)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $input = $request->validate(['decision' => ['required', 'in:approve,reject']]);

        DB::transaction(function () use ($company, $workspace, $actor, $id, $input) {
            DB::table('workspaces')->where('company_id', $company->id)
                ->where('id', $workspace->id)->lockForUpdate()->first();

            $proposal = DB::table('workspace_day_overrides')
                ->where('id', $id)->where('company_id', $company->id)
                ->where('workspace_id', $workspace->id)->lockForUpdate()->first();
            abort_unless($proposal, 404);
            if ($proposal->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This calendar request has already been reviewed.']);
            }

            $this->assertCalendarDateEditable($company, $workspace, $proposal->work_date);

            $status = $input['decision'] === 'approve' ? 'approved' : 'rejected';
            DB::table('workspace_day_overrides')->where('id', $proposal->id)->update([
                'status' => $status,
                'authorized_by' => $status === 'approved' ? $actor->id : null,
                'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'calendar.day_'.$status, 'workspace_day_override', $id, [
                'work_date' => $proposal->work_date,
                'proposed_by' => $proposal->proposed_by,
            ]);

            if ($proposal->proposed_by && $proposal->proposed_by !== $actor->id) {
                TenantOps::notify($actor, (int) $proposal->proposed_by,
                    'Calendar proposal '.$status,
                    'Your proposed calendar decision for '.$proposal->work_date.' was '.$status.'.');
            }
        }, 3);

        return back()->with('success', 'Calendar proposal decision saved.');
    }

    private function validateProspectiveWorkDate(object $company, string $date): void
    {
        $dt = CarbonImmutable::createFromFormat('!Y-m-d', $date, $company->timezone);
        if (! $dt || $dt->toDateString() !== $date
            || $date <= CarbonImmutable::now($company->timezone)->toDateString()) {
            throw ValidationException::withMessages(['work_date' => 'Only future calendar dates can be changed.']);
        }
    }

    private function assertCalendarDateEditable(object $company, object $workspace, string $date): void
    {
        $this->validateProspectiveWorkDate($company, $date);
        $month = substr($date, 0, 7).'-01';
        if (DB::table('payroll_runs')->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)->where('period_month', $month)
            ->whereIn('status', ['reviewed', 'approved', 'paid', 'locked'])->exists()) {
            throw ValidationException::withMessages(['work_date' => 'Payroll is frozen for this period.']);
        }
    }

    private function validatePolicy(Request $request, bool $companyWide): array
    {
        $rules = [
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'office_start' => ['required', 'date_format:H:i'],
            'office_end' => ['required', 'date_format:H:i', 'after:office_start'],
            'grace_minutes' => ['required', 'integer', 'between:0,30'],
            'weekly_holidays' => ['required', 'array', 'min:1', 'max:3'],
            'weekly_holidays.*' => ['integer', 'between:1,7', 'distinct'],
        ];
        if ($companyWide) {
            $rules['justification'] = ['required', 'string', 'min:15', 'max:2000'];
        }

        return $request->validate($rules);
    }

    private function nextMonth(object $company): CarbonImmutable
    {
        return CarbonImmutable::now($company->timezone)->startOfMonth()->addMonth();
    }

    private function requireFutureMonth(string $date, object $company): string
    {
        $dt = CarbonImmutable::createFromFormat('!Y-m-d', $date, $company->timezone);
        if (! $dt || $dt->toDateString() !== $date || $dt->day !== 1
            || $dt->lessThan($this->nextMonth($company))) {
            throw ValidationException::withMessages(['effective_date' => 'Policy changes must start on the first day of a future month.']);
        }
        return $date;
    }

    private function activePartnerIds(int $companyId): array
    {
        $ids = DB::table('memberships')->where('company_id', $companyId)
            ->where('role', 'partner')->where('active', true)->orderBy('id')->pluck('id')->all();
        return array_map('intval', $ids);
    }

    private function notifyPartners(int $companyId, string $title, string $message): void
    {
        $partners = DB::table('memberships')
            ->where('company_id', $companyId)->where('role', 'partner')->where('active', true)->get();

        foreach ($partners as $partner) {
            DB::table('tenant_notifications')->insert([
                'company_id' => $companyId, 'workspace_id' => $partner->workspace_id,
                'recipient_membership_id' => $partner->id,
                'title' => $title, 'message' => $message,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}