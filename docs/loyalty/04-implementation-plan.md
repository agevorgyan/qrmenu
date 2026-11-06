# Phase 0: Implementation Plan
**Enterprise Loyalty & Digital Wallet System**
**Execution Phases & Quality Gates**

---

## 1. Execution Overview

This implementation plan delivers a production-ready, multi-tenant Loyalty & Digital Wallet platform inside QR Menu SaaS. Work is structured into sequential phases with strict quality gates and automated verification at every step.

---

## 2. Phase Breakdown

### Phase 1: Domain Architecture & Scaffolding
- Establish `app/Domains/Loyalty/` directory hierarchy.
- Define Enums (`LoyaltyTransactionType`, `LoyaltyTierLevel`, `WalletProvider`, `WalletIssuerMode`, `CampaignTriggerType`, `StampStatus`).
- Implement Data Transfer Objects (DTOs) for calculations, redemptions, and wallet cards.
- Register new permissions in `PermissionCatalog` and canonical keys in `Permission`.

### Phase 2: Database Migrations & Eloquent Models
- Author PostgreSQL migrations covering:
  - Programs & versions (`loyalty_programs`, `loyalty_program_versions`)
  - Accounts & ledgers (`loyalty_points_accounts`, `loyalty_ledger_transactions`, `loyalty_point_lots`)
  - Cashback (`loyalty_cashback_accounts`, `loyalty_cashback_transactions`, `loyalty_cashback_lots`)
  - Rules, Tiers, Stamps (`loyalty_earning_rules`, `loyalty_tiers`, `loyalty_tier_histories`, `loyalty_stamp_programs`, `loyalty_stamp_cards`, `loyalty_stamp_transactions`)
  - Rewards & Redemptions (`loyalty_rewards`, `loyalty_reward_redemptions`)
  - Referrals & Campaigns (`loyalty_referrals`, `loyalty_campaigns`, `loyalty_campaign_events`)
  - Digital Wallets (`wallet_issuers`, `wallet_pass_designs`, `wallet_passes`, `wallet_device_registrations`)
  - Ephemeral tokens & Audits (`loyalty_customer_qr_tokens`, `loyalty_audit_logs`)
- Build Eloquent models with strict casts, relationships, and `BelongsToVendor`.
- Run migrations and verify database schema integrity in PostgreSQL.

### Phase 3: Customer Identity, Auth & Ephemeral QR Tokens
- Storefront customer authentication (OTP phone/email verification and customer session tokens).
- Account linking without cross-tenant collision.
- Ephemeral, signed QR token generation (`GenerateCustomerQrTokenAction`) with zero customer PII leakage in the QR payload.

### Phase 4: Core Loyalty Calculation Engine & Ledgers
- `PointsCalculator`: Precision calculation for fixed, percentage, category-specific, product-specific, and multiplier rules.
- `PointsLedgerService`: Double-entry FIFO lot-based debit and credit with database row-level locking.
- `CashbackService`: Independent cashback ledger.
- `TierService`: Threshold evaluation, upgrades/downgrades, multiplier calculation.
- `StampCardService`: Stamp qualification, stamp awards, completion, and card cycling.
- `RewardsService`: Catalog filtering, tier access validation, inventory decrements, and redemption verification.
- `ReferralService`: Anti-fraud checks, code generation, referral tracking, and reward disbursement.
- `CampaignService`: Trigger-based reward issuance.

### Phase 5: Order & Payment Lifecycle Integration
- Listen to `OrderStatusUpdated` and payment webhook completions.
- Automatically evaluate order rewards upon completion/payment.
- Process refunds (full or partial) and reverse loyalty points/cashback proportionally without duplicate reversal.
- Support both online QR orders and offline staff-entered orders.

### Phase 6: Staff Interface & POS Operations
- Responsive staff portal at `/admin/loyalty/staff` and POS integrations.
- Customer lookup by phone or scanning ephemeral QR tokens.
- Balance check, points issuance, offline order recording, and reward redemption.
- Strict authorization via RBAC (`loyalty.operate`) and audit logging.

### Phase 7: Apple Wallet (PassKit)
- Implement `AppleWalletService`:
  - Pass JSON structure builder complying with Apple PassKit specifications.
  - Manifest generation and SHA-1 hashing.
  - PKCS#7 signing using Apple WWDR certificate and Pass Type ID certificate.
  - Zip compression to generate `.pkpass` bundle.
- PassKit Web Service REST API endpoints (`/api/v1/devices/...`, `/api/v1/passes/...`, `/api/v1/log`).
- Silent push updates via APNs HTTP/2 protocol.
- Support hybrid issuer: Centralized platform certificate or custom tenant certificate.

### Phase 8: Google Wallet
- Implement `GoogleWalletService`:
  - Manage `LoyaltyClass` and `LoyaltyObject` schemas.
  - Generate cryptographically signed "Save to Google Wallet" JWT links (RS256).
  - Synchronize balance and tier changes via Google Wallet REST API.

### Phase 9: Wallet Card Visual Designer
- Admin visual editor for card background, text colors, brand logo, strip banner, and barcode fields.
- Real-time previews for Apple and Google card formats.
- Safe publishing workflow with version incrementation.

### Phase 10: Customer Loyalty Portal
- Multilingual responsive storefront view at `/m/{vendor_slug}/loyalty` (Armenian, English, Russian).
- Overview, My Card, Points Balance, Cashback, Rewards Catalog, Stamp Cards, Tier Status, Transaction History, Referrals.
- One-click "Add to Apple Wallet" and "Save to Google Wallet" buttons.

### Phase 11: Admin Management Console
- Integrated under `Marketing → Loyalty` in admin sidebar.
- Sub-modules: Overview, Program Settings, Customers, Card Designer, Points Rules, Cashback Rules, Membership Tiers, Stamp Cards, Rewards, Referrals, Campaigns, Transactions, Wallet Settings, Staff Operations, Reports, Audit Logs.

### Phase 12: Subscription & Entitlement Enforcement
- Map features to subscription tiers:
  - `Basic`: Points, basic rewards, basic stamps, QR identity.
  - `Pro`: Adds Apple & Google Wallet, Cashback, Tiers, Birthday campaigns.
  - `Advanced`: Adds Referrals, Advanced campaigns, segmentation, analytics.
- Enforce limits via `EntitlementService` and handle graceful downgrade/read-only behavior for expired subscriptions.

### Phase 13: Notifications & Scheduled Automations
- Automated daily scheduled commands:
  - Birthday campaigns
  - Expiring points warnings (e.g. 7 days before lot expiration)
  - Inactive customer win-back offers
- Multi-channel delivery (SMS via `CrmAutomationService`, Email, APNs push).

### Phase 14: Security Hardening & Concurrency Protection
- Prevent race conditions during concurrent redemptions (`lockForUpdate`).
- Validate tenant isolation across all endpoints.
- Rate limit OTP generation and QR verification.

### Phase 15: Analytics & Financial Reporting
- Tenant-scoped metrics: Active members, points liability, earned vs. redeemed, cashback outstanding, tier distribution, loyalty-attributed revenue.
- Optimized cached aggregate queries.

### Phase 16: Automated Test Suite
- Comprehensive PHPUnit tests:
  - Unit tests for calculations, ledgers, and lot expiration.
  - Feature tests for enrollment, orders, refunds, staff operations, redemptions.
  - Security tests for cross-tenant isolation and concurrent redemptions.

### Phase 17: Documentation & Final Delivery Report
- Author complete documentation in `docs/loyalty/` and final verification report.

