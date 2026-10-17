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
            ] as $check => $table) {
                $checks[$check] = Schema::hasTable($table);
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
            $this->info('RA&C ERP — cPanel Database Readiness');
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
