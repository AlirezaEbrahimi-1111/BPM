<?php
/**
 * reject-deadline.php
 * رد درخواست تمدید موعد انجام کار
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once '../../includes/TaskManager.php';
require_once '../../includes/Notification.php';

try {
    // 🔒 خط قرمز: این فایل قبلاً هیچ احراز هویت یا کنترل دسترسی‌ای نداشت —
    // هر کاربرِ ناشناس با فقط دانستنِ request_id می‌توانست درخواست تمدید
    // موعدِ هر کاری، در هر سازمانی را رد کند
    $user_id = requireAuth();

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
        SELECT dr.*, t.title, t.creator_id, t.assignee_id, t.deadline, t.organization_id
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

    // 🔒 مجوز: تأییدکنندهٔ فعلی، یا مدیرِ همان سازمان (هم‌راستا با approve-deadline.php)
    $roleStmt = $db->prepare("SELECT role, organization_id FROM users WHERE id = ?");
    $roleStmt->execute([$user_id]);
    $me = $roleStmt->fetch(PDO::FETCH_ASSOC);
    $isManager = $me
        && in_array($me['role'], ['management', 'supervisor', 'admin'])
        && (int)$me['organization_id'] === (int)$request['organization_id'];

    if ((int)($request['current_approver_id'] ?? 0) !== (int)$user_id && !$isManager) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجاز به رد این درخواست نیستید']);
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

    // ✅ ثبت در تاریخچهٔ کار — قبلاً فقط تأیید ثبت می‌شد، نه رد
    // from_user_id = کسی که این اقدام (رد) را انجام داد؛ نمایش تاریخچه نامِ from_user را به‌عنوان «انجام‌دهنده» نشان می‌دهد
    $history_stmt = $db->prepare("
        INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
        VALUES (?, ?, ?, 'deadline_rejected', ?)
    ");
    $history_stmt->execute([
        $task_id,
        $user_id,
        $request['requested_by'],
        $rejection_reason !== '' ? $rejection_reason : null
    ]);

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
            'link' => '/pages/task-detail.php?id=' . $task_id,
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