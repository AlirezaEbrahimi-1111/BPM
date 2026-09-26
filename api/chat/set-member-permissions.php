<?php
/**
 * API: تنظیم اختیارات اختصاصی یک مدیر گروه
 * POST /api/chat/set-member-permissions.php
 *   body: { conversation_id, user_id, permissions: ['pin','add_member',...] }
 *
 * دسترسی: فقط سازنده‌ی گروه — تا خود مدیرها نتونن اختیارات همدیگه رو
 * دست‌کاری کنن. permissions می‌تونه آرایه‌ی خالی باشه (یعنی این مدیر
 * فعلا هیچ اختیار اختصاصی‌ای نداره، فقط عنوان «مدیر» رو داره).
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
    $permissions = $input['permissions'] ?? null;

    if (!$conversationId || !$targetUserId || !is_array($permissions)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ورودی نامعتبر است']);
        exit;
    }

    // 🔒 فقط کلیدهای شناخته‌شده قبول می‌شن — هرچیز دیگه‌ای بی‌سروصدا کنار گذاشته می‌شه
    $cleanPermissions = array_values(array_intersect(array_unique($permissions), CHAT_GROUP_ADMIN_PERMISSIONS));

    $stmt = $db->prepare("SELECT type, created_by FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conv || $conv['type'] === 'direct') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این گفتگو گروهی نیست']);
        exit;
    }

    // 🔒 فقط سازنده — نه خود مدیرها
    if (!chatUserIsGroupCreator($db, $conversationId, $user_id)) {
        http_response_code(403);
        error_log("Chat set-member-permissions denied | user_id={$user_id} | conversation_id={$conversationId}");
        echo json_encode(['success' => false, 'message' => 'فقط سازنده‌ی گروه می‌تواند اختیارات مدیران را تنظیم کند']);
        exit;
    }

    if ($targetUserId === (int) $conv['created_by']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'اختیارات سازنده‌ی گروه قابل‌تغییر نیست — همیشه همه‌ی اختیارات را دارد']);
        exit;
    }

    $targetRole = chatMemberRole($db, $conversationId, $targetUserId);
    if ($targetRole !== 'admin') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این کاربر مدیر گروه نیست']);
        exit;
    }

    $db->prepare("UPDATE chat_participants SET permissions = ? WHERE conversation_id = ? AND user_id = ?")
        ->execute([json_encode($cleanPermissions), $conversationId, $targetUserId]);

    echo json_encode(['success' => true, 'permissions' => $cleanPermissions]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat set-member-permissions error: " . $e->getMessage());
}
