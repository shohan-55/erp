<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseReadinessCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_database_check_passes_with_migrated_schema(): void
    {
        $before = DB::table('users')->count();

        $this->artisan('erp:db:check')
            ->expectsOutputToContain('[PASS] connection_reachable')
            ->expectsOutputToContain('[PASS] companies_table_present')
            ->expectsOutputToContain('[PASS] conveyance_table_present')
            ->expectsOutputToContain('Decision: SCHEMA_READY')
            ->assertExitCode(0);

        $this->assertSame($before, DB::table('users')->count());
    }

    public function test_readiness_json_does_not_contain_connection_credentials(): void
    {
        config()->set('database.connections.sqlite.password', 'SECRET-NOT-FOR-OUTPUT');

        $this->artisan('erp:db:check --json')
            ->expectsOutputToContain('"decision": "SCHEMA_READY"')
            ->assertExitCode(0);
    }
}
