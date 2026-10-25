<?php
/**
 * api/holidays/override.php
 * تعطیلی رسمی (سراسری) → روز کاری، فقط برای سازمان خود سرپرست — و برعکس.
 * POST { holiday_date: 'Y-m-d', action: 'working' | 'holiday' }
 *
 * فقط سرپرست سازمان (isOrgWideRole)، فقط تعطیلی سراسری یک‌روزه، فقط امروز و آینده
 * (حقوق و غیبت روزهای گذشته که حساب شده عوض نشود). بقیهٔ سازمان‌ها دست نمی‌خورند.
 * جدول: holiday_org_overrides — خواندن در getHolidaySet() و core.HolidaySet.
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/support-accounts.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';

function ho_out(array $d, int $code = 200): void
{
    ob_end_clean();
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ho_out(['success' => false, 'message' => 'متد مجاز نیست'], 405);

try {
    $auth    = new Auth();
    $user_id = $auth->getUserFromToken();
    if (!$user_id) ho_out(['success' => false, 'message' => 'احراز هویت الزامی است'], 401);
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-access.php';
    requirePlanFeature($user_id, 'admin_other');

    $db = (new Database())->getConnection();
    $me = loadUserForPermissions($db, (int) $user_id);
    $org_id = (int) ($me['organization_id'] ?? 0);
    if (!isOrgWideRole($me) || $org_id <= 0) {
        ho_out(['success' => false, 'message' => 'فقط سرپرست سازمان می‌تواند این تغییر را بدهد'], 403);
    }

    $in     = json_decode(file_get_contents('php://input'), true) ?: [];
    $date   = trim((string) ($in['holiday_date'] ?? ''));
    $action = $in['action'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($action, ['working', 'holiday'], true)) {
        ho_out(['success' => false, 'message' => 'درخواست نامعتبر است'], 400);
    }

    $today = (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
    if ($date < $today) {
        ho_out(['success' => false, 'message' => 'روزهای گذشته قابل تغییر نیستند؛ حقوق و غیبت آن روزها قبلا حساب شده است.']);
    }

    $h = $db->prepare("SELECT title FROM holidays WHERE type = 'date' AND organization_id IS NULL AND holiday_date = ? LIMIT 1");
    $h->execute([$date]);
    $title = $h->fetchColumn();
    if ($title === false) {
        ho_out(['success' => false, 'message' => 'در این تاریخ تعطیلی رسمی (سراسری) ثبت نشده است'], 404);
    }

    if ($action === 'working') {
        $st = $db->prepare("INSERT IGNORE INTO holiday_org_overrides (organization_id, holiday_date, created_by) VALUES (?, ?, ?)");
        $st->execute([$org_id, $date, $user_id]);
    } else {
        $st = $db->prepare("DELETE FROM holiday_org_overrides WHERE organization_id = ? AND holiday_date = ?");
        $st->execute([$org_id, $date]);
    }
    if ($st->rowCount() === 0) {
        ho_out(['success' => true, 'message' => 'تغییری لازم نبود', 'changed' => false]);
    }

    // اعلان (و پیامک، مثل همهٔ اعلان‌های سیستم) به کارکنان فعال همین سازمان
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/JalaliHelper.php';
        $jDate = class_exists('JalaliHelper') ? JalaliHelper::formatJalaliDate($date) : $date;
        $msg = $action === 'working'
            ? "تعطیلی «{$title}» ({$jDate}) برای سازمان ما روز کاری اعلام شد. حضور در محل کار طبق برنامهٔ عادی است."
            : "تعطیلی «{$title}» ({$jDate}) برای سازمان ما دوباره تعطیل اعلام شد.";
        $u = $db->prepare("
            SELECT id FROM users
            WHERE organization_id = ? AND is_active = 1 AND (is_deleted = 0 OR is_deleted IS NULL)
              AND id <> ? AND " . supportOnlySqlExclude('id') . "
        ");
        $u->execute([$org_id, $user_id]);
        $n = new Notification($db);
        foreach ($u->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $n->create([
                'to_user_id'   => (int) $uid,
                'title'        => $action === 'working' ? 'روز کاری به‌جای تعطیلی رسمی' : 'تعطیلی رسمی برقرار شد',
                'message'      => $msg,
                'type'         => 'info',
                'related_type' => 'holiday',
                'related_id'   => 0,
                'link'         => '/pages/holidays.php',
            ]);
        }
    } catch (Exception $e) {
        error_log('holiday override notif error: ' . $e->getMessage());
    }

    ho_out([
        'success' => true,
        'changed' => true,
        'message' => $action === 'working'
            ? 'این روز برای سازمان شما روز کاری شد'
            : 'این روز برای سازمان شما دوباره تعطیل شد',
    ]);
} catch (Exception $e) {
    error_log('holidays/override.php failed | user_id=' . ($user_id ?? 'null') . ' | ' . $e->getMessage());
    ho_out(['success' => false, 'message' => 'خطای سرور'], 500);
}
