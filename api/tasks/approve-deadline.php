<?php
error_reporting(0);
ini_set('display_errors', 0);
// ==================================================
// api/tasks/approve-deadline.php
// تأیید درخواست تمدید موعد - نسخه DEBUG
// ==================================================

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once '../../includes/TaskManager.php';
require_once '../../includes/Notification.php';
require_once '../../includes/JalaliHelper.php';

try {
    error_log("========================================");
    error_log("🔵 APPROVE DEADLINE REQUEST START");
    error_log("========================================");

    $user_id = requireAuth();
    $data = json_decode(file_get_contents('php://input'), true);

    error_log("User ID: $user_id");
    error_log("Request data: " . json_encode($data));

    if (!isset($data['request_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID درخواست الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $request_id = (int) $data['request_id'];

    // دریافت اطلاعات درخواست
    $stmt = $db->prepare("
        SELECT dr.*, t.title, t.creator_id, t.assignee_id, t.deadline, t.is_workflow_task, t.workflow_instance_id, t.organization_id
        FROM deadline_requests dr
        JOIN tasks t ON dr.task_id = t.id
        WHERE dr.id = ? AND dr.status = 'pending'
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        error_log("❌ Request not found or already processed");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد یا قبلاً پاسخ داده شده']);
        exit;
    }

    error_log("Request info: " . json_encode($request));

    // نقش کاربر فعلی (برای اجازهٔ تأیید توسط مدیر)
    // 🔒 خط قرمز: اختیار «مدیر» فقط داخل همان سازمانِ کار معتبر است
    $roleStmt = $db->prepare("SELECT role, organization_id FROM users WHERE id = ?");
    $roleStmt->execute([$user_id]);
    $me = $roleStmt->fetch(PDO::FETCH_ASSOC);
    $isManager = $me
        && in_array($me['role'], ['management', 'supervisor', 'admin'])
        && (int)$me['organization_id'] === (int)$request['organization_id'];

    $is_workflow  = ($request['is_workflow_task'] == 1);
    $task_id      = $request['task_id'];
    $new_deadline = $request['requested_new_deadline'];

    // ✅ بررسی مجوز: approver فعلی یا مدیر
    if ($request['current_approver_id'] != $user_id && !$isManager) {
        error_log("❌ Access denied: current_approver=" . $request['current_approver_id'] . ", user_id=$user_id");
        error_log("approve-deadline denied (not approver/manager) | user_id={$user_id} | request_id={$request_id} | task_id={$task_id} | current_approver_id={$request['current_approver_id']} | isManager=" . ($isManager ? '1' : '0'));
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجاز به تأیید این درخواست نیستید']);
        exit;
    }

    // ===== کار روتین: تأیید تک‌مرحله‌ای و نهایی =====
    if ($is_workflow) {
        // original_deadline تغییر نمی‌کند
        $upd = $db->prepare("UPDATE tasks SET deadline = ?, has_pending_deadline_request = 0, updated_at = NOW() WHERE id = ?");
        $upd->execute([$new_deadline, $task_id]);

        // هم‌گام‌سازی موعد مرحله در روتین
        $sync = $db->prepare("UPDATE workflow_instance_steps SET deadline = ? WHERE task_id = ?");
        $sync->execute([$new_deadline, $task_id]);

        $reqUpd = $db->prepare("UPDATE deadline_requests SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $reqUpd->execute([$request_id]);

        // from_user_id = کسی که این اقدام (تأیید) را انجام داد؛ نمایش تاریخچه نامِ from_user را به‌عنوان «انجام‌دهنده» نشان می‌دهد
        $history_stmt = $db->prepare("
            INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
            VALUES (?, ?, ?, 'deadline_extended', ?)
        ");
        $history_stmt->execute([
            $task_id,
            $user_id,
            $request['requested_by'],
            json_encode([
                'old_deadline' => $request['deadline'],
                'new_deadline' => $new_deadline,
                'reason' => $request['reason']
            ], JSON_UNESCAPED_UNICODE)
        ]);

        // اطلاع به درخواست‌دهنده
        try {
            $notification = new Notification($db);
            $jalali_date = JalaliHelper::formatJalaliDate($new_deadline);
            $notification->create([
                'to_user_id'   => $request['requested_by'],
                'title'        => 'تمدید موعد تأیید شد: ' . $request['title'],
                'message'      => 'درخواست تمدید موعد کار «' . $request['title'] . '» تأیید شد. موعد جدید: ' . $jalali_date,
                'type'         => 'success',
                'related_type' => 'task',
                'related_id'   => $task_id,
                'link'         => '/pages/task-detail.php?id=' . $task_id,
            ]);
        } catch (Exception $e) {
            error_log("notif error: " . $e->getMessage());
        }

        echo json_encode([
            'success'        => true,
            'message'        => 'موعد کار روتین تمدید شد',
            'final_approval' => true,
            'new_deadline'   => $new_deadline
        ]);
        exit;
    }

    $approval_chain = json_decode($request['approval_chain'], true);

    error_log("🔗 Approval Chain: " . $request['approval_chain']);
    error_log("👤 Current Approver: " . $request['current_approver_id']);
    error_log("👤 User ID: " . $user_id);

    // پیدا کردن موقعیت فعلی در زنجیره
    $current_index = array_search($user_id, $approval_chain);

    if ($current_index === false) {
        error_log("❌ User not in approval chain!");
        error_log("approve-deadline denied (not in approval chain) | user_id={$user_id} | request_id={$request_id} | task_id={$task_id} | approval_chain=" . $request['approval_chain']);
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما در زنجیره تأیید نیستید']);
        exit;
    }

    error_log("📍 Current index in chain: $current_index");

    // ✅ آیا approver بعدی وجود دارد؟
    $next_index = $current_index + 1;
    $has_next_approver = isset($approval_chain[$next_index]);

    error_log("🔍 Has next approver? " . ($has_next_approver ? 'YES' : 'NO'));

    if ($has_next_approver) {
        // ===== مرحله میانی: ارسال به approver بعدی =====
        $next_approver_id = $approval_chain[$next_index];

        error_log("➡️ Moving to next approver: $next_approver_id");

        // آپدیت current_approver
        $update_stmt = $db->prepare("
            UPDATE deadline_requests 
            SET current_approver_id = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $result = $update_stmt->execute([$next_approver_id, $request_id]);

        if (!$result) {
            error_log("❌ Failed to update current_approver_id");
            error_log("Error: " . json_encode($update_stmt->errorInfo()));
        } else {
            error_log("✅ Updated current_approver_id to $next_approver_id");
        }

        // ارسال نوتیفیکیشن به approver بعدی
        $notification = new Notification($db);

        // دریافت اطلاعات approver فعلی
        $stmt = $db->prepare("
            SELECT CONCAT(first_name, ' ', last_name) as full_name 
            FROM users WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        $current_approver = $stmt->fetch(PDO::FETCH_ASSOC);

        // تبدیل تاریخ به شمسی
        $jalali_date = JalaliHelper::formatJalaliDate($new_deadline);

        $message = sprintf(
            '«%s» درخواست تمدید موعد کار «%s» تا تاریخ %s را تأیید کرد. لطفاً شما نیز بررسی کنید',
            $current_approver['full_name'],
            $request['title'],
            $jalali_date
        );

        error_log("📧 Sending notification to next approver: $next_approver_id");

        $notification->create([
            'to_user_id' => $next_approver_id,
            'title' => 'درخواست تمدید موعد - نیاز به تأیید شما',
            'message' => $message,
            'type' => 'warning',
            'related_type' => 'task',
            'related_id' => $task_id,
            'link' => '/pages/task-detail.php?id=' . $task_id,
            'sms_pattern' => 'deadline_mid_approved',
            'sms_args'    => [$current_approver['full_name'], $request['title'], $jalali_date],
        ]);

        error_log("✅ Moved to next stage successfully");
        error_log("========================================");

        echo json_encode([
            'success' => true,
            'message' => 'درخواست تأیید شد و به مرحله بعد ارسال گردید',
            'next_stage' => true,
            'next_approver' => $next_approver_id
        ]);

    } else {
        // ===== تأیید نهایی: آخرین approver است =====
        error_log("✅ Final approval - updating task deadline");

        // آپدیت deadline کار — این شاخه فقط کارِ مقطعی است (کارِ روتین بالاتر
        // return شده). due_date را هم هم‌راستا با deadline می‌کنیم چون گیتِ ارجاع
        // (TaskManager::delegateTask) و فیلتر/مرتب‌سازیِ تاریخ در my-tasks/all-tasks
        // مستقیم due_date را می‌خوانند، نه بیشینهٔ سه ستون را.
        $update_stmt = $db->prepare("
            UPDATE tasks
            SET
                deadline = ?,
                original_deadline = ?,
                due_date = ?,
                has_pending_deadline_request = 0,
                updated_at = NOW()
            WHERE id = ?
        ");
        $result = $update_stmt->execute([$new_deadline, $new_deadline, $new_deadline, $task_id]);

        if (!$result) {
            error_log("❌ Failed to update task deadline");
            error_log("Error: " . json_encode($update_stmt->errorInfo()));
        } else {
            error_log("✅ Updated task deadline to $new_deadline");
        }

        // آپدیت درخواست به approved
        $request_stmt = $db->prepare("
            UPDATE deadline_requests 
            SET status = 'approved', updated_at = NOW() 
            WHERE id = ?
        ");
        $result = $request_stmt->execute([$request_id]);
        // ثبت تاریخچه تمدید موعد
        // from_user_id = کسی که این اقدام (تأیید) را انجام داد؛ نمایش تاریخچه نامِ from_user را به‌عنوان «انجام‌دهنده» نشان می‌دهد
        $history_stmt = $db->prepare("
    INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
    VALUES (?, ?, ?, 'deadline_extended', ?)
");
        $history_stmt->execute([
            $task_id,
            $user_id,
            $request['requested_by'],
            json_encode([
                'old_deadline' => $request['deadline'],
                'new_deadline' => $new_deadline,
                'reason' => $request['reason']
            ], JSON_UNESCAPED_UNICODE)
        ]);
        if (!$result) {
            error_log("❌ Failed to update request status");
            error_log("Error: " . json_encode($request_stmt->errorInfo()));
        } else {
            error_log("✅ Updated request status to approved");
        }

        // ارسال نوتیفیکیشن به assignee (درخواست‌کننده)
        $notification = new Notification($db);

        // دریافت اطلاعات approver نهایی
        $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $final_approver = $stmt->fetch(PDO::FETCH_ASSOC);
        $approverName = $final_approver['full_name'] ?? 'مدیر';

        // تبدیل تاریخ به شمسی
        $jalali_date = JalaliHelper::formatJalaliDate($new_deadline);

        $message = "درخواست تمدید موعد کار «" . $request['title'] . "» توسط {$approverName} تأیید شد.\n";
        $message .= "موعد جدید: " . $jalali_date;

        $notification->create([
            'to_user_id' => $request['assignee_id'],
            'title' => 'درخواست تمدید تأیید شد: ' . $request['title'],
            'message' => $message,
            'type' => 'success',
            'related_type' => 'task',
            'related_id' => $task_id,
            'link' => '/pages/task-detail.php?id=' . $task_id,
            'sms_pattern' => 'deadline_approved',
            'sms_args'    => [$request['title'], $approverName, $jalali_date],
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'درخواست تمدید موعد تأیید نهایی شد',
            'final_approval' => true,
            'new_deadline' => $new_deadline,
            'jalali_deadline' => $jalali_date
        ]);
    }

} catch (Exception $e) {
    error_log("approve-deadline.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور'
    ]);
}
?>