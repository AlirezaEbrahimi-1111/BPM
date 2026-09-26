<?php
/**
 * API: api/admin/hekmat-get.php
 * دریافت همه‌ی اطلاعات صفحه‌ی مدیریت حکمت روزانه — فقط سوپرادمین (id=1)
 *
 *   GET /api/admin/hekmat-get.php
 *   → {success, settings, quotes, recipients, preview, log}
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

    $database = new Database();
    $db = $database->getConnection();

    echo json_encode([
        'success'    => true,
        'settings'   => HekmatBroadcast::getSettings($db),
        'quotes'     => HekmatBroadcast::getQuotes($db),
        'recipients' => HekmatBroadcast::getRecipients($db),
        'preview'    => HekmatBroadcast::previewToday($db),
        'log'        => HekmatBroadcast::getRecentLog($db, 50),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('hekmat-get.php failed | ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
