<?php
// api/tasks/approve-and-delegate.php
// تأیید کار + ارجاع به شخص جدید (بدون بازگشت به زنجیره قبلی)

ob_start();

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
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

    if (empty($input['to_user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'کاربر مقصد الزامی است']);
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

    if ($task['status'] !== 'pending_approval' || !$task['is_pending_approval']) {
        error_log("approve-and-delegate denied (wrong task status) | user_id={$user_id} | task_id={$input['task_id']} | status={$task['status']} | is_pending_approval=" . ($task['is_pending_approval'] ? '1' : '0'));
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این کار در وضعیت انتظار تأیید نیست']);
        exit;
    }

    if ($task['assignee_id'] != $user_id) {
        error_log("approve-and-delegate denied (not assignee) | user_id={$user_id} | task_id={$input['task_id']} | assignee_id={$task['assignee_id']}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجاز به تأیید این کار نیستید']);
        exit;
    }

    // بررسی کاربر مقصد
    // 🔒 خط قرمز: کاربر مقصد باید از همان سازمانِ کار باشد
    $stmt = $db->prepare("SELECT id, organization_id, CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$input['to_user_id']]);
    $toUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$toUser || (int)$toUser['organization_id'] !== (int)$task['organization_id']) {
        error_log("approve-and-delegate denied (cross-org target user) | user_id={$user_id} | task_id={$input['task_id']} | to_user_id={$input['to_user_id']} | to_user_org=" . ($toUser['organization_id'] ?? 'null') . " | task_org={$task['organization_id']}");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'کاربر مقصد یافت نشد']);
        exit;
    }

    if ($input['to_user_id'] == $user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نمی‌توانید کار را به خودتان ارجاع دهید']);
        exit;
    }

    // نام کاربر جاری
    $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $currentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentUserName = $currentUserRow['full_name'] ?? 'کاربر ' . $user_id;

    $toUserName = $toUser['full_name'] ?? 'کاربر ' . $input['to_user_id'];

    $approveNotes = $input['approve_notes'] ?? '';
    $delegateNotes = $input['delegate_notes'] ?? '';

    // ===== عملیات =====

    // ✅ فقط یک لاگ واحد در تاریخچه
    $historyNote = 'کار تأیید و به ' . $toUserName . ' ارجاع شد';
    if (!empty($approveNotes)) {
        $historyNote .= '. توضیحات: ' . $approveNotes;
    }

    $stmt = $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes) VALUES (?, ?, ?, 'delegated', ?)");
    $stmt->execute([
        $input['task_id'],
        $user_id,
        $input['to_user_id'],
        $historyNote
    ]);

    // بروزرسانی کار
    $delegationNoteText = $currentUserName . ': ' . ($delegateNotes ?: 'ارجاع پس از تأیید');
    $existingNotes = $task['delegation_notes'] ?: '';
    $newDelegationNotes = $existingNotes
        ? $existingNotes . "\n---\n" . $delegationNoteText
        : $delegationNoteText;

    // status مستقیماً 'not_started' ست می‌شه (نه 'delegated') — چون هرجایِ
    // دیگه‌یِ سیستم (دکمه‌ی شروع، تیکِ چک‌لیست، فیلترها) از قبل این دو رو
    // یکسان می‌دونست؛ خودِ رخدادِ ارجاع در task_history (action='delegated')
    // و delegation_notes ثبت می‌مونه، پس چیزی گم نمی‌شه
    $sql = "UPDATE tasks SET
                assignee_id = ?,
                status = 'not_started',
                is_pending_approval = FALSE,
                delegation_notes = ?,
                updated_at = NOW()
            WHERE id = ?";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        $input['to_user_id'],
        $newDelegationNotes,
        $input['task_id']
    ]);

    if (!$result) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در بروزرسانی کار']);
        exit;
    }

    // نوتیفیکیشن
    try {
        $notification->create([
            'to_user_id' => $input['to_user_id'],
            'title' => 'کار جدید ارجاع شده: ' . ($task['title'] ?? 'نامشخص'),
            'message' => 'کار «' . ($task['title'] ?? 'نامشخص') . '» توسط ' . $currentUserName . ' تأیید و به شما ارجاع داده شد.',
            'type' => 'info',
            'link' => '/pages/task-detail.php?id=' . $input['task_id'],
            'related_type' => 'task',
            'related_id' => $input['task_id'],
            'is_read' => 0,
            'sms_pattern' => 'task_approved',
            'sms_args' => [$task['title'] ?? 'نامشخص', $currentUserName],
            // 🔒 چند خط بالاتر assignee_id همین الان به to_user_id تغییر کرد؛
            // چکِ self-notificationِ پیش‌فرض این رو با ارجاع‌دادن به خودِ
            // creator اشتباه می‌گرفت و بی‌صدا نوتیف رو حذف می‌کرد
            'skip_self_check' => true
        ]);
    } catch (Exception $notifError) {
        error_log("Notification error in approve-and-delegate: " . $notifError->getMessage());
    }

    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'کار تأیید و به ' . $toUserName . ' ارجاع داده شد'
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    error_log("Approve-and-delegate error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور: ' . $e->getMessage()
    ]);
}

restore_error_handler();
?>