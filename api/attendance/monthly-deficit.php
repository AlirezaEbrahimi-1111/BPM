<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/AttendanceManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    $month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
    
    $database = new Database();
    $db = $database->getConnection();
    $attendanceManager = new AttendanceManager($db);
    
    $deficit = $attendanceManager->getMonthlyDeficit($user_id, $year, $month);
    
    echo json_encode([
        'success' => true,
        'year' => $year,
        'month' => $month,
        'deficit' => $deficit
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Monthly deficit API error: " . $e->getMessage());
}
?>