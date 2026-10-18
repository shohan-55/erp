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

    /**
     * Student-specific approved shifts take precedence over Partner-level
     * day exceptions. Requests awaiting approval never affect attendance.
     * All predicates use the selected membership's full tenant/workspace scope.
     */
    public static function forMemberDate(object $company, object $workspace, object $member, string $day): array
    {
        if ((int) $member->company_id !== (int) $company->id
            || (int) $member->workspace_id !== (int) $workspace->id
            || $member->role !== 'student') {
            throw new \InvalidArgumentException('Student does not belong to the requested workspace.');
        }

        $base = PolicyResolver::forDate($company, $workspace, $day);
        $weekly = is_array($base['weekly_holidays']) ? $base['weekly_holidays'] : [5, 6];
        $calendar = self::forDate($company, $workspace, $day, $weekly, $base);

        $shift = DB::table('student_calendar_shifts')
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)
            ->where('student_membership_id', $member->id)
            ->where('work_date', $day)
            ->where('status', 'approved')
            ->latest('id')->first();

        if (! $shift) {
            return [...$calendar, 'student_shift_id' => null];
        }

        return [
            ...$calendar,
            'holiday' => $shift->day_type === 'holiday',
            'working' => $shift->day_type === 'working',
            'office_start' => $shift->office_start ? self::normalizeTime($shift->office_start) : $calendar['office_start'],
            'office_end' => $shift->office_end ? self::normalizeTime($shift->office_end) : $calendar['office_end'],
            'grace_minutes' => $shift->grace_minutes !== null ? (int) $shift->grace_minutes : $calendar['grace_minutes'],
            'source' => 'student_shift',
            'student_shift_id' => $shift->id,
        ];
    }

    private static function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
