<?php

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';

try {
    $user_id = requireAuth();
    $user = getUserInfo($user_id);
    $org_id = $user['organization_id'];

    $database = new Database();
    $db = $database->getConnection();

    // گروه‌های شخصی همین کاربر + گروه‌های سازمانی همین سازمان
    $stmt = $db->prepare("
        SELECT id, name, color, icon, scope, created_by
        FROM task_groups
        WHERE is_active = 1
          AND organization_id = ?
          AND (
                (scope = 'personal' AND created_by = ?)
             OR (scope = 'org')
          )
        ORDER BY scope DESC, name ASC
    ");
    $stmt->execute([$org_id, $user_id]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'groups' => $groups], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("task-groups/list error: " . $e->getMessage());
}
