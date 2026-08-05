<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    
    $database = new Database();
    $db = $database->getConnection();
    
    $notification = new Notification($db);
    $result = $notification->markAllAsRead($user_id);
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'همه اعلان‌ها خوانده شدند' : 'خطا در علامت‌گذاری'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("notifications/mark-all-read.php failed | user_id=" . ($user_id ?? 'null') . " | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>