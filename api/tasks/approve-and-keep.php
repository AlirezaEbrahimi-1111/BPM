<?php
// api/tasks/approve-and-keep.php
// تأیید کار + نگهداری در لیست تعریف‌کننده
// وضعیت → in_progress، assignee → creator

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
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
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این کار در وضعیت انتظار تأیید نیست']);
        exit;
    }

    // فقط تعریف‌کننده کار مجاز است
    if ($task['creator_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط تعریف‌کننده کار می‌تواند این عمل را انجام دهد']);
        exit;
    }

    // نام کاربر جاری
    $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $currentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentUserName = $currentUserRow['full_name'] ?? 'کاربر ' . $user_id;

    $notes = $input['notes'] ?? '';

    // ── ثبت تاریخچه ──────────────────────────────────────────────────────────
    $historyNote = 'کار تأیید شد و در لیست ' . $currentUserName . ' نگه‌داشته شد';
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

    // ── بروزرسانی کار: status → in_progress، assignee → creator ─────────────
    $stmt = $db->prepare("
        UPDATE tasks
        SET status             = 'in_progress',
            assignee_id        = ?,
            is_pending_approval = FALSE,
            updated_at         = NOW()
        WHERE id = ?
    ");
    $result = $stmt->execute([$user_id, $input['task_id']]);

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
        'message' => 'کار تأیید شد و در لیست شما نگه‌داشته شد'
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    error_log("Approve-and-keep error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور: ' . $e->getMessage()
    ]);
}

restore_error_handler();