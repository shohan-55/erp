<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Bounded, read-only current-month projection of actual server records.
 * Lack of a punch is REVIEW NEEDED, never automatically "absent".
 */
final class DashboardMonthCalendar
{
    public static function forActor(object $company, object $workspace, object $actor, bool $showPersonal, ?CarbonImmutable $month = null): array
    {
        $now = CarbonImmutable::now($company->timezone);
        $start = ($month ?? $now)->setTimezone($company->timezone)->startOfMonth();
        $end = $now->endOfMonth();
        $today = $now->toDateString();

        $personal = $showPersonal && in_array($actor->role,['student','manager'],true);
        $sessionRows = $personal ? DB::table('attendance_sessions')
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->where('membership_id',$actor->id)
            ->whereBetween('work_date',[$start->toDateString(),$end->toDateString()])
            ->orderBy('started_at')->get()->groupBy('work_date') : collect();

        $leaves = $personal && $actor->role === 'student'
            ? DB::table('leave_requests')
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('membership_id',$actor->id)->where('status','approved')
                ->where('date_from','<=',$end->toDateString())
                ->where('date_to','>=',$start->toDateString())
                ->get(['date_from','date_to','day_portion','leave_type'])
            : collect();

        $teamCounts = collect();
        if (in_array($actor->role,['partner','manager'],true)) {
            $teamCounts = DB::table('attendance_sessions as s')
                ->join('memberships as m','m.id','=','s.membership_id')
                ->where('s.company_id',$company->id)->where('s.workspace_id',$workspace->id)
                ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
                ->where('m.role','student')->where('m.active',true)
                ->when($actor->role==='manager',fn($q)=>$q->where('m.manager_membership_id',$actor->id))
                ->whereBetween('s.work_date',[$start->toDateString(),$end->toDateString()])
                ->select('s.work_date','s.membership_id')->distinct()->get()
                ->groupBy('work_date')->map(fn($rows)=>$rows->count());
        }

        $days = [];
        for ($i=1;$i<=$end->day;$i++) {
            $date = $start->day($i)->toDateString();
            $base = PolicyResolver::forDate($company,$workspace,$date);
            $rule = $personal && $actor->role === 'student'
                ? WorkCalendar::forMemberDate($company,$workspace,$actor,$date)
                : WorkCalendar::forDate($company,$workspace,$date,
                    is_array($base['weekly_holidays'])?$base['weekly_holidays']:[], $base);

            $sessions = $sessionRows->get($date,collect());
            $approved = $leaves->filter(fn($l)=>$l->date_from <= $date && $l->date_to >= $date);
            $fullLeave = $approved->contains(fn($l)=>$l->day_portion==='full')
                || ($approved->contains(fn($l)=>$l->day_portion==='morning')
                    && $approved->contains(fn($l)=>$l->day_portion==='afternoon'));
            $halfLeave = ! $fullLeave && $approved->isNotEmpty();

            $stage='planned'; $label='Planned workday';
            if ($rule['holiday']) {
                $stage='holiday'; $label=$rule['holiday_title'] ?? 'Scheduled holiday';
            } elseif ($fullLeave) {
                $stage='leave'; $label='Approved leave';
            } elseif ($halfLeave && $sessions->isEmpty()) {
                $stage='half'; $label='Half-day leave · verify other half';
            } elseif ($personal && $sessions->isNotEmpty()) {
                $first=$sessions->first();
                $open = $sessions->contains(fn($s)=>$s->ended_at===null);
                if ($open && $date < $today) {
                    $stage='review'; $label='Missing checkout · review';
                } elseif ($open) {
                    $stage='active'; $label='Checked in';
                } elseif ($halfLeave) {
                    $stage='half'; $label='Half-day leave + attendance';
                } elseif ($sessions->contains(fn($s)=>(bool)$s->late)) {
                    $graceOnly=$sessions->filter(fn($s)=>(bool)$s->late)
                        ->every(fn($s)=>(bool)$s->late_within_grace);
                    $stage=$graceOnly?'grace':'late';
                    $label=$graceOnly?'Late within grace':'Late after grace';
                } elseif ($sessions->contains(fn($s)=>$s->work_mode==='client')) {
                    $stage='client'; $label='Client site attendance';
                } else {
                    $stage='on_time'; $label='Attendance recorded';
                }
            } elseif ($personal && $date < $today) {
                $stage='review'; $label='No check-in · review needed';
            } elseif ($personal && $date === $today) {
                $stage='today'; $label='Awaiting check-in';
            } elseif (! $personal && $date < $today) {
                $stage='scheduled'; $label='Scheduled workday';
            }
            $details=[];
            if ($rule['holiday']) $details[]='Holiday: '.($rule['holiday_title'] ?? 'Weekly or approved');
            if ($approved->isNotEmpty()) $details[]='Approved leave: '.$approved->pluck('leave_type')->unique()->implode(', ');
            if ($personal && $sessions->isNotEmpty()) {
                $first=$sessions->first();
                $last=$sessions->last();
                $details[]='Check-in: '.CarbonImmutable::parse($first->started_at,'UTC')
                    ->setTimezone($company->timezone)->format('h:i A');
                $details[]='Check-out: '.($last->ended_at
                    ? CarbonImmutable::parse($last->ended_at,'UTC')->setTimezone($company->timezone)->format('h:i A')
                    : 'Not recorded');
            }
            $details[]='Shift: '.substr($rule['office_start'],0,5)
                .' – '.substr($rule['office_end'],0,5);
            $count=(int)($teamCounts->get($date) ?? 0);
            if (in_array($actor->role,['partner','manager'],true)) {
                $details[]='Assigned student check-ins: '.$count;
            }
            $days[]=[
                'date'=>$date, 'number'=>$i, 'stage'=>$stage, 'label'=>$label,
                'today'=>$date===$today, 'future'=>$date>$today, 'details'=>implode(' · ',$details),
                'team_count'=>$count,
            ];
        }

        return [
            'month'=>$start->format('F Y'),
            'month_key'=>$start->format('Y-m'),
            'previous_month'=>$start->subMonth()->format('Y-m'),
            'next_month'=>$start->addMonth()->format('Y-m'),
            'leading_blanks'=>$start->dayOfWeek,
            'days'=>$days,
            'is_personal'=>$personal,
            'timezone'=>$company->timezone,
        ];
    }
}
