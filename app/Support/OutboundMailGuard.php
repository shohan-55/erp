<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class OutboundMailGuard
{
    public static function ensureConfigured(): void
    {
        // Never write password reset and invitation bearer tokens into production
        // application logs or allow silently dropped test-only mail transports.
        if (! app()->environment('production')) {
            return;
        }

        $mailer = config('mail.default');

        if (! is_string($mailer) || in_array($mailer, ['log', 'array', 'null'], true)
            || ! in_array($mailer, ['smtp', 'ses', 'postmark', 'resend', 'mailgun'], true)) {
            throw ValidationException::withMessages([
                'email' => 'Email delivery is unavailable. Please contact the platform administrator.',
            ]);
        }

        if (config('app.debug')) {
            throw new \RuntimeException('Never send account activation or reset tokens while APP_DEBUG is enabled in production.');
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            throw new \RuntimeException('A trusted HTTPS APP_URL is required before sending account links.');
        }
    }
}