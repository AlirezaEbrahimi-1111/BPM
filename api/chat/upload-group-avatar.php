<?php
/**
 * API: آپلود/تغییرِ عکسِ گروه — سازنده یا هر مدیرِ گروه مجاز است
 * POST /api/chat/upload-group-avatar.php   (multipart/form-data)   fields: conversation_id, avatar
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/chat-helpers.php';

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

    $conversationId = (int) ($_POST['conversation_id'] ?? 0);
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه گروه الزامی است']);
        exit;
    }

    $stmt = $db->prepare("SELECT type, created_by, avatar_path FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conv || $conv['type'] === 'direct') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این گفتگو گروهی نیست']);
        exit;
    }
    if (!chatUserIsGroupManager($db, $conversationId, $user_id)) {
        http_response_code(403);
        error_log("Chat upload-group-avatar denied | user_id={$user_id} | conversation_id={$conversationId}");
        echo json_encode(['success' => false, 'message' => 'فقط مدیر گروه می‌تواند عکس گروه را تغییر دهد']);
        exit;
    }

    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'فایل تصویر ارسال نشد']);
        exit;
    }

    $file = $_FILES['avatar'];
    $maxSize = 3 * 1024 * 1024;
    // 🔒 نوعِ فایل هرگز از روی $_FILES['type'] یا پسوندِ نامِ اصلیِ فایل تعیین نمی‌شود
    // — فقط محتوایِ واقعی با getimagesize() بررسی می‌شود (همان الگویِ upload-avatar.php)
    $allowedExtByType = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];

    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'حجم تصویر نباید بیش از ۳ مگابایت باشد']);
        exit;
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo || !isset($allowedExtByType[$imageInfo[2]])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'فقط تصویر JPG، PNG، WEBP یا GIF مجاز است']);
        exit;
    }

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/avatars/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext = $allowedExtByType[$imageInfo[2]];
    $storedName = 'grp' . $conversationId . '_' . uniqid() . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $storedName)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در ذخیره‌ی فایل']);
        exit;
    }

    $newRelativePath = 'uploads/avatars/' . $storedName;
    $db->prepare("UPDATE chat_conversations SET avatar_path = ? WHERE id = ?")->execute([$newRelativePath, $conversationId]);

    if ($conv['avatar_path']) {
        $oldFull = $_SERVER['DOCUMENT_ROOT'] . '/' . $conv['avatar_path'];
        if (is_file($oldFull)) @unlink($oldFull);
    }

    echo json_encode(['success' => true, 'avatar_path' => $newRelativePath]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat upload-group-avatar error: " . $e->getMessage());
}
