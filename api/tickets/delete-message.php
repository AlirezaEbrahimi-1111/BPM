<?php
/**
 * حذفِ منطقیِ یک پیامِ تیکت (soft delete) — فقط کاربر با id = 1
 * (هم‌راستا با api/tickets/delete.php و api/tickets/restore.php)
 * مسیر: api/tickets/delete-message.php
 * POST { message_id: int }
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

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
    error_log("Ticket message delete denied | user_id={$user_id}");
    echo json_encode(['success' => false, 'message' => 'شما مجوز حذف پیام را ندارید'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$messageId = isset($input['message_id']) ? intval($input['message_id']) : 0;

if (!$messageId) {
    echo json_encode(['success' => false, 'message' => 'شناسه پیام نامعتبر است'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $db->prepare("SELECT id, ticket_id FROM ticket_messages WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$message) {
        echo json_encode(['success' => false, 'message' => 'پیام یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Soft delete — فقط deleted_at رو پر می‌کنیم
    $stmt = $db->prepare("UPDATE ticket_messages SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$messageId]);

    // ثبت در تاریخچه‌ی تیکت
    $stmt = $db->prepare("
        INSERT INTO ticket_history (ticket_id, user_id, action, old_value, new_value, created_at)
        VALUES (?, ?, 'message_deleted', ?, NULL, NOW())
    ");
    $stmt->execute([$message['ticket_id'], $user_id, (string) $messageId]);

    echo json_encode(['success' => true, 'message' => 'پیام حذف شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Delete ticket message error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در پردازش درخواست'], JSON_UNESCAPED_UNICODE);
}
