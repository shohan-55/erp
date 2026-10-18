<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaasBilling
{
    public static function cents(string|float|int $amount):int
    {
        $number=number_format((float)$amount,2,'.','');
        [$units,$minor]=explode('.',$number);
        return (int)$units*100+(int)$minor;
    }

    public static function money(int $cents):string
    {
        return ($cents<0?'-':'').sprintf('%d.%02d',intdiv(abs($cents),100),abs($cents)%100);
    }

    public static function effectiveStatus(object $subscription,?string $today=null):string
    {
        if(!$subscription->current_period_end || !$subscription->current_period_start) return 'pending';
        $today=$today??CarbonImmutable::now('Asia/Dhaka')->toDateString();
        if($today<$subscription->current_period_end) return 'active';
        $grace=CarbonImmutable::parse($subscription->current_period_end)
            ->addDays((int)$subscription->grace_days)->toDateString();
        return $today<$grace?'grace':'overdue';
    }

    public static function audit(int $companyId,string $action,string $type,int $id,array $data=[]):void
    {
        DB::table('platform_control_events')->insert([
            'company_id'=>$companyId,'actor_user_id'=>auth()->id(),
            'action'=>$action,'subject_type'=>$type,'subject_id'=>$id,
            'details'=>json_encode($data,JSON_THROW_ON_ERROR),'created_at'=>now(),
        ]);
    }

    public static function newInvoice(object $company,object $subscription,object $plan,int $months):int
    {
        $today=CarbonImmutable::now($company->timezone)->toDateString();
        $start=$subscription->current_period_end && $subscription->current_period_end>$today
            ? $subscription->current_period_end : $today;
        $end=CarbonImmutable::parse($start)->addMonthsNoOverflow($months)->toDateString();
        $amount=self::cents($plan->monthly_bdt)*$months;
        if($amount>999999999999){
            throw ValidationException::withMessages(['months'=>'Invoice amount exceeds the supported limit.']);
        }
        // Prefix and database sequence ID are operator-facing and not
        // guessable tokens; authorization never depends on invoice numbers.
        $invoice=DB::table('saas_invoices')->insertGetId([
            'company_id'=>$company->id,'subscription_id'=>$subscription->id,'plan_id'=>$plan->id,
            'invoice_number'=>'TMP-'.\Illuminate\Support\Str::random(24),
            'plan_name_snapshot'=>$plan->name,'currency'=>'BDT','months'=>$months,
            'period_start'=>$start,'period_end'=>$end,
            'total_amount'=>self::money($amount),'paid_amount'=>'0.00',
            'status'=>'pending','due_on'=>CarbonImmutable::parse($today)->addDays(7)->toDateString(),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('saas_invoices')->where('id',$invoice)->update([
            'invoice_number'=>'DW-ERP-'.str_pad((string)$invoice,7,'0',STR_PAD_LEFT),
        ]);
        self::audit($company->id,'subscription.invoice.issued','saas_invoice',$invoice,[
            'months'=>$months,'amount_bdt'=>self::money($amount),
            'period_start'=>$start,'period_end'=>$end,'plan_id'=>$plan->id,
        ]);
        return $invoice;
    }

    /**
     * Reservation-safe plan limits for new workspaces and memberships.
     * Current members, unaccepted invitations and active Partner units count;
     * historical exited staff are retained without consuming a new seat.
     * Caller must already be in a DB transaction; company row lock serializes
     * parallel Platform and Partner-created invitations.
     */
    public static function assertCapacity(int $companyId,int $newWorkspaces,int $newMembers):void
    {
        DB::table('companies')->where('id',$companyId)->lockForUpdate()->first();
        $plan=DB::table('saas_subscriptions as s')
            ->join('saas_plans as p','p.id','=','s.plan_id')
            ->where('s.company_id',$companyId)
            ->select('p.max_partner_workspaces','p.max_members')->first();
        if(!$plan) return; // legacy unbilled firms are grandfathered.
        $ws=DB::table('workspaces')->where('company_id',$companyId)
            ->where('active',true)->count();
        $seats=DB::table('memberships')->where('company_id',$companyId)
            ->where(function($q){
                $q->where('active',true)
                    ->orWhere(function($p){
                        $p->whereNotNull('invited_at')->whereNull('activated_at')
                            ->whereNull('platform_suspended_at');
                    });
            })->count();
        if($ws+$newWorkspaces>$plan->max_partner_workspaces){
            throw ValidationException::withMessages(['workspace_slug'=>
                'The subscribed SaaS plan has reached its active Partner workspace capacity.']);
        }
        if($seats+$newMembers>$plan->max_members){
            throw ValidationException::withMessages(['email'=>
                'The subscribed SaaS plan has reached its active and invited membership capacity.']);
        }
    }

    public static function domainAllowed(int $companyId):bool
    {
        $sub=DB::table('saas_subscriptions as s')
            ->join('saas_plans as p','p.id','=','s.plan_id')
            ->where('s.company_id',$companyId)
            ->select('s.*','p.custom_domain_enabled')->first();
        // Never break pre-existing Domain workflows for tenants that have not
        // explicitly been onboarded to paid billing.
        if(!$sub) return true;
        return (bool)$sub->custom_domain_enabled
            && in_array(self::effectiveStatus($sub),['active','grace'],true);
    }
}
