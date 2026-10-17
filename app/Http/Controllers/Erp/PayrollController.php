<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\PolicyResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);

        // Payroll amounts are private financial records. Managers have no
        // implicit salary permission; students may only view their own
        // FINALIZED, LOCKED statement. Partners may review all run stages
        // within their own Home Partner workspace.
        TenantOps::requireRole($actor, ['partner', 'student']);

        $runs = DB::table('payroll_runs')->where('company_id', $company->id)
            ->where('workspace_id', $workspace->id)
            ->when($actor->role === 'student', fn ($q) => $q->where('status', 'locked'))
            ->orderByDesc('period_month')->limit(24)->get();

        $selectedId = $request->integer('run');
        $run = $runs->firstWhere('id', $selectedId) ?? $runs->first();

        $lines = collect();
        if ($run) {
            $lines = DB::table('payroll_lines as p')
                ->join('memberships as m', 'm.id', '=', 'p.membership_id')
                ->join('users as u', 'u.id', '=', 'm.user_id')
                ->where('p.payroll_run_id', $run->id)
                ->where('m.company_id', $company->id)
                ->where('m.workspace_id', $workspace->id)
                ->when($actor->role === 'student', fn ($q) => $q->where('m.id', $actor->id))
                ->select('p.*', 'u.name as member_name')->orderBy('u.name')->get();
        }

        return view('erp.payroll', compact('company', 'workspace', 'actor', 'firmSlug', 'runs', 'run', 'lines'));
    }

    public function generate(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $input = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $period = $input['month'].'-01';

        DB::transaction(function () use ($actor, $company, $workspace, $period) {
            DB::table('workspaces')->where('id', $workspace->id)->lockForUpdate()->first();

            if (DB::table('payroll_runs')->where('workspace_id', $workspace->id)
                ->where('period_month', $period)->exists()) {
                throw ValidationException::withMessages(['month' => 'This period already has a payroll run.']);
            }

            $runId = DB::table('payroll_runs')->insertGetId([
                'company_id' => $actor->company_id,
                'workspace_id' => $actor->workspace_id,
                'period_month' => $period,
                'status' => 'draft',
                'policy_snapshot' => json_encode([
                    ...PolicyResolver::forDate($company, $workspace, $period),
                    'timezone' => $company->timezone,
                    'no_auto_late_penalty' => true,
                ], JSON_THROW_ON_ERROR),
                'last_action_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->snapshotLines($runId, $period, $actor);
            TenantOps::audit($actor, 'payroll.generate', 'payroll_run', $runId);
        }, 3);

        return back()->with('success', 'Payroll draft generated. Review amounts before approval.');
    }

    public function advance(Request $request, string $firmSlug, int $runId)
    {
        [, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);

        $input = $request->validate([
            'action' => ['required', 'in:review,approve,record_paid,lock'],
            'payment_reference' => ['nullable', 'string', 'min:5', 'max:120', 'required_if:action,record_paid'],
            'payment_channel' => ['required_if:action,record_paid', 'nullable', 'in:bank_transfer,mobile_financial_service,cash,cheque'],
            'payment_date' => ['required_if:action,record_paid', 'nullable', 'date_format:Y-m-d'],
            'reconciled_total' => ['required_if:action,record_paid', 'nullable', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'payment_evidence' => ['required_if:action,record_paid', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        if ($input['action'] === 'record_paid'
            && $input['payment_date'] > CarbonImmutable::now('Asia/Dhaka')->toDateString()) {
            throw ValidationException::withMessages([
                'payment_date' => 'The external settlement date cannot be in the future.',
            ]);
        }

        // File persistence is outside the DB transaction: remove orphan files
        // whenever a concurrently rejected or invalid transition rolls back.
        $evidencePath = null;
        if ($input['action'] === 'record_paid') {
            $evidencePath = $request->file('payment_evidence')->store(
                'private-payroll-evidence/'.$actor->company_id.'/'.$workspace->id,
                'local'
            );
            if (! is_string($evidencePath) || $evidencePath === '') {
                throw ValidationException::withMessages(['payment_evidence' => 'Unable to store payment evidence.']);
            }
        }

        try {
            DB::transaction(function () use ($workspace, $actor, $runId, $input, $evidencePath) {
            DB::table('workspaces')->where('id', $workspace->id)->lockForUpdate()->first();
            $run = DB::table('payroll_runs')
                ->where('id', $runId)
                ->where('company_id', $actor->company_id)
                ->where('workspace_id', $workspace->id)
                ->lockForUpdate()->first();
            abort_unless($run, 404);

            $expected = [
                'review' => 'draft', 'approve' => 'reviewed',
                'record_paid' => 'approved', 'lock' => 'paid',
            ];
            $action = $input['action'];
            if ($run->status !== $expected[$action]) {
                throw ValidationException::withMessages(['action' => 'Invalid or already completed payroll transition.']);
            }

            if ($action === 'review') {
                // Materialize the final allowance+claim snapshot and reserve claims
                // under a workspace row lock, preventing a second run from reusing them.
                $this->snapshotLines($run->id, $run->period_month, $actor);
                $begin = CarbonImmutable::parse($run->period_month);
                $end = $begin->endOfMonth()->toDateString();
                $reserved = DB::table('conveyance_claims')
                    ->where('company_id', $actor->company_id)
                    ->where('workspace_id', $workspace->id)
                    ->where('status', 'approved')
                    ->whereBetween('journey_date', [$run->period_month, $end])
                    ->whereNull('reserved_payroll_run_id')
                    ->whereNull('paid_payroll_run_id')
                    ->whereIn('membership_id', DB::table('payroll_lines')
                        ->where('payroll_run_id', $run->id)->select('membership_id'))
                    ->update(['reserved_payroll_run_id' => $run->id, 'updated_at' => now()]);
            }

            if ($action === 'record_paid') {
                $lineTotals = DB::table('payroll_lines')
                    ->where('payroll_run_id', $run->id)
                    ->get(['total_amount']);
                if ($lineTotals->isEmpty()) {
                    throw ValidationException::withMessages(['action' => 'The payroll has no payable lines.']);
                }
                $expectedCents = $lineTotals->sum(fn ($line) => $this->cents($line->total_amount));
                if ($expectedCents !== $this->cents($input['reconciled_total'])) {
                    throw ValidationException::withMessages([
                        'reconciled_total' => 'The external settlement total must match all payroll lines exactly.',
                    ]);
                }

                $ref = trim($input['payment_reference']);
                if (DB::table('payroll_runs')->where('company_id', $actor->company_id)
                    ->where('payment_reference', $ref)->exists()) {
                    throw ValidationException::withMessages(['payment_reference' => 'This payment reference has already been recorded.']);
                }

                DB::table('conveyance_claims')
                    ->where('company_id', $actor->company_id)
                    ->where('workspace_id', $workspace->id)
                    ->where('reserved_payroll_run_id', $run->id)
                    ->where('status', 'approved')
                    ->update(['status' => 'paid', 'paid_payroll_run_id' => $run->id, 'updated_at' => now()]);
            }

            $status = [
                'review' => 'reviewed', 'approve' => 'approved',
                'record_paid' => 'paid', 'lock' => 'locked',
            ][$action];

            $change = ['status' => $status, 'last_action_by' => $actor->id, 'updated_at' => now()];
            if ($action === 'record_paid') {
                $change['paid_at'] = now();
                $change['payment_reference'] = trim($input['payment_reference']);
                $change['payment_channel'] = $input['payment_channel'];
                $change['payment_date'] = $input['payment_date'];
                $change['reconciled_total'] = $this->money($expectedCents);
                $change['payment_evidence_path'] = $evidencePath;
            }
            if ($action === 'lock') {
                $change['locked_at'] = now();
            }

            DB::table('payroll_runs')->where('id', $runId)->update($change);
            TenantOps::audit($actor, 'payroll.'.$status, 'payroll_run', $runId, [
                'payment_reference' => $action === 'record_paid' ? $change['payment_reference'] : null,
            ]);
            }, 3);
        } catch (\Throwable $exception) {
            if ($evidencePath !== null) {
                Storage::disk('local')->delete($evidencePath);
            }
            throw $exception;
        }

        return back()->with('success', 'Payroll state updated. The audit trail has been recorded.');
    }

    public function paymentEvidence(Request $request, string $firmSlug, int $runId)
    {
        [, $workspace, $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);

        $run = DB::table('payroll_runs')
            ->where('id', $runId)
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', ['paid', 'locked'])
            ->first();

        abort_unless($run && $run->payment_evidence_path
            && Storage::disk('local')->exists($run->payment_evidence_path), 404);

        return Storage::disk('local')->download($run->payment_evidence_path, 'payroll-evidence-'.$runId.'.'.pathinfo($run->payment_evidence_path, PATHINFO_EXTENSION));
    }

    private function snapshotLines(int $runId, string $period, object $actor): void
    {
        $lastDate = CarbonImmutable::parse($period)->endOfMonth()->toDateString();
        $members = DB::table('memberships')->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)->where('role', 'student')
            ->where('active', true)->get();

        $claims = DB::table('conveyance_claims')
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)
            ->where('status', 'approved')
            ->whereBetween('journey_date', [$period, $lastDate])
            ->whereNull('reserved_payroll_run_id')->whereNull('paid_payroll_run_id')
            ->lockForUpdate()->get()->groupBy('membership_id');

        foreach ($members as $member) {
            $base = $this->cents($member->monthly_allowance);
            $claimTotal = $claims->get($member->id, collect())
                ->sum(fn ($c) => $this->cents($c->approved_amount ?? $c->amount));

            DB::table('payroll_lines')->updateOrInsert([
                'payroll_run_id' => $runId, 'membership_id' => $member->id,
            ], [
                'base_allowance' => $this->money($base),
                'approved_claims' => $this->money($claimTotal),
                'adjustment' => '0.00',
                'total_amount' => $this->money($base + $claimTotal),
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }

    private function cents(string|int|float $amount): int
    {
        $value = number_format((float) $amount, 2, '.', '');
        [$units, $fraction] = explode('.', $value);
        return ((int) $units) * 100 + (int) $fraction;
    }

    private function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), abs($cents % 100));
    }
}