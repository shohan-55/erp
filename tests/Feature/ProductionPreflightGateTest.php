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
