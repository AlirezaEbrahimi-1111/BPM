<?php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();

    if (empty($_GET['task_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }

    $task_id = intval($_GET['task_id']);

    $database = new Database();
    $db = $database->getConnection();

    // 🔒 خط قرمز: فقط کسی که با این درخواست ارتباط دارد (درخواست‌دهنده،
    // تأییدکنندهٔ فعلی، سازنده یا مسئول کار) — نه هر کاربر لاگین‌کرده‌ای
    $stmt = $db->prepare("
        SELECT
            r.id,
            r.task_id,
            r.requested_by,
            r.current_approver_id,
            r.new_start_date,
            r.new_end_date,
            r.reason,
            r.status,
            CONCAT(u.first_name, ' ', u.last_name) AS requester_name
        FROM task_renewal_requests r
        LEFT JOIN users u ON u.id = r.requested_by
        JOIN tasks t ON r.task_id = t.id
        WHERE r.task_id = ? AND r.status = 'pending'
          AND (
              r.requested_by = ? OR r.current_approver_id = ?
              OR t.creator_id = ? OR t.assignee_id = ?
          )
        ORDER BY r.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$task_id, $user_id, $user_id, $user_id, $user_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => true, 'request' => null], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => true, 'request' => $request], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("get-pending-renewal error: " . $e->getMessage());
}