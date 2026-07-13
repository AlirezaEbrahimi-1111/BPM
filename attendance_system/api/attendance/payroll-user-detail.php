<?php
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings_helper.php';
require_once __DIR__ . '/../../includes/salary_calc.php';

try {
    if (session_status() === PHP_SESSION_NONE) {
        @require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
    }

    // اتصال دیتابیس (اگر database.php خودش $db نساخته باشد)
    if (!isset($db) || !$db) {
        $database = new Database();
        $db = $database->getConnection();
    }

    $auth = new Auth($db);
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id)
        $user_id = $auth->getUserFromToken();
    if (!$user_id && isset($_COOKIE['auth_token']))
        $user_id = $auth->validateToken($_COOKIE['auth_token']);

    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'احراز هویت نامعتبر'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // اطلاعات مدیرِ جاری + گیتِ دسترسی (هماهنگ با API گرید)
    $stmt = $db->prepare("SELECT id, role, activity_section, organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$me)
        throw new Exception('کاربر یافت نشد');

    $me = loadUserForPermissions($db, $user_id);
    requirePermission($me, 'view_payroll');

    $organization_id = (int) $me['organization_id'];
    if ($organization_id <= 0)
        throw new Exception('سازمان نامعتبر');

    // ورودیِ کاربرِ هدف
    $target_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
    if ($target_user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسهٔ کاربر نامعتبر'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $app_settings = loadSettings($db);

    // ماهِ شمسیِ موردنظر (هماهنگ با API گرید)
    $today = date('Y-m-d');
    list($g_y, $g_m, $g_d) = explode('-', $today);
    list($cj_y, $cj_m, $cj_d) = sc_gregorianToJalali($g_y, $g_m, $g_d);

    $jy = isset($_GET['jy']) ? (int) $_GET['jy'] : (int) $cj_y;
    $jm = isset($_GET['jm']) ? (int) $_GET['jm'] : (int) $cj_m;
    if ($jm < 1 || $jm > 12)
        $jm = (int) $cj_m;
    if ($jy < 1300 || $jy > 1500)
        $jy = (int) $cj_y;

    $range = sc_jalaliMonthRange($jy, $jm);
    $start_of_month = $range['start'];
    $end_of_month = $range['end'];

    // ✅ کنترلِ امنیتیِ حیاتی: کاربرِ هدف باید در سازمانِ همین مدیر و فعال باشد
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, activity_section,
               shift_count, shift_1_start, shift_1_end, shift_2_start, shift_2_end,
               monthly_salary, daily_work_hours
        FROM users
        WHERE id = ? AND organization_id = ? AND is_deleted = 0 AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$target_user_id, $organization_id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد یا دسترسی مجاز نیست'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // برچسبِ فارسیِ واحد
    $sec_key = $u['activity_section'] ?? '';
    $sec_label = $sec_key;
    try {
        $sl = $db->prepare("SELECT section_label FROM organization_activity_sections WHERE organization_id = ? AND section_key = ? LIMIT 1");
        $sl->execute([$organization_id, $sec_key]);
        $r = $sl->fetch(PDO::FETCH_ASSOC);
        if ($r && $r['section_label'])
            $sec_label = $r['section_label'];
    } catch (Exception $e) {
    }

    // تعطیلاتِ بازه
    $stmt = $db->prepare("SELECT holiday_date, title FROM holidays WHERE holiday_date >= ? AND holiday_date <= ?");
    $stmt->execute([$start_of_month, $end_of_month]);
    $holiday_dates = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
        $holiday_dates[$h['holiday_date']] = $h['title'];
    }

    // محاسبهٔ کامل + جزئیات روزانه (with_days = true)
    $rep = sc_computeUserSalaryReport($db, $u, $start_of_month, $end_of_month, $today, $app_settings, $holiday_dates, true);

    $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    if ($name === '')
        $name = 'کاربر ' . $u['id'];

    echo json_encode([
        'success' => true,
        'jy' => $jy,
        'jm' => $jm,
        'start_of_month' => $start_of_month,
        'end_of_month' => $end_of_month,
        'user' => [
            'user_id' => (int) $u['id'],
            'name' => $name,
            'section' => $sec_label,
            'shift_count' => (int) $u['shift_count'],
            'shift_1_start' => $u['shift_1_start'],
            'shift_1_end' => $u['shift_1_end'],
            'shift_2_start' => $u['shift_2_start'],
            'shift_2_end' => $u['shift_2_end'],
        ],
        'summary' => [
            'monthly_salary' => $rep['monthly_salary'],
            'hourly_rate' => $rep['hourly_rate'],
            'divisor_days' => $rep['divisor_days'],
            'before_minutes' => $rep['before_minutes'],
            'before_hms' => $rep['before_hms'],
            'final_minutes' => $rep['final_minutes'],
            'final_hms' => $rep['final_hms'],
            'shortage_money' => $rep['shortage_money'],
            'salary_received' => $rep['salary_received'],
        ],
        'days' => $rep['days'],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>