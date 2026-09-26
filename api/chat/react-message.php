<?php
/**
 * API: ری‌اکشن ایموجی روی یک پیام (تاگل)
 * POST /api/chat/react-message.php   body: { message_id: 45, emoji: '👍' }
 *
 * هر کاربر حداکثر یک ری‌اکشن روی هر پیام دارد: اگر همان ایموجی را دوباره
 * بفرستد، ری‌اکشن برداشته می‌شود؛ اگر ایموجی دیگری بفرستد، جایگزین می‌شود.
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

// 🔒 فقط این چند ایموجی مجازند — هم برای سادگی UI، هم برای جلوگیری از ورودی دلخواه
const ALLOWED_REACTION_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

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
    $emoji = trim((string) ($input['emoji'] ?? ''));

    if (!$messageId || !in_array($emoji, ALLOWED_REACTION_EMOJIS, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ورودی نامعتبر است']);
        exit;
    }

    $stmt = $db->prepare("SELECT conversation_id FROM chat_messages WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$messageId]);
    $conversationId = $stmt->fetchColumn();
    if (!$conversationId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'پیام یافت نشد']);
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $stmt = $db->prepare("SELECT emoji FROM chat_message_reactions WHERE message_id = ? AND user_id = ?");
    $stmt->execute([$messageId, $user_id]);
    $existing = $stmt->fetchColumn();

    if ($existing === $emoji) {
        $db->prepare("DELETE FROM chat_message_reactions WHERE message_id = ? AND user_id = ?")->execute([$messageId, $user_id]);
    } else {
        $db->prepare("
            INSERT INTO chat_message_reactions (message_id, user_id, emoji) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), created_at = NOW()
        ")->execute([$messageId, $user_id, $emoji]);
    }

    $stmt = $db->prepare("
        SELECT emoji, COUNT(*) AS cnt, SUM(user_id = ?) AS mine
        FROM chat_message_reactions
        WHERE message_id = ?
        GROUP BY emoji
    ");
    $stmt->execute([$user_id, $messageId]);
    $reactions = array_map(function ($r) {
        return [
            'emoji' => $r['emoji'],
            'count' => (int) $r['cnt'],
            'reacted_by_me' => (int) $r['mine'] > 0,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode(['success' => true, 'reactions' => $reactions], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat react-message error: " . $e->getMessage());
}
