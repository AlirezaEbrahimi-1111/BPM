<?php
/**
 * api/finance/compute-balances.php
 * ────────────────────────────────────────────────────────────────────
 * ورودی (JSON POST): ماتریسِ خامِ سه فایلِ شایگان که در مرورگر با SheetJS
 * خوانده شده‌اند (آرایه‌ای از ردیف‌ها، هر ردیف آرایه‌ای از سلول‌ها):
 *     { "trial":[[..],..], "detail":[[..],..], "overdue":[[..],..] }
 *
 * خروجی: بدهکاران/بستانکاران + خلاصه + تطابق. همهٔ منطق سمت سرور است؛
 * مرورگر فقط فایل را به سلول تبدیل می‌کند.
 *
 * دسترسی: فقط کاربر id=1.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/api-bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/FinanceBalances.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/crm_access.php';

if (session_status() === PHP_SESSION_NONE) {
    @require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
}

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id)                                  $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

// دسترسی موقت: همان crmReportAllowed (حسابداری + سوپروایزرهای سازمان ۱ + id=1/19).
if (!crmReportAllowed($db, (int) $user_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ورودی نامعتبر'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mapped  = FinanceBalances::mapMatrices($input);
$trial   = $mapped['trial'];
$detail  = $mapped['detail'];
$overdue = $mapped['overdue'];

$result = FinanceBalances::compute($trial, $detail, $overdue);

// تطابق: جمعِ مستقلِ ترازِ خام باید با (بدهکاری+بستانکاری) یکی باشد
$rawTotal = 0.0;
foreach ($trial as $t) {
    $n = FinanceBalances::cleanName((string)$t['name']);
    if ($n === 'جمع' || $n === '') continue;
    $rawTotal += ((float)$t['debit'] - (float)$t['credit']);
}
$result['summary']['raw_total'] = $rawTotal;
$result['summary']['reconciliation'] = $rawTotal - $result['summary']['report_total'];
$result['counts'] = ['trial' => count($trial), 'detail' => count($detail), 'overdue' => count($overdue)];

echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
