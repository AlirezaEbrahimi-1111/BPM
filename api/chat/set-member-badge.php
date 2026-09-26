<?php
/**
 * API: نمایش/عدم‌نمایش بج «مدیر» برای یک عضو گروه
 * POST /api/chat/set-member-badge.php
 *   body: { conversation_id, user_id, show_badge: true|false }
 *
 * 🔒 این بج کاملا مستقل از role/permissions واقعیه — هر عضوی (چه مدیر
 * واقعی، چه عضو عادی) می‌تونه این بج رو داشته باشه یا نداشته باشه، بدون
 * اینکه هیچ اختیار خاصی (پین/افزودن‌عضو/حذف‌عضو/عکس) بگیره یا از دست بده.
 * دسترسی: فقط سازنده‌ی گروه — مثل تنظیم اختیارات واقعی.
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
    $targetUserId = (int) ($input['user_id'] ?? 0);
    $showBadge = !empty($input['show_badge']) ? 1 : 0;

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

    if (!chatUserIsGroupCreator($db, $conversationId, $user_id)) {
        http_response_code(403);
        error_log("Chat set-member-badge denied | user_id={$user_id} | conversation_id={$conversationId}");
        echo json_encode(['success' => false, 'message' => 'فقط سازنده‌ی گروه می‌تواند بج را تنظیم کند']);
        exit;
    }

    if ($targetUserId === (int) $conv['created_by']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'سازنده‌ی گروه همیشه بج «سازنده‌ی گروه» را دارد']);
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $targetUserId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'این کاربر عضو گروه نیست']);
        exit;
    }

    $db->prepare("UPDATE chat_participants SET show_admin_badge = ? WHERE conversation_id = ? AND user_id = ?")
        ->execute([$showBadge, $conversationId, $targetUserId]);

    echo json_encode(['success' => true, 'show_badge' => (bool) $showBadge]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat set-member-badge error: " . $e->getMessage());
}
