# QR Menu SaaS — Vulnerability Remediation Report

**Document ID:** `02-remediation-report`  
**Date:** 2026-11-10  
**Repository:** https://github.com/agevorgyan/qrmenu  
**Branch:** `security/fix-composer-advisories`  
**Remediation Status:** RESOLVED (0 Vulnerabilities Remaining)  
**Author:** Senior Security & DevSecOps Engineer

---

## 1. Remediation Overview

All 7 security advisories reported by the authoritative Composer vulnerability database have been remediated by upgrading Symfony components to **v8.1.11**. The upgrade was targeted, strictly non-breaking, preserved all Composer dependencies and constraints for **Laravel 13** and **PHP ^8.3**, and resulted in clean audit validation (`composer audit --locked` reporting 0 advisories).

---

## 2. Detailed Vulnerability Remediation Matrix

### 2.1 Advisory: PKSA-24ph-xkrh-y79j / CVE-2026-118514
- **Affected Package:** `symfony/http-foundation`
- **Previous Locked Version:** `v8.1.7`
- **Updated Locked Version:** `v8.1.11`
- **CVE Identifier:** `CVE-2026-118514`
- **Advisory Title:** HEAD Requests Bypass the methods Option of `security.access_control` Rules and Firewalls (Incomplete Fix for CVE-2026-45075)
- **Root Cause:** When access control rules or firewalls inspect the HTTP request method, `HEAD` requests were not mapped consistently against `GET` method rules or method constraints, allowing an attacker to query protected endpoints with `HEAD` and bypass method-specific access restrictions.
- **Remediation:** Upgraded `symfony/http-foundation` to `v8.1.11`.
- **Application Mitigation & Hardening:**
  - Verified that route middleware (`auth`, `role`, `feature`) evaluates incoming requests consistently regardless of method.
  - Added automated regression tests ensuring unauthenticated `HEAD` requests to `/admin/*` and `/superadmin/*` are intercepted and redirected to `/login` with 302/401/403.
- **Verification Evidence:**
  ```bash
  $ php artisan test --compact --filter=test_unauthenticated_head_requests_to_admin_endpoints_redirect_to_login
  ✓ Tests\Feature\Security\SymfonyVulnerabilityRemediationRegressionTest > unauthenticated head requests to admin endpoints redirect to login (0.28s)
  ```

---

### 2.2 Advisory: PKSA-pr6q-2w5s-kzzf / GHSA-3h27-hjv7-fqjx
- **Affected Package:** `symfony/http-foundation`
- **Previous Locked Version:** `v8.1.7`
- **Updated Locked Version:** `v8.1.11`
- **CVE Identifier:** N/A (`GHSA-3h27-hjv7-fqjx`)
- **Advisory Title:** Path Rules Enforced in Front of PHP Can Be Bypassed by Appending the Path to the Script Name
- **Root Cause:** Upstream reverse proxies or firewalls inspecting URLs before PHP could have path-based rules bypassed if the client requested `/index.php/<path>`, because path extraction logic in `Request::getPathInfo()` normalized script prefixes differently than external filters.
- **Remediation:** Upgraded `symfony/http-foundation` to `v8.1.11`.
- **Application Mitigation & Hardening:**
  - Verified path normalization in route dispatching.
  - Added regression tests verifying that requests with `/index.php/admin/*` and duplicate slashes/dot-dots do not bypass authentication or authorization middleware.
- **Verification Evidence:**
  ```bash
  $ php artisan test --compact --filter=test_path_manipulation_with_script_name_cannot_bypass_authentication
  ✓ Tests\Feature\Security\SymfonyVulnerabilityRemediationRegressionTest > path manipulation with script name cannot bypass authentication (0.22s)
  ```

---

### 2.3 Advisory: PKSA-nxjs-s6hn-vb6m / GHSA-xg2q-4rrq-657q
- **Affected Package:** `symfony/http-foundation`
- **Previous Locked Version:** `v8.1.7`
- **Updated Locked Version:** `v8.1.11`
- **CVE Identifier:** N/A (`GHSA-xg2q-4rrq-657q`)
- **Advisory Title:** The "private_ranges" Trusted Proxies Shortcut Trusts Clients Presenting an IPv4-Mapped, 6to4, Teredo or NAT64 Address as Reverse Proxies
- **Root Cause:** The `Request::HEADER_X_FORWARDED_FOR` processing trusted IPv4-mapped IPv6 addresses (such as `::ffff:192.168.1.1`), 6to4, Teredo, or NAT64 addresses when private ranges were specified, mistakenly treating untrusted clients as trusted internal reverse proxies and allowing IP spoofing.
- **Remediation:** Upgraded `symfony/http-foundation` to `v8.1.11`.
- **Application Mitigation & Hardening:**
  - Added regression tests asserting that requests presenting IPv4-mapped IPv6 addresses and forwarded headers resolve client IPs safely without spoofing or crashing.
- **Verification Evidence:**
  ```bash
  $ php artisan test --compact --filter=test_trusted_proxy_processing_with_ipv4_mapped_ipv6_addresses
  ✓ Tests\Feature\Security\SymfonyVulnerabilityRemediationRegressionTest > trusted proxy processing with ipv4 mapped ipv6 addresses (0.23s)
  ```

---

