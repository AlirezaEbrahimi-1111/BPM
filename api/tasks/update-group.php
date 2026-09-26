<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
try {
    $user_id = requireAuth();
    $user = getUserInfo($user_id);
    $org_id = $user['organization_id'];

    $input = json_decode(file_get_contents('php://input'), true);
    $task_id  = intval($input['task_id'] ?? 0);
    // group_id می‌تواند null باشد (حذف از گروه)
    $group_id = isset($input['group_id']) && $input['group_id'] !== '' && $input['group_id'] !== null
        ? intval($input['group_id'])
        : null;

    if (!$task_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // فقط تعریف‌کننده کار مجاز است
    $stmt = $db->prepare("SELECT creator_id, status, is_deleted FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کار یافت نشد']);
        exit;
    }
    if ((int)$task['creator_id'] !== (int)$user_id) {
        error_log("update-group.php denied | user_id={$user_id} | task_id={$task_id}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط تعریف‌کننده کار می‌تواند گروه را تغییر دهد']);
        exit;
    }
    // کار حذف‌شده/کنسل‌شده/متوقف‌شده/تکمیل‌شده دیگه قابل تغییر گروه نیست
    if ((int)$task['is_deleted'] === 1 || in_array($task['status'], ['completed', 'approved', 'stopped', 'rejected'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این کار در وضعیت پایانی است و گروهش قابل تغییر نیست']);
        exit;
    }

    // اگر گروهی تعیین شده، مطمئن شو متعلق به همین سازمان و در دسترس کاربر است
    if ($group_id !== null) {
        $g = $db->prepare("
            SELECT id FROM task_groups
            WHERE id = ? AND organization_id = ? AND is_active = 1
              AND (scope = 'org' OR (scope = 'personal' AND created_by = ?))
        ");
        $g->execute([$group_id, $org_id, $user_id]);
        if (!$g->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'گروه نامعتبر است']);
            exit;
        }
    }

    $db->prepare("UPDATE tasks SET group_id = ? WHERE id = ?")->execute([$group_id, $task_id]);

    echo json_encode(['success' => true, 'message' => 'گروه کار به‌روزرسانی شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("tasks/update-group error: " . $e->getMessage());
}
