<?php

namespace App\Console\Commands;

use App\Support\PolicyResolver;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyPayrollDrafts extends Command
{
    protected $signature = 'erp:payroll:auto-draft';
    protected $description = 'On the first day of each month, generate prior-month draft payroll for active Partner workspaces.';

    public function handle(): int
    {
        $localNow = CarbonImmutable::now('Asia/Dhaka');
        $period = $localNow->startOfMonth()->subMonth()->toDateString();
        $created = 0;

        $workspaces = DB::table('workspaces as w')
            ->join('companies as c', 'c.id', '=', 'w.company_id')
            ->where('w.active', true)->where('c.active', true)
            ->select('w.id', 'w.company_id', 'w.name')
            ->orderBy('w.company_id')->orderBy('w.id')->get();

        foreach ($workspaces as $workspace) {
            $company = DB::table('companies')->where('id', $workspace->company_id)->first();
            if (! $company) {
                continue;
            }

            $didCreate = DB::transaction(function () use ($company, $workspace, $period) {
                DB::table('workspaces')->where('id', $workspace->id)->lockForUpdate()->first();

                if (DB::table('payroll_runs')->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)
                    ->where('period_month', $period)->exists()) {
                    return false;
                }

                $members = DB::table('memberships')
                    ->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)
                    ->where('role', 'student')->where('active', true)->get();

                $hasPartner = DB::table('memberships')
                    ->where('company_id', $company->id)
                    ->where('workspace_id', $workspace->id)
                    ->where('role', 'partner')->where('active', true)->exists();

                if ($members->isEmpty() || ! $hasPartner) {
                    return false;
                }

                $policy = PolicyResolver::forDate($company, $workspace, $period);
                $id = DB::table('payroll_runs')->insertGetId([
                    'company_id' => $company->id,
                    'workspace_id' => $workspace->id,
                    'period_month' => $period,
                    'status' => 'draft',
                    'policy_snapshot' => json_encode([
                        ...$policy, 'timezone' => $company->timezone, 'no_auto_late_penalty' => true,
                    ], JSON_THROW_ON_ERROR),
                    // Intentionally no actor: this run is scheduled by the SYSTEM.
                    'last_action_by' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                foreach ($members as $member) {
                    $claims = DB::table('conveyance_claims')
                        ->where('company_id', $company->id)
                        ->where('workspace_id', $workspace->id)
                        ->where('membership_id', $member->id)
                        ->where('status', 'approved')
                        ->whereBetween('journey_date', [$period, CarbonImmutable::parse($period)->endOfMonth()->toDateString()])
                        ->whereNull('reserved_payroll_run_id')
                        ->whereNull('paid_payroll_run_id')
                        ->get()->sum(fn ($c) => (int) round(((float) ($c->approved_amount ?? $c->amount)) * 100));

                    $base = (int) round(((float) $member->monthly_allowance) * 100);
                    $toMoney = fn (int $cents) => sprintf('%d.%02d', intdiv($cents, 100), abs($cents % 100));

                    DB::table('payroll_lines')->insert([
                        'payroll_run_id' => $id,
                        'membership_id' => $member->id,
                        'base_allowance' => $toMoney($base),
                        'approved_claims' => $toMoney($claims),
                        'adjustment' => '0.00',
                        'total_amount' => $toMoney($base + $claims),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                DB::table('tenant_audit_events')->insert([
                    'company_id' => $company->id,
                    'workspace_id' => $workspace->id,
                    'actor_membership_id' => null,
                    'action' => 'payroll.auto_draft', 'entity_type' => 'payroll_run',
                    'entity_id' => $id,
                    'details' => json_encode(['period'=>$period], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);

                return true;
            }, 3);

            if ($didCreate) {
                $created++;
            }
        }

        $this->info('Monthly payroll drafts created: '.$created.'. Period: '.$period.'. Drafts require manual Partner review and approval.');
        return self::SUCCESS;
    }
}