<?php
/**
 * API داخلی: اطلاع به مدیران هنگام ثبت یک دستگاه کاملا جدید (در انتظار تأیید)
 * POST /api/internal/notify-new-device.php
 *   header: Authorization: Bearer <go_internal_secret>
 *   body:   { organization_id, user_id, ip }
 *
 * چرا این فایل جدا؟
 *   go-api/internal/attendance/register.go بخش اصلی ثبت ورود/خروج رو
 *   کامل پورت کرده، اما طبق همون مرزی که در go-api/internal/attendance/
 *   admin_devices.go مستندشده، منطق Notification::create() (که شامل
 *   ارسال پیامک async هم می‌شه) عمدا به Go پورت نمی‌شه — پیچیده و
 *   وابسته به سرویس پیامکه. برای این‌که این یک حالت نادر (دستگاه کاملا
 *   جدید + کاربر تأییدنشده) رفتارش عوض نشه، Go فقط همین یک اکشن جانبی
 *   رو با یک تماس داخلی HTTP به همین فایل واگذار می‌کنه — نه کل فرایند.
 *
 * 🔒 این endpoint عمومی نیست — فقط با go_internal_secret (کلید مشترک
 *   config/config.php و go-api/config.json) قابل‌فراخوانیه، نه با JWT کاربر.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/AttendanceNotify.php';

$config = require $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
$expectedSecret = $config['go_internal_secret'] ?? '';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$providedSecret = trim(str_replace('Bearer', '', $authHeader));

if (empty($expectedSecret) || !hash_equals($expectedSecret, $providedSecret)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $organizationId = (int) ($input['organization_id'] ?? 0);
    $userId = (int) ($input['user_id'] ?? 0);
    $ip = (string) ($input['ip'] ?? '');

    if (!$organizationId || !$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ورودی نامعتبر است']);
        exit;
    }

    attendance_notify_managers_new_device($db, $organizationId, $userId, $ip);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("notify-new-device error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
