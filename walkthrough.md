# Walkthrough: QR Menu & Ordering SaaS Application

Ավարտվել է Ռեստորանների, Կաֆեների և Հյուրանոցների համար նախատեսված **SAAS Web Application**-ի մշակումը **Laravel (PHP)**, **MySQL**, **HTML5/CSS3/JS** տեխնոլոգիաներով։

---

## 🎬 Տեսաձայնագրություն (Browser Interactive Demo)

![QR Menu SaaS Platform Walkthrough](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/qrmenu_saas_demo_1789574809833.webp)

---

## 🚀 Իրականացված Մոդուլները (Implemented Features)

### 1. Super Admin Panel (`/superadmin`)
- **Vendor-ների Կառավարում**: Ստեղծել նոր Վենդոր (Կաֆե/Ռեստորան/Հյուրանոց) և ավտոմատ գեներացնել Owner-ի հաշիվը, ընտրել default թեման և բաժանորդագրության պլանը։
- **Գլոբալ Dashboard**: Ընդհանուր վենդորների, մասնաճյուղերի, պատվերների և platform-ի շրջանառության ցուցանիշներ։
- **Թեմաների Catalog**: Գլոբալ storefront թեմաների ցանկ (Modern Bistro Grid, Luxury Dark & Gold, Vibrant Glassmorphic Cafe)։

### 2. Vendor Admin Panel (`/admin`)
- **Multi-Location Hub**: Բազմաթիվ մասնաճյուղերի կառավարում մեկ հաշվից, active location-ի ակնթարթային փոփոխում top navbar-ից։
- **Menu Builder**:
  - Կատեգորիաներ (Armenian/English/Russian թարգմանություններով)։
  - Ուտեստներ (նկար, գին, ալերգեններ, դիետիկ tag-եր, calories & macros՝ սպիտակուցներ, ածխաջրեր, ճարպեր)։
  - Գների և stock-ի override ըստ մասնաճյուղի։
- **AI Menu Import & AI One-Click Translation**:
  - PDF/Word/Text ֆայլերից մենյուի ավտոմատ քաղում (Categories, Dish names, Prices, Descriptions):
  - 1-Click Ավտոմատ թարգմանություն ցանկացած լեզվով (հայերեն, անգլերեն, ռուսերեն, ֆրանսերեն, գերմաներեն, իսպաներեն)։
- **Live Kitchen Orders Board**:
  - Իրական ժամանակում ստացվող պատվերներ (Dine-In, Takeaway, WhatsApp):
  - Կարգավիճակների փոփոխություն (`Pending` -> `Preparing` -> `Ready` -> `Completed`)։
- **Theme & Branding Customizer**:
  - 3+ luxury թեմաների ընտրություն։
  - Brand color palette (Primary/Secondary color pickers, Dark/Light mode):
  - 📱 Live Mobile Storefront Preview iframe:
- **Table QR Code Studio**:
  - Յուրաքանչյուր սեղանի համար QR կոդի գեներացում (`Table 1`, `Table 2`, ...)։
  - Տպագրվող Printable Table Stand Flyer preview & print trigger (`window.print()`):
- **Analytics & Traffic Split**:
  - Օրական այցելությունների վիճակագրություն՝ բաժանված ըստ Dine-In QR scans-ի և Online Ordering traffic-ի։
  - Ամենաշատ պատվիրված ուտեստների վարկանիշ (Top Dishes Ranking)։

### 3. Customer Digital Storefront & PWA (`/m/{vendor_slug}/{location_slug?}`)
- **Progressive Web App (PWA)**: Manifest.json & Service Worker integration, "Add to Home Screen" prompt, native app feel.
- **Multilingual Switcher**: 🇦🇲 Armenian, 🇬🇧 English, 🇷🇺 Russian ակնթարթային լեզվի փոփոխություն։
- **Table Detection**: QR կոդի միջոցով սեղանի համարի ավտոմատ ճանաչում (`?table=4`)։
- **Interactive Order Tray / Cart Drawer**:
  - **Dine-In Mode**: «Ցույց տալ / ուղարկել մատուցողին» (Show to server) ցուցակ։
  - **WhatsApp Ordering**: Անմիջապես WhatsApp-ով պատվերի ուղարկում (Zero commission):

