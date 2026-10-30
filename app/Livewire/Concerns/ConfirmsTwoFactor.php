<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Support\TwoFactor;
use Illuminate\Support\Facades\Auth;

/**
 * Optional TOTP second factor for sign-in components (audit P1-3.2).
 *
 * The flow is deliberately the boring one, because a mistake here locks every
 * operator out of the platform:
 *
 *   1. password is verified exactly as before (Auth::attempt);
 *   2. if the account has no confirmed secret, nothing changes at all;
 *   3. if it does, login is undone immediately and the code field appears;
 *   4. the next submit re-checks the password plus the code (or a recovery
 *      code), and only then is the session kept.
 *
 * Accounts without a secret therefore behave exactly as they did before this
 * trait existed.
 */
trait ConfirmsTwoFactor
{
    public string $twoFactorCode = '';

    public bool $requiresTwoFactor = false;

    public ?string $twoFactorError = null;

    /**
     * Called right after a successful Auth::attempt().
     *
     * @return bool true when the session may be kept.
     */
    protected function twoFactorGate(User $user): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            $this->requiresTwoFactor = false;

            return true;
        }

        $submitted = trim($this->twoFactorCode);

        if ($submitted === '') {
            $this->logoutPending();
            $this->twoFactorError = 'Enter the 6-digit code from your authenticator app to finish signing in.';

            return false;
        }

        $secret = (string) $user->two_factor_secret;

        if (TwoFactor::verify($secret, $submitted) || $this->consumeRecoveryCode($user, $submitted)) {
            $this->requiresTwoFactor = false;
            $this->twoFactorCode = '';
            $this->twoFactorError = null;

            return true;
        }

        $this->logoutPending();
        $this->twoFactorCode = '';
        $this->twoFactorError = 'That authentication code is not valid. Codes rotate every 30 seconds — try the current one.';

        return false;
    }

    /**
     * Undo the half-finished login and ask for the code.
     */
    protected function logoutPending(): void
    {
        $this->requiresTwoFactor = true;

        try {
            // Only drop the authentication. Invalidating the whole session here
            // would also bin the CSRF token that the pending Livewire component
            // needs for the follow-up submit (the user would get a 419).
            Auth::logout();
        } catch (\Throwable) {
        }
    }

    /**
     * Single-use recovery codes, consumed on success.
     */
    protected function consumeRecoveryCode(User $user, string $submitted): bool
    {
        $codes = $user->two_factor_recovery_codes;

        if (! is_array($codes) || $codes === []) {
            return false;
        }

        // Users retype these from paper with or without the dashes, and in
        // whatever case their keyboard produced — normalise both sides.
        $needle = preg_replace('/[^A-Z0-9]/', '', strtoupper($submitted)) ?? '';

        if ($needle === '') {
            return false;
        }

        $remaining = [];
        $matched = false;

        foreach ($codes as $code) {
            $candidate = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $code)) ?? '';

            if (! $matched && $candidate !== '' && hash_equals($candidate, $needle)) {
                $matched = true;
                continue;
            }
            $remaining[] = $code;
        }

        if (! $matched) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();

        return true;
    }
}
