<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\TwoFactor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Enrol or remove a TOTP second factor for one account.
 *
 * There is no self-service QR screen yet on purpose: enrolment is an
 * operator action, so a compromised password alone can never be used to
 * silently enrol an attacker's device.
 *
 *   php artisan erp:two-factor admin@dhakawala.com          # enrol
 *   php artisan erp:two-factor admin@dhakawala.com --confirm 123456
 *   php artisan erp:two-factor admin@dhakawala.com --disable
 */
class ManageTwoFactor extends Command
{
    protected $signature = 'erp:two-factor
                            {email : Account to manage}
                            {--confirm= : 6-digit code from the authenticator app, completing enrolment}
                            {--disable : Remove the second factor and its recovery codes}';

    protected $description = 'Enrol, confirm or remove TOTP two-factor authentication for an account';

    public function handle(): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'two_factor_secret')) {
            $this->error('Two-factor columns are missing. Run `php artisan migrate` first.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $this->error("No account found for {$email}.");

            return self::FAILURE;
        }

        if ($this->option('disable')) {
            $user->forceFill([
                'two_factor_secret'         => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at'   => null,
            ])->save();

            $this->info("Two-factor authentication removed for {$user->email}.");

            return self::SUCCESS;
        }

        $code = trim((string) $this->option('confirm'));

        if ($code !== '') {
            if (empty($user->two_factor_secret)) {
                $this->error('No pending secret on this account. Run the command without --confirm first.');

                return self::FAILURE;
            }

            if (! TwoFactor::verify((string) $user->two_factor_secret, $code)) {
                $this->error('That code did not match. Check the device clock and try the current code.');

                return self::FAILURE;
            }

            $codes = TwoFactor::recoveryCodes();

            $user->forceFill([
                'two_factor_confirmed_at'   => now(),
                'two_factor_recovery_codes' => $codes,
            ])->save();

            $this->info("Two-factor authentication is now ACTIVE for {$user->email}.");
            $this->newLine();
            $this->warn('Store these single-use recovery codes somewhere safe. They are shown once:');
            foreach ($codes as $recoveryCode) {
                $this->line('  ' . $recoveryCode);
            }

            return self::SUCCESS;
        }

        // Fresh enrolment: issue a secret and print the QR payload.
        $secret = TwoFactor::generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null])->save();

        $uri = TwoFactor::provisioningUri($secret, (string) $user->email);

        $this->info("Secret for {$user->email}:");
        $this->line('  ' . TwoFactor::formatSecret($secret));
        $this->newLine();
        $this->line('Add it to an authenticator app (manual entry or this URI):');
        $this->line('  ' . $uri);
        $this->newLine();
        $this->comment('The factor is NOT active until you confirm one code:');
        $this->line('  php artisan erp:two-factor ' . $user->email . ' --confirm=123456');

        return self::SUCCESS;
    }
}
