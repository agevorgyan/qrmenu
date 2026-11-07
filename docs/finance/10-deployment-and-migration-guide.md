# QR Menu SaaS — Financial Operations Deployment & Migration Guide

## Overview

This guide provides the operational procedures for deploying the Cash Management & Financial Operations module into staging and production environments of the QR Menu SaaS platform.

---

## 1. Prerequisites & System Requirements

- **PHP**: 8.4+ (Tested on PHP 8.5.10 with `bcmath`, `pgsql`, `pdo_pgsql`, `intl`, `mbstring`).
- **Database**: PostgreSQL 16+ (Utilizes `uuid-ossp`, `numeric(14,2)` precision, partial indexes, and JSONB).
- **Framework**: Laravel 13.32+.
- **Cache / Queues**: Redis 7.0+ for high-throughput idempotency locks and asynchronous report compilation.

---

## 2. Migration Execution Order

The module introduces 7 database migrations, executed in strict dependency sequence:

1. `2026_11_07_020001_create_financial_chart_of_accounts_and_periods_tables.php`:
   - Creates `chart_of_accounts` and `accounting_periods`.
2. `2026_11_07_020002_create_financial_ledger_tables.php`:
   - Creates `journal_entries` and `journal_entry_lines`.
3. `2026_11_07_020003_create_cash_registers_and_enhanced_shifts_tables.php`:
   - Creates `cash_registers` and enhances `pos_shifts` / `pos_cash_movements`.
4. `2026_11_07_020004_create_financial_categories_expenses_and_incomes_tables.php`:
   - Creates `financial_categories`, `bank_accounts`, `expenses`, `expense_payments`, `incomes`.
5. `2026_11_07_020005_create_financial_transfers_and_bank_tables.php`:
   - Creates `financial_transfers`, `bank_transactions`, `bank_reconciliations`.
6. `2026_11_07_020006_create_financial_approval_and_audit_tables.php`:
   - Creates `financial_approval_policies`, `financial_approval_requests`, `financial_audit_logs`.
7. `2026_11_07_174336_add_journal_entry_id_to_expenses_table.php`:
   - Connects accrued expenses directly to `journal_entries`.

### Running Migrations

```bash
php artisan migrate --force --no-interaction
```

---

## 3. Permission Seeding & Enterprise Roles

The module registers 19 fine-grained permissions under the `finance` domain:

| Permission Code | Description | Default Roles |
| :--- | :--- | :--- |
| `finance.dashboard.view` | View executive financial dashboard | Owner, Accountant, Finance Manager |
| `finance.registers.view` | View cash registers & balances | Owner, Branch Manager, Cashier |
| `finance.registers.manage` | Create/edit registers & open drawers | Owner, Finance Manager |
| `finance.shifts.manage` | Open, reconcile, and close cash shifts | Owner, Branch Manager, Cashier |
| `finance.transfers.manage`| Safe drops, till transfers, bank deposits | Owner, Branch Manager |
| `finance.expenses.view` | View recorded operating expenses | Owner, Accountant, Finance Manager |
| `finance.expenses.create` | Create draft expenses | Owner, Staff, Cashier, Manager |
| `finance.expenses.approve`| Approve expenses & recognize liabilities | Owner, Finance Manager |
| `finance.expenses.pay` | Disburse cash or bank payments | Owner, Finance Manager |
| `finance.incomes.view` | View operational non-order incomes | Owner, Accountant, Finance Manager |
| `finance.incomes.create` | Record catering, rent, ancillary income | Owner, Finance Manager |
| `finance.bank.view` | View bank accounts & statements | Owner, Accountant, Finance Manager |
| `finance.bank.manage` | Add corporate bank accounts | Owner, Finance Manager |
| `finance.bank.reconcile` | Import statements & reconcile books | Owner, Accountant |
| `finance.ledger.view` | View Chart of Accounts & GL lines | Owner, Accountant, Auditor |
| `finance.ledger.manage` | Post manual journals & period closures | Owner, Accountant |
| `finance.reports.view` | Access P&L, Balance Sheet, Cash Flow | Owner, Accountant, Auditor |
| `finance.approvals.manage`| Configure monetary approval thresholds | Owner, Finance Manager |
| `finance.audit.view` | View immutable audit logs & tamper check| Owner, Auditor |

