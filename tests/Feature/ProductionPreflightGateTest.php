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

    public function test_privileged_financial_routes_cannot_omit_mfa_middleware(): void
    {
        foreach ([
            'erp.platform.dashboard', 'erp.platform.companies.create',
            'erp.dashboard', 'erp.attendance.check_in',
            'erp.payroll', 'erp.payroll.advance', 'erp.payroll.evidence',
            'erp.claims.receipt', 'erp.policies.vote',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains('mfa.verified', $route->gatherMiddleware(), $name);
        }
    }
}
