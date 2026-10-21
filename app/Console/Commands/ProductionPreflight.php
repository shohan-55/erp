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

        // Cover ALL commercial/financial/employee-document endpoints through
        // Phase 13, not just the original MVP routes. A route missing even
        // one guard is a release-blocking failure.
        $platformRoutes = [
            'erp.platform.dashboard','erp.platform.companies.create',
            'erp.platform.members.invite','erp.platform.partners.create',
            'erp.platform.domains.activate','erp.platform.domains.disable',
            'erp.platform.billing','erp.platform.billing.plans.create',
            'erp.platform.billing.plans.availability',
            'erp.platform.billing.company','erp.platform.billing.subscribe',
            'erp.platform.billing.invoice','erp.platform.billing.payment',
            'erp.platform.billing.payment.decide',
        ];
        $tenantRoutes = [
            'erp.dashboard','erp.attendance.check_in','erp.attendance.report',
            'erp.attendance.location','erp.attendance.correct',
            'erp.payroll','erp.payroll.generate','erp.payroll.advance',
            'erp.payroll.evidence','erp.payroll.payslip','erp.payroll.register',
            'erp.payroll.adjustments.create','erp.payroll.adjustments.void',
            'erp.claims.receipt','erp.policies.vote',
            'erp.engagements','erp.engagements.clients.create',
            'erp.engagements.create','erp.engagements.assign',
            'erp.timesheets','erp.timesheets.submit','erp.timesheets.decide',
            'erp.timesheets.export','erp.reports.practice',
            'erp.reports.practice.export','erp.notices.publish',
            'erp.documents','erp.documents.upload','erp.documents.download',
            'erp.documents.archive','erp.subscription',
        ];
        foreach ([
            'platform' => [$platformRoutes, ['platform.host','auth','platform.admin','mfa.verified']],
            'tenant' => [$tenantRoutes, ['tenant.host','auth','tenant.member','mfa.verified','student.features']],
        ] as $group => [$names, $required]) {
            foreach ($names as $routeName) {
                $route = Route::getRoutes()->getByName($routeName);
                $middleware = $route?->gatherMiddleware() ?? [];
                foreach ($required as $guard) {
                    $checks['guard_'.str_replace('.', '_', $routeName).'_'.str_replace('.', '_', $guard)]
                        = $route !== null && in_array($guard, $middleware, true);
                }
            }
        }
        $privateRoot = realpath(storage_path('app/private'));
        $publicRoot = realpath(public_path());
        $checks['private_files_outside_public_webroot'] = $privateRoot !== false
            && $publicRoot !== false
            && ! str_starts_with($privateRoot.DIRECTORY_SEPARATOR, $publicRoot.DIRECTORY_SEPARATOR);
        $checks['local_disk_matches_private_storage'] = rtrim(
            (string)config('filesystems.disks.local.root'), DIRECTORY_SEPARATOR
        ) === rtrim(storage_path('app/private'), DIRECTORY_SEPARATOR);

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
            $this->line('DhakaWala ERP — Production Configuration Preflight');
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
