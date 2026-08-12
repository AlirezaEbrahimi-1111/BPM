<?php
// ==================================================
// api/tasks/get-overdue-clear-requests.php
// دریافت درخواست رفع معوقهٔ باز برای یک کار
//   • اگر کاربر تأییدکننده باشد → برای نمایش دکمهٔ بررسی
//   • اگر کاربر درخواست‌دهنده باشد → برای نمایش وضعیت «در انتظار»
// ==================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    $task_id = (int) ($_GET['task_id'] ?? 0);

    if (!$task_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسهٔ کار الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        SELECT r.*,
               u.first_name AS requester_first_name,
               u.last_name  AS requester_last_name,
               t.assignee_id
        FROM overdue_clear_requests r
        JOIN users u ON r.requested_by = u.id
        JOIN tasks t ON r.task_id = t.id
        WHERE r.task_id = ? AND r.status = 'pending'
          AND (r.current_approver_id = ? OR t.assignee_id = ? OR r.requested_by = ?)
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$task_id, $user_id, $user_id, $user_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($requests as &$r) {
        $r['requester_name'] = trim($r['requester_first_name'] . ' ' . $r['requester_last_name']);
        $r['is_approver']    = ((int)$r['current_approver_id'] === $user_id);
        $r['is_requester']   = ((int)$r['requested_by'] === $user_id);
    }

    echo json_encode(['success' => true, 'requests' => $requests], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('get-overdue-clear-requests error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
