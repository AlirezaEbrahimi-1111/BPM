<?php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';

try {
    $user_id = requireAuth();
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['task_id']) || empty($data['new_start_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);

    $result = $taskManager->applyPeriodRenewalDirect(
        intval($data['task_id']),
        $user_id,
        $data['new_start_date'],
        $data['new_end_date'] ?? null,
        trim($data['reason'] ?? '')
    );

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("apply-renewal error: " . $e->getMessage());
}