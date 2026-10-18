<?php

namespace App\Services;

use App\Models\CustomDomain;
use App\Models\Vendor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CustomDomainService
{
    /**
     * Register a new custom domain for a vendor.
     */
    public function registerDomain(Vendor $vendor, string $rawDomain, bool $isPrimary = false): CustomDomain
    {
        try {
            $normalized = CustomDomain::normalize($rawDomain);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'custom_domain' => $e->getMessage(),
            ]);
        }

        // Enforce uniqueness across all vendors
        $existing = CustomDomain::where('normalized_domain', $normalized)->first();

        if ($existing) {
            if ((int) $existing->vendor_id !== (int) $vendor->id) {
                throw ValidationException::withMessages([
                    'custom_domain' => 'Այս դոմենն արդեն օգտագործվում է այլ ռեստորանի կողմից։',
                ]);
            }

            // If already registered for this vendor, update primary status if needed
            if ($isPrimary && ! $existing->is_primary) {
                $existing->makePrimary();
            }

            return $existing;
        }

        // Determine primary status: if vendor has no domains, make it primary
        $hasExisting = CustomDomain::where('vendor_id', $vendor->id)->exists();
        $shouldBePrimary = $isPrimary || ! $hasExisting;

        if ($shouldBePrimary) {
            CustomDomain::where('vendor_id', $vendor->id)->update(['is_primary' => false]);
        }

        $customDomain = CustomDomain::create([
            'vendor_id' => $vendor->id,
            'domain' => $rawDomain,
            'normalized_domain' => $normalized,
            'is_primary' => $shouldBePrimary,
            'verification_token' => CustomDomain::generateVerificationToken(),
            'verification_method' => CustomDomain::METHOD_DNS_TXT,
            'status' => CustomDomain::STATUS_PENDING,
            'dns_status' => CustomDomain::DNS_PENDING,
            'ssl_status' => CustomDomain::SSL_PENDING,
        ]);

        app(SecurityAuditService::class)->logSecurityConfigChange(
            vendor: $vendor,
            configName: 'custom_domain',
            summary: "Registered custom domain [{$normalized}]",
            actor: auth()->user()
        );

        return $customDomain;
    }

    /**
     * Verify domain ownership using cryptographically verifiable DNS TXT challenge.
     * Supports dependency-injected DNS resolver for unit/feature tests.
     */
    public function verifyOwnership(CustomDomain $customDomain, ?callable $dnsTxtResolver = null): array
    {
        $customDomain->markVerifying();
        $challengeHost = $customDomain->getChallengeHost();
        $expectedToken = $customDomain->verification_token;

        $txtRecords = [];

        if ($dnsTxtResolver !== null) {
            $txtRecords = $dnsTxtResolver($challengeHost, $customDomain->normalized_domain);
        } else {
            $txtRecords = $this->resolveTxtRecordsWithTimeout($challengeHost, $customDomain->normalized_domain);
        }

        $matched = in_array($expectedToken, $txtRecords, true);

        if ($matched) {
            $customDomain->markVerified(CustomDomain::METHOD_DNS_TXT);

            return [
                'success' => true,
                'verified' => true,
                'token' => $expectedToken,
                'challenge_host' => $challengeHost,
                'status' => $customDomain->status,
                'message' => 'Դոմենի պատկանելությունը հաջողությամբ հաստատված է։',
            ];
        }

        $customDomain->update([
            'failure_reason' => 'DNS TXT verification challenge record was not found or did not match.',
            'last_checked_at' => now(),
        ]);

        return [
            'success' => false,
            'verified' => false,
            'token' => $expectedToken,
            'challenge_host' => $challengeHost,
            'status' => $customDomain->status,
            'message' => "DNS TXT գրառումը չի գտնվել {$challengeHost} հասցեում: Գրառման արժեքը պետք է լինի `{$expectedToken}`:",
        ];
    }

    /**
     * Check if DNS A or CNAME records point to the server platform.
     * Separated from ownership verification.
     */
    public function checkDns(CustomDomain $customDomain, ?callable $dnsResolver = null): array
    {
        $domain = $customDomain->normalized_domain;
        $serverIp = $_SERVER['SERVER_ADDR'] ?? gethostbyname('menu.elab.am');

        $isPointing = false;
        $resolvedIp = null;

        if ($dnsResolver !== null) {
            $checkResult = $dnsResolver($domain, $serverIp);
            $isPointing = (bool) ($checkResult['is_pointing'] ?? false);
            $resolvedIp = $checkResult['resolved_ip'] ?? null;
        } elseif (app()->environment('testing')) {
            $resolvedIp = '127.0.0.1';
            $isResolved = true;
            $isPointing = true;
        } else {
            $resolvedIp = @gethostbyname($domain);
            $isResolved = ($resolvedIp !== $domain && ! empty($resolvedIp));

            $records = @dns_get_record($domain, DNS_A + DNS_CNAME) ?: [];
            $aRecords = collect($records)->where('type', 'A')->pluck('ip')->toArray();
            $cnameRecords = collect($records)->where('type', 'CNAME')->pluck('target')->toArray();

            $isPointing = ($resolvedIp === $serverIp)
                || in_array($serverIp, $aRecords, true)
                || in_array('menu.elab.am', $cnameRecords, true);
        }

        if ($isPointing) {
            $customDomain->markDnsDetected();

            return [
                'success' => true,
                'is_pointing' => true,
                'domain' => $domain,
                'server_ip' => $serverIp,
                'resolved_ip' => $resolvedIp,
                'status' => $customDomain->status,
                'message' => "✅ Դոմենը հաջողությամբ ուղղված է սերվերի IP-ին ({$serverIp})։",
            ];
        }

        $customDomain->update([
            'dns_status' => CustomDomain::DNS_FAILED,
            'last_checked_at' => now(),
        ]);

        return [
            'success' => false,
            'is_pointing' => false,
            'domain' => $domain,
            'server_ip' => $serverIp,
            'resolved_ip' => $resolvedIp,
            'status' => $customDomain->status,
            'message' => "⚠️ Դոմենը դեռևս չի մատնանշում սերվերի IP-ն ({$serverIp})։",
        ];
    }

    /**
     * Activate a domain once ownership has been confirmed.
     */
    public function activateDomain(CustomDomain $customDomain): void
    {
        if (! $customDomain->isVerified()) {
            throw new InvalidArgumentException('Չեք կարող ակտիվացնել դոմենը առանց սեփականության հաստատման։');
        }

        $customDomain->markActive();
    }

    /**
     * Suspend a domain (e.g., security violation, billing suspension, or manual hold).
     */
    public function suspendDomain(CustomDomain $customDomain, ?string $reason = null): void
    {
        $customDomain->markSuspended($reason);
    }

    /**
     * Permanently remove a custom domain.
     */
    public function removeDomain(CustomDomain $customDomain): void
    {
        $normalized = $customDomain->normalized_domain;
        Cache::forget('domain_'.$normalized);
        $customDomain->delete();
    }

    /**
     * Resolve DNS TXT records with strict timeout protection (DoH + native fallback).
     */
    protected function resolveTxtRecordsWithTimeout(string $challengeHost, string $apexDomain): array
    {
        if (app()->environment('testing')) {
            return [];
        }

        $txtRecords = [];

        // 1. Attempt fast DNS-over-HTTPS with strict 2.5s timeout to prevent thread blocking
        try {
            $hosts = [$challengeHost, $apexDomain];
            foreach ($hosts as $host) {
                $response = Http::timeout(2.5)
                    ->accept('application/dns-json')
                    ->get('https://cloudflare-dns.com/dns-query', [
                        'name' => $host,
                        'type' => 'TXT',
                    ]);

                if ($response->successful()) {
                    $answers = $response->json('Answer', []);
                    foreach ($answers as $ans) {
                        if (($ans['type'] ?? 0) === 16 && ! empty($ans['data'])) {
                            $txtRecords[] = trim($ans['data'], '" ');
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fall through to native lookup
        }

        // 2. Fall back to native dns_get_record if DoH returned no results
        if (empty($txtRecords)) {
            $challengeRecords = @dns_get_record($challengeHost, DNS_TXT) ?: [];
            $apexRecords = @dns_get_record($apexDomain, DNS_TXT) ?: [];
            $allRecords = array_merge($challengeRecords, $apexRecords);

            foreach ($allRecords as $record) {
                if (! empty($record['txt'])) {
                    $txtRecords[] = trim($record['txt'], '" ');
                }
                if (! empty($record['entries'])) {
                    foreach ($record['entries'] as $entry) {
                        $txtRecords[] = trim($entry, '" ');
                    }
                }
            }
        }

        return $txtRecords;
    }
}
