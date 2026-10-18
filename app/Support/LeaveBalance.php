<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LeaveBalance
{
    public const TYPES = ['annual','sick','casual','emergency','compensatory','unpaid'];
    public const PORTIONS = ['full','morning','afternoon'];
    public const CREDIT_TYPES = ['annual','sick','casual','emergency','compensatory'];

    public static function policy(object $actor, int $year): ?object
    {
        return DB::table('workspace_leave_policies')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('leave_year',$year)->first();
    }

    public static function charge(object $company, object $workspace, object $student,
        string $from, string $to, string $portion, ?object $policy): float
    {
        if ($portion !== 'full' && $from !== $to) {
            throw ValidationException::withMessages(['day_portion'=>'Half-day leave must cover exactly one date.']);
        }
        $start=CarbonImmutable::parse($from,$company->timezone);
        $end=CarbonImmutable::parse($to,$company->timezone);
        if ($start->gt($end) || $start->diffInDays($end) > 60) {
            throw ValidationException::withMessages(['date_to'=>'A request cannot exceed 61 consecutive days.']);
        }
        if ($start->year !== $end->year) {
            throw ValidationException::withMessages(['date_to'=>'Submit separate leave requests for each calendar year.']);
        }

        $count=0.0;
        for ($day=$start;$day->lte($end);$day=$day->addDay()) {
            $rule=WorkCalendar::forMemberDate($company,$workspace,$student,$day->toDateString());
            if (! $policy || $policy->count_holidays || ! $rule['holiday']) {
                $count += $portion === 'full' ? 1.0 : 0.5;
            }
        }
        if ($count < 0.5) {
            throw ValidationException::withMessages(['date_from'=>'Selected dates contain no working days under your leave policy.']);
        }
        return $count;
    }

    public static function allowance(object $student, int $year, string $type, string $asOf): array
    {
        $policy=self::policy($student,$year);
        if (! $policy) {
            return ['policy'=>null,'entitled'=>null,'used'=>null,'reserved'=>null,'remaining'=>null,'carry'=>0.0];
        }
        $base=$type === 'compensatory' || $type === 'unpaid'
            ? 0.0 : (float) ($policy->{$type.'_days'} ?? 0);
        $carry=0.0;
        if ($type === 'annual' && (float)$policy->carry_forward_limit > 0) {
            $prior=self::policy($student,$year-1);
            if ($prior) {
                $lastEntitled=(float)$prior->annual_days;
                $lastCredit=(float)DB::table('leave_balance_adjustments')
                    ->where('company_id',$student->company_id)
                    ->where('workspace_id',$student->workspace_id)
                    ->where('membership_id',$student->id)
                    ->where('leave_year',$year-1)->where('leave_type','annual')
                    ->where(fn($q)=>$q->whereNull('expires_on')
                        ->orWhere('expires_on','>=',($year-1).'-12-31'))
                    ->sum('days');
                $usedPrevious=(float)DB::table('leave_requests')
                    ->where('company_id',$student->company_id)->where('workspace_id',$student->workspace_id)
                    ->where('membership_id',$student->id)->where('leave_type','annual')
                    ->where('status','approved')->whereYear('date_from',$year-1)
                    ->get(['date_from','date_to','charge_days','day_portion'])
                    ->sum(fn($r)=>self::rowCharge($r));
                $carry=min((float)$policy->carry_forward_limit,max(0,$lastEntitled+$lastCredit-$usedPrevious));
            }
        }
        $credits=(float)DB::table('leave_balance_adjustments')
            ->where('company_id',$student->company_id)
            ->where('workspace_id',$student->workspace_id)
            ->where('membership_id',$student->id)
            ->where('leave_year',$year)->where('leave_type',$type)
            ->where(fn($q)=>$q->whereNull('expires_on')->orWhere('expires_on','>=',$asOf))
            ->sum('days');

        // Outstanding requests reserve entitlements until a final decision.
        $used=(float)DB::table('leave_requests')
            ->where('company_id',$student->company_id)->where('workspace_id',$student->workspace_id)
            ->where('membership_id',$student->id)->where('leave_type',$type)
            ->where('status','approved')->whereYear('date_from',$year)
            ->get(['date_from','date_to','charge_days','day_portion'])
            ->sum(fn($r)=>self::rowCharge($r));
        $reserved=(float)DB::table('leave_requests')
            ->where('company_id',$student->company_id)->where('workspace_id',$student->workspace_id)
            ->where('membership_id',$student->id)->where('leave_type',$type)
            ->whereIn('status',['pending','manager_reviewed'])->whereYear('date_from',$year)
            ->get(['date_from','date_to','charge_days','day_portion'])
            ->sum(fn($r)=>self::rowCharge($r));
        $entitled=$base+$credits+$carry;
        return [
            'policy'=>$policy,'entitled'=>$entitled,'used'=>$used,
            'reserved'=>$reserved,'remaining'=>$entitled-$used-$reserved,'carry'=>$carry,
        ];
    }

    public static function assertAffordable(object $student, string $type,
        string $date, float $charge, ?int $excludeRequestId = null): void
    {
        if ($type === 'unpaid') {
            return; // Does NOT automatically cause a payroll deduction.
        }
        $result=self::allowance($student,(int)substr($date,0,4),$type,$date);
        if (! $result['policy']) {
            return; // Existing tenants keep their historical workflow until enabled.
        }
        $available=$result['remaining'];
        if ($excludeRequestId) {
            $old=DB::table('leave_requests')->where('id',$excludeRequestId)
                ->where('company_id',$student->company_id)
                ->where('workspace_id',$student->workspace_id)
                ->where('membership_id',$student->id)->first();
            if ($old && $old->leave_type === $type
                && in_array($old->status,['pending','manager_reviewed'],true)) {
                $available+=(float)$old->charge_days;
            }
        }
        if ($charge > $available + 0.001) {
            throw ValidationException::withMessages([
                'date_from'=>'Insufficient '.$type.' leave balance. Available: '
                    .number_format(max(0,$available),1).' day(s).',
            ]);
        }
    }

    private static function rowCharge(object $row): float
    {
        if ($row->charge_days !== null) {
            return (float)$row->charge_days;
        }
        // Legacy requests have no balance snapshot. Charge conservatively by
        // calendar days rather than pretending historical approved leave is free.
        // Never rewrite older attendance, leave or payroll rows on migration.
        return $row->day_portion !== 'full' ? 0.5
            : CarbonImmutable::parse($row->date_from)->diffInDays(CarbonImmutable::parse($row->date_to)) + 1;
    }

    public static function overlaps(object $student, string $from, string $to,
        string $portion, ?int $exceptId = null): bool
    {
        $rows=DB::table('leave_requests')
            ->where('company_id',$student->company_id)
            ->where('workspace_id',$student->workspace_id)
            ->where('membership_id',$student->id)
            ->whereIn('status',['pending','manager_reviewed','approved'])
            ->where('date_from','<=',$to)->where('date_to','>=',$from)
            ->when($exceptId,fn($q)=>$q->where('id','!=',$exceptId))->get();
        return $rows->contains(function($row)use($from,$to,$portion) {
            // Morning and afternoon leave can coexist on one day.
            if ($from === $to && $row->date_from === $from && $row->date_to === $to
                && $portion !== 'full' && $row->day_portion !== 'full'
                && $row->day_portion !== $portion) {
                return false;
            }
            return true;
        });
    }
}
