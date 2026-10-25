<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\ErpFeatures;
use App\Support\FeatureGovernance;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CompanyFeatureGovernanceController extends Controller
{
    private function context(Request $request): array
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        return [$company, $workspace, $actor];
    }

    public function propose(Request $request, string $firmSlug)
    {
        [$company, , $actor] = $this->context($request);
        $data = $request->validate([
            'feature_key' => ['required', Rule::in(array_keys(ErpFeatures::MODULES))],
            'operation' => ['required', Rule::in(['enable', 'disable', 'reset'])],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:15', 'max:1000'],
        ]);
        $minimumDate = FeatureGovernance::nextEffectiveMonth($company->timezone);
        if ($data['effective_date'] < $minimumDate || substr($data['effective_date'], 8) !== '01') {
            throw ValidationException::withMessages([
                'effective_date' => 'Choose the first day of a future month.',
            ]);
        }

        DB::transaction(function () use ($company, $actor, $data) {
            $firm = DB::table('companies')->where('id', $company->id)->lockForUpdate()->first();
            abort_unless($firm && $firm->active && $firm->archived_at === null
                && $firm->deleted_at === null, 404);
            if ($data['effective_date'] < FeatureGovernance::nextEffectiveMonth($firm->timezone)) {
                throw ValidationException::withMessages([
                    'effective_date' => 'A future month is required for a company policy.',
                ]);
            }

            $voters = FeatureGovernance::activePartnerIds($company->id);
            if (!FeatureGovernance::eligible($firm, $voters)
                || !in_array((int) $actor->id, $voters, true)) {
                throw ValidationException::withMessages([
                    'effective_date' => 'The minimum approved active Partner roster has not been met.',
                ]);
            }
            if (DB::table('company_feature_policy_proposals')
                ->where('company_id', $company->id)
                ->where('feature_key', $data['feature_key'])
                ->where('effective_date', $data['effective_date'])
                ->whereIn('status', ['pending', 'approved'])->exists()) {
                throw ValidationException::withMessages([
                    'feature_key' => 'A pending or approved version exists for this module and effective month.',
                ]);
            }

            $id = DB::table('company_feature_policy_proposals')->insertGetId([
                'company_id' => $company->id,
                'proposed_by' => $actor->id,
                'feature_key' => $data['feature_key'],
                'operation' => $data['operation'],
                'effective_date' => $data['effective_date'],
                'eligible_partner_ids' => json_encode($voters, JSON_THROW_ON_ERROR),
                'reason' => trim($data['reason']),
                'status' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'feature_policy.proposed',
                'company_feature_policy_proposal', $id, [
                    'module' => $data['feature_key'],
                    'operation' => $data['operation'],
                    'effective_date' => $data['effective_date'],
                    'eligible_partner_ids' => $voters,
                    'reason' => trim($data['reason']),
                ]);
            $this->notifyPartners($company->id, $voters,
                'Company module change requires your vote',
                'Company-wide '.$data['feature_key'].' policy #'.$id.' needs an individual decision.');
        }, 3);

        return back()->with('success',
            'Company module policy proposed. No access changes until every Partner approves and the future date arrives.');
    }

    public function vote(Request $request, string $firmSlug, int $proposalId)
    {
        [$company, , $actor] = $this->context($request);
        $input = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
        ]);

        $status = DB::transaction(function () use ($company, $actor, $proposalId, $input) {
            $firm = DB::table('companies')->where('id', $company->id)->lockForUpdate()->first();
            abort_unless($firm && $firm->active && $firm->archived_at === null
                && $firm->deleted_at === null, 404);
            $proposal = DB::table('company_feature_policy_proposals')
                ->where('id', $proposalId)
                ->where('company_id', $company->id)->lockForUpdate()->first();
            abort_unless($proposal, 404);

            if ($proposal->status !== 'pending') {
                throw ValidationException::withMessages([
                    'decision' => 'This version is no longer open for voting.',
                ]);
            }

            $frozen = json_decode($proposal->eligible_partner_ids, true, 512, JSON_THROW_ON_ERROR);
            $active = FeatureGovernance::activePartnerIds($company->id);
            if ($frozen !== $active
                || !FeatureGovernance::eligible($firm, $active)
                || $proposal->effective_date < FeatureGovernance::nextEffectiveMonth($firm->timezone)) {
                // Committed suspension (not thrown/rolled back): the old
                // electorate can never approve a changed firm's future rules.
                DB::table('company_feature_policy_proposals')->where('id', $proposalId)->update([
                    'status' => 'suspended', 'decided_at' => now(), 'updated_at' => now(),
                ]);
                TenantOps::audit($actor, 'feature_policy.suspended',
                    'company_feature_policy_proposal', $proposalId, [
                        'reason' => 'eligible_partner_roster_or_effective_date_changed',
                    ]);
                return 'suspended';
            }
            abort_unless(in_array((int) $actor->id, $frozen, true), 403);
            if (DB::table('company_feature_policy_votes')->where('proposal_id', $proposalId)
                ->where('partner_membership_id', $actor->id)->exists()) {
                throw ValidationException::withMessages([
                    'decision' => 'You have already voted on this policy version.',
                ]);
            }

            DB::table('company_feature_policy_votes')->insert([
                'proposal_id' => $proposalId,
                'partner_membership_id' => $actor->id,
                'decision' => $input['decision'],
                'created_at' => now(),
            ]);
            TenantOps::audit($actor, 'feature_policy.vote_'.$input['decision'],
                'company_feature_policy_proposal', $proposalId);

            if ($input['decision'] === 'reject') {
                DB::table('company_feature_policy_proposals')->where('id', $proposalId)->update([
                    'status' => 'rejected', 'decided_at' => now(), 'updated_at' => now(),
                ]);
                return 'rejected';
            }

            $approved = DB::table('company_feature_policy_votes')
                ->where('proposal_id', $proposalId)
                ->where('decision', 'approve')->count();
            if ($approved === count($frozen)) {
                DB::table('company_feature_policy_proposals')->where('id', $proposalId)->update([
                    'status' => 'approved', 'decided_at' => now(), 'updated_at' => now(),
                ]);
                $this->notifyPartners($company->id, $frozen,
                    'Company module policy approved unanimously',
                    'Module '.$proposal->feature_key.' version #'.$proposalId
                    .' becomes effective on '.$proposal->effective_date.'.');
                return 'approved';
            }
            return 'pending';
        }, 3);

        return back()->with('success', 'Your individual vote was saved. Proposal status: '.$status.'.');
    }

    private function notifyPartners(int $companyId, array $ids, string $title, string $message): void
    {
        $members = DB::table('memberships')
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->where('role', 'partner')->where('active', true)
            ->whereNull('platform_suspended_at')->get(['id', 'workspace_id']);

        foreach ($members as $member) {
            DB::table('tenant_notifications')->insert([
                'company_id' => $companyId,
                'workspace_id' => $member->workspace_id,
                'recipient_membership_id' => $member->id,
                'title' => $title, 'message' => $message,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
