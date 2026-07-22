<?php
// جلوگیری از double session_start
if (session_status() === PHP_SESSION_NONE) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
}

// اتصال از فایل مرکزی
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/attendance_system/includes/date_helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings_helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';

// بساز $db رو// 2. مأموریت‌های منتظر تأیید مسئول
if (!isset($db)) {
    $database = new Database();
    $db = $database->getConnection();
}
$auth = new Auth($db);

$user_id = null;

// روش 1: SESSION
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
}

// روش 2: JWT Token از header
if (!$user_id) {
    $user_id = $auth->getUserFromToken();
}

// روش 3: JWT Token از Cookie
if (!$user_id && isset($_COOKIE['auth_token'])) {
    $user_id = $auth->validateToken($_COOKIE['auth_token']);
}

$app_settings = loadSettings($db);
// دریافت اطلاعات کاربر
$user = ['first_name' => '', 'last_name' => '', 'role' => 'employee', 'is_supervisor' => 0];
$mission_requests = [];
$leave_requests = [];
$pass_requests = [];
$forget_requests = [];
$technical_issue = [];
$pending_approvals = []; // درخواست‌های منتظر تأیید من

/**
 * محاسبه تعداد روزهای کاری بین دو تاریخ
 */
function countWorkingDays($from_date, $to_date, $db)
{
    $start = new DateTime($from_date);
    $end = new DateTime($to_date);
    $count = 0;

    // دریافت تعطیلات
    $stmt = $db->prepare("SELECT holiday_date FROM holidays WHERE holiday_date >= ? AND holiday_date <= ?");
    $stmt->execute([$start->format('Y-m-d'), $end->format('Y-m-d')]);
    $holidays = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
        $holidays[$h['holiday_date']] = true;
    }

    $current = clone $start;

    while ($current < $end) {
        $d = $current->format('Y-m-d');
        $is_friday = ($current->format('l') === 'Friday');
        if (!$is_friday && !isset($holidays[$d])) {
            $count++;
        }
        $current->modify('+1 day');
    }
    return $count;
}
// ============================================
// توابع کمکی برای محاسبات
// ============================================
function timeToMinutes($time)
{
    if (empty($time))
        return 0;
    $parts = explode(':', $time);
    return ((int) $parts[0] * 60) + (int) ($parts[1] ?? 0);
}

function minutesToHM($minutes)
{
    if ($minutes <= 0)
        return '۰:۰۰';
    $h = floor($minutes / 60);
    $m = $minutes % 60;
    return englishToFarsiNumber(sprintf('%d:%02d', $h, $m));
}

