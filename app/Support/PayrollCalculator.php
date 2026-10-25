<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single source of payroll draft arithmetic for automatic and Partner-created
 * drafts. Financial decisions stay explicit; attendance/leave are not penalties.
 */
final class PayrollCalculator
{
    public static function cents(string|float|int $amount): int
    {
        $v=number_format((float)$amount,2,'.','');
        // Keep the sign for negative values smaller than BDT 1.00.
        // Casting the "-0" whole-unit part would otherwise lose it.
        $negative=str_starts_with($v,'-');
        [$units,$minor]=explode('.',ltrim($v,'-'));
        $cents=(int)$units*100+(int)$minor;
        return $negative ? -$cents : $cents;
    }

    public static function money(int $cents): string
    {
        return ($cents < 0 ? '-' : '').sprintf('%d.%02d',
            intdiv(abs($cents),100),abs($cents)%100);
    }

    public static function refresh(int $runId,string $period,int $companyId,int $workspaceId): void
    {
        $run=DB::table('payroll_runs')->where('id',$runId)
            ->where('company_id',$companyId)->where('workspace_id',$workspaceId)
            ->where('status','draft')->first();
        if (!$run) {
            throw ValidationException::withMessages(['action'=>'Only draft payroll may be recalculated.']);
        }
        $end=CarbonImmutable::parse($period)->endOfMonth()->toDateString();
        $members=DB::table('memberships')->where('company_id',$companyId)
            ->where('workspace_id',$workspaceId)->where('role','student')
            ->where('active',true)->orderBy('id')->get();
        $claims=DB::table('conveyance_claims')->where('company_id',$companyId)
            ->where('workspace_id',$workspaceId)->where('status','approved')
            ->whereBetween('journey_date',[$period,$end])
            ->whereNull('reserved_payroll_run_id')->whereNull('paid_payroll_run_id')
            ->lockForUpdate()->get()->groupBy('membership_id');
        $adjustments=DB::table('payroll_line_adjustments')
            ->where('company_id',$companyId)->where('workspace_id',$workspaceId)
            ->where('payroll_run_id',$runId)->whereNull('voided_at')
            ->get()->groupBy('membership_id');

        foreach ($members as $member) {
            $salary=DB::table('staff_compensation_changes')
                ->where('company_id',$companyId)->where('workspace_id',$workspaceId)
                ->where('membership_id',$member->id)->where('effective_month','<=',$period)
                ->orderByDesc('effective_month')->orderByDesc('id')->first();
            $base=self::cents($salary?->new_amount ?? $member->monthly_allowance);
            $claimsCents=$claims->get($member->id,collect())->sum(
                fn($c)=>self::cents($c->approved_amount ?? $c->amount));
            $difference=$adjustments->get($member->id,collect())->sum(
                fn($a)=>($a->kind==='bonus' ? 1 : -1)*self::cents($a->amount));
            $total=$base+$claimsCents+$difference;
            if($total<0){
                throw ValidationException::withMessages([
                    'amount'=>'Payroll deductions must not exceed the available line amount.',
                ]);
            }
            DB::table('payroll_lines')->updateOrInsert([
                'payroll_run_id'=>$runId,'membership_id'=>$member->id,
            ],[
                'base_allowance'=>self::money($base),
                'approved_claims'=>self::money($claimsCents),
                'adjustment'=>self::money($difference),
                'total_amount'=>self::money($total),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }
}
