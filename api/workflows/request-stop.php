<?php
/**
 * api/workflows/request-stop.php
 * درخواست توقف روتین — از طرف کسی که مرحلهٔ جاری دستش است.
 * POST { task_id, reason }
 *
 * درخواست به تعریف‌کنندهٔ روتین می‌رود (includes/workflow-stop-helper.php).
 * اگر درخواست‌دهنده خودش تأییدکننده باشد → توقف فوری، بدون مرحلهٔ تأیید.
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

    $input   = json_decode(file_get_contents('php://input'), true);
    $task_id = (int) ($input['task_id'] ?? 0);
    $reason  = trim($input['reason'] ?? '');

    if (!$task_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($reason === '') {
        echo json_encode(['success' => false, 'message' => 'لطفا دلیل توقف روتین را بنویسید'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $s = $db->prepare("SELECT * FROM tasks WHERE id = ? AND (is_deleted = 0 OR is_deleted IS NULL)");
    $s->execute([$task_id]);
    $task = $s->fetch(PDO::FETCH_ASSOC);
    if (!$task || (int) $task['is_workflow_task'] !== 1 || empty($task['workflow_instance_id'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'این کار مرحله‌ای از یک روتین نیست'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $instance = wsr_loadInstance($db, (int) $task['workflow_instance_id']);
    $me = getUserInfo($user_id);
    // 🔒 خط قرمز: فقط داخل همان سازمان
    if (!$instance || (int) $instance['organization_id'] !== (int) $me['organization_id']) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'روتین یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!in_array($instance['status'], WSR_OPEN_INSTANCE_STATUSES, true)) {
        echo json_encode(['success' => false, 'message' => 'این روتین دیگر در حال اجرا نیست'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!wsr_isStepHolder($db, $user_id, $task)) {
        error_log("workflow request-stop denied (not step holder) | user_id={$user_id} | task_id={$task_id} | instance_id={$instance['id']}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط کسی که این مرحله دستش است می‌تواند درخواست توقف بدهد'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (wsr_pendingRequest($db, (int) $instance['id'])) {
        echo json_encode(['success' => false, 'message' => 'برای این روتین یک درخواست توقف در انتظار تأیید وجود دارد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $approver_id = wsr_resolveApprover($db, $instance);

    $db->prepare("INSERT INTO workflow_stop_requests
        (organization_id, instance_id, task_id, requested_by, approver_id, reason)
        VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$instance['organization_id'], $instance['id'], $task_id, $user_id, $approver_id, $reason]);
    $request_id = (int) $db->lastInsertId();

    // ✅ تأیید خودکار: درخواست‌دهنده خودش تأییدکننده است
    if ($approver_id === $user_id) {
        $request = ['id' => $request_id, 'task_id' => $task_id, 'requested_by' => $user_id, 'reason' => $reason];
        $res = wsr_stopInstance($db, $instance, $request, $user_id);
        if (empty($res['success'])) {
            $db->prepare("DELETE FROM workflow_stop_requests WHERE id = ?")->execute([$request_id]);
            echo json_encode(['success' => false, 'message' => $res['message'] ?? 'خطا در توقف روتین'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $db->prepare("UPDATE workflow_stop_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
            ->execute([$user_id, $request_id]);
        echo json_encode([
            'success'       => true,
            'auto_approved' => true,
            'message'       => 'روتین متوقف شد (بدون نیاز به تأیید، چون خودتان تعریف‌کنندهٔ روتین هستید)',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $requesterName = wsr_userName($db, $user_id);
    $approverName  = wsr_userName($db, $approver_id);
    $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
                  VALUES (?, ?, ?, 'workflow_stop_requested', ?)")
        ->execute([
            $task_id, $user_id, $approver_id,
            'درخواست توقف روتین «' . $instance['title'] . '» ثبت شد و برای بررسی به «' . $approverName . '» فرستاده شد. دلیل: ' . $reason,
        ]);

    try {
        $notification = new Notification($db);
        $notification->create([
            'to_user_id'   => $approver_id,
            'title'        => 'درخواست توقف روتین: ' . $instance['title'],
            'message'      => "«{$requesterName}» درخواست توقف روتین «{$instance['title']}» را داده است. لطفا بررسی کنید.",
            'type'         => 'warning',
            'related_type' => 'task',
            'related_id'   => $task_id,
            'link'         => '/pages/task-detail.php?id=' . $task_id,
        ]);
    } catch (Exception $e) {
        error_log('workflow request-stop notif error: ' . $e->getMessage());
    }

    echo json_encode([
        'success'       => true,
        'auto_approved' => false,
        'message'       => 'درخواست توقف روتین ثبت شد و برای «' . $approverName . '» فرستاده شد',
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('workflow request-stop error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
