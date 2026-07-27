<?php
// api/tasks/get-termination-request.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();

    if (empty($_GET['task_id'])) {
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }

    $task_id = intval($_GET['task_id']);

    $database = new Database();
    $db       = $database->getConnection();

    // 🔒 خط قرمز: فقط کسی که با این درخواست ارتباط دارد (درخواست‌دهنده،
    // بررسی‌کننده، سازنده یا مسئول کار) — نه هر کاربر لاگین‌کرده‌ای
    $stmt = $db->prepare("
        SELECT
            tr.*,
            CONCAT(ru.first_name,' ',ru.last_name) AS requester_name,
            ru.phone AS requester_phone
        FROM task_termination_requests tr
        JOIN users ru ON tr.requester_id = ru.id
        JOIN tasks t ON tr.task_id = t.id
        WHERE tr.task_id = ? AND tr.status = 'pending'
          AND (
              tr.requester_id = ? OR tr.reviewer_id = ?
              OR t.creator_id = ? OR t.assignee_id = ?
          )
        ORDER BY tr.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$task_id, $user_id, $user_id, $user_id, $user_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'request' => $request ?: null
    ]);

} catch (Exception $e) {
    error_log("get-termination-request error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
}
?>
