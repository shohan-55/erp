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
    protected const SECRET_COLUMNS = ['password', 'remember_token', 'invitation_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected $signature = 'erp:backup {--keep=14 : Number of days of backups to retain} {--disk= : Storage disk override}';

    protected $description = 'Write a chunk-streamed JSON snapshot of every tenant table to backups/db and prune old snapshots';

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

        $disk = (string) ($this->option('disk') ?: config('erp.backup_disk', 'local'));
        $tempFile = tempnam(sys_get_temp_dir(), 'erp_backup_');
        if ($tempFile === false) {
            $this->error('Could not allocate temporary file for backup stream.');
            return self::FAILURE;
        }

        $fh = fopen($tempFile, 'wb');
        if ($fh === false) {
            @unlink($tempFile);
            $this->error('Could not open temporary backup stream.');
            return self::FAILURE;
        }

        $exportedTables = 0;

        try {
            fwrite($fh, "{\n");
            fwrite($fh, '  "generated_at": ' . json_encode(now()->toIso8601String()) . ",\n");
            fwrite($fh, '  "application": ' . json_encode(config('app.name')) . ",\n");
            fwrite($fh, '  "environment": ' . json_encode(app()->environment()) . ",\n");
            fwrite($fh, "  \"tables\": {\n");

            $presentTables = array_values(array_filter($tables, fn (string $t) => Schema::hasTable($t)));
            $tableCount = count($presentTables);

            foreach ($presentTables as $tIdx => $table) {
                fwrite($fh, '    ' . json_encode($table) . ": [");
                $firstRow = true;

                $query = DB::table($table);
                $sanitizeRow = function ($row) use ($table): array {
                    $arr = (array) $row;
                    if ($table === 'users') {
                        foreach (self::SECRET_COLUMNS as $column) {
                            if (array_key_exists($column, $arr)) {
                                $arr[$column] = null;
                            }
                        }
                    }
                    return $arr;
                };

                if (Schema::hasColumn($table, 'id')) {
                    $query->orderBy('id')->chunk(500, function ($rows) use ($fh, &$firstRow, $sanitizeRow) {
                        foreach ($rows as $row) {
                            fwrite($fh, ($firstRow ? "\n      " : ",\n      ") . json_encode($sanitizeRow($row), JSON_UNESCAPED_UNICODE));
                            $firstRow = false;
                        }
                    });
                } else {
                    foreach ($query->cursor() as $row) {
                        fwrite($fh, ($firstRow ? "\n      " : ",\n      ") . json_encode($sanitizeRow($row), JSON_UNESCAPED_UNICODE));
                        $firstRow = false;
                    }
                }

                $comma = ($tIdx < $tableCount - 1) ? ',' : '';
                fwrite($fh, ($firstRow ? "]" : "\n    ]") . $comma . "\n");
                $exportedTables++;
            }

            fwrite($fh, "  }\n}\n");
            fclose($fh);
            $fh = null;
        } catch (\Throwable $e) {
            if (is_resource($fh)) {
                fclose($fh);
            }
            @unlink($tempFile);
            Log::error('erp:backup failed while streaming tables: ' . $e->getMessage());
            $this->error('Backup failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $path = 'backups/db/erp-' . now()->format('Y_m_d_His') . '.json';
        $contents = file_get_contents($tempFile);
        @unlink($tempFile);

        if ($contents === false || ! Storage::disk($disk)->put($path, $contents)) {
            Log::error('erp:backup could not write ' . $path);
            $this->error('Backup could not be written to ' . $path);

            return self::FAILURE;
        }

        $removed = $this->pruneOldBackups($disk, (int) $this->option('keep'));

        $this->info("Backup written: {$path} ({$exportedTables} tables)");
        if ($removed > 0) {
            $this->line("Pruned {$removed} backup(s) older than {$this->option('keep')} days.");
        }

        return self::SUCCESS;
    }

    protected function pruneOldBackups(string $disk, int $keepDays): int
    {
        $keepDays = max(1, $keepDays);
        $threshold = now()->subDays($keepDays)->getTimestamp();
        $removed = 0;

        try {
            foreach (Storage::disk($disk)->files('backups/db') as $file) {
                if (Storage::disk($disk)->lastModified($file) < $threshold) {
                    Storage::disk($disk)->delete($file);
                    $removed++;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('erp:backup prune skipped: ' . $e->getMessage());
        }

        return $removed;
    }
}
