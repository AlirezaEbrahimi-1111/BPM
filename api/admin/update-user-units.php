<?php
// api/admin/update-user-units.php - بروزرسانی واحدهای کاربر
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['user_id']) || !isset($input['units'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'داده‌های ناقص']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // بررسی دسترسی ادمین
    $currentUser = loadUserForPermissions($db, $user_id);
    requirePermission($currentUser, 'manage_users');

    $target_user_id = $input['user_id'];
    $units = $input['units'];

    // 🔒 خط قرمز: supervisor/admin فقط در سازمان خودشان، manager فقط
    // روی زیرمجموعهٔ خودش (زنجیرهٔ manager_id) — نه فراتر
    if (!canManageTargetUser($db, $currentUser, (int) $target_user_id)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit;
    }
    
    // شروع تراکنش
    $db->beginTransaction();
    
    try {
        // حذف واحدهای قبلی
        $deleteStmt = $db->prepare("DELETE FROM user_activity_units WHERE user_id = ?");
        $deleteStmt->execute([$target_user_id]);
        
        // اضافه کردن واحدهای جدید
        $insertStmt = $db->prepare("INSERT INTO user_activity_units (user_id, activity_unit, is_primary) VALUES (?, ?, ?)");
        
        foreach ($units as $unit) {
            $insertStmt->execute([
                $target_user_id,
                $unit['activity_unit'],
                $unit['is_primary'] ?? 0
            ]);
        }
        
        // بروزرسانی فیلد activity_unit در جدول users (برای سازگاری با کد قدیمی)
        if (count($units) > 0) {
            $primaryUnit = null;
            foreach ($units as $unit) {
                if ($unit['is_primary'] == 1) {
                    $primaryUnit = $unit['activity_unit'];
                    break;
                }
            }
            if (!$primaryUnit) {
                $primaryUnit = $units[0]['activity_unit'];
            }
            
            $updateUserStmt = $db->prepare("UPDATE users SET activity_unit = ? WHERE id = ?");
            $updateUserStmt->execute([$primaryUnit, $target_user_id]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'واحدهای فعالیت با موفقیت بروزرسانی شد'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور', 'error' => 'internal_error']);
    error_log("Admin update user units error: " . $e->getMessage());
}
?>