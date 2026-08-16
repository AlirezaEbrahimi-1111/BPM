<?php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $query = $_GET['q'] ?? '';
    
    if (empty($query) && empty($_GET['status']) && empty($_GET['priority'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'کلمه کلیدی یا فیلتر الزامی است']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);
    
    $filters = [];
    if (!empty($_GET['status'])) {
        $filters['status'] = $_GET['status'];
    }
    if (!empty($_GET['priority'])) {
        $filters['priority'] = $_GET['priority'];
    }
    
    $tasks = $taskManager->searchTasks($user_id, $query, $filters);
    
    echo json_encode(['success' => true, 'tasks' => $tasks, 'query' => $query]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Search tasks error: " . $e->getMessage());
}
?>