# QR Menu SaaS — Composer Security Baseline & Vulnerability Assessment

**Document ID:** `01-security-baseline`  
**Date:** 2026-11-10  
**Repository:** https://github.com/agevorgyan/qrmenu  
**Branch:** `security/fix-composer-advisories` (branched from `main` @ `b503373`)  
**PHP Version:** PHP 8.5.10 (cli) (built: Aug 25 2026 21:09:32) / Target requirement: `^8.3`  
**Composer Version:** 2.10.3  
**Framework:** Laravel Framework 13.32.0 (`^13.17`)  
**Author:** Senior Security & DevSecOps Engineer

---

## 1. Executive Summary

A comprehensive automated and manual security audit of all direct and transitive dependencies was conducted for the **elab.menu / QR Menu SaaS** platform. 

The audit identified **7 security vulnerability advisories** affecting **4 packages**:
- `symfony/http-foundation` (v8.1.7 locked) — 4 advisories
- `symfony/http-kernel` (v8.1.7 locked) — 1 advisory
- `symfony/routing` (v8.1.6 locked) — 1 advisory
- `symfony/yaml` (v8.1.6 locked) — 1 advisory

All 7 advisories are resolved in Symfony versions **>= 8.1.11** (as well as LTS/backport branches such as 5.4.56, 6.4.50, 7.4.23).

---

## 2. Environment & Dependency Inventory

### 2.1 System Runtime & Tooling
```bash
$ php -v
PHP 8.5.10 (cli) (built: Aug 25 2026 21:09:32) (NTS)
Copyright (c) The PHP Group
Built by Homebrew
Zend Engine v4.5.10, Copyright (c) Zend Technologies
    with Zend OPcache v8.5.10, Copyright (c), by Zend Technologies

$ composer --version
Composer version 2.10.3 2026-08-27 13:34:23

$ composer validate --strict
./composer.json is valid
```

### 2.2 Locked Versions of Affected Components
| Component | Current Locked Version | Direct or Transitive | Dependency Path(s) |
|---|---|---|---|
| `symfony/http-foundation` | `v8.1.7` | Transitive | `laravel/framework` (`^7.4.0 \|\| ^8.0.0`), `fruitcake/php-cors` (`^5.4\|^6.4\|^7.3\|^8`), `laravel/reverb` (`^6.3\|^7.0\|^8.0`), `symfony/http-kernel` (`^7.4\|^8.0`) |
| `symfony/http-kernel` | `v8.1.7` | Transitive | `laravel/framework` (`^7.4.0 \|\| ^8.0.0`) |
| `symfony/routing` | `v8.1.6` | Transitive | `laravel/framework` (`^7.4.0 \|\| ^8.0.0`) |
| `symfony/yaml` | `v8.1.6` | Transitive | `laravel/roster` (`^7.2\|^8.0`) via `laravel/boost` (`^2.9`) |

### 2.3 Comprehensive Symfony Package State (Locked)
- `symfony/clock`: `8.1.0`
- `symfony/console`: `8.1.7`
- `symfony/css-selector`: `8.1.6`
- `symfony/deprecation-contracts`: `3.7.1`
- `symfony/error-handler`: `8.1.5`
- `symfony/event-dispatcher`: `8.1.5`
- `symfony/event-dispatcher-contracts`: `3.7.1`
- `symfony/finder`: `8.1.7`
- `symfony/http-foundation`: `8.1.7` (VULNERABLE)
- `symfony/http-kernel`: `8.1.7` (VULNERABLE)
- `symfony/mailer`: `8.1.7`
- `symfony/mime`: `8.1.10`
- `symfony/polyfill-*`: `1.37.0` - `1.43.3`
- `symfony/process`: `8.1.7`
- `symfony/routing`: `8.1.6` (VULNERABLE)
- `symfony/service-contracts`: `3.7.3`
- `symfony/string`: `8.1.7`
- `symfony/translation`: `8.1.5`
- `symfony/translation-contracts`: `3.7.1`
- `symfony/uid`: `8.1.5`
- `symfony/var-dumper`: `8.1.7`
- `symfony/yaml`: `8.1.6` (VULNERABLE)

