<?php
/**
 * API: بی‌صدا/باصدا کردنِ شخصیِ یک گفتگو (تاگل)
 * POST /api/chat/toggle-mute.php   body: { conversation_id: 1 }
 *
 * دقیقاً مثلِ بایگانی: تنظیمی شخصی، فقط برایِ همین کاربر، بدونِ نیاز به
 * دسترسیِ مدیریتی حتی در گروه — چون هیچ اثری روی بقیه ندارد.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
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

    $stmt = $db->prepare("SELECT is_muted FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $user_id]);
    $current = $stmt->fetchColumn();
    if ($current === false) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $newValue = $current ? 0 : 1;
    $db->prepare("UPDATE chat_participants SET is_muted = ? WHERE conversation_id = ? AND user_id = ?")
        ->execute([$newValue, $conversationId, $user_id]);

    echo json_encode(['success' => true, 'is_muted' => (bool) $newValue]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat toggle-mute error: " . $e->getMessage());
}
