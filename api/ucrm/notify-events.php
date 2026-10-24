<?php
/**
 * api/ucrm/notify-events.php — کارهای جانبی CRM جدید بعد از هر رویداد: «تسک» و «اعلان».
 * ────────────────────────────────────────────────────────────────────
 * فراخوان: crm-service (crm-service/ucrm_notify.go) بعد از هر ثبت/تأیید/رد/حذف،
 * با «توکن همان کاربری که کار را انجام داد».
 *   POST { "event_ids": [12, 13, ...] }
 *
 * امنیت: فقط رویدادهایی پردازش می‌شوند که actor_id = همین کاربر و هنوز پردازش
 * نشده‌اند (notified_at IS NULL) — نه می‌شود تسک/اعلان دلخواه ساخت، نه یک رویداد
 * دوبار پردازش می‌شود. گیرنده‌ها و متن همین‌جا از روی دیتابیس تعیین می‌شوند.
 *
 * مرحله‌ها = تسک واقعی در «کارهای من» (includes/ucrm_tasks.php):
 *   ثبت/ارسال دوباره (در انتظار مدیر)  → تسک «تأیید انتساب مشتری» برای هر سرپرست
 *   تأیید مدیر / ثبت توسط مدیر        → تسک «تأیید حسابداری مشتری» برای هر عضو حسابداری
 *   رد (مدیر یا حسابداری)              → تسک «اصلاح مشتری» برای کارشناس (با دلیل)
 *   درخواست تغییر اطلاعات مشتری فعال  → تسک «تأیید تغییر اطلاعات مشتری» برای هر عضو حسابداری (مرحلهٔ change)
 *   هر اقدام، تسک‌های باز همان مرحله را (برای همهٔ نسخه‌ها) می‌بندد.
 * اعلان: مرحله‌هایی که تسک دارند فقط اعلان «کار جدید» می‌گیرند (بدون اعلان تکراری).
 * اعلان CRM فقط برای خبرهای بدون تسک: «تأیید مدیر شد»، «مشتری فعال شد» و
 * «مشتری به شما سپرده شد» برای کارشناس.
 * اعلان = Notification::create (داخل برنامه + پیامک + Push). چند مورد هم‌نوع برای
 * یک نفر (مثلا بارگذاری اکسل) → یک اعلان خلاصه.
 * حساب پشتیبان (id=1) و خودِ انجام‌دهنده هیچ‌وقت گیرنده نیستند.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/api-bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/support-accounts.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ucrm_tasks.php';

function ucrmOut(int $code, array $body)
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function ucrmFa($n): string
{
    return str_replace(range(0, 9), ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], (string) $n);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') ucrmOut(405, ['success' => false, 'message' => 'متد غیرمجاز']);

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$uid = (int) $auth->getUserFromToken();
if ($uid <= 0) ucrmOut(401, ['success' => false, 'message' => 'توکن نامعتبر است']);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$ids = array_values(array_unique(array_filter(array_map('intval', (array) ($input['event_ids'] ?? [])))));
if (!$ids) ucrmOut(200, ['success' => true, 'tasks' => 0, 'sent' => 0]);
$ids = array_slice($ids, 0, 2000);

$st = $db->prepare("SELECT TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) FROM users WHERE id = ?");
$st->execute([$uid]);
$actor = $st->fetchColumn() ?: 'یک کاربر';

// ── گیرنده‌های نقش‌محور (سازمان ۱) ──
$sx = supportOnlySqlExclude('u.id');
$managers = array_map('intval', $db->query("
    SELECT u.id FROM users u
    WHERE u.is_active = 1 AND u.organization_id = 1 AND $sx
      AND (u.role IN ('supervisor','admin','manager','management') OR COALESCE(u.is_supervisor, 0) = 1)
")->fetchAll(PDO::FETCH_COLUMN));
$accounting = array_map('intval', $db->query("
    SELECT u.id FROM users u
    WHERE u.is_active = 1 AND u.organization_id = 1 AND $sx
      AND (u.activity_section = 'accounting'
        OR EXISTS (SELECT 1 FROM user_activity_sections s WHERE s.user_id = u.id AND s.section_key = 'accounting'))
")->fetchAll(PDO::FETCH_COLUMN));

$ok = function ($to) use ($uid) {
    $to = (int) $to;
    return $to > 0 && $to !== $uid && !isSupportOnlyUser($to);
};

// $queue[گیرنده][نوع] = [ [...], ... ]  — بعد از commit فرستاده می‌شود
$queue = [];
$sentTasks = 0;

$db->beginTransaction();
try {
    // رویدادها را «برای خودمان» قفل و علامت بزن تا دوبار پردازش نشوند
    $in = implode(',', $ids);
    $events = $db->query("
        SELECT e.id, e.customer_id, e.action, c.name, c.type, c.status, c.rep_id, COALESCE(c.reject_reason, '') AS reject_reason,
               COALESCE(c.national_id, '') AS national_id, COALESCE(c.monthly_target, c.proposed_target, 0) AS target,
               TRIM(CONCAT(COALESCE(r.first_name,''), ' ', COALESCE(r.last_name,''))) AS rep_name
        FROM ucrm_customer_events e
        JOIN ucrm_customers c ON c.id = e.customer_id
        LEFT JOIN users r ON r.id = c.rep_id
        WHERE e.id IN ($in) AND e.actor_id = $uid AND e.notified_at IS NULL
        ORDER BY e.id
        FOR UPDATE
    ")->fetchAll(PDO::FETCH_ASSOC);
    if (!$events) {
        $db->commit();
        ucrmOut(200, ['success' => true, 'tasks' => 0, 'sent' => 0]);
    }
    $db->exec("UPDATE ucrm_customer_events SET notified_at = NOW() WHERE id IN (" . implode(',', array_column($events, 'id')) . ")");

    $approvedNow = [];
    foreach ($events as $e) {
        if ($e['action'] === 'manager_approved') $approvedNow[$e['customer_id']] = true;
    }

    $phonesOf = $db->prepare("SELECT phone FROM ucrm_customer_phones WHERE customer_id = ? ORDER BY sort, id");
    // آخرین درخواست تغییر مشتری (crm-service/ucrm_changes.go) — برای متن کار و گیرندهٔ نتیجه
    $chgOf = $db->prepare("SELECT id, requested_by, changes, status, COALESCE(reject_reason,'') AS reject_reason
                           FROM ucrm_change_requests WHERE customer_id = ? ORDER BY id DESC LIMIT 1");
    $chgText = function (string $json): string {
        $lines = [];
        foreach ((array) json_decode($json, true) as $ch) {
            $v = function ($x) { return is_array($x) ? implode('، ', $x) : ((string) $x !== '' ? (string) $x : '—'); };
            $lines[] = '• ' . ($ch['label'] ?? $ch['field'] ?? '') . ': ' . $v($ch['old'] ?? '') . ' ← ' . $v($ch['new'] ?? '');
        }
        return implode("\n", $lines);
    };
    $due = null;

    // تسک یک مرحله برای چند نفر + صف اعلان «کار جدید»
    $makeTasks = function (array $e, string $stage, array $people, string $title, string $desc, bool $pending)
                 use ($db, $uid, $ok, &$queue, &$sentTasks, &$due) {
        if ($due === null) $due = ucrmNextWorkingDay($db);
        foreach (array_unique($people) as $p) {
            if (!$ok($p)) continue;
            $tid = ucrmCreateTask($db, (int) $e['customer_id'], $stage, (int) $p, $uid, $title, $desc, $pending, $due);
            $queue[(int) $p]['task'][] = ['task_id' => $tid, 'title' => $title];
            $sentTasks++;
        }
    };

    foreach ($events as $e) {
        $cid = (int) $e['customer_id'];
        $name = '«' . $e['name'] . '»';
        $rep = $e['rep_name'] !== '' ? $e['rep_name'] : 'تعیین نشده';
        $phonesOf->execute([$cid]);
        $phones = implode('، ', $phonesOf->fetchAll(PDO::FETCH_COLUMN));
        $info = "کارشناس فروش: {$rep}\nنوع مشتری: " . ($e['type'] === 'individual' ? 'حقیقی' : 'حقوقی')
              . ($e['national_id'] !== '' ? "\nکد/شناسه ملی: {$e['national_id']}" : '')
              . ($phones !== '' ? "\nشماره تماس: {$phones}" : '')
              . ((int) $e['target'] > 0 ? "\nتارگت ماهانه: " . ucrmFa(number_format((int) $e['target'])) . ' تومان' : '');

        switch ($e['action']) {
            case 'created':
            case 'resubmitted':
                $again = $e['action'] === 'resubmitted';
                if ($again) {
                    ucrmCloseStage($db, $cid, ['rep_fix', 'manager', 'accounting'], $uid, 'completed',
                        "$actor مشتری $name را اصلاح کرد و دوباره برای تأیید فرستاد.",
                        "$actor مشتری $name را اصلاح کرد و دوباره فرستاد؛ نیازی به اقدام شما نیست.");
                }
                $verb = $again ? 'را اصلاح کرد و دوباره برای تأیید فرستاد' : 'را ثبت کرد و برای تأیید فرستاد';
                if ($e['status'] === 'pending_manager') {
                    $makeTasks($e, 'manager', $managers, "تأیید انتساب مشتری: {$e['name']}",
                        "$actor مشتری $name $verb.\n$info\nبرای تأیید یا رد، همین کار را باز کنید.", true);
                } elseif ($e['status'] === 'pending_accounting') {
                    $makeTasks($e, 'accounting', $accounting, "تأیید حسابداری مشتری: {$e['name']}",
                        "$actor (مدیر) مشتری $name $verb.\n$info\nبرای تأیید یا رد، همین کار را باز کنید.", true);
                }
                break;

            case 'manager_approved':
                ucrmCloseStage($db, $cid, ['manager'], $uid, 'approved',
                    "$actor مشتری $name را با کارشناس فروش «{$rep}» تأیید کرد و برای حسابداری فرستاد.",
                    "این درخواست توسط $actor تأیید شد؛ نیازی به اقدام شما نیست.");
                $makeTasks($e, 'accounting', $accounting, "تأیید حسابداری مشتری: {$e['name']}",
                    "مشتری $name توسط $actor (مدیر) تأیید شده و منتظر تأیید حسابداری است.\n$info\nبرای تأیید یا رد، همین کار را باز کنید.", true);
                if ($ok($e['rep_id'])) {
                    $queue[(int) $e['rep_id']]['rep_mgr_ok'][] = ['customer_id' => $cid, 'title' => 'تأیید مشتری توسط مدیر',
                        'message' => "مشتری $name توسط $actor تأیید شد و برای تأیید نهایی به حسابداری رفت."];
                }
                break;

            case 'accounting_approved':
                ucrmCloseStage($db, $cid, ['accounting'], $uid, 'approved',
                    "$actor (حسابداری) مشتری $name را تأیید کرد؛ مشتری فعال شد.",
                    "این درخواست توسط $actor تأیید شد؛ نیازی به اقدام شما نیست.");
                if ($ok($e['rep_id'])) {
                    $queue[(int) $e['rep_id']]['rep_active'][] = ['customer_id' => $cid, 'title' => 'مشتری فعال شد',
                        'message' => "مشتری $name توسط حسابداری ($actor) تأیید و فعال شد."];
                }
                break;

            case 'manager_rejected':
            case 'accounting_rejected':
                $stage = $e['action'] === 'accounting_rejected' ? 'accounting' : 'manager';
                $who = $stage === 'accounting' ? 'حسابداری' : 'مدیر';
                $why = $e['reject_reason'] !== '' ? ' دلیل: «' . $e['reject_reason'] . '»' : '';
                ucrmCloseStage($db, $cid, [$stage], $uid, 'rejected',
                    "$actor ($who) مشتری $name را رد کرد.$why",
                    "این درخواست توسط $actor رد شد؛ نیازی به اقدام شما نیست.");
                $makeTasks($e, 'rep_fix', [(int) $e['rep_id']], "اصلاح مشتری: {$e['name']}",
                    "مشتری $name توسط $actor ($who) رد شد.$why\nمشتری را اصلاح کنید و دوباره برای تأیید بفرستید، یا درخواست را پس بگیرید.", false);
                break;

            case 'rep_changed':
                if (empty($approvedNow[$cid]) && $ok($e['rep_id'])) {
                    $queue[(int) $e['rep_id']]['rep_assigned'][] = ['customer_id' => $cid, 'title' => 'مشتری به شما سپرده شد',
                        'message' => "$actor مشتری $name را به شما سپرد."];
                }
                break;

            // ── درخواست تغییر اطلاعات مشتری فعال (حسابداری تأیید می‌کند؛ مدیر در این مسیر نیست) ──
            case 'change_requested':
                $chgOf->execute([$cid]);
                $q = $chgOf->fetch(PDO::FETCH_ASSOC);
                if (!$q || $q['status'] !== 'pending') break;
                $makeTasks($e, 'change', $accounting, "تأیید تغییر اطلاعات مشتری: {$e['name']}",
                    "$actor درخواست تغییر اطلاعات مشتری $name را فرستاد:\n" . $chgText($q['changes']) .
                    "\nبرای تأیید یا رد، همین کار را باز کنید.", true);
                break;

            case 'change_approved':
            case 'change_rejected':
            case 'change_cancelled':
                $chgOf->execute([$cid]);
                $q = $chgOf->fetch(PDO::FETCH_ASSOC);
                $why = ($q && $q['reject_reason'] !== '') ? ' دلیل: «' . $q['reject_reason'] . '»' : '';
                if ($e['action'] === 'change_approved') {
                    ucrmCloseStage($db, $cid, ['change'], $uid, 'approved',
                        "$actor (حسابداری) تغییر اطلاعات مشتری $name را تأیید کرد؛ تغییرات اعمال شد.",
                        "این درخواست توسط $actor تأیید شد؛ نیازی به اقدام شما نیست.");
                    $kind = 'chg_ok';
                    $title = 'تغییر اطلاعات مشتری تأیید شد';
                    $msg = "تغییر اطلاعات مشتری $name توسط حسابداری ($actor) تأیید و اعمال شد.";
                } elseif ($e['action'] === 'change_rejected') {
                    ucrmCloseStage($db, $cid, ['change'], $uid, 'rejected',
                        "$actor (حسابداری) تغییر اطلاعات مشتری $name را رد کرد.$why",
                        "این درخواست توسط $actor رد شد؛ نیازی به اقدام شما نیست.");
                    $kind = 'chg_rej';
                    $title = 'تغییر اطلاعات مشتری رد شد';
                    $msg = "درخواست تغییر اطلاعات مشتری $name توسط حسابداری ($actor) رد شد.$why";
                } else {
                    ucrmCloseStage($db, $cid, ['change'], $uid, 'completed',
                        "$actor درخواست تغییر اطلاعات مشتری $name را لغو کرد.",
                        "$actor درخواست تغییر اطلاعات مشتری $name را لغو کرد؛ نیازی به اقدام شما نیست.");
                    $kind = '';
                }
                // نتیجه برای درخواست‌دهنده (اگر خودش تصمیم نگرفته)
                if ($kind && $q && $ok($q['requested_by'])) {
                    $queue[(int) $q['requested_by']][$kind][] = ['customer_id' => $cid, 'title' => $title, 'message' => $msg];
                }
                break;

            case 'deleted':
                ucrmCloseStage($db, $cid, ['manager', 'accounting', 'rep_fix', 'change'], $uid, 'completed',
                    "$actor مشتری $name را حذف کرد.",
                    "مشتری $name توسط $actor حذف شد؛ این کار دیگر لازم نیست.");
                break;
        }
    }
    $db->commit();
} catch (Throwable $ex) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('ucrm notify-events: ' . $ex->getMessage());
    ucrmOut(500, ['success' => false, 'message' => 'خطا در ساخت تسک/اعلان CRM']);
}

// ── ارسال اعلان‌ها (بعد از commit) ──
$summary = [
    'rep_mgr_ok'   => ['تأیید مشتری‌ها توسط مدیر', '%1$s مشتری شما توسط %2$s تأیید شد و برای حسابداری رفت.'],
    'rep_active'   => ['مشتری‌ها فعال شدند', '%1$s مشتری شما توسط حسابداری (%2$s) تأیید و فعال شد.'],
    'rep_assigned' => ['مشتری‌ها به شما سپرده شدند', '%2$s %1$s مشتری را به شما سپرد.'],
    'chg_ok'       => ['تغییر اطلاعات مشتری‌ها تأیید شد', 'تغییر اطلاعات %1$s مشتری توسط حسابداری (%2$s) تأیید و اعمال شد.'],
    'chg_rej'      => ['تغییر اطلاعات مشتری‌ها رد شد', 'درخواست تغییر اطلاعات %1$s مشتری توسط حسابداری (%2$s) رد شد.'],
];

$n = new Notification($db);
$sent = 0;
foreach ($queue as $to => $kinds) {
    foreach ($kinds as $kind => $items) {
        $cnt = count($items);
        if ($kind === 'task') {
            // هم‌شکل api/tasks/create.php — اعلان «کار جدید»
            if ($cnt === 1) {
                $data = [
                    'title'        => 'کار جدید برای شما',
                    'message'      => "یک کار جدید با عنوان «{$items[0]['title']}» توسط {$actor} برای شما ایجاد شد",
                    'link'         => '/pages/task-detail.php?id=' . $items[0]['task_id'],
                    'related_type' => 'task',
                    'related_id'   => $items[0]['task_id'],
                    'sms_pattern'  => 'task_created',
                    'sms_args'     => [$items[0]['title'], $actor],
                ];
            } else {
                $msg = ucrmFa($cnt) . " کار تأیید مشتری از CRM (فرستادهٔ {$actor}) برای شما ایجاد شد.";
                $data = [
                    'title'        => 'کارهای جدید برای شما',
                    'message'      => $msg,
                    'link'         => '/pages/my-tasks.php',
                    'related_type' => 'ucrm_customer',
                    'related_id'   => 0,
                    'sms_pattern'  => 'general',
                    'sms_args'     => ['کارهای جدید برای شما', $msg],
                ];
            }
            $data['type'] = 'info';
        } else {
            if ($cnt === 1) {
                $title = $items[0]['title'];
                $msg = $items[0]['message'];
                $link = '../pages/ucrm-customer.php?id=' . $items[0]['customer_id'];
                $rid = $items[0]['customer_id'];
            } else {
                $title = $summary[$kind][0];
                $msg = sprintf($summary[$kind][1], ucrmFa($cnt), $actor);
                $link = '../pages/ucrm-customers.php';
                $rid = 0;
            }
            $data = [
                'title' => $title, 'message' => $msg, 'type' => 'info', 'link' => $link,
                'related_type' => 'ucrm_customer', 'related_id' => $rid,
                'sms_pattern' => 'general', 'sms_args' => [$title, $msg],
            ];
        }
        try {
            $data['to_user_id'] = $to;
            $data['is_read'] = 0;
            $n->create($data);
            $sent++;
        } catch (Throwable $ex) {
            error_log('ucrm notify: ' . $ex->getMessage());
        }
    }
}

ucrmOut(200, ['success' => true, 'tasks' => $sentTasks, 'sent' => $sent]);
