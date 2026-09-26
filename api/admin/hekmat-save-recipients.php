<?php
/**
 * API: api/admin/hekmat-save-recipients.php
 * جایگزینی کامل لیست شماره‌تلفن‌ها — هر خط یک شماره (paste دسته‌جمعی)
 *
 *   POST /api/admin/hekmat-save-recipients.php
 *   body: {text: "09121234567\n09131234567\n..."}
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
    $rawText = (string) ($input['text'] ?? '');

    $database = new Database();
    $db = $database->getConnection();

    $count = HekmatBroadcast::replaceRecipients($db, $rawText);

    echo json_encode([
        'success'    => true,
        'count'      => $count,
        'recipients' => HekmatBroadcast::getRecipients($db),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('hekmat-save-recipients.php failed | ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