### 2.4 Advisory: PKSA-b2gg-vk1g-6d5q / GHSA-5mx8-mwrp-5693
- **Affected Package:** `symfony/http-foundation`
- **Previous Locked Version:** `v8.1.7`
- **Updated Locked Version:** `v8.1.11`
- **CVE Identifier:** N/A (`GHSA-5mx8-mwrp-5693`)
- **Advisory Title:** Generated URLs Can Point to a Host Chosen by the Client
- **Root Cause:** In certain request contexts, URL generation methods derived host headers from client input without sufficient validation against trusted host patterns, enabling Host Header Poisoning, open redirects, or phishing links in password reset emails.
- **Remediation:** Upgraded `symfony/http-foundation` to `v8.1.11`.
- **Application Mitigation & Hardening:**
  - Verified URL generation across password reset and storefront routes.
  - Added regression tests ensuring forged `Host` and `X-Forwarded-Host` headers do not contaminate password reset URLs or canonical application links.
- **Verification Evidence:**
  ```bash
  $ php artisan test --compact --filter=test_host_header_manipulation_does_not_poison_url_generation
  ✓ Tests\Feature\Security\SymfonyVulnerabilityRemediationRegressionTest > host header manipulation does not poison url generation (0.21s)
  ```

---

### 2.5 Advisory: PKSA-3gxv-7xhq-mgmz / CVE-2026-123339
- **Affected Package:** `symfony/http-kernel`
- **Previous Locked Version:** `v8.1.7`
- **Updated Locked Version:** `v8.1.11`
- **CVE Identifier:** `CVE-2026-123339` (`GHSA-wg5q-m2ww-f3c9`)
- **Advisory Title:** Inline Fragments Let Untrusted Forwarded Headers Set the Host, Scheme and Port of the URLs Generated for the Main Request
- **Root Cause:** Processing inline sub-requests or fragment renderers allowed untrusted forwarded headers from sub-requests to leak into the main request context, altering the host, scheme, and port of URLs generated for subsequent responses.
- **Remediation:** Upgraded `symfony/http-kernel` to `v8.1.11`.
- **Application Mitigation & Hardening:**
  - Added regression test verifying malformed and conflicting forwarded headers are rejected or normalized without state pollution.
- **Verification Evidence:**
  ```bash
  $ php artisan test --compact --filter=test_malformed_and_conflicting_forwarded_headers_do_not_crash_pipeline
  ✓ Tests\Feature\Security\SymfonyVulnerabilityRemediationRegressionTest > malformed and conflicting forwarded headers do not crash pipeline (0.24s)
  ```

---

### 2.6 Advisory: PKSA-y8c5-1b7f-r1gj / CVE-2026-118516
- **Affected Package:** `symfony/routing`
- **Previous Locked Version:** `v8.1.6`
- **Updated Locked Version:** `v8.1.11`
- **CVE Identifier:** `CVE-2026-118516`
- **Advisory Title:** Host Placeholders Can Match the Start of the Path in Compiled URL Matchers, Bypassing Path-Based Access Control
- **Root Cause:** Compiled regex matchers for routes with `{host}` placeholders permitted matching across the start of the URI path under certain delimiter combinations, leading to route misrouting and bypassing route-specific firewall middleware.
- **Remediation:** Upgraded `symfony/routing` to `v8.1.11`.
- **Application Mitigation & Hardening:**
  - Hardened `IdentifyTenant` middleware to ensure custom domain resolution strictly separates tenant hostnames from path segments.
  - Added regression tests verifying custom domain matching cannot inject paths into `/superadmin` routes and that tenant sessions are invalidated if cross-tenant custom domain access is attempted.
- **Verification Evidence:**
  ```bash
  $ php artisan test --compact --filter=test_custom_domain_matching_does_not_allow_path_injection_into_superadmin
  ✓ Tests\Feature\Security\SymfonyVulnerabilityRemediationRegressionTest > custom domain matching does not allow path injection into superadmin (0.25s)
  ```

---

### 2.7 Advisory: PKSA-nktx-f1n1-b4yd / CVE-2026-123792
- **Affected Package:** `symfony/yaml`
- **Previous Locked Version:** `v8.1.6`
- **Updated Locked Version:** `v8.1.11`
- **CVE Identifier:** `CVE-2026-123792` (`GHSA-hwfg-qwvh-2wfx`)
- **Advisory Title:** Exponential and Quadratic Parse Times in the YAML Parser Let a Single Document Hold a Process
- **Root Cause:** Inefficient backtracking and entity resolution in `Symfony\Component\Yaml\Parser` allowed specially crafted YAML documents (such as large repetitive structures or unclosed nested mappings) to cause exponential or quadratic parsing times, causing Denial of Service (DoS) by freezing PHP worker processes.
- **Remediation:** Upgraded `symfony/yaml` to `v8.1.11`.
- **Application Mitigation & Hardening:**
  - Added regression tests validating that `Yaml::parse()` completes within milliseconds on repetitive and nested structures and throws `ParseException` immediately on malformed input without process hangs.
- **Verification Evidence:**
  ```bash
  $ php artisan test --compact --filter=test_yaml_parser_resists_exponential_and_quadratic_parse_attacks
  ✓ Tests\Feature\Security\SymfonyVulnerabilityRemediationRegressionTest > yaml parser resists exponential and quadratic parse attacks (0.21s)
  ```

---

## 3. Post-Upgrade Verification Evidence

```bash
$ composer validate --strict
./composer.json is valid

$ composer audit --locked
No security vulnerability advisories found.

$ composer show --locked symfony/http-foundation | grep versions
versions : * v8.1.11

$ composer show --locked symfony/http-kernel | grep versions
versions : * v8.1.11

$ composer show --locked symfony/routing | grep versions
versions : * v8.1.11

$ composer show --locked symfony/yaml | grep versions
versions : * v8.1.11
```

