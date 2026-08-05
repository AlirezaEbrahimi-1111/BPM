<?php

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    $user_id = requireAuth();
    $user = getUserInfo($user_id);
    $org_id = $user['organization_id'];

    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه گروه الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT scope, created_by FROM task_groups WHERE id = ? AND organization_id = ?");
    $stmt->execute([$id, $org_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'گروه یافت نشد']);
        exit;
    }

    // بررسی دسترسی (همان منطق update)
    $allowed = false;
    if ($group['scope'] === 'org') {
        $me = loadUserForPermissions($db, $user_id);
        $allowed = hasPermission($me, 'manage_task_groups');
    } else {
        $allowed = (int)$group['created_by'] === (int)$user_id;
    }

    if (!$allowed) {
        error_log("task-groups/delete denied (unauthorized group delete) | user_id={$user_id} | group_id={$id} | scope={$group['scope']} | created_by={$group['created_by']} | organization_id={$org_id}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'اجازه حذف این گروه را ندارید']);
        exit;
    }

    $db->beginTransaction();
    // آزاد کردن کارهای متعلق به این گروه
    $db->prepare("UPDATE tasks SET group_id = NULL WHERE group_id = ?")->execute([$id]);
    // حذف نرم (یا حذف کامل — اینجا حذف کامل، چون کارها آزاد شده‌اند)
    $db->prepare("DELETE FROM task_groups WHERE id = ?")->execute([$id]);
    $db->commit();

    echo json_encode(['success' => true, 'message' => 'گروه حذف شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("task-groups/delete error: " . $e->getMessage());
}
