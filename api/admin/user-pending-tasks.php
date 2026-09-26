<?php
/**
 * API: لیست کارهای باز یک کاربر — قبل از غیرفعال‌سازی نمایش داده می‌شه تا
 * مدیر برای هرکدوم تصمیم بگیره (ارجاع/تکمیل/لغو)
 * GET /api/admin/user-pending-tasks.php?user_id=123
 *
 * «باز» یعنی هرچیزی که این کاربر هنوز باید انجامش می‌داد — همه‌ی وضعیت‌ها
 * به‌جز پایان‌یافته‌ها (completed/approved) و لغوشده‌ها (rejected/stopped).
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    $currentUser = loadUserForPermissions($db, $user_id);
    requirePermission($currentUser, 'manage_users');

    $target_user_id = (int) ($_GET['user_id'] ?? 0);
    if (!$target_user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کاربر الزامی است']);
        exit;
    }

    if (!canManageTargetUser($db, $currentUser, $target_user_id)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT id, title, task_type, status, is_workflow_task, priority,
               due_date, deadline, original_deadline, created_at
        FROM tasks
        WHERE assignee_id = ?
          AND is_deleted = 0
          AND status NOT IN ('completed', 'approved', 'rejected', 'stopped')
        ORDER BY created_at DESC
    ");
    $stmt->execute([$target_user_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'tasks' => $tasks], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("user-pending-tasks error: " . $e->getMessage());
}
