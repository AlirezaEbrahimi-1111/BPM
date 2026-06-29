<?php
/**
 * reject-deadline.php
 * رد درخواست تمدید موعد انجام کار
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once '../../includes/TaskManager.php';
require_once '../../includes/Notification.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['request_id'])) {
        echo json_encode(['success' => false, 'message' => 'ID درخواست الزامی است']);
        exit;
    }

    // ✅ اصلاح: استفاده از روش صحیح
    $database = new Database();
    $db = $database->getConnection();
    
    $rejection_reason = $data['rejection_reason'] ?? '';
    $request_id = (int)$data['request_id'];
    
    // دریافت اطلاعات درخواست
    $stmt = $db->prepare("
        SELECT dr.*, t.title, t.creator_id, t.assignee_id, t.deadline
        FROM deadline_requests dr
        JOIN tasks t ON dr.task_id = t.id
        WHERE dr.id = ? AND dr.status = 'pending'
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد یا قبلاً پاسخ داده شده']);
        exit;
    }

    $task_id = $request['task_id'];
    $current_deadline = $request['deadline'];

    // آپدیت: پاک کردن flag pending
    $update_stmt = $db->prepare("
        UPDATE tasks 
        SET 
            has_pending_deadline_request = 0,
            updated_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->execute([$task_id]);

    // آپدیت: تنظیم درخواست به rejected
    $request_stmt = $db->prepare("
        UPDATE deadline_requests 
        SET 
            status = 'rejected',
            rejection_reason = ?,
            updated_at = NOW() 
        WHERE id = ?
    ");
    $request_stmt->execute([$rejection_reason, $request_id]);

    // ارسال نوتیفیکیشن به مسئول انجام
    try {
        $notification = new Notification($db);
        
        $creator_stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?");
        $creator_stmt->execute([$request['creator_id']]);
        $creator = $creator_stmt->fetch(PDO::FETCH_ASSOC);
        $creatorName = $creator['full_name'] ?? 'مدیر';

        $message = "درخواست تمدید موعد کار «" . $request['title'] . "» توسط {$creatorName} رد شد.\n";
        $message .= "موعد باقی‌مانده: " . $current_deadline;
        
        if (!empty($rejection_reason)) {
            $message .= "\n\nدلیل رد: " . $rejection_reason;
        }

        $notification->create([
            'to_user_id' => $request['assignee_id'],
            'title' => 'درخواست تمدید رد شد: ' . $request['title'],
            'message' => $message,
            'type' => 'warning',
            'link' => 'task-detail.php?id=' . $task_id,
            'related_type' => 'task',
            'related_id' => $task_id,
                'sms_pattern' => 'deadline_rejected',
    'sms_args'    => [$request['title'], $creatorName],
        ]);
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'درخواست تمدید موعد رد شد',
        'current_deadline' => $current_deadline
    ]);

} catch (Exception $e) {
    error_log("reject-deadline error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور: ' . $e->getMessage()
    ]);
}
?>