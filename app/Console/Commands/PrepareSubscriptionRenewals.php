<?php

namespace App\Console\Commands;

use App\Support\SaasBilling;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PrepareSubscriptionRenewals extends Command
{
    protected $signature='erp:billing:renewals {--dry-run : Show eligible companies without modifying invoices}';
    protected $description='Issue opt-in unpaid SaaS renewal invoices without charging accounts or suspending tenants.';

    public function handle(): int
    {
        $issued=0;$eligible=0;
        DB::table('saas_subscriptions as s')
            ->join('companies as c','c.id','=','s.company_id')
            ->where('s.auto_invoice',true)
            ->whereNotNull('s.current_period_end')
            ->where('c.active',true)->whereNull('c.archived_at')->whereNull('c.deleted_at')
            ->select('s.id')->orderBy('s.id')->chunkById(50,function($subs)use(&$issued,&$eligible){
                foreach($subs as $item){
                    DB::transaction(function()use($item,&$issued,&$eligible){
                        // Same lock as manual billing; idempotent against parallel workers.
                        $subRef=DB::table('saas_subscriptions')->where('id',$item->id)->first();
                        if(!$subRef)return;
                        $company=DB::table('companies')->where('id',$subRef->company_id)
                            ->lockForUpdate()->first();
                        if(!$company || !$company->active || $company->archived_at || $company->deleted_at)return;
                        $sub=DB::table('saas_subscriptions')->where('id',$item->id)
                            ->where('company_id',$company->id)->lockForUpdate()->first();
                        if(!$sub || !$sub->auto_invoice || !$sub->current_period_end)return;
                        $today=CarbonImmutable::now($company->timezone?:'Asia/Dhaka')->toDateString();
                        $end=CarbonImmutable::parse($sub->current_period_end);
                        // No invoices for old, disputed or never-paid subscriptions.
                        $days=CarbonImmutable::parse($today)->diffInDays($end,false);
                        if($days>7 || $days< -30)return;
                        if(DB::table('saas_invoices')->where('subscription_id',$sub->id)
                            ->whereIn('status',['pending','partial'])->exists())return;
                        if(DB::table('saas_billing_automation_events')->where('subscription_id',$sub->id)
                            ->where('period_end_at_check',$sub->current_period_end)->exists())return;
                        $months=(int)$sub->renewal_months;
                        if(!in_array($months,[1,3,6,12],true))return;
                        $plan=DB::table('saas_plans')->where('id',$sub->plan_id)->first();
                        if(!$plan)return;
                        $eligible++;
                        if($this->option('dry-run'))return;
                        $invoice=SaasBilling::newInvoice($company,$sub,$plan,$months,false);
                        DB::table('saas_billing_automation_events')->insert([
                            'company_id'=>$company->id,'subscription_id'=>$sub->id,
                            'invoice_id'=>$invoice,'period_end_at_check'=>$sub->current_period_end,
                            'event_type'=>'renewal_invoice_issued','created_at'=>now(),
                        ]);
                        $issued++;
                    },3);
                }
            },'s.id','id');
        $this->info($this->option('dry-run')
            ?'Eligible auto-invoice subscriptions (no money charged): '.$eligible
            :'Unpaid renewal invoices prepared (no money charged): '.$issued);
        return self::SUCCESS;
    }
}
