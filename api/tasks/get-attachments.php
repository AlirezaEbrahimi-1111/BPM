<?php

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user-sections.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/task-access.php';
try {
    $user_id = requireAuth();

    if (empty($_GET['task_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }

    $task_id = intval($_GET['task_id']);

    $database = new Database();
    $db = $database->getConnection();

    // چک دسترسی به task
$stmt = $db->prepare("
    SELECT t.id, t.creator_id, t.assignee_id, t.organization_id,
           t.is_workflow_task, t.workflow_instance_id
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

    // چک دسترسی — زنجیره‌ی مشترک از includes/task-access.php؛ فقط قانون
    // تخصصی عضویت مرحله‌ی workflow پایین‌تر جداگانه باقی می‌مونه
    $access = taskUserAccess($db, (int) $user_id, $task);
    $hasAccess = $access['has_access'];
    $is_checklist_only = $access['is_checklist_only'];

// چک دسترسی برای workflow tasks
    if (!$hasAccess && $task['is_workflow_task'] == 1 && $task['workflow_instance_id']) {
        $stmt = $db->prepare("
            SELECT ws.activity_section
            FROM workflow_instance_steps wis
            JOIN workflow_steps ws ON wis.step_id = ws.id
            WHERE wis.instance_id = ?
            AND wis.task_id = ?
            LIMIT 1
        ");
        $stmt->execute([$task['workflow_instance_id'], $task_id]);
        $wfStep = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($wfStep && $wfStep['activity_section']) {
            // 🆕 عضویت در هر یک از واحدهای کاربر
            if (us_userInSection($db, $user_id, $wfStep['activity_section'])) {
                $hasAccess = true;
            }
        }
    }

    // 🆕 بیننده‌های صریحا اضافه‌شده — از قبل توسط taskUserAccess() بررسی شده؛
    // فقط اگه can_view_attachments روشن باشه، دسترسی بیننده‌بودن معتبره
    if ($access['is_viewer_only'] && !$access['viewer_can_view_attachments']) {
        $hasAccess = false;
    }

    if (!$hasAccess) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // دریافت فایل‌ها
// مرحلهٔ قالب (workflow_steps.id) همین کاری که کاربر باز کرده — برای فیلتر step_ids پایین.
// step_ids در صفحهٔ ساخت روتین (pages/create-task.php) با شناسهٔ مرحلهٔ قالب پر می‌شود،
// نه شمارهٔ ترتیب مرحله. (قبلا این‌جا با workflow_instances.current_step — یعنی شمارهٔ
// ترتیب — مقایسه می‌شد و فایل محدود به مرحلهٔ خاص تقریبا به هیچ‌کس نشان داده نمی‌شد.)
$viewed_step_id = null;
$stmtWf = $db->prepare("SELECT step_id FROM workflow_instance_steps WHERE task_id = ? LIMIT 1");
$stmtWf->execute([$task_id]);
$wfRow = $stmtWf->fetch(PDO::FETCH_ASSOC);
if ($wfRow) {
    $viewed_step_id = intval($wfRow['step_id']);
}

// دریافت فایل‌های همه تسک‌های همین workflow
// تشخیص: workflow task است یا معمولی؟
if ($task['is_workflow_task'] == 1 && $task['workflow_instance_id']) {
    // workflow: فایل‌های همه تسک‌های همین workflow
    $stmt = $db->prepare("
        SELECT
            ta.id,
            ta.file_name,
            ta.file_original_name,
            ta.file_path,
            ta.file_size,
            ta.file_type,
            ta.mime_type,
            ta.uploaded_by as uploader_id,
            ta.created_at,
            ta.step_ids,
            ta.checklist_item_id,
            tci.title as checklist_item_title,
            u.first_name,
            u.last_name
        FROM task_attachments ta
        LEFT JOIN users u ON ta.uploaded_by = u.id
        LEFT JOIN task_checklist_items tci ON ta.checklist_item_id = tci.id
        WHERE ta.task_id IN (
            SELECT id FROM tasks 
            WHERE workflow_instance_id = ?
        )
        ORDER BY ta.created_at DESC
    ");
    $stmt->execute([$task['workflow_instance_id']]);
} else {
    // تسک معمولی: فقط فایل‌های همین task_id
    $stmt = $db->prepare("
        SELECT
            ta.id,
            ta.file_name,
            ta.file_original_name,
            ta.file_path,
            ta.file_size,
            ta.file_type,
            ta.mime_type,
            ta.uploaded_by as uploader_id,
            ta.created_at,
            ta.step_ids,
            ta.checklist_item_id,
            tci.title as checklist_item_title,
            u.first_name,
            u.last_name
        FROM task_attachments ta
        LEFT JOIN users u ON ta.uploaded_by = u.id
        LEFT JOIN task_checklist_items tci ON ta.checklist_item_id = tci.id
        WHERE ta.task_id = ?
        ORDER BY ta.created_at DESC
    ");
    $stmt->execute([$task_id]);
}
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// فیلتر فایل‌ها بر اساس step_ids
$attachments = array_values(array_filter($attachments, function($att) use ($viewed_step_id, $user_id) {
    if (empty($att['step_ids'])) return true;

    $steps = json_decode($att['step_ids'], true);

    if (!is_array($steps) || count($steps) === 0) return true;

    // بارگذارندهٔ فایل همیشه فایل خودش را می‌بیند (وگرنه نمی‌تواند حذف/مدیریتش کند)
    if ((int) $att['uploader_id'] === (int) $user_id) return true;

    if ($viewed_step_id === null) return false;

    return in_array($viewed_step_id, array_map('intval', $steps), true);
}));

// 🔒 کاربر چک‌لیستی: فقط فایل‌هایی که خودش بارگذاری کرده
if ($is_checklist_only) {
    $attachments = array_values(array_filter($attachments, function ($a) use ($user_id) {
        return (int)$a['uploader_id'] === (int)$user_id;
    }));
}

    // بررسی: آیا بعد از آپلود هر فایل، ارجاعی اتفاق افتاده؟
    $stmt = $db->prepare("
    SELECT MIN(created_at) as first_delegation_at
    FROM task_history 
    WHERE task_id = ? AND action = 'delegated'
");
    $stmt->execute([$task_id]);
    $delegation = $stmt->fetch(PDO::FETCH_ASSOC);
    $first_delegation_at = $delegation['first_delegation_at'] ?? null;

    // فرمت کردن نتایج
    foreach ($attachments as &$attachment) {
        $attachment['uploader_name'] = trim(($attachment['first_name'] ?? '') . ' ' . ($attachment['last_name'] ?? ''));
        unset($attachment['first_name'], $attachment['last_name']);

        $attachment['file_size_formatted'] = formatFileSize($attachment['file_size']);
        $attachment['is_image'] = in_array($attachment['file_type'], ['jpg', 'jpeg', 'png']);

        $is_uploader = ($attachment['uploader_id'] == $user_id);
        $is_current_assignee = ($task['assignee_id'] == $user_id);

        // آیا بعد از آپلود این فایل ارجاعی بوده؟
        $delegated_after_upload = false;
        if ($first_delegation_at !== null) {
            // اگر اصلا ارجاعی وجود داره، چک کن آیا بعد از آپلود این فایل بوده
            $stmt2 = $db->prepare("
            SELECT COUNT(*) as cnt 
            FROM task_history 
            WHERE task_id = ? 
            AND action = 'delegated' 
            AND created_at >= ?
        ");
            $stmt2->execute([$task_id, $attachment['created_at']]);
            $delegated_after_upload = ($stmt2->fetch()['cnt'] > 0);
        }

        $attachment['can_delete'] = $is_uploader && $is_current_assignee && !$delegated_after_upload;
    }


    echo json_encode([
        'success' => true,
        'attachments' => $attachments
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Get attachments error: " . $e->getMessage());
}

function formatFileSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}