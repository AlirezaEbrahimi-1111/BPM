<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
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
    SELECT t.id, t.creator_id, t.assignee_id, 
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

    // چک دسترسی
    $hasAccess = false;

    if ($task['creator_id'] == $user_id || $task['assignee_id'] == $user_id) {
        $hasAccess = true;
    }

    // امنیت: به‌جای id ثابت → سوپرادمین یا مدیرِ هم‌سازمانِ این کار
    if (!$hasAccess) {
        $stmt = $db->prepare("
            SELECT u.role, u.activity_section, u.organization_id, t.organization_id as task_org_id
            FROM users u, tasks t
            WHERE u.id = ? AND t.id = ?
        ");
        $stmt->execute([$user_id, $task_id]);
        $chk = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($chk) {
            $isSuperadmin = ((int) $user_id === 1);
            $isOrgManager = ($chk['activity_section'] === 'management'
                && in_array($chk['role'], ['supervisor', 'manager'], true)
                && (int) $chk['organization_id'] === (int) $chk['task_org_id']);
            if ($isSuperadmin || $isOrgManager) {
                $hasAccess = true;
            }
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
        $historyCount = $stmt->fetch()['count'];

        if ($historyCount > 0) {
            $hasAccess = true;
        }
    }

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
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM users 
                WHERE id = ? 
                AND activity_section = ? 
                AND is_active = 1
            ");
            $stmt->execute([$user_id, $wfStep['activity_section']]);
            if ($stmt->fetch()['count'] > 0) {
                $hasAccess = true;
            }
        }
    }

    if (!$hasAccess) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // دریافت فایل‌ها
// گرفتن current_step از workflow_instances برای این task
$current_step = null;
$stmtWf = $db->prepare("
    SELECT wi.current_step 
    FROM workflow_instances wi
    JOIN tasks t ON t.workflow_instance_id = wi.id
    WHERE t.id = ?
    LIMIT 1
");
$stmtWf->execute([$task_id]);
$wfRow = $stmtWf->fetch(PDO::FETCH_ASSOC);
if ($wfRow) {
    $current_step = intval($wfRow['current_step']);
}

// گرفتن workflow_instance_id و current_step از طریق task_id
$current_step = null;
$stmtWf = $db->prepare("
    SELECT wi.current_step 
    FROM workflow_instances wi
    JOIN tasks t ON t.workflow_instance_id = wi.id
    WHERE t.id = ?
    LIMIT 1
");
$stmtWf->execute([$task_id]);
$wfRow = $stmtWf->fetch(PDO::FETCH_ASSOC);
if ($wfRow) {
    $current_step = intval($wfRow['current_step']);
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
            u.first_name,
            u.last_name
        FROM task_attachments ta
        LEFT JOIN users u ON ta.uploaded_by = u.id
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
            u.first_name,
            u.last_name
        FROM task_attachments ta
        LEFT JOIN users u ON ta.uploaded_by = u.id
        WHERE ta.task_id = ?
        ORDER BY ta.created_at DESC
    ");
    $stmt->execute([$task_id]);
}
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// فیلتر فایل‌ها بر اساس step_ids
$attachments = array_values(array_filter($attachments, function($att) use ($current_step) {
    if (empty($att['step_ids'])) return true;
    
    $steps = json_decode($att['step_ids'], true);
    
    if (!is_array($steps) || count($steps) === 0) return true;
    
    if ($current_step === null) return false;
    
    return in_array($current_step, $steps);
}));

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
            // اگر اصلاً ارجاعی وجود داره، چک کن آیا بعد از آپلود این فایل بوده
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