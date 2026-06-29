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

    $input = json_decode(file_get_contents('php://input'), true);
    $id    = intval($input['id'] ?? 0);
    $name  = trim($input['name'] ?? '');
    $color = trim($input['color'] ?? '#6366f1');
    $icon  = trim($input['icon'] ?? 'bi-tag');

    if (!$id || $name === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه و نام گروه الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // گرفتن گروه (و اطمینان از تعلق به همین سازمان)
    $stmt = $db->prepare("SELECT scope, created_by FROM task_groups WHERE id = ? AND organization_id = ?");
    $stmt->execute([$id, $org_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'گروه یافت نشد']);
        exit;
    }

    // بررسی دسترسی
    if (!canEditGroup($db, $user_id, $group)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'اجازه ویرایش این گروه را ندارید']);
        exit;
    }

    $stmt = $db->prepare("UPDATE task_groups SET name = ?, color = ?, icon = ? WHERE id = ?");
    $stmt->execute([$name, $color, $icon, $id]);

    echo json_encode(['success' => true, 'message' => 'گروه به‌روزرسانی شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("task-groups/update error: " . $e->getMessage());
}

// گروه سازمانی → فقط management+supervisor ؛ گروه شخصی → فقط سازنده
function canEditGroup($db, $user_id, $group) {
    if ($group['scope'] === 'org') {
        $stmt = $db->prepare("SELECT activity_section, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        return $u && $u['activity_section'] === 'management' && $u['role'] === 'supervisor';
    }
    return (int)$group['created_by'] === (int)$user_id;
}
