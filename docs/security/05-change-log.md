# QR Menu SaaS — Security Remediation Change Log

**Document ID:** `05-change-log`  
**Date:** 2026-11-10  
**Repository:** https://github.com/agevorgyan/qrmenu  
**Branch:** `security/fix-composer-advisories`  
**Target:** `main`  
**Author:** Senior Security & DevSecOps Engineer

---

## Changed Files Inventory

### 1. `composer.lock`
- **Category:** Dependency Management / Security Remediation
- **Reason:** Upgraded vulnerable Symfony packages (`symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing`, `symfony/yaml`) from `v8.1.6`/`v8.1.7` to `v8.1.11` to resolve 7 security advisories (CVE-2026-118514, GHSA-3h27-hjv7-fqjx, GHSA-xg2q-4rrq-657q, GHSA-5mx8-mwrp-5693, CVE-2026-123339, CVE-2026-118516, CVE-2026-123792).
- **Secondary Updates:** Updated minimal transitive dependencies (`symfony/deprecation-contracts` to `v3.8.1`, `symfony/error-handler` to `v8.1.10`, `symfony/event-dispatcher-contracts` to `v3.8.1`, `symfony/var-dumper` to `v8.1.10`, `symfony/polyfill-php85` to `v1.43.0`).

### 2. `.github/workflows/ci.yml`
- **Category:** CI/CD Security Hardening
- **Reason:** Updated `composer audit` to `composer audit --locked` to strictly validate the committed lockfile against the Composer security advisory database on every push and pull request.

### 3. `app/Http/Middleware/IdentifyTenant.php`
- **Category:** Multi-Tenant Security & Isolation
- **Reason:** Explicitly cleared `TenantContext` before calling `Auth::logout()` when an authenticated user attempts to access an unauthorized tenant's custom domain. This guarantees that `SecurityAuditService::logLogout` records the event under the user's actual tenant without violating `BelongsToVendor` cross-tenant creation rules.

### 4. `app/Mail/SubscriptionDowngradeScheduledMail.php` & `app/Mail/SubscriptionDowngradedMail.php`
- **Category:** Code Style & CI Enforcement
- **Reason:** Formatted via Laravel Pint (added missing trailing blank line at EOF) to ensure `vendor/bin/pint --test` passes cleanly in CI.

### 5. `lang/en.json`, `lang/hy.json`, `lang/ru.json`
- **Category:** Parity & Test Suite Stability
- **Reason:** Synchronized missing UI and subscription keys across English, Armenian, and Russian locales so that `MultilingualUiUxAuditTest` passes with complete key parity.

### 6. `tests/Feature/Security/SymfonyVulnerabilityRemediationRegressionTest.php`
- **Category:** Automated Testing & QA
- **Reason:** Added 15 comprehensive security regression tests covering:
  - HEAD requests against admin and superadmin firewalls.
  - Path manipulation with script names (`/index.php/admin/*`) and traversal sequences.
  - Trusted proxies handling IPv4-mapped IPv6 addresses.
  - Malformed and conflicting forwarded header resilience.
  - Host header poisoning protection for URL generation.
  - Custom domain path injection prevention and cross-tenant session invalidation.
  - YAML parser resource consumption, deep nesting, and error handling.

### 7. Security Documentation Suite
- `docs/security/01-security-baseline.md`: Initial dependency inventory and vulnerability assessment.
- `docs/security/02-remediation-report.md`: Detailed root causes, remediations, and verification evidence for all 7 advisories.
- `docs/security/03-security-regression-tests.md`: Test specification and results for newly added regression tests.
- `docs/security/04-final-security-report.md`: Comprehensive final status report, scorecard, and production deployment recommendations.
- `docs/security/05-change-log.md`: Detailed file-by-file log of all changes made during the remediation lifecycle.

