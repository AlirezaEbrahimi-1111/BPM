<?php

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/task-access.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/checklist/_helpers.php';

try {
    $user_id = requireAuth();

    // چک کردن task_id
    if (empty($_POST['task_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }

    $task_id = intval($_POST['task_id']);

    // 🆕 پیوست متعلق به یک آیتم چک‌لیست خاص (مثلا موقع تأیید آیتم
    // ارجاع‌شده) — اختیاری؛ اگه داده بشه، باید مال همین task باشه و کاربر
    // باید دقیقا همون کسی باشه که مجاز به عمل‌کردن روی همین آیتمه (نه صرفا
    // دسترسی کلی کار)، وگرنه بیننده‌ها/همکاران دیگه می‌تونستن به آیتم
    // فرد دیگه‌ای فایل بچسبونن.
    $checklist_item_id = !empty($_POST['checklist_item_id']) ? intval($_POST['checklist_item_id']) : null;

    // دریافت step_ids (آرایه JSON از مرحله‌ها) - برای کارهای روتین
    $step_ids = null;
    if (!empty($_POST['step_ids'])) {
        $step_ids = $_POST['step_ids'];
        // اعتبارسنجی JSON
        if (!json_decode($step_ids)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'فرمت step_ids نامعتبر است']);
            exit;
        }
    }

    // چک کردن فایل
    if (empty($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'فایلی انتخاب نشده است']);
        exit;
    }

    $file = $_FILES['file'];

    // چک کردن خطا در آپلود
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'خطا در آپلود فایل: ' . $file['error']]);
        exit;
    }

    // چک کردن حجم فایل (20MB)
    $maxSize = 20 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'حجم فایل نباید بیشتر از 20 مگابایت باشد']);
        exit;
    }

    // چک کردن نوع فایل
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'doc', 'xls', 'xlsx', 'mp3', 'm4a', 'ogg'];
    $allowedMimeTypes = [
        'image/jpeg', 'image/png', 'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/x-m4a'
    ];

    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (preg_match('/\.(php|phtml|phar|cgi|pl|py|sh|exe|js|html?)(\.|$)/i', $file['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نوع فایل مجاز نیست'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $fileMimeType = mime_content_type($file['tmp_name']);

    if (!in_array($fileExtension, $allowedExtensions)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'فرمت فایل مجاز نیست']);
        exit;
    }

    if (!in_array($fileMimeType, $allowedMimeTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نوع فایل مجاز نیست']);
        exit;
    }

    // چک دسترسی به task
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        SELECT t.id, t.creator_id, t.assignee_id, t.activity_section, t.organization_id
        FROM tasks t
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کار یافت نشد']);
        exit;
    }

    // چک دسترسی — زنجیره‌ی مشترک از includes/task-access.php. قبلا این فایل
    // نه قانون چک‌لیست رو داشت (مسئول یک آیتم چک‌لیست اصلا نمی‌تونست
    // پیوست آپلود کنه) نه از وجود بیننده‌های صرف (task_viewers) خبر داشت؛
    // چون بیننده‌ها فقط حق مشاهده دارن، صریحا از آپلود مستثنا می‌شن
    $access = taskUserAccess($db, (int) $user_id, $task);
    $hasAccess = $access['has_access'] && !$access['is_viewer_only'];

    if (!$hasAccess) {
        error_log("upload-attachment.php denied | user_id={$user_id} | task_id={$task_id}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما به این کار دسترسی ندارید']);
        exit;
    }

    // 🔒 اگه این پیوست مال یک آیتم چک‌لیست خاصه، همون قانون toggle.php رو
    // دوباره چک می‌کنیم — دسترسی کلی کار کافی نیست، باید دقیقا مسئول
    // همین آیتم باشه
    if ($checklist_item_id) {
        $itemStmt = $db->prepare("SELECT id, task_id, assignee_type, assignee_value FROM task_checklist_items WHERE id = ?");
        $itemStmt->execute([$checklist_item_id]);
        $checklistItem = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$checklistItem || (int) $checklistItem['task_id'] !== $task_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'آیتم چک‌لیست نامعتبر است']);
            exit;
        }

        $taskForCheck = ['_is_assignee' => ((int) $task['assignee_id'] === (int) $user_id)];
        if (!canActOnChecklistItem($db, $checklistItem, $taskForCheck, $user_id)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'شما مجاز به پیوست‌کردن فایل برای این آیتم نیستید']);
            exit;
        }
    }

    if ($step_ids) {
    $stepIdsArray = json_decode($step_ids, true);
    if (is_array($stepIdsArray) && count($stepIdsArray) > 0) {
        $allValid = true;
        foreach ($stepIdsArray as $sid) {
            if (!is_numeric($sid) || intval($sid) <= 0) {
                $allValid = false;
                break;
            }
        }
        if (!$allValid) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'step_ids نامعتبر است']);
            exit;
        }
    }
}

    // ایجاد پوشه
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/tasks/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // تولید نام یونیک برای فایل
    $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;

    // انتقال فایل
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در ذخیره فایل']);
        exit;
    }

    $relativePath = '/uploads/tasks/' . $fileName;

    // ========== ذخیره در دیتابیس (با step_ids و/یا checklist_item_id، هرکدوم که باشه) ==========

    // اگر step_ids داریم (کار روتین با چند مرحله)
    if ($step_ids) {
        $stmt = $db->prepare("
            INSERT INTO task_attachments
            (task_id, checklist_item_id, step_ids, uploaded_by, file_name, file_original_name, file_path, file_size, file_type, mime_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $task_id, $checklist_item_id, $step_ids, $user_id, $fileName, $file['name'],
            $relativePath, $file['size'], $fileExtension, $fileMimeType
        ]);
    }
    // تسک معمولی (بدون step_ids)
    else {
        $stmt = $db->prepare("
            INSERT INTO task_attachments
            (task_id, checklist_item_id, uploaded_by, file_name, file_original_name, file_path, file_size, file_type, mime_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $task_id, $checklist_item_id, $user_id, $fileName, $file['name'],
            $relativePath, $file['size'], $fileExtension, $fileMimeType
        ]);
    }

    $attachmentId = $db->lastInsertId();

    // ثبت در تاریخچهٔ کار
    try {
        $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes) VALUES (?, ?, NULL, 'attachment_added', ?)")
            ->execute([$task_id, $user_id, 'فایل «' . $file['name'] . '» را بارگذاری کرد']);
    } catch (Exception $e) {
        error_log("upload-attachment history insert failed | task_id={$task_id} | " . $e->getMessage());
    }

    // گرفتن اطلاعات کاربر
    $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $uploader = $stmt->fetch(PDO::FETCH_ASSOC);
    $uploaderName = trim(($uploader['first_name'] ?? '') . ' ' . ($uploader['last_name'] ?? ''));

    echo json_encode([
        'success' => true,
        'message' => 'فایل با موفقیت آپلود شد',
        'attachment' => [
            'id' => $attachmentId,
            'checklist_item_id' => $checklist_item_id,
            'step_ids' => $step_ids ? json_decode($step_ids) : null,
            'file_name' => $fileName,
            'file_original_name' => $file['name'],
            'file_path' => $relativePath,
            'file_size' => $file['size'],
            'file_type' => $fileExtension,
            'uploader_name' => $uploaderName,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Upload attachment error: " . $e->getMessage());
}