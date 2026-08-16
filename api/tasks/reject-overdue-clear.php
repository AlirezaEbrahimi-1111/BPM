<?php
// ==================================================
// api/tasks/reject-overdue-clear.php
// رد درخواست رفع دوره‌های معوقه
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';

try {
    $user_id = requireAuth();
    $data    = json_decode(file_get_contents('php://input'), true);
    $request_id = (int) ($data['request_id'] ?? 0);
    $reason     = trim($data['rejection_reason'] ?? '');

    if (!$request_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسهٔ درخواست الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        SELECT r.*, t.title, t.organization_id
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

    // 🔒 خط قرمز: اختیار «مدیر» فقط داخل همان سازمانِ کار معتبر است
    $roleStmt = $db->prepare("SELECT role, organization_id FROM users WHERE id = ?");
    $roleStmt->execute([$user_id]);
    $me = $roleStmt->fetch(PDO::FETCH_ASSOC);
    $isManager = $me
        && in_array($me['role'], ['management', 'supervisor', 'admin'], true)
        && (int)$me['organization_id'] === (int)$req['organization_id'];
    if ((int)$req['current_approver_id'] !== $user_id && !$isManager) {
        error_log("reject-overdue-clear denied (cross-org/not-approver, isManager check failed) | user_id={$user_id} | request_id={$request_id} | task_id={$req['task_id']} | current_approver_id={$req['current_approver_id']} | isManager=" . ($isManager ? '1' : '0'));
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجاز به رد این درخواست نیستید'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $task_id = (int) $req['task_id'];

    $db->beginTransaction();

    $db->prepare("UPDATE overdue_clear_requests
                  SET status = 'rejected', rejection_reason = ?, current_approver_id = ?, updated_at = NOW()
                  WHERE id = ?")
       ->execute([$reason, $user_id, $request_id]);

    // آزاد کردن قفل تا کاربر بتواند بعداً دوباره درخواست دهد
    $db->prepare("UPDATE tasks SET has_pending_overdue_request = 0, updated_at = NOW() WHERE id = ?")
       ->execute([$task_id]);

    try {
        $notification = new Notification($db);
        $msg = "درخواست رفع دوره‌های معوقهٔ کار «{$req['title']}» رد شد.";
        if ($reason !== '') $msg .= "\nدلیل: " . $reason;
        $notification->create([
            'to_user_id'   => $req['requested_by'],
            'title'        => 'درخواست رفع معوقه رد شد: ' . $req['title'],
            'message'      => $msg,
            'type'         => 'error',
            'related_type' => 'task',
            'related_id'   => $task_id,
            'link'         => '/pages/task-detail.php?id=' . $task_id,
            'sms_pattern'  => 'overdue_clear_rejected',
            'sms_args'     => [$req['title']],
        ]);
    } catch (Exception $e) {
        error_log('reject-overdue-clear notif error: ' . $e->getMessage());
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'درخواست رد شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('reject-overdue-clear error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
