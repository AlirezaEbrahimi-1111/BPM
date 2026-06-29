<?php
header('Content-Type: application/json; charset=utf-8');


try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';

    $database = new Database();
    $db = $database->getConnection();
    
    // آمار ساده
    $stmt = $db->query("SELECT COUNT(*) as count FROM workflows WHERE status = 'active'");
    $active = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM workflows WHERE status = 'completed'");
    $completed = $stmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'active' => (int)$active,
            'delayed' => 0,
            'completed' => (int)$completed,
            'active_steps' => (int)$active
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>