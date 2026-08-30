<?php

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
try {
    $user_id = requireAuth();
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    $database = new Database();
    $db = $database->getConnection();
    
    // کوئری ساده
 $sql = "SELECT 
        th.id,
        th.task_id,
        th.action,
        th.notes,
        th.created_at,
        th.from_user_id,
        th.to_user_id,
        t.title as task_title,
        t.priority,
        CONCAT(u_from.first_name, ' ', u_from.last_name) as from_user_name,
        CONCAT(u_to.first_name, ' ', u_to.last_name) as to_user_name
    FROM task_history th
    LEFT JOIN tasks t ON th.task_id = t.id
    LEFT JOIN users u_from ON th.from_user_id = u_from.id
    LEFT JOIN users u_to ON th.to_user_id = u_to.id
    WHERE (
        th.task_id IN (
            SELECT id FROM tasks WHERE assignee_id = ? OR creator_id = ?
        )
        OR th.from_user_id = ?
        OR th.to_user_id = ?
    )
    ORDER BY th.created_at DESC
    LIMIT ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $limit]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'activities' => $activities]);
    
} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا', 'error' => 'internal_error']);
}
?>