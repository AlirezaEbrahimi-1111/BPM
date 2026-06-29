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
    
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    $database = new Database();
    $db = $database->getConnection();
    $attendanceManager = new AttendanceManager($db);
    
    $suggestion = $attendanceManager->suggestRequest($user_id, $date);
    
    if ($suggestion) {
        echo json_encode([
            'success' => true,
            'suggestion' => $suggestion
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'کسرکاری وجود ندارد'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Suggest request API error: " . $e->getMessage());
}
?>