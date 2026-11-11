<?php
/**
 * API: حذف عکس پروفایل کاربر جاری (برگشت به حرف اول نام)
 * POST /api/profile/delete-avatar.php
 * هم‌خانوادهٔ upload-avatar.php — فقط عکس خود کاربر؛ فایل از دیسک هم پاک می‌شود.
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد مجاز نیست'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    $user_id = $auth->getUserFromToken();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $db->prepare("SELECT avatar_path FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $oldPath = (string) $stmt->fetchColumn();

    $db->prepare("UPDATE users SET avatar_path = NULL WHERE id = ?")->execute([$user_id]);

    // 🔒 فقط فایلی داخل uploads/avatars پاک شود (مسیر از دیتابیس است، ولی احتیاط)
    if ($oldPath !== '' && strpos($oldPath, 'uploads/avatars/') === 0 && strpos($oldPath, '..') === false) {
        $oldFull = $_SERVER['DOCUMENT_ROOT'] . '/' . $oldPath;
        if (is_file($oldFull)) @unlink($oldFull);
    }

    echo json_encode(['success' => true, 'message' => 'عکس پروفایل حذف شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
    error_log("Profile delete-avatar error: " . $e->getMessage());
}
