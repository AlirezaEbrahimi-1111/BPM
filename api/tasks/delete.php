<?php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: DELETE, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    
    $task_id = null;
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $task_id = $_GET['id'] ?? null;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $task_id = $input['task_id'] ?? null;
    }
    
    if (empty($task_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);
    
    $result = $taskManager->deleteTask($task_id, $user_id);
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        error_log("delete.php deleteTask failed | user_id={$user_id} | task_id={$task_id} | message=" . ($result['message'] ?? ''));
        http_response_code(400);
    }

    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Delete task error: " . $e->getMessage());
}
?>