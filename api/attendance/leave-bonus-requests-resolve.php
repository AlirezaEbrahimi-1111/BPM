<?php
/**
 * API: تصمیم سرپرست دربارهٔ یک درخواست سهمیهٔ تشویقی — اعطا یا رد.
 * مقدار اعطا همیشه دقیقا همون چیزیه که کارمند درخواست داده (requested_minutes) —
 * سرپرست دیگه مقدار رو دستی وارد نمی‌کنه، فقط تأیید/رد می‌کنه.
 * POST { request_id, action: 'grant'|'decline', note? }
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/leave-balance-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $requestId = (int) ($input['request_id'] ?? 0);
    $action    = $input['action'] ?? '';
    $note      = trim($input['note'] ?? '');

    if (!$requestId || !in_array($action, ['grant', 'decline'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'پارامترهای نامعتبر']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $me = loadUserForPermissions($db, $user_id);
    if (!$me || $me['role'] !== 'supervisor') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM leave_bonus_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$req || (int) $req['organization_id'] !== (int) $me['organization_id']) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد']);
        exit;
    }
    if ($req['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'این درخواست قبلا تعیین‌تکلیف شده']);
        exit;
    }

    $targetUserId = (int) $req['user_id'];
    $amount = (int) $req['requested_minutes'];

    if ($action === 'grant') {
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'این درخواست مقدار مشخصی نداره؛ فقط می‌توانید ردش کنید']);
            exit;
        }
        ensureMonthlyLeaveAccrual($db, $targetUserId);
        $stmt = $db->prepare("
            INSERT INTO leave_balance_transactions (user_id, type, amount, granted_by, note)
            VALUES (?, 'bonus_grant', ?, ?, ?)
        ");
        $stmt->execute([$targetUserId, $amount, $user_id, ($note !== '' ? $note : 'سهمیهٔ تشویقی')]);

        $stmt = $db->prepare("
            UPDATE leave_bonus_requests
            SET status = 'granted', resolved_by = ?, resolved_amount = ?, resolve_note = ?, resolved_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$user_id, $amount, ($note !== '' ? $note : null), $requestId]);

        $balanceMinutes = getLeaveBalance($db, $targetUserId);
        $notifMessage = 'درخواست سهمیهٔ تشویقی شما پذیرفته شد — ' . formatMinutesHM($amount) . ' ساعت به موجودی شما اضافه شد';
    } else {
        $stmt = $db->prepare("
            UPDATE leave_bonus_requests
            SET status = 'declined', resolved_by = ?, resolve_note = ?, resolved_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$user_id, ($note !== '' ? $note : null), $requestId]);

        $notifMessage = 'درخواست سهمیهٔ تشویقی شما رد شد';
        if ($note !== '') {
            $notifMessage .= ' — ' . $note;
        }
    }

    $notification = new Notification($db);
    $notification->create([
        'to_user_id'   => $targetUserId,
        'title'        => $action === 'grant' ? 'سهمیهٔ تشویقی پذیرفته شد' : 'درخواست سهمیهٔ تشویقی رد شد',
        'message'      => $notifMessage,
        'type'         => $action === 'grant' ? 'success' : 'warning',
        'link'         => '/attendance_system/pages/requests.php',
        'related_type' => 'leave_bonus_request',
        'related_id'   => $requestId,
        'sms_pattern'  => 'general',
    ]);

    echo json_encode(['success' => true, 'message' => 'ثبت شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log('leave-bonus-requests-resolve error: ' . $e->getMessage());
}
