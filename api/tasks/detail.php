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

    // بررسی دسترسی
    $hasAccess = false;

    // 1. سازنده کار
    if ($task['creator_id'] == $user_id) {
        $hasAccess = true;
    }

    // 2. فرد تخصیص داده شده (حتی اگر قبلاً بوده)
    if ($task['assignee_id'] == $user_id) {
        $hasAccess = true;
    }

    // امنیت: به‌جای id ثابت → سوپرادمین یا مدیرِ هم‌سازمانِ این کار
    if (!$hasAccess) {
        $stmt = $db->prepare("SELECT role, activity_section, organization_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $me = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($me) {
            $isOrgManager = ($me['activity_section'] === 'management'
                && in_array($me['role'], ['supervisor', 'manager'], true)
                && (int) $me['organization_id'] === (int) ($task['organization_id'] ?? 0));
            if ($isOrgManager) {
                $hasAccess = true;
            }
        }
    }

    // 3. افرادی که در زنجیره ارجاعات کار بوده‌اند
    if (!$hasAccess) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM task_history 
            WHERE task_id = ? 
            AND (from_user_id = ? OR to_user_id = ?)
        ");
        $stmt->execute([$_GET['id'], $user_id, $user_id]);
        $historyCount = $stmt->fetch()['count'];

        if ($historyCount > 0) {
            $hasAccess = true;
        }
    }

    // 4. چک دسترسی برای workflow tasks
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
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as count 
                        FROM users 
                        WHERE id = ? 
                        AND activity_section = ? 
                        AND is_active = 1
                    ");
                    $stmt->execute([$user_id, $currentStep['activity_section']]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($result['count'] > 0) {
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
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE id = ? 
            AND activity_section = ? 
            AND is_active = 1
        ");
        $stmt->execute([$user_id, $task['activity_section']]);
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            $hasAccess = true;
        }
    }
    // 6. کاربرانی که آیتم چک‌لیست به آن‌ها (یا واحدشان) ارجاع شده
    if (!$hasAccess) {
        // واحدِ کاربر را بخوان (برای ارجاع‌های نوع section)
        $secStmt = $db->prepare("SELECT activity_section FROM users WHERE id = ?");
        $secStmt->execute([$user_id]);
        $user_section = $secStmt->fetchColumn() ?: '';

        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM task_checklist_items ci
            WHERE ci.task_id = ?
              AND (
                  (ci.assignee_type = 'user'    AND ci.assignee_value = ?)
                  OR (ci.assignee_type = 'section' AND ci.assignee_value = ?)
              )
        ");
        $stmt->execute([$_GET['id'], (string)$user_id, $user_section]);
        $checklistCount = $stmt->fetch()['count'];

        if ($checklistCount > 0) {
            $hasAccess = true;
        }
    }
    if (!$hasAccess) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // دریافت تعداد تکمیل‌ها
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM task_history WHERE task_id = ? AND action = 'completed'");
    $stmt->execute([$_GET['id']]);
    $task['completed_count'] = $stmt->fetch()['count'];

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

    echo json_encode([
        'success' => true,
        'task' => $task,
        'history' => $history,
        'can_edit' => $hasAccess
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Get task detail error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
}
?>