<?php
/**
 * حذف تیکت (soft delete) — فقط کاربر با id = 1
 * مسیر: api/tickets/delete.php
 * POST { ticket_id: int }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';

// ✅ بعد
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$user_id = $auth->getUserFromToken();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'عدم احراز هویت'], JSON_UNESCAPED_UNICODE);
    exit;
}

// فقط کاربر با id = 1
if ($user_id != 1) {
    http_response_code(403);
    error_log("Ticket delete denied | user_id={$user_id}");
    echo json_encode(['success' => false, 'message' => 'شما مجوز حذف تیکت را ندارید'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ticketId = isset($input['ticket_id']) ? intval($input['ticket_id']) : 0;

if (!$ticketId) {
    echo json_encode(['success' => false, 'message' => 'شناسه تیکت نامعتبر است'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // بررسی وجود تیکت
    $stmt = $db->prepare("SELECT id FROM tickets WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$ticketId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'تیکت یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Soft delete — فقط deleted_at رو پر می‌کنیم
    $stmt = $db->prepare("UPDATE tickets SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$ticketId]);

    echo json_encode(['success' => true, 'message' => 'تیکت با موفقیت حذف شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Delete ticket error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}