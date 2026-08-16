<?php
// api/tasks/review-termination.php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
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

    if (empty($data['request_id']) || empty($data['action'])) {
        echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است']);
        exit;
    }

    $request_id = intval($data['request_id']);
    $action     = $data['action']; // 'approve' | 'reject'

    if (!in_array($action, ['approve', 'reject'])) {
        echo json_encode(['success' => false, 'message' => 'عملیات نامعتبر است']);
        exit;
    }

    if ($action === 'reject' && empty(trim($data['rejection_reason'] ?? ''))) {
        echo json_encode(['success' => false, 'message' => 'لطفاً دلیل رد را وارد کنید']);
        exit;
    }

    $database = new Database();
    $db       = $database->getConnection();

    // ── 1. دریافت درخواست ───────────────────────────────
    $stmt = $db->prepare("
        SELECT tr.*,
               t.title,
               t.status AS task_status,
               CONCAT(ru.first_name,' ',ru.last_name) AS requester_name,
               CONCAT(rv.first_name,' ',rv.last_name) AS reviewer_name
        FROM task_termination_requests tr
        JOIN tasks t  ON tr.task_id      = t.id
        JOIN users ru ON tr.requester_id = ru.id
        JOIN users rv ON tr.reviewer_id  = rv.id
        WHERE tr.id = ? AND tr.status = 'pending'
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد یا قبلاً بررسی شده است']);
        exit;
    }

    // ── 2. بررسی دسترسی: فقط تعریف‌کننده ───────────────
    if ($request['reviewer_id'] != $user_id) {
        error_log("review-termination denied (not reviewer) | user_id={$user_id} | request_id={$request_id} | task_id={$request['task_id']} | reviewer_id={$request['reviewer_id']}");
        echo json_encode(['success' => false, 'message' => 'فقط تعریف‌کننده کار می‌تواند بررسی کند']);
        exit;
    }

    $db->beginTransaction();

    if ($action === 'approve') {
        // ── تأیید: وضعیت کار → completed ─────────────────
        $stmt = $db->prepare("UPDATE tasks SET status = 'completed' WHERE id = ?");
        $stmt->execute([$request['task_id']]);

        $stmt = $db->prepare("
            UPDATE task_termination_requests
            SET status = 'approved', reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$request_id]);

        // تاریخچه
        $stmt = $db->prepare("
            INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
            VALUES (?, ?, ?, 'approved', ?)
        ");
        $stmt->execute([
            $request['task_id'],
            $user_id,
            $request['requester_id'],
            'درخواست اتمام کار تأیید شد'
        ]);

        $db->commit();

        // پیامک به assignee
        $sms = new SMS($db);
        $sms->sendFromTemplate(
            $request['requester_id'],
            'termination_approved_to_assignee',
            [
                'title'        => $request['title'],
                'creator_name' => $request['reviewer_name']
            ]
        );

        echo json_encode([
            'success' => true,
            'message' => 'درخواست اتمام کار تأیید شد و کار به وضعیت تکمیل تغییر یافت'
        ]);

    } else {
        // ── رد: وضعیت کار → وضعیت قبلی ──────────────────
        $previousStatus = $request['previous_task_status'];
        $rejectionReason = trim($data['rejection_reason']);

        $stmt = $db->prepare("UPDATE tasks SET status = ? WHERE id = ?");
        $stmt->execute([$previousStatus, $request['task_id']]);

        $stmt = $db->prepare("
            UPDATE task_termination_requests
            SET status = 'rejected', rejection_reason = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$rejectionReason, $request_id]);

        // تاریخچه
        $stmt = $db->prepare("
            INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
            VALUES (?, ?, ?, 'rejected', ?)
        ");
        $stmt->execute([
            $request['task_id'],
            $user_id,
            $request['requester_id'],
            'درخواست اتمام کار رد شد. دلیل: ' . $rejectionReason
        ]);

        $db->commit();

        // پیامک به assignee
        $sms = new SMS($db);
        $sms->sendFromTemplate(
            $request['requester_id'],
            'termination_rejected_to_assignee',
            [
                'title'        => $request['title'],
                'creator_name' => $request['reviewer_name'],
                'reason'       => $rejectionReason
            ]
        );

        echo json_encode([
            'success' => true,
            'message' => 'درخواست رد شد و کار به وضعیت قبلی بازگشت'
        ]);
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("review-termination error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
}
?>
