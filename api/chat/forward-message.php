<?php
/**
 * API: هدایت یک پیامِ موجود به گفتگویِ دیگر
 * POST /api/chat/forward-message.php   body: { message_id: 45, conversation_id: 3 }
 *   conversation_id همان گفتگویِ مقصد است
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
    $targetConversationId = (int) ($input['conversation_id'] ?? 0);

    if (!$messageId || !$targetConversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ورودی نامعتبر است']);
        exit;
    }

    $stmt = $db->prepare("SELECT id, conversation_id, user_id, message FROM chat_messages WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$messageId]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$original) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'پیام یافت نشد']);
        exit;
    }

    // 🔒 برای هدایت کاربر باید هم به گفتگویِ مبدأ (که پیام را می‌بیند) و هم
    // به گفتگویِ مقصد (که می‌خواهد در آن بفرستد) دسترسی داشته باشد
    $stmt = $db->prepare("SELECT id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$original['conversation_id'], $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }
    $stmt->execute([$targetConversationId, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'به گفتگوی مقصد دسترسی ندارید']);
        exit;
    }

    $stmt = $db->prepare("SELECT id, original_name, stored_name, mime_type, file_size FROM chat_attachments WHERE message_id = ?");
    $stmt->execute([$messageId]);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($original['message'] === null && !$attachments) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'پیام خالی قابل هدایت نیست']);
        exit;
    }

    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO chat_messages (conversation_id, user_id, message, forwarded_from_user_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$targetConversationId, $user_id, $original['message'], (int) $original['user_id']]);
    $newMessageId = (int) $db->lastInsertId();

    if ($attachments) {
        $stmt = $db->prepare("
            INSERT INTO chat_attachments (message_id, conversation_id, user_id, original_name, stored_name, mime_type, file_size)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($attachments as $a) {
            $stmt->execute([$newMessageId, $targetConversationId, $user_id, $a['original_name'], $a['stored_name'], $a['mime_type'], $a['file_size']]);
        }
    }

    $db->prepare("UPDATE chat_participants SET last_read_message_id = ? WHERE conversation_id = ? AND user_id = ?")
        ->execute([$newMessageId, $targetConversationId, $user_id]);
    $db->prepare("UPDATE chat_conversations SET updated_at = NOW() WHERE id = ?")->execute([$targetConversationId]);

    $db->commit();

    echo json_encode(['success' => true, 'message_id' => $newMessageId]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat forward-message error: " . $e->getMessage());
}
