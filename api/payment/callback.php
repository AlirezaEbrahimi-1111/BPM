<?php
/**
 * ============================================================
 *  بازگشت از درگاه و تأیید پرداخت
 * ============================================================
 *  این فایل برای سه حالت کار می‌کند:
 *    - پرداخت توسط سوپرادمین  (to=admin)
 *    - پرداخت توسط مدیر سازمان از وب (to=org)
 *    - پرداخت از اپِ موبایل (to=app) — بدونِ سشنِ وب، فقط یک صفحه‌ی
 *      نتیجه‌یِ سبک نشان می‌دهد که WebViewِ اپ آن را رصد می‌کند
 * ============================================================
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once __DIR__ . '/zarinpal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-pricing.php';

/* ── صفحه‌های مجاز برای بازگشت ── */
const RETURN_PAGES = [
    'admin' => '/admin/superAdmin.php',
    'org'   => '/pages/org-panel.php',
    'app'   => '/pages/payment-result.php',
];

$payment_id = (int)($_GET['pid'] ?? 0);
$authority  = $_GET['Authority'] ?? '';
$status     = $_GET['Status'] ?? '';   // OK یا NOK

$panel = RETURN_PAGES[$_GET['to'] ?? 'admin'] ?? RETURN_PAGES['admin'];

/* ── ۱) رکورد پرداخت متناظر را پیدا می‌کنیم ── */
$stmt = $db->prepare("SELECT * FROM payments WHERE id=? AND authority=?");
$stmt->execute([$payment_id, $authority]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    error_log('Payment callback: no matching payment record | pid=' . $payment_id . ' | authority=' . $authority . ' | ip=' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    header('Location: ' . $panel . '?payment=notfound');
    exit;
}

/* ── ۲) جلوگیری از تمدید دوباره (idempotency) ── */
if ($payment['status'] === 'paid') {
    header('Location: ' . $panel . '?payment=already');
    exit;
}

/* ── ۳) اگر کاربر پرداخت را لغو کرده باشد ── */
if ($status !== 'OK') {
    $db->prepare("UPDATE payments SET status='failed' WHERE id=?")->execute([$payment_id]);
    header('Location: ' . $panel . '?payment=cancel');
    exit;
}

/* ── ۴) تأیید سمت سرور با زرین‌پال (قلب امنیت) ── */
$verify = zarinpal_verify((int)$payment['amount'], $authority);

if (!$verify['ok']) {
    error_log('Payment verify failed | pid=' . $payment_id . ' | authority=' . $authority . ' | organization_id=' . ($payment['organization_id'] ?? 'unknown') . ' | error=' . json_encode($verify['error'] ?? null, JSON_UNESCAPED_UNICODE));
    $db->prepare("UPDATE payments SET status='failed' WHERE id=?")->execute([$payment_id]);
    header('Location: ' . $panel . '?payment=failed');
    exit;
}

/* ── ۵) ثبت پرداخت + ارتقایِ واقعیِ پلنِ سازمان با هم (تراکنش اتمیک) ──
   نکته‌ی مهم: منبعِ حقیقتِ پلن، ستون‌هایِ organizations.plan/plan_users/
   plan_expires_at هستند (همان‌ها که PlanAccess در اپ و planState() در
   وب می‌خوانند) — نه فقط جدولِ subscriptions. */
try {
    $db->beginTransaction();

    $db->prepare(
        "UPDATE payments SET status='paid', ref_id=?, paid_at=NOW()
         WHERE id=? AND status='pending'"
    )->execute([$verify['ref_id'], $payment_id]);

    $plan       = $payment['plan'];
    $plan_users = (int)$payment['plan_users'];
    $months     = (int)$payment['months'];

    $org = $db->prepare("SELECT plan, plan_users, plan_expires_at FROM organizations WHERE id=? FOR UPDATE");
    $org->execute([$payment['organization_id']]);
    $org = $org->fetch(PDO::FETCH_ASSOC);

    $today = (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
    $currentExpires = $org['plan_expires_at'] ? substr($org['plan_expires_at'], 0, 10) : null;
    // اگر پلنِ فعلی هنوز معتبر است، تمدید از همان تاریخ ادامه پیدا
    // می‌کند؛ وگرنه (منقضی/رایگان) از امروز شروع می‌شود
    $base = ($currentExpires !== null && $currentExpires > $today) ? $currentExpires : $today;
    $newExpires = (new DateTime($base))->modify("+{$months} months")->format('Y-m-d');

    $oldValue = [
        'plan' => $org['plan'], 'plan_users' => $org['plan_users'],
        'plan_expires_at' => $org['plan_expires_at'],
    ];
    $newValue = ['plan' => $plan, 'plan_users' => $plan_users, 'plan_expires_at' => $newExpires];

    $db->prepare("
        UPDATE organizations
        SET plan = ?, plan_users = ?, plan_expires_at = ?, plan_source = 'app_purchase'
        WHERE id = ?
    ")->execute([$plan, $plan_users, $newExpires, $payment['organization_id']]);

    $planType = $months >= 12 ? 'yearly' : 'monthly';
    $db->prepare("
        INSERT INTO subscriptions
            (organization_id, plan_type, plan, plan_users, duration_months, max_users, price, start_date, end_date, is_active, payment_ref)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
    ")->execute([
        $payment['organization_id'], $planType, $plan, $plan_users, $months,
        $plan_users, $payment['amount'], $today, $newExpires, $verify['ref_id'],
    ]);

    $nowDt = (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d H:i:s');
    $db->prepare("
        INSERT INTO organization_plan_audit (organization_id, old_value, new_value, source, created_at)
        VALUES (?, ?, ?, 'app_purchase', ?)
    ")->execute([$payment['organization_id'], json_encode($oldValue), json_encode($newValue), $nowDt]);

    $db->commit();

} catch (Exception $e) {
    $db->rollBack();
    error_log('CRITICAL: payment verified by Zarinpal but DB commit failed | pid=' . $payment_id . ' | ref_id=' . ($verify['ref_id'] ?? 'unknown') . ' | organization_id=' . ($payment['organization_id'] ?? 'unknown') . ' | error=' . $e->getMessage());
    header('Location: ' . $panel . '?payment=error');
    exit;
}

/* ── ۶) موفقیت ── */
header('Location: ' . $panel . '?payment=success&ref=' . $verify['ref_id']);
exit;
