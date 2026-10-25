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
        if(!$subscription->current_period_end || !$subscription->current_period_start) {
            $today=$today??CarbonImmutable::now('Asia/Dhaka')->toDateString();
            if(!empty($subscription->trial_ends_at) && $today<$subscription->trial_ends_at){
                return 'trial';
            }
            return 'pending';
        }
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

    public static function newInvoice(object $company,object $subscription,object $plan,int $months,bool $audit=true):int
    {
        $today=CarbonImmutable::now($company->timezone)->toDateString();
        $start=$subscription->current_period_end && $subscription->current_period_end>$today
            ? $subscription->current_period_end : $today;
        $end=CarbonImmutable::parse($start)->addMonthsNoOverflow($months)->toDateString();
        $base=self::cents($plan->monthly_bdt)*$months;
        $discount=$months===12 ? intdiv($base*(int)($plan->annual_discount_percent??0),100) : 0;
        $units=(int)($subscription->pending_storage_addon_units
            ?? $subscription->storage_addon_units ?? 0);
        $addonUnit=(int)($plan->storage_addon_mib??100);
        $addonCost=self::cents($plan->storage_addon_monthly_bdt??0)*$units*$months;
        $amount=$base-$discount+$addonCost;
        if($amount<0 || $amount>999999999999){
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
            'annual_discount_percent_snapshot'=>$months===12?(int)($plan->annual_discount_percent??0):0,
            'discount_total_bdt'=>self::money($discount),
            'storage_addon_units_snapshot'=>$units,
            'storage_addon_mib_snapshot'=>$units*$addonUnit,
            'storage_addon_total_bdt'=>self::money($addonCost),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('saas_invoices')->where('id',$invoice)->update([
            'invoice_number'=>'DW-ERP-'.str_pad((string)$invoice,7,'0',STR_PAD_LEFT),
        ]);
        if($audit) {
            self::audit($company->id,'subscription.invoice.issued','saas_invoice',$invoice,[
                'months'=>$months,'amount_bdt'=>self::money($amount),
                'annual_discount_bdt'=>self::money($discount),
                'storage_addon_bdt'=>self::money($addonCost),
                'period_start'=>$start,'period_end'=>$end,'plan_id'=>$plan->id,
            ]);
        }
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

    /**
     * Company-wide paid add-on quota ledger, with existing workspace defaults.
     * No vault object moves or physical capacity promises: the endpoint allocates
     * only metadata-backed MiB to already created private workspace vaults.
     */
    public static function assignVaultQuota(int $companyId,int $workspaceId,int $targetMib): void
    {
        $company=DB::table('companies')->where('id',$companyId)->lockForUpdate()->first();
        abort_unless($company && $company->active && !$company->archived_at && !$company->deleted_at,404);
        $sub=DB::table('saas_subscriptions as s')
            ->join('saas_plans as p','p.id','=','s.plan_id')
            ->where('s.company_id',$companyId)
            ->select('s.*','p.storage_addon_mib')->first();
        abort_unless($sub,404);
        if(!in_array(self::effectiveStatus($sub),['active','grace'],true)) {
            throw ValidationException::withMessages(['target_mib'=>
                'Vault add-on capacity requires a paid active/grace subscription.']);
        }
        $workspace=DB::table('workspaces')->where('company_id',$companyId)
            ->where('id',$workspaceId)->where('active',true)->lockForUpdate()->first();
        abort_unless($workspace,404);
        $base=(int)config('erp_vault.workspace_limit_bytes',104857600);
        $addon=(int)$sub->storage_addon_units*(int)$sub->storage_addon_mib;
        $maxTotal=$base*DB::table('workspaces')->where('company_id',$companyId)
            ->where('active',true)->count()+$addon*1048576;
        if($targetMib<ceil($base/1048576) || $targetMib>16384) {
            throw ValidationException::withMessages(['target_mib'=>'Invalid workspace storage capacity.']);
        }
        $rows=DB::table('erp_vault_quotas')->where('company_id',$companyId)
            ->orderBy('workspace_id')->lockForUpdate()->get();
        $current=$rows->firstWhere('workspace_id',$workspaceId);
        $bytes=$targetMib*1048576;
        $otherExtra=$rows->filter(fn($r)=>(int)$r->workspace_id!==$workspaceId)
            ->sum(fn($r)=>max(0,(int)$r->limit_bytes-$base));
        if($bytes+$otherExtra>$base+$addon*1048576){
            throw ValidationException::withMessages(['target_mib'=>'Company paid storage add-on allowance exceeded.']);
        }
        if($current && $bytes<(int)$current->used_bytes+(int)$current->reserved_bytes) {
            throw ValidationException::withMessages(['target_mib'=>'Cannot reduce below committed and reserved bytes.']);
        }
        DB::table('erp_vault_quotas')->updateOrInsert([
            'company_id'=>$companyId,'workspace_id'=>$workspaceId,
        ],[
            'limit_bytes'=>$bytes,
            'updated_at'=>now(),
            'created_at'=>$current->created_at??now(),
        ]);
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
