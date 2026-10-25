<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ExpenseRules
{
    public const CATEGORIES = [
        'transport' => 'Transport / Conveyance',
        'client_duty' => 'Client Duty Travel',
        'meals' => 'Meals on Client Duty',
        'lodging' => 'Approved Lodging',
        'other' => 'Other Authorized Expense',
    ];

    public static function policy(object $actor, string $date): ?object
    {
        return DB::table('workspace_expense_policies')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('expense_year',(int)substr($date,0,4))->first();
    }

    public static function enforce(object $actor,string $date,string $amount,
        string $category,?string $receiptHash,?int $excludeId=null): void
    {
        $policy=self::policy($actor,$date);
        if (!$policy) {
            return; // backward compatible: original 50,000 per-claim ceiling.
        }
        $cents=self::cents($amount);
        if ($cents>self::cents($policy->max_single_claim)) {
            throw ValidationException::withMessages([
                'amount'=>'This Partner policy permits at most BDT '.$policy->max_single_claim.' per claim.',
            ]);
        }
        $today=now('Asia/Dhaka')->startOfDay();
        if (\Carbon\CarbonImmutable::parse($date,'Asia/Dhaka')->lessThan($today->copy()->subDays((int)$policy->submission_window_days))) {
            throw ValidationException::withMessages(['journey_date'=>'This journey is older than the Partner submission window.']);
        }

        $first=substr($date,0,7).'-01';
        $last=\Carbon\CarbonImmutable::parse($date)->endOfMonth()->toDateString();
        $claims=DB::table('conveyance_claims')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('membership_id',$actor->id)
            ->whereBetween('journey_date',[$first,$last])
            ->whereIn('status',['pending','manager_reviewed','approved','paid'])
            ->when($excludeId,fn($q)=>$q->where('id','!=',$excludeId))->get(['amount','approved_amount']);
        $used=$claims->sum(fn($c)=>self::cents($c->approved_amount??$c->amount));
        if ($used+$cents>self::cents($policy->monthly_student_limit)) {
            throw ValidationException::withMessages([
                'amount'=>'Your monthly expense allowance would be exceeded. The Partner must review the policy.',
            ]);
        }
    }

    public static function receiptDuplicate(object $actor,string $hash,?int $except=null): bool
    {
        return DB::table('conveyance_claims')
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('membership_id',$actor->id)
            ->where('receipt_sha256',$hash)
            ->whereIn('status',['pending','manager_reviewed','approved','paid'])
            ->when($except,fn($q)=>$q->where('id','!=',$except))->exists();
    }

    public static function cents(string|int|float $v): int
    {
        [$whole,$frac]=explode('.',number_format((float)$v,2,'.',''));
        return (int)$whole*100+(int)$frac;
    }
}
