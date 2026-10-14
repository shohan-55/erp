<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class WorkCalendar
{
    /**
     * Resolve the company/workspace's attendance day status without applying
     * any unapproved pay deduction policy. Latest authorized append-only event
     * for the date takes precedence over ordinary weekly holidays.
     */
    public static function forDate(object $company, object $workspace, string $day, array $weeklyHolidays): array
    {
        $regularHoliday = in_array(
            \Carbon\CarbonImmutable::parse($day, $company->timezone)->isoWeekday(),
            $weeklyHolidays,
            true
        );

        $exception = DB::table('workspace_day_overrides')
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)
            ->where('work_date', $day)
            ->latest('id')
            ->first();

        return [
            'holiday' => $exception ? $exception->day_type === 'holiday' : $regularHoliday,
            'exception_id' => $exception?->id,
            'source' => $exception ? 'workspace_override' : 'weekly_policy',
        ];
    }
}
