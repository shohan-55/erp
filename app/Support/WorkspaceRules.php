<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class WorkspaceRules
{
    public static function effective(object $company, object $workspace, string $date): array
    {
        $version = DB::table('workspace_operational_policy_requests')
            ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
            ->where('status', 'approved')->where('effective_date', '<=', $date)
            ->orderByDesc('effective_date')->orderByDesc('id')->first();

        return [
            'version_id' => $version?->id,
            'effective_date' => $version?->effective_date,
            'leave_max_days' => (int) ($version?->leave_max_days ?? 61),
            'annual_leave_notice_days' => (int) ($version?->annual_leave_notice_days ?? 0),
            'conveyance_max_per_claim' => (float) ($version?->conveyance_max_per_claim ?? 50000),
        ];
    }
}
