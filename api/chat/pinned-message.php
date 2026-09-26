<?php
/**
 * API: دریافت پیام سنجاق‌شده‌ی یک گفتگو (اگر باشد)
 * GET /api/chat/pinned-message.php?conversation_id=1
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate');

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

    $conversationId = (int) ($_GET['conversation_id'] ?? 0);
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه گفتگو الزامی است']);
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT c.type, c.created_by, c.pinned_message_id,
               m.message, m.user_id AS sender_id, u.first_name, u.last_name
        FROM chat_conversations c
        LEFT JOIN chat_messages m ON m.id = c.pinned_message_id AND m.is_deleted = 0
        LEFT JOIN users u ON u.id = m.user_id
        WHERE c.id = ?
    ");
    $stmt->execute([$conversationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $canManage = $row && ($row['type'] === 'direct' || chatUserHasGroupPermission($db, $conversationId, $user_id, 'pin'));

    // 🔒 اگر پیام سنجاق‌شده بعدا soft-delete شده باشد، sender_id از جوین NULL می‌شود
    // (نه صرفا pinned_message_id) — همینجا هم به‌عنوان لایه‌ی دوم محافظت در نظر گرفته می‌شود
    if (!$row || !$row['pinned_message_id'] || $row['sender_id'] === null) {
        echo json_encode(['success' => true, 'pinned' => null, 'can_manage' => $canManage], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success'    => true,
        'can_manage' => $canManage,
        'pinned'     => [
            'id'        => (int) $row['pinned_message_id'],
            'snippet'   => mb_substr((string) $row['message'], 0, 100),
            'user_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            'is_own'    => (int) $row['sender_id'] === $user_id,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat pinned-message error: " . $e->getMessage());
}
