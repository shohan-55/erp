<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\PracticeNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Escalate check-ins that were never closed.
 *
 * Audit P1-3.4: a student who forgot to check out stayed "inside" forever, so
 * payroll counted the day as complete and the Partner had no signal that
 * something was wrong.
 *
 * The command does not invent an out-time — that would falsify a legal
 * attendance record. It flags the row for correction and notifies the student
 * and their manager once, which is what the missed-check-out workflow already
 * expects.
 */
class CloseMissedCheckouts extends Command
{
    protected $signature = 'erp:flag-missed-checkouts {--days=7 : How many days back to sweep}';

    protected $description = 'Flag attendance rows with a check-in but no check-out and notify the student and their manager';

    public function handle(): int
    {
        if (! Schema::hasTable('attendances')) {
            $this->warn('attendances table is missing; nothing to do.');

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $from = now()->subDays($days)->toDateString();

        $hasCorrectionFlag = Schema::hasColumn('attendances', 'correction_requested');

        try {
            $query = Attendance::query()
                ->whereNotNull('in_time')
                ->whereNull('out_time')
                ->whereDate('date', '<', now()->toDateString())
                ->whereDate('date', '>=', $from);

            if ($hasCorrectionFlag) {
                $query->where(function ($q) {
                    $q->where('correction_requested', false)->orWhereNull('correction_requested');
                });
            }

            $rows = $query->limit(500)->get();
        } catch (\Throwable $e) {
            Log::error('erp:flag-missed-checkouts query failed: ' . $e->getMessage());
            $this->error('Query failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $flagged = 0;

        foreach ($rows as $row) {
            try {
                if ($hasCorrectionFlag) {
                    $row->correction_requested = true;
                    $row->saveQuietly();
                }

                $this->notify($row, $row->user_id, 'student');
                $this->notify($row, $this->managerIdFor($row->user_id), 'manager');

                $flagged++;
            } catch (\Throwable $e) {
                Log::warning('Missed check-out flag failed for attendance ' . $row->id . ': ' . $e->getMessage());
            }
        }

        $this->info("Flagged {$flagged} missed check-out(s) in the last {$days} day(s).");

        return self::SUCCESS;
    }

    protected function managerIdFor(?int $userId): ?int
    {
        if (! $userId || ! Schema::hasTable('users')) {
            return null;
        }

        try {
            return \App\Models\User::whereKey($userId)->value('manager_id');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function notify(Attendance $row, ?int $userId, string $role): void
    {
        if (! $userId || ! Schema::hasTable('practice_notifications')) {
            return;
        }

        PracticeNotification::create([
            'firm_id'     => $row->firm_id,
            'user_id'     => $userId,
            'target_role' => $role,
            'icon'        => 'clock-alert',
            'title'       => 'Missed Check-out — ' . \Illuminate\Support\Carbon::parse($row->date)->format('d M Y'),
            'message'     => 'A check-in was recorded with no check-out for '
                . \Illuminate\Support\Carbon::parse($row->date)->format('d M Y')
                . '. Please submit a correction request with the actual departure time.',
            'is_read'     => false,
        ]);
    }
}
