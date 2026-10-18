<?php

namespace App\Console\Commands;

use App\Support\SaasBilling;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only finance safety check. Company names, transaction references,
 * invoice amounts, PII, bank credentials and SQL exceptions are NEVER printed.
 */
final class SaasBillingIntegrityCheck extends Command
{
    protected $signature = 'erp:billing:check {--json : Show aggregate-only JSON results}';
    protected $description = 'Detect SaaS billing settlement and domain-approval inconsistencies without modifying customer data';

    public function handle():int
    {
        $checks = [
            'invoices_with_paid_amount_mismatch' => 0,
            'paid_invoices_not_fully_settled' => 0,
            'open_invoices_marked_settled' => 0,
            'approved_payments_missing_verification' => 0,
            'cross_company_payment_links' => 0,
            'subscription_dates_without_fully_paid_invoice' => 0,
            'active_domains_without_dns_or_tls_approval' => 0,
        ];
        $schemaReady = true;
        try {
            foreach ([
                'saas_plans','saas_subscriptions','saas_invoices','saas_payments',
                'company_custom_domains',
            ] as $table) {
                if (! Schema::hasTable($table)) {
                    $schemaReady = false;
                    break;
                }
            }
            if ($schemaReady) {
                $paid = DB::table('saas_payments')
                    ->where('status','approved')
                    ->selectRaw('invoice_id, SUM(amount) AS verified_total')
                    ->groupBy('invoice_id')->get()->keyBy('invoice_id');
                $fullyPaidBySubscription = [];
                foreach (DB::table('saas_invoices')->orderBy('id')->cursor() as $invoice) {
                    $approvedCents = SaasBilling::cents(
                        $paid->get($invoice->id)?->verified_total ?? '0.00'
                    );
                    $paidCents = SaasBilling::cents($invoice->paid_amount);
                    $totalCents = SaasBilling::cents($invoice->total_amount);
                    if ($approvedCents !== $paidCents || $paidCents > $totalCents) {
                        ++$checks['invoices_with_paid_amount_mismatch'];
                    }
                    if ($invoice->status === 'paid') {
                        if ($totalCents === 0 || $paidCents !== $totalCents || !$invoice->settled_at) {
                            ++$checks['paid_invoices_not_fully_settled'];
                        } else {
                            $fullyPaidBySubscription[$invoice->subscription_id][$invoice->period_end] = true;
                        }
                    } elseif ($invoice->settled_at || $paidCents === $totalCents) {
                        ++$checks['open_invoices_marked_settled'];
                    }
                }
                $checks['approved_payments_missing_verification'] = DB::table('saas_payments')
                    ->where('status','approved')
                    ->where(function($q){
                        $q->whereNull('decided_by')->orWhereNull('decided_at')
                            ->orWhereNull('operator_note')->orWhere('operator_note','');
                    })->count();
                $checks['cross_company_payment_links'] = DB::table('saas_payments as p')
                    ->join('saas_invoices as i','i.id','=','p.invoice_id')
                    ->whereColumn('p.company_id','!=','i.company_id')->count();
                foreach (DB::table('saas_subscriptions')
                    ->whereNotNull('current_period_end')->cursor() as $subscription) {
                    if (empty($fullyPaidBySubscription[$subscription->id][$subscription->current_period_end])) {
                        ++$checks['subscription_dates_without_fully_paid_invoice'];
                    }
                }
                $checks['active_domains_without_dns_or_tls_approval'] =
                    DB::table('company_custom_domains')->where('active',true)
                        ->where(function($q){$q->whereNull('verified_at')
                            ->orWhereNull('tls_approved_at');})->count();
            }
        } catch(Throwable $exception) {
            $schemaReady = false;
        }
        $total=array_sum($checks);
        $result=[
            'decision'=>!$schemaReady?'SCHEMA_OR_QUERY_NOT_READY':($total>0?'REVIEW_REQUIRED':'NO_ANOMALIES_DETECTED'),
            'schema_ready'=>$schemaReady,'anomalies'=>$checks,
            'note'=>'Aggregate-only read check; not confirmation of bank deposits, SMTP, tenant UAT or backup recoverability.',
        ];
        if($this->option('json')){
            $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        } else {
            $this->line('DhakaWala ERP — SaaS Financial Integrity Precheck');
            foreach($checks as $key=>$count)$this->line($key.': '.$count);
            $this->line('Decision: '.$result['decision']);
            $this->warn($result['note']);
        }
        return $schemaReady && $total===0 ? self::SUCCESS : self::FAILURE;
    }
}
