<?php
/**
 * API: حذفِ کاملِ یک گفتگویِ مستقیم — فقط از دیدِ خودِ کاربرِ درخواست‌دهنده.
 * طرفِ مقابل هیچ اثری نمی‌بیند و پیام‌ها واقعاً حذف نمی‌شن (فقط با همون
 * مکانیزمِ chat_message_hidden که delete-message.php هم استفاده می‌کنه،
 * از دیدِ همین کاربر پنهان می‌شن) — پس اگه بعداً پیامِ جدیدی رد و بدل بشه،
 * گفتگو دوباره توی لیست ظاهر می‌شه.
 *
 * فقط برایِ گفتگوهایِ type='direct' — برایِ گروه معادلش leave-group.php ست.
 *
 * POST /api/chat/delete-conversation.php   body: { conversation_id }
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
    $conversationId = (int) ($input['conversation_id'] ?? 0);
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه گفتگو الزامی است']);
        exit;
    }

    // 🔒 فقط شرکت‌کننده‌های همین گفتگو اجازه دارند
    $stmt = $db->prepare("
        SELECT c.type
        FROM chat_participants cp
        JOIN chat_conversations c ON c.id = cp.conversation_id
        WHERE cp.conversation_id = ? AND cp.user_id = ?
    ");
    $stmt->execute([$conversationId, $user_id]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conv) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }
    if ($conv['type'] !== 'direct') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'برای گروه از «ترک گروه» استفاده کنید']);
        exit;
    }

    $db->prepare("
        INSERT IGNORE INTO chat_message_hidden (message_id, user_id)
        SELECT id, ? FROM chat_messages WHERE conversation_id = ? AND is_deleted = 0
    ")->execute([$user_id, $conversationId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat delete-conversation error: " . $e->getMessage());
}
