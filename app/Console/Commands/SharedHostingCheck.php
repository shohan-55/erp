<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SharedHostingCheck extends Command
{
    protected $signature = 'erp:hosting:check {--json : Output a secret-free machine-readable report}';

    protected $description = 'Check Laravel CLI and cPanel shared hosting prerequisites without revealing environment secrets';

    public function handle(): int
    {
        $required = [
            'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash',
            'mbstring', 'openssl', 'pcre', 'pdo', 'pdo_mysql',
            'session', 'tokenizer', 'xml',
        ];

        $checks = [
            'php_8_3_or_newer' => PHP_VERSION_ID >= 80300,
            'cli_php' => PHP_SAPI === 'cli',
        ];

        foreach ($required as $extension) {
            $checks['extension_'.$extension] = extension_loaded($extension);
        }

        foreach ([
            'storage_writable' => storage_path(),
            'bootstrap_cache_writable' => base_path('bootstrap/cache'),
        ] as $name => $path) {
            $checks[$name] = is_dir($path) && is_writable($path);
        }

        // A built release must ship its frontend assets; shared hosting need
        // not install Node or npm at all.
        $checks['vite_manifest_present'] = is_file(public_path('build/manifest.json'));

        $warnings = [];
        if (config('queue.default') !== 'sync') {
            $warnings[] = 'Queue is not sync. A persistent worker or tested cPanel cron queue drain is required.';
        }
        if (config('session.driver') === 'array') {
            $warnings[] = 'Array sessions are ephemeral; use database or file sessions for production.';
        }
        if (! extension_loaded('zip')) {
            $warnings[] = 'ZIP extension is absent. Install vendor dependencies off-server and deploy the tested vendor directory.';
        }

        $warnings[] = 'PHP web/FPM runtime may differ from CLI. Verify cPanel MultiPHP Manager uses the same supported version.';
        $warnings[] = 'cPanel Cron Jobs, SSL, SMTP delivery, database access, document root, account isolation and backups need separate real-server verification.';

        $failures = array_keys(array_filter($checks, fn (bool $passed) => ! $passed));
        $result = [
            'decision' => $failures === [] ? 'HOSTING_CLI_PREREQUISITES_PASSED' : 'HOSTING_NOT_READY',
            'php_cli_version' => PHP_VERSION,
            'checks' => $checks,
            'failed' => $failures,
            'warnings' => $warnings,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            $this->info('RA&C ERP — Shared Hosting Environment Check');
            $this->line('CLI PHP: '.PHP_VERSION);
            foreach ($checks as $name => $passed) {
                $this->line(($passed ? '[PASS] ' : '[FAIL] ').$name);
            }
            foreach ($warnings as $warning) {
                $this->warn('[CHECK MANUALLY] '.$warning);
            }
            $this->line('Decision: '.$result['decision']);
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }
}
