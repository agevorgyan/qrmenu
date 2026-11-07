# QR Menu SaaS — Տեղադրման Ուղեցույց cPanel Հոսթինգում (cPanel Deployment Guide)

Սույն ուղեցույցը քայլ առ քայլ ներկայացնում է **QR Menu SaaS** համակարգի ամբողջական տեղադրման և գործարկման գործընթացը cPanel վեբ հոսթինգում (Shared Hosting կամ VPS cPanel):

---

## 1. Համակարգային Պահանջներ (System Requirements)

cPanel-ում մինչև ֆայլերի տեղադրումը անհրաժեշտ է ապահովել հետևյալ պարամետրերը․

- **PHP Տարբերակ**․ **PHP 8.3** կամ **8.4** (cPanel-ում բացեք `Select PHP Version` կամ `MultiPHP Manager` և ընտրեք PHP 8.3+)։
- **Անհրաժեշտ PHP Ընդլայնումներ (Extensions)**․
  - `bcmath` (Ֆինանսական գործարքների բարձր ճշգրտության հաշվարկների համար)
  - `ctype`, `curl`, `dom`, `fileinfo`
  - `gd` կամ `imagick` (Նկարների և QR կոդերի մշակման համար)
  - `intl` (Բազմալեզու համակարգի և տեղայնացման համար)
  - `mbstring`, `openssl`, `tokenizer`, `xml`, `zip`
  - `pdo`, `pdo_pgsql` (եթե օգտագործվում է PostgreSQL) կամ `pdo_mysql` (եթե օգտագործվում է MySQL/MariaDB)
- **PHP Memory & Execution Limits** (`MultiPHP INI Editor`-ում)․
  - `memory_limit` = `512M` (կամ նվազագույնը `256M`)
  - `upload_max_filesize` = `64M`
  - `post_max_size` = `64M`
  - `max_execution_time` = `120`

---

## 2. Ֆայլերի Նախապատրաստում (Local Packaging)

Քանի որ cPanel հոսթինգների մեծ մասում Node.js/Vite գործիքները բացակայում են կամ ունեն սահմանափակ RAM, ֆրոնթենդ ռեսուրսները կոմպիլյացվում են լոկալ միջավայրում նախքան վերբեռնումը․

```bash
# 1. Գործարկեք պատրաստման ավտոմատ սկրիպտը
chmod +x scripts/pack-cpanel.sh
./scripts/pack-cpanel.sh
```

Սկրիպտը կստեղծի **`qrmenu-production-cpanel.zip`** արխիվը՝ ներառելով `vendor/` թղթապանակը, կոմպիլյացված Vite ասեթները (`public/build/`) և բացառելով ավելորդ ֆայլերը (`.git`, `node_modules`, `tests`):

---

## 3. Ֆայլերի Տեղադրում cPanel-ում (2 Տարբերակ)

### Տարբերակ A (Առաջարկվող / Ամենաանվտանգ)․ Project Root-ը `public_html`-ից դուրս

Սա Laravel-ի համար համաշխարհային լավագույն ստանդարտն է, որպեսզի `.env` և կոդի ֆայլերը երբեք հասանելի չլինեն վեբ բրաուզերից։

1. Մուտք գործեք cPanel -> **File Manager** (Ֆայլերի կառավարիչ):
2. Ստեղծեք նոր թղթապանակ ձեր home դիրեկտորիայում (օրինակ՝ `/home/username/qrmenu`):
3. Վերբեռնեք `qrmenu-production-cpanel.zip`-ը այդ թղթապանակում և սեղմեք **Extract** (Արխիվացնել):
4. Մուտք գործեք cPanel -> **Domains** (Դոմեններ)․
   - Ձեր դոմենի համար **Document Root** դաշտում նշեք՝  
     `/home/username/qrmenu/public`  
     *(ոչ թե `public_html`, այլ հենց նախագծի `public` ենթաթղթապանակը)*:

---

### Տարբերակ B (Եթե հոսթինգը թույլ չի տալիս փոխել `public_html` Document Root-ը)

