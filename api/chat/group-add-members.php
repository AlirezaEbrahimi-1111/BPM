<?php
/**
 * API: افزودنِ عضو به گروه — سازنده یا هر مدیرِ گروه مجاز است
 * POST /api/chat/group-add-members.php   body: { conversation_id: 1, member_ids: [4,5] }
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
    $memberIds = array_values(array_unique(array_filter(array_map('intval', $input['member_ids'] ?? []), function ($id) {
        return $id > 0;
    })));

    if (!$conversationId || !$memberIds) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ورودی نامعتبر است']);
        exit;
    }

    $stmt = $db->prepare("SELECT organization_id, type, created_by FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conv || $conv['type'] === 'direct') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این گفتگو گروهی نیست']);
        exit;
    }

    // 🔒 سازنده یا مدیرِ دارایِ اختیارِ اختصاصیِ «add_member» اجازه‌ی افزودنِ عضو داره
    if (!chatUserHasGroupPermission($db, $conversationId, $user_id, 'add_member')) {
        http_response_code(403);
        error_log("Chat group-add-members denied | user_id={$user_id} | conversation_id={$conversationId}");
        echo json_encode(['success' => false, 'message' => 'فقط مدیر گروه می‌تواند عضو اضافه کند']);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
    $stmt = $db->prepare("SELECT id FROM users WHERE id IN ($placeholders) AND organization_id = ? AND is_active = 1 AND is_deleted = 0");
    $stmt->execute(array_merge($memberIds, [$conv['organization_id']]));
    $validMemberIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!$validMemberIds) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'هیچ‌کدام از اعضای انتخاب‌شده معتبر نیستند']);
        exit;
    }

    $stmt = $db->prepare("INSERT IGNORE INTO chat_participants (conversation_id, user_id) VALUES (?, ?)");
    foreach ($validMemberIds as $mid) {
        $stmt->execute([$conversationId, $mid]);
    }

    $db->prepare("UPDATE chat_conversations SET updated_at = NOW() WHERE id = ?")->execute([$conversationId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat group-add-members error: " . $e->getMessage());
}