---

## 3. Vulnerability Details & Exploitability Assessment

### 3.1 Advisory 1: PKSA-24ph-xkrh-y79j / CVE-2026-118514
- **Package:** `symfony/http-foundation`
- **Severity:** High / Critical (Access Control Bypass)
- **Title:** `CVE-2026-118514: HEAD Requests Bypass the methods Option of security.access_control Rules and Firewalls: Incomplete Fix for CVE-2026-45075`
- **URL:** https://symfony.com/cve-2026-118514
- **Affected Versions:** `>=8.0.0,<8.1.0|>=8.1.0,<8.1.11` (and previous release branches)
- **Patched Version:** `>= 8.1.11`
- **Exploitability Analysis in QR Menu SaaS:**
  - HTTP `HEAD` requests are implicitly treated as `GET` in many web server configurations and frameworks, but if access control rules restrict methods (e.g., `GET`, `POST`) or firewall middleware checks `$request->getMethod()`, an attacker issuing a `HEAD` request might bypass firewall rules or state verifications.
  - In QR Menu SaaS, public routes `/m/{vendor_slug}`, `/api/m/{vendor_slug}/*`, and administrative endpoints `/admin/*` must strictly evaluate `HEAD` requests consistently with `GET` requests without bypassing `auth`, `role`, or `tenant` middleware.

### 3.2 Advisory 2: PKSA-pr6q-2w5s-kzzf / GHSA-3h27-hjv7-fqjx
- **Package:** `symfony/http-foundation`
- **Severity:** Medium / High (Path-Based Access Control Bypass)
- **Title:** `Path Rules Enforced in Front of PHP Can Be Bypassed by Appending the Path to the Script Name`
- **URL:** https://github.com/symfony/symfony/security/advisories/GHSA-3h27-hjv7-fqjx
- **Affected Versions:** `>=8.0.0,<8.1.0|>=8.1.0,<8.1.11`
- **Patched Version:** `>= 8.1.11`
- **Exploitability Analysis in QR Menu SaaS:**
  - Upstream reverse proxies or web application firewalls (e.g. Nginx, Cloudflare, Apache) may enforce path restrictions such as `/superadmin` or `/admin`. If an attacker crafts a URI appending path information after `/index.php` (e.g., `/index.php/superadmin/...` or path normalization tricks), path rules enforced in front of PHP could be circumvented while Symfony still dispatches the internal route.

### 3.3 Advisory 3: PKSA-nxjs-s6hn-vb6m / GHSA-xg2q-4rrq-657q
- **Package:** `symfony/http-foundation`
- **Severity:** Medium (Proxy Spoofing / IP Authentication Bypass)
- **Title:** `The "private_ranges" Trusted Proxies Shortcut Trusts Clients Presenting an IPv4-Mapped, 6to4, Teredo or NAT64 Address as Reverse Proxies`
- **URL:** https://github.com/symfony/symfony/security/advisories/GHSA-xg2q-4rrq-657q
- **Affected Versions:** `>=8.0.0,<8.1.0|>=8.1.0,<8.1.11`
- **Patched Version:** `>= 8.1.11`
- **Exploitability Analysis in QR Menu SaaS:**
  - When `Request::setTrustedProxies()` uses `Request::HEADER_X_FORWARDED_FOR` with private ranges, an attacker connecting over IPv6 with an IPv4-mapped IPv6 address (e.g., `::ffff:10.0.0.1`), 6to4 (`2002::`), Teredo, or NAT64 could be mistakenly recognized as a trusted internal reverse proxy. This could allow client IP spoofing, bypassing rate limiters, or forging client location headers.

