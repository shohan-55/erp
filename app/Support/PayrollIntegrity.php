<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Canonical review-time financial digest.
 * Detects out-of-band payroll line edits after review (not a cryptographic
 * signature against database administrators). Scope and ordering are explicit.
 */
final class PayrollIntegrity
{
    public static function capture(object $run): array
    {
        $lines = DB::table('payroll_lines as l')
            ->join('memberships as m', 'm.id', '=', 'l.membership_id')
            ->where('l.payroll_run_id', $run->id)
            ->where('m.company_id', $run->company_id)
            ->where('m.workspace_id', $run->workspace_id)
            ->orderBy('l.membership_id')
            ->get(['l.membership_id','l.base_allowance','l.approved_claims','l.adjustment','l.total_amount']);
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'action' => 'Payroll must have student lines before review.',
            ]);
        }
        $sums = ['base_cents'=>0,'claims_cents'=>0,'adjustment_cents'=>0,'net_cents'=>0];
        $data = [];
        foreach ($lines as $line) {
            $base = PayrollCalculator::cents($line->base_allowance);
            $claims = PayrollCalculator::cents($line->approved_claims);
            $adjustment = PayrollCalculator::cents($line->adjustment);
            $net = PayrollCalculator::cents($line->total_amount);
            if ($base + $claims + $adjustment !== $net || $net < 0) {
                throw ValidationException::withMessages([
                    'action' => 'One or more payroll lines do not reconcile to their components.',
                ]);
            }
            $data[] = [(int)$line->membership_id,$base,$claims,$adjustment,$net];
            $sums['base_cents'] += $base;
            $sums['claims_cents'] += $claims;
            $sums['adjustment_cents'] += $adjustment;
            $sums['net_cents'] += $net;
        }
        return [
            'v'=>1,
            'line_count'=>count($data),
            ...$sums,
            'sha256'=>hash('sha256', json_encode([
                'company_id'=>(int)$run->company_id,
                'workspace_id'=>(int)$run->workspace_id,
                'run_id'=>(int)$run->id,
                'period'=>$run->period_month,
                'lines'=>$data,
            ], JSON_THROW_ON_ERROR)),
        ];
    }

    public static function assertUnchanged(object $run): void
    {
        if ($run->review_snapshot === null) {
            // Existing reviewed/approved payroll predating this phase has no
            // review digest. It is not retroactively changed.
            return;
        }
        $prior = json_decode($run->review_snapshot,true);
        if (!is_array($prior) || ($prior['v'] ?? null)!==1
            || empty($prior['sha256'])) {
            throw ValidationException::withMessages([
                'action'=>'Payroll review snapshot is incomplete; contact your Partner.',
            ]);
        }
        $now = self::capture($run);
        if (!hash_equals((string)$prior['sha256'],$now['sha256'])
            || (int)($prior['net_cents'] ?? -1)!==$now['net_cents']
            || (int)($prior['line_count'] ?? -1)!==$now['line_count']) {
            throw ValidationException::withMessages([
                'action'=>'Payroll lines changed after review. Settlement is blocked to protect the reviewed snapshot.',
            ]);
        }
    }
}
