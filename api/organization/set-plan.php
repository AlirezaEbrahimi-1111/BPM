<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-access.php';
header('Content-Type: application/json; charset=utf-8');

// 🔒 فقط مدیر کل (superadmin) — ثبت دستیِ خرید/تمدید پلن
if (!in_array((int)($_SESSION['user_id'] ?? 0), getSuperAdminIds(), true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$orgId = (int) ($data['org_id'] ?? 0);
$plan = (string) ($data['plan'] ?? '');
$planUsers = (int) ($data['plan_users'] ?? 0);
$expires = (string) ($data['plan_expires_at'] ?? '');

$allowedUsers = [
    'free' => [1],
    'silver' => [10],
    'gold' => [5, 10, 20, 40],
];

$fail = function (string $msg) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
};

if (!$orgId) $fail('سازمان نامعتبر است');
if (!isset($allowedUsers[$plan])) $fail('پلن نامعتبر است');
if (!in_array($planUsers, $allowedUsers[$plan], true)) $fail('تعداد کاربر با این پلن سازگار نیست');

$today = planTehranToday();
if ($plan === 'free') {
    $expires = null;
} else {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)) $fail('تاریخ انقضا الزامی است');
    if ($expires <= $today) $fail('تاریخ انقضا باید بعد از امروز باشد');
}

$db = (new Database())->getConnection();

$orgStmt = $db->prepare("SELECT id FROM organizations WHERE id = ?");
$orgStmt->execute([$orgId]);
if (!$orgStmt->fetchColumn()) $fail('سازمان یافت نشد');

$old = planState($db, $orgId);
$oldValue = [
    'plan' => $old['plan'], 'plan_users' => $old['plan_users'],
    'plan_expires_at' => $old['plan_expires_at'], 'web_trial_ends_at' => $old['web_trial_ends_at'],
];
$newValue = ['plan' => $plan, 'plan_users' => $planUsers, 'plan_expires_at' => $expires];

$nowDt = (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d H:i:s');

$db->beginTransaction();
try {
    $db->prepare("
        UPDATE organizations
        SET plan = ?, plan_users = ?, plan_expires_at = ?, plan_source = 'admin_manual'
        WHERE id = ?
    ")->execute([$plan, $planUsers, $expires, $orgId]);

    if ($plan !== 'free') {
        $db->prepare("
            INSERT INTO subscriptions
                (organization_id, plan_type, plan, plan_users, duration_months, max_users, price, start_date, end_date, is_active, payment_ref)
            VALUES (?, NULL, ?, ?, NULL, ?, 0, ?, ?, 1, NULL)
        ")->execute([$orgId, $plan, $planUsers, $planUsers, $today, $expires]);
    }

    $db->prepare("
        INSERT INTO organization_plan_audit (organization_id, old_value, new_value, source, created_at)
        VALUES (?, ?, ?, 'admin_manual', ?)
    ")->execute([$orgId, json_encode($oldValue), json_encode($newValue), $nowDt]);

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    error_log('set-plan error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'ثبت پلن انجام نشد'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'message' => 'پلن سازمان ثبت شد'], JSON_UNESCAPED_UNICODE);
