<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Separate company-level feature governance.
 * Ratified proposal versions do not mutate a current override or historical
 * operation row; the runtime resolver applies them from the effective date.
 */
final class FeatureGovernance
{
    public static function activePartnerIds(int $companyId): array
    {
        return DB::table('memberships as m')
            ->join('workspaces as w', function ($join) {
                $join->on('w.id', '=', 'm.workspace_id')
                    ->on('w.company_id', '=', 'm.company_id');
            })
            ->where('m.company_id', $companyId)
            ->where('m.role', 'partner')
            ->where('m.active', true)->whereNull('m.platform_suspended_at')
            ->where('w.active', true)
            ->orderBy('m.id')->pluck('m.id')->map(fn ($id) => (int) $id)->all();
    }

    public static function nextEffectiveMonth(string $timezone): string
    {
        return CarbonImmutable::now($timezone)->startOfMonth()->addMonth()->toDateString();
    }

    public static function eligible(object $company, array $partnerIds): bool
    {
        return count($partnerIds) >= max(1, (int) ($company->min_active_partners_for_company_policy ?? 1));
    }

    /**
     * Latest approved version for each feature, on the tenant-local date.
     * A reset is a versioned, approved decision restoring the inherited
     * company baseline, not a deletion of earlier versions.
     */
    public static function companyDecisions(int $companyId, string $timezone): array
    {
        $today = CarbonImmutable::now($timezone)->toDateString();
        $rows = DB::table('company_feature_policy_proposals')
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->where('effective_date', '<=', $today)
            ->whereIn('feature_key', array_keys(ErpFeatures::MODULES))
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->get(['id', 'feature_key', 'operation', 'effective_date']);

        $latest = [];
        foreach ($rows as $row) {
            if (!isset($latest[$row->feature_key])) {
                $latest[$row->feature_key] = $row;
            }
        }
        return $latest;
    }
}
