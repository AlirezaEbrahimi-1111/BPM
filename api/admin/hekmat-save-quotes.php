<?php
/**
 * API: api/admin/hekmat-save-quotes.php
 * جایگزینی کامل لیست حکمت‌ها — هر خط یک جمله (paste دسته‌جمعی)
 *
 *   POST /api/admin/hekmat-save-quotes.php
 *   body: {text: "جمله اول\nجمله دوم\n..."}
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

    $count = HekmatBroadcast::replaceQuotes($db, $rawText);

    echo json_encode([
        'success' => true,
        'count'   => $count,
        'quotes'  => HekmatBroadcast::getQuotes($db),
        'preview' => HekmatBroadcast::previewToday($db),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('hekmat-save-quotes.php failed | ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
