<?php
/**
 * api/workflows/review-stop.php
 * بررسی درخواست توقف روتین — فقط تأییدکنندهٔ همان درخواست.
 * POST { request_id, decision: 'approve'|'reject', rejection_reason? }
 *
 * تأیید → روتین متوقف می‌شود (wsr_stopInstance → WorkflowManager::cancelWorkflow)
 * رد     → روتین ادامه پیدا می‌کند؛ دلیل رد الزامی است.
 */

header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/workflow-stop-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد مجاز نیست'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $user_id = requireAuth();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-access.php';
    requirePlanFeature($user_id, 'routine');

    $input      = json_decode(file_get_contents('php://input'), true);
    $request_id = (int) ($input['request_id'] ?? 0);
    $decision   = $input['decision'] ?? '';
    $rejection  = trim($input['rejection_reason'] ?? '');

    if (!$request_id || !in_array($decision, ['approve', 'reject'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر است'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($decision === 'reject' && $rejection === '') {
        echo json_encode(['success' => false, 'message' => 'لطفا دلیل رد درخواست را بنویسید'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $s = $db->prepare("SELECT * FROM workflow_stop_requests WHERE id = ? AND status = 'pending'");
    $s->execute([$request_id]);
    $request = $s->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد یا قبلا پاسخ داده شده'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ((int) $request['approver_id'] !== $user_id) {
        error_log("workflow review-stop denied (not approver) | user_id={$user_id} | request_id={$request_id} | approver_id={$request['approver_id']}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجاز به بررسی این درخواست نیستید'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $instance = wsr_loadInstance($db, (int) $request['instance_id']);
    if (!$instance || !in_array($instance['status'], WSR_OPEN_INSTANCE_STATUSES, true)) {
        // روتین در این فاصله تمام یا لغو شده — درخواست بی‌موضوع است
        $db->prepare("UPDATE workflow_stop_requests SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
            ->execute(['روتین پیش از بررسی به پایان رسیده بود', $user_id, $request_id]);
        echo json_encode(['success' => false, 'message' => 'این روتین دیگر در حال اجرا نیست'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($decision === 'approve') {
        $res = wsr_stopInstance($db, $instance, $request, $user_id);
        if (empty($res['success'])) {
            echo json_encode(['success' => false, 'message' => $res['message'] ?? 'خطا در توقف روتین'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $db->prepare("UPDATE workflow_stop_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
            ->execute([$user_id, $request_id]);
        echo json_encode(['success' => true, 'message' => 'درخواست تأیید شد و روتین متوقف شد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // رد
    $db->beginTransaction();
    $db->prepare("UPDATE workflow_stop_requests SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
        ->execute([$rejection, $user_id, $request_id]);
    $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
                  VALUES (?, ?, ?, 'workflow_stop_rejected', ?)")
        ->execute([
            $request['task_id'], $user_id, $request['requested_by'],
            'درخواست توقف روتین «' . $instance['title'] . '» رد شد و روتین ادامه پیدا می‌کند. دلیل رد: ' . $rejection,
        ]);
    $db->commit();

    try {
        $notification = new Notification($db);
        $notification->create([
            'to_user_id'   => (int) $request['requested_by'],
            'title'        => 'درخواست توقف روتین رد شد: ' . $instance['title'],
            'message'      => "درخواست توقف روتین «{$instance['title']}» رد شد. دلیل: {$rejection}",
            'type'         => 'info',
            'related_type' => 'task',
            'related_id'   => (int) $request['task_id'],
            'link'         => '/pages/task-detail.php?id=' . (int) $request['task_id'],
        ]);
    } catch (Exception $e) {
        error_log('workflow review-stop notif error: ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'درخواست رد شد و روتین ادامه پیدا می‌کند'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('workflow review-stop error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
