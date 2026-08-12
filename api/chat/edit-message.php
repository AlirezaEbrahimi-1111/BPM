<?php
/**
 * API: ویرایشِ متنِ یک پیامِ چت
 * POST /api/chat/edit-message.php   body: { message_id, message }
 *
 *   فقط فرستنده‌ی خودِ پیام مجاز است؛ پیام‌های حاوی فقط پیوست (بدون متن) یا
 *   پیام‌های حذف‌شده قابل‌ویرایش نیستند.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/JalaliHelper.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    $user_id = $auth->getUserFromToken();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $messageId = (int) ($input['message_id'] ?? 0);
    $newMessage = trim($input['message'] ?? '');

    if (!$messageId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه پیام الزامی است']);
        exit;
    }
    if ($newMessage === '') {
        echo json_encode(['success' => false, 'message' => 'متن پیام نمی‌تواند خالی باشد']);
        exit;
    }

    $stmt = $db->prepare("SELECT id, user_id FROM chat_messages WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$message) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'پیام یافت نشد']);
        exit;
    }

    // 🔒 فقط فرستنده‌ی خودِ پیام مجاز به ویرایش است
    if ((int) $message['user_id'] !== $user_id) {
        http_response_code(403);
        error_log("Chat edit-message denied (not sender) | user_id={$user_id} | message_id={$messageId}");
        echo json_encode(['success' => false, 'message' => 'فقط فرستنده می‌تواند پیام را ویرایش کند']);
        exit;
    }

    $db->prepare("UPDATE chat_messages SET message = ?, edited_at = NOW() WHERE id = ?")
        ->execute([$newMessage, $messageId]);

    echo json_encode([
        'success' => true,
        'message' => $newMessage,
        'edited_jalali' => JalaliHelper::Persian(date('H:i')),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat edit-message error: " . $e->getMessage());
}
