# QR Menu SaaS — Security Regression Test Documentation

**Document ID:** `03-security-regression-tests`  
**Date:** 2026-11-10  
**Repository:** https://github.com/agevorgyan/qrmenu  
**Test Suite File:** [`tests/Feature/Security/SymfonyVulnerabilityRemediationRegressionTest.php`](file:///Users/apple/Projects/qrmenu/tests/Feature/Security/SymfonyVulnerabilityRemediationRegressionTest.php)  
**Status:** ALL 15 TESTS PASSING (Exit Code 0)  
**Author:** QA Automation & Security Engineer

---

## 1. Test Suite Purpose & Coverage

This regression test suite was created to provide automated verification that the vulnerabilities remediated in Symfony v8.1.11:
1. Cannot be exploited at the application layer.
2. Do not regress authentication, authorization, or multi-tenant isolation.
3. Will be continually guarded by the CI/CD pipeline.

---

## 2. Test Cases and Verification Results

| # | Test Method Name | Vulnerability Class Tested | Expected Behavior | Actual Result |
|---|---|---|---|---|
| 1 | `test_unauthenticated_head_requests_to_admin_endpoints_redirect_to_login` | CVE-2026-118514 (HEAD Access Control) | Unauthenticated HEAD requests to `/admin/dashboard` return 302 redirect to login. | **PASSED** (0.28s) |
| 2 | `test_unauthenticated_head_requests_to_superadmin_endpoints_redirect_to_login` | CVE-2026-118514 (HEAD Access Control) | Unauthenticated HEAD requests to `/superadmin/dashboard` return 302 redirect to login. | **PASSED** (0.23s) |
| 3 | `test_tenant_staff_cannot_bypass_superadmin_firewall_via_head_request` | CVE-2026-118514 (Role-Based Firewall) | Tenant staff user sending HEAD request to `/superadmin/dashboard` is denied (403/302). | **PASSED** (0.23s) |
| 4 | `test_public_storefront_menu_handles_head_request_consistently` | CVE-2026-118514 (Storefront Methods) | HEAD request to public `/m/{vendor_slug}` returns 200 with empty body and identical status headers. | **PASSED** (0.23s) |
| 5 | `test_post_only_mutation_routes_reject_head_requests` | HTTP Method Integrity | HEAD requests to POST-only action routes (`/api/m/{slug}/call-waiter`) are rejected (405/404). | **PASSED** (0.22s) |
| 6 | `test_path_manipulation_with_script_name_cannot_bypass_authentication` | GHSA-3h27-hjv7-fqjx (Script Name Traversal) | Requests with `/index.php/admin/*` and `/index.php/superadmin/*` cannot bypass authentication. | **PASSED** (0.22s) |
| 7 | `test_duplicate_slashes_and_dot_dot_sequences_do_not_escape_access_controls` | Path Normalization / Traversal | URIs with `//admin//dashboard` and `/admin/../admin/dashboard` remain strictly protected. | **PASSED** (0.23s) |
| 8 | `test_trusted_proxy_processing_with_ipv4_mapped_ipv6_addresses` | GHSA-xg2q-4rrq-657q (Proxy Spoofing) | Client connecting with `::ffff:192.168.1.1` and `X-Forwarded-For` is processed cleanly and safely. | **PASSED** (0.23s) |
| 9 | `test_malformed_and_conflicting_forwarded_headers_do_not_crash_pipeline` | CVE-2026-123339 (Forwarded Headers) | Requests with corrupted or conflicting forwarded headers do not trigger 500 errors. | **PASSED** (0.24s) |
| 10 | `test_host_header_manipulation_does_not_poison_url_generation` | GHSA-5mx8-mwrp-5693 (Host Header Poisoning) | Manipulating `Host` or `X-Forwarded-Host` during password reset flows does not inject attacker domain into links. | **PASSED** (0.21s) |
| 11 | `test_custom_domain_matching_does_not_allow_path_injection_into_superadmin` | CVE-2026-118516 (Host Placeholders & Paths) | Custom domain routing for `/superadmin` is redirected away to platform domain and blocked. | **PASSED** (0.25s) |
| 12 | `test_cross_tenant_custom_domain_isolation_with_authenticated_session` | Multi-Tenant Session Security | Cross-tenant user authenticated on Vendor B accessing Vendor A custom domain is immediately invalidated and logged out. | **PASSED** (0.24s) |
| 13 | `test_yaml_parser_handles_malformed_input_safely_with_parse_exception` | CVE-2026-123792 (YAML Parser Exceptions) | Malformed YAML syntax throws `Symfony\Component\Yaml\Exception\ParseException` gracefully without fatal error. | **PASSED** (0.21s) |
| 14 | `test_yaml_parser_resists_exponential_and_quadratic_parse_attacks` | CVE-2026-123792 (Exponential Parse DoS) | Anchor expansion vectors parse in under 1.0s without quadratic explosion or process hang. | **PASSED** (0.21s) |
| 15 | `test_yaml_parser_handles_deep_nesting_safely` | CVE-2026-123792 (Nesting Limits) | Deeply nested mappings (40+ levels) parse safely in under 0.5s. | **PASSED** (0.21s) |

---

## 3. Test Execution Command & Terminal Output

```bash
$ php artisan test --compact tests/Feature/Security/SymfonyVulnerabilityRemediationRegressionTest.php

   PASS  Tests\Feature\Security\SymfonyVulnerabilityRemediationRegressionTest
  ✓ unauthenticated head requests to admin endpoints redirect to login (0.28s)
  ✓ unauthenticated head requests to superadmin endpoints redirect to login (0.23s)
  ✓ tenant staff cannot bypass superadmin firewall via head request (0.23s)
  ✓ public storefront menu handles head request consistently (0.23s)
  ✓ post only mutation routes reject head requests (0.22s)
  ✓ path manipulation with script name cannot bypass authentication (0.22s)
  ✓ duplicate slashes and dot dot sequences do not escape access controls (0.23s)
  ✓ trusted proxy processing with ipv4 mapped ipv6 addresses (0.23s)
  ✓ malformed and conflicting forwarded headers do not crash pipeline (0.24s)
  ✓ host header manipulation does not poison url generation (0.21s)
  ✓ custom domain matching does not allow path injection into superadmin (0.25s)
  ✓ cross tenant custom domain isolation with authenticated session (0.24s)
  ✓ yaml parser handles malformed input safely with parse exception (0.21s)
  ✓ yaml parser resists exponential and quadratic parse attacks (0.21s)
  ✓ yaml parser handles deep nesting safely (0.21s)

  Tests:    15 passed (31 assertions)
  Duration: 5.71s
```

---

## 4. Full Security Feature Suite Execution

In addition to the new targeted regression test file, all 92 pre-existing and expanded security tests were executed:

```bash
$ php artisan test --compact tests/Feature/Security/
{"tool":"phpunit","result":"passed","tests":92,"passed":92,"assertions":273,"duration_ms":15933}
```
All 92 tests passed with 0 failures, 0 errors, and 0 warnings.

