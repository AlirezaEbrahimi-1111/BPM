<?php
/**
 * API: لیستِ اعضایِ یک گفتگویِ گروهی
 * GET /api/chat/group-members.php?conversation_id=1
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
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
        echo json_encode(['success' => false, 'message' => 'شناسه گروه الزامی است']);
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $stmt = $db->prepare("SELECT type, created_by, title, avatar_path FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conv || $conv['type'] === 'direct') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این گفتگو گروهی نیست']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.avatar_path, cp.joined_at
        FROM chat_participants cp
        JOIN users u ON u.id = cp.user_id AND u.is_active = 1 AND u.is_deleted = 0
        WHERE cp.conversation_id = ?
        ORDER BY (u.id = ?) DESC, cp.joined_at ASC
    ");
    $stmt->execute([$conversationId, $conv['created_by']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ownerId = (int) $conv['created_by'];
    $members = array_map(function ($r) use ($ownerId) {
        return [
            'id'         => (int) $r['id'],
            'full_name'  => trim($r['first_name'] . ' ' . $r['last_name']),
            'is_owner'   => (int) $r['id'] === $ownerId,
            'avatar_url' => $r['avatar_path'] ?: null,
        ];
    }, $rows);

    echo json_encode([
        'success'         => true,
        'is_owner'        => $ownerId === $user_id,
        'group_title'     => $conv['title'],
        'group_avatar_url' => $conv['avatar_path'] ?: null,
        'members'         => $members,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat group-members error: " . $e->getMessage());
}
