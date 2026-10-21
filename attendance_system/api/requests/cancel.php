<?php
/**
 * API: لغو درخواست توسط مسئول (سرپرست سازمان) — ۱۴۰۵/۰۷/۳۰
 * مسیر: /attendance_system/api/requests/cancel.php   متد: POST   بدنه: {id, type, reason}
 *
 * مسئولِ سازمانِ صاحب درخواست (یا مدیر کل) می‌تواند هر درخواست را در هر مرحله — حتی بعد از
 * تأیید نهایی — با ذکر دلیل لغو کند. ردیف حذف نمی‌شود: status = 'cancelled' و چه کسی/کِی/چرا
 * ذخیره می‌شود تا در مودال تاریخچه دیده شود. سهمیهٔ مرخصی/پاس کامل برمی‌گردد (درخواست باطل
 * شده، خرج نشده). صاحب درخواست اعلان + پیامک می‌گیرد.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/leave-balance-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/attendance_system/includes/request-supervisor-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'فقط POST مجاز است']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    $auth = new Auth($db);
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) $user_id = $auth->getUserFromToken();
    if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'لطفا وارد شوید']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int) ($data['id'] ?? 0);
    $type = (string) ($data['type'] ?? '');
    $reason = trim((string) ($data['reason'] ?? ''));
    $tables = reqTables();
    if ($id <= 0 || !isset($tables[$type])) {
        echo json_encode(['success' => false, 'message' => 'اطلاعات درخواست ناقص است']);
        exit;
    }
    if (mb_strlen($reason) < 3) {
        echo json_encode(['success' => false, 'message' => 'دلیل لغو را بنویسید']);
        exit;
    }
    if (mb_strlen($reason) > 500) $reason = mb_substr($reason, 0, 500);
    $table = $tables[$type];

    $st = $db->prepare("SELECT * FROM {$table} WHERE id = ?");
    $st->execute([$id]);
    $req = $st->fetch(PDO::FETCH_ASSOC);
    if (!$req) {
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد']);
        exit;
    }
    $ownerId = (int) $req['user_id'];

    $me = reqIsOrgSupervisor($db, (int) $user_id, $ownerId);
    if (!$me) {
        // غیرمسئول: وجود درخواست را فاش نکن
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد']);
        exit;
    }
    if (($req['status'] ?? '') === 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'این درخواست قبلا لغو شده است']);
        exit;
    }

    $db->beginTransaction();
    // technical_issues ستون can_edit/can_delete ندارد
    $lockCols = $type === 'technical' ? '' : 'can_edit = 0, can_delete = 0,';
    $db->prepare("UPDATE {$table}
                  SET status = 'cancelled', {$lockCols}
                      cancelled_by = ?, cancelled_at = NOW(), cancel_reason = ?
                  WHERE id = ?")
       ->execute([(int) $user_id, $reason, $id]);

    // سهمیهٔ مرخصی/پاس برمی‌گردد (درخواست باطل شد)
    $refunded = 0;
    if ($type === 'leave' || $type === 'pass') {
        $refunded = refundLeaveRequestBalance($db, $type, $id, $ownerId, 'بازگشت سهمیه به‌دلیل لغو درخواست توسط مسئول');
    }
    $db->commit();

    $byName = trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''));
    error_log("Attendance request cancelled by supervisor | supervisor_id={$user_id} | owner_id={$ownerId} | request_id={$id} | type={$type} | code=" . ($req['request_code'] ?? '') . " | prev_status=" . ($req['status'] ?? ''));
    if ($ownerId !== (int) $user_id) {
        reqNotifyOwner($db, $ownerId, 'cancelled', $type, (string) ($req['request_code'] ?? '—'), $byName, $reason, $id);
    }

    echo json_encode([
        'success' => true,
        'message' => 'درخواست لغو شد' . ($refunded > 0 ? ' و سهمیهٔ آن (' . formatMinutesHM($refunded) . ') برگشت' : ''),
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Cancel request error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در لغو درخواست']);
}
