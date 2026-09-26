<?php
// api/workflows/restore.php — بازگرداندن روتین حذف‌شده (Undo)
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'متد مجاز نیست'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user_id = requireAuth();
    $user    = getUserInfo($user_id);
    $org_id  = $user['organization_id'];

    if (!requireRole($user_id, ['management', 'supervisor'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'اجازه بازگردانی ندارید'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input       = json_decode(file_get_contents('php://input'), true);
    $instance_id = (int)($input['instance_id'] ?? 0);
    if ($instance_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه روتین الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // فقط روتین حذف‌شدهٔ همین سازمان
    $stmt = $db->prepare("SELECT id FROM workflow_instances
                          WHERE id = :id AND organization_id = :org_id AND is_deleted = 1");
    $stmt->execute(['id' => $instance_id, 'org_id' => $org_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'روتین حذف‌شده یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db->beginTransaction();

    // ۱) بازگرداندن خود روتین
    $stmt = $db->prepare("UPDATE workflow_instances
                          SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL
                          WHERE id = :id AND organization_id = :org_id");
    $stmt->execute(['id' => $instance_id, 'org_id' => $org_id]);

    // ۲) بازگرداندن تسک‌های فرزند پنهان‌شده (وضعیت همان‌طور که بوده می‌ماند)
    $stmt = $db->prepare("UPDATE tasks
                          SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL
                          WHERE workflow_instance_id = :id
                            AND organization_id = :org_id
                            AND is_deleted = 1");
    $stmt->execute(['id' => $instance_id, 'org_id' => $org_id]);

    $db->commit();

    echo json_encode(['success' => true, 'message' => 'روتین بازگردانده شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در بازگردانی روتین'], JSON_UNESCAPED_UNICODE);
}