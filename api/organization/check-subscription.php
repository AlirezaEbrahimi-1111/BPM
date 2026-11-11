<?php
/**
 * API: وضعیت پایانِ دسترسیِ سازمان (برای هشدار «اشتراک رو به پایان»)
 * GET /api/organization/check-subscription.php
 *
 * 🔒 منبع: سیستم پلن (includes/plan-access.php::planEndInfo) — همان چیزی که قفل وب/اپ
 * را تعیین می‌کند. قبلا جدول قدیمی subscriptions خوانده می‌شد که با تمدید دستیِ دورهٔ
 * رایگان یا پلن دائمی هماهنگ نبود. محاسبهٔ روز با تاریخ تهران.
 *
 * خروجی (شکل قدیمی حفظ شده + فیلدهای تازه):
 *   has_subscription  false یعنی پایانی ندارد (پلن دائمی) → هشداری لازم نیست
 *   days_remaining    روز مانده (۰ = از امروز قفل)
 *   status            active | expired
 *   kind              trial | paid
 *   end_date          Y-m-d
 *   can_manage        کاربر سرپرست سازمان است (هشدار و دکمهٔ خرید فقط برای او)
 *
 * ⚠️ داخل باندل هدر هم include می‌شود — exit وسط فایل نگذارید.
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-access.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    $me = loadUserForPermissions($db, (int) $user_id);
    $orgId = (int) ($me['organization_id'] ?? 0);

    if ($orgId <= 0) {
        echo json_encode(['success' => false, 'message' => 'سازمان کاربر یافت نشد'], JSON_UNESCAPED_UNICODE);
    } else {
        $end = planEndInfo($db, $orgId);
        $canManage = isOrgWideRole($me) && !in_array((int) $user_id, getSuperAdminIds(), true);

        if ($end['kind'] === null) {
            echo json_encode([
                'success' => true,
                'has_subscription' => false,
                'days_remaining' => null,
                'status' => 'active',
                'can_manage' => $canManage,
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => true,
                'has_subscription' => true,
                'days_remaining' => $end['days_remaining'],
                'end_date' => $end['end_date'],
                'kind' => $end['kind'],
                'status' => $end['days_remaining'] > 0 ? 'active' : 'expired',
                'can_manage' => $canManage,
            ], JSON_UNESCAPED_UNICODE);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
    error_log("Check subscription error: " . $e->getMessage());
}
