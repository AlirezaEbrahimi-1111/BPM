<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sms.php';
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

    if (empty($input['task_id']) || empty($input['message'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار و پیام الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // بررسی مالکیت کار
    $stmt = $db->prepare("SELECT t.*, u.phone as assignee_phone, u.first_name, u.last_name 
                          FROM tasks t 
                          LEFT JOIN users u ON t.assignee_id = u.id 
                          WHERE t.id = ? AND t.creator_id = ?");
    $stmt->execute([$input['task_id'], $user_id]);
    $task = $stmt->fetch();

    if (!$task) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجاز به ارسال یادآوری برای این کار نیستید']);
        exit;
    }
    error_log("🔔 Sending notification to user: " . $task['assignee_id']);

    // ✅ ارسال نوتیفیکیشن به کاربر مسئول
    $notification = new Notification($db);

// ✅ مقداردهی اولیه
$notification_message = 'یادآوری برای کار: ' . $task['title'];

    if (!empty($input['task_id'])) {
        $notification_message = 'پیام: ' . $input['message'];
    }

    $notification_data = [
        'to_user_id' => $task['assignee_id'],
        'title' => 'یادآوری بابت: ' . $task['title'],
        'message' => $notification_message,
        'type' => 'warning',
        'related_type' => 'task',
        'related_id' => $input['task_id'],
        'link' => '/pages/task-detail.php?id=' . $input['task_id'],
        'sms_pattern' => 'task_reminder','sms_args' => [$task['title'],
        $input['message']]
    ];

    error_log("Notification data: " . print_r($notification_data, true));

    $result = $notification->create($notification_data);
    // ✅ ارسال پیامک به کاربر مسئول
    // $sms = new SMS($db);
    // $sms_message = 'یادآوری: ' . $task['title'] . ' - ' . $input['message'];
    // $sms->send(
    //     $task['assignee_id'],           // آی‌دی کاربر
    //     $task['assignee_phone'],        // شماره موبایل
    //     $sms_message,                   // متن پیامک
    //     'task_notification',            // نام الگو (برای لاگ)
    //     null                            // notification_id
    // );
    
    // ثبت در تاریخچه
    $stmt = $db->prepare("
        INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
        VALUES (?, ?, ?, 'updated', ?)
    ");
    $stmt->execute([
        $input['task_id'],
        $user_id,
        $task['assignee_id'],
        'یادآوری ارسال شد: ' . $input['message']
    ]);
    echo json_encode(['success' => true, 'message' => 'یادآوری با موفقیت ارسال شد']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Send reminder error: " . $e->getMessage());
}

// تابع ارسال پیامک (شبیه‌سازی)
function sendSMS($phone, $message)
{
    // در اینجا باید با API سرویس پیامکی متصل شوید
    error_log("SMS Reminder to {$phone}: {$message}");
    return true; // شبیه‌سازی موفقیت
}
?>