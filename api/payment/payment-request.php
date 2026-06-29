<?php
/**
 * ============================================================
 *  مرحله ۱ — شروع پرداخت
 * ============================================================
 *  وقتی کاربر دکمه‌ی «تمدید و پرداخت» را می‌زند، به این فایل
 *  می‌رسد. اینجا یک رکورد پرداخت ساخته و کاربر را به درگاه
 *  زرین‌پال می‌فرستیم.
 * ============================================================
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once __DIR__ . '/zarinpal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';

/* ── ۱) فقط کاربرِ لاگین‌کرده اجازه دارد ──
   نکته: کلید سشن را با سیستم لاگین خودت هماهنگ کن.
   اگر کاربر لاگین‌نکرده باشد، اینجا متوقف می‌شود. */
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('برای پرداخت ابتدا وارد شوید.');
}

/* ── ۲) سازمان را از روی سشن می‌گیریم، نه از کاربر ──
   این یعنی کاربر نمی‌تواند برای سازمان دیگری پرداخت کند. */
$org_id = (int)($_SESSION['organization_id'] ?? 0);
$months = (int)($_POST['months'] ?? 0);

if (!$org_id || $months < 1 || $months > 24) {
    exit('پارامتر نامعتبر است.');
}

/* ── ۳) قیمت را *سرور* تعیین می‌کند ──
   هرگز مبلغ را از سمت کاربر نگیر، وگرنه می‌تواند دستکاری کند.
   این عدد را به دلخواه تغییر بده (واحد: تومان). */
$price_per_month = 200000;            // ۲۰۰٬۰۰۰ تومان برای هر ماه (نمونه)
$amount = $price_per_month * $months;

/* ── ۴) یک رکورد پرداخت با وضعیت «در انتظار» می‌سازیم ── */
$db->prepare(
    "INSERT INTO payments (organization_id, months, amount, status)
     VALUES (?, ?, ?, 'pending')"
)->execute([$org_id, $months, $amount]);

$payment_id = (int)$db->lastInsertId();

/* ── ۵) آدرس بازگشت (callback) ──
   ⚠️ yourdomain.com را با دامنه‌ی واقعی سایت خودت عوض کن. */
$callback = 'https://yourdomain.com/api/payment/callback.php?pid=' . $payment_id;

/* ── ۶) درخواست به درگاه زرین‌پال ── */
$req = zarinpal_request($amount, $callback, "تمدید اشتراک {$months} ماهه");

if (!$req['ok']) {
    // اگر درگاه جواب نداد، پرداخت را ناموفق علامت می‌زنیم
    $db->prepare("UPDATE payments SET status='failed' WHERE id=?")
       ->execute([$payment_id]);
    exit('اتصال به درگاه پرداخت ناموفق بود. کمی بعد دوباره تلاش کنید.');
}

/* ── ۷) کد authority را ذخیره می‌کنیم تا در مرحله‌ی تأیید بشناسیمش ── */
$db->prepare("UPDATE payments SET authority=? WHERE id=?")
   ->execute([$req['authority'], $payment_id]);

/* ── ۸) کاربر را به صفحه‌ی پرداخت زرین‌پال می‌فرستیم ── */
header('Location: ' . $req['startUrl']);
exit;
