<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $admin_id = requireAuth();
    
    // بررسی ادمین بودن (فرض: user_id = 1 ادمین است)
    if ($admin_id != 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط ادمین دسترسی دارد']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id']) || !isset($input['has_access'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'داده‌های ناقص']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $sql = "UPDATE users SET can_create_routine = ? WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    if ($stmt->execute([$input['has_access'], $input['user_id']])) {
        echo json_encode(['success' => true, 'message' => 'دسترسی بروزرسانی شد']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطا در بروزرسانی']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Toggle routine access error: " . $e->getMessage());
}
?>