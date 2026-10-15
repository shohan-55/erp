<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionPreflightGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_refuses_test_or_insecure_configuration(): void
    {
        $this->artisan('erp:release:check')
            ->expectsOutputToContain('Decision: NO_GO')
            ->assertExitCode(1);
    }

    public function test_release_preflight_rejects_malformed_and_wrong_size_encryption_keys(): void
    {
        config()->set('app.cipher', 'AES-256-CBC');

        foreach ([
            'REPLACE_WITH_YOUR_EXISTING_APP_KEY',
            'base64:%%%not-base64%%%',
            'base64:'.base64_encode(random_bytes(31)),
            'base64:'.base64_encode(random_bytes(33)),
        ] as $invalid) {
            config()->set('app.key', $invalid);

            $this->artisan('erp:release:check')
                ->expectsOutputToContain('[FAIL] application_key_configured')
                ->assertExitCode(1);
        }
    }

    public function test_release_preflight_recognizes_correct_key_length_without_disclosing_key(): void
    {
        config()->set('app.cipher', 'AES-256-CBC');
        $key = 'base64:'.base64_encode(random_bytes(32));
        config()->set('app.key', $key);

        $this->artisan('erp:release:check')
            ->expectsOutputToContain('[PASS] application_key_configured')
            ->assertExitCode(1); // CI deliberately uses non-production environment.

        config()->set('app.key', random_bytes(32));
        $this->artisan('erp:release:check')
            ->expectsOutputToContain('[PASS] application_key_configured')
            ->assertExitCode(1);

        config()->set('app.cipher', 'AES-128-CBC');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(16)));
        $this->artisan('erp:release:check')
            ->expectsOutputToContain('[PASS] application_key_configured')
            ->assertExitCode(1);
    }

    public function test_preflight_requires_revocable_encrypted_sessions_and_host_only_secure_cookies(): void
    {
        $requirements = [
            ['session.driver', 'file', 'database_session_store_required'],
            ['session.driver', 'array', 'database_session_store_required'],
            ['session.encrypt', false, 'encrypted_session_payloads'],
            ['session.http_only', false, 'http_only_session_cookie'],
            ['session.same_site', 'none', 'safe_same_site_cookie'],
            ['session.domain', '.dhakawala.com', 'host_only_session_cookie'],
            ['session.secure', false, 'secure_session_cookie'],
        ];

        foreach ($requirements as [$key, $badValue, $checkName]) {
            $previous = config($key);
            config()->set($key, $badValue);

            $this->artisan('erp:release:check')
                ->expectsOutputToContain('[FAIL] '.$checkName)
                ->assertExitCode(1);

            config()->set($key, $previous);
        }
    }

    public function test_secure_database_session_settings_pass_individual_session_guards(): void
    {
        config()->set([
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.domain' => null,
            'session.secure' => true,
        ]);

        $this->artisan('erp:release:check')
            ->expectsOutputToContain('[PASS] database_session_store_required')
            ->expectsOutputToContain('[PASS] encrypted_session_payloads')
            ->expectsOutputToContain('[PASS] http_only_session_cookie')
            ->expectsOutputToContain('[PASS] safe_same_site_cookie')
            ->expectsOutputToContain('[PASS] host_only_session_cookie')
            ->assertExitCode(1); // Non-production test context remains NO_GO.
    }

    public function test_sensitive_attendance_and_financial_routes_cannot_omit_mfa_middleware(): void
    {
        foreach ([
            'erp.platform.dashboard', 'erp.platform.companies.create',
            'erp.dashboard', 'erp.attendance.check_in',
            'erp.attendance.report', 'erp.attendance.location', 'erp.attendance.correct',
            'erp.payroll', 'erp.payroll.generate', 'erp.payroll.advance', 'erp.payroll.evidence',
            'erp.claims.receipt', 'erp.policies.vote',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains('mfa.verified', $route->gatherMiddleware(), $name);
        }
    }
}
