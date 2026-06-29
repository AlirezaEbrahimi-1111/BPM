<?php
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sms.php';



try {
    $user_id = requireAuth();
    
    $database = new Database();
    $db = $database->getConnection();
    $sms = new SMS($db);
    
    $stats = $sms->getStats();
    $logs = $sms->getLogs([], 50, 0);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'logs' => $logs
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>