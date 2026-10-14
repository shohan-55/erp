<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\PolicyResolver;
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
            ->join('users as u', 'u.id', '=', DB::raw('(SELECT user_id FROM memberships WHERE id = p.proposed_by)'))
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

        return view('erp.policies', compact('company', 'workspace', 'actor', 'firmSlug', 'effectivePolicy', 'proposals', 'votes', 'overrides'));
    }

    public function propose(Request $request, string $firmSlug)
    {
        [$company, , $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $data = $this->validatePolicy($request, true);
        $date = $this->requireFutureMonth($data['effective_date'], $company);

        DB::transaction(function () use ($company, $actor, $data, $date) {
            DB::table('companies')->where('id', $company->id)->lockForUpdate()->first();
            $partners = $this->activePartnerIds($company->id);

            if (($company->slug === 'rac' && count($partners) !== 3) || count($partners) < 1) {
                throw ValidationException::withMessages(['effective_date' => 'The RA&C firm must first have exactly three active Partners to change a company-wide policy.']);
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