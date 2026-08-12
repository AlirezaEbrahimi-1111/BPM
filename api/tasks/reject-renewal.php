<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';

try {
    $user_id = requireAuth();
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['request_id']) || empty($data['rejection_reason'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'دلیل رد الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);

    $result = $taskManager->rejectPeriodRenewal(
        intval($data['request_id']),
        $user_id,
        trim($data['rejection_reason'])
    );

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("reject-renewal error: " . $e->getMessage());
}