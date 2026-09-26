<?php
/**
 * API: خلاصه‌ی ری‌اکشن‌های همه‌ی پیام‌های یک گفتگو (برای poll دوره‌ای)
 * GET /api/chat/reactions.php?conversation_id=1
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
        SELECT r.message_id, r.emoji, COUNT(*) AS cnt, SUM(r.user_id = ?) AS mine
        FROM chat_message_reactions r
        JOIN chat_messages m ON m.id = r.message_id
        WHERE m.conversation_id = ? AND m.is_deleted = 0
        GROUP BY r.message_id, r.emoji
    ");
    $stmt->execute([$user_id, $conversationId]);

    $byMessage = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $byMessage[$r['message_id']][] = [
            'emoji'         => $r['emoji'],
            'count'         => (int) $r['cnt'],
            'reacted_by_me' => (int) $r['mine'] > 0,
        ];
    }

    echo json_encode(['success' => true, 'reactions' => $byMessage], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat reactions error: " . $e->getMessage());
}
