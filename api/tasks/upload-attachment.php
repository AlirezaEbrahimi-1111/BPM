<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    $user_id = requireAuth();

    // چک کردن task_id
    if (empty($_POST['task_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }

    $task_id = intval($_POST['task_id']);
    
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

    // چک دسترسی
    $hasAccess = false;
    if ($task['creator_id'] == $user_id || $task['assignee_id'] == $user_id) {
        $hasAccess = true;
    }
    // سوپرادمین یا supervisor/adminِ هم‌سازمانِ این کار
    if (!$hasAccess) {
        $me = loadUserForPermissions($db, $user_id);

        if (hasPermission($me, 'view_all_org_tasks')
            && isSameOrganization($me, $task['organization_id'])) {
            $hasAccess = true;
        }
    }
    // managerِ فقط اگر سازنده/مسئولِ این کار زیرمجموعهٔ خودش باشد
    if (!$hasAccess) {
        $me = $me ?? loadUserForPermissions($db, $user_id);
        if (canManageTargetUser($db, $me, (int) $task['creator_id'])
            || canManageTargetUser($db, $me, (int) $task['assignee_id'])) {
            $hasAccess = true;
        }
    }
    if (!$hasAccess) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM task_history 
            WHERE task_id = ? 
            AND (from_user_id = ? OR to_user_id = ?)
        ");
        $stmt->execute([$task_id, $user_id, $user_id]);
        if ($stmt->fetch()['count'] > 0) {
            $hasAccess = true;
        }
    }

    if (!$hasAccess) {
        error_log("upload-attachment.php denied | user_id={$user_id} | task_id={$task_id}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما به این کار دسترسی ندارید']);
        exit;
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

    // ========== ذخیره در دیتابیس با step_ids ==========
    
    // اگر step_ids داریم (کار روتین با چند مرحله)
    if ($step_ids) {
        $stmt = $db->prepare("
            INSERT INTO task_attachments 
            (task_id, step_ids, uploaded_by, file_name, file_original_name, file_path, file_size, file_type, mime_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $task_id, $step_ids, $user_id, $fileName, $file['name'],
            $relativePath, $file['size'], $fileExtension, $fileMimeType
        ]);
    } 
    // تسک معمولی (بدون step_ids)
    else {
        $stmt = $db->prepare("
            INSERT INTO task_attachments 
            (task_id, uploaded_by, file_name, file_original_name, file_path, file_size, file_type, mime_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $task_id, $user_id, $fileName, $file['name'],
            $relativePath, $file['size'], $fileExtension, $fileMimeType
        ]);
    }

    $attachmentId = $db->lastInsertId();

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
echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
    error_log("Upload attachment error: " . $e->getMessage());
}