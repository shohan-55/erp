<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SendWorkspaceMilestoneReminders extends Command
{
    protected $signature = 'erp:milestone-reminders {--dry-run : Count eligible milestone reminders without saving or notifying}';
    protected $description = 'Deliver tenant-isolated, idempotent milestone inbox reminders; never email or change project/payroll state.';

    public function handle(): int
    {
        $sent = 0;
        $eligible = 0;
        // Only a Partner's explicitly enabled policy can trigger a reminder.
        DB::table('workspace_reminder_rules as r')
            ->join('companies as c', 'c.id', '=', 'r.company_id')
            ->join('workspaces as w', function ($join) {
                $join->on('w.id', '=', 'r.workspace_id')->on('w.company_id', '=', 'r.company_id');
            })
            ->where('r.rule_type', 'milestone_due')
            ->where('r.active', true)->where('c.active', true)->where('w.active', true)
            ->select('r.*', 'c.timezone')
            ->orderBy('r.id')
            ->chunkById(50, function ($rules) use (&$sent, &$eligible) {
                foreach ($rules as $rule) {
                    $today = CarbonImmutable::today($rule->timezone ?: 'Asia/Dhaka');
                    // Exclude older historical tasks, and never broadcast beyond a 30-day lookahead.
                    $oldest = $today->subDays(30)->toDateString();
                    $latest = $today->addDays((int) $rule->days_before)->toDateString();
                    DB::table('audit_engagement_milestones as m')
                        ->join('memberships as member', function ($join) {
                            $join->on('member.id', '=', 'm.membership_id')
                                ->on('member.company_id', '=', 'm.company_id')
                                ->on('member.workspace_id', '=', 'm.workspace_id');
                        })
                        ->where('m.company_id', $rule->company_id)
                        ->where('m.workspace_id', $rule->workspace_id)
                        ->whereIn('m.status', ['open', 'rejected'])
                        ->where('member.active', true)->whereNull('member.platform_suspended_at')
                        ->whereBetween('m.due_on', [$oldest, $latest])
                        ->select('m.id', 'm.membership_id', 'm.due_on')
                        ->orderBy('m.id')
                        ->chunkById(100, function ($milestones) use ($rule, $today, &$sent, &$eligible) {
                            foreach ($milestones as $item) {
                                $kind = $item->due_on < $today->toDateString() ? 'overdue' : 'upcoming';
                                $delivery = [
                                    'rule_id' => $rule->id,
                                    'milestone_id' => $item->id,
                                    'recipient_membership_id' => $item->membership_id,
                                    'due_on' => $item->due_on,
                                    'kind' => $kind,
                                    'created_at' => now(),
                                ];
                                if ($this->option('dry-run')) {
                                    $exists = DB::table('workspace_reminder_deliveries')
                                        ->where('rule_id', $rule->id)
                                        ->where('milestone_id', $item->id)
                                        ->where('due_on', $item->due_on)
                                        ->where('kind', $kind)->exists();
                                    if (! $exists) $eligible++;
                                    continue;
                                }
                                DB::transaction(function () use ($rule, $item, $kind, $delivery, &$sent) {
                                    // Unique delivery key is the concurrency guard across scheduler workers.
                                    if (DB::table('workspace_reminder_deliveries')->insertOrIgnore($delivery) !== 1) {
                                        return;
                                    }
                                    DB::table('tenant_notifications')->insert([
                                        'company_id' => $rule->company_id,
                                        'workspace_id' => $rule->workspace_id,
                                        'recipient_membership_id' => $item->membership_id,
                                        'title' => $kind === 'overdue'
                                            ? 'Engagement milestone overdue' : 'Engagement milestone deadline approaching',
                                        'message' => 'Your assigned milestone #'.$item->id
                                            .' has a due date of '.$item->due_on.'. Please review your engagement tasks.',
                                        'created_at' => now(), 'updated_at' => now(),
                                    ]);
                                    $sent++;
                                }, 3);
                            }
                        }, 'm.id', 'id');
                }
            }, 'r.id', 'id');

        $this->info($this->option('dry-run')
            ? 'Eligible unsent reminders: '.$eligible.' (dry run; nothing changed).'
            : 'Private in-app milestone reminders delivered: '.$sent.'.');
        return self::SUCCESS;
    }
}