### Synchronizing Permissions to Database

```bash
php artisan db:seed --class=EnterprisePermissionsSeeder --force
```

---

## 4. Tenant Onboarding & COA Initialization

When a new restaurant tenant registers, or when existing tenants activate the finance module, their standard restaurant Chart of Accounts must be initialized.

### Automatic Initialization

`FinancialLedgerService::initializeDefaultAccounts(Vendor $vendor)` automatically runs during tenant creation or upon visiting `/admin/finance/ledger`.

### Republic of Armenia Standard Chart of Accounts (COA - ՀՀ Հաշվային Պլան)

- **Դաս 1. Ոչ ընթացիկ ակտիվներ**:
  - `1114`: Խոհանոցային և ռեստորանային սարքավորումներ
  - `1116`: Արտադրական և տնտեսական գույք (կահույք, բար)
  - `1121`: Հիմնական միջոցների մաշվածություն
  - `1311`: Ոչ նյութական ակտիվներ / POS լիցենզիաներ
- **Դաս 2. Ընթացիկ ակտիվներ**:
  - `2111`: Հումք և նյութեր (սննդամթերք, միս, բանջարեղեն)
  - `2114`: Տարա և տարանյութեր (առաքման տուփեր, բաժակներ)
  - `2152`: Սեփական արտադրության կիսապատրաստուկներ
  - `2161`: Ապրանքներ պահեստում (բարի ալկոհոլային և ոչ ալկոհոլային խմիչքներ)
  - `2211`: Դեբիտորական պարտքեր վաճառքների գծով (AR)
  - `2281`: Առհաշիվ տրված գումարներ (աշխատակիցների կանխավճարներ)
  - `2511`: Ազգային արժույթի դրամարկղ (POS դրամարկղային արկղեր / Cash in Till)
  - `2512`: Գլխավոր դրամարկղ / Պահարան (Չհրկիզվող պահարան - Սեյֆ)
  - `2521`: Հաշվարկային հաշիվ (Բանկային գործառնական հաշիվ ՀՀ դրամով)
  - `2531`: Արտարժութային հաշիվ
  - `2551`: Դրամական միջոցներ ճանապարհին - Էքվայրինգ (POS քարտային վճարումներ)
  - `2552`: Դրամական միջոցներ ճանապարհին - Օնլայն դարպասներ (Իդրամ, Թելսել, Սթրայփ)
- **Դաս 3. Սեփական կապիտալ**:
  - `3111`: Կանոնադրական (բաժնեհավաք) կապիտալ
  - `3411`: Նախորդ տարիների չբաշխված շահույթ (չծածկված վնաս)
  - `3421`: Հաշվետու տարվա զուտ շահույթ (վնաս)
- **Դաս 4. Ոչ ընթացիկ պարտավորություններ**:
  - `4111`: Երկարաժամկետ բանկային վարկեր
  - `4211`: Երկարաժամկետ պարտավորություններ վարձակալության գծով
- **Դաս 5. Ընթացիկ պարտավորություններ**:
  - `5111`: Կարճաժամկետ բանկային վարկեր և օվերդրաֆտներ
  - `5211`: Կրեդիտորական պարտքեր գնումների գծով (մատակարարներ)
  - `5213`: Կրեդիտորական պարտքեր ծառայությունների գծով
  - `5231`: Ստացված ընթացիկ կանխավճարներ (բանկետների ամրագրումներ)
  - `5241`: Պարտքեր շահութահարկի գծով
  - `5242`: Պարտքեր եկամտային հարկի գծով (աշխատավարձից)
  - `5243`: Պարտքեր ԱԱՀ-ի գծով
  - `5248`: Պարտքեր դրոշմանիշային վճարների գծով
  - `5249`: Պարտքեր շրջանառության հարկի գծով
  - `5251`: Պարտքեր պարտադիր կենսաթոշակային վճարների գծով
  - `5271`: Պարտքեր աշխատավարձի վճարման գծով (զուտ աշխատավարձ)
  - `5311`: Թեյավճարների գծով ընթացիկ պարտավորություն (մատուցողների ֆոնդ)
