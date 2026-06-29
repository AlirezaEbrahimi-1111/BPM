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

    // ساخت اتصال دیتابیس (اگر database.php خودش $db نساخته باشد)
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

    // اطلاعات مدیرِ جاری
    $stmt = $db->prepare("SELECT id, role, activity_section, organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$me)
        throw new Exception('کاربر یافت نشد');

    $is_superadmin = ((int) $me['id'] === 1);
    $is_manager = ($me['activity_section'] === 'management' && in_array($me['role'], ['supervisor', 'admin'], true));

    if (!$is_superadmin && !$is_manager) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $organization_id = (int) $me['organization_id'];
    if ($organization_id <= 0)
        throw new Exception('سازمان نامعتبر');

    $app_settings = loadSettings($db);

    // ماهِ شمسیِ موردنظر
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

    // تعطیلاتِ بازه (یک‌بار برای همه)
    $stmt = $db->prepare("SELECT holiday_date, title FROM holidays WHERE holiday_date >= ? AND holiday_date <= ?");
    $stmt->execute([$start_of_month, $end_of_month]);
    $holiday_dates = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
        $holiday_dates[$h['holiday_date']] = $h['title'];
    }

    // کاربرانِ فعالِ سازمان
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, activity_section,
               shift_count, shift_1_start, shift_1_end, shift_2_start, shift_2_end,
               monthly_salary, daily_work_hours
        FROM users
        WHERE organization_id = ? AND is_deleted = 0 AND is_active = 1
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$organization_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // برچسبِ فارسیِ واحدها
    $sec_labels = [];
    try {
        $sl = $db->prepare("SELECT section_key, section_label FROM organization_activity_sections WHERE organization_id = ?");
        $sl->execute([$organization_id]);
        foreach ($sl->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sec_labels[$row['section_key']] = $row['section_label'];
        }
    } catch (Exception $e) {
    }

    $rows = [];
    $sum_base = 0.0;
    $sum_penalty = 0.0;
    $sum_received = 0.0;
    $sum_final_minutes = 0;

    foreach ($users as $u) {
        $rep = sc_computeUserSalaryReport($db, $u, $start_of_month, $end_of_month, $today, $app_settings, $holiday_dates);

        $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
        if ($name === '')
            $name = 'کاربر ' . $u['id'];
        $sec_key = $u['activity_section'] ?? '';
        $sec_label = $sec_labels[$sec_key] ?? $sec_key;

        $rows[] = [
            'user_id' => (int) $u['id'],
            'name' => $name,
            'section' => $sec_label,
            'base_salary' => $rep['monthly_salary'],     // ریال
            'final_minutes' => $rep['final_minutes'],     // کسری ×ضریب (دقیقه)
            'final_hms' => $rep['final_hms'],
            'before_hms' => $rep['before_hms'],
            'shortage_money' => $rep['shortage_money'],   // ریال (جریمه تا دیروز)
            'salary_received' => $rep['salary_received'], // ریال (حقوق − جریمه)
        ];

        $sum_base += $rep['monthly_salary'];
        $sum_penalty += $rep['shortage_money'];
        $sum_received += $rep['salary_received'];
        $sum_final_minutes += $rep['final_minutes'];
    }

    echo json_encode([
        'success' => true,
        'jy' => $jy,
        'jm' => $jm,
        'today_jalali' => sprintf('%04d/%02d/%02d', $cj_y, $cj_m, $cj_d),
        'start_of_month' => $start_of_month,
        'end_of_month' => $end_of_month,
        'count' => count($rows),
        'rows' => $rows,
        'totals' => [
            'base_salary' => $sum_base,
            'shortage_money' => $sum_penalty,
            'salary_received' => $sum_received,
            'final_minutes' => $sum_final_minutes,
            'final_hms' => sc_minutesToHM($sum_final_minutes),
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
