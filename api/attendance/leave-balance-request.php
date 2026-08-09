<?php
/**
 * API: درخواستِ سهمیهٔ تشویقیِ مرخصی/پاس از سرپرست — یک ردیفِ ماندگار در
 * leave_bonus_requests ثبت می‌شه (تا با فراموش‌شدنِ نوتیفیکیشن، درخواست
 * گم نشه) + یک نوتیفیکیشنِ آنی هم برایِ اطلاع‌رسانیِ سریع ارسال می‌شه.
 * خودِ اعطا از طریقِ فهرستِ درخواست‌ها یا دکمهٔ موجود در pages/users.php
 * (leave-balance-grant.php) انجام می‌شه
 * POST { requested_minutes, note? }
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/leave-balance-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $note = trim($input['note'] ?? '');
    $requestedMinutes = isset($input['requested_minutes']) ? (int) $input['requested_minutes'] : 0;

    if ($requestedMinutes <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'میزانِ سهمیهٔ درخواستی الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id, first_name, last_name, manager_id, organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$me) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit;
    }
    $myName = trim($me['first_name'] . ' ' . $me['last_name']);

    // مقصدِ درخواست: همیشه سرپرست(هایِ) سازمان — نه مدیرِ بخش
    $stmt = $db->prepare("SELECT id FROM users WHERE organization_id = ? AND role = 'supervisor' AND is_active = 1");
    $stmt->execute([$me['organization_id']]);
    $recipients = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (empty($recipients)) {
        echo json_encode(['success' => false, 'message' => 'مدیر/سرپرستی برایِ ارسالِ درخواست یافت نشد']);
        exit;
    }

    // اگر همین کاربر یک درخواستِ حل‌نشده از قبل داره، دوباره ردیفِ جدید نساز
    $stmt = $db->prepare("SELECT id FROM leave_bonus_requests WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    if ($stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'شما همین الان هم یک درخواستِ در‌انتظار دارید']);
        exit;
    }

    $balanceMinutes = getLeaveBalance($db, $user_id);
    $message = $myName . ' درخواستِ ' . formatMinutesHM($requestedMinutes) . ' ساعت سهمیهٔ تشویقیِ مرخصی/پاس دارد (موجودیِ فعلی: ' . formatMinutesHM($balanceMinutes) . ')';
    if ($note !== '') {
        $message .= ' — ' . $note;
    }

    $stmt = $db->prepare("
        INSERT INTO leave_bonus_requests (user_id, organization_id, note, requested_minutes, balance_at_request)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $me['organization_id'], ($note !== '' ? $note : null), $requestedMinutes, $balanceMinutes]);
    $requestId = (int) $db->lastInsertId();

    $notification = new Notification($db);
    foreach ($recipients as $toUserId) {
        $notification->create([
            'to_user_id'   => $toUserId,
            'title'        => 'درخواستِ سهمیهٔ تشویقی',
            'message'      => $message,
            'type'         => 'info',
            'link'         => '/pages/users.php',
            'related_type' => 'leave_bonus_request',
            'related_id'   => $requestId,
            'sms_pattern'  => 'general',
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'درخواستِ شما برایِ سرپرست ارسال شد و تا حل‌شدن در فهرستِ او باقی می‌ماند']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log('leave-balance-request error: ' . $e->getMessage());
}
