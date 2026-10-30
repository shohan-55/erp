<?php

namespace App\Support;

/**
 * Minimal RFC 6238 (TOTP) implementation — no external dependency.
 *
 * Audit P1-3.2: the highest-value accounts in the system (a Partner who can
 * run payroll, and the platform operator who can delete a tenant) were
 * protected by a password alone.
 *
 * This class only does the maths: secret generation, code verification and the
 * otpauth:// URI that Google Authenticator / Authy / 1Password scan. It is
 * verified against the RFC 6238 reference vectors in the test suite.
 */
class TwoFactor
{
    /** Digits in a generated code. Six is what every authenticator app expects. */
    public const DIGITS = 6;

    /** Seconds per code — RFC 6238 default time step. */
    public const PERIOD = 30;

    /**
     * Generate a new base32 secret (160 bits, the RFC 4226 recommendation).
     */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /**
     * otpauth:// URI for an authenticator app to scan.
     */
    public static function provisioningUri(string $secret, string $accountName, string $issuer = 'DhakaWala ERP'): string
    {
        $label = rawurlencode($issuer . ':' . $accountName);

        $query = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/' . $label . '?' . $query;
    }

    /**
     * Verify a submitted code, allowing one period of clock drift either way.
     */
    public static function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        // Fail closed on anything that is not a usable secret: a corrupt or
        // truncated value must never verify, not even against "000000".
        if (strlen($code) !== self::DIGITS
            || $secret === ''
            || self::base32Decode($secret) === '') {
            return false;
        }

        $timestamp ??= time();
        $current = intdiv($timestamp, self::PERIOD);

        foreach ([-1, 0, 1] as $offset) {
            if (hash_equals(self::codeForCounter($secret, $current + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The code for a specific time step (exposed for tests).
     */
    public static function codeForCounter(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);

        if ($key === '') {
            return '';
        }

        // 8-byte big-endian counter, per RFC 4226 §5.3.
        // On a 32-bit PHP build (wasm, some shared hosts) $counter already fits
        // in one word, and 2^32 is a float, so the split is only computed where
        // the platform can actually represent it.
        $high = 0;
        $low = $counter;

        if (PHP_INT_SIZE >= 8 && $counter > 0xFFFFFFFF) {
            $high = intdiv($counter, 0x100000000);
            $low = $counter - ($high * 0x100000000);
        }

        $binary = pack('N2', $high, $low);

        $hash = hash_hmac('sha1', $binary, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $part = substr($hash, $offset, 4);
        $value = unpack('N', $part)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Recover the current code for a secret (used by the enrolment command so
     * an operator can sanity-check their app before confirming).
     */
    public static function currentCode(string $secret, ?int $timestamp = null): string
    {
        return self::codeForCounter($secret, intdiv($timestamp ?? time(), self::PERIOD));
    }

    // ─── base32 (RFC 4648, no padding) ──────────────────────────────────────

    protected const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function base32Encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $out .= self::ALPHABET[bindec($chunk)];
        }

        return $out;
    }

    public static function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $encoded) ?? '');

        if ($encoded === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($encoded) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                continue;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }

    /**
     * Human-friendly grouping for manual entry: "ABCD EFGH IJKL MNOP".
     */
    public static function formatSecret(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    /**
     * Ten single-use recovery codes ("ABCD-EFGH-JKLM").
     *
     * Deliberately keeps no framework dependency: this class must stay
     * runnable in isolation so the RFC vectors can be checked without
     * booting Laravel. The alphabet drops I, O, 0 and 1 so a code read off
     * paper cannot be mistyped, and 12 characters still carry 60 bits.
     *
     * @return array<int, string>
     */
    public static function recoveryCodes(int $count = 10): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $raw = '';

            for ($j = 0; $j < 12; $j++) {
                $raw .= $alphabet[random_int(0, $max)];
            }

            $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
        }

        return $codes;
    }
}
