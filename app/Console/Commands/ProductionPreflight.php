<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class ProductionPreflight extends Command
{
    protected $signature = 'erp:release:check {--json : Machine-readable release check output}';
    protected $description = 'Check fail-closed security and deployment configuration before ERP production release';

    public function handle(): int
    {
        $checks = [
            'production_environment' => app()->environment('production'),
            'debug_disabled' => config('app.debug') === false,
            'https_application_url' => str_starts_with((string) config('app.url'), 'https://'),
            'application_key_configured' => is_string(config('app.key')) && strlen((string) config('app.key')) >= 32,
            'secure_session_cookie' => config('session.secure') === true,
            'session_store_not_ephemeral' => ! in_array(config('session.driver'), ['array', 'null'], true),
            'mysql_production_database' => in_array(config('database.default'), ['mysql', 'mariadb'], true),
            'verified_outbound_mailer' => in_array(config('mail.default'), ['smtp', 'ses', 'postmark', 'resend', 'mailgun'], true),
        ];

        foreach ([
            'erp.platform.dashboard',
            'erp.platform.companies.create',
            'erp.dashboard',
            'erp.attendance.check_in',
            'erp.payroll',
            'erp.payroll.advance',
            'erp.claims.receipt',
            'erp.policies.vote',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $checks['mfa_guard_'.str_replace('.', '_', $routeName)]
                = $route !== null && in_array('mfa.verified', $route->gatherMiddleware(), true);
        }

        $previewRoutes = collect(Route::getRoutes())->contains(
            fn ($route) => $route->uri() === 'platform/dashboard-preview'
                || ($route->uri() !== '' && str_contains($route->uri(), '/preview/'))
        );
        $checks['demo_routes_disabled'] = ! $previewRoutes;

        $failed = array_keys(array_filter($checks, fn ($ok) => ! $ok));
        $result = [
            'release_decision' => count($failed) === 0 ? 'CONFIGURATION_PRECHECK_PASSED' : 'NO_GO',
            'checks' => $checks,
            'failed' => $failed,
            'note' => 'Passing configuration checks does not validate backups, restore drills, SMTP delivery, tenant UAT or financial-policy signoff.',
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->line('RA&C ERP — Production Configuration Preflight');
            foreach ($checks as $name => $pass) {
                $this->line(($pass ? '[PASS] ' : '[FAIL] ').$name);
            }
            $this->newLine();
            $this->line('Decision: '.$result['release_decision']);
            $this->warn($result['note']);
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
