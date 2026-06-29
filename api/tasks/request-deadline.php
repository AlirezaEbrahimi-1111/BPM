<?php
// ==================================================
// api/tasks/request-deadline.php
// درخواست تمدید موعد انجام کار - نسخه DEBUG
// ==================================================

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/JalaliHelper.php';

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: [$errno] $errstr in $errfile:$errline");
    return true;
});

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('فقط POST مجاز است');
    }

    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['task_id']) || empty($input['new_deadline']) || empty($input['reason'])) {
        http_response_code(400);
        throw new Exception('شناسه کار، تاریخ جدید و دلیل الزامی هستند');
    }

    $task_id = (int) $input['task_id'];
    $new_deadline = $input['new_deadline'];
    $reason = trim($input['reason']);

    $database = new Database();
    $db = $database->getConnection();

    // ===== بررسی 1: آیا این کار مربوط به کاربر فعلی است (assignee)؟ =====
    $stmt = $db->prepare("SELECT id, assignee_id, creator_id, deadline, is_workflow_task, workflow_instance_id, activity_section FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        http_response_code(404);
        throw new Exception('کار یافت نشد');
    }

    error_log("Task info: creator=" . $task['creator_id'] . ", assignee=" . $task['assignee_id']);

    $is_workflow = ($task['is_workflow_task'] == 1);

    // نقش کاربر درخواست‌دهنده (برای روتین: تشخیص مدیر/تأییدکننده)
    $roleStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $roleStmt->execute([$user_id]);
    $requester_role = $roleStmt->fetchColumn();
    $requester_is_manager = in_array($requester_role, ['management', 'supervisor', 'admin']);

    if ($is_workflow) {
        // کار روتین: assignee ندارد؛ مجوز = عضو بخشِ همین مرحله (یا مدیر)
        $secChk = $db->prepare("SELECT COUNT(*) AS c FROM users WHERE id = ? AND activity_section = ? AND is_active = 1");
        $secChk->execute([$user_id, $task['activity_section']]);
        if ((int)$secChk->fetch(PDO::FETCH_ASSOC)['c'] === 0 && !$requester_is_manager) {
            http_response_code(403);
            throw new Exception('فقط کاربران بخش این مرحله می‌توانند درخواست تمدید بدهند');
        }
    } else {
        if ($task['assignee_id'] != $user_id) {
            http_response_code(403);
            throw new Exception('فقط مسئول انجام کار می‌تواند درخواست مهلت ارسال کند');
        }
    }

    // ===== بررسی 2: محدودیت روزانه (3 درخواست در روز) =====
    $today = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM deadline_requests 
        WHERE requested_by = ? 
        AND task_id = ?
        AND DATE(created_at) = ?
    ");
    $stmt->execute([$user_id, $task_id, $today]);
    $daily_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($daily_count >= 3) {
        http_response_code(429);
        throw new Exception('امکان درخواست تمدید موعد برای این کار، بیش از 3 بار در روز وجود ندارد.');
    }

    // ===== بررسی 3: صحت تاریخ/زمان =====
    $new_ts = strtotime($new_deadline);
    if ($new_ts === false) {
        http_response_code(400);
        throw new Exception('فرمت تاریخ نادرست است');
    }

    if ($is_workflow) {
        // کار روتین: موعد ساعتی، باید در آینده باشد، بدون سقف
        if ($new_ts <= time()) {
            echo json_encode(['success' => false, 'message' => 'موعد جدید باید در آینده باشد'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        // کار مقطعی: مثل قبل (تاریخ روز، سقف ۷۲۰ روز)
        if ($new_ts < strtotime(date('Y-m-d'))) {
            echo json_encode(['success' => false, 'message' => 'تاریخ جدید نمی‌تواند در گذشته باشد']);
            exit;
        }
        if ($new_ts > strtotime('+720 days')) {
            http_response_code(400);
            throw new Exception('تاریخ جدید خیلی دور است');
        }
    }

    // ===== بررسی 4: آیا قبلاً درخواست منتظر دارد؟ =====
    $stmt = $db->prepare("
        SELECT id FROM deadline_requests 
        WHERE task_id = ? AND status = 'pending'
    ");
    $stmt->execute([$task_id]);
    $pending_request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pending_request) {
        http_response_code(409);
        throw new Exception('درخواست منتظری برای این کار وجود دارد. لطفاً برای پاسخ منتظر بمانید');
    }

    // ===== 🆕 پیدا کردن زنجیره تأیید (Approval Chain) =====
    error_log("🔗 Building approval chain...");

    // کار روتین: تأییدکننده = سازندهٔ روتین (تعریف‌کننده)؛ زنجیرهٔ ارجاع لازم نیست
    $delegation_chain = [];
    if (!$is_workflow) {
        $stmt = $db->prepare("
            SELECT from_user_id, to_user_id, action, created_at
            FROM task_history 
            WHERE task_id = ? 
            AND action IN ('created', 'delegated')
            ORDER BY created_at ASC
        ");
        $stmt->execute([$task_id]);
        $delegation_chain = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    error_log("Delegation chain count: " . count($delegation_chain));
    error_log("Delegation chain: " . json_encode($delegation_chain));

    // ساخت زنجیره تأیید (از آخر به اول)
    $approval_chain = [];

    // پیدا کردن اولین کسی که به assignee فعلی کار داده
    if (!empty($delegation_chain)) {
        error_log("✅ Delegation chain exists");

        $first_delegator_to_current = null;

        foreach ($delegation_chain as $delegation) {
            if ((int) $delegation['to_user_id'] === (int) $task['assignee_id']) {
                $first_delegator_to_current = (int) $delegation['from_user_id'];
                error_log("✅ Found first delegator to current assignee: " . $first_delegator_to_current);
                break; // اولین رو پیدا کردیم
            }
        }

        if ($first_delegator_to_current) {
            $approval_chain[] = $first_delegator_to_current;
        } else {
            error_log("⚠️ No delegator found for current assignee, using creator");
            $approval_chain[] = (int) $task['creator_id'];
        }
    } else {
        error_log("⚠️ No delegation chain found, using creator");
        $approval_chain[] = (int) $task['creator_id'];
    }

    // creator را همیشه در انتهای زنجیره اضافه کن
    error_log("✅ Adding creator: " . $task['creator_id']);
    $approval_chain[] = (int) $task['creator_id'];

    // حذف تکراری‌ها
    $approval_chain = array_unique($approval_chain);
    $approval_chain = array_values($approval_chain);

    error_log("📊 Final approval chain: " . json_encode($approval_chain));

    // اولین approver (که الان باید تأیید کند)
    $current_approver_id = !empty($approval_chain) ? $approval_chain[0] : null;
    error_log("👤 Current approver ID: " . ($current_approver_id ?? 'NULL'));

    if (empty($current_approver_id)) {
        error_log("❌ CRITICAL: current_approver_id is empty!");
        throw new Exception('خطا در تعیین approver');
    }

    // ===== ایجاد درخواست جدید =====
    error_log("💾 Inserting deadline request...");

    $stmt = $db->prepare("
        INSERT INTO deadline_requests 
        (task_id, requested_by, requested_new_deadline, reason, status, current_approver_id, approval_chain) 
        VALUES (?, ?, ?, ?, 'pending', ?, ?)
    ");

    $approval_chain_json = json_encode($approval_chain);
    error_log("Inserting with current_approver_id=$current_approver_id, approval_chain=$approval_chain_json");

    $result = $stmt->execute([
        $task_id,
        $user_id,
        $new_deadline,
        $reason,
        $current_approver_id,
        $approval_chain_json
    ]);

    if (!$result) {
        error_log("❌ INSERT failed!");
        error_log("Error info: " . json_encode($stmt->errorInfo()));
        throw new Exception('خطا در ثبت درخواست');
    }

    $request_id = $db->lastInsertId();
    error_log("✅ Request inserted with ID: $request_id");

    // ===== بروزرسانی وضعیت کار =====
    $stmt = $db->prepare("
        UPDATE tasks 
        SET has_pending_deadline_request = 1 
        WHERE id = ?
    ");
    $stmt->execute([$task_id]);

    // ===== ارسال نوتیفیکیشن به current_approver =====
    $notification = new Notification($db);

    // دریافت اطلاعات کاربر درخواست‌کننده
    $stmt = $db->prepare("
        SELECT CONCAT(first_name, ' ', last_name) as full_name 
        FROM users WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $requester = $stmt->fetch(PDO::FETCH_ASSOC);

    // دریافت اطلاعات کار
    $stmt = $db->prepare("SELECT title FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // ✅ تبدیل تاریخ به شمسی با اعداد فارسی
    $jalali_date = JalaliHelper::formatJalaliDate($new_deadline);

    $message = sprintf(
        '«%s» درخواست تمدید موعد انجام کار «%s» تا تاریخ %s را ارسال کرده است',
        $requester['full_name'],
        $task_info['title'],
        $jalali_date
    );

    // ✅ تأیید خودکار؟
    //   - کار مقطعی: سازنده و مسئول یکی باشند
    //   - کار روتین: درخواست‌دهنده خودِ سازندهٔ روتین یا مدیر باشد
    $auto_approve = (!$is_workflow && $task['creator_id'] == $task['assignee_id'])
                 || ($is_workflow && ($user_id == $task['creator_id'] || $requester_is_manager));

    // نوتیفیکیشن فقط وقتی واقعاً نیاز به تأیید است (نه در حالت تأیید خودکار)
    if (!$auto_approve) {
        error_log("📧 Sending notification to user $current_approver_id");

        $notification->create([
            'to_user_id' => $current_approver_id,
            'title' => 'درخواست تمدید موعد انجام کار',
            'message' => $message,
            'type' => 'warning',
            'related_type' => 'task',
            'related_id' => $task_id,
            'link' => 'http://bpm.computeryekta.com/pages/task-detail.php?id=' . $task_id,
            'sms_pattern' => 'deadline_request',
            'sms_args'    => [$requester['full_name'], $task_info['title'], $jalali_date],
        ]);
    }

    ob_end_clean();

    if ($auto_approve) {
        error_log("🔄 Auto-approving deadline request");

        $approve_stmt = $db->prepare("
            UPDATE deadline_requests 
            SET status = 'approved', updated_at = NOW() 
            WHERE id = ?
        ");
        $approve_stmt->execute([$request_id]);

        // کار روتین: original_deadline دست‌نخورده می‌ماند
        $update_task = $db->prepare("
            UPDATE tasks 
            SET deadline = ?, has_pending_deadline_request = 0, updated_at = NOW() 
            WHERE id = ?
        ");
        $update_task->execute([$new_deadline, $task_id]);

        if ($is_workflow) {
            $sync = $db->prepare("UPDATE workflow_instance_steps SET deadline = ? WHERE task_id = ?");
            $sync->execute([$new_deadline, $task_id]);
        }
        // ثبت تاریخچه
        $history_stmt = $db->prepare("
    INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
    VALUES (?, ?, ?, 'deadline_extended', ?)
");
        $history_stmt->execute([
            $task_id,
            $user_id,
            $task['creator_id'],
            json_encode([
                'old_deadline' => $task['deadline'],
                'new_deadline' => $new_deadline,
                'reason' => $reason
            ], JSON_UNESCAPED_UNICODE)
        ]);
        echo json_encode([
            'success' => true,
            'message' => 'موعد انجام با موفقیت تغییر یافت (تأیید خودکار)',
            'auto_approved' => true,
            'new_deadline' => $new_deadline
        ]);
        exit;
    }

    error_log("✅ Request completed successfully");

    echo json_encode([
        'success' => true,
        'message' => 'درخواست مهلت بیشتر با موفقیت ارسال شد',
        'request_id' => $request_id,
        'approval_chain' => $approval_chain,
        'current_approver' => $current_approver_id
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(isset($http_code) ? $http_code : 500);
    error_log("❌ Deadline request error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

restore_error_handler();
?>