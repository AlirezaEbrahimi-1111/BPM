<?php
// api/tasks/get-chain-length.php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['task_id'])) {
        echo json_encode(['success' => false, 'message' => 'task_id الزامی']);
        exit;
    }
    
    $db = (new Database())->getConnection();
    $tm = new TaskManager($db);
    $chain = $tm->getDelegationChain($input['task_id']);
    
    echo json_encode([
        'success' => true,
        'chain_length' => count($chain)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'خطا']);
}
?>