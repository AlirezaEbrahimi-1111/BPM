<?php

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    $user_id = requireAuth();
    $user = getUserInfo($user_id);
    $org_id = $user['organization_id'];

    $database = new Database();
    $db = $database->getConnection();

    $currentUser = loadUserForPermissions($db, $user_id);
    requirePermission($currentUser, 'manage_task_groups');

    // همه گروه‌های سازمان + نام سازنده + تعداد کارهای هر گروه
    $stmt = $db->prepare("
        SELECT g.id, g.name, g.color, g.icon, g.scope, g.created_by, g.created_at,
               CONCAT(COALESCE(c.first_name,''),' ',COALESCE(c.last_name,'')) AS creator_name,
               (SELECT COUNT(*) FROM tasks t WHERE t.group_id = g.id) AS tasks_count
        FROM task_groups g
        LEFT JOIN users c ON g.created_by = c.id
        WHERE g.organization_id = ? AND g.is_active = 1
        ORDER BY g.scope DESC, g.created_at DESC
    ");
    $stmt->execute([$org_id]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // علامت‌گذاری اینکه کدام گروه‌ها متعلق به خودِ مدیر است (قابل ویرایش)
    foreach ($groups as &$g) {
        $g['can_edit'] = ((int)$g['created_by'] === (int)$user_id);
    }
    unset($g);

    echo json_encode(['success' => true, 'groups' => $groups, 'current_user_id' => $user_id], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("task-groups/list-all error: " . $e->getMessage());
}
