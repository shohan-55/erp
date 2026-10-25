<?php

namespace App\Support;

/**
 * Check BOTH tenant control (TXT) and correct CNAME routing.
 * This does not verify that cPanel/AutoSSL actually serves the certificate.
 */
class CustomDomainDnsVerifier
{
    public function verified(string $hostname, string $token): bool
    {
        $hostname = strtolower(rtrim($hostname, '.'));
        $target = strtolower(rtrim((string) config('erp.custom_domain_cname', 'erp.dhakawala.com'), '.'));

        $txtRecords = @dns_get_record('_dhakawala-erp.'.$hostname, DNS_TXT) ?: [];
        $cnameRecords = @dns_get_record($hostname, DNS_CNAME) ?: [];
        $expectedTxt = 'dhakawala-erp-verification='.$token;

        $txtValid = collect($txtRecords)->contains(function ($record) use ($expectedTxt) {
            $text = (string) ($record['txt'] ?? implode('', $record['entries'] ?? []));
            return hash_equals($expectedTxt, trim($text, '"'));
        });
        $cnameValid = collect($cnameRecords)->contains(fn ($record) =>
            strtolower(rtrim((string) ($record['target'] ?? ''), '.')) === $target
        );

        return $txtValid && $cnameValid;
    }
}
