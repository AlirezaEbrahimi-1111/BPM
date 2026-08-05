<?php
// api/tasks/request-termination.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/TaskManager.php';
require_once '../../includes/SMS.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'روش غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $data    = json_decode(file_get_contents('php://input'), true);

    if (empty($data['task_id']) || empty($data['reason'])) {
        echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است']);
        exit;
    }

    $task_id = intval($data['task_id']);
    $reason  = trim($data['reason']);

    $database = new Database();
    $db       = $database->getConnection();

    // ── 1. دریافت اطلاعات کار ───────────────────────────
    $stmt = $db->prepare("
        SELECT t.*, 
               CONCAT(u.first_name,' ',u.last_name) AS creator_name,
               u.phone AS creator_phone
        FROM tasks t
        LEFT JOIN users u ON t.creator_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'کار یافت نشد']);
        exit;
    }

    // ── 2. بررسی دسترسی ─────────────────────────────────
    if ($task['assignee_id'] != $user_id) {
        error_log("request-termination denied (not assignee) | user_id={$user_id} | task_id={$task_id} | assignee_id={$task['assignee_id']}");
        echo json_encode(['success' => false, 'message' => 'فقط مسئول انجام می‌تواند درخواست دهد']);
        exit;
    }

    if ($task['task_type'] !== 'continuous') {
        error_log("request-termination denied (wrong task_type) | user_id={$user_id} | task_id={$task_id} | task_type={$task['task_type']}");
        echo json_encode(['success' => false, 'message' => 'این قابلیت فقط برای کارهای دوره‌ای است']);
        exit;
    }

    if (!in_array($task['status'], ['not_started', 'in_progress'])) {
        error_log("request-termination denied (wrong task status) | user_id={$user_id} | task_id={$task_id} | status={$task['status']}");
        echo json_encode(['success' => false, 'message' => 'وضعیت کار اجازه ارسال درخواست را نمی‌دهد']);
        exit;
    }

    // ── 3. بررسی: کاربر مستقیماً از تعریف‌کننده دریافت کرده؟
    $stmt = $db->prepare("
        SELECT COUNT(*) AS cnt
        FROM task_history
        WHERE task_id   = ?
          AND action    = 'delegated'
          AND to_user_id = ?
    ");
    $stmt->execute([$task_id, $user_id]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0) {
        error_log("request-termination denied (received via delegation) | user_id={$user_id} | task_id={$task_id}");
        echo json_encode([
            'success' => false,
            'message' => 'شما این کار را از طریق ارجاع دریافت کرده‌اید و امکان درخواست اتمام ندارید'
        ]);
        exit;
    }

    // ── 4. بررسی درخواست تکراری ─────────────────────────
    $stmt = $db->prepare("
        SELECT id FROM task_termination_requests
        WHERE task_id = ? AND requester_id = ? AND status = 'pending'
    ");
    $stmt->execute([$task_id, $user_id]);
    if ($stmt->fetch()) {
        error_log("request-termination denied (duplicate pending request) | user_id={$user_id} | task_id={$task_id}");
        echo json_encode(['success' => false, 'message' => 'یک درخواست در انتظار بررسی وجود دارد']);
        exit;
    }

    // ── 5. ثبت درخواست ──────────────────────────────────
    $stmt = $db->prepare("
        INSERT INTO task_termination_requests
            (task_id, requester_id, reviewer_id, reason, status, previous_task_status)
        VALUES (?, ?, ?, ?, 'pending', ?)
    ");
    $stmt->execute([
        $task_id,
        $user_id,
        $task['creator_id'],
        $reason,
        $task['status']
    ]);

    // ── 6. تغییر وضعیت کار ──────────────────────────────
    $stmt = $db->prepare("UPDATE tasks SET status = 'termination_requested' WHERE id = ?");
    $stmt->execute([$task_id]);

    // ── 7. ثبت در تاریخچه ───────────────────────────────
    $stmt = $db->prepare("
        INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
        VALUES (?, ?, ?, 'termination_requested', ?)
    ");
    $stmt->execute([
        $task_id,
        $user_id,
        $task['creator_id'],
        'درخواست اتمام کار: ' . $reason
    ]);

    // ── 8. دریافت نام درخواست‌دهنده ─────────────────────
    $stmt = $db->prepare("SELECT CONCAT(first_name,' ',last_name) AS full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $requester = $stmt->fetch(PDO::FETCH_ASSOC);
    $requesterName = $requester['full_name'] ?? 'کاربر';

    // ── 9. ارسال پیامک به تعریف‌کننده ───────────────────
    $sms = new SMS($db);
    $sms->sendFromTemplate(
        $task['creator_id'],
        'termination_request_to_creator',
        [
            'title'          => $task['title'],
            'requester_name' => $requesterName
        ]
    );

    echo json_encode([
        'success' => true,
        'message' => 'درخواست اتمام کار با موفقیت ارسال شد'
    ]);

} catch (Exception $e) {
    error_log("request-termination error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
}
?>
