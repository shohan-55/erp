<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Firm;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Warn about subscriptions that have lapsed or are about to lapse.
 *
 * Audit P1-3.4 / P2: billing was a static ledger — nothing ever looked at
 * `subscription_expires_at`, so a firm lost access the moment the new
 * workspace gate kicked in, with no warning to anybody beforehand.
 */
class SubscriptionExpiryReminder extends Command
{
    protected $signature = 'erp:subscription-expiry-reminder {--days=7 : Warn about firm renewals within this many days}';

    protected $description = 'Log and email a renewal reminder for firms whose subscription has expired or is about to';

    public function handle(): int
    {
        if (! Schema::hasTable('firms')) {
            $this->warn('firms table is missing; nothing to do.');

            return self::SUCCESS;
        }

        $window = max(1, (int) $this->option('days'));

        try {
            $firms = Firm::query()
                ->whereNotNull('subscription_expires_at')
                ->whereDate('subscription_expires_at', '<=', now()->addDays($window)->toDateString())
                ->where(function ($q) {
                    $q->where('status', 'active')->orWhereNull('status');
                })
                ->get();
        } catch (\Throwable $e) {
            Log::error('erp:subscription-expiry-reminder query failed: ' . $e->getMessage());
            $this->error('Query failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($firms->isEmpty()) {
            $this->info('No subscriptions expiring within ' . $window . ' day(s).');

            return self::SUCCESS;
        }

        foreach ($firms as $firm) {
            $expired = $firm->isSubscriptionExpired();
            $expiresOn = optional($firm->subscription_expires_at)->format('d M Y') ?? 'unknown';

            $this->line(sprintf(
                '%s  %-28s %s  %s',
                $expired ? 'EXPIRED ' : 'DUE SOON',
                $firm->name,
                $expiresOn,
                $firm->email ?: '(no contact email)'
            ));

            $this->recordAudit($firm, $expired, $expiresOn);
            $this->sendReminder($firm, $expired, $expiresOn);
        }

        $this->info('Checked ' . $firms->count() . ' firm(s).');

        return self::SUCCESS;
    }

    protected function recordAudit(Firm $firm, bool $expired, string $expiresOn): void
    {
        try {
            if (! Schema::hasTable('audit_logs')) {
                return;
            }

            AuditLog::create([
                'firm_id'    => $firm->id,
                'actor_id'   => null,
                'actor_name' => 'Scheduler',
                'actor_role' => 'system',
                'module'     => 'platform',
                'action'     => $expired ? 'subscription_expired' : 'subscription_due_soon',
                'target_ref' => (string) $firm->slug,
                'summary'    => ($expired ? 'Subscription expired on ' : 'Subscription expires on ')
                    . $expiresOn . ' for ' . $firm->name . '.',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Subscription audit log failed: ' . $e->getMessage());
        }
    }

    protected function sendReminder(Firm $firm, bool $expired, string $expiresOn): void
    {
        $recipient = $firm->email;

        if (! $recipient || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $subject = $expired
                ? 'Your DhakaWala ERP workspace has expired'
                : 'Your DhakaWala ERP subscription is due on ' . $expiresOn;

            $body = $expired
                ? "The subscription for {$firm->name} expired on {$expiresOn}. The workspace is now read-only until the renewal is recorded."
                : "The subscription for {$firm->name} will expire on {$expiresOn}. Please renew it to avoid a service interruption.";

            Mail::raw(
                $body . "\n\n— DhakaWala ERP, erp.dhakawala.com",
                function ($message) use ($recipient, $subject) {
                    $message->to($recipient)->subject($subject);
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Subscription reminder email failed for ' . $recipient . ': ' . $e->getMessage());
        }
    }
}