### 5. Order Buttons Fix & Multilingual Labels (New)
- **Ordering Buttons Fix**: Fixed JavaScript syntax/attribute escaping and PHP 8.5 optional array key handling in `ClientStorefrontController.php`.
- **Multilingual Button Labels**:
  - 🇦🇲 Armenian (`hy`): **`Պատվիրել`**
  - 🇬🇧 English (`en`): **`Order`**
  - 🇷🇺 Russian (`ru`): **`Заказать`**
- **WhatsApp Order Button**:
  - 🇦🇲 Armenian (`hy`): **`Պատվիրել WhatsApp-ով`**
  - 🇬🇧 English (`en`): **`Order via WhatsApp`**
  - 🇷🇺 Russian (`ru`): **`Заказать через WhatsApp`**

### 7. Customer Data, Privacy Policy & Terms of Service (New)
- **Customer Information Fields**: Added **Name** (`customer_name`), **Phone Number** (`customer_phone`), and **Email Address** (`customer_email`) to storefront order checkout modal.
- **Privacy & Marketing Consent**: Added marketing consent checkbox with explicit notification that data is confidential and may be used for promotional offers.
- **Legal Document Pages**: Created dedicated public pages:
  - 🔒 **[Privacy Policy (Գաղտնիության Քաղաքականություն)](file:///Users/apple/Projects/qrmenu/resources/views/legal/privacy.blade.php)** (`/privacy-policy`)
  - 📜 **[Terms of Service (Օգտագործման Պայմաններ)](file:///Users/apple/Projects/qrmenu/resources/views/legal/terms.blade.php)** (`/terms-of-service`)
- **Kitchen Panel Integration**: Added **Customer Email** display and **`Marketing Consent`** badge in Live Kitchen Orders (`/admin/orders`).

### 8. Customer CRM Module, Auto-Matching & Excel Export (New)
- **Automatic Customer Matching**: Storefront checkout automatically searches existing customer profiles by **Phone Number** or **Email Address**. If found, updates details and accumulates orders under that profile. If not found, creates a new `Customer` profile.
- **Vendor Admin Customer Management (`/admin/customers`)**:
  - Customer directory table with real-time search, marketing consent filter, lifetime value (LTV), and order counts.
  - **+ Add Customer Modal** (ձեռքով հաճախորդ ավելացնել) & **Edit Customer Modal** (առկա հաճախորդի տվյալների խմբագրում)։
- **Customer Orders Timeline (`/admin/customers/{customer}`)**:
  - Chronological timeline displaying customer's full order history, total spent (LTV), average order value, and dish item breakdowns.
- **Excel / CSV Export (`/admin/customers/export`)**:
  - 1-Click Excel-compatible UTF-8 BOM CSV export containing customer details, opt-in consent, total orders count, and total spent.

### 9. Category Smooth Scroll & ScrollSpy Active Highlighting (New)
- **Continuous Storefront Menu**: Removed category filter hiding logic (`x-show`), allowing all categories and items to stay continuously present on the page.
- **Smooth Category Scroll (`scrollToCat`)**: Clicking any category chip smoothly scrolls the page directly to that category's section with custom sticky header offset (`scroll-margin-top: 75px`).
- **Real-Time ScrollSpy Highlighting (`initScrollSpy`)**: Integrated high-performance `IntersectionObserver` to track the visible section while scrolling up or down. Automatically highlights the corresponding category chip (`cat-chip active`) and auto-scrolls the active chip into view in the horizontally scrollable navbar.
- **All 3 Storefront Themes Updated**: Implemented across `luxury-dark.blade.php`, `modern-bistro.blade.php`, and `vibrant-glass.blade.php`.

---

## 📸 Էկրանի Նկարներ (Screenshots & Recordings)

````carousel
![ScrollSpy Category Smooth Scroll & Active Highlight](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/.tempmediaStorage/media_1789673142879.png)
<!-- slide -->
![Category ScrollSpy Auto-Highlighting on Scroll](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/.tempmediaStorage/media_1789673282316.png)
<!-- slide -->
![Customer Profile & Orders Timeline](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/customer_timeline_aram_1789672547404.png)
<!-- slide -->
![Customer Directory & CRM](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/customers_crm_initial_1789671427084.png)
<!-- slide -->
![Customer Order & Marketing Consent Verified](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/customer_order_verified_1789670094693.png)
<!-- slide -->
![Light Mode Custom Storefront](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/storefront_light_custom_colors_1789664742043.png)
<!-- slide -->
![Modern Bistro Grid Light Mode](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/bistro_grid_light_mode_1789665038433.png)
<!-- slide -->
![Vibrant Glassmorphic Cafe Light Mode](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/glassmorphic_light_mode_1789665134930.png)
<!-- slide -->
![Vendor Dashboard](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/vendor_dashboard_1789574998167.png)
<!-- slide -->
![Menu Builder](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/menu_builder_1789575013537.png)
<!-- slide -->
![AI Menu Import](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/ai_menu_import_1789575029513.png)
<!-- slide -->
![Live Kitchen Orders](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/live_kitchen_orders_1789575055469.png)
<!-- slide -->
![Theme Customizer](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/theme_customizer_1789575080260.png)
<!-- slide -->
![Table QR Studio](/Users/apple/.gemini/antigravity-ide/brain/4849ef08-5ea2-4d19-b821-c237765ac0f9/table_qr_studio_1789575110221.png)
````

---

## 🔐 Դեմո Մուտքի Տվյալներ (Demo Credentials)

| Role | Email | Password | Access URL |
|---|---|---|---|
| **Super Admin (Platform Owner)** | `admin@qrmenu.local` | `password` | `http://127.0.0.1:8000/login` |
| **Vendor Owner (Bistro Yerevan)** | `owner@bistro.am` | `password` | `http://127.0.0.1:8000/login` |
| **Cascades Branch Manager** | `manager@bistro.am` | `password` | `http://127.0.0.1:8000/login` |
| **Customer Storefront** | *(Public)* | *(No auth)* | `http://127.0.0.1:8000/m/bistro-yerevan` |

---

## 💎 Նոր Իրականացված 5 Գլխավոր Մոդուլները (Latest Major Implementations)

### 1. 💳 Բաժանորդագրություն & Վճարումներ (Online Subscription Renewal)
- **Հասանելիություն**: `/admin/subscription`
- Գործընկերը (Vendor) կարող է ինքնուրույն երկարաձգել իր բաժանորդագրությունը կամ անցնել ավելի բարձր պլանի (Starter, Pro, Enterprise)։
- Տևողության զեղչեր՝ 1 ամիս, 3 ամիս, 6 ամիս (-10%), 12 ամիս (-20%)։
- Վճարման տարբերակներ՝ Idram, Telcell, FastShift, ArCa / Ameriabank vPOS, Stripe, Բանկային փոխանցում։
- Ավտոմատ գեներացվում է պաշտոնական հաշիվ-ապրանքագիր (`INV-XXXX-YYYYMMDD`), երկարաձգվում է վավերականության ժամկետը և պահպանվում `subscription_payments` աղյուսակում։

### 2. 💰 Տեղական և Միջազգային Վճարային Համակարգեր (Payment Gateways)
- **Կարգավորումներ**: `/admin/settings` -> «Վճարային Համակարգեր»
- **Հայկական շուկայի համար**:
  - **Idram** (QR & Wallet Web checkout)
  - **Telcell Wallet**
  - **FastShift**
  - **ArCa / Ameriabank vPOS** (MasterCard, Visa, ArCa)
- **Միջազգային շուկայի համար**:
  - **Stripe** (Cards & Apple Pay)
- Կարգավորումներից հնարավոր է ընտրել՝ թույլ տալ միայն կանխիկ / տերմինալով տեղում, թե՞ միացնել օնլայն վճարումները։
- Զամբյուղում հաճախորդը ընտրում է եղանակը՝ 💵 Կանխիկ, 💳 POS Տերմինալ, 🍊 Idram, 🟣 Telcell, ⚡ FastShift, 🇦🇲 ArCa / Visa / MC, 💳 Stripe։
- Օնլայն վճարման դեպքում ավտոմատ redirect է լինում gateway, իսկ հաջողվելուց հետո callback-ը (`/payment/callback/...`) պատվերը նշում է որպես `paid`։

### 3. 🖨️ ESC/POS Ջերմային Տպիչներ (Kitchen Thermal Printers)
- **Կարգավորումներ**: `/admin/settings` -> «Ջերմային Տպիչ (ESC/POS)»
- Աջակցում է **58mm** և **80mm** ջերմային ժապավենների ձևաչափերին։
- Միացման եղանակներ՝
  - **Web Bluetooth API**: Անմիջապես բրաուզերից միանալ Bluetooth տպիչին (ESC/POS binary stream)։
  - **RawBT Android**: 1-Click տպում Android պլանշետներից `rawbt:data:text/plain;base64,...` URL սխեմայով։
  - **Browser System Print**: Ստանդարտ տպիչի պատուհան։
- Ավտոմատ տպում (Auto-print live orders)՝ պատվերը WebSocket-ով խոհանոց հասնելուն պես ավտոմատ տպվում է կտրոնը։
- Պատվերների էջում (`/admin/orders`) յուրաքանչյուր քարտ ունի «🖨️» կոճակ և վճարման կարգավիճակի նշան։

### 4. 🗺️ Սեղանների Ինտերակտիվ Քարտեզ (Interactive Table Floor Plan)
- **Հասանելիություն**: `/admin/floor-plan`
- Սրահների ներդիրներ (Tabs)՝ «Բոլորը», «Գլխավոր Սրահ», «Տեռասա», «VIP Սրահ» և այլն։
- **Drag-and-Drop տեղաշարժ**: Սեղանները կարելի է մկնիկով տեղափոխել սրահի քարտեզի վրա և պահպանել «Պահպանել Քարտեզը» կոճակով։
- **Կենդանի կարգավիճակներ**:
  - 🟢 **Ազատ**: Պատրաստ է նոր հյուրերի համար։
  - 🔴 **Զբաղված**: Ցույց է տալիս ակտիվ պատվերի տևողությունը (ժամանակաչափ՝ օր․ `12ր`), ընդհանուր հաշվի գումարը և սեղմելիս բացում է պատվերի բոլոր ուտեստները։
  - 🟡 **Զանգ Մատուցողին (Pulse Alert)**: Թարթող դեղին անիմացիա, երբ հաճախորդը սեղանից կանչել է մատուցողին կամ խնդրել հաշիվը։
- Նոր սեղանների ավելացում և ձևի ընտրություն (կլոր, քառակուսի, ուղղանկյուն)։

### 5. 🎂 CRM Ավտոմատացում & Ծննդյան Տոներ (Loyalty & Birthday Discounts)
- **Կարգավորումներ**: `/admin/settings` -> «CRM & Հավատարմության Ծրագիր»
- **Հաճախորդների էջ**: `/admin/customers`
- **Ավտոմատ Ծննդյան Զեղչ**: Եթե հաճախորդի ծնունդը նշված միջակայքում է (օր․ ±3 օր), համակարգը զամբյուղում ավտոմատ հաշվարկում է հատուկ զեղչ (օր․ 15% կամ 20%)։
- **Առաջիկա ծնունդների վիջեթ**: Ադմինիստրատորին ցույց է տալիս առաջիկա 14 օրվա բոլոր հաճախորդների ծննդյան օրերը, օրերի հետհաշվարկը և հեռախոսահամարները։
- **1-Click SMS Շնորհավորանք**: «Ուղարկել SMS» կոճակով գործընկերը կարող է ակնթարթորեն ուղարկել անհատական շնորհավորական հաղորդագրություն (Mobipace, SMS.am, Twilio կամ Log պրովայդերներով)։

### 6. 💻 Դեսքթոփում Էկրանի Լայնության Սահմանափակում & Շրջանակ (Desktop Frame Max-Width)
- **Կարգավորումներ**: `/admin/branding` (Քայլ 4) կամ `/admin/settings` (Մասնաճյուղի և Մենյուի Տեղեկություն)
- **Հիմնախնդիր**: Համակարգիչներով (PC/Laptop/iMac) բացելիս մենյուն չի լղոզվում ամբողջ մոնիտորով մեկ (1920px+), այլ ունի նորաոճ, կենտրոնացված շրջանակ։
- **Չափսերի ընտրություն**:
  - `480px` (Կոմպակտ Սմարթֆոն)
  - `600px` (**Լռելյայն / Լավագույն ընտրություն** - Perfect Mobile App Feel)
  - `680px` (Մեծ Սմարթֆոն / Mini Tablet)
  - `768px` (Ստանդարտ Պլանշետ)
  - `100%` (Ամբողջ Էկրանով / Առանց Սահմանափակման)
- **Կիրառված է բոլոր 4 շաբլոնների համար**:
  - `modern-bistro` (Modern Bistro Grid)
  - `luxury-dark` (Luxury Dark & Gold)
  - `vibrant-glass` (Vibrant Glassmorphic Cafe)
  - `minimalist-light` (Minimalist Light)
- **Էսթետիկա**: Դեսքթոփում էջի ֆոնն ունի էլեգանտ մթնոլորտային գրադիենտ, իսկ 600px շրջանակն ունի նուրբ ստվերներ (`box-shadow`), սահմանագծեր, կատեգորիաների ստիկի նավիգացիա, և bottom navigation / modals / toast-եր, որոնք մնում են ճշգրիտ շրջանակի ներսում։
- **Live Preview ինտեգրացիա**: `/admin/branding` էջում ավելացված է «600px» կոճակ, որը թույլ է տալիս անմիջապես տեսնել դեսքթոփ տեսքը live iframe-ում։

### 7. 🔐 Երկփուլային Նույնականացում (2FA - Two-Factor Authentication)
- **Հասանելիություն**: 
  - **Ռեստորանի Ադմին / Աշխատակազմ**: `/admin/profile` կամ վերին վահանակից «2FA & Պրոֆիլ» կոճակով, ինչպես նաև `/admin/settings` -> «Անձնական Անվտանգություն & 2FA»
  - **SuperAdmin**: `/superadmin/settings` -> «Ադմինիստրատորի Անվտանգություն & 2FA»
- **Եղանակներ**:
  - 📧 **Email OTP Code**: 6-նիշ միանգամյա կոդ, որն ուղարկվում է օգտատիրոջ գրանցված էլ․ փոստին (գործում է 10 րոպե)։
  - 📱 **Google Authenticator (TOTP)**: Համատեղելի է Google Authenticator, Microsoft Authenticator, Authy հավելվածների հետ (QR կոդի սկանավորում և 16-նիշ Secret Key)։
- **Պաշտպանվող գործողություններ**:
  1. 🚪 **/login Մուտք**: Երբ 2FA-ն միացված է, ճիշտ էլ․ փոստ և գաղտնաբառ մուտքագրելուց հետո համակարգը պահանջում է 2FA կոդը (`/login/2fa` էջում)։
  2. 🔑 **Գաղտնաբառի Փոփոխություն**: Ընթացիկ գաղտնաբառի և նոր գաղտնաբառի հետ միասին պարտադիր պահանջվում է 6-նիշ 2FA կոդը (հասանելի է «Ուղարկել 2FA կոդ» կոճակով կամ Authenticator հավելվածից)։
  3. ✉️ **Էլ․ Փոստի Փոփոխություն**: Ընթացիկ գաղտնաբառի և նոր էլ․ փոստի հետ միասին պարտադիր պահանջվում է 6-նիշ 2FA կոդը։
- **Ակտիվացում & Անջատում**:
  - Ակտիվացման ժամանակ պահանջվում է ստուգիչ կոդի հաստատում։
  - Անջատման ժամանակ պահանջվում է ընթացիկ գաղտնաբառի հաստատում։



