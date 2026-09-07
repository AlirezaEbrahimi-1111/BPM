<?php
/**
 * includes/page-bootstrap.php
 * ─────────────────────────────────────────────────────────────────
 * بوت‌استرپِ مشترکِ همهٔ صفحاتِ داخلِ pages/ :
 *   سشن + هدرهای امنیتی (از session_start.php) + نسخهٔ اَسِت‌ها +
 *   اتصالِ دیتابیس + احراز هویت + گاردِ ورود.
 *
 * استفاده — اولین خطِ اجراییِ هر صفحه، قبل از هر خروجی:
 *     require_once __DIR__ . '/../includes/page-bootstrap.php';
 *
 * بعد از این خط این متغیرها موجودند (همان نام‌های قبلی):
 *     $db, $database, $auth, $user_id, $__me
 *
 * چکِ دسترسیِ خاصِ هر صفحه (hasPermission / نقش / سوپرادمین) بعد از
 * این require در خودِ صفحه می‌ماند — این فایل فقط «کاربرِ معتبرِ
 * لاگین‌کردهٔ فعال» را تضمین می‌کند؛ اگر نبود، همین‌جا redirect + exit.
 *
 * چرا: تا این ~۱۵ خطِ گاردِ احراز هویت در ۳۹ صفحه کپی نشود و یک نقطهٔ
 * واحدِ بازبینی داشته باشد. رفتار دقیقاً برابرِ همان بلوکِ تکراری است؛
 * تنها تفاوت: مقصدِ ریدایرکتِ «لاگین نیست» از '../index.php' به
 * '/index.php' (مطلق) تغییر کرد تا از داخلِ includes/ درست حل شود —
 * برای همهٔ صفحاتِ pages/ به همان آدرس می‌رسد.
 */

require_once __DIR__ . '/session_start.php';
require_once __DIR__ . '/version.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';

if (!isset($db)) {
    $database = new Database();
    $db = $database->getConnection();
}
$auth = new Auth($db);

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    header('Location: /index.php');
    exit;
}

$__me = loadUserForPermissions($db, (int) $user_id);
if (!$__me) {
    header('Location: /index.php');
    exit;
}