- **Դաս 6. Եկամուտներ**:
  - `6111`: Հասույթ արտադրանքի վաճառքից (ուտեստներ - Food Revenue)
  - `6112`: Հասույթ ապրանքների վաճառքից (բարի խմիչքներ - Beverage Revenue)
  - `6114`: Հասույթ ծառայությունների մատուցումից (սպասարկման վճար / առաքում)
  - `6121`: Հետվերադարձված արտադրանք և ապրանքներ (Կոնտրա-հասույթ)
  - `6123`: Տրամադրված առևտրային զեղչեր (Կոնտրա-հասույթ)
  - `6143`: Եկամուտներ գույքագրման ավելցուկներից
  - `6144`: Եկամուտներ դահլիճի/տարածքի վարձակալությունից
  - `6146`: Այլ գործառնական եկամուտներ
- **Դաս 7. Ծախսեր**:
  - `7111`: Իրացված արտադրանքի ինքնարժեք (Food Cost)
  - `7112`: Իրացված ապրանքների ինքնարժեք (Beverage Cost)
  - `7121`: Իրացման աշխատակիցների աշխատավարձ (առաքիչներ)
  - `7124`: Փոխադրման և առաքման ծախսեր
  - `7125`: Գովազդի և մարքեթինգի ծախսեր
  - `7126`: Առաքման ծառայությունների միջնորդավճարներ (Glovo, Yandex)
  - `7131`: Վարչական անձնակազմի աշխատանքի վճարման ծախսեր
  - `7132`: Հիմնական միջոցների մաշվածություն
  - `7133`: Տարածքի և գույքի վարձակալական ծախսեր
  - `7134`: Կոմունալ ծախսեր (էլեկտրաէներգիա, գազ, ջուր)
  - `7135`: Նորոգման և տեխնիկական սպասարկման ծախսեր
  - `7136`: Գրասենյակային, տնտեսական և մաքրության նյութերի ծախսեր
  - `7137`: Կապի, ինտերնետի և ՏՏ ծախսեր
  - `7138`: Բանկային ծառայությունների և էքվայրինգի ծախսեր
  - `7139`: Այլ վարչական ծախսեր և լիցենզիաներ
  - `7144`: Տույժեր, տուգանքներ
  - `7145`: Գույքագրման պակասորդներ, փչացումից կորուստներ
  - `7511`: Շահութահարկի գծով ծախս
  - `7512`: Շրջանառության հարկի գծով ծախս
  - `5100`: Wages & Salaries Expense
  - `5200`: Rent & Facility Lease
  - `5300`: Utilities (Electric, Gas, Water)
  - `5400`: Marketing & Advertising
  - `5500`: Operating Supplies & Disposables
  - `5800`: Cash Over / Short Discrepancy

---

## 5. Webhook & Background Queue Configuration

Configure the Laravel queue worker to handle async payment notifications and bank statement parsing:

```bash
php artisan queue:work redis --queue=financial-transactions,default --tries=3 --timeout=60
```

### Idempotent Webhook Processing

All incoming payment webhooks must pass the provider event ID as `idempotency_key` to `FinancialTransactionService::recordPaymentSettlement()`. Retried deliveries will instantly return the existing `JournalEntry` without re-posting lines or double-crediting balances.

---

## 6. Verification Checklist

After deploying to staging or production:

1. [ ] Run `php artisan test --compact tests/Feature/Finance` (Verify all 29 tests pass).
2. [ ] Visit `/admin/finance` as a Vendor Owner; ensure dashboard metrics render cleanly.
3. [ ] Verify sidebar navigation under **Finance & Cash** displays all 11 sub-modules.
4. [ ] Run `verifyTamperIntegrity` from `/admin/finance/audit/verify` to confirm zero initial ledger anomalies.
5. [ ] Open and close a test cashier shift to verify float reconciliation.