function gregorianToJalaliCalc($gy, $gm, $gd)
{
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + (int) (($gy2 + 3) / 4) - (int) (($gy2 + 99) / 100) + (int) (($gy2 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * (int) ($days / 12053);
    $days %= 12053;
    $jy += 4 * (int) ($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += (int) (($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int) ($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $days -= 186;
        $jm = 7 + (int) ($days / 30);
        $jd = 1 + ($days % 30);
    }
    return array($jy, $jm, $jd);
}

function jalaliToGregorianCalc($jy, $jm, $jd)
{
    $jy = (int) $jy;
    $jm = (int) $jm;
    $jd = (int) $jd;
    $gy = ($jy < 979) ? 621 : 1600;
    if ($jy >= 979)
        $jy -= 979;
    $days = (365 * $jy) + ((int) ($jy / 33) * 8) + (int) ((($jy % 33) + 3) / 4) + 78 + $jd;
    if ($jm < 7)
        $days += ($jm - 1) * 31;
    else
        $days += (($jm - 7) * 30) + 186;
    $gy += 400 * (int) ($days / 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * (int) (--$days / 36524);
        $days %= 36524;
        if ($days >= 365)
            $days++;
    }
    $gy += 4 * (int) ($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += (int) (($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = array(0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    for ($gm = 0; $gm < 13; $gm++) {
        if ($gd <= $sal_a[$gm])
            break;
        $gd -= $sal_a[$gm];
    }
    return array($gy, $gm, $gd);
}

function calculateOverlapMinutes($start1, $end1, $start2, $end2)
{
    $s1 = timeToMinutes($start1);
    $e1 = timeToMinutes($end1);
    $s2 = timeToMinutes($start2);
    $e2 = timeToMinutes($end2);
    if ($s1 >= $e2 || $s2 >= $e1)
        return 0;
    return max(0, min($e1, $e2) - max($s1, $s2));
}

// متغیرهای آمار جدید
$stats = [
    'work_hours' => '۰:۰۰',
    'work_minutes_total' => 0,
    'approved_requests' => 0,
    'shortage_money' => 0,
    'salary_till_today' => 0
];

if ($user_id) {
    // دریافت اطلاعات کامل کاربر فعلی
    $stmt = $db->prepare("SELECT id, first_name, last_name, role, is_supervisor, is_manager, 
                          shift_count, shift_1_start, shift_1_end, shift_2_start, shift_2_end, 
                          monthly_salary, daily_work_hours, organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // ============================================
    // محاسبه آمار جدید
    // ============================================
    date_default_timezone_set('Asia/Tehran');
    $today = date('Y-m-d');
    list($g_y, $g_m, $g_d) = explode('-', $today);
    list($j_y, $j_m, $j_d) = gregorianToJalaliCalc($g_y, $g_m, $g_d);
    list($g_start_y, $g_start_m, $g_start_d) = jalaliToGregorianCalc($j_y, $j_m, 1);
    $start_of_month = sprintf('%04d-%02d-%02d', $g_start_y, $g_start_m, $g_start_d);
    // ✅ تا پایان ماه شمسی (شامل روزهای آینده): آخرین روزِ ماه = روز اولِ ماهِ بعد منهای یک روز
    $__nj_y = ($j_m == 12) ? $j_y + 1 : $j_y;
    $__nj_m = ($j_m == 12) ? 1 : $j_m + 1;
    list($__ng_y, $__ng_m, $__ng_d) = jalaliToGregorian($__nj_y, $__nj_m, 1);
    $__end_dt = new DateTime(sprintf('%04d-%02d-%02d', $__ng_y, $__ng_m, $__ng_d));
    $__end_dt->modify('-1 day');
    $end_of_month = $__end_dt->format('Y-m-d');
    $yesterday_for_stats = date('Y-m-d', strtotime('-1 day', strtotime($today)));
    // 1. ساعت کار تا امروز
    $stmt = $db->prepare("SELECT check_in, check_out FROM attendance_records WHERE user_id = ? AND date >= ? AND date <= ?");
    $stmt->execute([$user_id, $start_of_month, $yesterday_for_stats]);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_work_minutes = 0;
    foreach ($attendance_records as $rec) {
        if (!empty($rec['check_in']) && !empty($rec['check_out'])) {
            $in_time = strtotime($rec['check_in']);
            $out_time = strtotime($rec['check_out']);
            if ($out_time > $in_time) {
                $total_work_minutes += ($out_time - $in_time) / 60;
            }
        }
    }
    $stats['work_minutes_total'] = round($total_work_minutes);
    $stats['work_hours'] = minutesToHM(round($total_work_minutes));

    // 2. درخواست‌های تأیید شده این ماه
    $approved_count = 0;

    // مأموریت
    $stmt = $db->prepare("SELECT COUNT(*) FROM mission_requests WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ? AND status = 'approved'");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $approved_count += $stmt->fetchColumn();

    // مرخصی
    $stmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND start_date >= ? AND start_date <= ? AND status = 'approved'");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $approved_count += $stmt->fetchColumn();

    // پاس (خودکار تأیید)
    $stmt = $db->prepare("SELECT COUNT(*) FROM pass_requests WHERE user_id = ? AND pass_date >= ? AND pass_date <= ? AND (status IS NULL OR status != 'cancelled')");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $approved_count += $stmt->fetchColumn();

    // فراموشی
    $stmt = $db->prepare("SELECT COUNT(*) FROM forget_requests WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ? AND status = 'approved'");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $approved_count += $stmt->fetchColumn();

    // مشکل فنی
    $stmt = $db->prepare("SELECT COUNT(*) FROM technical_issues WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ? AND status = 'approved'");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $approved_count += $stmt->fetchColumn();

    $stats['approved_requests'] = $approved_count;

    // 5. درخواست‌های در انتظار تأیید ماه جاری
    $pending_count_month = 0;
    $stmt = $db->prepare("SELECT COUNT(*) FROM mission_requests WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ? AND status = 'pending'");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $pending_count_month += $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND start_date >= ? AND start_date <= ? AND status = 'pending'");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $pending_count_month += $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM forget_requests WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ? AND status = 'pending'");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $pending_count_month += $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM technical_issues WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ? AND status = 'pending'");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $pending_count_month += $stmt->fetchColumn();

    $stats['pending_count'] = $pending_count_month;

    // 6. درخواست‌های رد شده ماه جاری
    $rejected_count_month = 0;
    $stmt = $db->prepare("SELECT COUNT(*) FROM mission_requests WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ? AND status = 'rejected'");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $rejected_count_month += $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND start_date >= ? AND start_date <= ? AND status = 'rejected'");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $rejected_count_month += $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM forget_requests WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ? AND status = 'rejected'");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $rejected_count_month += $stmt->fetchColumn();

    $stats['rejected_count'] = $rejected_count_month;
    // 3. میزان کسری ریالی (از monthly-report API)
    // نرخ ساعتی
    $working_days = intval($app_settings['working_days_per_month'] ?? 30);
    // تعداد روزهای غیرجمعهٔ این ماه (فقط جمعه‌ها کم می‌شوند) — مبنای تقسیم حقوق
    $salary_divisor_days = 0;
    $__sd = new DateTime($start_of_month);
    $__sd_end = new DateTime($end_of_month);
    while ($__sd <= $__sd_end) {
        if ($__sd->format('l') !== 'Friday') $salary_divisor_days++;
        $__sd->modify('+1 day');
    }
    if ($salary_divisor_days < 1) $salary_divisor_days = 1; // محافظت از تقسیم بر صفر

    // نرخ ساعتی
    $hourly_rate = ($user['monthly_salary'] > 0 && $user['daily_work_hours'] > 0)
        ? $user['monthly_salary'] / $salary_divisor_days / $user['daily_work_hours'] : 0;

    // تعطیلات
    $stmt = $db->prepare("SELECT holiday_date FROM holidays WHERE holiday_date >= ? AND holiday_date <= ?");
    $stmt->execute([$start_of_month, $end_of_month]);
    $holiday_dates = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
        $holiday_dates[$h['holiday_date']] = true;
    }

    // همه درخواست‌ها برای محاسبه پوشش کسری
    $all_requests_for_calc = [];

    // مأموریت
    $stmt = $db->prepare("SELECT 'mission' as type,request_code, DATE(start_date) as request_date, TIME(start_date) as start_time, TIME(end_date) as end_time, status FROM mission_requests WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ?");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $all_requests_for_calc = array_merge($all_requests_for_calc, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // مرخصی
    $stmt = $db->prepare("SELECT 'leave' as type,request_code, start_date as request_date, COALESCE(start_time, '00:00:00') as start_time, COALESCE(end_time, '23:59:59') as end_time, status FROM leave_requests WHERE user_id = ? AND start_date >= ? AND start_date <= ?");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $all_requests_for_calc = array_merge($all_requests_for_calc, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // پاس
    $stmt = $db->prepare("SELECT 'pass' as type,request_code, pass_date as request_date, start_time, end_time, CASE WHEN status = 'cancelled' THEN 'cancelled' ELSE 'approved' END as status FROM pass_requests WHERE user_id = ? AND pass_date >= ? AND pass_date <= ?");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $all_requests_for_calc = array_merge($all_requests_for_calc, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // مشکل فنی
    $stmt = $db->prepare("SELECT 'technical' as type,request_code, DATE(start_date) as request_date, start_time, end_time, status FROM technical_issues WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ?");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $all_requests_for_calc = array_merge($all_requests_for_calc, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // فراموشی
    $stmt = $db->prepare("SELECT 'forget' as type,request_code, DATE(start_date) as request_date, TIME(start_date) as start_time, TIME(end_date) as end_time, status FROM forget_requests WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ?");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $all_requests_for_calc = array_merge($all_requests_for_calc, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // گروه‌بندی بر اساس تاریخ
    $requests_by_date = [];
    foreach ($all_requests_for_calc as $req) {
        $requests_by_date[$req['request_date']][] = $req;
    }

    // رکوردهای حضور بر اساس تاریخ
    $stmt = $db->prepare("SELECT date, shift_number, check_in, check_out FROM attendance_records WHERE user_id = ? AND date >= ? AND date <= ? ORDER BY date, shift_number");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $records_by_date = [];
    foreach ($records as $rec) {
        $records_by_date[$rec['date']][] = $rec;
    }

    // محاسبه کسری
    $total_shortage_money = 0;
    $current_date = new DateTime($start_of_month);
    $end_date_obj = new DateTime($end_of_month);
    $today_obj = new DateTime($today);

    while ($current_date <= $end_date_obj) {
        $date_str = $current_date->format('Y-m-d');
        $day_name_en = $current_date->format('l');
        $is_friday = ($day_name_en === 'Friday');
        $is_holiday = isset($holiday_dates[$date_str]) || $is_friday;
        $is_past_day = ($current_date < $today_obj);

        if (!$is_holiday) {
            $day_records = $records_by_date[$date_str] ?? [];
            $shift1_in = $shift1_out = null;

            foreach ($day_records as $rec) {
                if ($rec['shift_number'] == 1) {
                    $shift1_in = $rec['check_in'] ? substr($rec['check_in'], 11, 5) : null;
                    $shift1_out = $rec['check_out'] ? substr($rec['check_out'], 11, 5) : null;
                }
            }

            $day_requests = $requests_by_date[$date_str] ?? [];

            // محاسبه کسری روز
            $shortage_minutes = 0;
            $shift_start = substr($user['shift_1_start'] ?? '08:00:00', 0, 5);
            $shift_end = substr($user['shift_1_end'] ?? '18:00:00', 0, 5);

            $shortage_slots = [];

            if (!$shift1_in && $is_past_day) {
                // عدم ورود
                $shift_minutes = timeToMinutes($shift_end) - timeToMinutes($shift_start);
                $shortage_slots[] = ['start' => $shift_start, 'end' => $shift_end, 'minutes' => $shift_minutes];
            } else if ($shift1_in) {
                // تأخیر ورود
                if ($shift1_in > $shift_start) {
                    $delay = timeToMinutes($shift1_in) - timeToMinutes($shift_start);
                    $shortage_slots[] = ['start' => $shift_start, 'end' => $shift1_in, 'minutes' => $delay];
                }
                // خروج زودهنگام
                if ($shift1_out && $shift1_out < $shift_end) {
                    $early = timeToMinutes($shift_end) - timeToMinutes($shift1_out);
                    $shortage_slots[] = ['start' => $shift1_out, 'end' => $shift_end, 'minutes' => $early];
                }
                // عدم خروج
                if ($shift1_in && !$shift1_out && $is_past_day) {
                    $no_checkout = timeToMinutes($shift_end) - timeToMinutes($shift1_in);
                    $shortage_slots[] = ['start' => $shift1_in, 'end' => $shift_end, 'minutes' => $no_checkout];
                }
            }

            // محاسبه پوشش با درخواست‌ها
            $initial_shortage = 0;
            $total_covered = 0;

            foreach ($shortage_slots as $slot) {
                $initial_shortage += $slot['minutes'];
                $slot_covered = 0;

                foreach ($day_requests as $req) {
                    $is_valid = ($req['type'] === 'pass') ? ($req['status'] !== 'cancelled') : ($req['status'] === 'approved');
                    if (!$is_valid)
                        continue;

                    $req_start = substr($req['start_time'] ?? '00:00', 0, 5);
                    $req_end = substr($req['end_time'] ?? '23:59', 0, 5);
                    $overlap = calculateOverlapMinutes($slot['start'], $slot['end'], $req_start, $req_end);
                    if ($overlap > 0)
                        $slot_covered += $overlap;
                }
                $total_covered += min($slot_covered, $slot['minutes']);
            }

            $uncovered = max(0, $initial_shortage - $total_covered);
            $final_shortage = $uncovered * $app_settings['shortage_multiplier'];

            if ($hourly_rate > 0) {
                $total_shortage_money += round(($final_shortage / 60) * $hourly_rate, 0);
            }
        }

        $current_date->modify('+1 day');
    }

    $stats['shortage_money'] = $total_shortage_money;

    // 4. حقوق تا دیروز = حقوق ماهانه - کسری ریالی نهایی
    $stats['salary_till_yesterday'] = max(0, (float) $user['monthly_salary'] - $total_shortage_money);

    // گرد کردن کسری ریالی
    $round_to = $app_settings['salary_round_to'];
    $stats['shortage_money'] = floor($stats['shortage_money'] / $round_to) * $round_to;
    $stats['salary_till_yesterday'] = max(0, (float) $user['monthly_salary'] - $stats['shortage_money']);
    $stats['salary_till_yesterday'] = floor($stats['salary_till_yesterday'] / $round_to) * $round_to;
    // ============================================
    // دریافت درخواست‌های منتظر تأیید من
    // ============================================

    // 1. مأموریت‌های منتظر تأیید مدیر (من مدیر هستم)
    $stmt = $db->prepare("
        SELECT 
            'mission' as type,
            m.id,
            m.request_code,
            m.start_date as request_date,
            m.end_date,
            m.purpose as description,
            m.status,
            m.created_at,
            m.manager_approval,
            m.supervisor_approval,
            u.first_name as requester_first_name,
            u.last_name as requester_last_name,
            'manager' as my_role
        FROM mission_requests m
        JOIN users u ON m.user_id = u.id
        WHERE u.manager_id = ?
        AND m.manager_approval = 'pending'
        AND m.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $pending_approvals = array_merge($pending_approvals, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    // امنیت:  شخیصِ مسئول/مدیر (نقش‌محور، نه id ثابت) + سازمانِ جاری
    $stmt = $db->prepare("SELECT role, activity_section, organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $me_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $my_org_id = (int) ($me_row['organization_id'] ?? 0);
    $is_supervisor = ($me_row && (
        (int) $user_id === 1 ||
        ($me_row['activity_section'] === 'management' && in_array($me_row['role'], ['supervisor', 'admin'], true))
    ));

    // 2. مأموریت‌های منتظر تأیید مسئول (من مسئول هستم)
    if ($is_supervisor) {
        $stmt = $db->prepare("
            SELECT 
                'mission' as type,
                m.id,
                m.request_code,
                m.start_date as request_date,
                m.end_date,
                m.purpose as description,
                m.status,
                m.created_at,
                m.manager_approval,
                m.supervisor_approval,
                u.first_name as requester_first_name,
                u.last_name as requester_last_name,
                'supervisor' as my_role
            FROM mission_requests m
            JOIN users u ON m.user_id = u.id
            WHERE m.manager_approval = 'approved'
            AND m.supervisor_approval = 'pending'
            AND m.status = 'pending'
            AND u.organization_id = ?
        ");
        $stmt->execute([$my_org_id]);
        $pending_approvals = array_merge($pending_approvals, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    // 3. مرخصی‌های منتظر تأیید جانشین
    $stmt = $db->prepare("
        SELECT 
            'leave' as type,
            l.id,
            l.request_code,
            CONCAT(l.start_date, ' ', l.start_time) as request_date,
            CONCAT(l.end_date, ' ', l.end_time) as end_date,
            l.reason as description,
            l.status,
            l.created_at,
            l.substitute_approval,
            l.manager_approval,
            l.supervisor_approval,
            u.first_name as requester_first_name,
            u.last_name as requester_last_name,
            'substitute' as my_role
        FROM leave_requests l
        JOIN users u ON l.user_id = u.id
        JOIN substitutes s ON s.user_id = l.user_id AND s.substitute_user_id = ? AND s.is_active = 1
        WHERE l.substitute_approval = 'pending'
        AND l.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $pending_approvals = array_merge($pending_approvals, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    // 4. مرخصی‌های منتظر تأیید مدیر
    $stmt = $db->prepare("
        SELECT 
            'leave' as type,
            l.id,
            l.request_code,
            CONCAT(l.start_date, ' ', l.start_time) as request_date,
            CONCAT(l.end_date, ' ', l.end_time) as end_date,
            l.reason as description,
            l.status,
            l.created_at,
            l.substitute_approval,
            l.manager_approval,
            l.supervisor_approval,
            u.first_name as requester_first_name,
            u.last_name as requester_last_name,
            'manager' as my_role
        FROM leave_requests l
        JOIN users u ON l.user_id = u.id
        WHERE u.manager_id = ?
        AND l.substitute_approval = 'approved'
        AND l.manager_approval = 'pending'
        AND l.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $pending_approvals = array_merge($pending_approvals, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    // 5. مرخصی‌های منتظر تأیید مسئول
    if ($is_supervisor) {
        $stmt = $db->prepare("
            SELECT 
                'leave' as type,
                l.id,
                l.request_code,
                CONCAT(l.start_date, ' ', l.start_time) as request_date,
                CONCAT(l.end_date, ' ', l.end_time) as end_date,
                l.reason as description,
                l.status,
                l.created_at,
                l.substitute_approval,
                l.manager_approval,
                l.supervisor_approval,
                u.first_name as requester_first_name,
                u.last_name as requester_last_name,
                'supervisor' as my_role
            FROM leave_requests l
            JOIN users u ON l.user_id = u.id
            WHERE l.manager_approval = 'approved'
            AND l.supervisor_approval = 'pending'
            AND l.status = 'pending'
            AND u.organization_id = ?
        ");
        $stmt->execute([$my_org_id]);
        $pending_approvals = array_merge($pending_approvals, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    // 6. فراموشی‌های منتظر تأیید مدیر
    $stmt = $db->prepare("
        SELECT 
            'forget' as type,
            f.id,
            f.request_code,
            f.start_date as request_date,
            f.end_date,
            f.description,
            f.status,
            f.created_at,
            f.manager_approval,
            f.supervisor_approval,
            u.first_name as requester_first_name,
            u.last_name as requester_last_name,
            'manager' as my_role
        FROM forget_requests f
        JOIN users u ON f.user_id = u.id
        WHERE u.manager_id = ?
        AND f.manager_approval = 'pending'
        AND f.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $pending_approvals = array_merge($pending_approvals, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    // 7. فراموشی‌های منتظر تأیید مسئول
    if ($is_supervisor) {
        $stmt = $db->prepare("
            SELECT 
                'forget' as type,
                f.id,
                f.request_code,
                f.start_date as request_date,
                f.end_date,
                f.description,
                f.status,
                f.created_at,
                f.manager_approval,
                f.supervisor_approval,
                u.first_name as requester_first_name,
                u.last_name as requester_last_name,
                'supervisor' as my_role
            FROM forget_requests f
            JOIN users u ON f.user_id = u.id
            WHERE f.manager_approval = 'approved'
            AND f.supervisor_approval = 'pending'
            AND f.status = 'pending'
            AND u.organization_id = ?
        ");
        $stmt->execute([$my_org_id]);
        $pending_approvals = array_merge($pending_approvals, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    // 8. مشکلات فنی منتظر تأیید (فقط admin یا supervisor)
    if ($user['is_supervisor'] == 1) {
        $stmt = $db->prepare("
            SELECT 
                'technical' as type,
                t.id,
                t.request_code,
                CONCAT(DATE(t.start_date), ' ', t.start_time) as request_date,
                CONCAT(DATE(t.start_date), ' ', t.end_time) as end_date,
                t.description,
                t.status,
                t.created_at,
                u.first_name as requester_first_name,
                u.last_name as requester_last_name,
                'admin' as my_role
            FROM technical_issues t
            JOIN users u ON t.user_id = u.id
            WHERE t.status = 'pending'
            AND u.organization_id = ?
        ");
        $stmt->execute([$my_org_id]);
        $pending_approvals = array_merge($pending_approvals, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    // مرتب‌سازی بر اساس تاریخ ایجاد
    usort($pending_approvals, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // ============================================
    // درخواست‌های کاربر (یا همه سازمان برای مدیران)
    // ============================================

    // بررسی اینکه آیا کاربر مدیر (supervisor یا management) است
    $is_admin_role = in_array($user['role'] ?? '', ['supervisor', 'management']);
    $org_id = $user['organization_id'] ?? null;

    $org_users_meta = [];
    $section_labels = [];
    if ($is_admin_role && $org_id) {
        // کاربران سازمان + واحد هرکدام
        $stmt = $db->prepare("SELECT id, CONCAT(first_name,' ',last_name) AS full_name, activity_section FROM users WHERE organization_id = ?");
        $stmt->execute([$org_id]);
        $org_users_meta = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $org_user_ids = array_column($org_users_meta, 'id');

        // نگاشت کلید واحد → نام فارسی
        $stmt = $db->prepare("SELECT section_key, section_label FROM organization_activity_sections WHERE organization_id = ?");
        $stmt->execute([$org_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $section_labels[$s['section_key']] = $s['section_label'];
        }
    } else {
        $org_user_ids = [$user_id];
    }

    // ساخت placeholder برای IN query
    $placeholders = implode(',', array_fill(0, count($org_user_ids), '?'));

    // نام کاربر برای نمایش در جدول مدیران
    $user_name_select = $is_admin_role
        ? "CONCAT(u.first_name, ' ', u.last_name) as user_full_name,"
        : "NULL as user_full_name,";
    $user_join = $is_admin_role
        ? "JOIN users u ON mr.user_id = u.id"
        : "";

    // دریافت درخواست‌های مأموریت
    $stmt = $db->prepare("
        SELECT 
            'mission' as type,
            mr.request_code,
            mr.id,
            mr.start_date as request_date,
            mr.end_date,
            mr.purpose as description,
            mr.status,
            mr.created_at,
            mr.manager_approval,
            mr.supervisor_approval,
            mr.can_edit,
            mr.can_delete,
            $user_name_select
            mr.user_id
        FROM mission_requests mr
        $user_join
        WHERE mr.user_id IN ($placeholders)
        ORDER BY mr.created_at DESC
    ");
    $stmt->execute($org_user_ids);
    $mission_requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // دریافت درخواست‌های مرخصی
    $stmt = $db->prepare("
        SELECT 
            'leave' as type,
            lr.id,
            lr.request_code,
            CONCAT(lr.start_date, ' ', lr.start_time) as request_date,
            CONCAT(lr.end_date, ' ', lr.end_time) as end_date,
            lr.reason as description,
            lr.status,
            lr.created_at,
            lr.substitute_approval,
            lr.manager_approval,
            lr.supervisor_approval,
            lr.can_edit,
            lr.can_delete,
            $user_name_select
            lr.user_id
        FROM leave_requests lr
        " . ($is_admin_role ? "JOIN users u ON lr.user_id = u.id" : "") . "
        WHERE lr.user_id IN ($placeholders)
        ORDER BY lr.created_at DESC
    ");
    $stmt->execute($org_user_ids);
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // دریافت درخواست‌های پاس
    $stmt = $db->prepare("
        SELECT 
            'pass' as type,
            pr.id,
            pr.request_code,
            CONCAT(pr.pass_date, ' ', pr.start_time) as request_date,
            CONCAT(pr.pass_date, ' ', pr.end_time) as end_date,
            pr.reason as description,
            pr.status,
            pr.created_at,
            pr.can_edit,
            pr.can_delete,
            $user_name_select
            pr.user_id
        FROM pass_requests pr
        " . ($is_admin_role ? "JOIN users u ON pr.user_id = u.id" : "") . "
        WHERE pr.user_id IN ($placeholders)
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute($org_user_ids);
    $pass_requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // دریافت درخواست‌های فراموشی
    try {
        $stmt = $db->prepare("
            SELECT 
                'forget' as type,
                fr.id,
                fr.request_code,
                fr.start_date as request_date,
                fr.end_date as end_date,
                fr.description as description,
                fr.status,
                fr.created_at,
                fr.manager_approval,
                fr.supervisor_approval,
                fr.can_edit,
                fr.can_delete,
                $user_name_select
                fr.user_id
            FROM forget_requests fr
            " . ($is_admin_role ? "JOIN users u ON fr.user_id = u.id" : "") . "
            WHERE fr.user_id IN ($placeholders)
            ORDER BY fr.created_at DESC
        ");
        $stmt->execute($org_user_ids);
        $forget_requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        echo "<!-- REQ_QUERY_ERROR: " . htmlspecialchars($e->getMessage()) . " -->";
    }

    // دریافت درخواست‌های مشکل فنی
    try {
        $stmt = $db->prepare("
            SELECT 
                'technical' as type,
                ti.id,
                ti.request_code,
                CONCAT(date(ti.start_date), ' ', ti.start_time) as request_date,
                CONCAT(date(ti.start_date), ' ', ti.end_time) as end_date,
                ti.description as description,
                ti.status,
                ti.created_at,
                ti.admin_id,
                $user_name_select
                ti.user_id
            FROM technical_issues ti
            " . ($is_admin_role ? "JOIN users u ON ti.user_id = u.id" : "") . "
            WHERE ti.user_id IN ($placeholders)
            ORDER BY ti.created_at DESC
        ");
        $stmt->execute($org_user_ids);
        $technical_issue = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
    }
}

// ترکیب تمام درخواست‌ها
$all_requests = array_merge($mission_requests, $leave_requests, $pass_requests, $forget_requests, $technical_issue);
// ===== لایهٔ دادهٔ گرید درخواست‌ها (فاز ج‑۱) =====
// واحدِ کاربرانِ درخواست‌ها
$__sections_map = [];
$__uids = array_values(array_unique(array_filter(array_map(function ($r) {
    return $r['user_id'] ?? null;
}, $all_requests))));
if (!empty($__uids)) {
    $__in = implode(',', array_fill(0, count($__uids), '?'));
    try {
        $__st = $db->prepare("SELECT id, activity_section FROM users WHERE id IN ($__in)");
        $__st->execute($__uids);
        foreach ($__st->fetchAll(PDO::FETCH_ASSOC) as $__r) {
            $__sections_map[$__r['id']] = $__r['activity_section'];
        }
    } catch (Exception $e) {
    }
}

$requests_for_grid = [];
$__type_labels = ['mission' => 'مأموریت', 'leave' => 'مرخصی', 'pass' => 'پاس', 'forget' => 'فراموشی', 'technical' => 'مشکل فنی'];
foreach ($all_requests as $req) {
    $type   = $req['type'];
    $status = $req['status'] ?? 'pending';

    // can_edit / can_delete (همان منطق جدول فعلی)
    $can_edit = false;
    $can_delete = false;
    $is_own = (($req['user_id'] ?? null) == $user_id);
    if (!($is_admin_role ?? false) || $is_own) {
        if ($type === 'pass') {
            $ca = new DateTime($req['created_at']);
            $nw = new DateTime();
            $hp = ($nw->getTimestamp() - $ca->getTimestamp()) / 3600;
            $can_edit = $can_delete = ($hp <= ($app_settings['pass_edit_hours'] ?? 24));
        } else {
            $has_action = in_array($status, ['approved', 'rejected', 'cancelled']);
            if (!$has_action) {
                if ($type === 'leave') {
                    // supervisor از اول approved است؛ فقط جانشین و مدیر شمرده می‌شوند
                    if (($req['substitute_approval'] ?? 'pending') !== 'pending' || ($req['manager_approval'] ?? 'pending') !== 'pending') $has_action = true;
                } elseif ($type === 'mission' || $type === 'forget') {
                    // supervisor از اول approved است؛ فقط مدیر شمرده می‌شود
                    if (($req['manager_approval'] ?? 'pending') !== 'pending') $has_action = true;
                } elseif ($type === 'technical') {
                    if (!empty($req['admin_id'])) $has_action = true;
                }
            }
            $can_edit = $can_delete = !$has_action;
        }
    }

    // برچسب وضعیت + دلیل رد
    $status_label = 'در انتظار تأیید';
    $reject_reason = '';
    if ($status === 'pending') {
        if ($type === 'leave') {
            if (($req['substitute_approval'] ?? 'pending') === 'pending') $status_label = 'در انتظار تأیید جانشین';
            elseif (($req['manager_approval'] ?? 'pending') === 'pending') $status_label = 'در انتظار تأیید مدیر';
            elseif (($req['supervisor_approval'] ?? 'pending') === 'pending') $status_label = 'در انتظار تأیید مسئول';
        } elseif ($type === 'mission' || $type === 'forget') {
            if (($req['manager_approval'] ?? 'pending') === 'pending') $status_label = 'در انتظار تأیید مدیر';
            elseif (($req['supervisor_approval'] ?? 'pending') === 'pending') $status_label = 'در انتظار تأیید مسئول';
        } elseif ($type === 'technical') {
            $status_label = 'در انتظار تأیید ادمین';
        }
    } elseif ($status === 'rejected') {
        $status_label = 'رد شده';
        if ($type === 'leave') {
            if (($req['substitute_approval'] ?? '') === 'rejected') $reject_reason = $req['substitute_notes'] ?? '';
            elseif (($req['manager_approval'] ?? '') === 'rejected') $reject_reason = $req['manager_notes'] ?? '';
            elseif (($req['supervisor_approval'] ?? '') === 'rejected') $reject_reason = $req['supervisor_notes'] ?? '';
        } elseif ($type === 'mission' || $type === 'forget') {
            if (($req['manager_approval'] ?? '') === 'rejected') $reject_reason = $req['manager_notes'] ?? '';
            elseif (($req['supervisor_approval'] ?? '') === 'rejected') $reject_reason = $req['supervisor_notes'] ?? '';
        } elseif ($type === 'technical') {
            $reject_reason = $req['admin_notes'] ?? '';
        }
    } elseif ($status === 'approved') {
        $status_label = 'تأیید شده';
    } else {
        $status_label = 'تأیید خودکار';
    }

    $date_only  = substr($req['request_date'] ?? '', 0, 10);
    $start_time = (strlen($req['request_date'] ?? '') > 10) ? substr($req['request_date'], 11, 5) : '';
    $end_time   = (!empty($req['end_date']) && strlen($req['end_date']) > 10) ? substr($req['end_date'], 11, 5) : '';

    $requests_for_grid[] = [
        'id'             => (int) ($req['id'] ?? 0),
        'type'           => $type,
        'type_label'     => $__type_labels[$type] ?? 'نامشخص',
        'status'         => $status,
        'status_label'   => $status_label,
        'reject_reason'  => $reject_reason,
        'user_id'        => $req['user_id'] ?? null,
        'user_full_name' => $req['user_full_name'] ?? '—',
        'user_section'   => $__sections_map[$req['user_id'] ?? 0] ?? '',
        'request_code'   => $req['request_code'] ?? '—',
        'date_jalali'    => $date_only ? formatDateJalali($date_only) : '—',
        'date_greg'      => $date_only,
        'start_time'     => $start_time,
        'end_time'       => $end_time,
        'description'    => $req['description'] ?? '',
        'created_jalali' => (!empty($req['created_at'])) ? (formatDateJalali(substr($req['created_at'], 0, 10)) . ' - ' . substr($req['created_at'], 11, 5)) : '',
        'created_at'     => $req['created_at'] ?? '',
        'can_edit'       => (bool) $can_edit,
        'can_delete'     => (bool) $can_delete,
        '_debug'         => "type={$type} status={$status} sub=" . ($req['substitute_approval'] ?? 'NULL') . " mgr=" . ($req['manager_approval'] ?? 'NULL') . " sup=" . ($req['supervisor_approval'] ?? 'NULL') . " can_del=" . ($can_delete ? '1' : '0'),
    ];
}
usort($all_requests, function ($a, $b) {
    return strtotime($a['created_at']) - strtotime($b['created_at']);
});

// ادامه کدهای قبلی (status_info, type_labels, formatDateJalali)
$status_info = [
    'pending' => ['label' => 'در انتظار', 'color' => '#F59E0B', 'bg' => '#FEF3C7'],
    'approved' => ['label' => 'تایید شده', 'color' => '#10B981', 'bg' => '#D1FAE5'],
    'rejected' => ['label' => 'رد شده', 'color' => '#EF4444', 'bg' => '#FEE2E2'],
    'cancelled' => ['label' => 'لغو شده', 'color' => '#6B7280', 'bg' => '#F3F4F6'],
];

$type_labels = [
    'mission' => 'مأموریت',
    'leave' => 'مرخصی',
    'pass' => 'پاس',
    'forget' => 'فراموشی',
    'technical' => 'مشکل فنی',
];

function formatDateJalali($gregorianDate)
{
    if (empty($gregorianDate) || $gregorianDate === '0000-00-00' || $gregorianDate === '0000-00-00 00:00:00') {
        return '-';
    }

    $dateTimeParts = explode(' ', $gregorianDate);
    $date_part = $dateTimeParts[0];
    $time_part = isset($dateTimeParts[1]) ? substr($dateTimeParts[1], 0, 5) : '';

    $parts = explode('-', $date_part);
    if (count($parts) !== 3) {
        return 'فرمت نامعتبر';
    }

    $gy = intval($parts[0]);
    $gm = intval($parts[1]);
    $gd = intval($parts[2]);

    list($jy, $jm, $jd) = gregorianToJalali($gy, $gm, $gd);

    $monthNames = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند'
    ];

    $jd_persian = englishToFarsiNumber($jd);
    $jy_persian = englishToFarsiNumber($jy);

    $result = $jd_persian . ' ' . $monthNames[$jm] . ' ' . $jy_persian;

    if ($time_part) {
        $time_persian = englishToFarsiNumber($time_part);
        $result .= ' - ' . $time_persian;
    }

    return $result;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>درخواست‌های من - سیستم حضور و غیاب</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/js/cdn/bootstrap-icons.css">
    <link href="../../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d" rel="stylesheet">
    <script src="../../assets/js/config.js"></script>
    <script src="../../assets/js/cdn/intro.min.js"></script>
    <link rel="stylesheet" href="../../assets/js/cdn/introjs.min.css">
    <script src="../../assets/js/cdn/jquery-3.6.0.min.js"></script>
    <script src="../../assets/js/persian-datepicker.js"></script>
    <link rel="stylesheet" href="../../assets/css/persian-datepicker.css">
    <link rel="stylesheet" href="../../assets/css/deadline-toast.css">
    <link rel="stylesheet" href="../../assets/css/custom.css">
    <script src="../../assets/js/ag-grid-community.min.js"></script>
    <script src="../../assets/js/undo-toast.js"></script>

    <style>
        /* ======================================== 
           📐 Layout دو ستونی
        ======================================== */
        .main-layout {
            padding: 32px;
            margin: 0 auto;
            display: block;
        }

        .requests-section {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .attendance-sidebar {
            min-width: 0;
        }

        /* ======================================== 
           📋 Header با کارت‌های آماری (5×20%)
        ======================================== */
        .container {
            flex: 1;
        }

        .page-header {
            display: flex;
            flex-wrap: nowrap;
            gap: 12px;
            width: 100%;
        }

        .page-header .stat-card {
            flex: 1;
            min-width: 0;
        }



        .stat-card {
            background: white;
            padding: .7rem 1.2rem .7rem !important;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(116, 76, 164, 0.15);
        }

        .stat-label {
            font-size: 11px;
            color: #5b5b5b !important;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .stat-label .bi-info-circle {
            color: #9CA3AF !important;
        }

        .stat-label .bi-info-circle:hover {
            color: #744CA4 !important;
        }

        .stat-value {
            font-size: 15px !important;
            font-weight: 700;
            color: #2D3748;
            padding-top: .5rem;
        }

        /* ======================================== 
           📋 بخش درخواست‌ها
        ======================================== */
        .controls {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 230px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 10px 35px 10px 40px;
            border: 1px solid rgba(116, 76, 164, 0.2);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #744CA4;
            box-shadow: 0 0 0 3px rgba(116, 76, 164, 0.1);
        }

        .search-box svg {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
        }

        .filters {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .filter-btn {
            padding: 10px 16px;
            background: white;
            border: 1px solid rgba(116, 76, 164, 0.2);
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #744CA4;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .filter-btn:hover {
            border-color: #744CA4;
            background: rgba(116, 76, 164, 0.05);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #744CA4 0%, #657AE7 100%);
            color: white;
            border-color: #744CA4;
        }

        /* ✅ چک‌باکس فیلتر ماه جاری */
        .current-month-filter {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px 14px;
            background: white;
            border: 1px solid rgba(116, 76, 164, 0.2);
            border-radius: 10px;
            transition: all 0.3s ease;
            user-select: none;
        }

        .current-month-filter:hover {
            border-color: #744CA4;
            background: rgba(116, 76, 164, 0.05);
        }

        .current-month-filter input[type="checkbox"] {
            display: none;
        }

        .current-month-filter .checkmark {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(116, 76, 164, 0.3);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .current-month-filter .checkmark::after {
            content: '';
            width: 5px;
            height: 9px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg) scale(0);
            transition: transform 0.2s ease;
        }

        .current-month-filter input[type="checkbox"]:checked+.checkmark {
            background: linear-gradient(135deg, #744CA4 0%, #657AE7 100%);
            border-color: #744CA4;
        }

        .current-month-filter input[type="checkbox"]:checked+.checkmark::after {
            transform: rotate(45deg) scale(1);
        }

        .current-month-filter .filter-label {
            font-size: 13px;
            font-weight: 600;
            color: #744CA4;
            white-space: nowrap;
        }

        .desc-cell {
            display: inline-block;
            max-width: 200px;
            /* عرض دلخواه ستون؛ کم/زیاد کن */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            /* همان «…» */
            vertical-align: middle;
            cursor: pointer;
        }

        .desc-cell.expanded {
            /* بعد از کلیک: نمایش کامل */
            max-width: none;
            white-space: normal;
            word-break: break-word;
        }

        .new-request-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #744CA4 0%, #657AE7 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            display: none;
        }

        .new-request-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(116, 76, 164, 0.3);
        }

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .table-scroll {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead {
            background: #F8F9FA;
            border-bottom: 1px solid rgba(116, 76, 164, 0.1);
        }

        th {
            padding: 16px;
            text-align: right;
            font-weight: 600;
            color: #64748B;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid rgba(116, 76, 164, 0.05);
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background: rgba(116, 76, 164, 0.02);
        }

        td {
            padding: 6px 12px 6px 12px;
            color: #2D3748;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 500;
            text-align: center;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 64px 32px;
            color: #A0AEC0;
            width: 100%;
        }

        /* ======================================== 
           📄 Pagination
        ======================================== */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 10px;
            gap: 8px;
            border-top: 1px solid rgba(116, 76, 164, 0.1);
        }

        .pagination-btn {
            padding: 6px 9px;
            background: white;
            border: 1px solid rgba(116, 76, 164, 0.2);
            border-radius: 8px;
            font-size: 11px;
            font-weight: 500;
            color: #744CA4;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 30px;
            text-align: center;
        }

        .pagination-btn:hover:not(:disabled) {
            border-color: #744CA4;
            background: rgba(116, 76, 164, 0.05);
            color: #744CA4;
        }

        .pagination-btn.active {
            background: linear-gradient(135deg, #744CA4 0%, #657AE7 100%);
            color: white;
            border-color: #744CA4;
        }

        .pagination-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;

        }

        /* ======================================== 
           💼 باکس اطلاعات کاربر
        ======================================== */
        .user-info-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(116, 76, 164, 0.1);
            padding: 24px;
        }

        .user-info-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid rgba(116, 76, 164, 0.1);
        }

        .user-info-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #744CA4 0%, #657AE7 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .user-info-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #2D3748;
            margin: 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(116, 76, 164, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 13px;
            color: #64748B;
            font-weight: 500;
        }

        .info-value {
            font-size: 13px;
            color: #2D3748;
            font-weight: 600;
            direction: ltr;
            text-align: left;
        }

        .shift-badge {
            display: inline-block;
            padding: 4px 10px;
            background: linear-gradient(135deg, rgba(116, 76, 164, 0.1) 0%, rgba(101, 122, 231, 0.1) 100%);
            color: #744CA4;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        /* ======================================== 
           📅 جدول حضور و غیاب
        ======================================== */
        .attendance-table-wrapper {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(116, 76, 164, 0.1);
            overflow: hidden;
            height: 700px;
        }

        .attendance-table-header {
            background: linear-gradient(135deg, #744ca4 0%, #657ae7 100%);
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .attendance-table-header h4 {
            color: white;
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .month-display {
            color: white;
            font-size: 13px;
            opacity: 0.95;
        }

        .attendance-table-container {
            /*max-height: calc(100vh - 280px);*/
            overflow-y: auto;
        }

        .attendance-table-container::-webkit-scrollbar {
            width: 8px;
        }

        .attendance-table-container::-webkit-scrollbar-track {
            background: rgba(116, 76, 164, 0.05);
        }

        .attendance-table-container::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #744ca4 0%, #657ae7 100%);
            border-radius: 10px;
        }

        .attendance-table {
            width: 100%;
            font-size: 12px;
        }

        .attendance-table thead {
            background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%) !important;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .attendance-table thead th {
            padding: 15px;

            font-weight: 600;
            color: #ffffffff;
            border: none;
            font-size: .85rem;
            text-align: center;
        }

        .attendance-table tbody tr {
            border-bottom: 1px solid rgba(116, 76, 164, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .attendance-table tbody tr:hover:not(.holiday-row) {
            background: linear-gradient(135deg, rgba(116, 76, 164, 0.04) 0%, rgba(101, 122, 231, 0.04) 100%);
        }

        .attendance-table tbody td {
            padding: 12px;
            font-size: 13px !important;
            color: #2d3748;
            text-align: center;
            border: none;
        }

        .date-cell {
            font-weight: 600;
        }

        .day-name {
            color: #64748b;
            display: block;
        }

        .date-number {
            color: #2d3748;
            font-size: 13px;
            font-weight: 600;
        }

        .time-cell {
            line-height: 1.5;
        }

        .shift-time {
            display: block;
            color: #2d3748;
            font-weight: 500;
            font-size: 13px !important;
            line-height: 2;
            margin-top: 5px;
        }

        .shift-time.shift-2 {
            color: #64748b;
            font-size: 13px;
            margin-top: 0;
            line-height: 2;
        }

        .time-empty {
            color: #cbd5e1;
            font-style: italic;
            font-size: 10px;
        }

        .shortage-hours {
            font-weight: 600;
            color: #dc2626;
            font-size: 13px !important;
        }

        .shortage-hours.zero {
            color: #059669;
        }

        .shortage-money {
            font-weight: 600;
            color: #dc2626;
            direction: rtl;
            font-size: 13px !important;
        }

        .shortage-money.zero {
            color: #059669;
        }

        .action-btn-small {
            background: linear-gradient(135deg, #744ca4 0%, #657ae7 100%);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-btn-small:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(116, 76, 164, 0.3);
        }

        .holiday-row {
            background: linear-gradient(135deg, rgba(203, 213, 225, 0.3) 0%, rgba(226, 232, 240, 0.3) 100%);
            cursor: default !important;
        }

        .holiday-row:hover {
            background: linear-gradient(135deg, rgba(203, 213, 225, 0.3) 0%, rgba(226, 232, 240, 0.3) 100%) !important;
        }

        .holiday-row td {
            color: #94a3b8 !important;
        }

        .holiday-label {
            display: inline-block;

            color: #64748b;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
        }

        .attendance-loading {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }

        /* ======================================== 
           Modal Styles
        ======================================== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 200;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
            padding: 20px;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 700px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid rgba(116, 76, 164, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: #2D3748;
        }

        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 4px 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            opacity: 0.6;
        }

        .modal-body {
            flex: 1;
            padding: 0;
            overflow: auto;
            display: flex;
            flex-direction: column;
        }

        .tabs-container {
            display: flex;
            border-bottom: 2px solid #E5E7EB;
            background: white;
            flex-shrink: 0;
            width: 100%;
        }

        .tab-button {
            flex: 1;
            min-width: 100px;
            padding: 16px;
            background: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #718096;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            position: relative;
            white-space: nowrap;
        }

        .tab-button:hover {
            color: #744CA4;
            background: rgba(116, 76, 164, 0.03);
        }

        .tab-button.active {
            color: #744CA4;
            border-bottom-color: #744CA4;
        }

        .tab-button svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .tab-content {
            display: none;
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #2D3748;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .form-group label.required::after {
            content: ' *';
            color: #EF4444;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(116, 76, 164, 0.2);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Vazir', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #744CA4;
            box-shadow: 0 0 0 3px rgba(116, 76, 164, 0.1);
        }

        .form-group input:disabled,
        .form-group select:disabled,
        .form-group textarea:disabled {
            background-color: #f3f4f6;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            padding: 24px;
            background: white;
            border-top: 1px solid rgba(116, 76, 164, 0.1);
            flex-shrink: 0;
        }

        .btn-submit {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, #744CA4 0%, #657AE7 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(116, 76, 164, 0.3);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-cancel {
            flex: 1;
            padding: 12px;
            background: #F3F4F6;
            color: #2D3748;
            border: 1px solid rgba(116, 76, 164, 0.2);
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-cancel:hover {
            background: #E5E7EB;
        }

        .request-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 12px !important;
            font-weight: 600;
            margin: 1px;
            white-space: nowrap;
        }

        .request-badge.pass {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
        }

        .request-badge.mission {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            color: white;
        }

        .request-badge.leave {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            color: white;
        }

        .request-badge.forget {
            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
            color: white;
        }

        .request-badge.technical {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            color: white;
        }

        .request-badge i {
            font-size: 10px;
        }

        .requests-cell {
            max-width: 120px;
            line-height: 1.6;
        }

        /* ======================================== 
           📱 Responsive
        ======================================== */
        @media (max-width: 1400px) {
            .main-layout {
                grid-template-columns: 1fr;
            }

            .attendance-sidebar {
                position: static;
            }
        }

        @media (max-width: 992px) {
            .page-header {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 120px;
            }

            .main-layout {
                padding: 16px;
            }

            .controls {
                flex-direction: column;
            }

            .search-box {
                min-width: 100%;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .modal-content {
                max-width: 95%;
            }

            .tab-button {
                font-size: 12px;
                padding: 12px;
            }
        }

        /* ======================================== 
           🔘 دکمه‌های عملیات در جدول
        ======================================== */

        /* تب‌های سوییچ بین بخش‌ها */
        .section-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            background: #ebebebff;
            padding: 6px;
            border-radius: 12px;
        }

        .section-tab {
            flex: 1;
            padding: 12px 20px;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            color: #6B7280;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .section-tab:hover {
            color: #374151;
        }

        .section-tab.active {
            background: white;
            color: #744CA4;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .section-tab i {
            font-size: 16px;
        }

        .pending-badge {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            color: white;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
        }

        /* بج نقش تأیید */
        .role-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .role-badge.substitute {
            background: #FEF3C7;
            color: #D97706;
        }

        .role-badge.manager {
            background: #DBEAFE;
            color: #2563EB;
        }

        .role-badge.supervisor {
            background: #F3E8FF;
            color: #7C3AED;
        }

        .role-badge.admin {
            background: #FCE7F3;
            color: #DB2777;
        }

        /* دکمه‌های تأیید و رد */
        .action-icon-btn.approve-btn {
            background: #D1FAE5;
            color: #059669;
        }

        .action-icon-btn.approve-btn:hover {
            background: #059669;
            color: white;
        }

        .action-icon-btn.reject-btn {
            background: #FEE2E2;
            color: #DC2626;
        }

        .action-icon-btn.reject-btn:hover {
            background: #DC2626;
            color: white;
        }

        .loading-state {
            padding: 40px;
            text-align: center;
            color: #6B7280;
        }

        .loading-state .spinner-border {
            width: 30px;
            height: 30px;
            border-width: 3px;
        }

        /* ======================================== 
           💬 Tooltip برای دلیل رد
        ======================================== */
        .status-with-timeline {
            cursor: pointer;
            display: inline-block;
            align-items: center;
            gap: 6px;
        }

        .timeline-icon {
            font-size: 12px;
            opacity: 0.6;
        }

        /* Tooltip Container - با JS کنترل میشه */
        .custom-tooltip {
            position: fixed;
            background: #1F2937;
            color: white;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            max-width: 280px;
            z-index: 99999;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .custom-tooltip.visible {
            opacity: 1;
        }

        .reject-reason-icon {
            cursor: help;
            font-size: 13px;
            margin-right: 4px;
            color: #DC2626;
        }

        .reject-reason-icon:hover {
            color: #991B1B;
        }

        /* ======================================== 
           📅 Timeline Modal
        ======================================== */
        .timeline-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .timeline-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .timeline-modal {
            background: white;
            border-radius: 16px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .timeline-modal-overlay.active .timeline-modal {
            transform: scale(1);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #E5E7EB;
        }

        .timeline-header h3 {
            margin: 0;
            font-size: 16px;
            color: #1F2937;
        }

        .timeline-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #6B7280;
            padding: 4px;
        }

        .timeline-container {
            position: relative;
            padding-right: 24px;
        }

        .timeline-container::before {
            content: '';
            position: absolute;
            right: 7px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #E5E7EB;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 20px;
            padding-right: 24px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            right: -24px;
            top: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #E5E7EB;
        }

        .timeline-dot.pending {
            background: #F59E0B;
            box-shadow: 0 0 0 2px #FEF3C7;
        }

        .timeline-dot.approved {
            background: #10B981;
            box-shadow: 0 0 0 2px #D1FAE5;
        }

        .timeline-dot.rejected {
            background: #EF4444;
            box-shadow: 0 0 0 2px #FEE2E2;
        }

        .timeline-dot.waiting {
            background: #9CA3AF;
            box-shadow: 0 0 0 2px #F3F4F6;
        }

        .timeline-content {
            background: #F9FAFB;
            border-radius: 10px;
            padding: 12px;
        }

        .timeline-role {
            font-weight: 600;
            font-size: 13px;
            color: #374151;
            margin-bottom: 4px;
        }

        .timeline-status {
            font-size: 12px;
            margin-bottom: 4px;
        }

        .timeline-status.approved {
            color: #059669;
        }

        .timeline-status.rejected {
            color: #DC2626;
        }

        .timeline-status.pending {
            color: #D97706;
        }

        .timeline-status.waiting {
            color: #6B7280;
        }

        .timeline-date {
            font-size: 11px;
            color: #9CA3AF;
            margin-bottom: 4px;
        }

        .timeline-approver {
            font-size: 11px;
            color: #6B7280;
        }

        .timeline-notes {
            font-size: 11px;
            color: #6B7280;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px dashed #E5E7EB;
            font-style: italic;
        }

        .actions-cell {
            display: flex;
            gap: 4px;
            justify-content: center;
            align-items: center;
        }

        .action-icon-btn {
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 13px;
        }

        .action-icon-btn.edit-btn {
            background: #EEF2FF;
            color: #4F46E5;
        }

        .action-icon-btn.edit-btn:hover {
            background: #4F46E5;
            color: white;
        }

        .action-icon-btn.delete-btn {
            background: #FEE2E2;
            color: #EF4444;
        }

        .action-icon-btn.delete-btn:hover {
            background: #EF4444;
            color: white;
        }

        .action-icon-btn.approve-btn {
            background: #D1FAE5;
            color: #10B981;
        }

        .action-icon-btn.approve-btn:hover {
            background: #10B981;
            color: white;
        }

        .no-action {
            color: #9CA3AF;
            font-size: 12px;
        }

        /* ردیف‌های جدول حضور قابل کلیک */
        .attendance-table tbody tr:not(.holiday-row) {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .attendance-table tbody tr:not(.holiday-row):hover {
            background-color: rgba(116, 76, 164, 0.08) !important;
        }

        /* مودال ویرایش */
        .edit-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1100;
            justify-content: center;
            align-items: center;
        }

        .edit-modal-overlay.show {
            display: flex;
        }

        .edit-modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .edit-modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .edit-modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: #1F2937;
        }

        .edit-modal-body {
            padding: 24px;
        }

        .edit-modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #E5E7EB;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-save {
            padding: 10px 20px;
            background: linear-gradient(135deg, #744CA4 0%, #657AE7 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(116, 76, 164, 0.3);
        }

        .btn-close-modal {
            padding: 10px 20px;
            background: #F3F4F6;
            color: #374151;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-close-modal:hover {
            background: #E5E7EB;
        }

        /* فرمت ۲۴ ساعته برای input time */
        input[type="time"] {
            font-variant-numeric: tabular-nums;
        }

        /* مخفی کردن AM/PM در مرورگرهای WebKit */
        input[type="time"]::-webkit-datetime-edit-ampm-field {
            display: none;
        }

        /* استایل input ساعت سفارشی */
        .time-input-custom {
            direction: ltr;
            text-align: center;
            font-size: 16px;
            letter-spacing: 2px;
            font-family: monospace, 'Vazir', sans-serif;
        }

        .time-input-custom::placeholder {
            font-family: 'Vazir', sans-serif;
            letter-spacing: 0;
            font-size: 13px;
        }

        #requestsGrid .req-actions-cell {
            display: flex !important;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        #requestsGrid .req-actions-cell .action-icon-btn {
            flex: 0 0 auto;
        }

        #attendanceGrid {
            font-family: inherit;
        }

        #attendanceGrid .att-clickable {
            cursor: pointer;
        }

        #attendanceGrid .att-clickable:hover {
            background: #f5f4fb !important;
        }

        #attendanceGrid .att-holiday {
            background: #f3f4f6 !important;
            color: #9ca3af !important;
        }

        #attendanceGrid .att-holiday .att-off {
            color: #9ca3af;
            font-weight: 700;
        }

        #attendanceGrid .att-disabled {
            opacity: .55;
            cursor: default;
        }

        #attendanceGrid .att-date-num {
            font-weight: 600;
        }
    </style>
</head>

<body>
    <!-- استفاده از header.php واقعی -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/pages/header.php'; ?>

    <!-- تنظیمات سیستم برای JavaScript -->
    <script>
        // ── مبدل سراسری: همهٔ alertها به toast تبدیل می‌شوند ──
        window.alert = function(msg) {
            const text = String(msg).replace(/^\s*[✅❌⚠️ℹ️]\s*/, '');
            const type = /موفق|ثبت شد|ذخیره شد|انجام شد|تکمیل/.test(text) ? 'success' : 'warning';
            showToast(text, type);
        };

        const APP_SETTINGS = {
            clickable_days_limit: <?php echo intval($app_settings['clickable_days_limit'] ?? 5); ?>,
            shortage_multiplier: <?php echo intval($app_settings['shortage_multiplier'] ?? 2); ?>,
            pass_edit_hours: <?php echo intval($app_settings['pass_edit_hours'] ?? 24); ?>,
            pass_max_count_monthly: <?php echo intval($app_settings['pass_max_count_monthly'] ?? 0); ?>,
            pass_max_hours_daily: <?php echo intval($app_settings['pass_max_hours_daily'] ?? 0); ?>,
            pass_max_hours_monthly: <?php echo intval($app_settings['pass_max_hours_monthly'] ?? 0); ?>,
            mission_max_hours_monthly: <?php echo intval($app_settings['mission_max_hours_monthly'] ?? 0); ?>,
            technical_max_monthly: <?php echo intval($app_settings['technical_max_monthly'] ?? 0); ?>,
            forget_max_monthly: <?php echo intval($app_settings['forget_max_monthly'] ?? 0); ?>,
            leave_max_consecutive: <?php echo intval($app_settings['leave_max_consecutive'] ?? 20); ?>,
            working_days_per_month: <?php echo intval($app_settings['working_days_per_month'] ?? 30); ?>,
            salary_round_to: <?php echo intval($app_settings['salary_round_to'] ?? 100000); ?>,
        };
        const REQUESTS_DATA = <?php echo json_encode($requests_for_grid, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const IS_ADMIN_ROLE = <?php echo ($is_admin_role ?? false) ? 'true' : 'false'; ?>;
    </script>
    <!-- Main Layout -->
    <div class="main-layout">

        <!-- یک ستون کامل با سه تب -->
        <div class="requests-section">
            <div class="container">
                <!-- تب‌های سوییچ -->
                <div class="section-tabs">
                    <button class="section-tab active" onclick="switchSection('attendance')" id="tab-attendance">
                        <i class="bi bi-calendar-check"></i>
                        ورود و خروج
                    </button>
                    <button class="section-tab" onclick="switchSection('my-requests')" id="tab-my-requests">
                        <i class="bi bi-file-earmark-text"></i>
                        <?php echo ($is_admin_role ?? false) ? 'درخواست‌های سازمان' : 'درخواست‌های من'; ?>
                    </button>
                    <button class="section-tab" onclick="switchSection('pending-approvals')" id="tab-pending-approvals">
                        <i class="bi bi-clock-history"></i>
                        منتظر تأیید من
                        <span class="pending-badge" id="pendingCount" style="display: none;">۰</span>
                    </button>
                </div>

                <!-- بخش ورود و خروج -->
                <div id="attendance-section">
                    <div class="attendance-sidebar">
                        <div class="attendance-table-wrapper">
                            <div class="attendance-table-container" id="attendanceTableContainer">
                                <div class="attendance-loading">
                                    <div class="spinner-border" role="status"></div>
                                    <div class="mt-2">در حال بارگذاری...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- بخش درخواست‌های من -->
                <div id="my-requests-section" style="display: none;">
                    <!-- Header: عنوان + 4 کارت آماری (5×20%) -->
                    <?php if (!($is_admin_role ?? false)): ?>
                        <div class="page-header">
                            <div class="stat-card">
                                <div class="stat-label">ساعت کار تا دیروز</div>
                                <div class="stat-value" style="color: #3B82F6;">
                                    <?php echo $stats['work_hours']; ?>
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-label">درخواست‌ تأیید شده</div>
                                <div class="stat-value" style="color: #10B981;">
                                    <?php echo englishToFarsiNumber($stats['approved_requests']); ?>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">در انتظار تأیید</div>
                                <div class="stat-value" style="color: #F59E0B;">
                                    <?php echo englishToFarsiNumber($stats['pending_count']); ?>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">رد شده ماه جاری</div>
                                <div class="stat-value" style="color: #EF4444;">
                                    <?php echo englishToFarsiNumber($stats['rejected_count']); ?>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">
                                    کسری تا دیروز
                                    <i class="bi bi-info-circle" id="shortageInfoIcon" style="font-size:12px;color:#9CA3AF;cursor:help;margin-right:4px;" title=""></i>
                                </div>
                                <div class="stat-value" style="color: #EF4444;" id="cardShortageHM">—</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">جریمهٔ کسری تا دیروز</div>
                                <div class="stat-value" style="color: #EF4444; font-size: 15px;" id="cardPenaltyToman">—</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">حقوق تا دیروز</div>
                                <div class="stat-value" style="color: #8B5CF6; font-size: 15px;" id="cardSalaryToman">—</div>
                            </div>

                            <div class="stat-card">
                                <div style="display:flex;align-items:center;gap:4px;margin-bottom:6px;">
                                    <span class="stat-label" style="margin-bottom:0;">درخواست‌های این ماه</span>
                                    <i class="bi bi-info-circle" id="monthCountInfoIcon" style="font-size:11px;cursor:help;color:#6B7280 !important;"></i>
                                </div>
                                <div class="stat-value" id="cardCounts" style="color:#744CA4;">—</div>
                            </div>
                            <script>
                                const MY_USER_ID = <?php echo (int) $user_id; ?>;
                                const MY_MONTHLY_SALARY = <?php echo (float) ($user['monthly_salary'] ?? 0); ?>;
                            </script>

                        </div>
                    <?php else: ?>
                        <!-- نمایش عنوان برای مدیران -->
                        <div style="margin-bottom: 20px; padding: 14px 20px; background: linear-gradient(135deg, rgba(116,76,164,0.08) 0%, rgba(101,122,231,0.08) 100%); border-radius: 12px; border-right: 4px solid #744CA4;">
                            <h5 style="margin: 0; color: #744CA4; font-weight: 700;">
                                <i class="bi bi-people-fill me-2"></i>
                                درخواست‌های همه کارمندان سازمان
                            </h5>
                        </div>
                        <!-- کارت‌های آماریِ شخصیِ مدیر -->
                        <div class="page-header">
                            <div class="stat-card">
                                <div class="stat-label">
                                    کسری تا دیروز
                                    <i class="bi bi-info-circle" id="shortageInfoIcon" style="font-size:12px;color:#6B7280;cursor:help;margin-right:4px;opacity:0.7;" title=""></i>
                                </div>
                                <div class="stat-value" style="color: #EF4444;" id="cardShortageHM">—</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">جریمهٔ کسری تا دیروز</div>
                                <div class="stat-value" style="color: #EF4444; font-size: 15px;" id="cardPenaltyToman">—</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">حقوق تا دیروز</div>
                                <div class="stat-value" style="color: #8B5CF6; font-size: 15px;" id="cardSalaryToman">—</div>
                            </div>
                            <div class="stat-card">
                                <div style="display:flex;align-items:center;gap:4px;margin-bottom:6px;">
                                    <span class="stat-label" style="margin-bottom:0;">درخواست‌های این ماه</span>
                                    <i class="bi bi-info-circle" id="monthCountInfoIcon" style="font-size:11px;cursor:help;color:#6B7280 !important;"></i>
                                </div>
                                <div class="stat-value" id="cardCounts" style="color:#744CA4;">—</div>
                            </div>
                        </div>
                        <script>
                            const MY_USER_ID = <?php echo (int) $user_id; ?>;
                            const MY_MONTHLY_SALARY = <?php echo (float) ($user['monthly_salary'] ?? 0); ?>;
                        </script>
                    <?php endif; ?>

                    <!-- Controls -->
                    <div class="controls">
                        <div class="search-box">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            <input type="text" id="searchInput" placeholder="جستجو در درخواست‌ها...">
                        </div>
                        <div class="filters">
                            <button class="filter-btn active" onclick="filterByStatus('all')">همه</button>
                            <button class="filter-btn" onclick="filterByStatus('pending')">در انتظار</button>
                            <button class="filter-btn" onclick="filterByStatus('approved')">تایید شده</button>
                            <button class="filter-btn" onclick="filterByStatus('rejected')">رد شده</button>
                        </div>

                        <!-- ✅ چک‌باکس فیلتر ماه جاری -->
                        <label class="current-month-filter">
                            <input type="checkbox" id="currentMonthFilter" onchange="toggleCurrentMonthFilter()" checked>
                            <span class="checkmark"></span>
                            <span class="filter-label">فقط ماه جاری</span>
                        </label>

                        <?php if ($is_admin_role ?? false): ?>
                            <!-- فیلتر کارمند/واحد برای مدیران -->
                            <?php
                            // برچسب فارسیِ واحدها (همهٔ واحدهای سازمان)
                            $__sec_labels = [];
                            if (!empty($org_id)) {
                                try {
                                    $__sl = $db->prepare("SELECT section_key, section_label FROM organization_activity_sections WHERE organization_id = ?");
                                    $__sl->execute([$org_id]);
                                    foreach ($__sl->fetchAll(PDO::FETCH_ASSOC) as $__row) {
                                        $__sec_labels[$__row['section_key']] = $__row['section_label'];
                                    }
                                } catch (Exception $e) {
                                }
                            }

                            // همهٔ کاربران فعالِ سازمان (مثل create-task)
                            $filter_users_list = [];
                            if (!empty($org_id)) {
                                try {
                                    $__uq = $db->prepare("SELECT id, first_name, last_name, activity_section FROM users WHERE organization_id = ? AND is_deleted = 0 AND is_active = 1 ORDER BY first_name, last_name");
                                    $__uq->execute([$org_id]);
                                    foreach ($__uq->fetchAll(PDO::FETCH_ASSOC) as $__u) {
                                        $__name = trim(($__u['first_name'] ?? '') . ' ' . ($__u['last_name'] ?? ''));
                                        if ($__name === '') $__name = 'کاربر ' . $__u['id'];
                                        $filter_users_list[] = [
                                            'id' => (int) $__u['id'],
                                            'full_name' => $__name,
                                            'first_name' => $__name,
                                            'last_name' => '',
                                            'activity_section' => $__u['activity_section'] ?? '',
                                        ];
                                    }
                                } catch (Exception $e) {
                                }
                            }

                            // همهٔ واحدهای سازمان
                            $filter_sections_list = [];
                            foreach ($__sec_labels as $__k => $__l) {
                                $filter_sections_list[] = ['section_key' => $__k, 'section_label' => $__l];
                            }
                            ?>
                            <div id="employeeFilterPicker" style="min-width: 240px;"></div>
                            <script>
                                const FILTER_USERS = <?php echo json_encode($filter_users_list, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                                const FILTER_SECTIONS = <?php echo json_encode($filter_sections_list, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                            </script>
                        <?php endif; ?>

                        <button class="new-request-btn" onclick="openNewRequestModal()">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            درخواست جدید
                        </button>
                    </div>

                    <!-- Table -->
                    <div class="table-container">
                        <div class="table-scroll">
                            <div id="requestsGrid" class="ag-theme-alpine" style="width:100%;height:600px;"></div>
                        </div>

                        <!-- Pagination -->
                        <div class="pagination-container" id="paginationContainer">
                            <!-- JavaScript will populate this -->
                        </div>
                    </div>
                </div><!-- پایان my-requests-section -->

                <!-- بخش درخواست‌های منتظر تأیید من -->
                <div id="pending-approvals-section" style="display: none;">

                    <div class="table-container">
                        <div id="pendingApprovalsGrid" class="ag-theme-alpine" style="width:100%;height:520px;"></div>
                    </div>
                </div><!-- پایان pending-approvals-section -->

            </div>

        </div>

    </div>

    <!-- Modal درخواست -->
    <div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2>درخواست جدید</h2>
                <button class="modal-close" onclick="closeModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <div class="tabs-container">
                <button class="tab-button active" onclick="switchTab(0)">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2h12a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"></path>
                        <path d="M9 7h6M9 13h6M9 19h3"></path>
                    </svg>
                    <span>مأموریت</span>
                </button>
                <button class="tab-button" onclick="switchTab(1)">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <span>مرخصی</span>
                </button>
                <button class="tab-button" onclick="switchTab(2)">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span>پاس</span>
                </button>
                <button class="tab-button" onclick="switchTab(3)">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 6v6l4 2"></path>
                    </svg>
                    <span>فراموشی</span>
                </button>
                <button class="tab-button" onclick="switchTab(4)">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="1"></circle>
                        <path d="M12 1v6m0 6v6"></path>
                        <path d="M4.22 4.22l4.24 4.24m6.08 0l4.24-4.24"></path>
                        <path d="M1 12h6m6 0h6"></path>
                        <path d="M4.22 19.78l4.24-4.24m6.08 0l4.24 4.24"></path>
                    </svg>
                    <span>مشکل فنی</span>
                </button>
            </div>

            <form id="requestForm">
                <div class="modal-body">
                    <!-- تب مأموریت -->
                    <div class="tab-content active">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="required">تاریخ شروع</label>
                                <div class="persian-datepicker-wrapper"
                                    data-restrict-past="<?php echo $app_settings['clickable_days_limit']; ?>">
                                    <input type="text" class="persian-datepicker-input" id="missionStartDate"
                                        placeholder="تاریخ شروع را وارد کنید" readonly>
                                    <div class="persian-datepicker">
                                        <div class="datepicker-header">
                                            <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                            <span class="datepicker-current"></span>
                                            <button type="button" class="datepicker-nav" data-action="next">◄</button>
                                        </div>
                                        <div class="datepicker-weekdays">
                                            <div class="datepicker-weekday">ش</div>
                                            <div class="datepicker-weekday">ی</div>
                                            <div class="datepicker-weekday">د</div>
                                            <div class="datepicker-weekday">س</div>
                                            <div class="datepicker-weekday">چ</div>
                                            <div class="datepicker-weekday">پ</div>
                                            <div class="datepicker-weekday">ج</div>
                                        </div>
                                        <div class="datepicker-days"></div>
                                        <button type="button" class="datepicker-today-btn">امروز</button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="required">تاریخ پایان</label>
                                <div class="persian-datepicker-wrapper"
                                    data-restrict-past="<?php echo $app_settings['clickable_days_limit']; ?>">
                                    <input type="text" class="persian-datepicker-input" id="missionEndDate"
                                        placeholder="تاریخ پایان" readonly disabled>
                                    <div class="persian-datepicker" style="display:none;">
                                        <div class="datepicker-header">
                                            <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                            <span class="datepicker-current"></span>
                                            <button type="button" class="datepicker-nav" data-action="next">◄</button>
                                        </div>
                                        <div class="datepicker-weekdays">
                                            <div class="datepicker-weekday">ش</div>
                                            <div class="datepicker-weekday">ی</div>
                                            <div class="datepicker-weekday">د</div>
                                            <div class="datepicker-weekday">س</div>
                                            <div class="datepicker-weekday">چ</div>
                                            <div class="datepicker-weekday">پ</div>
                                            <div class="datepicker-weekday">ج</div>
                                        </div>
                                        <div class="datepicker-days"></div>
                                        <button type="button" class="datepicker-today-btn">امروز</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="required">ساعت شروع</label>
                                <input type="text" id="missionStartTime" class="time-input-custom"
                                    placeholder="مثلاً ۰۸:۳۰" maxlength="5" required />
                            </div>
                            <div class="form-group">
                                <label class="required">ساعت پایان</label>
                                <input type="text" id="missionEndTime" class="time-input-custom"
                                    placeholder="مثلاً ۰۸:۳۰" maxlength="5" required />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="required">توضیحات</label>
                            <textarea id="missionDesc" required></textarea>
                        </div>
                    </div>

                    <!-- تب مرخصی -->
                    <div class="tab-content">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="required">تاریخ شروع</label>
                                <div class="persian-datepicker-wrapper"
                                    data-restrict-past="<?php echo $app_settings['clickable_days_limit']; ?>">
                                    <input type="text" class="persian-datepicker-input" id="leaveStartDate"
                                        placeholder="تاریخ شروع را وارد کنید" readonly>
                                    <div class="persian-datepicker">
                                        <div class="datepicker-header">
                                            <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                            <span class="datepicker-current"></span>
                                            <button type="button" class="datepicker-nav" data-action="next">◄</button>
                                        </div>
                                        <div class="datepicker-weekdays">
                                            <div class="datepicker-weekday">ش</div>
                                            <div class="datepicker-weekday">ی</div>
                                            <div class="datepicker-weekday">د</div>
                                            <div class="datepicker-weekday">س</div>
                                            <div class="datepicker-weekday">چ</div>
                                            <div class="datepicker-weekday">پ</div>
                                            <div class="datepicker-weekday">ج</div>
                                        </div>
                                        <div class="datepicker-days"></div>
                                        <button type="button" class="datepicker-today-btn">امروز</button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="required">تاریخ پایان</label>
                                <div class="persian-datepicker-wrapper"
                                    data-restrict-past="<?php echo $app_settings['clickable_days_limit']; ?>">
                                    <input type="text" class="persian-datepicker-input" id="leaveEndDate"
                                        placeholder="تاریخ پایان" readonly disabled>
                                    <div class="persian-datepicker" style="display:none;">
                                        <div class="datepicker-header">
                                            <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                            <span class="datepicker-current"></span>
                                            <button type="button" class="datepicker-nav" data-action="next">◄</button>
                                        </div>
                                        <div class="datepicker-weekdays">
                                            <div class="datepicker-weekday">ش</div>
                                            <div class="datepicker-weekday">ی</div>
                                            <div class="datepicker-weekday">د</div>
                                            <div class="datepicker-weekday">س</div>
                                            <div class="datepicker-weekday">چ</div>
                                            <div class="datepicker-weekday">پ</div>
                                            <div class="datepicker-weekday">ج</div>
                                        </div>
                                        <div class="datepicker-days"></div>
                                        <button type="button" class="datepicker-today-btn">امروز</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="required">ساعت شروع</label>
                                <input type="text" id="leaveStartTime" class="time-input-custom"
                                    placeholder="مثلاً ۰۸:۳۰" maxlength="5" required />
                            </div>
                            <div class="form-group">
                                <label class="required">ساعت پایان</label>
                                <input type="text" id="leaveEndTime" class="time-input-custom" placeholder="مثلاً ۰۸:۳۰"
                                    maxlength="5" required />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="required">جانشین</label>
                            <select id="leaveSubstitute" required>
                                <option value="">انتخاب جانشین...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="required">دلیل</label>
                            <textarea id="leaveReason" required></textarea>
                        </div>
                    </div>

                    <!-- تب پاس -->
                    <div class="tab-content">
                        <div class="form-group">
                            <label class="required">تاریخ پاس</label>
                            <div class="persian-datepicker-wrapper"
                                data-restrict-past="<?php echo $app_settings['clickable_days_limit']; ?>">
                                <input type="text" class="persian-datepicker-input" id="passDate"
                                    placeholder="تاریخ پاس را وارد کنید" readonly>
                                <div class="persian-datepicker">
                                    <div class="datepicker-header">
                                        <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                        <span class="datepicker-current"></span>
                                        <button type="button" class="datepicker-nav" data-action="next">◄</button>
                                    </div>
                                    <div class="datepicker-weekdays">
                                        <div class="datepicker-weekday">ش</div>
                                        <div class="datepicker-weekday">ی</div>
                                        <div class="datepicker-weekday">د</div>
                                        <div class="datepicker-weekday">س</div>
                                        <div class="datepicker-weekday">چ</div>
                                        <div class="datepicker-weekday">پ</div>
                                        <div class="datepicker-weekday">ج</div>
                                    </div>
                                    <div class="datepicker-days"></div>
                                    <button type="button" class="datepicker-today-btn">امروز</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="required">ساعت شروع</label>
                                <input type="text" id="passStartTime" class="time-input-custom"
                                    placeholder="مثلاً ۰۸:۳۰" maxlength="5" required />
                            </div>
                            <div class="form-group">
                                <label class="required">ساعت پایان</label>
                                <input type="text" id="passEndTime" class="time-input-custom" placeholder="مثلاً ۰۸:۳۰"
                                    maxlength="5" required />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="required">دلیل</label>
                            <textarea id="passReason" required></textarea>
                        </div>
                    </div>

                    <!-- تب فراموشی -->
                    <div class="tab-content">
                        <div class="form-group">
                            <label class="required">تاریخ</label>
                            <div class="persian-datepicker-wrapper"
                                data-restrict-past="<?php echo $app_settings['clickable_days_limit']; ?>">
                                <input type="text" class="persian-datepicker-input" id="forgetPasswordDate"
                                    placeholder="تاریخ را وارد کنید" readonly>
                                <div class="persian-datepicker">
                                    <div class="datepicker-header">
                                        <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                        <span class="datepicker-current"></span>
                                        <button type="button" class="datepicker-nav" data-action="next">◄</button>
                                    </div>
                                    <div class="datepicker-weekdays">
                                        <div class="datepicker-weekday">ش</div>
                                        <div class="datepicker-weekday">ی</div>
                                        <div class="datepicker-weekday">د</div>
                                        <div class="datepicker-weekday">س</div>
                                        <div class="datepicker-weekday">چ</div>
                                        <div class="datepicker-weekday">پ</div>
                                        <div class="datepicker-weekday">ج</div>
                                    </div>
                                    <div class="datepicker-days"></div>
                                    <button type="button" class="datepicker-today-btn">امروز</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="required">ساعت شروع</label>
                                <input type="text" id="forgetStartTime" class="time-input-custom"
                                    placeholder="مثلاً ۰۸:۳۰" maxlength="5" required />
                            </div>
                            <div class="form-group">
                                <label class="required">ساعت پایان</label>
                                <input type="text" id="forgetEndTime" class="time-input-custom"
                                    placeholder="مثلاً ۰۸:۳۰" maxlength="5" required />
                            </div>
                        </div>
                        <div class="form-group">
                            <label>توضیحات مشکل</label>
                            <textarea id="forgetPasswordDesc" placeholder="توضیح مشکل خود را درج کنید..."
                                required></textarea>
                        </div>
                    </div>

                    <!-- تب مشکل فنی -->
                    <div class="tab-content">
                        <div class="form-group">
                            <label class="required">تاریخ</label>
                            <div class="persian-datepicker-wrapper"
                                data-restrict-past="<?php echo $app_settings['clickable_days_limit']; ?>">
                                <input type="text" class="persian-datepicker-input" id="technicalIssueDate"
                                    placeholder="تاریخ را وارد کنید" readonly>
                                <div class="persian-datepicker">
                                    <div class="datepicker-header">
                                        <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                        <span class="datepicker-current"></span>
                                        <button type="button" class="datepicker-nav" data-action="next">◄</button>
                                    </div>
                                    <div class="datepicker-weekdays">
                                        <div class="datepicker-weekday">ش</div>
                                        <div class="datepicker-weekday">ی</div>
                                        <div class="datepicker-weekday">د</div>
                                        <div class="datepicker-weekday">س</div>
                                        <div class="datepicker-weekday">چ</div>
                                        <div class="datepicker-weekday">پ</div>
                                        <div class="datepicker-weekday">ج</div>
                                    </div>
                                    <div class="datepicker-days"></div>
                                    <button type="button" class="datepicker-today-btn">امروز</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="required">ساعت شروع</label>
                                <input type="text" id="technicalStartTime" class="time-input-custom"
                                    placeholder="مثلاً ۰۸:۳۰" maxlength="5" required />
                            </div>
                            <div class="form-group">
                                <label class="required">ساعت پایان</label>
                                <input type="text" id="technicalEndTime" class="time-input-custom"
                                    placeholder="مثلاً ۰۸:۳۰" maxlength="5" required />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="required">توضیح مشکل</label>
                            <textarea id="technicalIssueDesc" placeholder="توضیح دقیق مشکل را درج کنید..."
                                required></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">انصراف</button>
                    <button type="button" class="btn-submit" id="submitBtn" onclick="submitRequest(event)">
                        ارسال درخواست
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../assets/js/cdn/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/assignee-picker.js"></script>

    <script>
        (function() {
            const farsi = s => convertToFarsiNumber(String(s ?? ''));
            const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

            function statusBadge(d) {
                const tl = `onclick="showTimeline(${d.id}, '${d.type}', event)"`;
                if (d.status === 'pending')
                    return `<span class="status-badge status-with-timeline" style="color:#F59E0B;" ${tl}>${esc(d.status_label)}<i class="bi bi-clock-history timeline-icon"></i></span>`;
                if (d.status === 'rejected') {
                    const rr = d.reject_reason ? `<i class="bi bi-chat-dots reject-reason-icon" data-tooltip="${esc(d.reject_reason)}"></i>` : '';
                    return `<span class="status-badge status-with-timeline" style="color:#EF4444;" ${tl}>رد شده ${rr}</span>`;
                }
                if (d.status === 'approved')
                    return `<span class="status-badge status-with-timeline" style="color:#10B981;" ${tl}>تأیید شده</span>`;
                return `<span class="status-badge" style="color:#6b8dcf;">تأیید خودکار</span>`;
            }

            function actionsCell(d) {
                let h = '';
                if (d.can_edit) h += `<button class="action-icon-btn edit-btn" onclick="editRequest(${d.id}, '${d.type}')" title="ویرایش"><i class="bi bi-pencil"></i></button>`;
                if (d.can_delete) h += `<button class="action-icon-btn delete-btn" onclick="deleteRequest(${d.id}, '${d.type}')" title="حذف"><i class="bi bi-trash"></i></button>`;
                if (!d.can_edit && !d.can_delete) return '<span class="no-action">—</span>';
                return `<div style="display:flex;gap:6px;justify-content:center;align-items:center;height:100%;">${h}</div>`;
            }

            function reqFilterPass(d) {
                const activeBtn = document.querySelector('.filter-btn.active');
                const statusFilter = activeBtn ? (activeBtn.textContent.includes('همه') ? 'all' : activeBtn.textContent.includes('انتظار') ? 'pending' : activeBtn.textContent.includes('تایید') ? 'approved' : 'rejected') : 'all';
                if (statusFilter !== 'all' && d.status !== statusFilter) return false;

                const ef = window.__empFilter;
                if (ef && ef.value) {
                    if (ef.type === 'section') {
                        if ((d.user_section || '') !== ef.value) return false;
                    } else if (String(d.user_id) !== String(ef.value)) return false;
                }

                if (document.getElementById('currentMonthFilter') && document.getElementById('currentMonthFilter').checked && d.date_greg) {
                    const t = new Date();
                    const j = gregorianToJalaliJS(t.getFullYear(), t.getMonth() + 1, t.getDate());
                    const g = jalaliToGregorianJS(j[0], j[1], 1);
                    if (new Date(d.date_greg) < new Date(g[0], g[1] - 1, g[2])) return false;
                }

                const si = document.getElementById('searchInput');
                const term = (si ? si.value : '').trim().toLowerCase();
                if (term) {
                    const hay = (d.request_code + ' ' + d.type_label + ' ' + (d.user_full_name || '') + ' ' + (d.description || '')).toLowerCase();
                    if (!hay.includes(term)) return false;
                }
                return true;
            }

            function buildRequestsGrid() {
                const el = document.getElementById('requestsGrid');
                if (!el || typeof agGrid === 'undefined') return;

                const cols = [{
                        headerName: 'کد',
                        field: 'request_code',
                        width: 120,
                        cellRenderer: p => farsi(p.value || '—')
                    },
                    {
                        headerName: 'نوع',
                        field: 'type_label',
                        width: 100
                    }
                ];
                if (IS_ADMIN_ROLE) cols.push({
                    headerName: 'کارمند',
                    field: 'user_full_name',
                    flex: 1,
                    minWidth: 120
                });
                cols.push({
                    headerName: 'تاریخ',
                    field: 'date_jalali',
                    width: 120,
                    cellRenderer: p => farsi(p.value || '—')
                }, {
                    headerName: 'شروع',
                    field: 'start_time',
                    width: 80,
                    cellRenderer: p => p.value ? farsi(p.value) : '—'
                }, {
                    headerName: 'پایان',
                    field: 'end_time',
                    width: 80,
                    cellRenderer: p => p.value ? farsi(p.value) : '—'
                }, {
                    headerName: 'توضیحات',
                    field: 'description',
                    flex: 1,
                    minWidth: 140,
                    tooltipField: 'description',
                    cellRenderer: p => p.value ? esc(p.value) : '—'
                }, {
                    headerName: 'وضعیت',
                    width: 175,
                    sortable: false,
                    cellRenderer: p => statusBadge(p.data)
                }, {
                    headerName: 'تاریخ ایجاد',
                    field: 'created_jalali',
                    width: 150,
                    cellRenderer: p => farsi(p.value || '—')
                }, {
                    headerName: 'عملیات',
                    width: 120,
                    sortable: false,
                    cellClass: 'req-actions-cell',
                    cellRenderer: p => actionsCell(p.data)
                });

                window.__reqGridApi = agGrid.createGrid(el, {
                    enableRtl: true,
                    rowHeight: 46,
                    headerHeight: 44,
                    pagination: true,
                    paginationPageSize: 10,
                    enableBrowserTooltips: true,
                    onPaginationChanged: () => persianizePaging(),
                    columnDefs: cols,
                    rowData: (typeof REQUESTS_DATA !== 'undefined' ? REQUESTS_DATA : []),
                    isExternalFilterPresent: () => true,
                    doesExternalFilterPass: node => reqFilterPass(node.data),
                    overlayNoRowsTemplate: '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem;color:#A0AEC0;"><div style="font-size:48px;margin-bottom:12px;">📭</div><div style="font-size:16px;font-weight:600;color:#718096;">هیچ درخواستی موجود نیست</div></div>'
                });
                window.__reqGridApi.onFilterChanged();
            }

            // وصل‌کردن فیلترهای فعلی به گرید (بازنویسی توابع قدیمی)
            applyAllFilters = function() {
                if (window.__reqGridApi) window.__reqGridApi.onFilterChanged();
            };
            initPagination = function() {
                /* صفحه‌بندی داخلیِ گرید */
            };

            document.addEventListener('DOMContentLoaded', function() {
                buildRequestsGrid();
                // پیکر سرچ‌دار کاربر/واحد
                const pc = document.getElementById('employeeFilterPicker');
                if (pc && typeof AssigneePicker !== 'undefined' && typeof FILTER_USERS !== 'undefined') {
                    const secMap = {};
                    (typeof FILTER_SECTIONS !== 'undefined' ? FILTER_SECTIONS : []).forEach(s => secMap[s.section_key] = s.section_label);
                    AssigneePicker.create({
                        container: '#employeeFilterPicker',
                        users: FILTER_USERS,
                        sections: (typeof FILTER_SECTIONS !== 'undefined' ? FILTER_SECTIONS : []),
                        sectionMap: secMap,
                        showSections: true,
                        placeholder: 'فیلتر بر اساس کاربر یا واحد...',
                        onSelect: (type, value) => {
                            window.__empFilter = (value && value !== '__all__' && value !== '__all_users__') ? {
                                type: type,
                                value: value
                            } : null;
                            if (window.__reqGridApi) window.__reqGridApi.onFilterChanged();
                        }
                    });
                }
                const s = document.getElementById('searchInput');
                if (s) s.addEventListener('input', () => {
                    if (window.__reqGridApi) window.__reqGridApi.onFilterChanged();
                });
            });
        })();
    </script>
    <script src="/assets/js/alert.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/js/alert.js') ?>"></script>

    <script>
        (async function checkAuth() {
            const authToken = localStorage.getItem('auth_token');

            if (!authToken) {
                window.location.href = '/index.php';
                return;
            }

            // ✅ Set کردن Cookie برای PHP
            document.cookie = 'auth_token=' + authToken + '; path=/; max-age=86400';

            try {
                const response = await fetch('/api/auth/profile.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                const data = await response.json();

                if (!data.success) {
                    localStorage.removeItem('auth_token');
                    localStorage.removeItem('user_info');
                    document.cookie = 'auth_token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC';
                    window.location.href = '/index.php';
                }
            } catch (error) {
                console.error('Auth check error:', error);
                window.location.href = '/index.php';
            }
        })();

        // ============================================
        // ماسک ساعت ۲۴ ساعته (HH:MM)
        // ============================================
        // ============================================
        // ماسک ساعت ۲۴ ساعته (HH:MM)
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.time-input-custom').forEach(input => {

                input.addEventListener('input', function(e) {
                    let value = this.value;

                    // تبدیل اعداد فارسی به انگلیسی
                    const persianNums = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                    persianNums.forEach((p, i) => {
                        value = value.replace(new RegExp(p, 'g'), i.toString());
                    });

                    // فقط عدد و : مجاز
                    value = value.replace(/[^0-9:]/g, '');

                    // حذف : های اضافی
                    const colonCount = (value.match(/:/g) || []).length;
                    if (colonCount > 1) {
                        const firstColon = value.indexOf(':');
                        value = value.substring(0, firstColon + 1) + value.substring(firstColon + 1).replace(/:/g, '');
                    }

                    // اضافه کردن خودکار : بعد از ۲ رقم اول
                    if (value.length === 2 && !value.includes(':') && !e.inputType?.includes('delete')) {
                        value = value + ':';
                    }

                    // محدود به 5 کاراکتر
                    if (value.length > 5) {
                        value = value.substring(0, 5);
                    }

                    this.value = value;
                });

                // اعتبارسنجی هنگام خروج از فیلد
                input.addEventListener('blur', function() {
                    let value = this.value.trim();
                    if (!value) return;

                    // تبدیل فارسی به انگلیسی
                    const persianNums = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                    persianNums.forEach((p, i) => {
                        value = value.replace(new RegExp(p, 'g'), i.toString());
                    });

                    // اگر فقط عدد بدون : وارد شده
                    if (!value.includes(':')) {
                        if (value.length <= 2) {
                            value = value.padStart(2, '0') + ':00';
                        } else if (value.length === 3) {
                            value = '0' + value[0] + ':' + value[1] + value[2];
                        } else if (value.length === 4) {
                            value = value[0] + value[1] + ':' + value[2] + value[3];
                        }
                    }

                    const parts = value.split(':');
                    if (parts.length !== 2) {
                        this.value = '';
                        return;
                    }

                    let hours = parseInt(parts[0], 10);
                    let minutes = parseInt(parts[1], 10);

                    if (isNaN(hours) || isNaN(minutes) || hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
                        this.value = '';
                        this.style.borderColor = '#EF4444';
                        setTimeout(() => this.style.borderColor = '', 2000);
                        alert('ساعت نامعتبر است. فرمت صحیح: ۰۰:۰۰ تا ۲۳:۵۹');
                        return;
                    }

                    // فرمت نهایی — بدون هیچ تبدیلی
                    this.value = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
                });
            });
        });
        // ============================================
        // توابع کمکی
        // ============================================
        // تابع تبدیل اعداد انگلیسی به فارسی (مشابه PHP)
        function englishToFarsiNumber(num) {
            if (num === null || num === undefined || num === '') return '';

            const str = num.toString();
            const english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            const persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

            let result = str;
            for (let i = 0; i < english.length; i++) {
                const regex = new RegExp(english[i], 'g');
                result = result.replace(regex, persian[i]);
            }
            return result;
        }

        // تابع کامل برای تبدیل اعداد در رشته‌های پیچیده
        function convertToFarsiNumber(input) {
            if (!input && input !== 0) return '';

            const str = input.toString();

            // اگر شامل : است (زمان)
            if (str.includes(':')) {
                return str.split(':').map(part => englishToFarsiNumber(part)).join(':');
            }

            // اگر شامل / است (تاریخ)
            if (str.includes('/')) {
                return str.split('/').map(part => englishToFarsiNumber(part)).join('/');
            }

            // اگر شامل . است (اعداد اعشاری)
            if (str.includes('.')) {
                return str.split('.').map(part => englishToFarsiNumber(part)).join('.');
            }

            // عدد ساده
            return englishToFarsiNumber(str);
        }

        // تابع formatNumber برای اعداد با جداکننده هزارگان
        function formatNumber(num) {
            if (!num && num !== 0) return '۰';

            const numValue = parseFloat(num);
            if (isNaN(numValue)) return '۰';

            // ابتدا عدد را فرمت کن
            const formatted = numValue.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");

            // سپس به فارسی تبدیل کن
            return convertToFarsiNumber(formatted);
        }

        function getMonthName(monthNum) {
            const months = [
                'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
            ];
            return months[parseInt(monthNum) - 1] || '';
        }

        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // ============================================
        // Pagination
        // ============================================
        let currentPage = 1;
        const itemsPerPage = 13;
        let filteredRows = [];

        function initPagination() {
            filteredRows = Array.from(document.querySelectorAll('.request-row')).filter(row => row.style.display !== 'none');
            const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
            currentPage = 1;
            renderPagination(totalPages);
            showPage(currentPage);
        }

        function renderPagination(totalPages) {
            const container = document.getElementById('paginationContainer');
            if (totalPages <= 1) {
                container.style.display = 'none';
                return;
            }
            container.style.display = 'flex';

            let html = '';

            // دکمه قبلی
            html += `<button class="pagination-btn" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>قبلی</button>`;

            // شماره صفحات
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    html += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${convertToFarsiNumber(i)}</button>`;
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    html += `<span style="padding: 0 8px; color: #94A3B8;">...</span>`;
                }
            }

            // دکمه بعدی
            html += `<button class="pagination-btn" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>بعدی</button>`;

            container.innerHTML = html;
        }

        function changePage(page) {
            const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
            if (page < 1 || page > totalPages) return;

            currentPage = page;
            showPage(page);
            renderPagination(totalPages);
        }

        function showPage(page) {
            const start = (page - 1) * itemsPerPage;
            const end = start + itemsPerPage;

            filteredRows.forEach((row, index) => {
                row.style.display = (index >= start && index < end) ? '' : 'none';
            });
        }


        // ============================================
        // بارگذاری گزارش ماه جاری (از اول ماه تا امروز)
        // ============================================
        // ============================================
        // بارگذاری گزارش ماه جاری (از اول ماه تا امروز)
        // ============================================
        // اضافه کردن این توابع به بخش JavaScript در requests.php

        // تابع بررسی اینکه آیا برای بازه کسری درخواست تأیید شده وجود دارد
        function hasApprovedRequestForSlot(slotStart, slotEnd, dayRequests) {
            if (!dayRequests || dayRequests.length === 0) {
                return false;
            }

            for (const req of dayRequests) {
                // فقط درخواست‌های approved (پاس همیشه approved است)
                const isApproved = req.status === 'approved' || req.type === 'pass';

                if (isApproved) {
                    const reqStart = req.start_time ? req.start_time.substring(0, 5) : '00:00';
                    const reqEnd = req.end_time ? req.end_time.substring(0, 5) : '23:59';

                    // بررسی همپوشانی
                    if (calculateOverlapMinutes(reqStart, reqEnd, slotStart, slotEnd) > 0) {
                        return true;
                    }
                }
            }

            return false;
        }

        // تابع محاسبه همپوشانی (مشابه PHP)
        function calculateOverlapMinutes(start1, end1, start2, end2) {
            function timeToMinutes(time) {
                if (!time) return 0;
                const parts = time.split(':');
                const hours = parseInt(parts[0]) || 0;
                const minutes = parseInt(parts[1]) || 0;
                return (hours * 60) + minutes;
            }

            const s1 = timeToMinutes(start1);
            const e1 = timeToMinutes(end1);
            const s2 = timeToMinutes(start2);
            const e2 = timeToMinutes(end2);

            // اگر همپوشانی ندارند
            if (s1 >= e2 || s2 >= e1) {
                return 0;
            }

            // محاسبه همپوشانی
            const overlap_start = Math.max(s1, s2);
            const overlap_end = Math.min(e1, e2);

            return Math.max(0, overlap_end - overlap_start);
        }

        // تابع محاسبه کسری نهایی و مبلغ ریالی
        function calculateFinalShortage(day, monthlySalary, dailyWorkHours) {
            if (day.is_holiday || day.shortage_minutes <= 0) {
                return {
                    finalShortageMinutes: 0,
                    finalShortageHours: 0,
                    finalShortageHMS: '۰:۰۰',
                    shortageMoney: 0,
                    shortageMoneyFormatted: '۰ ریال',
                    multiplier: 1
                };
            }

            // 🔴 پیدا کردن بازه‌های کسری (شبیه PHP)
            const shortageSlots = [];

            // برای سادگی، کل بازه کسری را یکجا در نظر می‌گیریم
            // در واقعیت باید مثل PHP بازه‌ها را استخراج کنیم
            // اما برای سادگی فرض می‌کنیم یک بازه کلی داریم
            shortageSlots.push({
                start: '08:00', // زمان شروع شیفت فرضی
                end: '17:00', // زمان پایان شیفت فرضی
                minutes: day.shortage_minutes
            });

            let finalMinutesWithMultiplier = 0;

            // بررسی هر بازه کسری
            shortageSlots.forEach(slot => {
                const hasApproved = hasApprovedRequestForSlot(slot.start, slot.end, day.requests);
                const multiplier = hasApproved ? 1 : 2;
                finalMinutesWithMultiplier += (slot.minutes * multiplier);
            });

            const finalShortageHours = finalMinutesWithMultiplier / 60;

            // محاسبه مبلغ ریالی
            const hourlySalary = monthlySalary / 30 / dailyWorkHours;
            const minuteSalary = hourlySalary / 60;
            const shortageMoney = Math.round(minuteSalary * finalMinutesWithMultiplier);

            return {
                finalShortageMinutes: finalMinutesWithMultiplier,
                finalShortageHours: finalShortageHours,
                finalShortageHMS: decimalToHourMinute(finalShortageHours),
                shortageMoney: shortageMoney,
                shortageMoneyFormatted: 'ریال' + formatNumber(shortageMoney),
                multiplier: finalMinutesWithMultiplier > day.shortage_minutes ? 2 : 1
            };
        }

        // تابع تبدیل اعشار به ساعت:دقیقه
        function decimalToHourMinute(decimal_hours) {
            if (!decimal_hours || decimal_hours < 0) {
                return '۰:۰۰';
            }

            if (decimal_hours == 0) {
                return '۰:۰۰';
            }

            const hours = Math.floor(decimal_hours);
            const minutes = Math.round((decimal_hours - hours) * 60);

            // اگر دقیقه ۶۰ شد
            let finalHours = hours;
            let finalMinutes = minutes;
            if (minutes >= 60) {
                finalHours += 1;
                finalMinutes = 0;
            }

            // تبدیل به فارسی
            const persianHours = convertToFarsiNumber(finalHours);
            const persianMinutes = convertToFarsiNumber(finalMinutes.toString().padStart(2, '0'));

            return persianHours + ':' + persianMinutes;
        }

        // تابع formatNumber برای اعداد با جداکننده هزارگان
        function formatNumber(num) {
            if (!num && num !== 0) return '۰';

            const numValue = parseFloat(num);
            if (isNaN(numValue)) return '۰';

            // ابتدا عدد را فرمت کن
            const formatted = numValue.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");

            // سپس به فارسی تبدیل کن
            return convertToFarsiNumber(formatted);
        }

        // در تابع loadMonthlyAttendance()، بخش نمایش جدول را اصلاح کنید:
        function getRequestBadges(validRequests) {
            if (!validRequests || validRequests.length === 0) {
                return '<span class="time-empty">—</span>';
            }

            const typeInfo = {
                'pass': {
                    icon: 'bi-person-walking',
                    label: 'پاس',
                    color: 'pass'
                },
                'mission': {
                    icon: 'bi-briefcase',
                    label: 'مأموریت',
                    color: 'mission'
                },
                'leave': {
                    icon: 'bi-house',
                    label: 'مرخصی',
                    color: 'leave'
                },
                'forget': {
                    icon: 'bi-clock-history',
                    label: 'فراموشی',
                    color: 'forget'
                },
                'technical': {
                    icon: 'bi-wrench',
                    label: 'فنی',
                    color: 'technical'
                }
            };

            let badges = '';
            validRequests.forEach(req => {
                const info = typeInfo[req.type] || {
                    icon: 'bi-question',
                    label: '?',
                    color: ''
                };
                const startTime = req.start_time ? req.start_time.substring(0, 5) : '';
                const endTime = req.end_time ? req.end_time.substring(0, 5) : '';

                // محاسبه مدت زمان
                let durationText = '';
                if (startTime && endTime) {
                    const startMinutes = parseInt(startTime.split(':')[0]) * 60 + parseInt(startTime.split(':')[1]);
                    const endMinutes = parseInt(endTime.split(':')[0]) * 60 + parseInt(endTime.split(':')[1]);
                    const durationMinutes = endMinutes - startMinutes;
                    if (durationMinutes > 0) {
                        const hours = Math.floor(durationMinutes / 60);
                        const mins = durationMinutes % 60;
                        if (hours > 0 && mins > 0) {
                            durationText = convertToFarsiNumber(hours) + ':' + convertToFarsiNumber(mins);
                        } else if (hours > 0) {
                            durationText = convertToFarsiNumber(hours) + ' ساعت';
                        } else {
                            durationText = convertToFarsiNumber(mins) + ' دقیقه';
                        }
                    }
                }

                badges += `<span class="request-badge ${info.color}" title="${info.label}: ${startTime} تا ${endTime}">
            <i class="bi ${info.icon}"></i>
            ${durationText}
        </span>`;
            });

            return badges;
        }

        // ============================================
        // ✅ تابع اصلی بارگذاری گزارش ماهانه
        // ============================================
        async function loadMonthlyAttendance() {
            const container = document.getElementById('attendanceTableContainer');

            try {
                const authToken = localStorage.getItem('auth_token');

                if (!authToken) {
                    console.error('❌ No auth token found');
                    container.innerHTML = '<div class="attendance-loading"><i class="bi bi-exclamation-triangle"></i><div class="mt-2">لطفاً مجدداً وارد شوید</div></div>';
                    return;
                }

                // Set کردن Cookie برای PHP
                document.cookie = 'auth_token=' + authToken + '; path=/; max-age=86400';

                const response = await fetch('/api/attendance/monthly-report.php?t=' + Date.now(), {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                const responseText = await response.text();
                let data;

                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('❌ JSON Parse Error:', parseError);
                    console.error('Response:', responseText);
                    container.innerHTML = '<div class="attendance-loading"><i class="bi bi-exclamation-triangle"></i><div class="mt-2">خطا در فرمت داده‌ها</div></div>';
                    return;
                }

                if (data.success) {
                    console.log('✅ API Success - Days count:', data.days?.length || 0);
                    console.log('✅ Debug pass count:', data.debug_pass_count);

                    if (!data.days || data.days.length === 0) {
                        container.innerHTML = `<div class="attendance-loading"><i class="bi bi-calendar-x"></i><div class="mt-2">داده‌ای برای نمایش وجود ندارد</div></div>`;
                        return;
                    }


                    // ساخت گرید ورود/خروج
                    const todayStr2 = todayLocal();
                    const clickableLimit = APP_SETTINGS.clickable_days_limit;
                    const shiftCount = data.shift_count;

                    // محاسبهٔ قابلیت کلیک هر روز
                    data.days.forEach(day => {
                        let cc = false;
                        if (day.is_holiday) {
                            cc = false;
                        } else if (day.date === todayStr2) {
                            cc = true;
                        } else if (day.date > todayStr2) {
                            cc = true; // روزهای آینده
                        } else {
                            let wb = 0;
                            for (let i = 0; i < data.days.length; i++) {
                                const d = data.days[i];
                                if (d.date > day.date && d.date <= todayStr2 && !d.is_holiday) wb++;
                            }
                            cc = (wb <= clickableLimit);
                        }
                        day._canClick = cc;
                    });

                    const dateRenderer = p => {
                        const parts = (p.data.jalali_date || '').split('/');
                        return '<span class="att-date-num">' + convertToFarsiNumber(parts[2] || '') + ' ' + getMonthName(parts[1] || '') + '</span>';
                    };
                    const inOutRenderer = (p, which) => {
                        if (p.data.is_holiday) return '<span class="att-off">تعطیل</span>';
                        const a = which === 'in' ? p.data.shift1_in : p.data.shift1_out;
                        const b = which === 'in' ? p.data.shift2_in : p.data.shift2_out;
                        // اگر شیفت/ورودِ دوم وجود دارد یا کاربر دوشیفته است → هر دو زیرِ هم
                        if (shiftCount >= 2 || b) {
                            return '<span class="shift-time">' + (a ? convertToFarsiNumber(a) : '') + '</span>' +
                                '<span class="shift-time shift-2">' + (b ? convertToFarsiNumber(b) : '') + '</span>';
                        }
                        return a ? '<span class="shift-time">' + convertToFarsiNumber(a) + '</span>' : '<span class="time-empty"></span>';
                    };

                    const columnDefs = [{
                            headerName: 'روز',
                            field: 'day_name',
                            width: 90,
                            cellRenderer: p => '<span class="day-name">' + (p.value || '') + '</span>'
                        },
                        {
                            headerName: 'تاریخ',
                            width: 120,
                            cellRenderer: dateRenderer
                        },
                        {
                            headerName: 'ورود',
                            width: 110,
                            cellRenderer: p => inOutRenderer(p, 'in')
                        },
                        {
                            headerName: 'خروج',
                            width: 110,
                            cellRenderer: p => inOutRenderer(p, 'out')
                        },
                        {
                            headerName: 'کسری اولیه',
                            width: 110,
                            cellRenderer: p => p.data.is_holiday ? '—' : convertToFarsiNumber(p.data.shortage_hms || '۰:۰۰')
                        },
                        {
                            headerName: 'درخواست‌ها',
                            flex: 1,
                            minWidth: 140,
                            cellRenderer: p => p.data.is_holiday ? '—' : getRequestBadges(p.data.valid_requests)
                        },
                        {
                            headerName: 'کسری نهایی',
                            width: 110,
                            cellRenderer: p => p.data.is_holiday ? '—' : convertToFarsiNumber(p.data.final_shortage_hms || '۰:۰۰')
                        },
                        {
                            headerName: 'ریالی',
                            width: 130,
                            cellRenderer: p => p.data.is_holiday ? '—' : (formatNumber(p.data.shortage_money) + ' ریال')
                        }
                    ];

                    const gridOptions = {
                        enableRtl: true,
                        rowHeight: shiftCount >= 2 ? 68 : 46,
                        headerHeight: 44,
                        suppressRowHoverHighlight: true,
                        columnDefs: columnDefs,
                        rowData: data.days,
                        getRowClass: p => p.data.is_holiday ? 'att-holiday' : (p.data._canClick ? 'att-clickable' : 'att-disabled'),
                        onRowClicked: e => {
                            if (!e.data.is_holiday && e.data._canClick) {
                                openModalWithDate(e.data.date, e.data.jalali_date);
                            }
                        },
                        overlayNoRowsTemplate: '<div style="padding:2rem;color:#9097a6;">داده‌ای برای نمایش وجود ندارد</div>'
                    };

                    container.innerHTML = '<div id="attendanceGrid" class="ag-theme-alpine" style="width:100%;height:700px;"></div>';
                    if (window.attendanceGridApi) {
                        try {
                            window.attendanceGridApi.destroy();
                        } catch (e) {}
                    }
                    window.attendanceGridApi = agGrid.createGrid(document.getElementById('attendanceGrid'), gridOptions);

                    // اسکرول خودکار به امروز
                    const todayIdx = data.days.findIndex(d => d.date === todayStr2);
                    if (todayIdx >= 0) {
                        setTimeout(() => {
                            try {
                                window.attendanceGridApi.ensureIndexVisible(todayIdx, 'middle');
                            } catch (e) {}
                        }, 60);
                    }

                } else {
                    container.innerHTML = `
                <div class="attendance-loading" style="color: #dc2626;">
                    <i class="bi bi-exclamation-triangle"></i>
                    <div class="mt-2">${data.message || 'خطا در دریافت داده‌ها'}</div>
                </div>`;
                }

            } catch (error) {
                console.error('❌ Network/Other Error:', error);
                container.innerHTML = `
            <div class="attendance-loading">
                <i class="bi bi-exclamation-triangle"></i>
                <div class="mt-2">خطا در ارتباط با سرور</div>
            </div>`;
            }
        }
        // تابع بررسی اینکه آیا برای بازه کسری درخواست تأیید شده وجود دارد
        function hasApprovedRequestForSlot(slotStart, slotEnd, dayRequests) {
            if (!dayRequests || dayRequests.length === 0) {
                return false;
            }

            for (const req of dayRequests) {
                // فقط درخواست‌های approved (پاس همیشه approved است)
                const isApproved = req.status === 'approved' || req.type === 'pass';

                if (isApproved) {
                    const reqStart = req.start_time ? req.start_time.substring(0, 5) : '00:00';
                    const reqEnd = req.end_time ? req.end_time.substring(0, 5) : '23:59';

                    // بررسی همپوشانی
                    if (calculateOverlapMinutes(reqStart, reqEnd, slotStart, slotEnd) > 0) {
                        return true;
                    }
                }
            }

            return false;
        }
        // تابع تبدیل اعشار به ساعت:دقیقه
        function decimalToHourMinute(decimal_hours) {
            if (!decimal_hours || decimal_hours < 0) {
                return '۰:۰۰';
            }

            if (decimal_hours == 0) {
                return '۰:۰۰';
            }

            const hours = Math.floor(decimal_hours);
            const minutes = Math.round((decimal_hours - hours) * 60);

            // اگر دقیقه ۶۰ شد
            let finalHours = hours;
            let finalMinutes = minutes;
            if (minutes >= 60) {
                finalHours += 1;
                finalMinutes = 0;
            }

            // تبدیل به فارسی
            const persianHours = convertToFarsiNumber(finalHours);
            const persianMinutes = convertToFarsiNumber(finalMinutes.toString().padStart(2, '0'));

            return persianHours + ':' + persianMinutes;
        }
        // تابع محاسبه کسری نهایی و مبلغ ریالی
        function calculateFinalShortage(day, monthlySalary, dailyWorkHours) {
            if (day.is_holiday || day.shortage_minutes <= 0) {
                return {
                    finalShortageMinutes: 0,
                    finalShortageHours: 0,
                    finalShortageHMS: '۰:۰۰',
                    shortageMoney: 0,
                    shortageMoneyFormatted: '۰ ریال',
                    multiplier: 1
                };
            }

            // 🔴 پیدا کردن بازه‌های کسری (شبیه PHP)
            const shortageSlots = [];

            // برای سادگی، کل بازه کسری را یکجا در نظر می‌گیریم
            // در واقعیت باید مثل PHP بازه‌ها را استخراج کنیم
            // اما برای سادگی فرض می‌کنیم یک بازه کلی داریم
            shortageSlots.push({
                start: '08:00', // زمان شروع شیفت فرضی
                end: '17:00', // زمان پایان شیفت فرضی
                minutes: day.shortage_minutes
            });

            let finalMinutesWithMultiplier = 0;

            // بررسی هر بازه کسری
            shortageSlots.forEach(slot => {
                const hasApproved = hasApprovedRequestForSlot(slot.start, slot.end, day.requests);
                const multiplier = hasApproved ? 1 : APP_SETTINGS.shortage_multiplier;
                finalMinutesWithMultiplier += (slot.minutes * multiplier);
            });

            const finalShortageHours = finalMinutesWithMultiplier / 60;

            // محاسبه مبلغ ریالی
            const hourlySalary = monthlySalary / APP_SETTINGS.working_days_per_month / dailyWorkHours;
            const minuteSalary = hourlySalary / 60;
            const shortageMoney = Math.round(minuteSalary * finalMinutesWithMultiplier);

            return {
                finalShortageMinutes: finalMinutesWithMultiplier,
                finalShortageHours: finalShortageHours,
                finalShortageHMS: decimalToHourMinute(finalShortageHours),
                shortageMoney: shortageMoney,
                shortageMoneyFormatted: formatNumber(shortageMoney) + ' ریال',
                multiplier: finalMinutesWithMultiplier > day.shortage_minutes ? 2 : 1
            };
        }
        // تابع محاسبه همپوشانی (مشابه PHP)
        function calculateOverlapMinutes(start1, end1, start2, end2) {
            function timeToMinutes(time) {
                if (!time) return 0;
                const parts = time.split(':');
                const hours = parseInt(parts[0]) || 0;
                const minutes = parseInt(parts[1]) || 0;
                return (hours * 60) + minutes;
            }

            const s1 = timeToMinutes(start1);
            const e1 = timeToMinutes(end1);
            const s2 = timeToMinutes(start2);
            const e2 = timeToMinutes(end2);

            // اگر همپوشانی ندارند
            if (s1 >= e2 || s2 >= e1) {
                return 0;
            }

            // محاسبه همپوشانی
            const overlap_start = Math.max(s1, s2);
            const overlap_end = Math.min(e1, e2);

            return Math.max(0, overlap_end - overlap_start);
        }
        // ============================================
        // باز کردن Modal با تاریخ انتخابی
        // ============================================
        function openModalWithDate(gregorianDate, jalaliDate) {
            // باز کردن modal با تب مأموریت
            switchTab(0);
            document.getElementById('modalOverlay').classList.add('show');

            // Set کردن تاریخ در تمام تب‌ها
            const allDateInputs = [
                'missionStartDate',
                'leaveStartDate',
                'passDate',
                'forgetPasswordDate',
                'technicalIssueDate'
            ];

            allDateInputs.forEach(inputId => {
                const input = document.getElementById(inputId);
                if (input) {
                    input.value = jalaliDate;
                    input.setAttribute('data-date', gregorianDate);
                }
            });

            // Set کردن تاریخ پایان (برای مأموریت و مرخصی)
            const missionEnd = document.getElementById('missionEndDate');
            const leaveEnd = document.getElementById('leaveEndDate');

            if (missionEnd) {
                missionEnd.value = jalaliDate;
                missionEnd.setAttribute('data-date', gregorianDate);
            }

            if (leaveEnd) {
                leaveEnd.value = jalaliDate;
                leaveEnd.setAttribute('data-date', gregorianDate);
            }
        }

        // ============================================
        // توابع Modal
        // ============================================
        let currentTab = 0;

        function switchTab(tabIndex) {
            currentTab = tabIndex;
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach((btn, index) => {
                btn.classList.toggle('active', index === tabIndex);
            });

            tabContents.forEach((content, index) => {
                content.classList.toggle('active', index === tabIndex);
            });
        }

        function openNewRequestModal() {
            currentTab = 0;
            switchTab(0);
            document.getElementById('modalOverlay').classList.add('show');
            document.getElementById('requestForm').reset();

            // پاک کردن تاریخ‌های پایان
            ['missionEndDate', 'leaveEndDate'].forEach(id => {
                const input = document.getElementById(id);
                if (input) {
                    input.value = '';
                    input.removeAttribute('data-date');
                }
            });
        }

        function closeModal(e) {
            if (e && e.target.id !== 'modalOverlay') return;
            document.getElementById('modalOverlay').classList.remove('show');

            // پاک کردن حالت ویرایش
            const form = document.getElementById('requestForm');
            form.removeAttribute('data-edit-id');
            form.removeAttribute('data-edit-type');

            // برگرداندن متن دکمه
            document.getElementById('submitBtn').textContent = 'ارسال درخواست';
            document.getElementById('submitBtn').disabled = false;

            // reset فرم
            form.reset();
        }
        // ✅ چک Authentication با JWT Token
        // ============================================
        (async function checkAuth() {
            const authToken = localStorage.getItem('auth_token');

            if (!authToken) {
                window.location.href = '/index.php';
                return;
            }

            try {
                const response = await fetch('/api/auth/profile.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                const data = await response.json();

                if (!data.success) {
                    localStorage.removeItem('auth_token');
                    localStorage.removeItem('user_info');
                    window.location.href = '/index.php';
                }
            } catch (error) {
                console.error('Auth check error:', error);
                window.location.href = '/index.php';
            }
        })();
        // ============================================
        // Listener برای آپدیت خودکار تاریخ پایان
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            // تاریخ شروع مأموریت
            const missionStartInput = document.getElementById('missionStartDate');
            const missionEndInput = document.getElementById('missionEndDate');

            if (missionStartInput) {
                // Observer برای تغییرات
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'data-date') {
                            const startDate = missionStartInput.getAttribute('data-date');
                            const startValue = missionStartInput.value;
                            if (startDate && missionEndInput) {
                                missionEndInput.value = startValue;
                                missionEndInput.setAttribute('data-date', startDate);
                            }
                        }
                    });
                });

                observer.observe(missionStartInput, {
                    attributes: true,
                    attributeFilter: ['data-date']
                });
            }

            // تاریخ شروع مرخصی
            const leaveStartInput = document.getElementById('leaveStartDate');
            const leaveEndInput = document.getElementById('leaveEndDate');

            if (leaveStartInput) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'data-date') {
                            const startDate = leaveStartInput.getAttribute('data-date');
                            const startValue = leaveStartInput.value;
                            if (startDate && leaveEndInput) {
                                leaveEndInput.value = startValue;
                                leaveEndInput.setAttribute('data-date', startDate);
                            }
                        }
                    });
                });

                observer.observe(leaveStartInput, {
                    attributes: true,
                    attributeFilter: ['data-date']
                });
            }
        });

        // تابع اعتبارسنجی ساعت
        function validateTimeRange(startTime, endTime) {
            if (!startTime || !endTime) return true; // بعداً چک الزامی میشه
            return endTime > startTime;
        }

        async function submitRequest(e) {
            if (e && e.preventDefault) {
                e.preventDefault();
            }
            const submitBtn = document.getElementById('submitBtn');
            // ✅ قفل کردن دکمه در همان لحظه اول
            submitBtn.disabled = true;
            submitBtn.textContent = 'در حال ارسال...';

            const type = ['mission', 'leave', 'pass', 'forget', 'technical'][currentTab];
            let data = {
                type
            };

            if (type === 'mission') {
                const startDate = document.getElementById('missionStartDate').getAttribute('data-date');
                let endDate = document.getElementById('missionEndDate').getAttribute('data-date');
                const startTime = document.getElementById('missionStartTime').value;
                const endTime = document.getElementById('missionEndTime').value;
                const desc = document.getElementById('missionDesc').value;

                // ✅ اگر تاریخ پایان خالی یا null بود، از تاریخ شروع استفاده کن
                if (!endDate || endDate === 'null' || endDate === 'undefined') {
                    endDate = startDate;
                }

                console.log('📌 Debug mission:', {
                    startDate,
                    endDate,
                    startTime,
                    endTime
                }); // برای دیباگ                if (!startDate || !startTime || !endTime || !desc) {

                if (!startDate || !startTime || !endTime || !desc) {
                    alert('❌ لطفاً تمام فیلدها را پر کنید');
                    return;
                }

                if (!validateTimeRange(startTime, endTime)) {
                    alert('❌ ساعت پایان باید بعد از ساعت شروع باشد');
                    return;
                }
                // ✅ اعتبارسنجی سقف ساعت مأموریت در ماه
                if (APP_SETTINGS.mission_max_hours_monthly > 0) {
                    try {
                        const checkRes = await fetch(`/attendance_system/api/requests/check-limits.php?type=mission&date=${data.start_datetime.split(' ')[0]}`, {
                            headers: {
                                'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
                            }
                        });
                        const checkData = await checkRes.json();
                        if (checkData.success && checkData.limit_reached) {
                            alert('❌ ' + checkData.message);
                            return;
                        }
                    } catch (e) {
                        console.error('خطا در بررسی سقف:', e);
                    }
                }
                data = {
                    ...data,
                    start_datetime: `${startDate} ${startTime}`,
                    end_datetime: `${endDate} ${endTime}`,
                    description: desc
                };
            } else if (type === 'leave') {
                const startDate = document.getElementById('leaveStartDate').getAttribute('data-date');
                let endDate = document.getElementById('leaveEndDate').getAttribute('data-date');
                const startTime = document.getElementById('leaveStartTime').value;
                const endTime = document.getElementById('leaveEndTime').value;
                const reason = document.getElementById('leaveReason').value;
                const substituteId = document.getElementById('leaveSubstitute').value;
                if (!substituteId) {
                    alert('❌ لطفاً جانشین را انتخاب کنید');
                    return;
                }
                // ✅ اگر تاریخ پایان خالی یا null بود، از تاریخ شروع استفاده کن
                if (!endDate || endDate === 'null' || endDate === 'undefined') {
                    endDate = startDate;
                }

                console.log('📌 Debug mission:', {
                    startDate,
                    endDate,
                    startTime,
                    endTime
                }); // برای دیباگ

                if (!startDate || !startTime || !endTime || !reason) {
                    alert('❌ لطفاً تمام فیلدها را پر کنید');
                    return;
                }
                // اگر تاریخ شروع و پایان یکی باشد، ساعت را چک کن
                if (startDate === endDate && !validateTimeRange(startTime, endTime)) {
                    alert('❌ ساعت پایان باید بعد از ساعت شروع باشد');
                    return;
                }

                // if (APP_SETTINGS.forget_max_monthly > 0) {
                //     try {
                //         const checkRes = await fetch(`/attendance_system/api/requests/check-limits.php?type=forget&date=${date}`, {
                //             headers: { 'Authorization': 'Bearer ' + localStorage.getItem('auth_token') }
                //         });

                //         const checkData = await checkRes.json();
                //         if (checkData.success && checkData.limit_reached) {
                //             alert('❌ ' + checkData.message);
                //             return;
                //         }
                //     } catch (e) { console.error('خطا در بررسی سقف:', e); }
                // }
                console.log('📥 Raw response from server:', APP_SETTINGS.forget_max_monthly);
                data = {
                    ...data,
                    start_datetime: `${startDate} ${startTime}`,
                    end_datetime: `${endDate} ${endTime}`,
                    reason: reason,
                    substitute_id: substituteId
                };
            } else if (type === 'pass') {
                const passDate = document.getElementById('passDate').getAttribute('data-date');
                const startTime = document.getElementById('passStartTime').value;
                const endTime = document.getElementById('passEndTime').value;
                const reason = document.getElementById('passReason').value;

                if (!passDate || !startTime || !endTime || !reason) {
                    alert('❌ لطفاً تمام فیلدها را پر کنید');
                    return;
                }
                if (!validateTimeRange(startTime, endTime)) {
                    alert('❌ ساعت پایان باید بعد از ساعت شروع باشد');
                    return;
                }
                // ✅ اعتبارسنجی سقف تعداد پاس در ماه
                if (APP_SETTINGS.pass_max_count_monthly > 0) {
                    try {
                        const checkRes = await fetch(`/attendance_system/api/requests/check-limits.php?type=pass&date=${passDate}`, {
                            headers: {
                                'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
                            }
                        });
                        const checkData = await checkRes.json();
                        if (checkData.success && checkData.limit_reached) {
                            alert('❌ ' + checkData.message);
                            return; // متوقف کردن ارسال
                        }
                        // اگر سرور پاسخی جز success داد
                        if (!checkData.success) {
                            alert('❌ خطایی در بررسی سقف رخ داد. لطفاً دوباره تلاش کنید.');
                            return;
                        }
                    } catch (e) {
                        console.error('خطا در بررسی سقف:', e);
                        // ✅ این بسیار مهم است: اگر اینترنت قطع شد یا سرور ارور داد، اجازه ثبت نده!
                        alert('❌ خطا در ارتباط با سرور برای بررسی محدودیت‌ها. درخواست ثبت نشد.');
                        return;
                    }
                }

                // ✅ اعتبارسنجی سقف ساعت پاس در روز
                if (APP_SETTINGS.pass_max_hours_daily > 0) {
                    const startMinutes = parseInt(startTime.split(':')[0]) * 60 + parseInt(startTime.split(':')[1]);
                    const endMinutes = parseInt(endTime.split(':')[0]) * 60 + parseInt(endTime.split(':')[1]);
                    const durationHours = (endMinutes - startMinutes) / 60;
                    if (durationHours > APP_SETTINGS.pass_max_hours_daily) {
                        alert('❌ مدت زمان پاس بیشتر از حداکثر مجاز (' + APP_SETTINGS.pass_max_hours_daily + ' ساعت) است');
                        return;
                    }
                }
                data = {
                    ...data,
                    pass_date: passDate,
                    start_time: startTime,
                    end_time: endTime,
                    reason: reason
                };
            } else if (type === 'forget') {
                const date = document.getElementById('forgetPasswordDate').getAttribute('data-date');
                const startTime = document.getElementById('forgetStartTime').value;
                const endTime = document.getElementById('forgetEndTime').value;
                const desc = document.getElementById('forgetPasswordDesc').value;

                if (!date || !startTime || !endTime || !desc) {
                    alert('❌ لطفاً تمام فیلدها را پر کنید');
                    return;
                }
                if (!validateTimeRange(startTime, endTime)) {
                    alert('❌ ساعت پایان باید بعد از ساعت شروع باشد');
                    return;
                }
                // ✅ اعتبارسنجی سقف فراموشی در ماه
                if (APP_SETTINGS.forget_max_monthly > 0) {
                    try {
                        const checkRes = await fetch(`/attendance_system/api/requests/check-limits.php?type=forget&date=${date}`, {
                            headers: {
                                'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
                            }
                        });
                        const checkData = await checkRes.json();
                        if (checkData.success && checkData.limit_reached) {
                            alert('❌ ' + checkData.message);
                            return;
                        }
                    } catch (e) {
                        console.error('خطا در بررسی سقف:', e);
                    }
                }
                data = {
                    ...data,
                    datetime: `${date} ${startTime}`,
                    end_time: `${date} ${endTime}`,
                    description: desc
                };
            } else if (type === 'technical') {
                const date = document.getElementById('technicalIssueDate').getAttribute('data-date');
                const startTime = document.getElementById('technicalStartTime').value;
                const endTime = document.getElementById('technicalEndTime').value;
                const desc = document.getElementById('technicalIssueDesc').value;

                if (!date || !startTime || !endTime || !desc) {
                    alert('❌ لطفاً تمام فیلدها را پر کنید');
                    return;
                }
                if (!validateTimeRange(startTime, endTime)) {
                    alert('❌ ساعت پایان باید بعد از ساعت شروع باشد');
                    return;
                }
                // ✅ اعتبارسنجی سقف مشکل فنی در ماه
                if (APP_SETTINGS.technical_max_monthly > 0) {
                    try {
                        const checkRes = await fetch(`/attendance_system/api/requests/check-limits.php?type=technical&date=${date}`, {
                            headers: {
                                'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
                            }
                        });
                        const checkData = await checkRes.json();
                        if (checkData.success && checkData.limit_reached) {
                            alert('❌ ' + checkData.message);
                            return;
                        }
                    } catch (e) {
                        console.error('خطا در بررسی سقف:', e);
                    }
                }
                data = {
                    ...data,
                    datetime: `${date} ${startTime}`,
                    end_time: endTime,
                    description: desc
                };
            }

            try {
                const submitBtn = document.getElementById('submitBtn');
                const form = document.getElementById('requestForm');
                const editId = form.getAttribute('data-edit-id');
                const editType = form.getAttribute('data-edit-type');

                submitBtn.disabled = true;
                submitBtn.textContent = editId ? 'در حال ذخیره...' : 'در حال ارسال...';

                // تعیین API بر اساس حالت ویرایش یا ایجاد
                let apiUrl = '/attendance_system/api/requests/create.php';
                if (editId) {
                    apiUrl = '/attendance_system/api/requests/edit.php';
                    data.id = editId;
                }

                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const responseText = await response.text();
                let result;
                try {
                    console.log("SERVER RESPONSE:", responseText);

                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('❌ خطا در parse JSON:', parseError);
                    alert('❌ خطا در سرور');
                    submitBtn.disabled = false;
                    submitBtn.textContent = editId ? 'ذخیره تغییرات' : 'ارسال درخواست';
                    return;
                }

                if (result.success) {
                    alert(editId ? '✅ تغییرات ذخیره شد' : '✅ درخواست ثبت شد');
                    // پاک کردن حالت ویرایش
                    form.removeAttribute('data-edit-id');
                    form.removeAttribute('data-edit-type');
                    closeModal();
                    setTimeout(() => location.reload(), 1200);
                } else {
                    alert('❌ خطا: ' + (result.message || 'نامشخص'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = editId ? 'ذخیره تغییرات' : 'ارسال درخواست';
                }
            } catch (error) {
                console.error('❌ خطا:', error);
                alert('❌ خطا در ارتباط با سرور');
                const form = document.getElementById('requestForm');
                const editId = form.getAttribute('data-edit-id');
                document.getElementById('submitBtn').disabled = false;
                document.getElementById('submitBtn').textContent = editId ? 'ذخیره تغییرات' : 'ارسال درخواست';
            }
        }

        // ============================================
        // تابع مرکزی فیلتر - همه فیلترها از اینجا اعمال میشن
        // ============================================
        function applyAllFilters() {
            // 1. وضعیت فعال
            const activeFilterBtn = document.querySelector('.filter-btn.active');
            const statusFilter = activeFilterBtn ?
                (activeFilterBtn.textContent.includes('همه') ? 'all' :
                    activeFilterBtn.textContent.includes('انتظار') ? 'pending' :
                    activeFilterBtn.textContent.includes('تایید') ? 'approved' : 'rejected') :
                'all';

            // 2. فیلتر کارمند یا واحد
            const employeeSelect = document.getElementById('employeeFilter');
            const empVal = employeeSelect ? employeeSelect.value : '';
            let selectedUserId = '',
                selectedSection = '';
            if (empVal.indexOf('section:') === 0) {
                selectedSection = empVal.slice(8);
            } else {
                selectedUserId = empVal;
            }
            const secMap = window.USER_SECTION_MAP || {};

            // 3. فیلتر ماه جاری
            const monthFilterChecked = document.getElementById('currentMonthFilter')?.checked || false;
            let startOfMonth = null;
            if (monthFilterChecked) {
                const today = new Date();
                const [jy, jm] = gregorianToJalaliJS(today.getFullYear(), today.getMonth() + 1, today.getDate());
                const [sGy, sGm, sGd] = jalaliToGregorianJS(jy, jm, 1);
                startOfMonth = new Date(sGy, sGm - 1, sGd);
            }

            // اعمال همه فیلترها به هر ردیف از صفر
            document.querySelectorAll('.request-row').forEach(row => {
                let visible = true;

                // فیلتر وضعیت
                if (statusFilter !== 'all') {
                    const rowStatus = row.getAttribute('data-status');
                    if (rowStatus !== statusFilter) visible = false;
                }

                // فیلتر کارمند
                if (visible && selectedUserId) {
                    const rowUserId = row.getAttribute('data-userid');
                    if (rowUserId !== selectedUserId) visible = false;
                }
                // فیلتر واحد
                if (visible && selectedSection) {
                    const rowUserId = row.getAttribute('data-userid');
                    if (String(secMap[rowUserId]) !== selectedSection) visible = false;
                }

                // فیلتر ماه جاری
                if (visible && startOfMonth) {
                    const dateStr = row.getAttribute('data-date');
                    if (dateStr) {
                        const rowDate = new Date(dateStr);
                        if (rowDate < startOfMonth) visible = false;
                    }
                }

                row.style.display = visible ? '' : 'none';
            });

            initPagination();
        }
        // پیش‌فرض هنگام لود: فقط ماه جاری
        document.addEventListener('DOMContentLoaded', applyAllFilters);

        function filterByStatus(status) {
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            applyAllFilters();
        }

        // فیلتر بر اساس کارمند (فقط مدیران)
        function filterByEmployee() {
            applyAllFilters();
        }

        // فیلتر فقط ماه جاری
        function toggleCurrentMonthFilter() {
            applyAllFilters();
        }

        // توابع تبدیل تاریخ برای فیلتر
        function gregorianToJalaliJS(gy, gm, gd) {
            const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
            let jy = (gy <= 1600) ? 0 : 979;
            gy -= (gy <= 1600) ? 621 : 1600;
            const gy2 = (gm > 2) ? (gy + 1) : gy;
            let days = (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) +
                Math.floor((gy2 + 399) / 400) - 80 + gd + g_d_m[gm - 1];
            jy += 33 * Math.floor(days / 12053);
            days %= 12053;
            jy += 4 * Math.floor(days / 1461);
            days %= 1461;
            if (days > 365) {
                jy += Math.floor((days - 1) / 365);
                days = (days - 1) % 365;
            }
            const jm = (days < 186) ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
            const jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
            return [jy, jm, jd];
        }

        function jalaliToGregorianJS(jy, jm, jd) {
            let gy = (jy < 979) ? 621 : 1600;
            if (jy >= 979) jy -= 979;
            let days = (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + 78 + jd;
            if (jm < 7) days += (jm - 1) * 31;
            else days += ((jm - 7) * 30) + 186;
            gy += 400 * Math.floor(days / 146097);
            days %= 146097;
            if (days > 36524) {
                gy += 100 * Math.floor(--days / 36524);
                days %= 36524;
                if (days >= 365) days++;
            }
            gy += 4 * Math.floor(days / 1461);
            days %= 1461;
            if (days > 365) {
                gy += Math.floor((days - 1) / 365);
                days = (days - 1) % 365;
            }
            let gd = days + 1;
            const leap = ((gy % 4 == 0 && gy % 100 != 0) || (gy % 400 == 0));
            const sal_a = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            let gm;
            for (gm = 0; gm < 13; gm++) {
                if (gd <= sal_a[gm]) break;
                gd -= sal_a[gm];
            }
            return [gy, gm, gd];
        }

        // جستجو
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('searchInput').addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase();
                document.querySelectorAll('.request-row').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });

                initPagination();
            });
            setTimeout(function() {

                loadMonthlyAttendance();
                loadSubstituteUsers();
                initPagination();
                loadPendingCount(); // لود تعداد درخواست‌های منتظر تأیید

                // چک کردن پارامتر URL برای باز کردن تب منتظر تأیید
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('tab') === 'pending-approvals') {
                    switchSection('pending-approvals');
                }
            }, 100);

        });

        // ============================================
        // توابع حذف و ویرایش درخواست
        // ============================================
        // async function loadSubstituteUsers() {
        //     try {
        //         const authToken = localStorage.getItem('auth_token');
        //         const response = await fetch('/api/users/list.php', {
        //             headers: { 'Authorization': 'Bearer ' + authToken }
        //         });
        //         const data = await response.json();

        //         if (data.success) {
        //             const select = document.getElementById('leaveSubstitute');
        //             // دریافت اطلاعات کاربر جاری
        //             const currentUserInfo = JSON.parse(localStorage.getItem('user_info') || '{}');

        //             data.users.forEach(user => {
        //                 // کاربر جاری نمایش داده نشود
        //                 if (user.id == currentUserInfo.id) return;

        //                 const name = user.full_name ||
        //                     `${user.first_name || ''} ${user.last_name || ''}`.trim() ||
        //                     user.phone;
        //                 const option = new Option(name, user.id);
        //                 select.add(option);
        //             });
        //         }
        //     } catch (error) {
        //         console.error('خطا در بارگذاری لیست جانشین‌ها:', error);
        //     }
        // }

        async function loadSubstituteUsers() {
            try {
                const authToken = localStorage.getItem('auth_token');
                const response = await fetch('/api/users/list.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                const data = await response.json();
                if (data.success) {
                    const currentUserInfo = JSON.parse(localStorage.getItem('user_info') || '{}');
                    users = data.users;

                    unitsToFilter = ['all', 'RS', 'ATM', 'AC'];
                    const filteredUsers = users.filter(user => {
                        return unitsToFilter.includes(user.activity_unit);
                    });

                    const select = document.getElementById('leaveSubstitute');

                    filteredUsers.forEach(user => {
                        const option = document.createElement('option');
                        option.value = user.id;
                        option.textContent = user.full_name || `${user.first_name || ''} ${user.last_name || ''}`.trim() || user.phone;
                        select.appendChild(option);
                    });

                }
            } catch (error) {
                console.error('Error loading users:', error);
            }
        }

        // سوییچ بین بخش‌ها
        function switchSection(section) {
            document.querySelectorAll('.section-tab').forEach(tab => tab.classList.remove('active'));
            document.getElementById('tab-' + section).classList.add('active');

            // hide all sections
            document.getElementById('attendance-section').style.display = 'none';
            document.getElementById('my-requests-section').style.display = 'none';
            document.getElementById('pending-approvals-section').style.display = 'none';

            if (section === 'attendance') {
                document.getElementById('attendance-section').style.display = 'block';
            } else if (section === 'my-requests') {
                document.getElementById('my-requests-section').style.display = 'block';
            } else {
                document.getElementById('pending-approvals-section').style.display = 'block';
                loadPendingApprovals();
            }
        }

        let pendingGridApi = null;

        function buildPendingGrid(rows) {
            const el = document.getElementById('pendingApprovalsGrid');
            if (!el || typeof agGrid === 'undefined') return;

            const typeLabels = {
                mission: 'مأموریت',
                leave: 'مرخصی',
                pass: 'پاس',
                forget: 'فراموشی',
                technical: 'مشکل فنی'
            };
            const roleLabels = {
                substitute: 'جانشین',
                manager: 'مدیر',
                supervisor: 'مسئول',
                admin: 'ادمین'
            };
            const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

            const cols = [{
                    headerName: 'کد',
                    field: 'request_code',
                    width: 120,
                    cellRenderer: p => convertToFarsiNumber(p.value || '—')
                },
                {
                    headerName: 'نوع',
                    width: 90,
                    valueGetter: p => typeLabels[p.data.type] || p.data.type
                },
                {
                    headerName: 'درخواست‌دهنده',
                    width: 150,
                    valueGetter: p => ((p.data.first_name || '') + ' ' + (p.data.last_name || '')).trim() || '—'
                },
                {
                    headerName: 'جانشین',
                    field: 'substitute_name',
                    width: 120,
                    cellRenderer: p => p.value ? esc(p.value) : '—'
                },
                {
                    headerName: 'شروع',
                    width: 145,
                    valueGetter: p => formatDateTimeJalali(p.data.start_datetime)
                },
                {
                    headerName: 'پایان',
                    width: 145,
                    valueGetter: p => formatDateTimeJalali(p.data.end_datetime)
                },
                {
                    headerName: 'توضیحات',
                    field: 'description',
                    flex: 1,
                    minWidth: 140,
                    tooltipField: 'description',
                    cellRenderer: p => p.value ? esc(p.value) : '—'
                },
                {
                    headerName: 'نقش',
                    width: 90,
                    cellRenderer: p => `<span class="role-badge ${p.data.pending_role}">${roleLabels[p.data.pending_role] || p.data.pending_role}</span>`
                },
                {
                    headerName: 'عملیات',
                    width: 120,
                    sortable: false,
                    cellRenderer: p => {
                        if (p.data.is_expired) return '<span style="background:#FEF3C7;color:#D97706;font-size:10px;padding:4px 8px;border-radius:6px;white-space:nowrap;"><i class="bi bi-clock"></i> منقضی شده</span>';
                        return `<div style="display:flex;gap:6px;justify-content:center;align-items:center;height:100%;">
                            <button class="action-icon-btn approve-btn" onclick="approveRequest(${p.data.id}, '${p.data.type}')" title="تأیید"><i class="bi bi-check-lg"></i></button>
                            <button class="action-icon-btn reject-btn" onclick="rejectRequest(${p.data.id}, '${p.data.type}')" title="رد"><i class="bi bi-x-lg"></i></button>
                        </div>`;
                    }
                }
            ];

            if (!pendingGridApi) {
                pendingGridApi = agGrid.createGrid(el, {
                    enableRtl: true,
                    rowHeight: 46,
                    headerHeight: 44,
                    pagination: true,
                    paginationPageSize: 10,
                    enableBrowserTooltips: true,
                    columnDefs: cols,
                    rowData: rows,
                    getRowStyle: p => p.data.is_expired ? {
                        opacity: '0.6'
                    } : null,
                    onPaginationChanged: () => persianizePaging(),
                    overlayNoRowsTemplate: '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem;"><div style="font-size:48px;margin-bottom:12px;">✅</div><div style="font-size:16px;font-weight:600;color:#10B981;">هیچ درخواستی منتظر تأیید شما نیست</div></div>'
                });
                window.__pendingGridApi = pendingGridApi;
            } else {
                pendingGridApi.setGridOption('rowData', rows);
            }
        }

        async function loadPendingApprovals() {
            try {
                const response = await fetch('/attendance_system/api/requests/pending-approvals.php');
                const result = await response.json();

                const badge = document.getElementById('pendingCount');
                if (badge) {
                    if (result.success && result.count > 0) {
                        badge.textContent = convertToFarsiNumber(result.count);
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }

                buildPendingGrid(result.success ? (result.data || []) : []);
            } catch (error) {
                console.error('خطا:', error);
                buildPendingGrid([]);
            }
        }

        // تبدیل تاریخ و زمان به جلالی
        function formatDateTimeJalali(datetime) {
            if (!datetime) return '—';
            const parts = datetime.split(' ');
            const datePart = parts[0];
            const timePart = parts[1] ? parts[1].substring(0, 5) : '';

            // تبدیل تاریخ
            const dateParts = datePart.split('-');
            if (dateParts.length !== 3) return datetime;

            const jalali = gregorianToJalali(parseInt(dateParts[0]), parseInt(dateParts[1]), parseInt(dateParts[2]));
            return convertToFarsiNumber(`${jalali[0]}/${String(jalali[1]).padStart(2, '0')}/${String(jalali[2]).padStart(2, '0')}`) +
                (timePart ? ' - ' + convertToFarsiNumber(timePart) : '');
        }

        // تبدیل میلادی به جلالی
        function gregorianToJalali(gy, gm, gd) {
            const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
            let jy = (gy <= 1600) ? 0 : 979;
            gy = (gy <= 1600) ? (gy - 621) : (gy - 1600);
            let gy2 = (gm > 2) ? (gy + 1) : gy;
            let days = (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) +
                Math.floor((gy2 + 399) / 400) - 80 + gd + g_d_m[gm - 1];
            jy += 33 * Math.floor(days / 12053);
            days %= 12053;
            jy += 4 * Math.floor(days / 1461);
            days %= 1461;
            if (days > 365) {
                jy += Math.floor((days - 1) / 365);
                days = (days - 1) % 365;
            }
            let jm, jd;
            if (days < 186) {
                jm = 1 + Math.floor(days / 31);
                jd = 1 + (days % 31);
            } else {
                days -= 186;
                jm = 7 + Math.floor(days / 30);
                jd = 1 + (days % 30);
            }
            return [jy, jm, jd];
        }

        // تأیید درخواست
        async function approveRequest(id, type) {
            uiConfirm('آیا از تأیید این درخواست مطمئن هستید؟', async function() {
                try {
                    const response = await fetch('/attendance_system/api/requests/approve.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id,
                            type,
                            action: 'approve'
                        })
                    });
                    const result = await response.json();
                    if (result.success) {
                        showToast(result.message, 'success');
                        setTimeout(() => {
                            window.location.href = window.location.pathname + '?tab=pending-approvals';
                        }, 1200);
                    } else {
                        showToast(result.message, 'warning');
                    }
                } catch (error) {
                    console.error('خطا:', error);
                    showToast('خطا در ارتباط با سرور', 'warning');
                }
            }, {
                yesText: 'بله، تأیید شود',
                noText: 'خیر، منصرف شدم'
            });
        }

        // رد درخواست
        // رد درخواست — گرفتن دلیل با مودال (جایگزین prompt)
        function rejectRequest(id, type) {
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;display:flex;align-items:center;justify-content:center;';
            overlay.innerHTML = `
                <div style="background:#fff;border-radius:12px;padding:20px;width:90%;max-width:420px;box-shadow:0 10px 40px rgba(0,0,0,.2);direction:rtl;">
                    <h6 style="margin:0 0 12px;font-weight:700;">دلیل رد درخواست</h6>
                    <textarea id="rejectReasonInput" rows="4"
                        style="width:100%;border:1px solid #ddd;border-radius:8px;padding:10px;resize:vertical;font-family:inherit;"
                        placeholder="لطفاً دلیل رد را بنویسید..."></textarea>
                    <div style="display:flex;gap:8px;margin-top:14px;">
                        <button id="rejectConfirmBtn" class="btn btn-danger">رد درخواست</button>
                        <button id="rejectCancelBtn" class="btn btn-secondary">انصراف</button>
                    </div>
                </div>`;
            document.body.appendChild(overlay);

            const close = () => overlay.remove();
            overlay.querySelector('#rejectCancelBtn').onclick = close;
            overlay.onclick = (e) => {
                if (e.target === overlay) close();
            };
            overlay.querySelector('#rejectReasonInput').focus();

            overlay.querySelector('#rejectConfirmBtn').onclick = async function() {
                const notes = overlay.querySelector('#rejectReasonInput').value.trim();
                if (!notes) {
                    showToast('لطفاً دلیل رد را وارد کنید', 'warning');
                    return;
                }
                close();

                try {
                    const response = await fetch('/attendance_system/api/requests/approve.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id,
                            type,
                            action: 'reject',
                            notes
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('✅ ' + result.message);
                        // رفرش صفحه با پارامتر برای ماندن در تب منتظر تأیید
                        setTimeout(() => {
                            window.location.href = window.location.pathname + '?tab=pending-approvals';
                        }, 1200);
                    } else {
                        alert('❌ ' + result.message);
                    }
                } catch (error) {
                    console.error('خطا:', error);
                    alert('❌ خطا در ارتباط با سرور');
                }
            };
        }

        // لود تعداد منتظر تأیید هنگام بارگذاری صفحه
        async function loadPendingCount() {
            try {
                const response = await fetch('/attendance_system/api/requests/pending-approvals.php');
                const text = await response.text();
                console.log('Status:', response.status);
                console.log('Response:', text);

                if (!text.trim()) {
                    console.error('پاسخ خالیه');
                    return;
                }

                const result = JSON.parse(text);
                if (result.success && result.count > 0) {
                    const badge = document.getElementById('pendingCount');
                    badge.textContent = convertToFarsiNumber(result.count);
                    badge.style.display = 'inline-block';
                }
            } catch (error) {
                console.error('خطا در لود تعداد:', error);
            }
        }

        // حذف درخواست
        async function deleteRequest(id, type) {
            showToast('آیا از حذف این درخواست مطمئن هستید؟', 'warning', {
                duration: 1500000,
                buttons: [{
                        label: 'بله، حذف شود',
                        style: 'primary',
                        onClick: async function() {
                            try {
                                const response = await fetch('/attendance_system/api/requests/delete.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        id,
                                        type
                                    })
                                });

                                const result = await response.json();

                                if (result.success) {
                                    alert('✅ ' + result.message);
                                    setTimeout(() => location.reload(), 1200);
                                } else {
                                    alert('❌ ' + result.message);
                                }
                            } catch (error) {
                                console.error('خطا:', error);
                                alert('❌ خطا در ارتباط با سرور');
                            }
                        }
                    },
                    {
                        label: 'خیر، منصرف شدم',
                        style: 'ghost',
                        onClick: function() {
                            return;
                        }
                    }
                ]
            });
        }

        // ویرایش درخواست - باز کردن مودال با داده‌های موجود
        async function editRequest(id, type) {
            try {
                const response = await fetch(`/attendance_system/api/requests/get.php?id=${id}&type=${type}`);
                const result = await response.json();

                if (!result.success) {
                    alert('❌ ' + result.message);
                    return;
                }

                const data = result.data;

                // تعیین tab بر اساس نوع
                const tabIndex = {
                    'mission': 0,
                    'leave': 1,
                    'pass': 2,
                    'forget': 3,
                    'technical': 4
                } [type];
                switchTab(tabIndex);

                // باز کردن مودال
                document.getElementById('modalOverlay').classList.add('show');

                // ذخیره id برای submit
                document.getElementById('requestForm').setAttribute('data-edit-id', id);
                document.getElementById('requestForm').setAttribute('data-edit-type', type);

                // پر کردن فیلدها بر اساس نوع
                setTimeout(() => {
                    if (type === 'mission') {
                        const startInput = document.getElementById('missionStartDate');
                        const endInput = document.getElementById('missionEndDate');
                        if (startInput) {
                            startInput.setAttribute('data-date', data.start_date);
                            startInput.value = convertToJalaliDisplay(data.start_date);
                        }
                        if (endInput) {
                            endInput.setAttribute('data-date', data.end_date);
                            endInput.value = convertToJalaliDisplay(data.end_date);
                        }
                        document.getElementById('missionStartTime').value = data.start_time || '';
                        document.getElementById('missionEndTime').value = data.end_time || '';
                        document.getElementById('missionDesc').value = data.description || '';
                    } else if (type === 'leave') {
                        const startInput = document.getElementById('leaveStartDate');
                        const endInput = document.getElementById('leaveEndDate');
                        if (startInput) {
                            startInput.setAttribute('data-date', data.start_date);
                            startInput.value = convertToJalaliDisplay(data.start_date);
                        }
                        if (endInput) {
                            endInput.setAttribute('data-date', data.end_date);
                            endInput.value = convertToJalaliDisplay(data.end_date);
                        }
                        document.getElementById('leaveStartTime').value = data.start_time || '';
                        document.getElementById('leaveEndTime').value = data.end_time || '';
                        document.getElementById('leaveReason').value = data.reason || '';
                        // پر کردن جانشین
                        if (data.substitute_id) {
                            document.getElementById('leaveSubstitute').value = data.substitute_id;
                        }
                    } else if (type === 'pass') {
                        const dateInput = document.getElementById('passDate');
                        if (dateInput) {
                            dateInput.setAttribute('data-date', data.pass_date);
                            dateInput.value = convertToJalaliDisplay(data.pass_date);
                        }
                        document.getElementById('passStartTime').value = data.start_time || '';
                        document.getElementById('passEndTime').value = data.end_time || '';
                        document.getElementById('passReason').value = data.reason || '';
                    } else if (type === 'forget') {
                        const dateInput = document.getElementById('forgetPasswordDate');
                        if (dateInput) {
                            dateInput.setAttribute('data-date', data.date);
                            dateInput.value = convertToJalaliDisplay(data.date);
                        }
                        document.getElementById('forgetStartTime').value = data.start_time || '';
                        document.getElementById('forgetEndTime').value = data.end_time || '';
                        document.getElementById('forgetPasswordDesc').value = data.description || '';
                    } else if (type === 'technical') {
                        const dateInput = document.getElementById('technicalIssueDate');
                        if (dateInput) {
                            dateInput.setAttribute('data-date', data.date);
                            dateInput.value = convertToJalaliDisplay(data.date);
                        }
                        document.getElementById('technicalStartTime').value = data.start_time || '';
                        document.getElementById('technicalEndTime').value = data.end_time || '';
                        document.getElementById('technicalIssueDesc').value = data.description || '';
                    }

                    // تغییر متن دکمه
                    document.getElementById('submitBtn').textContent = 'ذخیره تغییرات';
                }, 100);

            } catch (error) {
                console.error('خطا:', error);
                alert('❌ خطا در دریافت اطلاعات');
            }
        }

        // تبدیل تاریخ میلادی به شمسی برای نمایش
        function convertToJalaliDisplay(gregorianDate) {
            if (!gregorianDate) return '';
            const parts = gregorianDate.split('-');
            if (parts.length !== 3) return gregorianDate;

            const gy = parseInt(parts[0]);
            const gm = parseInt(parts[1]);
            const gd = parseInt(parts[2]);

            // تبدیل به جلالی
            const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
            let jy = (gy <= 1600) ? 0 : 979;
            let gy2 = (gy <= 1600) ? (gy - 621) : (gy - 1600);
            let gy3 = (gm > 2) ? (gy2 + 1) : gy2;
            let days = (365 * gy2) + Math.floor((gy3 + 3) / 4) - Math.floor((gy3 + 99) / 100) +
                Math.floor((gy3 + 399) / 400) - 80 + gd + g_d_m[gm - 1];
            jy += 33 * Math.floor(days / 12053);
            days %= 12053;
            jy += 4 * Math.floor(days / 1461);
            days %= 1461;
            if (days > 365) {
                jy += Math.floor((days - 1) / 365);
                days = (days - 1) % 365;
            }
            let jm, jd;
            if (days < 186) {
                jm = 1 + Math.floor(days / 31);
                jd = 1 + (days % 31);
            } else {
                days -= 186;
                jm = 7 + Math.floor(days / 30);
                jd = 1 + (days % 30);
            }

            return `${jy}/${String(jm).padStart(2, '0')}/${String(jd).padStart(2, '0')}`;
        }

        // ============================================
        // نمایش دلیل رد درخواست
        // ============================================
        function showRejectReason(reason) {
            document.getElementById('rejectReasonText').textContent = reason || 'دلیلی ثبت نشده است';
            document.getElementById('rejectReasonModal').classList.add('active');
        }

        function closeRejectModal() {
            document.getElementById('rejectReasonModal').classList.remove('active');
        }

        // ============================================
        // Timeline تأییدات
        // ============================================
        function showTimeline(requestId, requestType, event) {
            event.stopPropagation();

            const overlay = document.getElementById('timelineModalOverlay');
            const content = document.getElementById('timelineContent');

            content.innerHTML = '<div class="loading-state"><div class="spinner-border"></div><div>در حال بارگذاری...</div></div>';
            overlay.classList.add('active');

            fetch(`/attendance_system/api/requests/timeline.php?id=${requestId}&type=${requestType}`)
                .then(res => res.json())
                .then(result => {
                    if (!result.success) {
                        content.innerHTML = `<div class="text-danger text-center">${result.message}</div>`;
                        return;
                    }

                    let html = '<div class="timeline-container">';
                    result.data.forEach(item => {
                        html += `
                            <div class="timeline-item">
                                <div class="timeline-dot ${item.status}"></div>
                                <div class="timeline-content">
                                    <div class="timeline-role">${item.role_label}</div>
                                    <div class="timeline-status ${item.status}">${item.status_label}</div>
                                    ${item.date ? `<div class="timeline-date">${item.date}</div>` : ''}
                                    ${item.approver_name ? `<div class="timeline-approver">توسط: ${item.approver_name}</div>` : ''}
                                    ${item.notes ? `<div class="timeline-notes">"${item.notes}"</div>` : ''}
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';

                    content.innerHTML = html;
                })
                .catch(err => {
                    console.error(err);
                    content.innerHTML = '<div class="text-danger text-center">خطا در دریافت اطلاعات</div>';
                });
        }

        function closeTimelineModal() {
            document.getElementById('timelineModalOverlay').classList.remove('active');
        }
    </script>

    <!-- Reject Reason Modal -->
    <!-- <div class="reject-modal-overlay" id="rejectReasonModal" onclick="closeRejectModal()">
        <div class="reject-modal-content" onclick="event.stopPropagation()">
            <h4><i class="bi bi-x-circle"></i> دلیل رد درخواست</h4>
            <p id="rejectReasonText"></p>
            <button onclick="closeRejectModal()">متوجه شدم</button>
        </div>
    </div> -->

    <!-- Timeline Modal -->
    <div class="timeline-modal-overlay" id="timelineModalOverlay" onclick="closeTimelineModal()">
        <div class="timeline-modal" onclick="event.stopPropagation()">
            <div class="timeline-header">
                <h3><i class="bi bi-clock-history me-2"></i> تاریخچه تأییدات</h3>
                <button class="timeline-close" onclick="closeTimelineModal()">&times;</button>
            </div>
            <div id="timelineContent"></div>
        </div>
    </div>

    <!-- Custom Tooltip for reject reason -->
    <div class="custom-tooltip" id="rejectTooltip"></div>

    <script>
        // Tooltip برای دلیل رد
        const tooltip = document.getElementById('rejectTooltip');

        document.addEventListener('mouseover', function(e) {
            if (e.target.classList.contains('reject-reason-icon')) {
                const text = e.target.getAttribute('data-tooltip');
                if (text) {
                    tooltip.textContent = text;
                    tooltip.classList.add('visible');

                    const rect = e.target.getBoundingClientRect();
                    tooltip.style.left = rect.left + 'px';
                    tooltip.style.top = (rect.top - tooltip.offsetHeight - 8) + 'px';
                }
            }
        });

        document.addEventListener('mouseout', function(e) {
            if (e.target.classList.contains('reject-reason-icon')) {
                tooltip.classList.remove('visible');
            }
        });

        function validateTimeOrder(startId, endId, errorMsg) {
            const startInput = document.getElementById(startId);
            const endInput = document.getElementById(endId);

            if (!startInput || !endInput) return true;

            endInput.addEventListener('change', function() {
                const start = startInput.value.trim();
                const end = endInput.value.trim();

                if (start && end) {
                    // تبدیل HH:MM به عدد برای مقایسه
                    const [sh, sm] = start.split(':').map(Number);
                    const [eh, em] = end.split(':').map(Number);
                    const startMinutes = sh * 60 + sm;
                    const endMinutes = eh * 60 + em;

                    if (endMinutes <= startMinutes) {
                        alert(errorMsg || 'ساعت پایان نمی‌تواند قبل یا برابر ساعت شروع باشد.');
                        endInput.value = '';
                        endInput.focus();
                    }
                }
            });
        }

        // برای مأموریت
        validateTimeOrder('missionStartTime', 'missionEndTime', 'ساعت پایان مأموریت نمی‌تواند قبل از ساعت شروع باشد.');

        // برای مرخصی
        validateTimeOrder('leaveStartTime', 'leaveEndTime', 'ساعت پایان مرخصی نمی‌تواند قبل از ساعت شروع باشد.');

        // برای پاس
        validateTimeOrder('passStartTime', 'passEndTime', 'ساعت پایان پاس نمی‌تواند قبل از ساعت شروع باشد.');

        // برای مشکل فنی
        validateTimeOrder('technicalStartTime', 'technicalEndTime', 'ساعت پایان مشکل فنی نمی‌تواند قبل از ساعت شروع باشد.');

        // برای فراموشی
        validateTimeOrder('forgetStartTime', 'forgetEndTime', 'ساعت پایان فراموشی نمی‌تواند قبل از ساعت شروع باشد.');
    </script>
    <script>
        (function() {
            function fmtHM(min) {
                min = Math.max(0, Math.round(min));
                const h = Math.floor(min / 60),
                    m = min % 60;
                return convertToFarsiNumber(h + ':' + String(m).padStart(2, '0'));
            }
            const tomanFromRial = rial => Math.round((rial || 0) / 10);

            function computeMonthCounts() {
                const counts = {
                    leave: 0,
                    mission: 0,
                    pass: 0,
                    technical: 0,
                    forget: 0
                };
                if (typeof REQUESTS_DATA === 'undefined' || typeof MY_USER_ID === 'undefined') return counts;
                const t = new Date();
                const jNow = gregorianToJalaliJS(t.getFullYear(), t.getMonth() + 1, t.getDate());
                REQUESTS_DATA.forEach(d => {
                    if (String(d.user_id) !== String(MY_USER_ID)) return;
                    const ds = (d.date_greg || '').split(' ')[0].split('-');
                    if (ds.length < 3) return;
                    const dj = gregorianToJalaliJS(+ds[0], +ds[1], +ds[2]);
                    if (dj[0] !== jNow[0] || dj[1] !== jNow[1]) return; // فقط ماه جاری
                    const valid = (d.type === 'pass') ? (d.status !== 'cancelled') : (d.status === 'approved');
                    if (!valid) return;
                    if (counts[d.type] !== undefined) counts[d.type]++;
                });
                return counts;
            }

            async function loadStatCards() {
                const elHM = document.getElementById('cardShortageHM');
                if (!elHM) return; // فقط در نمای کارمند وجود دارد
                try {
                    const res = await fetch('/api/attendance/monthly-report.php?t=' + Date.now());
                    const data = await res.json();
                    if (!data || !data.days) return;

                    const t = new Date();
                    const pad = n => String(n).padStart(2, '0');
                    const todayStr = t.getFullYear() + '-' + pad(t.getMonth() + 1) + '-' + pad(t.getDate());

                    let sumBefore = 0,
                        sumFinal = 0,
                        sumMoney = 0;
                    data.days.forEach(day => {
                        if (day.is_holiday) return;
                        if (day.date < todayStr) { // فقط تا دیروز
                            sumBefore += (day.shortage_minutes || 0); // قبل از ×۲
                            sumFinal += (day.final_shortage_minutes || 0); // بعد از ×۲
                            sumMoney += (day.shortage_money || 0); // ریال
                        }
                    });

                    elHM.textContent = fmtHM(sumFinal);
                    const icon = document.getElementById('shortageInfoIcon');
                    if (icon) icon.title = 'کسری واقعی (قبل از ضرب): ' + fmtHM(sumBefore) + ' — پس از ضرب در ۲: ' + fmtHM(sumFinal);

                    const penaltyToman = tomanFromRial(sumMoney);
                    const salaryToman = Math.max(0, tomanFromRial(MY_MONTHLY_SALARY - sumMoney));
                    document.getElementById('cardPenaltyToman').textContent = convertToFarsiNumber(formatNumber(penaltyToman)) + ' تومان';
                    document.getElementById('cardSalaryToman').textContent = convertToFarsiNumber(formatNumber(salaryToman)) + ' تومان';

                    const c = computeMonthCounts();
                    const total = c.leave + c.mission + c.pass + c.technical + c.forget;
                    const tooltipText = [
                        c.mission ? `مأموریت (${convertToFarsiNumber(c.mission)})` : '',
                        c.leave ? `مرخصی (${convertToFarsiNumber(c.leave)})` : '',
                        c.pass ? `پاس (${convertToFarsiNumber(c.pass)})` : '',
                        c.forget ? `فراموشی (${convertToFarsiNumber(c.forget)})` : '',
                        c.technical ? `مشکل فنی (${convertToFarsiNumber(c.technical)})` : '',
                    ].filter(Boolean).join(' , ') || 'درخواستی ثبت نشده';
                    document.getElementById('cardCounts').innerHTML =
                        convertToFarsiNumber(total) + ' درخواست';
                    const countIcon = document.getElementById('monthCountInfoIcon');
                    if (countIcon) countIcon.title = tooltipText;
                } catch (e) {
                    console.error('خطا در کارت‌های آماری:', e);
                }
            }

            document.addEventListener('DOMContentLoaded', loadStatCards);
        })();
    </script>
    <script>
        window.persianizePaging = function() {
            setTimeout(() => {
                document.querySelectorAll('.ag-paging-panel span, .ag-paging-panel button').forEach(el => {
                    if (el.childElementCount === 0 && !el.classList.contains('injected-az')) {
                        el.textContent = el.textContent
                            .replace(/Page/g, 'صفحه')
                            .replace(/\bof\b/g, 'از')
                            .replace(/\bto\b/g, 'تا')
                            .replace(/\d+/g, n => n.replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹' [d]));
                    }
                });
                document.querySelectorAll('.ag-paging-panel > span, .ag-paging-panel > div:not(.ag-paging-row-summary-panel):not(.ag-paging-page-size):not(.ag-paging-button-wrapper):not(.ag-paging-page-summary-panel)').forEach(el => {
                    if (el.textContent.trim() === 'از') el.remove();
                });
                const summary = document.querySelector('.ag-paging-row-summary-panel');
                if (summary) {
                    summary.querySelectorAll('.injected-az').forEach(el => el.remove());
                    const azSpan = document.createElement('span');
                    azSpan.textContent = 'از ';
                    azSpan.className = 'injected-az';
                    summary.insertBefore(azSpan, summary.firstChild);
                }
            }, 100);
        };
    </script>
</body>

</html>