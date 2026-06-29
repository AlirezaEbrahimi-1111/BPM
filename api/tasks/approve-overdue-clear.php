<?php
// ==================================================
// api/tasks/approve-overdue-clear.php
// تأیید درخواست رفع دوره‌های معوقه توسط تعریف‌کننده/مدیر
// ==================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';

/** باقی‌ماندهٔ معوقه (هم‌خوان با complete-recurring.php) */
function oc_calc_remaining(PDO $db, array $task): int {
    if (($task['task_type'] ?? '') !== 'continuous' || empty($task['start_date'])) return 0;
    $start = new DateTime($task['start_date']); $start->setTime(0, 0, 0);
    $today = new DateTime();                     $today->setTime(0, 0, 0);
    if ($today < $start) return 0;
    $map = ['daily' => 'P1D', 'weekly' => 'P7D', 'monthly' => 'P1M'];
    $interval = new DateInterval($map[$task['period_type']] ?? 'P1D');
    $overdue = 0; $cur = clone $start;
    while ($cur < $today) { $overdue++; $cur->add($interval); }
    $stmt = $db->prepare("SELECT COUNT(*) FROM task_history WHERE task_id = ? AND action = 'completed'");
    $stmt->execute([$task['id']]);
    $completed = (int) $stmt->fetchColumn();
    $forgiven  = (int) ($task['overdue_forgiven_credit'] ?? 0);
    return max(0, $overdue - $completed - $forgiven);
}

try {
    $user_id = requireAuth();
    $data    = json_decode(file_get_contents('php://input'), true);
    $request_id = (int) ($data['request_id'] ?? 0);

    if (!$request_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسهٔ درخواست الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        SELECT r.*, t.title, t.creator_id, t.assignee_id, t.task_type, t.period_type,
               t.start_date, t.overdue_forgiven_credit, t.organization_id
        FROM overdue_clear_requests r
        JOIN tasks t ON r.task_id = t.id
        WHERE r.id = ? AND r.status = 'pending'
    ");
    $stmt->execute([$request_id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد یا قبلاً پاسخ داده شده'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // مجوز: تأییدکنندهٔ فعلی یا مدیر
    $roleStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $roleStmt->execute([$user_id]);
    $isManager = in_array($roleStmt->fetchColumn(), ['management', 'supervisor', 'admin'], true);
    if ((int)$req['current_approver_id'] !== $user_id && !$isManager) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجاز به تأیید این درخواست نیستید'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $task_id   = (int) $req['task_id'];
    $remaining = oc_calc_remaining($db, $req);   // مقدار معتبر در لحظهٔ تأیید

    $db->beginTransaction();

    // اعمال بخشش: اعتبار += باقی‌مانده
    $db->prepare("UPDATE tasks
                  SET overdue_forgiven_credit = overdue_forgiven_credit + ?,
                      has_pending_overdue_request = 0, updated_at = NOW()
                  WHERE id = ?")
       ->execute([$remaining, $task_id]);

    $db->prepare("UPDATE overdue_clear_requests
                  SET status = 'approved', forgiven_count = ?, current_approver_id = ?, updated_at = NOW()
                  WHERE id = ?")
       ->execute([$remaining, $user_id, $request_id]);

    $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
                  VALUES (?, ?, ?, 'updated', ?)")
       ->execute([$task_id, $req['requested_by'], $user_id,
           'رفع دوره‌های معوقه تأیید شد — تعداد: ' . $remaining]);

    // نوتیفیکیشن به درخواست‌دهنده
    try {
        $notification = new Notification($db);
        $notification->create([
            'to_user_id'   => $req['requested_by'],
            'title'        => 'درخواست رفع معوقه تأیید شد: ' . $req['title'],
            'message'      => "درخواست رفع دوره‌های معوقهٔ کار «{$req['title']}» تأیید شد. $remaining دوره برداشته شد.",
            'type'         => 'success',
            'related_type' => 'task',
            'related_id'   => $task_id,
            'link'         => 'http://bpm.computeryekta.com/pages/task-detail.php?id=' . $task_id,
            'sms_pattern'  => 'overdue_clear_approved',
            'sms_args'     => [$req['title'], (string)$remaining],
        ]);
    } catch (Exception $e) {
        error_log('approve-overdue-clear notif error: ' . $e->getMessage());
    }

    $db->commit();
    echo json_encode([
        'success'  => true,
        'message'  => "درخواست تأیید شد. $remaining دورهٔ معوقه برداشته شد.",
        'forgiven' => $remaining
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('approve-overdue-clear error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
