<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/LeaveManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['request_id']) || empty($input['request_type']) || empty($input['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'اطلاعات کامل نیست']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $leaveManager = new LeaveManager($db);
    
    $result = $leaveManager->approveRequest(
        $input['request_id'],
        $input['request_type'],
        $user_id,
        $input['action'],
        $input['notes'] ?? ''
    );
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Approve request API error: " . $e->getMessage());
}
?>