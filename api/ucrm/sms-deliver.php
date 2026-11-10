<?php
/**
 * ارسالِ واقعیِ پیامکِ CRM به مشتری (۱۴۰۵/۰۸/۱۹) — فقط crm-service (ucrm_sms.go) این را صدا می‌زند،
 * با توکنِ همان کاربری که «ارسال» را زده. اطلاعات حسابِ ملی‌پیامک فقط در includes/sms.php است (ارسال: includes/ucrm-sms-send.php).
 *
 * ورودی: { log_id }. ردیفِ ucrm_sms_log باید queued و فرستنده‌اش همین کاربر باشد (دوبار ارسال نمی‌شود).
 * خروجی: { status: sent|failed, message }؛ نتیجه در همان ردیف نوشته می‌شود.
 */
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/api-bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ucrm-sms-send.php';

function smsOut(int $code, string $status, string $message)
{
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') smsOut(405, 'failed', 'متد غیرمجاز');

$db = (new Database())->getConnection();
$uid = (int) (new Auth($db))->getUserFromToken();
if ($uid <= 0) smsOut(401, 'failed', 'توکن نامعتبر است');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$logId = (int) ($input['log_id'] ?? 0);

// قفلِ ردیف: فقط یک‌بار و فقط برای فرستندهٔ خودش
$db->beginTransaction();
$st = $db->prepare("
    SELECT l.phone, l.args, l.status, l.sent_by, t.body_id
    FROM ucrm_sms_log l LEFT JOIN ucrm_sms_templates t ON t.id = l.template_id
    WHERE l.id = ? FOR UPDATE
");
$st->execute([$logId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row || (int) $row['sent_by'] !== $uid) {
    $db->rollBack();
    smsOut(404, 'failed', 'پیامک یافت نشد');
}
if ($row['status'] !== 'queued') {
    $db->rollBack();
    smsOut(409, $row['status'], 'این پیامک قبلا پردازش شده است');
}
if (empty($row['body_id'])) {
    $db->prepare("UPDATE ucrm_sms_log SET status = 'failed', error = ? WHERE id = ?")->execute(['قالب یافت نشد', $logId]);
    $db->commit();
    smsOut(200, 'failed', 'قالب یافت نشد');
}
// «در حال ارسال» تا درخواستِ هم‌زمانِ دوم دوباره نفرستد
$db->prepare("UPDATE ucrm_sms_log SET status = 'sending' WHERE id = ?")->execute([$logId]);
$db->commit();

$args = json_decode((string) $row['args'], true);
$res = ucrmSendPatternToPhone($db, (string) $row['phone'], (int) $row['body_id'], is_array($args) ? $args : [], $uid);

$db->prepare("UPDATE ucrm_sms_log SET status = ?, error = ?, sent_at = IF(? = 'sent', NOW(), NULL) WHERE id = ?")
    ->execute([$res['ok'] ? 'sent' : 'failed', $res['ok'] ? null : mb_substr((string) $res['error'], 0, 255), $res['ok'] ? 'sent' : 'failed', $logId]);

smsOut(200, $res['ok'] ? 'sent' : 'failed', $res['ok'] ? 'پیامک فرستاده شد.' : (string) $res['error']);
