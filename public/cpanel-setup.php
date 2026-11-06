<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * cPanel Setup, Diagnostics & Repair Tool for menu.elab.am
 *
 * Usage:
 * 1. Open https://menu.elab.am/cpanel-setup.php?key=elab2026 in your browser
 * 2. Check PHP Version, Extensions, Database Connection, and Upload Limits
 * 3. Inspect recent Laravel errors and run Migrations / Symlink / Cache clear
 * 4. Once done, delete this file for security!
 */
$securityKey = 'elab2026';

if (! isset($_GET['key']) || $_GET['key'] !== $securityKey) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Unauthorized</title></head><body style="font-family:sans-serif;padding:40px;text-align:center;">';
    echo '<h1 style="color:#e11d48;">403 Unauthorized</h1>';
    echo '<p>Մուտքն արգելված է։ Խնդրում ենք հարցման վերջում ավելացնել գաղտնաբառը՝ <code>?key='.htmlspecialchars($securityKey).'</code></p>';
    echo '</body></html>';
    exit;
}

// Bootstrap Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="hy">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>cPanel Diagnostics & Repair - menu.elab.am</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0b1329; color: #f8fafc; padding: 24px 16px; margin: 0; }
        .container { max-width: 900px; margin: 0 auto; background: #17213c; border-radius: 14px; padding: 28px; box-shadow: 0 10px 30px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.06); }
        h1 { color: #38bdf8; margin-top: 0; font-size: 24px; display: flex; align-items: center; gap: 8px; }
        h3 { color: #94a3b8; font-size: 14px; margin-top: 24px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .box { background: #080d1a; border: 1px solid #1e293b; border-radius: 8px; padding: 14px; margin: 8px 0; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace; white-space: pre-wrap; font-size: 13px; color: #cbd5e1; line-height: 1.5; word-break: break-all; }
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 12px; }
        .badge-ok { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); }
        .badge-err { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .badge-warn { background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; }
        .card { background: #0c1427; border: 1px solid #1e2d4d; border-radius: 8px; padding: 12px 16px; }
        .warning-box { background: rgba(251,191,36,0.08); border: 1px solid rgba(251,191,36,0.3); color: #fef08a; padding: 14px; border-radius: 8px; margin-top: 20px; font-size: 13px; line-height: 1.5; }
        .success-box { background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.3); color: #86efac; padding: 14px; border-radius: 8px; margin-top: 20px; font-size: 13px; line-height: 1.5; }
        .btn { display: inline-block; background: #38bdf8; color: #071329; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px; margin-top: 15px; border: none; cursor: pointer; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
<div class="container">
    <h1>🚀 cPanel Դիագնոստիկա և Կարգավորում</h1>
    <p style="color: #94a3b8; font-size: 14px; margin-bottom: 20px;">menu.elab.am համակարգի սերվերի կարգավիճակ, բազայի կապ, թղթապանակների իրավունքներ և ֆայլեր:</p>

    <!-- 1. PHP VERSION & CORE ENVIRONMENT -->
    <h3>1. PHP Տարբերակ և Հիմնական Կարգավորումներ</h3>
    <div class="grid-2">
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span>PHP Տարբերակ:</span>
                <?php
                $phpVer = PHP_VERSION;
$isPhpValid = version_compare($phpVer, '8.2.0', '>=');
?>
                <strong><?php echo $phpVer; ?></strong>
                <span class="status-badge <?php echo $isPhpValid ? 'badge-ok' : 'badge-err'; ?>">
                    <?php echo $isPhpValid ? 'OK (>=8.2)' : 'Անհրաժեշտ է >=8.2'; ?>
                </span>
            </div>
            <small style="color:#64748b;font-size:11px;">Laravel 11+ պահանջում է PHP 8.2 կամ 8.3+</small>
        </div>

        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span>Միջավայր (APP_ENV / URL):</span>
                <strong><?php echo config('app.env'); ?></strong>
            </div>
            <small style="color:#64748b;font-size:11px;">APP_URL: <?php echo config('app.url'); ?></small>
        </div>
    </div>

    <!-- 2. DATABASE CONNECTION CHECK -->
    <h3>2. Տվյալների Բազայի Կապ (Database Connection)</h3>
    <div class="card" style="margin-bottom: 12px;">
        <?php
        $dbConnected = false;
$dbError = null;
$dbDriver = config('database.default');
try {
    DB::connection()->getPdo();
    $dbConnected = true;
    $dbName = DB::connection()->getDatabaseName();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span>Կապ բազայի հետ (<?php echo htmlspecialchars($dbDriver); ?>):</span>
            <?php if ($dbConnected) { ?>
                <span class="status-badge badge-ok">Միացված է (<?php echo htmlspecialchars($dbName); ?>)</span>
            <?php } else { ?>
                <span class="status-badge badge-err">Կապի Սխալ</span>
            <?php } ?>
        </div>
        <?php if ($dbError) { ?>
            <div style="color: #f87171; font-size: 12px; margin-top: 6px; font-family: monospace;">
                <?php echo htmlspecialchars($dbError); ?>
            </div>
            <small style="color: #fbbf24; font-size: 11px; display: block; margin-top: 4px;">
                Խնդրում ենք ստուգել <code>.env</code> ֆայլի <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_DATABASE</code>, <code>DB_USERNAME</code>, <code>DB_PASSWORD</code> տվյալները:
            </small>
        <?php } ?>
    </div>

    <!-- 3. PHP EXTENSIONS -->
    <h3>3. PHP Մոդուլներ (PHP Extensions)</h3>
    <div class="grid-2">
        <?php
$extensions = [
    'pdo' => 'PHP PDO Core Database',
    'pdo_pgsql' => 'PostgreSQL Driver (Production Database)',
    'pdo_mysql' => 'MySQL Driver',
    'fileinfo' => 'MIME Type & Image Validation',
    'gd' => 'PHP GD (Նկարների մշակում)',
    'mbstring' => 'Multibyte String (Հայերեն տեքստեր)',
    'openssl' => 'SSL / Encryption',
    'curl' => 'cURL (Արտաքին API հարցումներ)',
    'intl' => 'Internationalization (Թարգմանություններ)',
    'bcmath' => 'BCMath (Ֆինանսական ճշգրիտ հաշվարկներ)',
];

foreach ($extensions as $ext => $desc) {
    $isLoaded = extension_loaded($ext);
    echo '<div class="card">';
    echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">';
    echo '<strong>'.$ext.'</strong>';
    echo $isLoaded
        ? '<span class="status-badge badge-ok">Ակտիվ է</span>'
        : '<span class="status-badge badge-warn">Անջատված է</span>';
    echo '</div>';
    echo '<small style="color:#64748b;font-size:11px;">'.$desc.'</small>';
    echo '</div>';
}
?>
    </div>

    <!-- 4. VITE ASSETS CHECK -->
    <h3>4. Դիզայնի և Սկրիպտների Ֆայլեր (Vite Manifest & Build)</h3>
    <div class="card">
        <?php
$manifestPath = public_path('build/manifest.json');
$hasViteBuild = file_exists($manifestPath);
?>
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span>public/build/manifest.json:</span>
            <?php if ($hasViteBuild) { ?>
                <span class="status-badge badge-ok">Առկա է (CSS/JS build OK)</span>
            <?php } else { ?>
                <span class="status-badge badge-err">ԲԱՑԱԿԱՅՈՒՄ Է (Missing Build)</span>
            <?php } ?>
        </div>
        <small style="color:#64748b;font-size:11px;">
            <?php if ($hasViteBuild) { ?>
                Ֆրոնտենդի բոլոր Tailwind CSS և JS ֆայլերը գեներացված են:
            <?php } else { ?>
                ⚠️ Եթե public/build թղթապանակը չկա հոսթինգում, էջերը կարող են բացվել առանց դիզայնի (կիսատ): Պետք է վերբեռնել public/build թղթապանակը:
            <?php } ?>
        </small>
    </div>

    <!-- 5. STORAGE DIRECTORIES & PERMISSIONS AUTO-FIX -->
    <h3>5. Storage Թղթապանակներ և Իրավունքներ (Permissions Auto-Fix)</h3>
    <div class="box"><?php
    $dirsToEnsure = [
        storage_path('app/public/products'),
        storage_path('app/public/branding'),
        storage_path('framework/views'),
        storage_path('framework/sessions'),
        storage_path('framework/cache'),
        storage_path('logs'),
        base_path('bootstrap/cache'),
    ];

foreach ($dirsToEnsure as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0775, true);
        echo 'Ստեղծվեց թղթապանակ: '.str_replace(base_path().'/', '', $dir)." (0775)\n";
    } else {
        @chmod($dir, 0775);
        echo 'Թղթապանակն առկա է: '.str_replace(base_path().'/', '', $dir)." (OK)\n";
    }
}
?></div>

    <!-- 6. PUBLIC/STORAGE SYMLINK REPAIR -->
    <h3>6. Ֆայլերի Հանրային Կապ (Storage Symlink Repair)</h3>
    <div class="box"><?php
$publicStorage = public_path('storage');
$targetStorage = storage_path('app/public');

if (is_link($publicStorage)) {
    $linkTarget = @readlink($publicStorage);
    // Check if link points to local mac path or non-existent path
    if (! file_exists($publicStorage) || str_contains((string) $linkTarget, '/Users/')) {
        @unlink($publicStorage);
        echo "Հայտնաբերվել և հեռացվել է հին/կոտրված սիմվոլիկ կապը ({$linkTarget})\n";
    }
} elseif (is_dir($publicStorage) && ! is_link($publicStorage)) {
    echo "public/storage-ը սովորական թղթապանակ է (ոչ սիմլինկ): Ֆայլերը կպահպանվեն:\n";
}

if (! file_exists($publicStorage) && ! is_link($publicStorage)) {
    try {
        Artisan::call('storage:link');
        echo Artisan::output();
    } catch (Throwable $e) {
        // Fallback to relative symlink
        @symlink('../storage/app/public', $publicStorage);
        echo 'Ստեղծվեց symlink fallback-ի միջոցով: '.htmlspecialchars($e->getMessage())."\n";
    }
} else {
    echo "public/storage հանրային հասանելիությունն ակտիվ է (OK):\n";
}
?></div>

    <!-- 7. DATABASE MIGRATIONS -->
    <h3>7. Տվյալների Բազայի Միգրացիաներ (Migrations)</h3>
    <div class="box"><?php
if ($dbConnected) {
    try {
        Artisan::call('migrate', ['--force' => true]);
        echo htmlspecialchars(Artisan::output());
    } catch (Throwable $e) {
        echo 'ՍԽԱԼ: '.htmlspecialchars($e->getMessage());
    }
} else {
    echo 'Բազայի հետ կապ չկա, միգրացիաները բաց են թողնվել:';
}
?></div>

    <!-- 8. RECONCILE PERMISSIONS -->
    <h3>8. Իրավունքների Սինխրոնիզացիա (Reconcile Permissions)</h3>
    <div class="box"><?php
if ($dbConnected) {
    try {
        Artisan::call('auth:reconcile-permissions');
        echo htmlspecialchars(Artisan::output());
    } catch (Throwable $e) {
        echo 'ՍԽԱԼ: '.htmlspecialchars($e->getMessage());
    }
} else {
    echo 'Բազայի հետ կապ չկա, իրավունքները բաց են թողնվել:';
}
?></div>

    <!-- 9. CACHE CLEAR -->
    <h3>9. Քեշի Մաքրում (Clear Cache)</h3>
    <div class="box"><?php
try {
    Artisan::call('optimize:clear');
    echo htmlspecialchars(Artisan::output());
} catch (Throwable $e) {
    echo 'ՍԽԱԼ: '.htmlspecialchars($e->getMessage());
}
?></div>

    <!-- 10. RECENT LOGS VIEWER -->
    <h3>10. Վերջին Սխալների Մատյան (storage/logs/laravel.log)</h3>
    <div class="box" style="max-height: 280px; overflow-y: auto;"><?php
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -60);
    if (! empty($lastLines)) {
        echo htmlspecialchars(implode('', $lastLines));
    } else {
        echo 'Մատյանը դատարկ է (սխալներ չկան):';
    }
} else {
    echo 'storage/logs/laravel.log ֆայլը դեռ չի ստեղծվել (սխալներ չկան):';
}
?></div>

    <div class="success-box">
        ✅ <strong>Ստուգումն ավարտված է:</strong> Բոլոր հիմնական գործողությունները (Թղթապանակների իրավունքներ, Symlink, Միգրացիաներ, Իրավունքներ, Քեշի մաքրում) կատարվել են ավտոմատ:
    </div>

    <div class="warning-box">
        💡 <strong>ԻՆՉԻՑ ԿԱՐՈՂ Է ԷՋԸ ԿԻՍԱՏ ԲԱՑՎԵԼ.</strong><br>
        1. <strong>Անհամապատասխան HTTP/HTTPS (Mixed Content):</strong> Համոզվեք, որ կայքը բացվում է <code>https://menu.elab.am</code> հասցեով:<br>
        2. <strong>PHP Տարբերակ:</strong> Եթե cPanel-ում PHP-ն 8.1 կամ ցածր է, փոխեք այն <strong>8.2 կամ 8.3</strong> (cPanel &rarr; <em>Select PHP Version</em>):<br>
        3. <strong>Vite Build ֆայլեր:</strong> Եթե <code>public/build</code> թղթապանակը բացակայում է, վերբեռնեք այն արխիվով հոսթինգի <code>public/build</code>-ում:<br>
        4. <strong>Բազայի Միգրացիաներ:</strong> Եթե վերևում նոր աղյուսակները չեն ավելացել, միգրացիաների սխալը կերևա բաժին 7-ում:
    </div>

    <p style="text-align: center; margin-top: 24px;">
        <a href="/admin/inventory/procurement" class="btn">Գնալ Պահեստի Բաժին &rarr;</a>
        <a href="/admin/menu" class="btn" style="background:#64748b; margin-left: 10px;">Մենյու &rarr;</a>
    </p>
</div>
</body>
</html>
