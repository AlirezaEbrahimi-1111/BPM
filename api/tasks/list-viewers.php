<?php
/**
 * API: فهرست بیننده‌های صریحا اضافه‌شده به یک تسک (task_viewers)
 * GET /api/tasks/list-viewers.php?task_id=123
 */

header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    $user_id = requireAuth();
    $task_id = (int) ($_GET['task_id'] ?? 0);
    if (!$task_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسهٔ کار الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id, creator_id, assignee_id, organization_id FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کار یافت نشد']);
        exit;
    }

    // 🔒 مدیریت دسترسی بینندگان فقط با تعریف‌کننده‌ی کار (یا مدیر سازمانی‌اش)
    // است، نه با مسئول فعلی انجام کار
    $me = loadUserForPermissions($db, $user_id);
    $canManageViewers = (
        (int) $task['creator_id'] === (int) $user_id
        || canManageTargetUser($db, $me, (int) $task['creator_id'])
        || (hasPermission($me, 'view_all_org_tasks') && isSameOrganization($me, $task['organization_id'] ?? 0))
    );

    $stmt = $db->prepare("
        SELECT tv.user_id AS id, TRIM(CONCAT(u.first_name, ' ', u.last_name)) AS full_name,
               tv.can_view_attachments, tv.can_view_history, tv.can_view_checklist
        FROM task_viewers tv
        JOIN users u ON u.id = tv.user_id AND u.is_active = 1 AND u.is_deleted = 0
        WHERE tv.task_id = ?
        ORDER BY tv.created_at ASC
    ");
    $stmt->execute([$task_id]);

    echo json_encode([
        'success'    => true,
        'viewers'    => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'can_manage' => $canManageViewers,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("list-viewers error: " . $e->getMessage());
}
