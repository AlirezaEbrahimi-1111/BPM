<?php
// api/admin/routines-all.php - لیست تمام روتین‌ها
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
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
    
    $sql = "SELECT rw.*, 
                   (SELECT COUNT(*) FROM routine_steps WHERE routine_id = rw.id) as steps_count,
                   (SELECT COUNT(*) FROM routine_instances WHERE routine_id = rw.id) as instances_count
            FROM routine_workflows rw
            ORDER BY rw.created_at DESC";
    
    $stmt = $db->query($sql);
    $routines = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'routines' => $routines
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Admin get all routines error: " . $e->getMessage());
}
?>