# Phase 0: Full Project Discovery & Audit Report
**Enterprise Loyalty & Digital Wallet System**
**QR Menu SaaS Platform**

---

## 1. Executive Summary

This discovery audit investigates the current QR Menu SaaS codebase to establish a baseline for integrating an enterprise-grade, multi-tenant Loyalty & Digital Wallet System (supporting Points, Cashback, Tiers, Digital Stamp Cards, Rewards, Referrals, Campaigns, Apple Wallet PassKit, and Google Wallet REST API).

The platform is a multi-tenant Laravel 13 application running on PHP 8.3/8.5 with PostgreSQL 16. It enforces strict multi-tenancy at the database and application levels using `vendor_id` scoping (`TenantScope` and `BelongsToVendor`). The existing codebase contains mature subsystems for:
- Role-based Access Control (RBAC) with canonical permissions (`PermissionCatalog`, `Permission`)
- Granular subscription entitlements and quotas (`FeatureCatalog`, `QuotaCatalog`, `EntitlementService`)
- Multi-currency, tax-compliant POS and online ordering
- Zero-dependency cryptographic QR code generation (`QrCodeService`)
- Encrypted secrets management (`VendorCredential`)
- Storage quotas and file isolation (`StorageService`)
- Multi-channel notification delivery (SMS, Email, Telegram)

The Loyalty & Digital Wallet domain will be implemented without duplicating existing models (User, Customer, Order, Vendor, Subscription, Permission) and will seamlessly integrate into the existing event-driven lifecycle and admin UI.

---

## 2. Infrastructure & Environment Audit

| Component | Current Version / Implementation | Notes & Loyalty Integration |
|---|---|---|
| **Framework** | Laravel `13.32.0` | Modern features, job batches, rate limiters, strict casts |
| **PHP Runtime** | PHP `8.3` / `8.5` compatible | Strict typing, promoted constructor properties, match expressions |
| **Database** | PostgreSQL `16` (`pgsql`) | JSONB support, row-level locking (`lockForUpdate`), transactions |
| **Testing Engine** | PHPUnit `12.5.35` / `laravel/pao` | Compact JSON execution reporting, comprehensive test suites |
| **Queue** | Database queue driver / Redis | Async jobs for PassKit pushes (APNs), Google Wallet sync, notifications |
| **Cache** | Redis / Database cache | Tenant caching, rate limiting, token TTL enforcement |
| **Code Style** | Laravel Pint `1.32.1` | PSR-12 + Laravel standard (`vendor/bin/pint`) |

---

## 3. Multi-Tenancy Architecture

### 3.1 Tenant Identity & Resolution
- **Tenant Entity**: The tenant is the `Vendor` (`vendors` table).
- **Resolution Strategy**:
  - Storefront requests: Resolved via URL slug `/m/{vendor_slug}/...` or custom domain host header (`custom_domains` table).
  - Staff / Admin requests: Resolved via authenticated user's `vendor_id` (`Auth::user()->vendor_id`).
  - SuperAdmin: Global administrative scope; tenant filtering bypassed or selected explicitly.
- **Context Holder**: `App\Services\TenantContext` maintains request-scoped tenant state.
- **Model Scoping**: `App\Models\Traits\BelongsToVendor` applies `App\Models\Scopes\TenantScope`. It prevents cross-tenant writes:
  ```php
  static::creating(function (Model $model) {
      $tenantId = app(TenantContext::class)->getTenantId();
      if ($tenantId !== null) {
          if (! empty($model->vendor_id) && (int) $model->vendor_id !== (int) $tenantId) {
              throw new \InvalidArgumentException('Cross-tenant entity creation forbidden.');
          }
          $model->vendor_id = $tenantId;
      }
  });
  ```
- **Loyalty Mandate**: All loyalty tables (`loyalty_programs`, `loyalty_points_accounts`, `loyalty_ledger_entries`, `loyalty_rewards`, `wallet_passes`, etc.) must include `vendor_id` with foreign key cascade to `vendors(id)` and implement `BelongsToVendor`.

---

## 4. Existing Customer & Identity Model

### 4.1 Existing Customer Table (`customers`)
- Attributes: `id`, `vendor_id`, `location_id`, `name`, `phone`, `email`, `birthdate`, `address`, `notes`, `marketing_opt_in`, `total_orders_count`, `total_spent`, `loyalty_points`, `loyalty_tier`, `last_order_at`, `created_at`, `updated_at`.
- Cross-tenant rule: Customers belong strictly to a single `vendor_id`. A customer can have the same phone number at Restaurant A and Restaurant B, represented as two distinct customer records with independent ledger balances.
- Synchronization: `CustomerSyncService` matches customers within a vendor by phone or email upon order submission or table reservation.

### 4.2 Legacy Loyalty Stubs
- Table `pos_customer_loyalty_transactions`: Initial POS transaction log with fields `points`, `balance_after`, `type`.
- Service `PosLoyaltyService`: Hardcoded constant conversion `SPEND_PER_POINT = 100.0`.
- **Strategy**: The new enterprise Loyalty Engine will supersede `PosLoyaltyService` with dynamic, configurable earning rules, multi-lot point ledgers, expiration, cashback, digital stamp cards, and auditability while retaining backward compatibility for POS callers.

---

## 5. Orders, Payments & Refund Lifecycle

