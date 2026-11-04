<?php
/**
 * تمدیدِ دستیِ دوره‌ی رایگان یک سازمان (فقط مدیر کل — دکمه‌ی superAdmin.php).
 * دوره‌ی رایگان = web_trial_ends_at (includes/plan-access.php). N روز از
 * «پایان فعلی» اگر هنوز نرسیده، وگرنه از امروز. ثبت در organization_plan_audit.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-access.php';
header('Content-Type: application/json; charset=utf-8');

if (!in_array((int) ($_SESSION['user_id'] ?? 0), getSuperAdminIds(), true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$orgId = (int) ($data['org_id'] ?? 0);
$days = (int) ($data['days'] ?? 0);
if (!$orgId || $days < 1 || $days > 365) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'تعداد روز باید بین ۱ تا ۳۶۵ باشد'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = (new Database())->getConnection();
$stmt = $db->prepare("SELECT DATE_FORMAT(web_trial_ends_at, '%Y-%m-%d') FROM organizations WHERE id = ?");
$stmt->execute([$orgId]);
$cur = $stmt->fetch(PDO::FETCH_NUM);
if (!$cur) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'سازمان یافت نشد'], JSON_UNESCAPED_UNICODE);
    exit;
}

$today = planTehranToday();
$base = ($cur[0] && $cur[0] > $today) ? $cur[0] : $today;
$newEnd = (new DateTime($base))->modify("+{$days} days")->format('Y-m-d');
$nowDt = (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d H:i:s');

$db->beginTransaction();
try {
    $db->prepare("UPDATE organizations SET web_trial_ends_at = ?, web_trial_used = 1 WHERE id = ?")->execute([$newEnd, $orgId]);
    $db->prepare("
        INSERT INTO organization_plan_audit (organization_id, old_value, new_value, source, created_at)
        VALUES (?, ?, ?, 'admin_manual', ?)
    ")->execute([
        $orgId,
        json_encode(['web_trial_ends_at' => $cur[0]]),
        json_encode(['web_trial_ends_at' => $newEnd, 'extended_days' => $days, 'by' => (int) $_SESSION['user_id']]),
        $nowDt,
    ]);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    error_log('extend-free error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/JalaliHelper.php';
echo json_encode([
    'success' => true,
    'message' => 'دوره‌ی رایگان تا ' . JalaliHelper::formatJalaliDate($newEnd) . ' تمدید شد.',
], JSON_UNESCAPED_UNICODE);
