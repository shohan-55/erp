<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Server-authoritative schedule for the display clock and attendance grading.
 * The optional half-day threshold is VISUAL ONLY (it never grants leave, marks
 * an absence, or deducts wages). Company and approved member policy govern it.
 */
final class AttendanceClock
{
    public static function schedule(object $company, object $workspace, object $actor, ?CarbonImmutable $instant = null): array
    {
        $now = ($instant ?? CarbonImmutable::now($company->timezone))->setTimezone($company->timezone);
        $date = $now->toDateString();
        $policy = PolicyResolver::forDate($company, $workspace, $date);
        $weekly = is_array($policy['weekly_holidays']) ? $policy['weekly_holidays'] : [5, 6];
        $calendar = $actor->role === 'student'
            ? WorkCalendar::forMemberDate($company, $workspace, $actor, $date)
            : WorkCalendar::forDate($company, $workspace, $date, $weekly, $policy);

        $start = CarbonImmutable::parse($date.' '.$calendar['office_start'], $company->timezone);
        $end = CarbonImmutable::parse($date.' '.$calendar['office_end'], $company->timezone);
        $graceEnd = $start->addMinutes((int) $calendar['grace_minutes']);
        $halfDay = null;
        if ($workspace->half_day_warning_time) {
            $candidate = CarbonImmutable::parse($date.' '.$workspace->half_day_warning_time, $company->timezone);
            // Invalidated by an approved special shift? Fail closed: no half-day
            // warning unless the configured time is strictly within this shift.
            if ($candidate->greaterThan($graceEnd) && $candidate->lessThan($end)) {
                $halfDay = $candidate;
            }
        }
        return [
            'server_ms' => $now->getTimestampMs(),
            'timezone' => $company->timezone,
            'date' => $date,
            'office_start' => $start->format('h:i A'),
            'office_end' => $end->format('h:i A'),
            'grace_minutes' => (int) $calendar['grace_minutes'],
            'start_ms' => $start->getTimestampMs(),
            'grace_end_ms' => $graceEnd->getTimestampMs(),
            'office_end_ms' => $end->getTimestampMs(),
            'half_day_ms' => $halfDay?->getTimestampMs(),
            'holiday' => (bool) $calendar['holiday'],
            'calendar_source' => $calendar['source'],
            'calendar_exception_id' => $calendar['exception_id'],
            'student_shift_id' => $calendar['student_shift_id'] ?? null,
        ];
    }

    public static function arrival(array $schedule, CarbonImmutable $instant, bool $firstSession): array
    {
        $when = $instant->getTimestampMs();
        $late = $firstSession && ! $schedule['holiday'] && $when > $schedule['start_ms'];
        return [
            'late' => $late,
            'late_within_grace' => $late && $when <= $schedule['grace_end_ms'],
            'after_grace' => $late && $when > $schedule['grace_end_ms'],
            'half_day_warning' => $late && $schedule['half_day_ms'] !== null && $when >= $schedule['half_day_ms'],
        ];
    }

    public static function qualifyingLateDays(object $company, object $workspace, object $actor, string $date): int
    {
        $begin = substr($date, 0, 7).'-01';
        return DB::table('attendance_sessions')
            ->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)
            ->where('membership_id', $actor->id)
            ->whereBetween('work_date', [$begin, $date])
            ->where('late', true)
            ->where('late_within_grace', false)
            ->distinct()->count('work_date');
    }
}
