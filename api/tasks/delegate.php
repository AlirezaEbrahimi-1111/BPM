<?php

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['task_id']) || empty($input['to_user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار و کاربر مقصد الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);

    $task = $taskManager->getTask($input['task_id']);
    if (!$task || ($task['assignee_id'] != $user_id && $task['creator_id'] != $user_id)) {
        error_log("delegate.php denied | user_id={$user_id} | task_id={$input['task_id']}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // ارجاع (همیشه با ثبت تاریخچه)
    $result = $taskManager->delegateTask(
        $input['task_id'],
        $input['to_user_id'],
        $user_id,
        $input['notes'] ?? '',
        $input['due_date'] ?? null
    );

    if ($result['success']) {

        // ✅ مدیریت دید تاریخچه: فقط تعریف‌کنندهٔ کار می‌تواند سیاست را تعیین/تغییر دهد
        if ((int)$task['creator_id'] === (int)$user_id && array_key_exists('share_history', $input)) {
            $shareHistory = (int)(!empty($input['share_history']));
            $shStmt = $db->prepare("UPDATE tasks SET share_history = ? WHERE id = ?");
            $shStmt->execute([$shareHistory, $input['task_id']]);
        }
        // اگر کاربر تعریف‌کننده نباشد، سیاست تغییر نمی‌کند (تیک هم برایش نمایش داده نمی‌شود)

        // نوتیفیکیشن
        $notif = new Notification($db);
        $notes = $input['notes'] ?? '';
        if (empty($notes)) {
            $name = 'کاربر';
        } else {
            $parts = explode(':', $notes, 2);
            $name = trim($parts[0]) ?: 'کاربر';
        }
        $notif->create([
            'to_user_id' => $input['to_user_id'],
            'title' => "کار جدید ارجاع شده",
            'message' => "کار «{$task['title']}» توسط {$name} به شما ارجاع داده شد",
            'type' => "warning",
            'related_type' => 'task',
            'related_id' => $input['task_id'],
            'link' => "/pages/task-detail.php?id={$input['task_id']}",
            'sms_pattern' => 'task_assigned',
            'sms_args'    => [$task['title'], $name],
            // 🔒 چند خط بالاتر assignee_id همین الان به to_user_id تغییر کرد؛
            // چک self-notification پیش‌فرض این رو با ارجاع‌دادن به خود
            // creator اشتباه می‌گرفت و بی‌صدا نوتیف رو حذف می‌کرد
            'skip_self_check' => true
        ]);

        http_response_code(200);
    } else {
        error_log("delegate.php delegateTask failed | user_id={$user_id} | task_id={$input['task_id']} | to_user_id={$input['to_user_id']} | message=" . ($result['message'] ?? ''));
        http_response_code(400);
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Delegate task error: " . $e->getMessage());
}
?>