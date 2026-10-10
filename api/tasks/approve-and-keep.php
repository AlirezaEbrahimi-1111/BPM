<?php
// api/tasks/approve-and-keep.php
// تأیید کار + نگهداری در لیست تعریف‌کننده
// وضعیت → in_progress، assignee → creator

ob_start();

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: [$errno] $errstr in $errfile:$errline");
    return true;
});

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

    if (empty($input['task_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);
    $notification = new Notification($db);

    $task = $taskManager->getTask($input['task_id']);

    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کار یافت نشد']);
        exit;
    }

    // فقط وقتی کار در انتظار تأیید است
    if ($task['status'] !== 'pending_approval' || !$task['is_pending_approval']) {
        error_log("approve-and-keep denied (wrong task status) | user_id={$user_id} | task_id={$input['task_id']} | status={$task['status']} | is_pending_approval=" . ($task['is_pending_approval'] ? '1' : '0'));
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این کار در وضعیت انتظار تأیید نیست']);
        exit;
    }

    // 🔒 کارهای روتین (workflow) منطقِ تأیید کاملاً جداگانه‌ای دارند
    // (WorkflowManager::resolveStepDecision) — از این مسیر عبور نمی‌کنند
    if (!empty($task['is_workflow_task'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'کارهای روتین از این طریق تأیید نمی‌شوند']);
        exit;
    }

    // 🔒 کارِ دوره‌ای «تأیید و نگهداری» ندارد (درخواست صریح، ۱۴۰۵/۰۷/۱۸) — دکمه‌اش در
    // صفحهٔ کار هم برای کارِ دوره‌ای نمایش داده نمی‌شود. دوره را باید با «تأیید» معمولی
    // تأیید کرد تا کار پیشِ انجام‌دهنده برگردد.
    if ($task['task_type'] === 'continuous') {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'برای کار دوره‌ای «تأیید و نگهداری» وجود ندارد. لطفا از دکمهٔ «تأیید» استفاده کنید.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 🆕 قبلاً فقط تعریف‌کننده (نفرِ اولِ زنجیره) اجازه داشت. حالا هر کسی که
    // الان کار منتظرِ تأییدِ اوست (همون کسی که دکمه‌های تأیید/رد را می‌بیند —
    // دقیقا همون چکی که TaskManager::approveOrRejectTask انجام می‌دهد) هم
    // می‌تواند «تأیید و نگهداری» بزند؛ یعنی اگر نفرِ ۲ (واسطه) منتظرِ تأییدِ
    // کارِ نفرِ ۳ باشد، می‌تواند به‌جایِ ارسالِ خودکار برایِ نفرِ ۱، کار را
    // تأیید کند و نزدِ خودش نگه دارد تا هر عملیاتی (ازجمله ارجاعِ دوباره)
    // روی آن انجام دهد؛ بعداً که «تکمیل» بزند، طبقِ همان زنجیره درست می‌رود
    // پیشِ نفرِ ۱.
    if ((int) $task['assignee_id'] !== (int) $user_id) {
        error_log("approve-and-keep denied (not current approver) | user_id={$user_id} | task_id={$input['task_id']} | assignee_id={$task['assignee_id']}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجاز به تأیید این کار نیستید']);
        exit;
    }

    // نام کاربر جاری
    $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $currentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentUserName = $currentUserRow['full_name'] ?? 'کاربر ' . $user_id;

    $notes = $input['notes'] ?? '';

    // 🆕 کارِ دوره‌ای (continuous) ممکن است چند دورهٔ منتظرِ تأیید روی هم
    // جمع شده باشد (pending_approval_count) — دقیقا همون شمارشی که
    // TaskManager::approveOrRejectTask هم استفاده می‌کند. «تأیید و نگهداری»
    // فقط همون یک دوره‌ی جاری را تأیید می‌کند؛ اگر دوره‌ی دیگری هم در
    // صف مانده باشد، کار هنوز منتظرِ تأیید (نزدِ همین شخص) می‌ماند و
    // «نگهداری» (بازگشت به حالتِ عادیِ قابل‌کار) فقط وقتی اتفاق می‌افتد که
    // آخرین دوره هم تأیید شده باشد.
    $isContinuous = ($task['task_type'] === 'continuous');
    $newPendingCount = $isContinuous ? max(0, ((int) ($task['pending_approval_count'] ?? 1)) - 1) : 0;
    $stillPending = $isContinuous && $newPendingCount > 0;

    // ── ثبت تاریخچه ──────────────────────────────────────────────────────────
    $historyNote = $stillPending
        ? "یک دوره تأیید شد و نزد {$currentUserName} باقی ماند ({$newPendingCount} دورهٔ دیگر در انتظار تأیید)"
        : "کار تأیید شد و در لیست {$currentUserName} نگه‌داشته شد";
    if (!empty($notes)) {
        $historyNote .= '. توضیحات: ' . $notes;
    }

    $stmt = $db->prepare("
        INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
        VALUES (?, ?, ?, 'approved', ?)
    ");
    $stmt->execute([
        $input['task_id'],
        $user_id,
        $user_id,
        $historyNote
    ]);

    if ($stillPending) {
        // هنوز دوره‌ی دیگری در صف است — فقط شمارش کم می‌شود، چیزِ دیگری عوض نمی‌شود
        $stmt = $db->prepare("
            UPDATE tasks SET pending_approval_count = ?, last_approved_date = ?, updated_at = NOW() WHERE id = ?
        ");
        $result = $stmt->execute([$newPendingCount, date('Y-m-d'), $input['task_id']]);
    } else {
        // ── بروزرسانی کار: status → in_progress، assignee → همین کاربر ──
        // (پاک‌کردنِ موعد فقط برای کار مقطعی غیرروتین معنی داره — کارِ دوره‌ای
        // از end_date/دوره استفاده می‌کند، نه due_date)
        // 🔒 موعد فقط وقتی پاک می‌شود که نگه‌دارنده خودِ تعریف‌کننده باشد (کار به
        // صاحبش برگشته). واسطه‌ای که کار را نزدِ خودش نگه می‌دارد هنوز به نفرِ
        // قبلیِ زنجیره بدهکار است، پس موعدی که برایش گذاشته شده باید بماند.
        $keeperIsCreator = ((int) $task['creator_id'] === (int) $user_id);
        $clearDueDate = (!$isContinuous && empty($task['is_workflow_task']) && $keeperIsCreator);
        $sql = "
            UPDATE tasks
            SET status             = 'in_progress',
                assignee_id        = ?,
                is_pending_approval = FALSE,
                pending_approval_count = 0"
            . ($isContinuous ? ", last_approved_date = ?" : "")
            . ($clearDueDate ? ", due_date = NULL, deadline = NULL, original_deadline = NULL" : "") . "
                , updated_at         = NOW()
            WHERE id = ?
        ";
        $stmt = $db->prepare($sql);
        $params = [$user_id];
        if ($isContinuous) $params[] = date('Y-m-d');
        $params[] = $input['task_id'];
        $result = $stmt->execute($params);
    }

    if (!$result) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در بروزرسانی کار']);
        exit;
    }

    // ── نوتیفیکیشن به انجام‌دهنده قبلی (اگر متفاوت از creator بود) ──────────
    $previousAssigneeId = $task['assignee_id'];
    if ($previousAssigneeId && $previousAssigneeId != $user_id) {
        try {
            $notification->create([
                'to_user_id'   => $previousAssigneeId,
                'title'        => 'کار شما تأیید شد: ' . ($task['title'] ?? 'نامشخص'),
                'message'      => 'کار «' . ($task['title'] ?? 'نامشخص') . '» توسط ' . $currentUserName . ' تأیید و ادامه آن در دست ایشان است.',
                'type'         => 'info',
                'link'         => '/pages/task-detail.php?id=' . $input['task_id'],
                'related_type' => 'task',
                'related_id'   => $input['task_id'],
                'is_read'      => 0,
            ]);
        } catch (Exception $notifError) {
            error_log("Notification error in approve-and-keep: " . $notifError->getMessage());
        }
    }

    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $stillPending
            ? "یک دوره تأیید شد. {$newPendingCount} دورهٔ دیگر منتظر تأیید است"
            : 'کار تأیید شد و در لیست شما نگه‌داشته شد',
        'remaining_approvals' => $stillPending ? $newPendingCount : 0
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    error_log("Approve-and-keep error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور'
    ]);
}

restore_error_handler();