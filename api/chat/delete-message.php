<?php
/**
 * API: حذفِ یک پیامِ چت
 * POST /api/chat/delete-message.php   body: { message_id, for_everyone: true|false }
 *
 *   for_everyone=true  → فقط فرستنده مجاز است؛ پیام واقعاً برای هر دو طرف حذف می‌شود
 *                         (chat_messages.is_deleted=1)
 *   for_everyone=false → فقط از دیدِ همین کاربر پنهان می‌شود (chat_message_hidden)
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

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
    $forEveryone = !empty($input['for_everyone']);

    if (!$messageId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه پیام الزامی است']);
        exit;
    }

    $stmt = $db->prepare("SELECT id, conversation_id, user_id FROM chat_messages WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$message) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'پیام یافت نشد']);
        exit;
    }

    // 🔒 فقط شرکت‌کننده‌های همین گفتگو اجازه دارند
    $stmt = $db->prepare("SELECT id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$message['conversation_id'], $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        error_log("Chat delete-message denied (not participant) | user_id={$user_id} | message_id={$messageId}");
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    if ($forEveryone) {
        // 🔒 حذف برای همه فقط برای فرستنده‌ی خودِ پیام مجاز است
        if ((int) $message['user_id'] !== $user_id) {
            http_response_code(403);
            error_log("Chat delete-message-for-everyone denied (not sender) | user_id={$user_id} | message_id={$messageId}");
            echo json_encode(['success' => false, 'message' => 'فقط فرستنده می‌تواند پیام را برای همه حذف کند']);
            exit;
        }
        $db->prepare("UPDATE chat_messages SET is_deleted = 1 WHERE id = ?")->execute([$messageId]);

        // ⚠️ حذف اینجا soft-delete است (is_deleted=1)، نه حذفِ واقعیِ ردیف — پس
        // ON DELETE SET NULL روی pinned_message_id فعال نمی‌شود؛ باید دستی برداریم
        $db->prepare("UPDATE chat_conversations SET pinned_message_id = NULL WHERE id = ? AND pinned_message_id = ?")
            ->execute([$message['conversation_id'], $messageId]);
    } else {
        $db->prepare("
            INSERT IGNORE INTO chat_message_hidden (message_id, user_id) VALUES (?, ?)
        ")->execute([$messageId, $user_id]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat delete-message error: " . $e->getMessage());
}
