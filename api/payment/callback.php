<?php
/**
 * ============================================================
 *  بازگشت از درگاه و تأیید پرداخت
 * ============================================================
 *  این فایل برای هر دو حالت کار می‌کند:
 *    - پرداخت توسط سوپرادمین  (to=admin)
 *    - پرداخت توسط مدیر سازمان (to=org)
 *  بسته به اینکه پرداخت از کجا شروع شده، کاربر را به همان صفحه
 *  برمی‌گرداند.
 * ============================================================
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once __DIR__ . '/zarinpal.php';

/* ── صفحه‌های مجاز برای بازگشت ──
   ⚠️ اگر مسیر این دو فایل در سایت تو فرق دارد، همین‌جا اصلاحشان کن. */
const RETURN_PAGES = [
    'admin' => '/admin/superAdmin.php',
    'org'   => '/pages/org-panel.php',
];

$payment_id = (int)($_GET['pid'] ?? 0);
$authority  = $_GET['Authority'] ?? '';
$status     = $_GET['Status'] ?? '';   // OK یا NOK

// صفحه‌ی بازگشت را از روی پارامتر to تعیین می‌کنیم (پیش‌فرض: پنل سوپرادمین)
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

/* ── ۵) ثبت پرداخت و تمدید اشتراک با هم (تراکنش اتمیک) ── */
try {
    $db->beginTransaction();

    $db->prepare(
        "UPDATE payments SET status='paid', ref_id=?, paid_at=NOW()
         WHERE id=? AND status='pending'"
    )->execute([$verify['ref_id'], $payment_id]);

    $upd = $db->prepare("
        UPDATE subscriptions
        SET end_date = DATE_ADD(
                IF(end_date > CURDATE(), end_date, CURDATE()),
                INTERVAL ? MONTH
            ),
            is_active   = 1,
            payment_ref = ?
        WHERE organization_id = ?
          AND is_active = 1
        ORDER BY end_date DESC
        LIMIT 1
    ");
    $upd->execute([$payment['months'], $verify['ref_id'], $payment['organization_id']]);

    if ($upd->rowCount() === 0) {
        $db->prepare("
            INSERT INTO subscriptions
              (organization_id, plan_type, max_users, price, start_date, end_date, is_active, payment_ref)
            VALUES (?, 'monthly', 50, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? MONTH), 1, ?)
        ")->execute([
            $payment['organization_id'],
            $payment['amount'],
            $payment['months'],
            $verify['ref_id'],
        ]);
    }

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
