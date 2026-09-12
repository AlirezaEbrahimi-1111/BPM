<?php
/**
 * API: ساختِ یک گفتگویِ گروهیِ جدید
 * POST /api/chat/create-group.php   body: { title: '...', member_ids: [1,2,3] }
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
    $title = trim((string) ($input['title'] ?? ''));
    $memberIds = array_values(array_unique(array_filter(array_map('intval', $input['member_ids'] ?? []), function ($id) use ($user_id) {
        return $id > 0 && $id !== $user_id;
    })));

    if ($title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام گروه الزامی است']);
        exit;
    }
    if (!$memberIds) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'حداقل یک عضو دیگر را انتخاب کنید']);
        exit;
    }

    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $orgId = $stmt->fetchColumn();

    // 🔒 همه‌ی اعضا باید از همان سازمان و فعال باشند
    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
    $stmt = $db->prepare("SELECT id FROM users WHERE id IN ($placeholders) AND organization_id = ? AND is_active = 1 AND is_deleted = 0");
    $stmt->execute(array_merge($memberIds, [$orgId]));
    $validMemberIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!$validMemberIds) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'هیچ‌کدام از اعضای انتخاب‌شده معتبر نیستند']);
        exit;
    }

    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO chat_conversations (organization_id, type, title, created_by) VALUES (?, 'group', ?, ?)");
    $stmt->execute([$orgId, $title, $user_id]);
    $conversationId = (int) $db->lastInsertId();

    $stmt = $db->prepare("INSERT INTO chat_participants (conversation_id, user_id) VALUES (?, ?)");
    $stmt->execute([$conversationId, $user_id]);
    foreach ($validMemberIds as $mid) {
        $stmt->execute([$conversationId, $mid]);
    }

    $db->commit();

    error_log("Chat group created | conversation_id={$conversationId} | created_by={$user_id} | member_count=" . (count($validMemberIds) + 1));

    echo json_encode(['success' => true, 'conversation_id' => $conversationId]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat create-group error: " . $e->getMessage());
}
