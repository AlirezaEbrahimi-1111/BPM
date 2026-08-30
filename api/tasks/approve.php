<?php
// api/tasks/approve.php

// ✅ شروع Output Buffering برای جلوگیری از HTML در output
ob_start();

// ✅ Headers را قبل از require کنید
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// ✅ Set error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: [$errno] $errstr in $errfile:$errline");
    // Don't output error to browser
    return true;
});

// ✅ پاک کردن هر output غیر ضروری
ob_clean();

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once '../../includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);

    // اعتبارسنجی ورودی‌ها
    if (empty($input['task_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }

    if (!isset($input['approve'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'وضعیت تأیید الزامی است']);
        exit;
    }

    // اگر رد می‌شود، توضیحات الزامی است
    if (!$input['approve'] && empty($input['notes'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'برای رد کار، وارد کردن دلیل الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);

    // 🆕 فاز ۲: اگر این تسک یک «مرحلهٔ تصمیم»ِ روتین است، تأیید/رد از منطقِ انشعابِ
    // WorkflowManager رد شود (نه از approveOrRejectTaskِ عادی).
    $wfDecision = $db->prepare("
        SELECT 1 FROM tasks t
        JOIN workflow_instance_steps wis ON wis.task_id = t.id
        JOIN workflow_steps ws ON ws.id = wis.step_id
        WHERE t.id = ? AND t.is_workflow_task = 1 AND ws.is_decision = 1
    ");
    $wfDecision->execute([$input['task_id']]);
    if ($wfDecision->fetchColumn()) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/WorkflowManager.php';
        $workflowManager = new WorkflowManager($db);
        $result = $workflowManager->resolveStepDecision(
            $input['task_id'],
            $user_id,
            $input['approve'] ? 'approve' : 'reject',
            $input['notes'] ?? ''
        );
        ob_end_clean();
        echo json_encode($result);
        exit;
    }

    // فراخوانی متد تأیید/رد
    $result = $taskManager->approveOrRejectTask(
        $input['task_id'],
        $user_id,
        (bool) $input['approve'],
        $input['notes'] ?? ''
    );

    if ($result['success']) {
        $previousPerson = null;
        $notification = new Notification($db);
        $task = $taskManager->getTask($input['task_id']);

        // دریافت نام کامل کاربر تأیید/رد کننده
        $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $rejectorName = $userRow['full_name'] ?? 'کاربر ' . $user_id;

        if ($task['creator_id'] != $task['assignee_id']) {
            if ($input['approve']) {
                // ✅ نوتیفیکیشن برای تأیید کار
                $message = 'کار «' . ($task['title'] ?? 'نامشخص') . '» توسط "' . $rejectorName . '" تأیید شد.';

                if (!empty($input['notes'])) {
                    $message .= "\n\nتوضیحات: " . trim($input['notes']);
                }

                if ($result['continue_approval'] ?? false) {
                    $nextApproverId = $task['assignee_id'];
                    if ($previousPerson != $user_id) {
                        try {
                            $notification->create([
                                'to_user_id' => $nextApproverId,
                                'title' => 'کار جدید برای تأیید: ' . ($task['title'] ?? 'نامشخص'),
                                'message' => $message,
                                'type' => 'info',
                                'link' => '/pages/task-detail.php?id=' . $input['task_id'],
                                'related_type' => 'task',
                                'related_id' => $input['task_id'],
                                'is_read' => 0,
                                'sms_pattern' => 'task_needs_approval',
                                'sms_args' => [$task['title'] ?? 'نامشخص']
                            ]);
                        } catch (Exception $notifError) {
                            error_log("Notification create error: " . $notifError->getMessage());
                            // Continue despite notification error
                        }
                    }
                }
            } else {
                // ✅ نوتیفیکیشن برای رد کار
                $message = 'کار «' . ($task['title'] ?? 'نامشخص') . '» توسط "' . $rejectorName . '" رد شد.';

                if (!empty($input['notes'])) {
                    $message .= "\n\nدلیل: " . trim($input['notes']);
                }

                // پیدا کردن نفر قبلی (انجام‌دهنده)
                $chain = $taskManager->getDelegationChain($input['task_id']);
                if (!empty($chain)) {
                    for ($i = count($chain) - 1; $i >= 0; $i--) {
                        if ($chain[$i]['to_user_id'] == $user_id) {
                            $previousPerson = $chain[$i]['from_user_id'];
                            break;
                        }
                    }
                }

                // اگر از زنجیره پیدا نشد، از task_history پیدا کن
                if (!$previousPerson) {
                    $stmt = $db->prepare("
                        SELECT from_user_id 
                        FROM task_history 
                        WHERE task_id = ? 
                        AND action = 'pending_approval'
                        AND to_user_id = ?
                        ORDER BY created_at DESC 
                        LIMIT 1
                    ");
                    $stmt->execute([$input['task_id'], $user_id]);
                    $historyRow = $stmt->fetch(PDO::FETCH_ASSOC);
                    $previousPerson = $historyRow['from_user_id'] ?? null;
                }

                // اگر هنوز پیدا نشد، از جدول tasks خود کار بگیر
                if (!$previousPerson) {
                    $stmt = $db->prepare("
                        SELECT assignee_id 
                        FROM tasks 
                        WHERE id = ? 
                        AND status = 'in_progress'
                    ");
                    $stmt->execute([$input['task_id']]);
                    $taskRow = $stmt->fetch(PDO::FETCH_ASSOC);
                    $previousPerson = $taskRow['assignee_id'] ?? null;
                }

                // فقط اگر نفر پیدا شد، notification بفرست
                if ($previousPerson) {
                    if ($previousPerson != $user_id) {
                        try {
                            $notification->create([
                                'to_user_id' => $previousPerson,
                                'title' => 'کار رد شد: ' . ($task['title'] ?? 'نامشخص'),
                                'message' => $message,
                                'type' => 'warning',
                                'link' => '/pages/task-detail.php?id=' . $input['task_id'],
                                'related_type' => 'task',
                                'related_id' => $input['task_id'],
                                'is_read' => 0
                            ]);
                        } catch (Exception $notifError) {
                            error_log("Notification create error: " . $notifError->getMessage());
                            // Continue despite notification error
                        }
                    }
                } else {
                    error_log("Could not find previous person for rejected task: " . $input['task_id']);
                }
            }
        }

        http_response_code(200);
    } else {
        error_log("approve.php approveOrRejectTask failed | user_id={$user_id} | task_id={$input['task_id']} | approve=" . (($input['approve'] ?? false) ? '1' : '0') . " | message=" . ($result['message'] ?? ''));
        http_response_code(400);
    }

    // ✅ پاک کردن buffer و خروج JSON خالص
    ob_end_clean();
    echo json_encode($result);

} catch (Exception $e) {
    // ✅ پاک کردن buffer برای خروج JSON پاک
    ob_end_clean();
    
    http_response_code(500);
    error_log("Approve task error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'خطای داخلی سرور'
    ]);
}

// ✅ بازگردانی error handler به حالت پیش‌فرض
restore_error_handler();

?>