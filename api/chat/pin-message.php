<?php
/**
 * API: سنجاق‌کردن یک پیام در گفتگو
 * POST /api/chat/pin-message.php   body: { conversation_id: 1, message_id: 45 }
 *
 * دسترسی: در گفتگوی مستقیم هر دو طرف مجازند؛ در گروه سازنده یا مدیری
 * که اختیار اختصاصی «pin» را دارد (chatUserHasGroupPermission).
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/chat-helpers.php';

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
    $conversationId = (int) ($input['conversation_id'] ?? 0);
    $messageId = (int) ($input['message_id'] ?? 0);

    if (!$conversationId || !$messageId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ورودی نامعتبر است']);
        exit;
    }

    $stmt = $db->prepare("SELECT type, created_by FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conv) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'گفتگو یافت نشد']);
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // 🔒 در گروه فقط سازنده یا مدیر دارای اختیار «pin» اجازه‌ی سنجاق‌کردن دارن؛ در گفتگوی مستقیم هر دو طرف
    if ($conv['type'] !== 'direct' && !chatUserHasGroupPermission($db, $conversationId, $user_id, 'pin')) {
        http_response_code(403);
        error_log("Chat pin-message denied | user_id={$user_id} | conversation_id={$conversationId}");
        echo json_encode(['success' => false, 'message' => 'فقط مدیر گروه می‌تواند پیام سنجاق کند']);
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM chat_messages WHERE id = ? AND conversation_id = ? AND is_deleted = 0");
    $stmt->execute([$messageId, $conversationId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'پیام یافت نشد']);
        exit;
    }

    $db->prepare("UPDATE chat_conversations SET pinned_message_id = ? WHERE id = ?")->execute([$messageId, $conversationId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat pin-message error: " . $e->getMessage());
}
