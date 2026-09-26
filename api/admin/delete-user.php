<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

$user_id = requireAuth();

$database = new Database();
$db = $database->getConnection();

$me = loadUserForPermissions($db, $user_id);
requirePermission($me, 'manage_users');

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'شناسه کاربر الزامی است']);
    exit;
}

$uid = (int)$input['user_id'];

if ($uid === (int)$user_id) {
    echo json_encode(['success' => false, 'message' => 'نمی‌توانید خودتان را حذف کنید']);
    exit;
}

try {
    // 🔒 خط قرمز: supervisor/admin فقط در سازمان خودشان، manager فقط
    // روی زیرمجموعهٔ خودش (زنجیرهٔ manager_id) — نه فراتر
    if (!canManageTargetUser($db, $me, $uid)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit;
    }

    $targetStmt = $db->prepare('SELECT phone FROM users WHERE id = ?');
    $targetStmt->execute([$uid]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);

    $suffix = 'deleted_' . $target['phone'] . '_' . time();
    // 🔒 is_active هم صفر می‌شود — قبلا فقط is_deleted ست می‌شد، و چون
    // خیلی از کوئری‌های سراسر پروژه (پیکرها/لیست کاربران) فقط
    // is_active=1 را چک می‌کنند، کاربر حذف‌شده همچنان توی همه‌جا دیده
    // می‌شد — این ناهماهنگی ریشه‌ی باگ «کاربران حذف‌شده هنوز نشون داده
    // می‌شن» بود.
    $stmt = $db->prepare('UPDATE users SET is_deleted = 1, is_active = 0, deleted_at = NOW(), deleted_by = ?, phone = ?, username = ? WHERE id = ?');
    $stmt->execute([$user_id, $suffix, $suffix, $uid]);
    echo json_encode(['success' => true, 'message' => 'کاربر حذف شد']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطای پایگاه داده']);
}