<?php
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/audit-log.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    // بررسی دسترسی مدیریت
    $currentUser = loadUserForPermissions($db, $user_id);
    requirePermission($currentUser, 'manage_users');

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کاربر الزامی است']);
        exit;
    }

    $target_user_id = $input['user_id'];

    // خواندن وضعیت فعلی کاربر
    // 🔒 خط قرمز: supervisor/admin فقط در سازمانِ خودشان، manager فقط
    // روی زیرمجموعهٔ خودش (زنجیرهٔ manager_id) — نه فراتر
    $getUser = $db->prepare("SELECT is_active FROM users WHERE id = ?");
    $getUser->execute([$target_user_id]);
    $targetUser = $getUser->fetch();

    if (!$targetUser || !canManageTargetUser($db, $currentUser, (int) $target_user_id)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit;
    }

    // معکوس کردن وضعیت — token_version هم بالا می‌ره تا در غیرفعال‌سازی،
    // توکنِ از‌قبل‌صادرشده‌یِ این کاربر فوراً باطل بشه (نه این‌که تا انقضایِ
    // طبیعیِ توکن، که می‌تونه تا ۳۰ روز باشه، بازم کار کنه)
    $newStatus = $targetUser['is_active'] == 1 ? 0 : 1;

    $updateStmt = $db->prepare("UPDATE users SET is_active = ?, token_version = token_version + 1 WHERE id = ?");
    $updateStmt->execute([$newStatus, $target_user_id]);

    logSecurityEvent($user_id, $newStatus == 1 ? 'user_activated' : 'user_deactivated', (int) $target_user_id);

    echo json_encode([
        'success' => true,
        'is_active' => $newStatus,
        'message' => $newStatus == 1 ? 'کاربر فعال شد' : 'کاربر غیرفعال شد'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور', 'error' => $e->getMessage()]);
    error_log("Toggle user status error: " . $e->getMessage());
}
