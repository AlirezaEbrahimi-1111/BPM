<?php
/**
 * API: حذفِ دسترسیِ یک «بیننده» از یک تسک
 * POST /api/tasks/remove-viewer.php   { task_id, user_id }
 */

header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $task_id = (int) ($input['task_id'] ?? 0);
    $viewer_id = (int) ($input['user_id'] ?? 0);

    if (!$task_id || !$viewer_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'پارامترهای نامعتبر']);
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

    // 🔒 مدیریتِ دسترسیِ بینندگان فقط با تعریف‌کننده‌یِ کار (یا مدیرِ سازمانی‌اش)
    // است، نه با مسئولِ فعلیِ انجامِ کار
    $me = loadUserForPermissions($db, $user_id);
    $canManageViewers = (
        (int) $task['creator_id'] === (int) $user_id
        || canManageTargetUser($db, $me, (int) $task['creator_id'])
        || (hasPermission($me, 'view_all_org_tasks') && isSameOrganization($me, $task['organization_id'] ?? 0))
    );
    if (!$canManageViewers) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM task_viewers WHERE task_id = ? AND user_id = ?");
    $stmt->execute([$task_id, $viewer_id]);

    echo json_encode(['success' => true, 'message' => 'دسترسی بیننده حذف شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("remove-viewer error: " . $e->getMessage());
}
