<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only, aggregate-only staging audit.
 *
 * It never prints employee names, wages, evidence paths, GPS, DB connection
 * errors or credentials. Findings require authorized financial review; this
 * tool does not infer or modify any payroll decision.
 */
class FinancialIntegrityCheck extends Command
{
    protected $signature = 'erp:integrity:check {--json : Print aggregate results in JSON without private records}';
    protected $description = 'Read-only detection of payroll/claim consistency risks before a reviewed release';

    public function handle(): int
    {
        $anomalies = [];
        $error = false;

        try {
            foreach (['conveyance_claims', 'payroll_lines', 'payroll_runs', 'attendance_sessions'] as $table) {
                if (! Schema::hasTable($table)) {
                    $error = true;
                    break;
                }
            }

            if (! $error) {
                // Older rows with no final Partner-approved amount require
                // case-by-case review. Never silently restore their original
                // requested amount as an approved financial decision.
                $anomalies['approved_claims_without_final_amount'] = DB::table('conveyance_claims')
                    ->whereIn('status', ['approved', 'paid'])
                    ->whereNull('approved_amount')->count();

                $anomalies['approved_claims_with_invalid_final_amount'] = DB::table('conveyance_claims')
                    ->whereIn('status', ['approved', 'paid'])
                    ->whereNotNull('approved_amount')
                    ->where(function ($query) {
                        $query->where('approved_amount', '<=', 0)
                            ->orWhereColumn('approved_amount', '>', 'amount');
                    })->count();

                // All columns are stored as fixed-precision DECIMAL in both
                // tested database engines. A half-cent tolerance protects
                // against numeric-driver representation differences.
                $anomalies['payroll_line_totals_mismatch'] = DB::table('payroll_lines')
                    ->whereRaw('ABS((base_allowance + approved_claims + adjustment) - total_amount) > 0.005')
                    ->count();

                $anomalies['paid_claims_without_paid_run_link'] = DB::table('conveyance_claims')
                    ->where('status', 'paid')
                    ->whereNull('paid_payroll_run_id')->count();

                $anomalies['settled_runs_missing_evidence'] = DB::table('payroll_runs')
                    ->whereIn('status', ['paid', 'locked'])
                    ->where(function ($query) {
                        $query->whereNull('payment_reference')
                            ->orWhere('payment_reference', '')
                            ->orWhereNull('payment_evidence_path')
                            ->orWhere('payment_evidence_path', '')
                            ->orWhereNull('reconciled_total')
                            ->orWhereNull('payment_date')
                            ->orWhereNull('payment_channel');
                    })->count();

                $anomalies['payroll_runs_without_lines'] = DB::table('payroll_runs as r')
                    ->whereNotExists(function ($query) {
                        $query->selectRaw('1')->from('payroll_lines as l')
                            ->whereColumn('l.payroll_run_id', 'r.id');
                    })->count();

                $anomalies['members_with_duplicate_open_sessions'] = DB::table('attendance_sessions')
                    ->whereNull('ended_at')
                    ->select('membership_id')
                    ->groupBy('membership_id')
                    ->havingRaw('COUNT(*) > 1')
                    ->get()->count();
            }
        } catch (Throwable $exception) {
            // Do not leak SQL, table names from exception messages, private
            // paths, connection strings or financial records to stdout.
            $error = true;
        }

        $decision = $error ? 'NO_GO' : (
            array_sum($anomalies) === 0 ? 'NO_ANOMALIES_DETECTED' : 'REVIEW_REQUIRED'
        );

        $report = [
            'decision' => $decision,
            'counts' => $anomalies,
            'note' => 'Read-only aggregate checks. This is neither a financial sign-off nor proof of backups, privacy compliance, or production readiness.',
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->line('ERP — Read-Only Financial Integrity Preflight');
            foreach ($anomalies as $name => $count) {
                $this->line(($count === 0 ? '[PASS] ' : '[REVIEW] ').$name.': '.$count);
            }
            if ($error) {
                $this->warn('Integrity checks could not complete. Verify schema and database access privately.');
            }
            $this->line('Decision: '.$decision);
            $this->warn($report['note']);
        }

        return $decision === 'NO_ANOMALIES_DETECTED' ? self::SUCCESS : self::FAILURE;
    }
}
