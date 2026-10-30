<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class BackupDatabaseCommand extends Command
{
    /**
     * Credential columns are deliberately excluded: a backup file must not be
     * a ready-made password dump. A restore resets these values instead.
     */
    protected const SECRET_COLUMNS = ['password', 'remember_token', 'invitation_token'];

    protected $signature = 'erp:backup {--keep=14 : Number of days of backups to retain}';

    protected $description = 'Write a JSON snapshot of every tenant table to storage/app/private/backups/db and prune old snapshots';

    public function handle(): int
    {
        $tables = [
            'firms',
            'users',
            'partner_policies',
            'practice_holidays',
            'attendances',
            'leave_requests',
            'conveyance_claims',
            'payroll_records',
            'practice_notifications',
            'policy_proposals',
            'audit_clients',
            'audit_logs',
        ];

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'application'  => config('app.name'),
            'environment'  => app()->environment(),
            'tables'       => [],
        ];

        try {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $rows = DB::table($table)->get()->map(function ($row) use ($table) {
                    $row = (array) $row;

                    if ($table === 'users') {
                        foreach (self::SECRET_COLUMNS as $column) {
                            if (array_key_exists($column, $row)) {
                                $row[$column] = null;
                            }
                        }
                    }

                    return $row;
                })->all();

                $payload['tables'][$table] = $rows;
            }
        } catch (\Throwable $e) {
            Log::error('erp:backup failed while reading tables: ' . $e->getMessage());
            $this->error('Backup failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $path = 'backups/db/erp-' . now()->format('Y_m_d_His') . '.json';
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false || ! Storage::disk('local')->put($path, $json)) {
            Log::error('erp:backup could not write ' . $path);
            $this->error('Backup could not be written to ' . $path);

            return self::FAILURE;
        }

        $removed = $this->pruneOldBackups((int) $this->option('keep'));

        $this->info("Backup written: storage/app/private/{$path} (" . count($payload['tables']) . ' tables)');
        if ($removed > 0) {
            $this->line("Pruned {$removed} backup(s) older than {$this->option('keep')} days.");
        }

        return self::SUCCESS;
    }

    protected function pruneOldBackups(int $keepDays): int
    {
        $keepDays = max(1, $keepDays);
        $threshold = now()->subDays($keepDays)->getTimestamp();
        $removed = 0;

        try {
            foreach (Storage::disk('local')->files('backups/db') as $file) {
                if (Storage::disk('local')->lastModified($file) < $threshold) {
                    Storage::disk('local')->delete($file);
                    $removed++;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('erp:backup prune skipped: ' . $e->getMessage());
        }

        return $removed;
    }
}
