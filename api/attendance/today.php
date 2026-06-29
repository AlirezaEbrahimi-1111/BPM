<?php
// api/attendance/today.php

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../AttendanceManager.php';
require_once '../AttendanceCalculator.php';
require_once '../JalaliDate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $userId = requireAuth();

    $database = new Database();
    $db = $database->getConnection();
    $attendanceManager = new AttendanceManager($db);
    $calculator = new AttendanceCalculator($db, $userId);

    // دریافت وضعیت امروز
    $todayStatus = $attendanceManager->getTodayStatus($userId);

    // دریافت کسری تا امروز
    $shortage = $calculator->calculateTotalShortage();

    // دریافت درخواست‌های در انتظار
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM (
            SELECT id FROM mission_requests WHERE user_id = ? AND status = 'pending'
            UNION ALL
            SELECT id FROM leave_requests WHERE user_id = ? AND status = 'pending'
            UNION ALL
            SELECT id FROM pass_requests WHERE user_id = ? AND status = 'pending' AND is_cancelled = 0
            UNION ALL
            SELECT id FROM forget_requests WHERE user_id = ? AND status = 'pending'
            UNION ALL
            SELECT id FROM technical_issues WHERE user_id = ? AND status = 'pending'
        ) as pending_requests
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
    $pendingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    http_response_code(200);
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'today_status' => $todayStatus,
            'total_shortage_hours' => $shortage['total_shortage_hours'],
            'total_overtime_hours' => $shortage['total_overtime_hours'],
            'pending_requests_count' => $pendingCount,
            'today_date' => JalaliDate::formatJalali(JalaliDate::toJalali(date('Y-m-d')))
        ]
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    error_log("Today status error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور'
    ]);
}

?>