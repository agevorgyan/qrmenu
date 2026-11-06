# Phase 0: Risk Register
**Enterprise Loyalty & Digital Wallet System**
**Risk Identification, Mitigation & Contingency Strategy**

---

## 1. Risk Matrix Overview

| Risk ID | Category | Risk Description | Severity | Probability | Mitigation Strategy |
|---|---|---|---|---|---|
| **RSK-01** | Security / Tenant Isolation | Customer account collision or cross-tenant point earning/redemption. | Critical | Low | Scoped database schema with `vendor_id` on all entities; `BelongsToVendor` trait and `TenantScope`; composite unique keys. |
| **RSK-02** | Financial / Fraud | Double-spend of loyalty points or cashback via concurrent HTTP requests. | High | Medium | Pessimistic row locking (`lockForUpdate()`) inside atomic database transactions; atomic balance checks before debit. |
| **RSK-03** | Financial / Fraud | Refund gaming: Customer earns points, redeems rewards, then requests an order refund. | High | Medium | Automatic deduction of earned points upon refund; if balance drops below zero, point ledger records negative balance or flags customer for staff review. |
| **RSK-04** | Security | Exposure of Apple Developer PKPass certificates or Google Service Account private keys. | Critical | Low | Encrypted storage via `VendorCredential` with AES-256-GCM; exclusion from JSON serialization and logs. |
| **RSK-05** | Operational | Apple Developer certificate expiration leading to broken pass generation or APNs push rejection. | Medium | High | Daily certificate expiration monitoring command; automated notification to vendor admin 30/14/7 days prior to expiry. |
| **RSK-06** | Privacy / Security | Customer PII (Phone/Email) exposure inside QR codes scanned at POS or tables. | High | Low | Customer QR codes use cryptographic ephemeral tokens (`lq_...`) with 10-minute validity; zero plain text PII in barcode payloads. |
| **RSK-07** | Commercial | Vendor on Basic subscription accessing Pro/Advanced loyalty features (Apple Wallet, Cashback, Referrals). | High | Medium | Server-side validation via `EntitlementService::hasFeature()` on all loyalty controllers and actions; UI locks and feature gates. |
| **RSK-08** | Performance | Large volume of expiring point lots causing database timeouts during daily cleanup. | Medium | Medium | Chunked batch processing with cursor pagination in `ExpireLoyaltyPointsJob` running during off-peak hours. |
| **RSK-09** | Integration | PassKit Web Service endpoint abuse or unauthorized pass download. | High | Low | Authentication token validation (`Authorization: ApplePass <token>`) matching `wallet_passes.authentication_token`. |
| **RSK-10** | Accounting | Drift between customer cached balance and transaction ledger. | High | Low | Balances are derived from and synchronized with immutable ledger transactions; periodic reconciliation command (`loyalty:reconcile`). |

---

## 2. Deep Dive: Mitigations for High-Risk Scenarios

### 2.1 Double-Spending Prevention
When a redemption request is received, the ledger service executes:
```php
DB::transaction(function () use ($account, $pointsToRedeem) {
    $lockedAccount = LoyaltyPointsAccount::where('id', $account->id)->lockForUpdate()->firstOrFail();
    if ($lockedAccount->current_balance < $pointsToRedeem) {
        throw new InsufficientPointsException("Insufficient balance.");
    }
    // Deduct via FIFO lots and write immutable ledger transaction
    ...
});
```

### 2.2 Proportional Refund Reversal
When an order is refunded (partially or fully):
1. The original `earned` loyalty transaction for that order is retrieved.
2. The refund ratio is calculated: `$refundRatio = $refundAmount / $order->total_amount`.
3. Points to reverse: `ceil($originalPoints * $refundRatio)`.
4. A `reversal` transaction is written to the ledger, and the customer's account balance is adjusted.
5. If stamps were awarded for this order, the associated stamp card transaction is marked reversed and stamps decremented.

---

## 3. Monitoring & Alerting

- **Audit Logging**: Every staff-initiated adjustment, manual redemption, and reversal writes to `loyalty_audit_logs`.
- **APNs & Google Wallet Telemetry**: Delivery failures are logged in `wallet_events` and surfaced in the admin Wallet Settings dashboard.
- **Ledger Invariant Checks**: Automated health check asserts that `SUM(points)` from `loyalty_ledger_transactions` matches `current_balance` for every active account.
