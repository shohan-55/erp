<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes the oldest precise browser locations after each Partner's retention
 * period. Attendance sessions and audit metadata remain for payroll controls.
 */
class PruneAttendanceLocations extends Command
{
    protected $signature = 'erp:attendance:prune-locations {--dry-run : Report eligible rows without deleting}';

    protected $description = 'Remove expired encrypted GPS readings from tenant attendance records';

    public function handle(): int
    {
        $eligible = 0;
        $deleted = 0;

        DB::table('workspaces')
            ->select('id', 'company_id', 'attendance_location_retention_days')
            ->orderBy('id')
            ->chunkById(100, function ($workspaces) use (&$eligible, &$deleted): void {
                foreach ($workspaces as $workspace) {
                    $days = min(90, max(7, (int) $workspace->attendance_location_retention_days));
                    $query = DB::table('attendance_locations')
                        ->where('company_id', $workspace->company_id)
                        ->where('workspace_id', $workspace->id)
                        ->where('captured_at', '<', now()->subDays($days));

                    if ($this->option('dry-run')) {
                        $eligible += $query->count();
                    } else {
                        $deleted += $query->delete();
                    }
                }
            });

        $this->line($this->option('dry-run')
            ? 'Expired encrypted location readings eligible for deletion: '.$eligible
            : 'Expired encrypted location readings deleted: '.$deleted);
        $this->warn('Private database backups may retain historical coordinates under the separate off-site retention policy.');

        return self::SUCCESS;
    }
}
