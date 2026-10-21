<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * A read-only, secret-free database readiness report for the cPanel deployer.
 * This command must never print SQL exception messages: they can contain
 * database hostnames, usernames, SQL and other sensitive environment details.
 */
class DatabaseReadinessCheck extends Command
{
    protected $signature = 'erp:db:check {--json : Output secret-free JSON}';

    protected $description = 'Check that the cPanel ERP database is accessible and its schema is ready, without changing data';

    public function handle(): int
    {
        $driver = (string) config('database.default');

        $checks = [
            'database_driver_supported' => in_array($driver, ['mysql', 'mariadb'], true)
                || app()->environment('testing') && $driver === 'sqlite',
            'connection_reachable' => false,
            'migrations_table_present' => false,
            'users_table_present' => false,
            'companies_table_present' => false,
            'workspaces_table_present' => false,
            'memberships_table_present' => false,
            'attendance_table_present' => false,
            'leave_table_present' => false,
            'conveyance_table_present' => false,
            'payroll_table_present' => false,
            'custom_domains_table_present' => false,
            'staff_lifecycle_table_present' => false,
            'audit_clients_table_present' => false,
            'audit_engagements_table_present' => false,
            'audit_timesheets_table_present' => false,
            'practice_notices_table_present' => false,
            'private_documents_table_present' => false,
            'saas_plans_table_present' => false,
            'saas_subscriptions_table_present' => false,
            'saas_invoices_table_present' => false,
            'saas_payments_table_present' => false,
            'all_release_migrations_applied' => false,
        ];

        try {
            DB::connection()->select('SELECT 1');
            $checks['connection_reachable'] = true;

            foreach ([
                'migrations_table_present' => 'migrations',
                'users_table_present' => 'users',
                'companies_table_present' => 'companies',
                'workspaces_table_present' => 'workspaces',
                'memberships_table_present' => 'memberships',
                'attendance_table_present' => 'attendance_sessions',
                'leave_table_present' => 'leave_requests',
                'conveyance_table_present' => 'conveyance_claims',
                'payroll_table_present' => 'payroll_runs',
                'custom_domains_table_present' => 'company_custom_domains',
                'staff_lifecycle_table_present' => 'staff_profiles',
                'audit_clients_table_present' => 'audit_clients',
                'audit_engagements_table_present' => 'audit_engagements',
                'audit_timesheets_table_present' => 'audit_timesheets',
                'practice_notices_table_present' => 'practice_notices',
                'private_documents_table_present' => 'practice_documents',
                'saas_plans_table_present' => 'saas_plans',
                'saas_subscriptions_table_present' => 'saas_subscriptions',
                'saas_invoices_table_present' => 'saas_invoices',
                'saas_payments_table_present' => 'saas_payments',
            ] as $check => $table) {
                $checks[$check] = Schema::hasTable($table);
            }

            if ($checks['migrations_table_present']) {
                $files = glob(database_path('migrations/*.php')) ?: [];
                $expected = array_map(static fn (string $path) => pathinfo($path, PATHINFO_FILENAME), $files);
                $applied = DB::table('migrations')->pluck('migration')->all();
                $checks['all_release_migrations_applied'] = $expected !== []
                    && count(array_diff($expected, $applied)) === 0;
            }
        } catch (Throwable $exception) {
            // Do NOT display or log the raw connection exception to end users
            // or CI: it can include DNS, SQL and server account details.
        }

        $failed = array_keys(array_filter($checks, static fn (bool $pass) => ! $pass));
        $state = $failed === [] ? 'SCHEMA_READY' : (
            $checks['connection_reachable']
                ? 'SCHEMA_NOT_READY'
                : 'CONNECTION_NOT_READY'
        );

        $result = [
            'decision' => $state,
            'checks' => $checks,
            'failed' => $failed,
            'note' => 'Read-only precheck only; not a production approval or proof of data isolation/backups.',
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->info('DhakaWala ERP — cPanel Database Readiness');
            foreach ($checks as $name => $pass) {
                $this->line(($pass ? '[PASS] ' : '[FAIL] ').$name);
            }
            $this->line('Decision: '.$state);
            if (! $checks['connection_reachable']) {
                $this->warn('Database connection failed. Check credentials privately in the cPanel account; do not paste passwords into chat or logs.');
            }
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
