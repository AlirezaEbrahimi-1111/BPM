<?php
/**
 * API: لیستِ اعضایِ یک گفتگویِ گروهی
 * GET /api/chat/group-members.php?conversation_id=1
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
        SELECT u.id, u.first_name, u.last_name, u.avatar_path, cp.joined_at, cp.role, cp.permissions
        FROM chat_participants cp
        JOIN users u ON u.id = cp.user_id AND u.is_active = 1 AND u.is_deleted = 0
        WHERE cp.conversation_id = ?
        ORDER BY (u.id = ?) DESC, cp.joined_at ASC
    ");
    $stmt->execute([$conversationId, $conv['created_by']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ownerId = (int) $conv['created_by'];

    // 🔒 اختیاراتِ مؤثر از همین ردیفی که همین‌الان خوندیم حل می‌شه (نه یک
    // کوئریِ جداگانه به‌ازایِ هر عضو) — permissions=NULL یعنی پیش‌فرضِ
    // CHAT_GROUP_ADMIN_DEFAULT_PERMISSIONS (بدونِ 'badge' — نگاهِ توضیحِ
    // کاملش در chat-helpers.php)، وگرنه دقیقاً همون آرایه‌ی JSONِ ثبت‌شده
    $resolvePermissions = function (array $r) {
        if ($r['role'] !== 'admin') return [];
        if ($r['permissions'] === null) return CHAT_GROUP_ADMIN_DEFAULT_PERMISSIONS;
        $decoded = json_decode($r['permissions'], true);
        return is_array($decoded) ? array_values(array_intersect($decoded, CHAT_GROUP_ADMIN_PERMISSIONS)) : CHAT_GROUP_ADMIN_DEFAULT_PERMISSIONS;
    };

    $members = array_map(function ($r) use ($ownerId, $resolvePermissions) {
        return [
            'id'          => (int) $r['id'],
            'full_name'   => trim($r['first_name'] . ' ' . $r['last_name']),
            'is_owner'    => (int) $r['id'] === $ownerId,
            'is_admin'    => $r['role'] === 'admin',
            'permissions' => (int) $r['id'] === $ownerId ? CHAT_GROUP_ADMIN_PERMISSIONS : $resolvePermissions($r),
            'avatar_url'  => $r['avatar_path'] ?: null,
        ];
    }, $rows);

    $myPermissions = $ownerId === $user_id ? CHAT_GROUP_ADMIN_PERMISSIONS : chatGroupAdminEffectivePermissions($db, $conversationId, $user_id);

    echo json_encode([
        'success'              => true,
        'is_owner'             => $ownerId === $user_id,
        'can_manage'           => chatUserIsGroupManager($db, $conversationId, $user_id),
        'my_permissions'       => $myPermissions,
        'all_permissions'      => CHAT_GROUP_ADMIN_PERMISSIONS,
        'group_title'          => $conv['title'],
        'group_avatar_url'     => $conv['avatar_path'] ?: null,
        'members'              => $members,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat group-members error: " . $e->getMessage());
}
