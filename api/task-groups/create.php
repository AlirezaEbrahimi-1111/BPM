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
    $name  = trim($input['name'] ?? '');
    $color = trim($input['color'] ?? '#6366f1');
    $icon  = trim($input['icon'] ?? 'bi-tag');
    $scope = ($input['scope'] ?? 'personal') === 'org' ? 'org' : 'personal';

    if ($name === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام گروه الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // گروه سازمانی: فقط management + supervisor
    if ($scope === 'org' && !canManageOrgGroup($db, $user_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط مدیر سازمان می‌تواند گروه سازمانی بسازد']);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO task_groups (organization_id, created_by, name, color, icon, scope)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$org_id, $user_id, $name, $color, $icon, $scope]);

    echo json_encode([
        'success' => true,
        'message' => 'گروه ساخته شد',
        'id' => $db->lastInsertId()
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("task-groups/create error: " . $e->getMessage());
}

// بررسی دسترسی ساخت گروه سازمانی
function canManageOrgGroup($db, $user_id) {
    $me = loadUserForPermissions($db, $user_id);
    return hasPermission($me, 'manage_task_groups');
}