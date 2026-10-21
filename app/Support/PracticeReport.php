<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class PracticeReport
{
    public static function month(object $actor, string $period): array
    {
        $first=$period.'-01';
        $last=CarbonImmutable::parse($first)->endOfMonth()->toDateString();
        $members=DB::table('memberships as m')
            ->join('users as u','u.id','=','m.user_id')
            ->where('m.company_id',$actor->company_id)->where('m.workspace_id',$actor->workspace_id)
            ->where('m.role','student')
            ->select('m.id','u.name','m.active')
            ->orderBy('u.name')->get();
        $ids=$members->pluck('id');
        if($ids->isEmpty()) {
            return ['period'=>$period,'rows'=>collect(),'totals'=>self::empty()];
        }
        $scoped=function(string $table) use($actor,$ids){
            return DB::table($table)
                ->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)
                ->whereIn('membership_id',$ids);
        };
        $attendance=$scoped('attendance_sessions')->whereBetween('work_date',[$first,$last])
            ->selectRaw('membership_id, COUNT(*) as sessions, SUM(CASE WHEN late = 1 THEN 1 ELSE 0 END) as late_count, SUM(CASE WHEN ended_at IS NULL THEN 1 ELSE 0 END) as open_count')
            ->groupBy('membership_id')->get()->keyBy('membership_id');
        // Phase 21: aggregate in SQL. Never load every historical claim or
        // leave row into PHP just to count/sum a month's practice dashboard.
        $leave=$scoped('leave_requests')
            ->where('status','approved')->where('date_from','<=',$last)
            ->where('date_to','>=',$first)
            ->selectRaw('membership_id, COUNT(*) as request_count')
            ->groupBy('membership_id')->get()->keyBy('membership_id');
        $claims=$scoped('conveyance_claims')->whereBetween('journey_date',[$first,$last])
            ->whereIn('status',['approved','paid'])
            ->selectRaw('membership_id, SUM(COALESCE(approved_amount, amount)) as approved_total')
            ->groupBy('membership_id')->get()->keyBy('membership_id');
        $timesheets=$scoped('audit_timesheets')->whereBetween('work_date',[$first,$last])
            ->where('status','approved')
            ->selectRaw('membership_id, SUM(hours) as hours, SUM(CASE WHEN billable = 1 THEN hours ELSE 0 END) as billable_hours')
            ->groupBy('membership_id')->get()->keyBy('membership_id');
        $payroll=DB::table('payroll_runs')
            ->where('company_id',$actor->company_id)->where('workspace_id',$actor->workspace_id)
            ->where('period_month',$first)
            ->where('status','locked')->first();
        $paylines=$payroll? DB::table('payroll_lines')
            ->where('payroll_run_id',$payroll->id)->whereIn('membership_id',$ids)
            ->get()->keyBy('membership_id') : collect();
        $rows=$members->map(function($member)use($first,$last,$attendance,$leave,$claims,$timesheets,$paylines){
            $a=$attendance->get($member->id);
            $t=$timesheets->get($member->id);
            $l=$leave->get($member->id);
            $c=$claims->get($member->id);
            // Leave is a count of approved requests overlapping this report
            // period; never infer absent days or salary deductions.
            return (object)[
                'member_id'=>$member->id,'name'=>$member->name,'active'=>$member->active,
                'sessions'=>(int)($a?->sessions??0),
                'late'=>(int)($a?->late_count??0),
                'open'=>(int)($a?->open_count??0),
                'leave_requests'=>(int)($l?->request_count??0),
                'approved_claim_bdt'=>round((float)($c?->approved_total??0),2),
                'approved_hours'=>round((float)($t?->hours??0),2),
                'billable_hours'=>round((float)($t?->billable_hours??0),2),
                'locked_payroll_bdt'=>$paylines->has($member->id)
                    ? (string)$paylines->get($member->id)->total_amount : null,
            ];
        });
        $total=self::empty();
        foreach($rows as $row){
            foreach(['sessions','late','open','leave_requests','approved_claim_bdt','approved_hours','billable_hours'] as $key){
                $total[$key]+=$row->{$key};
            }
            if($row->locked_payroll_bdt!==null){
                $total['locked_payroll_bdt']+=(float)$row->locked_payroll_bdt;
                $total['payroll_members']++;
            }
        }
        $total['members']=$rows->count();
        return ['period'=>$period,'rows'=>$rows,'totals'=>$total];
    }

    private static function empty():array
    {
        return ['members'=>0,'sessions'=>0,'late'=>0,'open'=>0,'leave_requests'=>0,
            'approved_claim_bdt'=>0.0,'approved_hours'=>0.0,'billable_hours'=>0.0,
            'locked_payroll_bdt'=>0.0,'payroll_members'=>0];
    }
}
