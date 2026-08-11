<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/recurring-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/task-dates-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user-sections.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/task-access.php';
try {
    $user_id = requireAuth();

    if (empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);

    $task = $taskManager->getTask($_GET['id']);

    // بررسی دسترسی — زنجیره‌ی مشترک (سازنده/مسئول/مدیر/تاریخچه/چک‌لیست/بیننده)
    // از includes/task-access.php میاد؛ فقط قوانینِ تخصصیِ همین صفحه (عضویتِ
    // مرحله‌ی workflow و عضویتِ سراسریِ واحد برایِ کارهایِ روتین) پایین‌تر
    // به‌عنوانِ راهِ‌فرارِ اضافی باقی می‌مونن
    $access = taskUserAccess($db, (int) $user_id, $task);
    $hasAccess = $access['has_access'];
    $me = loadUserForPermissions($db, $user_id);

    // چک دسترسی برای workflow tasks
    if (!$hasAccess && $task['is_workflow_task'] == 1 && $task['workflow_instance_id']) {
        try {
            $stmt = $db->prepare("
                SELECT current_step
                FROM workflow_instances
                WHERE id = ?
            ");
            $stmt->execute([$task['workflow_instance_id']]);
            $workflowInstance = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($workflowInstance && $workflowInstance['current_step']) {
                // ✅ اصلاح: JOIN با workflow_steps
                $stmt = $db->prepare("
                    SELECT ws.activity_section
                    FROM workflow_instance_steps wis
                    JOIN workflow_steps ws ON wis.step_id = ws.id
                    WHERE wis.instance_id = ?
                    AND wis.task_id = ?
                    LIMIT 1
                ");
                $stmt->execute([
                    $task['workflow_instance_id'],
                    $_GET['id']
                ]);
                $currentStep = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($currentStep && $currentStep['activity_section']) {
                    // 🆕 عضویت در هر یک از واحدهای کاربر (چندواحدی)
                    if (us_userInSection($db, $user_id, $currentStep['activity_section'])) {
                        if (in_array($task['status'], ['in_progress', 'pending', 'not_started', 'delegated'])) {
                            $hasAccess = true;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error checking workflow access: " . $e->getMessage());
        }
    }

   // 5. برای کارهای روتین (periodic/continuous)، همه اعضای بخش مربوطه
    if (!$hasAccess && in_array($task['task_type'], ['periodic', 'continuous']) && $task['activity_section']) {
        // 🆕 عضویت در هر یک از واحدهای کاربر (چندواحدی)
        if (us_userInSection($db, $user_id, $task['activity_section'])) {
            $hasAccess = true;
        }
    }
    // چک‌لیست/بیننده از قبل توسطِ taskUserAccess() بالاتر بررسی شده؛ اگه هنوزم
    // دسترسی نبود یعنی نه از راهِ اون‌ها نه از راهِ workflow/periodic بالا
    $is_checklist_only = $access['is_checklist_only'];
    $is_viewer_only = $access['is_viewer_only'];
    $viewer_can_view_attachments = $access['viewer_can_view_attachments'];
    $viewer_can_view_history = $access['viewer_can_view_history'];

    if (!$hasAccess) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // دریافت تعداد تکمیل‌ها
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM task_history WHERE task_id = ? AND action = 'completed'");
    $stmt->execute([$_GET['id']]);
    $task['completed_count'] = $stmt->fetch()['count'];
    // ✅ موتور مشترک دوره — تا با داشبورد و لیست‌ها یکسان باشد
    $holidays = getHolidaySet($db);
    $task = enrichTaskDates($task, $db, $holidays, date('Y-m-d'));
    // ✅ اصلاح: دریافت اطلاعات مرحله فعال workflow (روش صحیح)
    $task['current_workflow_step'] = null;
    if ($task['is_workflow_task'] == 1 && $task['workflow_instance_id']) {
        try {
            // مرحله 1: گرفتن current_step از workflow_instances
            $stmt = $db->prepare("
                SELECT current_step
                FROM workflow_instances
                WHERE id = ?
            ");
            $stmt->execute([$task['workflow_instance_id']]);
            $instance = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($instance && $instance['current_step']) {
                // مرحله 2: گرفتن اطلاعات step از workflow_instance_steps
                $task['workflow_current_step'] = intval($instance['current_step']);
                error_log("✅ Workflow current_step: " . $task['workflow_current_step']);

                // ✅ بخش و وضعیتِ مرحلهٔ خودِ این تسک (نه current_step) — لازم برای حالت موازی
                $stmt = $db->prepare("
                    SELECT ws.activity_section, wis.status AS step_status,
                           wis.step_description
                    FROM workflow_instance_steps wis
                    JOIN workflow_steps ws ON wis.step_id = ws.id
                    WHERE wis.instance_id = ?
                    AND wis.task_id = ?
                    LIMIT 1
                ");
                $stmt->execute([
                    $task['workflow_instance_id'],
                    $_GET['id']
                ]);
                $stepInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                $task['current_step_section'] = $stepInfo ? $stepInfo['activity_section'] : null;
                $task['current_step_status']  = $stepInfo ? $stepInfo['step_status'] : null;
                $task['current_step_description'] = ($stepInfo && isset($stepInfo['step_description'])) ? $stepInfo['step_description'] : null;
            }
        } catch (Exception $e) {
            error_log("❌ Error getting workflow step: " . $e->getMessage());
        }
    }

    // دریافت تاریخچه
    $history = $taskManager->getTaskHistory($_GET['id']);

    // ✅ فیلتر تاریخچه بر اساس سیاست share_history (تعیین‌شده توسط تعریف‌کننده)
    if (!isset($task['share_history'])) {
        $shStmt = $db->prepare("SELECT share_history FROM tasks WHERE id = ?");
        $shStmt->execute([$_GET['id']]);
        $task['share_history'] = (int)($shStmt->fetchColumn() ?? 1);
    }
    $shareHistory    = (int)$task['share_history'];
    $isCreatorViewer = ((int)$task['creator_id'] === (int)$user_id);

    if ($shareHistory === 0 && !$isCreatorViewer) {
        // نقطهٔ ورود این بیننده = آخرین ارجاع/تخصیص کار به خودش
        $entryStmt = $db->prepare("
            SELECT MAX(created_at) FROM task_history
            WHERE task_id = ? AND to_user_id = ? AND action IN ('delegated','assigned')
        ");
        $entryStmt->execute([$_GET['id'], $user_id]);
        $viewerEntry = $entryStmt->fetchColumn();

        // فقط اگر نقطهٔ ورود مشخص داشت، تاریخچه را از آن لحظه به بعد محدود کن
        // (اگر نداشت → کل تاریخچه دیده می‌شود، طبق تصمیم)
        if (!empty($viewerEntry)) {
            $history = array_values(array_filter($history, function ($item) use ($viewerEntry) {
                return isset($item['created_at']) && $item['created_at'] >= $viewerEntry;
            }));
        }
    }

    // ✅ اطمینان از وجود is_deleted
    if (!isset($task['is_deleted'])) {
        $stmt = $db->prepare("SELECT is_deleted FROM tasks WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $delResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $task['is_deleted'] = $delResult ? intval($delResult['is_deleted']) : 0;
    }

    // 🔄 بررسی برگشت از period_done به حالت فعال (اگر دوره‌ی بعدی رسیده باشد)
    maybeStartNextPeriod($db, $task, $user_id);

    // 🔒 کاربر چک‌لیستی: فقط رویدادهای مربوط به خودش یا واحدهایش
    if ($is_checklist_only) {
        // 🆕 همهٔ واحدهای کاربر (در بلوک ۶ خوانده شده است)
        $mySections = $userSections ?? [];
        $history = array_values(array_filter($history, function ($h) use ($user_id, $mySections) {
            if ((int)($h['from_user_id'] ?? 0) === (int)$user_id) return true;
            if ((int)($h['to_user_id']   ?? 0) === (int)$user_id) return true;
            $hs = $h['checklist_section'] ?? '';
            if ($hs !== '' && in_array($hs, $mySections, true)) return true;
            return false;
        }));
    }

    echo json_encode([
        'success' => true,
        'task' => $task,
        'history' => $history,
        'is_checklist_only' => $is_checklist_only,
        'is_viewer_only' => $is_viewer_only, // 🆕 فقط از راهِ task_viewers دسترسی داره — نه ویرایش/اقدام
        'viewer_can_view_attachments' => $viewer_can_view_attachments, // 🆕 فقط برایِ is_viewer_only معنا داره
        'viewer_can_view_history' => $viewer_can_view_history,         // 🆕
        'can_edit' => $hasAccess && !$is_viewer_only
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Get task detail error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
}
