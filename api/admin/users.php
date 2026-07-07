<?php

// api/admin/users.php - لیست کاربران برای ادمین
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    // بررسی دسترسی ادمین (باید activity_section = 'management' باشد)
    $database = new Database();
    $db = $database->getConnection();
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

    $checkAdmin = $db->prepare("SELECT id, role, activity_section, organization_id FROM users WHERE id = ? and is_deleted=0");
    $checkAdmin->execute([$user_id]);
    $currentUser = $checkAdmin->fetch();
    $org_id = $currentUser['organization_id'];

    // 🔐 کنترل دسترسی متمرکز: فقط کسانی که اجازه‌ی مدیریت کاربرها را دارند
    requirePermission($currentUser, 'manage_users');
    
    // دریافت لیست کاربران با واحدهایشان
    $sql = "SELECT u.id, u.phone, u.username, u.first_name, u.last_name, u.email,
                   u.activity_section, u.role, u.is_active, u.created_at, u.last_login,
                   u.shift_type, u.shift_1_start, u.shift_1_end, u.shift_2_start, u.shift_2_end,
                   u.daily_work_hours, u.monthly_salary,
                   u.can_create_routine, u.can_create_workflow,
                   u.manager_id, u.manager_name, u.manager_lastname
            FROM users u
            WHERE u.organization_id = ? AND u.is_deleted = 0
            ORDER BY u.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$org_id]);
    $users = $stmt->fetchAll();
    
    // دریافت واحدهای هر کاربر
    foreach ($users as &$user) {
        $unitsStmt = $db->prepare("SELECT activity_unit, is_primary FROM user_activity_units WHERE user_id = ?  ORDER BY is_primary DESC");
        $unitsStmt->execute([$user['id']]);
        $user['units'] = $unitsStmt->fetchAll();
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => count($users)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور', 'error' => $e->getMessage()]);
    error_log("Admin get users error: " . $e->getMessage());
}
?>
