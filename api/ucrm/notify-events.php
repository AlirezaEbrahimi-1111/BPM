<?php
/**
 * api/ucrm/notify-events.php — اعلان‌های CRM جدید (حسابداری غیررسمی).
 * ────────────────────────────────────────────────────────────────────
 * فراخوان: crm-service (crm-service/ucrm_notify.go) بعد از هر ثبت/تأیید/رد،
 * با «توکن همان کاربری که کار را انجام داد».
 *   POST { "event_ids": [12, 13, ...] }
 *
 * امنیت: فقط رویدادهایی پردازش می‌شوند که actor_id آن‌ها = همین کاربر و هنوز
 * اعلان نشده‌اند (notified_at IS NULL) — پس نه می‌شود اعلان دلخواه ساخت، نه یک
 * رویداد دوبار اعلان می‌شود. گیرنده‌ها و متن همین‌جا از روی دیتابیس تعیین می‌شوند.
 *
 * ارسال: Notification::create (اعلان داخل برنامه + پیامک + Push، مثل بقیهٔ سیستم).
 * اگر یک نفر چند اعلان هم‌نوع بگیرد (مثلا بارگذاری اکسل)، یک اعلانِ خلاصه می‌رود.
 *
 * گیرنده‌ها:
 *   ثبت/ارسال دوباره (در انتظار مدیر)       → سرپرست‌های سازمان ۱
 *   ثبت توسط مدیر / تأیید مدیر               → تیم حسابداری (+ کارشناس، برای تأیید مدیر)
 *   تأیید حسابداری                           → کارشناس
 *   رد (مدیر یا حسابداری)                    → کارشناس، با دلیل
 *   تغییر کارشناس (ویرایش مستقیم مدیر)       → کارشناس جدید
 * حساب پشتیبان (id=1) و خودِ انجام‌دهنده هیچ‌وقت گیرنده نیستند.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/api-bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/support-accounts.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';

function ucrmOut(int $code, array $body)
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') ucrmOut(405, ['success' => false, 'message' => 'متد غیرمجاز']);

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$uid = (int) $auth->getUserFromToken();
if ($uid <= 0) ucrmOut(401, ['success' => false, 'message' => 'توکن نامعتبر است']);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$ids = array_values(array_unique(array_filter(array_map('intval', (array) ($input['event_ids'] ?? [])))));
if (!$ids) ucrmOut(200, ['success' => true, 'sent' => 0]);
$ids = array_slice($ids, 0, 2000);

// ── رویدادها را «برای خودمان» قفل و علامت بزن تا دوبار ارسال نشوند ──
$in = implode(',', $ids);
$db->beginTransaction();
$events = $db->query("
    SELECT e.id, e.customer_id, e.action, c.name, c.status, c.rep_id, COALESCE(c.reject_reason, '') AS reject_reason,
           TRIM(CONCAT(COALESCE(r.first_name,''), ' ', COALESCE(r.last_name,''))) AS rep_name
    FROM ucrm_customer_events e
    JOIN ucrm_customers c ON c.id = e.customer_id
    LEFT JOIN users r ON r.id = c.rep_id
    WHERE e.id IN ($in) AND e.actor_id = $uid AND e.notified_at IS NULL
    FOR UPDATE
")->fetchAll(PDO::FETCH_ASSOC);
if ($events) {
    $db->exec("UPDATE ucrm_customer_events SET notified_at = NOW() WHERE id IN (" . implode(',', array_column($events, 'id')) . ")");
}
$db->commit();
if (!$events) ucrmOut(200, ['success' => true, 'sent' => 0]);

// ── گیرنده‌های نقش‌محور (سازمان ۱) ──
$sx = supportOnlySqlExclude('u.id');
$managers = $db->query("
    SELECT u.id FROM users u
    WHERE u.is_active = 1 AND u.organization_id = 1 AND $sx
      AND (u.role IN ('supervisor','admin','manager','management') OR COALESCE(u.is_supervisor, 0) = 1)
")->fetchAll(PDO::FETCH_COLUMN);
$accounting = $db->query("
    SELECT u.id FROM users u
    WHERE u.is_active = 1 AND u.organization_id = 1 AND $sx
      AND (u.activity_section = 'accounting'
        OR EXISTS (SELECT 1 FROM user_activity_sections s WHERE s.user_id = u.id AND s.section_key = 'accounting'))
")->fetchAll(PDO::FETCH_COLUMN);

$st = $db->prepare("SELECT TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) FROM users WHERE id = ?");
$st->execute([$uid]);
$actor = $st->fetchColumn() ?: 'یک کاربر';

// مشتری‌هایی که در همین دسته «تأیید مدیر» دارند: پیام «تغییر کارشناس» جدا لازم ندارند
$approvedNow = [];
foreach ($events as $e) {
    if ($e['action'] === 'manager_approved') $approvedNow[$e['customer_id']] = true;
}

// $queue[گیرنده][نوع] = [ ['customer_id','title','message'], ... ]
$queue = [];
$add = function ($to, string $kind, array $e, string $title, string $msg) use (&$queue, $uid) {
    $to = (int) $to;
    if ($to <= 0 || $to === $uid || isSupportOnlyUser($to)) return;
    $queue[$to][$kind][] = ['customer_id' => (int) $e['customer_id'], 'title' => $title, 'message' => $msg];
};

foreach ($events as $e) {
    $name = '«' . $e['name'] . '»';
    $rep = $e['rep_name'] !== '' ? $e['rep_name'] : 'تعیین نشده';
    switch ($e['action']) {
        case 'created':
        case 'resubmitted':
            $again = $e['action'] === 'resubmitted';
            if ($e['status'] === 'pending_manager') {
                foreach ($managers as $m) {
                    $add($m, 'to_manager', $e, 'مشتری جدید در انتظار تأیید',
                        $again ? "$actor مشتری $name را اصلاح کرد و دوباره برای تأیید شما فرستاد."
                               : "$actor مشتری $name را ثبت کرد و منتظر تأیید شماست.");
                }
            } elseif ($e['status'] === 'pending_accounting') {
                foreach ($accounting as $a) {
                    $add($a, 'to_accounting', $e, 'مشتری در انتظار تأیید حسابداری',
                        $again ? "$actor مشتری $name (کارشناس: {$rep}) را اصلاح کرد و دوباره برای تأیید حسابداری فرستاد."
                               : "$actor مشتری $name را با کارشناس فروش «{$rep}» ثبت کرد و منتظر تأیید حسابداری است.");
                }
            }
            break;

        case 'manager_approved':
            foreach ($accounting as $a) {
                $add($a, 'to_accounting', $e, 'مشتری در انتظار تأیید حسابداری',
                    "مشتری $name (کارشناس: {$rep}) توسط $actor تأیید شد و منتظر تأیید حسابداری است.");
            }
            $add($e['rep_id'], 'rep_mgr_ok', $e, 'تأیید مشتری توسط مدیر',
                "مشتری $name توسط $actor تأیید شد و برای تأیید نهایی به حسابداری رفت.");
            break;

        case 'accounting_approved':
            $add($e['rep_id'], 'rep_active', $e, 'مشتری فعال شد',
                "مشتری $name توسط حسابداری ($actor) تأیید و فعال شد.");
            break;

        case 'manager_rejected':
        case 'accounting_rejected':
            $who = $e['action'] === 'accounting_rejected' ? 'حسابداری' : 'مدیر';
            $why = $e['reject_reason'] !== '' ? ' دلیل: «' . $e['reject_reason'] . '».' : '';
            $add($e['rep_id'], 'rep_rejected', $e, 'مشتری رد شد',
                "مشتری $name توسط $actor ($who) رد شد.$why لطفا اصلاح کنید و دوباره بفرستید.");
            break;

        case 'rep_changed':
            if (empty($approvedNow[$e['customer_id']])) {
                $add($e['rep_id'], 'rep_assigned', $e, 'مشتری به شما سپرده شد',
                    "$actor مشتری $name را به شما سپرد.");
            }
            break;
    }
}

// خلاصهٔ چندتایی (مثلا بارگذاری اکسل) — یک اعلان به‌جای ده‌ها پیامک
$summary = [
    'to_manager'    => ['مشتری‌های جدید در انتظار تأیید', '%d مشتری جدید از طرف %s منتظر تأیید شماست.'],
    'to_accounting' => ['مشتری‌ها در انتظار تأیید حسابداری', '%d مشتری (کار %s) منتظر تأیید حسابداری است.'],
    'rep_mgr_ok'    => ['تأیید مشتری‌ها توسط مدیر', '%d مشتری شما توسط %s تأیید شد و برای حسابداری رفت.'],
    'rep_active'    => ['مشتری‌ها فعال شدند', '%d مشتری شما توسط حسابداری (%s) تأیید و فعال شد.'],
    'rep_rejected'  => ['مشتری‌ها رد شدند', '%d مشتری شما توسط %s رد شد؛ دلیل‌ها را در فهرست مشتریان ببینید.'],
    'rep_assigned'  => ['مشتری‌ها به شما سپرده شدند', '%s %d مشتری را به شما سپرد.'],
];

$n = new Notification($db);
$sent = 0;
foreach ($queue as $to => $kinds) {
    foreach ($kinds as $kind => $items) {
        if (count($items) === 1) {
            $it = $items[0];
            $title = $it['title'];
            $msg = $it['message'];
            $link = '../pages/ucrm-customer.php?id=' . $it['customer_id'];
            $rid = $it['customer_id'];
        } else {
            $title = $summary[$kind][0];
            $cnt = str_replace(range(0, 9), ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], (string) count($items));
            $msg = $kind === 'rep_assigned'
                ? sprintf(str_replace('%d', '%s', $summary[$kind][1]), $actor, $cnt)
                : sprintf(str_replace('%d', '%s', $summary[$kind][1]), $cnt, $actor);
            $link = '../pages/ucrm-customers.php';
            $rid = 0;
        }
        try {
            $n->create([
                'to_user_id'   => $to,
                'title'        => $title,
                'message'      => $msg,
                'type'         => $kind === 'rep_rejected' ? 'warning' : 'info',
                'link'         => $link,
                'related_type' => 'ucrm_customer',
                'related_id'   => $rid,
                'is_read'      => 0,
                'sms_pattern'  => 'general',
                'sms_args'     => [$title, $msg],
            ]);
            $sent++;
        } catch (Throwable $e) {
            error_log('ucrm notify: ' . $e->getMessage());
        }
    }
}

ucrmOut(200, ['success' => true, 'sent' => $sent]);
