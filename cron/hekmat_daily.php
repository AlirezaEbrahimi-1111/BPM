<?php
/**
 * cron/hekmat_daily.php
 * ارسالِ روزانه‌ی خودکارِ حکمتِ نهج‌البلاغه — ساعتِ ارسال از صفحه‌ی
 * مدیریت (hekmat_settings) خونده می‌شه، نه از خودِ crontab. به همین
 * دلیل این اسکریپت باید هر دقیقه اجرا بشه و خودش تشخیص بده الان وقتشه یا نه:
 *
 *   Crontab:  * * * * * /usr/bin/php /path/to/cron/hekmat_daily.php >> /path/to/cron/logs/hekmat.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

date_default_timezone_set('Asia/Tehran');

if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/HekmatBroadcast.php';

function hekmat_cron_log(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . "] $msg\n";
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . '/hekmat_' . date('Y-m') . '.log', $line, FILE_APPEND);
    echo $line;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $settings = HekmatBroadcast::getSettings($db);

    if ((int) $settings['is_enabled'] !== 1) {
        // فعال نیست — سکوت (لاگ نمی‌کنه تا فایلِ لاگ با اجرایِ هردقیقه‌ای پر نشه)
        exit(0);
    }

    $now = date('H:i');
    $target = sprintf('%02d:%02d', (int) $settings['send_hour'], (int) $settings['send_minute']);
    if ($now !== $target) {
        exit(0);
    }

    $today = date('Y-m-d');
    if ($settings['last_sent_date'] === $today) {
        // امروز قبلاً فرستاده شده (مثلاً با دکمه‌ی ارسال دستی) — دوباره نفرست
        exit(0);
    }

    $result = HekmatBroadcast::runDailySend($db);

    if (!$result['ok']) {
        hekmat_cron_log('رد شد — دلیل: ' . $result['reason']);
    } else {
        hekmat_cron_log("ارسال شد — موفق: {$result['sent']} | ناموفق: {$result['failed']} | جمله: " . mb_substr($result['quote_text'], 0, 40, 'UTF-8') . '...');
    }
} catch (Throwable $e) {
    hekmat_cron_log('خطا: ' . $e->getMessage());
}