### 5.1 Order Flow
1. **Creation**:
   - Online QR Menu: `ClientStorefrontController::submitOrder` creates `Order`, triggers `OrderCreated` event.
   - POS / Staff: `PosWorkspaceController::submitOrder` or `PosOfflineSyncController::syncBatch` handles dine-in/takeaway orders.
2. **Payment**:
   - Online: Arca, Stripe, Idram, Telcell, Telcell Wallet, FastShift, or Cash. Processed through `PaymentVerificationService` and `PaymentGatewayManager`.
   - POS: Recorded via `PosPaymentShiftService` into `pos_payments`.
3. **Completion**:
   - Order marked `completed` / `paid` -> eligible for loyalty reward points and cashback.
4. **Refund & Cancellation**:
   - `OrderController::updateStatus` handles cancellation and refund permissions.
   - `PosPaymentShiftService::refundPayment` tracks refunded amounts and sets `payment_status` to `partially_refunded` or `refunded`.
   - **Loyalty Integration**: Event listeners on `OrderStatusUpdated` and payment refunds will trigger proportional reversal of points/cashback and invalidate associated stamp awards.

---

## 6. Security, RBAC & Subscriptions

### 6.1 Role-Based Access Control
- `PermissionCatalog` defines canonical permissions.
- Relevant existing permissions: `customers.view`, `customers.manage`, `customers.export`.
- Additional loyalty permissions to be registered in `PermissionCatalog` and `Permission`:
  - `loyalty.view`: View loyalty dashboards, rules, and customer balances.
  - `loyalty.manage`: Configure loyalty rules, rewards, tiers, and wallet settings.
  - `loyalty.operate`: Staff operations (issue points, redeem rewards, stamp cards via QR scan).
  - `loyalty.adjust`: Manual balance adjustments and reversals (sensitive, manager level).

### 6.2 Entitlements & Subscription Tiers
- Plans: `Basic`, `Pro`, `Advanced` (defined in `SubscriptionPlanSeeder`).
- Existing feature keys in `FeatureCatalog`:
  - `FeatureCatalog::MARKETING_LOYALTY = 'marketing.loyalty'`
  - `FeatureCatalog::MARKETING_APPLE_WALLET = 'marketing.apple_wallet'`
  - `FeatureCatalog::MARKETING_CUSTOMERS = 'marketing.customers'`
  - `FeatureCatalog::MARKETING_SEGMENTATION = 'marketing.segmentation'`
- Entitlement checks are enforced via `EntitlementService::hasFeature($vendor, $featureCode)` and middleware `EnsurePlanHasFeature`.

---

## 7. Wallet Providers & Cryptography

### 7.1 Apple Wallet (PassKit)
- Requires signed `.pkpass` bundle containing `pass.json`, icons/logos, and `manifest.json` signed using Apple Worldwide Developer Relations (WWDR) certificate and Pass Type ID certificate (PKCS#7 / CMS).
- Requires PassKit Web Service endpoints:
  - Device registration: `POST /v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}`
  - Device unregistration: `DELETE /v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}`
  - Up-to-date passes: `GET /v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}`
  - Pass delivery: `GET /v1/passes/{passTypeIdentifier}/{serialNumber}`
  - Logging: `POST /v1/log`
- APNs push notifications for silent pass updates via HTTP/2 APNs service.
- **Hybrid Issuer Architecture**: Support platform-managed eLab certificate OR vendor-provided custom Apple Developer certificates. Private keys stored encrypted via `VendorCredential` with zero plaintext leakage.

### 7.2 Google Wallet REST API
- Requires Google Service Account with credentials (client email, private key) linked to Google Pay & Wallet Console Issuer ID.
- Generates `LoyaltyClass` and `LoyaltyObject`.
- Users add cards via signed JWT link: `https://pay.google.com/gp/v/save/{jwt}` signed with RS256 using the service account private key.
- Updates via REST API (`PATCH /walletobjects/v1/loyaltyObject/{resourceId}`).

---

## 8. Missing Infrastructure & Gaps Identified

1. **Loyalty Ledger & Lot Tracking**: Need double-entry immutable ledgers for both Points and Cashback with lot-based expiration (FIFO).
2. **Customer Portal & Customer Authentication**: Storefront has no customer login session. Need customer OTP-based authentication (phone/email) to view their card, wallet links, balance, and reward catalog.
3. **Staff Scanner & Redemption**: Staff needs a lightweight scanning interface to verify customer QR tokens, issue stamps/points, and process redemptions securely.
4. **PassKit Engine**: Need a pure, robust PKPass packager using PHP OpenSSL (no third-party cloud pass SaaS dependencies).
5. **Google Wallet Engine**: Need a self-contained JWT and REST client for Google Wallet API.
6. **Pass Visual Designer**: Admin visual editor for card background, colors, logos, and field placement for both Apple and Google formats.
7. **Idempotency & Reversal**: Need idempotency keys and transactional reversals on refund/cancellation.

---

## 9. Conclusion

The QR Menu SaaS architecture is well structured and provides all necessary architectural hooks (multi-tenant scoping, event dispatching, subscription entitlements, and encrypted credential storage). The Loyalty & Digital Wallet domain can be cleanly added under `app/Domains/Loyalty` with zero disruption to existing core services.
