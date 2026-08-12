<?php
// api/tickets/restore.php — بازگرداندن تیکت حذف‌شده (فقط کاربر id=1)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد مجاز نیست'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    $user_id = $auth->getUserFromToken();

    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($user_id != 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'مجوز بازگردانی ندارید'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input    = json_decode(file_get_contents('php://input'), true);
    $ticketId = (int)($input['ticket_id'] ?? 0);
    if (!$ticketId) {
        echo json_encode(['success' => false, 'message' => 'شناسه تیکت نامعتبر است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM tickets WHERE id = ? AND deleted_at IS NOT NULL");
    $stmt->execute([$ticketId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'تیکت حذف‌شده یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $db->prepare("UPDATE tickets SET deleted_at = NULL WHERE id = ?");
    $stmt->execute([$ticketId]);

    echo json_encode(['success' => true, 'message' => 'تیکت بازگردانده شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Ticket restore error: " . $e->getMessage() . " | user_id=" . ($user_id ?? 'n/a') . " | ticket_id=" . ($ticketId ?? 'n/a'));
    echo json_encode(['success' => false, 'message' => 'خطا در بازگردانی تیکت'], JSON_UNESCAPED_UNICODE);
}