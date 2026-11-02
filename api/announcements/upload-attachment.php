<?php
/**
 * API: بارگذاری یک فایل پیوست برای اطلاعیه
 * POST multipart: announcement_id, file
 * فقط کسی که اجازهٔ ویرایش همان اطلاعیه را دارد.
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/announcement-attachments.php';

function ann_att_out(array $arr, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $user_id = requireAuth();

    // کل درخواست از post_max_size بزرگ‌تر بوده: PHP هم $_POST و هم $_FILES را خالی می‌دهد
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        ann_att_out(['success' => false, 'message' => 'حجم فایل بیشتر از حد مجاز است (ویدیو حداکثر ۵۰ مگابایت، بقیهٔ فایل‌ها حداکثر ۲۰ مگابایت)'], 400);
    }

    $announcementId = (int) ($_POST['announcement_id'] ?? 0);
    if (!$announcementId || empty($_FILES['file'])) {
        ann_att_out(['success' => false, 'message' => 'شناسهٔ اطلاعیه و فایل الزامی است'], 400);
    }

    $database = new Database();
    $db = $database->getConnection();

    $user = annAttLoadUser($db, (int) $user_id);
    $ann = annAttLoad($db, $announcementId);
    if (!$user) ann_att_out(['success' => false, 'message' => 'عدم احراز هویت'], 401);
    if (!$ann) ann_att_out(['success' => false, 'message' => 'اطلاعیه یافت نشد'], 404);
    if (!annAttCanManage($user, $ann)) {
        error_log("announcement upload denied | user_id={$user_id} | announcement_id={$announcementId}");
        ann_att_out(['success' => false, 'message' => 'دسترسی غیر مجاز'], 403);
    }

    if (count(annAttStoredNames($db, $announcementId)) >= ANN_ATT_MAX_COUNT) {
        ann_att_out(['success' => false, 'message' => 'هر اطلاعیه حداکثر ۱۰ فایل پیوست می‌تواند داشته باشد'], 400);
    }

    $file = $_FILES['file'];
    $check = annAttValidate((string) $file['name'], (string) $file['tmp_name'], (int) $file['size'], (int) $file['error']);
    if ($check['error'] !== null) ann_att_out(['success' => false, 'message' => $check['error']], 400);

    $dir = annAttDir();
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    do {
        $storedName = bin2hex(random_bytes(16)) . '.' . $check['extension'];
    } while (file_exists($dir . $storedName));

    if (!move_uploaded_file($file['tmp_name'], $dir . $storedName)) {
        ann_att_out(['success' => false, 'message' => 'خطا در ذخیره فایل'], 500);
    }

    $originalName = mb_substr(basename(str_replace('\\', '/', (string) $file['name'])), 0, 255);
    $db->prepare("
        INSERT INTO announcement_attachments (announcement_id, uploaded_by, stored_name, original_name, file_size, mime_type)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$announcementId, $user_id, $storedName, $originalName, (int) $file['size'], $check['mime']]);

    ann_att_out(['success' => true, 'id' => (int) $db->lastInsertId()]);

} catch (Throwable $e) {
    error_log("announcement upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
