<?php
/**
 * API: حذفِ یک عضو از گروه — فقط سازنده‌ی گروه مجاز است
 * POST /api/chat/group-remove-member.php   body: { conversation_id: 1, user_id: 4 }
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
    $targetUserId = (int) ($input['user_id'] ?? 0);

    if (!$conversationId || !$targetUserId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ورودی نامعتبر است']);
        exit;
    }

    $stmt = $db->prepare("SELECT type, created_by FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conv || $conv['type'] === 'direct') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این گفتگو گروهی نیست']);
        exit;
    }

    // 🔒 فقط سازنده‌ی گروه اجازه‌ی حذفِ عضو دارد
    if ((int) $conv['created_by'] !== $user_id) {
        http_response_code(403);
        error_log("Chat group-remove-member denied | user_id={$user_id} | conversation_id={$conversationId}");
        echo json_encode(['success' => false, 'message' => 'فقط مدیرِ گروه می‌تواند عضو حذف کند']);
        exit;
    }

    if ($targetUserId === (int) $conv['created_by']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'مدیرِ گروه نمی‌تواند خودش را حذف کند؛ برای خروج از «خروج از گروه» استفاده کنید']);
        exit;
    }

    $db->prepare("DELETE FROM chat_participants WHERE conversation_id = ? AND user_id = ?")
        ->execute([$conversationId, $targetUserId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat group-remove-member error: " . $e->getMessage());
}
