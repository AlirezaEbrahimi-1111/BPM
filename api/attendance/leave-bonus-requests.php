<?php
/**
 * API: فهرستِ درخواست‌هایِ سهمیهٔ تشویقی برایِ سرپرستِ سازمان — فقط
 * درخواست‌هایِ در‌انتظار (pending) رو نشون می‌ده تا فراموش نشن؛ نتیجهٔ
 * غیرِپندینگ نیازی به نمایشِ همیشگی نداره چون خودِ کارمند نوتیفیکیشنِ
 * نتیجه رو می‌گیره.
 * GET
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    $me = loadUserForPermissions($db, $user_id);
    if (!$me || $me['role'] !== 'supervisor') {
        echo json_encode(['success' => true, 'requests' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $db->prepare("
        SELECT r.id, r.user_id, r.note, r.requested_minutes, r.balance_at_request, r.created_at,
               u.first_name, u.last_name
        FROM leave_bonus_requests r
        JOIN users u ON u.id = r.user_id
        WHERE r.organization_id = ? AND r.status = 'pending'
        ORDER BY r.created_at ASC
    ");
    $stmt->execute([$me['organization_id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/leave-balance-helper.php';
    foreach ($rows as &$row) {
        $row['name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        $row['balance_formatted'] = formatMinutesHM((int) $row['balance_at_request']);
        $row['requested_formatted'] = formatMinutesHM((int) $row['requested_minutes']);
        unset($row['first_name'], $row['last_name']);
    }
    unset($row);

    echo json_encode(['success' => true, 'requests' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log('leave-bonus-requests error: ' . $e->getMessage());
}
