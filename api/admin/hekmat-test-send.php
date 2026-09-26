<?php
/**
 * API: api/admin/hekmat-test-send.php
 * ارسال آزمایشی پیامک به یک شماره‌ی دلخواه — بدون تأثیر روی چرخش/تاریخ
 * آخرین ارسال خودکار (فقط برای تست فرمت پیام)
 *
 *   POST /api/admin/hekmat-test-send.php
 *   body: {phone, text?}   — text اختیاریه؛ اگه نباشه، پیش‌نمایش امروز فرستاده می‌شه
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/HekmatBroadcast.php';

try {
    $user_id = requireAuth();
    if ((int) $user_id !== 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز — فقط سوپرادمین'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $phone = trim((string) ($input['phone'] ?? ''));
    $text  = isset($input['text']) ? (string) $input['text'] : null;

    if ($phone === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شماره تلفن الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $result = HekmatBroadcast::sendTest($db, $phone, $text);

    echo json_encode(['success' => $result['ok'], 'message' => $result['message']], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('hekmat-test-send.php failed | ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