Եթե ձեր հիմնական դոմենը կոշտ կապված է `public_html`-ին․

1. Բոլոր նախագծի ֆայլերը քաղեք `/home/username/qrmenu/` թղթապանակում։
2. `/home/username/qrmenu/public/` թղթապանակի **ամբողջ պարունակությունը** (ներառյալ `build/`, `.htaccess`, `index.php`, `robots.txt`) տեղափոխեք կամ պատճենեք `/home/username/public_html/` դիրեկտորիա։
3. Խմբագրեք `/home/username/public_html/index.php` ֆայլը՝ ճանապարհները թարմացնելով դեպի `qrmenu`․
   ```php
   // public_html/index.php
   use Illuminate\Http\Request;

   define('LARAVEL_START', microtime(true));

   // Ուղղեք autoload և bootstrap ճանապարհները
   require __DIR__.'/../qrmenu/vendor/autoload.php';

   $app = require_once __DIR__.'/../qrmenu/bootstrap/app.php';

   $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

   $response = $kernel->handle(
       $request = Request::capture()
   )->send();

   $kernel->terminate($request, $response);
   ```

---

## 4. Տվյալների Բազայի Ստեղծում (Database Setup)

### Եթե cPanel-ում օգտագործում եք PostgreSQL (Առաջարկվող)․
1. cPanel -> **PostgreSQL Database Wizard**
2. Ստեղծեք բազա (օր.՝ `username_qrmenu`)
3. Ստեղծեք օգտատեր (օր.՝ `username_qruser`) և գաղտնաբառ
4. Օգտատիրոջը կցեք բազային՝ տալով **ALL PRIVILEGES**

### Եթե cPanel-ում օգտագործում եք MySQL / MariaDB․
1. cPanel -> **MySQL Database Wizard**
2. Ստեղծեք բազա (օր.՝ `username_qrmenu`)
3. Ստեղծեք օգտատեր (օր.՝ `username_qruser`) և գաղտնաբառ
4. Օգտատիրոջը կցեք բազային՝ տալով **ALL PRIVILEGES**

---

## 5. `.env` Ֆայլի Կարգավորում

cPanel File Manager-ում `/home/username/qrmenu/` թղթապանակում ստեղծեք `.env` ֆայլը (կամ պատճենեք `.env.example`-ից) և լրացրեք արժեքները․

```dotenv
APP_NAME="QR Menu SaaS"
APP_ENV=production
APP_KEY=base64:c29tZXJhbmRvbXNlY3JldGtleWZvcnRlc3RpbmcxMjM= # Կամ գեներացրեք php artisan key:generate
APP_DEBUG=false
APP_URL=https://yourdomain.com

APP_LOCALE=hy
APP_FALLBACK_LOCALE=en

# Տվյալների բազա (PostgreSQL օրինակ)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=username_qrmenu
DB_USERNAME=username_qruser
DB_PASSWORD=YourStrongPasswordHere

# ԿԱՄ MySQL օրինակ (եթե MySQL եք ընտրել)․
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=username_qrmenu
# DB_USERNAME=username_qruser
# DB_PASSWORD=YourStrongPasswordHere

# Սեսիաներ, Քեշ և Հերթեր (cPanel-ի համար ամենակայուն տարբերակը)
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public

# Էլ․ Փոստ (SMTP - կարող եք օգտագործել cPanel Email հաշիվը)
MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=465
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=YourEmailPasswordHere
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="QR Menu SaaS"
```

---

## 6. Միգրացիաներ և Սկզբնական Կարգավորում (Artisan Commands)

Բացեք cPanel -> **Terminal** (կամ միացեք SSH-ով) և մուտք գործեք նախագծի թղթապանակ․

```bash
cd /home/username/qrmenu

# 1. Գեներացրեք գաղտնագրման բանալին (եթե .env-ում դեռ չկա)
php artisan key:generate --force

# 2. Գործարկեք բոլոր աղյուսակների միգրացիաները
php artisan migrate --force

# 3. Տեղադրեք սկզբնական տվյալները (Roles, Permissions, Plans, Default COA)
php artisan db:seed --force

# 4. Ստեղծեք ֆայլերի հրապարակային սիմվոլիկ հղումը (storage symlink)
php artisan storage:link

# 5. Կատարեք արտադրողականության օպտիմալացում
php artisan optimize
```

