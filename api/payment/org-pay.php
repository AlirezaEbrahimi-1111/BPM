<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php'; // ⚠️ مسیر فایل کلاس Auth خودت
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/payment/zarinpal.php'; // ⚠️ مسیر فایل zarinpal.php خودت
header('Content-Type: application/json; charset=utf-8');

/* ── تشخیص کاربر از روی توکن ── */
$auth    = new Auth($db);
$user_id = $auth->getUserFromToken();

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'توکن نامعتبر است'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── نقش و سازمان را از دیتابیس بخوان ── */
$me = $db->prepare("
    SELECT organization_id, role
    FROM users
    WHERE id = ? AND is_active = 1 AND is_deleted = 0
    LIMIT 1
");
$me->execute([$user_id]);
$me = $me->fetch(PDO::FETCH_ASSOC);

if (!$me || !in_array($me['role'], ['manager', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

$org_id = (int)$me['organization_id'];

/* ── خواندن تعداد ماه ── */
$data   = json_decode(file_get_contents('php://input'), true);
$months = (int)($data['months'] ?? 0);

if ($months < 1 || $months > 24) {
    echo json_encode(['success' => false, 'message' => 'تعداد ماه نامعتبر است'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── قیمت را سرور تعیین می‌کند (تومان) ── */
$price_per_month = 200000;            // قیمت هر ماه؛ به دلخواه تغییر بده
$amount = $price_per_month * $months;

/* ── ساخت رکورد پرداختِ «در انتظار» ── */
$db->prepare(
    "INSERT INTO payments (organization_id, months, amount, status)
     VALUES (?, ?, ?, 'pending')"
)->execute([$org_id, $months, $amount]);
$payment_id = (int)$db->lastInsertId();

/* ── آدرس بازگشت (to=org یعنی بعد از پرداخت به پنل مدیر برگرد) ──
   ⚠️ yourdomain.com را با دامنه‌ی واقعی سایتت عوض کن. */
$callback = 'https://bpm.computeryekta.com/api/payment/callback.php?pid=' . $payment_id . '&to=org';

/* ── درخواست به زرین‌پال ── */
$req = zarinpal_request($amount, $callback, "تمدید اشتراک سازمان به مدت {$months} ماه");

if (!$req['ok']) {
    $db->prepare("UPDATE payments SET status='failed' WHERE id=?")->execute([$payment_id]);
    echo json_encode(['success' => false, 'message' => 'اتصال به درگاه ناموفق بود'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db->prepare("UPDATE payments SET authority=? WHERE id=?")
   ->execute([$req['authority'], $payment_id]);

echo json_encode([
    'success'     => true,
    'payment_url' => $req['startUrl'],
], JSON_UNESCAPED_UNICODE);
