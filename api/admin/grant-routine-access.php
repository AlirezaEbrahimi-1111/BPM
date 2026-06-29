<?php
// api/admin/grant-routine-access.php - دادن دسترسی به کاربر
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
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کاربر الزامی است']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // بررسی دسترسی ادمین
    $checkAdmin = $db->prepare("SELECT activity_section FROM users WHERE id = ?");
    $checkAdmin->execute([$user_id]);
    $user = $checkAdmin->fetch();
    
    if (!$user || $user['activity_section'] !== 'management') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }
    
    $target_user_id = $input['user_id'];
    $can_create = $input['can_create'] ? 1 : 0;
    
    $stmt = $db->prepare("UPDATE users SET can_create_routine = ? WHERE id = ?");
    $stmt->execute([$can_create, $target_user_id]);
    
    echo json_encode([
        'success' => true,
        'message' => $can_create ? 'دسترسی با موفقیت اعطا شد' : 'دسترسی با موفقیت حذف شد'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Grant routine access error: " . $e->getMessage());
}
?>