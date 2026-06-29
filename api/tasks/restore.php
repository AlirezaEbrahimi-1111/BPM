<?php
// api/tasks/restore.php — بازگرداندن تسک حذف‌شده
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد مجاز نیست'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $user_id = requireAuth();
    $user    = getUserInfo($user_id);
    $org_id  = $user['organization_id'];

    $input   = json_decode(file_get_contents('php://input'), true);
    $task_id = (int)($input['task_id'] ?? 0);
    if ($task_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه تسک الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // تسکِ حذف‌شدهٔ همین سازمان
    $stmt = $db->prepare("SELECT creator_id FROM tasks
                          WHERE id = ? AND is_deleted = 1 AND organization_id = ?");
    $stmt->execute([$task_id, $org_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'تسک حذف‌شده یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // مجوز: سازنده یا مدیر (همان منطق حذف تسک)
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    $isCreator = ($task['creator_id'] == $user_id);
    $isManager = ($u && in_array($u['role'], ['management', 'supervisor']));
    if (!$isCreator && !$isManager) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'اجازه بازگردانی این تسک را ندارید'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $db->prepare("UPDATE tasks
                          SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL
                          WHERE id = ? AND organization_id = ?");
    $stmt->execute([$task_id, $org_id]);

    echo json_encode(['success' => true, 'message' => 'تسک بازگردانده شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در بازگردانی تسک'], JSON_UNESCAPED_UNICODE);
}