### 3.4 Advisory 4: PKSA-b2gg-vk1g-6d5q / GHSA-5mx8-mwrp-5693
- **Package:** `symfony/http-foundation`
- **Severity:** Medium / High (Host Header Injection / SSRF / Open Redirect)
- **Title:** `Generated URLs Can Point to a Host Chosen by the Client`
- **URL:** https://github.com/symfony/symfony/security/advisories/GHSA-5mx8-mwrp-5693
- **Affected Versions:** `>=8.0.0,<8.1.0|>=8.1.0,<8.1.11`
- **Patched Version:** `>= 8.1.11`
- **Exploitability Analysis in QR Menu SaaS:**
  - If URL generators construct URLs using client-controlled `Host` or `X-Forwarded-Host` headers without strict validation against trusted hosts, generated links (e.g., password reset emails, tenant verification links, payment redirect URLs) could point to an attacker's domain, leading to credential theft, account takeover, or phishing.

### 3.5 Advisory 5: PKSA-3gxv-7xhq-mgmz / CVE-2026-123339 / GHSA-wg5q-m2ww-f3c9
- **Package:** `symfony/http-kernel`
- **Severity:** Medium (Host/Scheme/Port Poisoning via Untrusted Forwarded Headers)
- **Title:** `Inline Fragments Let Untrusted Forwarded Headers Set the Host, Scheme and Port of the URLs Generated for the Main Request`
- **URL:** https://github.com/symfony/symfony/security/advisories/GHSA-wg5q-m2ww-f3c9
- **Affected Versions:** `>=8.0.0,<8.1.0|>=8.1.0,<8.1.11`
- **Patched Version:** `>= 8.1.11`
- **Exploitability Analysis in QR Menu SaaS:**
  - When processing internal sub-requests or inline rendering fragments, untrusted forwarded headers could contaminate the request context of main requests, corrupting subsequent URL generation for the main request.

### 3.6 Advisory 6: PKSA-y8c5-1b7f-r1gj / CVE-2026-118516
- **Package:** `symfony/routing`
- **Severity:** High (Access Control Bypass via Host Placeholders)
- **Title:** `CVE-2026-118516: Host Placeholders Can Match the Start of the Path in Compiled URL Matchers, Bypassing Path-Based Access Control`
- **URL:** https://symfony.com/cve-2026-118516
- **Affected Versions:** `>=8.0.0,<8.1.0|>=8.1.0,<8.1.11`
- **Patched Version:** `>= 8.1.11`
- **Exploitability Analysis in QR Menu SaaS:**
  - In applications supporting custom domains or subdomains (like QR Menu SaaS, which features custom domain routing for restaurants via `IdentifyTenant` and domain routes), compiled URL matchers with host placeholders could mistakenly match part of the path, causing incorrect route resolution and bypassing route-specific middleware or access control.

### 3.7 Advisory 7: PKSA-nktx-f1n1-b4yd / CVE-2026-123792 / GHSA-hwfg-qwvh-2wfx
- **Package:** `symfony/yaml`
- **Severity:** Medium / High (Denial of Service - ReDoS / Resource Exhaustion)
- **Title:** `Exponential and Quadratic Parse Times in the YAML Parser Let a Single Document Hold a Process`
- **URL:** https://github.com/symfony/symfony/security/advisories/GHSA-hwfg-qwvh-2wfx
- **Affected Versions:** `>=8.0.0,<8.1.0|>=8.1.0,<8.1.11`
- **Patched Version:** `>= 8.1.11`
- **Exploitability Analysis in QR Menu SaaS:**
  - Parsing untrusted YAML input with deeply nested or crafted structures causes quadratic or exponential parsing times, exhausting CPU and holding PHP worker processes indefinitely (DoS).

---

## 4. Remediation Plan & Dependency Compatibility Risks

1. **Targeted Update:**
   Upgrading `symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing`, and `symfony/yaml` to the latest secure version in the 8.1 / 8.2 series (`>= 8.1.11`).
2. **Laravel 13 & PHP ^8.3 Compatibility:**
   `laravel/framework` v13.32.0 explicitly requires `^7.4.0 || ^8.0.0` for all Symfony 8 components. Upgrading within Symfony 8 is completely supported by Laravel 13 and PHP 8.5.
3. **No Unrelated Major Upgrades:**
   All Symfony components remain within the Symfony 8 major release line, ensuring zero breaking API changes.
4. **Validation:**
   Post-upgrade verification via `composer validate --strict`, `composer audit --locked`, comprehensive security regression tests, Pint code formatting, and test suite execution.

