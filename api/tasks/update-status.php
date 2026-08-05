<?php
// ✅ شروع Output Buffering
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: PUT, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// ✅ Set error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: [$errno] $errstr in $errfile:$errline");
    return true;
});

// ✅ پاک کردن هر output غیر ضروری
ob_clean();

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/WorkflowManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['task_id']) || empty($input['status'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار و وضعیت الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);
    $workflowManager = new WorkflowManager($db);

    // بررسی: آیا این کار بخشی از workflow است؟
    $stmt = $db->prepare("SELECT is_workflow_task, workflow_instance_id FROM tasks WHERE id = ?");
    $stmt->execute([$input['task_id']]);
    $task_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($task_info && $task_info['is_workflow_task'] == 1 && in_array($input['status'], ['completed', 'approved'])) {
        // این کار بخشی از workflow است و در حال تکمیل شدن است
        $result = $workflowManager->completeStep($input['task_id'], $user_id, $input['notes'] ?? '');

        if (!$result['success']) {
            // اگر completeStep خطا داد، از روش عادی استفاده می‌کنیم
            error_log("update-status.php completeStep failed, falling back | user_id={$user_id} | task_id={$input['task_id']} | status={$input['status']} | message=" . ($result['message'] ?? ''));
            $result = $taskManager->updateTaskStatus(
                $input['task_id'],
                $input['status'],
                $user_id,
                $input['notes'] ?? null
            );
        }
    } else {
        // کار عادی (غیر workflow)
        $result = $taskManager->updateTaskStatus(
            $input['task_id'],
            $input['status'],
            $user_id,
            $input['notes'] ?? null
        );
    }

    if ($result['success']) {
        http_response_code(200);
    } else {
        error_log("update-status.php failed | user_id={$user_id} | task_id={$input['task_id']} | status={$input['status']} | message=" . ($result['message'] ?? ''));
        http_response_code(400);
    }

    // ✅ پاک کردن buffer و خروج JSON خالص
    ob_end_clean();
    echo json_encode($result);

} catch (Exception $e) {
    // ✅ پاک کردن buffer
    ob_end_clean();
    
    http_response_code(500);
    error_log("Update status error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'خطای داخلی سرور: ' . $e->getMessage()
    ]);
}

// ✅ بازگردانی error handler
restore_error_handler();

?>