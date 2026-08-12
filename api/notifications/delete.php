<?php
// ==================================================
// api/notifications/delete.php
// ==================================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        throw new Exception('شناسه اعلان الزامی است');
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $notification = new Notification($db);
    $result = $notification->delete($input['id'], $user_id);
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'اعلان حذف شد' : 'خطا در حذف'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("notifications/delete.php failed | user_id=" . ($user_id ?? 'null') . " | id=" . ($input['id'] ?? 'null') . " | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>