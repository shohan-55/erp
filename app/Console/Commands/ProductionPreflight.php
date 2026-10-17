<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Route;

class ProductionPreflight extends Command
{
    protected $signature = 'erp:release:check {--json : Machine-readable release check output}';
    protected $description = 'Check fail-closed security and deployment configuration before ERP production release';

    /**
     * Reject placeholders, malformed base64 and encryption keys with the
     * wrong byte length. This check never echoes or logs the secret key.
     *
     * Laravel supports raw 16/32-byte keys and base64:-encoded keys, with the
     * expected length determined by APP_CIPHER.
     */
    private static function applicationKeyValid(): bool
    {
        $configured = config('app.key');
        $cipher = config('app.cipher');

        if (! is_string($configured) || $configured === '' || ! is_string($cipher)) {
            return false;
        }

        $key = str_starts_with($configured, 'base64:')
            ? base64_decode(substr($configured, 7), true)
            : $configured;

        return is_string($key) && Encrypter::supported($key, $cipher);
    }

    public function handle(): int
    {
        $checks = [
            'production_environment' => app()->environment('production'),
            'debug_disabled' => config('app.debug') === false,
            'https_application_url' => str_starts_with((string) config('app.url'), 'https://'),
            'application_key_configured' => self::applicationKeyValid(),
            'secure_session_cookie' => config('session.secure') === true,
            // Account recovery and MFA deactivation revoke sessions through
            // the database-backed sessions table. File/Redis sessions would
            // otherwise remain active after these sensitive operations.
            'database_session_store_required' => config('session.driver') === 'database',
            // MFA enrollment holds a pending TOTP secret in the session.
            // Encrypt all persisted session payloads at rest.
            'encrypted_session_payloads' => config('session.encrypt') === true,
            'http_only_session_cookie' => config('session.http_only') === true,
            'safe_same_site_cookie' => in_array(config('session.same_site'), ['lax', 'strict'], true),
            // Host-only cookies avoid sharing ERP login state with other
            // unrelated sites hosted under the DhakaWala parent domain.
            'host_only_session_cookie' => in_array(config('session.domain'), [null, ''], true),
            'mysql_production_database' => in_array(config('database.default'), ['mysql', 'mariadb'], true),
            'verified_outbound_mailer' => in_array(config('mail.default'), ['smtp', 'ses', 'postmark', 'resend', 'mailgun'], true),
        ];

        foreach ([
            'erp.platform.dashboard',
            'erp.platform.companies.create',
            'erp.dashboard',
            'erp.attendance.check_in',
            'erp.attendance.report',
            'erp.attendance.location',
            'erp.attendance.correct',
            'erp.payroll',
            'erp.payroll.generate',
            'erp.payroll.advance',
            'erp.payroll.evidence',
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
