<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class PolicyResolver
{
    /** Immutable effective policy lookup for a Home Partner on a local calendar date. */
    public static function forDate(object $company, object $workspace, string $localDate): array
    {
        $policy = [
            'company_id' => $company->id,
            'workspace_id' => $workspace->id,
            'office_start' => $company->office_start,
            'office_end' => $company->office_end,
            'grace_minutes' => (int) $company->grace_minutes,
            'weekly_holidays' => json_decode($company->weekly_holidays ?? '[5,6]', true),
            'half_day_warning_time' => $workspace->half_day_warning_time,
            'late_review_days' => (int) $company->late_review_days,
            'grace_late_exempt' => (bool) $company->grace_late_exempt,
            'hr_policy_revision_id' => null,
            'company_policy_proposal_id' => null,
            'workspace_override_id' => null,
            'effective_on' => $localDate,
        ];

        $companyVersion = DB::table('company_policy_proposals')
            ->where('company_id', $company->id)
            ->where('status', 'approved')
            ->where('effective_date', '<=', $localDate)
            ->orderByDesc('effective_date')->orderByDesc('id')->first();

        if ($companyVersion) {
            $policy['office_start'] = self::normalizeTime($companyVersion->office_start);
            $policy['office_end'] = self::normalizeTime($companyVersion->office_end);
            $policy['grace_minutes'] = (int) $companyVersion->grace_minutes;
            $policy['weekly_holidays'] = json_decode($companyVersion->weekly_holidays, true);
            $policy['company_policy_proposal_id'] = $companyVersion->id;
        }

        $workspaceVersion = DB::table('workspace_policy_overrides')
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)
            ->where('effective_date', '<=', $localDate)
            ->orderByDesc('effective_date')->orderByDesc('id')->first();

        if ($workspaceVersion) {
            $policy['office_start'] = self::normalizeTime($workspaceVersion->office_start);
            $policy['office_end'] = self::normalizeTime($workspaceVersion->office_end);
            $policy['grace_minutes'] = (int) $workspaceVersion->grace_minutes;
            $policy['weekly_holidays'] = json_decode($workspaceVersion->weekly_holidays, true);
            $policy['workspace_override_id'] = $workspaceVersion->id;
        }

        // Dated, approved snapshots are immutable. A later legacy workspace
        // schedule override may still take precedence for office hours, while
        // modern HR-only settings follow their latest approved snapshot.
        $hr = DB::table('workspace_hr_policy_revisions')
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)
            ->where('status', 'approved')
            ->where('effective_date', '<=', $localDate)
            ->orderByDesc('effective_date')->orderByDesc('id')->first();

        if ($hr) {
            $snapshot = json_decode($hr->rules_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $scheduleKeys = ['office_start', 'office_end', 'grace_minutes', 'weekly_holidays'];
            foreach ($snapshot as $key => $value) {
                if (in_array($key, $scheduleKeys, true)
                    && $workspaceVersion && $workspaceVersion->effective_date > $hr->effective_date) {
                    continue;
                }
                if (in_array($key, [...$scheduleKeys, 'half_day_warning_time',
                    'late_review_days', 'grace_late_exempt'], true)) {
                    $policy[$key] = $value;
                }
            }
            $policy['hr_policy_revision_id'] = $hr->id;
        }

        return $policy;
    }

    private static function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}