<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class AttendanceReconciliation
{
    /**
     * Read-only daily review. Missed attendance is a provisional REVIEW status,
     * never a payroll penalty or an automatic confirmed absence.
     * No browser GPS or payroll data is queried.
     */
    public static function forDate(object $company, object $workspace, object $actor, string $day): array
    {
        $members = DB::table('memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.company_id', $company->id)->where('m.workspace_id', $workspace->id)
            ->where('m.role', 'student')->where('m.active', true)
            ->when($actor->role === 'student', fn ($q) => $q->where('m.id', $actor->id))
            ->when($actor->role === 'manager', fn ($q) => $q->where('m.manager_membership_id', $actor->id))
            ->select('m.id','m.company_id','m.workspace_id','m.role','u.name')
            ->orderBy('u.name')->get();

        if ($members->isEmpty()) {
            return ['rows' => collect(), 'summary' => self::initialSummary()];
        }

        $ids = $members->pluck('id')->all();
        $sessions = DB::table('attendance_sessions')
            ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
            ->whereIn('membership_id', $ids)->where('work_date', $day)
            ->orderBy('started_at')->get()->groupBy('membership_id');

        $leaves = DB::table('leave_requests')
            ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
            ->whereIn('membership_id', $ids)
            ->where('status', 'approved')
            ->where('date_from', '<=', $day)->where('date_to', '>=', $day)
            ->pluck('membership_id')->flip();

        $approvedShifts = DB::table('student_calendar_shifts')
            ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
            ->whereIn('student_membership_id', $ids)
            ->where('work_date', $day)->where('status', 'approved')
            ->orderByDesc('id')->get()->unique('student_membership_id')
            ->keyBy('student_membership_id');

        $base = PolicyResolver::forDate($company,$workspace,$day);
        $weekly = is_array($base['weekly_holidays']) ? $base['weekly_holidays'] : [5,6];
        $workspaceDay = WorkCalendar::forDate($company,$workspace,$day,$weekly,$base);
        $today = CarbonImmutable::now($company->timezone)->toDateString();

        $summary = self::initialSummary();
        $rows = $members->map(function ($member) use (
            $day, $sessions, $leaves, $approvedShifts, $workspaceDay,
            $company, $today, &$summary
        ) {
            $dayRule = $workspaceDay;
            $shift = $approvedShifts->get($member->id);
            if ($shift) {
                $dayRule['holiday'] = $shift->day_type === 'holiday';
                $dayRule['office_start'] = $shift->office_start ?: $dayRule['office_start'];
                $dayRule['office_end'] = $shift->office_end ?: $dayRule['office_end'];
                $dayRule['grace_minutes'] = $shift->grace_minutes !== null
                    ? (int) $shift->grace_minutes : $dayRule['grace_minutes'];
            }

            $own = $sessions->get($member->id, collect());
            $first = $own->first();
            $last = $own->last();
            $approvedLeave = $leaves->has($member->id);
            $flag = 'present';
            $label = 'Present';

            if ($own->isNotEmpty()) {
                if ($own->contains(fn ($s) => $s->ended_at === null)) {
                    $flag = $day === $today ? 'checked_in' : 'missing_punch';
                    $label = $day === $today ? 'Checked In' : 'Missing Punch · Review';
                } elseif ($own->contains(fn ($s) => $s->work_mode === 'client')) {
                    $flag = 'client_duty';
                    $label = 'Client Duty';
                } else {
                    $scheduledEnd = CarbonImmutable::parse($day.' '.substr($dayRule['office_end'],0,8),$company->timezone);
                    $actualEnd = CarbonImmutable::parse($last->ended_at,'UTC')->setTimezone($company->timezone);
                    if (! $dayRule['holiday'] && $actualEnd->lessThan($scheduledEnd)) {
                        $flag = 'early_exit';
                        $label = 'Early Exit · Review';
                    } elseif ($own->contains(fn ($s) => (bool) $s->late)) {
                        $flag = 'late';
                        $label = 'Late';
                    }
                }
            } elseif ($dayRule['holiday']) {
                $flag = 'holiday';
                $label = 'Holiday';
            } elseif ($approvedLeave) {
                $flag = 'leave';
                $label = 'Approved Leave';
            } elseif ($day === $today) {
                $flag = 'not_checked_in';
                $label = 'Not Checked In Yet';
            } else {
                $flag = 'review';
                $label = 'No Check-in · Review';
            }
            $summary[$flag] = ($summary[$flag] ?? 0) + 1;

            return (object) [
                'membership_id' => $member->id,
                'student_name' => $member->name,
                'status_key' => $flag,
                'status_label' => $label,
                'scheduled_start' => substr($dayRule['office_start'],0,5),
                'scheduled_end' => substr($dayRule['office_end'],0,5),
                'holiday' => $dayRule['holiday'],
                'session_count' => $own->count(),
                'has_approved_leave' => $approvedLeave,
                'has_conflict' => $approvedLeave && $own->isNotEmpty(),
                'first_check_in' => $first?->started_at,
                'last_check_out' => $last?->ended_at,
            ];
        });

        $summary['total'] = $rows->count();
        return ['rows' => $rows, 'summary' => $summary];
    }

    private static function initialSummary(): array
    {
        return [
            'total' => 0, 'present' => 0, 'checked_in' => 0,
            'client_duty' => 0, 'late' => 0, 'early_exit' => 0,
            'missing_punch' => 0, 'holiday' => 0, 'leave' => 0,
            'not_checked_in' => 0, 'review' => 0,
        ];
    }
}
