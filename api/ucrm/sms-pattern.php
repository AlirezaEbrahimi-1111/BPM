<?php
/**
 * مدیریتِ الگوهای ملی‌پیامک برای قالب‌های پیامکِ CRM (۱۴۰۵/۰۸/۱۹) — crm-service (ucrm_sms.go)
 * این را با توکنِ همان مدیر صدا می‌زند؛ اطلاعات حساب فقط در includes/sms.php است.
 *
 * ورودی { action, ... }:
 *   add    {title, body}          → { ok, body_id }      (الگو در «در انتظار تأیید» ساخته می‌شود)
 *   list   {}                     → { ok, items:[{body_id, status, note}] }
 *   edit   {body_id, title, body} → { ok }               (فقط وقتی ملی‌پیامک «نیاز به اصلاح» گفته)
 *   delete {body_id}              → { ok }
 */
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/api-bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ucrm_access.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ucrm-sms-send.php';

function patOut(int $code, array $body)
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') patOut(405, ['ok' => false, 'error' => 'متد غیرمجاز']);

$db = (new Database())->getConnection();
$uid = (int) (new Auth($db))->getUserFromToken();
if ($uid <= 0) patOut(401, ['ok' => false, 'error' => 'توکن نامعتبر است']);
if (!ucrmRoles($db, $uid)['manager']) patOut(403, ['ok' => false, 'error' => 'مدیریت قالب‌های پیامک فقط با مدیر است']);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$action = (string) ($in['action'] ?? '');

switch ($action) {
    case 'add':
        $r = ucrmPatternApi($db, 'AddBody', ['title' => (string) ($in['title'] ?? ''), 'body' => (string) ($in['body'] ?? '')]);
        $bodyId = (int) $r['value'];
        if (!$r['ok'] || $bodyId <= 0) patOut(200, ['ok' => false, 'error' => $r['error'] ?: 'ساخت الگو در ملی‌پیامک ناموفق بود']);
        patOut(200, ['ok' => true, 'body_id' => $bodyId]);

    case 'list':
        $r = ucrmPatternApi($db, 'GetBodies', ['status' => -1]);
        if (!$r['ok']) patOut(200, ['ok' => false, 'error' => $r['error']]);
        $items = [];
        foreach ((array) $r['data'] as $d) {
            $items[] = [
                'body_id' => (int) ($d['BodyID'] ?? 0),
                'status'  => (int) ($d['BodyStatus'] ?? 0),
                'note'    => (string) ($d['Description'] ?? ''),
            ];
        }
        patOut(200, ['ok' => true, 'items' => $items]);

    case 'edit':
        $r = ucrmPatternApi($db, 'EditBody', ['bodyId' => (int) ($in['body_id'] ?? 0),
            'title' => (string) ($in['title'] ?? ''), 'body' => (string) ($in['body'] ?? '')]);
        patOut(200, $r['ok'] ? ['ok' => true] : ['ok' => false, 'error' => $r['error']]);

    case 'delete':
        $r = ucrmPatternApi($db, 'DeleteBody', ['bodyId' => (int) ($in['body_id'] ?? 0)]);
        patOut(200, $r['ok'] ? ['ok' => true] : ['ok' => false, 'error' => $r['error']]);
}
patOut(400, ['ok' => false, 'error' => 'عملیات نامعتبر']);
