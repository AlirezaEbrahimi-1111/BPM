<?php
/**
 * API: حذف یک فایل پیوست اطلاعیه
 * POST JSON: { id }
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/announcement-attachments.php';

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT announcement_id, stored_name FROM announcement_attachments WHERE id = ?");
    $stmt->execute([(int) ($input['id'] ?? 0)]);
    $att = $stmt->fetch(PDO::FETCH_ASSOC);

    $user = annAttLoadUser($db, (int) $user_id);
    $ann = $att ? annAttLoad($db, (int) $att['announcement_id']) : null;
    if (!$user || !$ann) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'فایل یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!annAttCanManage($user, $ann)) {
        error_log("announcement attachment delete denied | user_id={$user_id} | announcement_id={$ann['id']}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیر مجاز'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db->prepare("DELETE FROM announcement_attachments WHERE id = ?")->execute([(int) $input['id']]);
    annAttUnlink($att['stored_name']);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log("announcement attachment delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
