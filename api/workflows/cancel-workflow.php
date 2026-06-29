<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/WorkflowManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $database = new Database();
    $db = $database->getConnection();
    $workflowManager = new WorkflowManager($db);
    
    $result = $workflowManager->cancelWorkflow($input['instance_id'], $user_id, $input['reason'] ?? '');
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
?>