<?php
// api/attendance/check-in.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/AttendanceManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();

    // دریافت IP کاربر
    $ip_address = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }

    $database = new Database();
    $db = $database->getConnection();
    $attendanceManager = new AttendanceManager($db);

    $auth = new Auth($db);
    $organization_id = $auth->getOrganizationFromToken();

    if (!$organization_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'سازمان نامعتبر']);
        exit;
    }

    $result = $attendanceManager->checkIn($user_id, $ip_address, $organization_id);


    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Check-in API error: " . $e->getMessage());
}
?>