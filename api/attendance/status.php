<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/AttendanceManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $database = new Database();
    $db = $database->getConnection();
    $attendanceManager = new AttendanceManager($db);
    
    $status = $attendanceManager->getTodayStatus($user_id);
    $deficit = $attendanceManager->getDailyDeficit($user_id, date('Y-m-d'));
    
    echo json_encode([
        'success' => true,
        'status' => $status,
        'deficit' => $deficit
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Status API error: " . $e->getMessage());
}
?>