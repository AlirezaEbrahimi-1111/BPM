<?php
// api/workflows/delete.php — حذف نرم یک روتین (workflow instance)
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

    // فقط POST مجاز است
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'متد مجاز نیست'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user_id = requireAuth();
    $user    = getUserInfo($user_id);
    $org_id  = $user['organization_id'];

    // فقط مدیر (واحد management) اجازه حذف دارد — در صورت نیاز این شرط را تغییر بده
    if (!requireRole($user_id, ['management', 'supervisor'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'اجازه حذف ندارید'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // دریافت شناسه روتین
    $input       = json_decode(file_get_contents('php://input'), true);
    $instance_id = (int)($input['instance_id'] ?? 0);
    if ($instance_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه روتین الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // اطمینان از تعلق روتین به همین سازمان و حذف‌نشده بودن
    $stmt = $db->prepare("SELECT id FROM workflow_instances
                          WHERE id = :id AND organization_id = :org_id AND is_deleted = 0");
    $stmt->execute(['id' => $instance_id, 'org_id' => $org_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'روتین یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db->beginTransaction();

    // ۱) حذف نرم خود روتین
    $stmt = $db->prepare("UPDATE workflow_instances
                          SET is_deleted = 1, deleted_at = NOW(), deleted_by = :uid
                          WHERE id = :id AND organization_id = :org_id");
    $stmt->execute(['uid' => $user_id, 'id' => $instance_id, 'org_id' => $org_id]);

    // ۲) پنهان‌کردن تسک‌های فرزند + توقف آن‌هایی که تمام‌نشده‌اند
    $stmt = $db->prepare("UPDATE tasks
                          SET is_deleted = 1,
                              deleted_at = NOW(),
                              deleted_by = :uid,
                              status = CASE WHEN status IN ('completed','approved') THEN status ELSE 'stopped' END
                          WHERE workflow_instance_id = :id
                            AND organization_id = :org_id
                            AND is_deleted = 0");
    $stmt->execute(['uid' => $user_id, 'id' => $instance_id, 'org_id' => $org_id]);

    $db->commit();

    echo json_encode(['success' => true, 'message' => 'روتین حذف شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در حذف روتین'], JSON_UNESCAPED_UNICODE);
}