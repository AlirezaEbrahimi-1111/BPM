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

    // 🔒 دسترسی: فقط کسی که با این کار ارتباط دارد
    $stmt = $db->prepare("SELECT id FROM tasks WHERE id = ? AND (creator_id = ? OR assignee_id = ?)");
    $stmt->execute([$input['task_id'], $user_id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $tm = new TaskManager($db);
    $chain = $tm->getDelegationChain($input['task_id']);

    echo json_encode([
        'success' => true,
        'chain_length' => count($chain)
    ]);
} catch (Exception $e) {
    error_log("get-chain-length error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا']);
}
?>