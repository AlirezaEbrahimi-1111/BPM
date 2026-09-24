<?php
/**
 * API: api/admin/hekmat-save-settings.php
 * ذخیره‌ی تنظیماتِ ارسالِ روزانه (ساعت/دقیقه، متنِ پایانی، فعال/غیرفعال، نحوه‌ی چرخش)
 *
 *   POST /api/admin/hekmat-save-settings.php
 *   body: {send_hour, send_minute, closing_text, is_enabled, rotation_mode}
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

    $database = new Database();
    $db = $database->getConnection();

    $settings = HekmatBroadcast::saveSettings($db, $input);

    echo json_encode(['success' => true, 'settings' => $settings], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('hekmat-save-settings.php failed | ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
