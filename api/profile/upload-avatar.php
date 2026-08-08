<?php
/**
 * API: آپلود/تغییرِ عکسِ پروفایلِ کاربرِ جاری
 * POST /api/profile/upload-avatar.php   (multipart/form-data)   field: avatar
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    $user_id = $auth->getUserFromToken();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت']);
        exit;
    }

    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'فایلِ تصویر ارسال نشد']);
        exit;
    }

    $file = $_FILES['avatar'];
    $maxSize = 3 * 1024 * 1024;
    // 🔒 نوعِ فایل هرگز از روی $_FILES['type'] (هدرِ کلاینت، به‌سادگی قابلِ جعل) یا
    // پسوندِ نامِ اصلیِ فایل تعیین نمی‌شود — فقط محتوایِ واقعی با getimagesize() بررسی
    // می‌شود، تا امکانِ آپلودِ فایلِ اجراشدنی (مثلاً .php) با Content-Type جعلی نباشد
    $allowedExtByType = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];

    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'حجمِ تصویر نباید بیش از ۳ مگابایت باشد']);
        exit;
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo || !isset($allowedExtByType[$imageInfo[2]])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'فقط تصویرِ JPG، PNG، WEBP یا GIF مجاز است']);
        exit;
    }

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/avatars/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext = $allowedExtByType[$imageInfo[2]];
    $storedName = 'u' . $user_id . '_' . uniqid() . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $storedName)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در ذخیره‌یِ فایل']);
        exit;
    }

    $stmt = $db->prepare("SELECT avatar_path FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $oldPath = $stmt->fetchColumn();

    $newRelativePath = 'uploads/avatars/' . $storedName;
    $db->prepare("UPDATE users SET avatar_path = ? WHERE id = ?")->execute([$newRelativePath, $user_id]);

    if ($oldPath) {
        $oldFull = $_SERVER['DOCUMENT_ROOT'] . '/' . $oldPath;
        if (is_file($oldFull)) @unlink($oldFull);
    }

    echo json_encode(['success' => true, 'avatar_path' => $newRelativePath]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Profile upload-avatar error: " . $e->getMessage());
}
