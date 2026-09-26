<?php
// api/admin/user-units.php - دریافت واحدهای یک کاربر
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
try {
    $user_id = requireAuth();
    
    if (empty($_GET['user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کاربر الزامی است']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // بررسی دسترسی ادمین
    $currentUser = loadUserForPermissions($db, $user_id);
    requirePermission($currentUser, 'manage_users');

    $target_user_id = $_GET['user_id'];

    // 🔒 خط قرمز: supervisor/admin فقط در سازمان خودشان، manager فقط
    // روی زیرمجموعهٔ خودش (زنجیرهٔ manager_id) — نه فراتر
    if (!canManageTargetUser($db, $currentUser, (int) $target_user_id)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit;
    }

    // دریافت واحدهای کاربر
    $stmt = $db->prepare("SELECT activity_unit, is_primary FROM user_activity_units WHERE user_id = ? ORDER BY is_primary DESC");
    $stmt->execute([$target_user_id]);
    $units = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'units' => $units
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور', 'error' => 'internal_error']);
    error_log("Admin get user units error: " . $e->getMessage());
}
?>