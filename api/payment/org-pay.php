<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/payment/zarinpal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-pricing.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
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
    SELECT id, organization_id, role
    FROM users
    WHERE id = ? AND is_active = 1 AND is_deleted = 0
    LIMIT 1
");
$me->execute([$user_id]);
$me = $me->fetch(PDO::FETCH_ASSOC);

// 🔒 تمدید/ارتقایِ پلنِ سازمان یک تصمیم مالی سراسری است، نه چیزی که به
// زیرمجموعهٔ یک مدیر محدود شود؛ پس فقط supervisor/admin
if (!isOrgWideRole($me)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

$org_id = (int)$me['organization_id'];

/* ── خواندن پلن/تعدادِ کاربر/تعدادِ ماه ── */
$data       = json_decode(file_get_contents('php://input'), true) ?: [];
$plan       = (string)($data['plan'] ?? '');
$plan_users = isset($data['plan_users']) ? (int)$data['plan_users'] : null;
$months     = (int)($data['months'] ?? 0);

if (!in_array($plan, ['silver', 'gold'], true)) {
    echo json_encode(['success' => false, 'message' => 'پلن نامعتبر است'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($months < 1 || $months > 24) {
    echo json_encode(['success' => false, 'message' => 'تعداد ماه نامعتبر است'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── پلنِ نقره‌ای تعدادِ کاربرِ قیمتی ندارد — سقفِ کاربرِ فعلیِ سازمان
   حفظ می‌شود (یا دستِ‌کم ۱۰ نفر، اگر کمتر بود) ── */
if ($plan === 'silver') {
    $orgStmt = $db->prepare("SELECT max_users FROM organizations WHERE id = ?");
    $orgStmt->execute([$org_id]);
    $currentMaxUsers = (int)($orgStmt->fetchColumn() ?: 10);
    $plan_users = silverPlanUsersFor($currentMaxUsers);
}

/* ── قیمت را *سرور* تعیین می‌کند (تومان) — هرگز از کلاینت گرفته نمی‌شود ── */
$amount = planPurchasePrice($plan, $plan_users, $months);
if ($amount === null) {
    echo json_encode(['success' => false, 'message' => 'ترکیبِ پلن/تعدادِ کاربر/مدت نامعتبر است'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── ساختِ رکوردِ پرداختِ «در انتظار» ── */
$db->prepare(
    "INSERT INTO payments (organization_id, plan, plan_users, months, amount, status)
     VALUES (?, ?, ?, ?, ?, 'pending')"
)->execute([$org_id, $plan, $plan_users, $months, $amount]);
$payment_id = (int)$db->lastInsertId();

/* ── آدرسِ بازگشت — to=app یعنی بعدِ پرداخت به صفحه‌ی نتیجه‌یِ سبکِ
   مخصوصِ اپ (بدونِ نیاز به سشنِ وب) برگرد؛ همان‌جا که WebViewِ اپ آن
   را رصد می‌کند ── */
$callback = 'https://bpm.itmalek.com/api/payment/callback.php?pid=' . $payment_id . '&to=app';

/* ── درخواست به زرین‌پال ── */
$planLabel = $plan === 'gold' ? 'طلایی' : 'نقره‌ای';
$req = zarinpal_request($amount, $callback, "خرید/تمدیدِ پلن {$planLabel} به مدت {$months} ماه");

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
    'amount'      => $amount,
], JSON_UNESCAPED_UNICODE);
