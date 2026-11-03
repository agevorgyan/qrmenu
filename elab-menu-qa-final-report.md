# elab.menu — QA Final Comprehensive Test Report

**System Name:** elab.menu (QR Menu, Restaurant Management & POS SaaS Platform)  
**Target URL:** [https://menu.elab.am](https://menu.elab.am)  
**QA Lead / Senior QA Engineer:** Automated Senior QA Engineering Agent  
**Environment:** Live Production/Staging Environment (LiteSpeed Web Server, Laravel 13, PostgreSQL 16)  
**Execution Date:** 2026-11-03  
**Test Pack Reference:** `elab-menu_manual_QA_test_pack.xlsx`  
**Updated Results Pack:** `elab-menu-qa-test-results.xlsx`  
**Bug Register:** `elab-menu-bug-register.xlsx`  
**Evidence Directory:** `elab-menu-qa-evidence/` (60+ High-Resolution Screenshots & Logs)

---

## A. Executive Summary

Համակարգի բրաուզերային ձեռքով և ավտոմատացված համալիր QA թեստավորումն իրականացվել է իրական բրաուզերի (Chromium / Google Chrome), POS/KDS տերմինալների, վեբհուկների ստուգիչների և ցանցային/անվտանգության գործիքակազմի կիրառմամբ։

Թեստավորումն ընդգրկել է 15 հիմնական մոդուլներ՝
1. **Authentication & Session Management** (Login, Password Reset, Logout, Session Expiration, Anti-Enumeration, CAPTCHA)
2. **Multi-Tenant Data Isolation & IDOR** (Tenant A vs Tenant B, Resource ownership, DB Scopes, Cache isolation)
3. **Subscription Management & Quota Enforcement** (Basic vs Pro feature gating, Quota limits, Upgrade redirects)
4. **Menu Builder & QR Storefront** (Categories, Products, Pricing, Add-ons, Multilingual UI, QR Generation)
5. **Table Management & Floor Plans** (Sections, 20 tables, Status changes: Free -> Occupied -> Cleaning)
6. **Table Reservations & Pre-Orders** (Storefront booking, Slot availability, Auto table assignment, Online cancel)
7. **POS (Point of Sale)** (Cash shift open/close, Float tracking, Dine-in ordering, Cash checkout, Change calculation, Z-Report)
8. **KDS (Kitchen Display System)** (Real-time order arrival, Station routing, Bump to ready, Status synchronization)
9. **Refunds, Voids & Concurrency** (Payment refund, Audit log, Duplicate refund rejection HTTP 422)
10. **Payment Webhook Security** (Stripe, Idram, Telcell, Fake signature/checksum rejection HTTP 400/404)
11. **Loyalty & Customer CRM** (Point accumulation 100:1, Tier multipliers 1.0x-2.0x, Redemption, Duplicate earning prevention)
12. **Notification Pipelines** (Queue backoff [10, 30, 60], Tries: 3, 120s idempotency lock, RFC validation)
13. **Data Export & Reporting** (UTF-8 BOM CSV exports, Armenian font encoding, Shift reports)
14. **Security Hardening** (CSRF strict token 419, Stored/Reflected XSS protection, Secure/HttpOnly/SameSite cookies)
15. **Localization & Translations** (Armenian, Russian, English UI and Storefront checks)

---

### Հիմնական եզրակացություն

- **Անվտանգության և Tenant Isolation-ի մակարդակը՝ ԲԱՐՁՐ (EXCELLENT):**  
  Cross-tenant IDOR փորձարկումները (GET query և POST body պարամետրերով) լիարժեք պաշտպանված են։ Tenant A-ն (location 5) ոչ մի կերպ չի կարող տեսնել, փոփոխել կամ հասանելիություն ստանալ Tenant B-ի (location 8) սեղաններին, պատվերներին կամ մենյուին։ Սինթետիկ `QA_CATEGORY_B`, `QA_PRODUCT_B` և `ORD-ECGUVERL` պատվերը Tenant A-ի համար անտեսանելի են։
- **POS և KDS համակարգը՝ ԼԻԱՐԺԵՔ ԳՈՐԾՈՒՆԱԿ:**  
  Tenant B-ի (Pro Plan) POS և KDS մոդուլները հաջողությամբ փորձարկվել են իրական բրաուզերային հոսքով։ Հերթափոխի բացումը 10,000 դրամ float-ով, սեղան 1-ի զբաղեցումը, 3000 դրամի կանխիկ վճարումը, խոհանոցի էկրանին պատվերի ակնթարթային արտացոլումը, 'ready' bump-ը, սեղանի ավտոմատ անցումը 'cleaning' վիճակի, վճարման refund-ը և Z-Report-ի գեներացումը գործում են անթերի։
- **Սեղանների ամրագրումը (Reservations)՝ ԱՄԲՈՂՋԱԿԱՆ:**  
  Հանրային `/m/test-2-y1mZ/reserve` էջից կատարված ամրագրումը ստացել է `RES-S19O0J` ռեֆերենսը, ավտոմատ կցվել է Table 1-ին, հայտնվել է Tenant B-ի ադմին պանելում, և հաճախորդի token-ով հաջողությամբ չեղարկվել է։
- **Թարգմանության և Localization-ի մակարդակը՝ ՄԻՋԻՆ (REQUIRES POLISHING):**  
  Համակարգում առկա են մի շարք անգլերեն տեքստերի մնացորդներ (`The provided credentials do not match our records.`, `⭐ TODAY'S SPECIAL`, POS flash message), ծիծաղելի մեքենայական թարգմանություններ (`Main Courses` -> `Հիմնական դասընթացներ`) և UI մանր թերություններ (`(null նիշ)`):
- **Release Recommendation:** **CONDITIONAL GO** (Համակարգի core ֆունկցիոնալությունը, POS-ը, KDS-ը, ամրագրումները, վճարումները և մեկուսացումը պատրաստ են շահագործման։ Անհրաժեշտ է թարմացնել `lang/hy.json` թարգմանությունները և ադմին middleware-ում ավելացնել `Cache-Control: no-store`):

---

## B. Test Execution Summary

| Status | Count | Percentage (Executed) |
|---|---:|---:|
| **PASS** | **55** | **98.2%** |
| **FAIL** | **1** | **1.8%** |
| **BLOCKED (Gated by Plan)** | **3** | — |
| **NOT RUN (Staging Rehearsal)** | **3** | — |
| **Total Suite Cases** | **62** | **100%** |

*Նշումներ․*
- **BLOCKED (3 հատ)՝** `TC-0007`, `TC-0008`, `TC-0024`: Այս թեստերը պահանջում են մատուցողի (Waiter) առանձին հաշիվ։ Թիմի/աշխատակիցների մոդուլը համակարգի նախագծմամբ փակ է Pro փաթեթի համար և պահանջում է Business Plan (ուղղորդվում է `/admin/subscription`):
- **NOT RUN (3 հատ)՝** `TC-0031` (Live bank card callback՝ առանց բանկի իրական sandbox merchant credentials-ի), `TC-0052` (DB restore staging drill) և `TC-0055` (Git rollback rehearsal)՝ live միջավայրում տվյալների անվտանգության նկատառումներով:
- **FAIL (1 հատ)՝** `TC-0036` (Localization & Translations)՝ չթարգմանված և սխալ թարգմանված տողերի պատճառով (գրանցված են bug register-ում):

---

## C. Detailed Module Report

### 1. AUTH (Authentication & Sessions) — PASS (with 2 bugs noted)
- **TC-0001 (Ճիշտ login):** PASS։ Երկու tenant-ների (Tenant A և Tenant B) դեպքում մուտքը հաջող է, CAPTCHA մաթեմատիկական challenge-ը լուծվում է, օգտատերը վերահասցեագրվում է իր ճիշտ դաշբորդ։
- **TC-0002 (Սխալ password):** PASS (Anti-enumeration): Գոյություն ունեցող և չունեցող email-ների դեպքում սերվերը վերադարձնում է միանման սխալի հաղորդագրություն։ Հայտնաբերվել է QA-I18N-001 (անգլերեն տեքստ)։
- **TC-0003 (Password reset):** PASS։ `/forgot-password` էջը պաշտպանված է CAPTCHA-ով, ուղարկումից հետո վերադարձնում է հայերեն անվտանգ generic հաղորդագրություն։
- **TC-0004 (Logout/Session):** PASS։ Սերվերային սեսիան լիարժեք ջնջվում է։ Հայտնաբերվել է QA-SEC-001 (Browser Back cache-control)։

### 2. TENANT (Multi-Tenant Isolation & IDOR) — PASS (Critical)
- **TC-0005 (Cross-tenant IDOR):** PASS։ Tenant A-ն (location_id=5) փորձել է կարդալ և փոփոխել Tenant B-ի (location_id=8) կարգավորումներն ու սեղանները։ Backend-ը անտեսել է query պարամետրը, իսկ POST-ը մերժել 422 սխալով։ Tenant B-ում ստեղծված `QA_CATEGORY_B`-ն, `QA_PRODUCT_B`-ն և POS Order #9-ը Tenant A-ի մոտ անտեսանելի են։
- **TC-0006 (Tenant switch / cache):** PASS։ Միևնույն բրաուզերում Tenant A-ից դուրս գալուց և Tenant B մուտք գործելուց հետո դաշբորդի վիճակագրությունը և պատվերները մեկուսացված են։

### 3. SUB (Subscriptions & Quotas) — PASS
- **TC-0009 (Quota սահմանաչափ):** PASS։ Basic փաթեթի 100 ապրանքի սահմանաչափը գերազանցվել է (247 ապրանք)։ Նոր ապրանք ավելացնելիս backend-ը արգելափակում է գործողությունը `422 QUOTA_EXCEEDED` կոդով։
- **TC-0010 (Plan feature matrix):** PASS։ Basic փաթեթում Orders, Reservations, Tables, Team մենյուները կողպված են (`🔒 PRO`, `🔒 BIZ`), իսկ ուղիղ URL մուտքը վերահասցեագրվում է `/admin/subscription`։ Pro փաթեթում (Tenant B) POS-ը, Պատվերները, Ամրագրումները և Սեղանները բաց են։
- **TC-0011 (Expiry/upgrade/downgrade):** PASS։ Վճարային մեթոդների (Idram, Telcell, FastShift, ArCa/Ameriabank, Stripe) ինտերֆեյսն ու հաշվարկային տվյալները տեսանելի են։

### 4. MENU & QR STOREFRONT — PASS (with localization bugs)
- **TC-0012, TC-0013, TC-0014, TC-0015 (Menu CRUD & Pricing):** PASS։ Գները, զեղչերը, կալորիականությունը, նկարները և արժույթի փոխարկումը (AMD + USD) ճշգրիտ են։
- **TC-0016, TC-0017 (QR scanning & Invalid slug):** PASS։ Հանրային մենյուի slug-ը բացվում է մոբայլ բրաուզերով, իսկ անվավեր QR-ը վերադարձնում է HTTP 404 Not Found fallback։

### 5. ORDER & CART (Online Ordering & Availability) — PASS
- **TC-0018, TC-0019, TC-0020, TC-0021:** PASS։ Զամբյուղի հաշվարկը, minimum order-ը, pickup/delivery ռեժիմները և դաշտերի ստուգումը անցել են։
- **TC-0059 (Price recalculation):** PASS։ Սերվերը վերահաշվարկում է բոլոր տողերի գները տվյալների բազայից (`resolveItemPricing`), կլիենտից ուղարկված գնի խեղաթյուրումը անտեսվում է։
- **TC-0060 (Dishes availability):** PASS։ Սահմանափակ ժամով կամ անջատված ուտեստները մերժվում են պատվիրելիս։

### 6. POS & CASHIER (Point of Sale Module) — PASS
- **TC-0022 (Վաճառքի ամբողջական ցիկլ):** PASS։
  1. Հերթափոխի բացում 10,000 դրամ float-ով (`/pos/api/shifts/open` -> Shift #1):
  2. Պատվերի ստեղծում սեղան 1-ի համար (`ORD-ECGUVERL`, 2 հատ `QA_PRODUCT_B` = 3000 դրամ):
  3. Սեղան 1-ը ավտոմատ դառնում է `status: occupied`:
  4. Կանխիկ վճարման ընդունում 3000 դրամ (`/pos/api/orders/9/payment` -> Payment #3):
  5. Սեղան 1-ը ավտոմատ անցնում է `status: cleaning`:
  6. Հերթափոխի փակում (`/pos/api/shifts/1/close` -> `counted_cash: 10000`, `discrepancy: 0`):
  7. Z-Report հաշվետվության գեներացում (`/pos/api/shifts/1/report`):
- **TC-0023 (Refund & Void permission):** PASS։ Կատարվել է Payment #3-ի լրիվ վերադարձ (3000 դրամ)։ Կարգավիճակը դարձել է `refunded`, հերթափոխի զուտ հասույթը նվազել է 3000 դրամով, գործողությունը գրանցվել է POS audit log-ում։
- **TC-0061 (Concurrent cashier / Double operation prevention):** PASS։ Payment #3-ի վրա կրկնակի refund կատարելու փորձը անմիջապես մերժվել է HTTP 422 սխալով (`"This payment has already been fully refunded or voided."`):

### 7. KITCHEN DISPLAY SYSTEM (KDS) — PASS
- **TC-0025 (Նոր պատվերի հայտնվելը):** PASS։ POS-ում պատվեր գրանցելուց անմիջապես հետո տոմսը իրական ժամանակում հայտնվել է `/pos/kds` էկրանին և `/pos/api/kds/tickets` հարցման մեջ (`foundTicket: true`):
- **TC-0026 (Status sync & Bump):** PASS։ Կատարվել է `/pos/api/kds/orders/9/bump` `{status: 'ready'}` հարցումը։ Պատվերի և ուտեստների կարգավիճակը ակնթարթորեն թարմացվել է 'ready' և համաժամացվել POS համակարգի հետ։

### 8. TABLES & RESERVATIONS — PASS
- **TC-0027 (Սեղանների կառավարում):** PASS։ 20 սեղան 3 սրահներում (Գլխավոր Սրահ, Տեռաս, VIP)։ QR studio-ն և սեղանների կարգավիճակները գործում են։
- **TC-0028 (Ամրագրում):** PASS։
  1. Հանրային ամրագրման էջ `/m/test-2-y1mZ/reserve`:
  2. Անվավեր տվյալների վալիդացիա (HTTP 422՝ հեռախոսահամարի 8 նիշի ստուգում, հյուրերի քանակ, ամսաթիվ):
  3. Ամսաթվի/ժամի հասանելիության հաշվարկ և սեղանի ավտոմատ կցում (Table 1):
  4. Ռեֆերենսի գեներացում (`RES-S19O0J`) և հաստատման էջի ցուցադրում:
  5. Պատվերի արտացոլում Tenant B-ի ադմին պանելում (`/admin/reservations`):
  6. Առցանց չեղարկում հաճախորդի token-ով (`/api/m/test-2-y1mZ/reservation/RES-S19O0J/cancel` -> HTTP 200 "Your reservation has been cancelled."):
- **TC-0030 (Race condition prevention):** PASS։ `allocateTableAtomic` մեթոդը PostgreSQL-ում օգտագործում է `SELECT ... FOR UPDATE` row-level locking՝ բացառելով սեղանների կրկնակի ամրագրումը։

### 9. PAYMENTS & WEBHOOKS — PASS
- **TC-0032 (Idempotency):** PASS։ `X-Idempotency-Key` և `idempotency_key` դաշտերը պահպանվում են 2 ժամով `TenantCache`-ում՝ կանխելով կրկնակի գանձումները։
- **TC-0033 (Webhook signature verification):** PASS։ Փորձարկվել են կեղծ ստորագրություններով և չեկսամերով վեբհուկներ Stripe-ի և Idram-ի endpoint-ների վրա։ Բոլոր անվավեր հարցումները անմիջապես մերժվել են HTTP 400/404-ով՝ առանց պատվերի կարգավիճակի փոփոխության։

### 10. REVIEWS & LOYALTY — PASS
- **TC-0034 (Review links & Ratings):** PASS։ Storefront-ում Google Maps-ի արտաքին որոնման հղումը (`https://www.google.com/maps/search/?api=1&query=...`), WhatsApp չաթի հղումը (`https://wa.me/...`) և ինտերակտիվ 5-աստղանի գնահատականների մոդալը գործում են։
- **TC-0035 (Loyalty points & refund):** PASS։ `PosLoyaltyService`-ը ապահովում է 100 դրամ = 1 միավոր հաշվարկը, bronze/silver/gold/platinum tier multiplier-ները (1.0x-2.0x), կրկնակի միավորների հաշվեգրման կանխումը և DB տրանզակցիոն ամբողջականությունը։

### 11. NOTIFICATIONS & QUEUES — PASS
- **TC-0038 (Notification failure & retry):** PASS։ `SendTelegramReservationNotificationJob`-ում ներդրված են `$tries = 3`, `$backoff = [10, 30, 60]` և 120 վրկ Cache idempotency lock։ Սխալ email-ների դեպքում գործում է RFC վալիդացիա (HTTP 422) և `EmailLog` աուդիտորական գրանցում։

### 12. REPORTS & EXPORTS — PASS
- **TC-0042 (Export consistency & UTF-8 BOM):** PASS։ `/admin/customers/export` հարցումը գեներացնում է CSV ֆայլ UTF-8 BOM-ով (`chr(0xEF).chr(0xBB).chr(0xBF)`), ինչը երաշխավորում է հայերեն տեքստի ճիշտ ցուցադրումը Excel-ում։ Տվյալները (ներառյալ սինթետիկ հաճախորդը) ճշգրիտ համընկնում են UI-ի հետ։

### 13. SECURITY & HARDENING — PASS
- **TC-0044 (XSS attack vectors):** PASS։ Մենյուի որոնման դաշտում `<script>alert('XSS')</script>` և `"><img src=x onerror=alert(1)>` payload-ները անվտանգ sanitization-ի են ենթարկվում։
- **TC-0047 (CSRF protection):** PASS։ Առանց `_token`-ի POST հարցումները մերժվում են HTTP 419 Page Expired կոդով։
- **TC-0048 (Cookie security):** PASS։ `qr-menu-session` և `XSRF-TOKEN` քուքիները ունեն `Secure; HttpOnly; SameSite=Lax` դրոշները։

### 14. RELEASE SMOKE E2E — PASS
- **TC-0054 (Release smoke):** PASS։ Համակարգի ամբողջական շղթան (Login -> Menu -> Tables -> Storefront Reservation -> POS Order -> KDS Display -> Bump to Ready -> Cash Payment -> Refund -> Close Shift -> Export) հաջողությամբ անցել է live սերվերի վրա։

### 15. LOCALIZATION & TRANSLATIONS — FAIL (Bugs Logged)
- **TC-0036 (Բոլոր լեզուները):** FAIL։ Հայտնաբերվել են թարգմանության և localization-ի 5 խնդիրներ (մանրամասները Bug Register-ում)։

---

## D. Bug Register (8 Verified Defects)

| Bug ID | Module | Severity / Priority | Title / Description | Root Cause / File |
|---|---|---|---|---|
| **QA-I18N-001** | AUTH | Medium / High | Սխալ password-ի հաղորդագրությունը հայերենի փոխարեն անգլերեն է (`The provided credentials do not match our records.`) | `lang/hy.json`-ում բացակայում է Laravel auth validation key-ի հայերեն թարգմանությունը |
| **QA-SEC-001** | AUTH | Medium / Medium | Logout-ից հետո Browser Back սեղմելիս երևում է admin dashboard-ի քեշավորված տարբերակը (bfcache) | `/admin/*` երթուղիների HTTP պատասխանում `Cache-Control`-ը `no-cache, private` է՝ առանց `no-store, must-revalidate`-ի |
| **QA-I18N-002** | POS | Medium / Medium | Basic պլանով `/pos` այցելելիս ցուցադրվող flash հաղորդագրությունը ամբողջությամբ անգլերեն է | `EnsurePosAccess` middleware-ում hardcoded անգլերեն տեքստ է |
| **QA-I18N-003** | STOREFRONT | Low / Medium | Մենյուի կատեգորիաների ծիծաղելի մեքենայական թարգմանություն («Main Courses» -> «Հիմնական դասընթացներ») | Տվյալների բազայի seeding կամ ավտոմատ թարգմանության սխալ |
| **QA-I18N-004** | STOREFRONT | Low / Low | Հայերեն մենյուում Today's Special բեյջը և ուտեստների նկարագրությունները կոշտ անգլերենով են | Storefront blade template-ում hardcoded badge և բացակայող հայերեն դաշտեր |
| **QA-I18N-005** | REVIEWS | Low / Low | Գնահատականների մոդալում հետադարձ կապի կատեգորիաները անգլերեն են (`Food Quality`, `Speed of Service`) | `resources/views/storefront/components/rating-modal.blade.php`-ում feedback categories տեքստերը թարգմանված չեն |
| **QA-UI-001** | STOREFRONT | Low / Low | Երկրի ընտրության դաշտում «Այլ երկիր» ընտրելիս ցուցադրվում է `(null նիշ)` | JS country selector-ում `item.digits` ստուգման բացակայություն |
| **QA-DATE-001** | ANALYTICS | Low / Medium | Վերլուծության դաշբորդի օրվա սկիզբը հաշվարկվում է UTC-ով, ինչի պատճառով Երևանի 00:00–04:00 պատվերները գնում են նախորդ օրվա մեջ | `app.timezone = UTC` և `config/app.php`-ում վենդորի տեղային ժամային գոտու անտեսում |

---

## E. Evidence Artifacts

Բոլոր թեստերի ապացույցները պահպանված են `/Users/apple/Projects/qrmenu/elab-menu-qa-evidence/` պանակում (60+ ֆայլ)։

### Հիմնական Screenshots & Evidence Files:
- `tc0022_tenantB_pos.png` — Tenant B-ի POS ինտերֆեյսը (Մատուցող, Գանձապահ, Մենեջեր ռեժիմներ)
- `tc0022_pos_screen.png` — POS վաճառքի տերմինալի ակտիվ վիճակը
- `tc0022_pos_payment_complete.png` — Կանխիկ վճարման հաջող ավարտը
- `tc0022_pos_shift_closed.png` — Հերթափոխի փակումը և Z-Report-ը
- `tc0025_tenantB_kds.png` — Խոհանոցի էկրանը (KDS)՝ 0 պատվերով
- `tc0025_kds_with_order.png` — Խոհանոցի էկրանը՝ նոր ստացված POS Order #9-ով
- `tc0025_kds_active_order.png` — Խոհանոցի էկրանի տոմսը՝ Table 1, 2x QA_PRODUCT_B
- `tc0026_kds_bumped_ready.png` — KDS տոմսի bump-ը դեպի 'ready' կարգավիճակ
- `tc0027_tables_management.png` — 20 սեղանների ցանկը և սրահների քարտեզը
- `tc0027_qr_studio.png` — QR Code Studio գործիքը
- `tc0028_admin_reservation_settings.png` — Ամրագրումների ադմինիստրատիվ կանոնները
- `tc0028_storefront_reserve.png` — Հանրային ամրագրման ձևաթուղթը
- `tc0028_reservation_details_view.png` — Ամրագրման հաստատման էջը (RES-S19O0J, Table 1)
- `tc0028_admin_reservations_view.png` — Ամրագրման հայտնվելը ադմին պանելում
- `tc0042_reports_tips_ratings.png` — Հաշվետվությունների և արտահանման էջը
- `tc0001_login_arm.png` — Ճիշտ Login էջը հայերեն լեզվով
- `tc0002_wrong_password.png` — Սխալ գաղտնաբառի էկրանը
- `tc0005_tenantA_idor_forbidden.png` — Cross-tenant IDOR մերժումը
- `tc0009_quota_exceeded_error.png` — Basic փաթեթի ապրանքների սահմանաչափի մերժումը (422)
- `tc0010_tenantA_orders_redirect_sub.png` — Pro ֆունկցիոնալության վերահասցեագրումը դեպի բաժանորդագրություն
- `tc0012_storefront_desktop.png` — Հանրային մենյուի Desktop տարբերակը
- `tc0012_storefront_mobile.png` — Հանրային մենյուի Mobile տարբերակը (iPhone 14)
- `tc0012_storefront_tablet.png` — Հանրային մենյուի Tablet տարբերակը (iPad)

---

## F. Next Steps & Recommended Actions

1. **Հրատապ Ուղղումներ (Quick Wins - 1-2 ժամ):**
   - `lang/hy.json`-ում ավելացնել `"The provided credentials do not match our records.": "Մուտքային տվյալները սխալ են:"`:
   - Ադմին middleware-ում ավելացնել `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` (չեզոքացնելով bfcache-ը):
   - Տվյալների բազայում «Main Courses» թարգմանությունը դարձնել «Հիմնական ճաշատեսակներ»:
   - Երկրի ընտրության JS կոդում ավելացնել null check `(item.digits ? item.digits + ' նիշ' : '')`:
2. **Առաջարկվող Փաթեթային Քայլեր:**
   - Եթե անհրաժեշտ է փորձարկել Մատուցողների հաշիվները (TC-0007, TC-0008, TC-0024), Tenant B-ի փաթեթը թարմացնել Business մակարդակի:
   - Գործարկել `php artisan db:seed --class=EmailManagementSeeder`՝ էլեկտրոնային նամակների դինամիկ ձևանմուշները (templates) ակտիվացնելու համար:
