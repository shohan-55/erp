<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestoreDatabaseCommand extends Command
{
    protected $signature = 'erp:restore-backup
        {file? : Backup path under storage disk (defaults to latest in backups/db)}
        {--disk= : Storage disk override}
        {--dry-run : Validate snapshot integrity and row counts without writing to the database}';

    protected $description = 'Verify or restore a JSON database snapshot produced by erp:backup';

    public function handle(): int
    {
        $disk = (string) ($this->option('disk') ?: config('erp.backup_disk', 'local'));
        $file = $this->argument('file');

        if (! $file) {
            $files = Storage::disk($disk)->files('backups/db');
            if (empty($files)) {
                $this->error('No backup snapshots found in backups/db.');
                return self::FAILURE;
            }
            usort($files, fn ($a, $b) => Storage::disk($disk)->lastModified($b) <=> Storage::disk($disk)->lastModified($a));
            $file = $files[0];
        }

        if (! Storage::disk($disk)->exists($file)) {
            $this->error("Backup file not found: {$file}");
            return self::FAILURE;
        }

        $payload = json_decode((string) Storage::disk($disk)->get($file), true);
        if (! is_array($payload) || ! isset($payload['tables']) || ! is_array($payload['tables'])) {
            $this->error("Invalid backup JSON structure in {$file}.");
            return self::FAILURE;
        }

        $totalRows = 0;
        foreach ($payload['tables'] as $table => $rows) {
            if (! is_array($rows)) {
                $this->error("Malformed rows array for table {$table}.");
                return self::FAILURE;
            }
            $totalRows += count($rows);
        }

        if ($this->option('dry-run')) {
            $this->info("Backup drill verified: {$file} (" . count($payload['tables']) . " tables, {$totalRows} total rows).");
            return self::SUCCESS;
        }

        try {
            DB::beginTransaction();
            foreach ($payload['tables'] as $table => $rows) {
                if (! Schema::hasTable($table) || empty($rows)) {
                    continue;
                }
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    if ($table === 'users' && empty($row['password'])) {
                        $row['password'] = Hash::make(Str::password(24));
                    }
                    if (isset($row['id'])) {
                        DB::table($table)->updateOrInsert(['id' => $row['id']], $row);
                    } else {
                        DB::table($table)->insert($row);
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('erp:restore-backup failed: ' . $e->getMessage());
            $this->error('Restore failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Restored {$totalRows} row(s) from {$file}.");
        return self::SUCCESS;
    }
}
