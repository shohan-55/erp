<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Append-only annual snapshots. A newer approved year plan replaces the prior
 * year's snapshot for FUTURE days, but cannot rewrite dates before approval.
 * Explicit approved one-day exceptions and student shifts take precedence.
 */
final class AnnualHolidayCalendar
{
    public static function forDate(object $company, object $workspace, string $date): ?array
    {
        $endOfLocalDayUtc = CarbonImmutable::parse($date.' 23:59:59', $company->timezone)
            ->utc()->toDateTimeString();
        $plan = DB::table('annual_holiday_plans')
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)
            ->where('calendar_year', (int) substr($date, 0, 4))
            ->where('status', 'approved')
            ->where('reviewed_at', '<=', $endOfLocalDayUtc)
            ->orderByDesc('reviewed_at')->orderByDesc('id')->first();
        if (! $plan) return null;

        $dates = json_decode($plan->holiday_dates, true, 512, JSON_THROW_ON_ERROR);
        if (! array_key_exists($date, $dates)) return null;
        $holiday = $dates[$date];

        return [
            'title' => $holiday['title'],
            'category' => $holiday['category'],
            'plan_id' => $plan->id,
        ];
    }
}
