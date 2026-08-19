<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  migrate.php — رابط اجرای مهاجرت‌های پایگاه داده
 *  محل: /migrate.php  (ریشهٔ سایت)
 * ───────────────────────────────────────────────────────────────────
 *  نحوهٔ استفاده از مرورگر:
 *    https://bpm.computeryekta.com/migrate.php?key=SECRET&action=status
 *    https://bpm.computeryekta.com/migrate.php?key=SECRET&action=up
 *    https://bpm.computeryekta.com/migrate.php?key=SECRET&action=down
 *    https://bpm.computeryekta.com/migrate.php?key=SECRET&action=up&dry=1
 *
 *  نحوهٔ استفاده از خط فرمان (اگر دسترسی داشتید):
 *    php migrate.php status
 *    php migrate.php up
 *    php migrate.php down
 *
 *  ⚠️ امنیت:
 *    این فایل به اسکیمای دیتابیس دسترسی کامل دارد. حتماً:
 *      ۱) مقدار MIGRATE_SECRET را به یک رشتهٔ تصادفی و طولانی تغییر دهید
 *      ۲) پس از هر بار استفاده، در نظر بگیرید فایل را از سرور بردارید
 * ═══════════════════════════════════════════════════════════════════
 */

// ───────────────────────────────────────────────────────────────
//  ۱) کلید امنیتی — این را حتماً عوض کنید!
// ───────────────────────────────────────────────────────────────
define('MIGRATE_SECRET', 'CHANGE-ME-به-یک-رشتهٔ-تصادفی-طولانی');


// ───────────────────────────────────────────────────────────────
//  ۲) تشخیص محیط اجرا
// ───────────────────────────────────────────────────────────────
$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    $action = $argv[1] ?? 'status';
    $dryRun = in_array('--dry', $argv ?? [], true);
} else {
    // اجرا از مرورگر → کلید لازم است
    if (($_GET['key'] ?? '') !== MIGRATE_SECRET) {
        http_response_code(403);
        exit('دسترسی غیرمجاز.');
    }
    $action = $_GET['action'] ?? 'status';
    $dryRun = !empty($_GET['dry']);

    header('Content-Type: text/html; charset=utf-8');
}


// ───────────────────────────────────────────────────────────────
//  ۳) اتصال به دیتابیس
// ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Migrator.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    exit('اتصال به دیتابیس برقرار نشد.');
}

$migrator = new Migrator($db, __DIR__ . '/migrations');


// ───────────────────────────────────────────────────────────────
//  ۴) اجرای دستور
// ───────────────────────────────────────────────────────────────
$output = [];
$title  = '';

switch ($action) {

    case 'up':
        $title  = $dryRun ? 'پیش‌نمایش اجرا (بدون تغییر واقعی)' : 'اجرای مهاجرت‌ها';
        $output = $migrator->up($dryRun);
        break;

    case 'down':
        $title  = $dryRun ? 'پیش‌نمایش بازگشت (بدون تغییر واقعی)' : 'بازگرداندن آخرین دسته';
        $output = $migrator->down($dryRun);
        break;

    case 'status':
    default:
        $title = 'وضعیت مهاجرت‌ها';
        foreach ($migrator->status() as $row) {
            $output[] = [
                'type' => $row['applied'] ? 'ok' : 'pending',
                'msg'  => ($row['applied'] ? '✅ اجرا شده  ' : '⏳ در انتظار ')
                          . $row['migration'],
            ];
        }
        if (empty($output)) {
            $output[] = ['type' => 'info', 'msg' => 'هیچ فایل مهاجرتی در پوشهٔ migrations نیست.'];
        }
        break;
}


// ───────────────────────────────────────────────────────────────
//  ۵) نمایش نتیجه
// ───────────────────────────────────────────────────────────────
if ($isCli) {
    echo "\n=== {$title} ===\n\n";
    foreach ($output as $line) {
        echo $line['msg'] . "\n";
    }
    echo "\n";
    exit(0);
}

// خروجی برای مرورگر
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>مهاجرت پایگاه داده</title>
    <style>
        body {
            font-family: Tahoma, sans-serif;
            background: #f8fafc;
            padding: 32px;
            color: #1f2937;
        }
        .box {
            max-width: 820px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }
        .head {
            background: #f3f4f6;
            border-bottom: 1px solid #e5e7eb;
            padding: 14px 18px;
            font-weight: bold;
        }
        .body { padding: 8px 0; }
        .line {
            padding: 10px 18px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
            font-family: monospace;
            direction: ltr;
            text-align: left;
        }
        .line:last-child { border-bottom: none; }
        .ok      { color: #1b7b39; }
        .pending { color: #b45309; }
        .error   { color: #b91c1c; background: #fef2f2; }
        .dry     { color: #8e57fe; }
        .info    { color: #6b7280; }
        .nav {
            padding: 14px 18px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .nav a {
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 13px;
            border: 1px solid #e5e7eb;
            color: #374151;
            background: #fff;
        }
        .nav a:hover { background: #f3f4f6; }
        .nav a.danger { color: #b91c1c; border-color: #fecaca; }
    </style>
</head>
<body>
    <div class="box">
        <div class="head"><?= htmlspecialchars($title) ?></div>
        <div class="body">
            <?php foreach ($output as $line): ?>
                <div class="line <?= $line['type'] ?>"><?= htmlspecialchars($line['msg']) ?></div>
            <?php endforeach; ?>
        </div>
        <div class="nav">
            <?php $k = urlencode(MIGRATE_SECRET); ?>
            <a href="?key=<?= $k ?>&action=status">وضعیت</a>
            <a href="?key=<?= $k ?>&action=up&dry=1">پیش‌نمایش اجرا</a>
            <a href="?key=<?= $k ?>&action=up">اجرای مهاجرت‌ها</a>
            <a href="?key=<?= $k ?>&action=down" class="danger">بازگرداندن آخرین دسته</a>
        </div>
    </div>
</body>
</html>
