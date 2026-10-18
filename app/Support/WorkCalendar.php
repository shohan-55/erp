<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class WorkCalendar
{
    /**
     * Fully resolved, effective-dated Partner calendar. Approved append-only
     * decisions override the weekly policy. Pending/rejected requests NEVER
     * affect attendance or locked payroll. No pay penalty is calculated.
     *
     * @param array<int,int> $weeklyHolidays ISO weekdays (Mon=1...Sun=7)
     */
    public static function forDate(object $company, object $workspace, string $day, array $weeklyHolidays, ?array $basePolicy = null): array
    {
        $base = $basePolicy ?? PolicyResolver::forDate($company, $workspace, $day);
        $weekly = in_array(
            CarbonImmutable::parse($day, $company->timezone)->isoWeekday(),
            $weeklyHolidays, true
        );

        $exception = DB::table('workspace_day_overrides')
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)
            ->where('work_date', $day)
            ->where('status', 'approved')
            ->orderByDesc('id')->first();

        $holiday = $exception ? $exception->day_type === 'holiday' : $weekly;
        $start = $exception?->office_start ?: $base['office_start'];
        $end = $exception?->office_end ?: $base['office_end'];
        $grace = $exception?->grace_minutes !== null
            ? (int) $exception->grace_minutes : (int) $base['grace_minutes'];

        return [
            'holiday' => $holiday,
            'working' => ! $holiday,
            'office_start' => self::normalizeTime($start),
            'office_end' => self::normalizeTime($end),
            'grace_minutes' => $grace,
            'holiday_category' => $exception?->holiday_category,
            'exception_id' => $exception?->id,
            'source' => $exception ? 'workspace_override' : 'weekly_policy',
        ];
    }

    private static function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