> **Նշում (Առանց Terminal-ի հոսթինգների համար)**․ Եթե ձեր cPanel-ում Terminal գործիքը անջատված է, կարող եք cPanel Cron Jobs բաժնում 1 անգամով գործարկել հրամանները կամ ժամանակավոր ստեղծել հատուկ setup ֆայլ, գործարկել բրաուզերով և անմիջապես ջնջել։

---

## 7. Թղթապանակների Իրավունքներ (Permissions)

cPanel File Manager-ում ստուգեք, որ հետևյալ թղթապանակներն ունեն գրելու իրավունք (Permission `775` կամ `755`)․
- `storage/` (և դրա բոլոր ենթաթղթապանակները)
- `bootstrap/cache/`

```bash
chmod -R 775 storage bootstrap/cache
```

---

## 8. Ավտոմատացում (cPanel Cron Jobs)

QR Menu-ի պլանավորված ֆինանսական գործողությունների, բաժանորդագրությունների ստուգման, ռեզերվացիաների հիշեցումների և ֆոնային հերթերի (Queue) համար անհրաժեշտ է միացնել Cron․

1. cPanel-ում բացեք **Cron Jobs** (Պլանավորված առաջադրանքներ)։
2. Պարբերականությունը ընտրեք՝ **Once Per Minute** (`* * * * *`):
3. Հրամանի դաշտում տեղադրեք հետևյալ տողը (ճշտեք ձեր PHP-ի և home դիրեկտորիայի ճանապարհը)․

```bash
* * * * * /usr/local/bin/php /home/username/qrmenu/artisan schedule:run >> /dev/null 2>&1
```

*(Եթե օգտագործում եք cPanel EA PHP 8.3, ապա՝ `/opt/cpanel/ea-php83/root/usr/bin/php /home/username/qrmenu/artisan schedule:run >> /dev/null 2>&1`)*:

### Ֆոնային Հերթերի Աշխատանք (Queue Worker cPanel-ում)
Եթե հոսթինգում չկա Supervisor (դեյմոն), ապա ավելացրեք երկրորդ Cron առաջադրանքը՝ հերթերի ավտոմատ մշակման համար (ամեն 5 րոպեն մեկ)․

```bash
*/5 * * * * /usr/local/bin/php /home/username/qrmenu/artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

---

## 9. SSL Անվտանգություն (HTTPS)

1. cPanel -> **SSL/TLS Status**
2. Սեղմեք **Run AutoSSL**՝ անվճար Let's Encrypt / cPanel SSL սերտիֆիկատ ստանալու համար։
3. Նախագծի `public/.htaccess` ֆայլը արդեն իսկ ավտոմատ կերպով բոլոր HTTP հարցումները վերահասցեագրում է դեպի ապահով **HTTPS**։

---

## 10. Հնարավոր Խնդիրներ և Լուծումներ (Troubleshooting)

| Խնդիր | Պատճառ | Լուծում |
| :--- | :--- | :--- |
| **500 Internal Server Error** | Թույլտվությունների կամ `.env`-ի սխալ | Ստուգեք `storage/logs/laravel.log` ֆայլը։ Համոզվեք, որ `storage` թղթապանակը `775` է։ |
| **Vite Manifest Not Found** | Ֆրոնթենդ ասեթները կոմպիլյացված չեն | Համոզվեք, որ `public/build/manifest.json` ֆայլը առկա է (կատարեք `./scripts/pack-cpanel.sh`): |
| **403 Forbidden** | Ֆայլերի սխալ դասավորություն | Համոզվեք, որ Document Root-ը նայում է `public` թղթապանակին։ |
| **Images / Attachments Not Showing** | Բացակայում է storage symlink-ը | Կատարեք `php artisan storage:link` (կամ cPanel File Manager-ում ստեղծեք symlink `public/storage` -> `storage/app/public`): |

