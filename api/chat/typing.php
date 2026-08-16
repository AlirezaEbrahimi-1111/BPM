<?php
/**
 * API: اعلامِ «در حالِ تایپ» در یک گفتگو
 * POST /api/chat/typing.php   body: { conversation_id }
 *
 *   هر بار کاربر تایپ می‌کند، این endpoint صدا زده می‌شود (throttle‌شده در
 *   سمتِ کلاینت). typing_until به NOW()+۵ثانیه ست می‌شود؛ طرفِ مقابل با
 *   polling (api/chat/conversations.php یا یک چک سبک‌تر) می‌فهمد.
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
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

    $stmt = $db->prepare("
        UPDATE chat_participants
        SET typing_until = DATE_ADD(NOW(), INTERVAL 5 SECOND)
        WHERE conversation_id = ? AND user_id = ?
    ");
    $stmt->execute([$conversationId, $user_id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat typing error: " . $e->getMessage());
}
