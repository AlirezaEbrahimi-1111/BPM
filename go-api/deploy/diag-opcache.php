<?php
/**
 * تشخیص یک‌باره: آیا مقدار activity_unit توی خود دیتابیس واقعا خالیه،
 * یا PHP‌ی وب‌سرور (Apache/PHP-FPM) هنوز نسخه‌ی قدیمی کامپایل‌شده‌ی
 * middleware.php رو از opcache سرو می‌کنه؟
 *
 * این اسکریپت با PHP CLI اجرا می‌شه (نه از راه وب)، پس یه پراسس کاملا
 * تازه‌ست و opcache‌ی وب‌سرور روش اثری نداره — اگه نتیجه‌ی این با
 * خروجی endpoint وب فرق داشت، یعنی opcache‌ی وب‌سرور بیاته.
 *
 *   php go-api/deploy/diag-opcache.php <user_id>
 */

$userId = isset($argv[1]) ? (int) $argv[1] : 0;
if (!$userId) {
    fwrite(STDERR, "Usage: php diag-opcache.php <user_id>\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
require $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';

$db = (new Database())->getConnection();

// ۱) مقدار خام دیتابیس — حقیقت مطلق
$stmt = $db->prepare("SELECT activity_unit, official_code, manager_id, manager_name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$raw = $stmt->fetch(PDO::FETCH_ASSOC);
echo "۱) مقدار خام دیتابیس:\n";
echo "   " . json_encode($raw, JSON_UNESCAPED_UNICODE) . "\n\n";

// ۲) نتیجه‌ی صدازدن خود getUserInfo() — با یک CLI PHP کاملا تازه
require $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
$fresh = getUserInfo($userId);
echo "۲) نتیجه‌ی getUserInfo() با یک پراسس CLI تازه (بدون اثر opcache‌ی وب‌سرور):\n";
echo "   activity_unit موجوده؟ " . (array_key_exists('activity_unit', $fresh ?? []) ? 'بله' : 'خیر — یعنی opcache یا کد روی سرور هنوز قدیمیه!') . "\n";
echo "   " . json_encode($fresh, JSON_UNESCAPED_UNICODE) . "\n\n";

// ۳) وضعیت opcache
if (function_exists('opcache_get_status')) {
    $st = opcache_get_status(false);
    echo "۳) opcache فعاله؟ " . ($st ? 'بله' : 'خیر') . "\n";
} else {
    echo "۳) opcache_get_status وجود نداره (احتمالا opcache غیرفعاله برای CLI)\n";
}
