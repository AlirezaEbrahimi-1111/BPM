<?php
/**
 * API: فهرست فایل‌های پیوست یک اطلاعیه
 * GET ?announcement_id=1 → {success, attachments:[{id, original_name, file_size}], can_manage}
 * جدا از list (که از go-api سرو می‌شود) و فقط هنگام باز کردن اطلاعیه صدا زده می‌شود.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/announcement-attachments.php';

try {
    $user_id = requireAuth();
    $database = new Database();
    $db = $database->getConnection();

    $user = annAttLoadUser($db, (int) $user_id);
    $ann = annAttLoad($db, (int) ($_GET['announcement_id'] ?? 0));
    if (!$user || !$ann || !annAttCanView($db, $user, $ann)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'اطلاعیه یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $db->prepare("SELECT id, original_name, file_size FROM announcement_attachments WHERE announcement_id = ? ORDER BY id");
    $stmt->execute([$ann['id']]);
    $rows = array_map(fn($r) => [
        'id'            => (int) $r['id'],
        'original_name' => $r['original_name'],
        'file_size'     => (int) $r['file_size'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode(['success' => true, 'attachments' => $rows, 'can_manage' => annAttCanManage($user, $ann)], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log("announcement attachments error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
