<?php

namespace App\Support;

use InvalidArgumentException;

final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        return self::encode(random_bytes(20));
    }

    public static function encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $character) {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    public static function decode(string $base32): string
    {
        $text = strtoupper(preg_replace('/[\s-]/', '', $base32));
        if ($text === '' || preg_match('/[^A-Z2-7]/', $text)) {
            throw new InvalidArgumentException('Invalid authenticator secret.');
        }

        $bits = '';
        foreach (str_split($text) as $character) {
            $position = strpos(self::ALPHABET, $character);
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }

    public static function code(string $secret, int $step): string
    {
        $key = self::decode($secret);
        $binary = pack('N2', intdiv($step, 4294967296), $step % 4294967296);
        $digest = hash_hmac('sha1', $binary, $key, true);
        $offset = ord(substr($digest, -1)) & 0x0f;
        $number = (unpack('N', substr($digest, $offset, 4))[1] & 0x7fffffff) % 1000000;
        return str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    /** Returns accepted 30-second counter (not a boolean) to reject replay. */
    public static function matchingStep(string $secret, string $code, ?int $lastStep = null, ?int $timestamp = null): ?int
    {
        if (! preg_match('/^[0-9]{6}$/D', $code)) {
            return null;
        }

        $now = intdiv($timestamp ?? time(), 30);
        for ($step = $now - 1; $step <= $now + 1; $step++) {
            if ($step >= 0 && ($lastStep === null || $step > $lastStep)
                && hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public static function provisioningUri(string $secret, string $email): string
    {
        return 'otpauth://totp/'.rawurlencode('DhakaWala ERP:'.$email)
            .'?secret='.rawurlencode($secret)
            .'&issuer='.rawurlencode('DhakaWala ERP')
            .'&algorithm=SHA1&digits=6&period=30';
    }
}
