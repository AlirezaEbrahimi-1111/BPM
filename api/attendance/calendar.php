<?php
// api/attendance/calendar.php

ob_start();
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../AttendanceManager.php';
require_once '../JalaliDate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $userId = requireAuth();
    
    // دریافت سال و ماه از query
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

    $database = new Database();
    $db = $database->getConnection();
    $attendanceManager = new AttendanceManager($db);

    // دریافت تقویم
    $calendar = $attendanceManager->getMonthlyCalendar($year, $month, $userId);

    http_response_code(200);
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'year' => $year,
            'month' => $month,
            'calendar' => $calendar
        ]
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    error_log("Calendar error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور'
    ]);
}

?>