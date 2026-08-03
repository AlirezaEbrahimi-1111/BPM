<?php
/**
 * گزارش ماهانه حضور و غیاب
 * نسخه 3 - رفع مشکل پاس
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
date_default_timezone_set('Asia/Tehran');
// require_once '../includes/settings_helper.php';

// ============================================
// توابع کمکی
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
        return '0:00';
    return sprintf('%d:%02d', floor($minutes / 60), $minutes % 60);
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

function gregorianToJalali($gy, $gm, $gd)
{
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + (int) (($gy2 + 3) / 4) - (int) (($gy2 + 99) / 100) +
        (int) (($gy2 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1];
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

function jalaliToGregorian($jy, $jm, $jd)
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

function getShiftShortageSlots($check_in, $check_out, $shift_start, $shift_end, $is_past_day = false)
{
    // منبع واحد محاسبه: منطق در salary_calc.php (sc_getShiftShortageSlots)
    $shift_start = substr($shift_start ?? '00:00', 0, 5);
    $shift_end = substr($shift_end ?? '00:00', 0, 5);
    $ci = $check_in ? substr($check_in, 0, 5) : null;
    $co = $check_out ? substr($check_out, 0, 5) : null;
    if ($shift_start === '00:00' && $shift_end === '00:00')
        return [];
    return sc_getShiftShortageSlots($ci, $co, $shift_start, $shift_end, $is_past_day);
}

function calculateDailyShortage($shortage_slots, $day_requests, $app_settings)
{
    // منبع واحد محاسبه: منطق در salary_calc.php (sc_calculateDailyShortage)
    return sc_calculateDailyShortage($shortage_slots, $day_requests, $app_settings);
}

// ============================================
// برنامه اصلی
// ============================================

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
$auth = new Auth($db);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings_helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/attendance_system/includes/salary_calc.php';
$app_settings = loadSettings($db);
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
    
try {

    // اطلاعات کاربر
    $stmt = $db->prepare("SELECT shift_count, shift_1_start, shift_1_end, shift_2_start, shift_2_end, monthly_salary, daily_work_hours FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user)
        throw new Exception('کاربر یافت نشد');

    // بازه ماه شمسی
    $today = date('Y-m-d'); // همیشه امروزِ واقعی (برای تشخیصِ گذشته/آینده در ادامه)
    list($g_y, $g_m, $g_d) = explode('-', $today);
    list($cur_j_y, $cur_j_m, $cur_j_d) = gregorianToJalali($g_y, $g_m, $g_d);

    // ✅ ماهِ هدف: از querystring (مرورِ ماه‌های قبل) یا پیش‌فرض ماهِ جاری
    $j_y = isset($_GET['jy']) ? (int) $_GET['jy'] : (int) $cur_j_y;
    $j_m = isset($_GET['jm']) ? (int) $_GET['jm'] : (int) $cur_j_m;
    if ($j_m < 1 || $j_m > 12)
        $j_m = (int) $cur_j_m;
    if ($j_y < 1300 || $j_y > 1500)
        $j_y = (int) $cur_j_y;

    list($g_start_y, $g_start_m, $g_start_d) = jalaliToGregorian($j_y, $j_m, 1);
    $start_of_month = sprintf('%04d-%02d-%02d', $g_start_y, $g_start_m, $g_start_d);
    // ✅ آخرین روزِ ماهِ شمسی (مستقل از تابع تبدیل) — بر اساس طول ماه‌های شمسی
    $__jy = (int) $j_y;
    $__jm = (int) $j_m;
    if ($__jm <= 6) {
        $__last_day = 31;                 // فروردین تا شهریور
    } elseif ($__jm <= 11) {
        $__last_day = 30;                 // مهر تا بهمن
    } else {
        // اسفند: ۳۰ روز در سال کبیسه، وگرنه ۲۹
        $__leap = ((($__jy + 12) % 33) % 4 === 1);
        $__last_day = $__leap ? 30 : 29;
    }
    list($__ge_y, $__ge_m, $__ge_d) = jalaliToGregorian($__jy, $__jm, $__last_day);
    $end_of_month = sprintf('%04d-%02d-%02d', (int)$__ge_y, (int)$__ge_m, (int)$__ge_d);

    // رکوردهای حضور
    $stmt = $db->prepare("SELECT date, shift_number, check_in, check_out FROM attendance_records WHERE user_id = ? AND date >= ? AND date <= ? ORDER BY date, shift_number");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $records_by_date = [];
    foreach ($records as $rec) {
        $records_by_date[$rec['date']][] = $rec;
    }

    // تعطیلات
    $stmt = $db->prepare("SELECT holiday_date, title FROM holidays WHERE holiday_date >= ? AND holiday_date <= ?");
    $stmt->execute([$start_of_month, $end_of_month]);
    $holiday_dates = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
        $holiday_dates[$h['holiday_date']] = $h['title'];
    }

    // ============================================
    // ✅ دریافت درخواست‌ها - کوئری‌های جداگانه برای اطمینان
    // ============================================
    $all_requests = [];

    // مأموریت
    $stmt = $db->prepare("SELECT 'mission' as type,request_code, DATE(start_date) as request_date, TIME(start_date) as start_time, TIME(end_date) as end_time, status FROM mission_requests WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ?");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $all_requests = array_merge($all_requests, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // مرخصی
    $stmt = $db->prepare("SELECT 'leave' as type,request_code, start_date as request_date, COALESCE(start_time, '00:00:00') as start_time, COALESCE(end_time, '23:59:59') as end_time, status FROM leave_requests WHERE user_id = ? AND start_date >= ? AND start_date <= ?");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $all_requests = array_merge($all_requests, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // ✅ پاس - بدون فیلتر status (چون می‌تواند خالی باشد)
    $stmt = $db->prepare("SELECT 'pass' as type,request_code, pass_date as request_date, start_time, end_time, CASE WHEN status = 'cancelled' THEN 'cancelled' ELSE 'approved' END as status FROM pass_requests WHERE user_id = ? AND pass_date >= ? AND pass_date <= ?");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $pass_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $all_requests = array_merge($all_requests, $pass_results);

    // مشکل فنی
    $stmt = $db->prepare("SELECT 'technical' as type,request_code, DATE(start_date) as request_date, start_time, end_time, status FROM technical_issues WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ?");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $all_requests = array_merge($all_requests, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // فراموشی
    $stmt = $db->prepare("SELECT 'forget' as type,request_code, DATE(start_date) as request_date, TIME(start_date) as start_time, TIME(end_date) as end_time, status FROM forget_requests WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ?");
    $stmt->execute([$user_id, $start_of_month, $end_of_month]);
    $all_requests = array_merge($all_requests, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // گروه‌بندی بر اساس تاریخ
    $requests_by_date = [];
    foreach ($all_requests as $req) {
        $requests_by_date[$req['request_date']][] = $req;
    }

    // روزهای کاری
    $current_date_temp = new DateTime($start_of_month);
    $end_date_temp = new DateTime($today);   // شمارش روزهای کاری فقط تا امروز
    $working_days_count = 0;
    while ($current_date_temp <= $end_date_temp) {
        $d = $current_date_temp->format('Y-m-d');
        if ($current_date_temp->format('l') !== 'Friday' && !isset($holiday_dates[$d]))
            $working_days_count++;
        $current_date_temp->modify('+1 day');
    }

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

    // پردازش روزها
    $days = [];
    $current_date = new DateTime($start_of_month);
    $end_date = new DateTime($end_of_month);
    $today_date = new DateTime($today);

    $day_names = ['Saturday' => 'شنبه', 'Sunday' => 'یکشنبه', 'Monday' => 'دوشنبه', 'Tuesday' => 'سه‌شنبه', 'Wednesday' => 'چهارشنبه', 'Thursday' => 'پنج‌شنبه', 'Friday' => 'جمعه'];

    $total_initial_shortage = 0;
    $total_final_shortage = 0;
    $total_shortage_money = 0;

    while ($current_date <= $end_date) {
        $date_str = $current_date->format('Y-m-d');
        $day_name_en = $current_date->format('l');
        $day_name = $day_names[$day_name_en] ?? '';

        list($y, $m, $d) = explode('-', $date_str);
        list($jy, $jm, $jd) = gregorianToJalali($y, $m, $d);
        $jalali_date = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

        $is_friday = ($day_name_en === 'Friday');
        $is_holiday = isset($holiday_dates[$date_str]) || $is_friday;
        $holiday_title = $holiday_dates[$date_str] ?? ($is_friday ? 'جمعه' : null);
        $is_past_day = ($current_date < $today_date);
        $is_future_day = ($current_date > $today_date);   // ✅ روز آینده

        $day_records = $records_by_date[$date_str] ?? [];
        $shift1_in = $shift1_out = $shift2_in = $shift2_out = null;

        // تطبیقِ هوشمندِ شیفت بر اساسِ ساعتِ ورود (قانون گزینه ۳)
        // ورودِ بعد از پایانِ شیفت ۱ ⟵ متعلق به شیفت ۲، در غیر این صورت شیفت ۱
        $mr_has_two_shifts = ($user['shift_count'] >= 2 && $user['shift_2_start'] && $user['shift_2_end']);
        $mr_shift1_end_hm = $user['shift_1_end'] ? substr($user['shift_1_end'], 0, 5) : null;

        foreach ($day_records as $rec) {
            $rec_in = $rec['check_in'] ? substr($rec['check_in'], 11, 5) : null;
            $rec_out = $rec['check_out'] ? substr($rec['check_out'], 11, 5) : null;

            // تعیینِ شیفتِ مقصد بر اساسِ ساعتِ ورود
            $target = 1;
            if ($mr_has_two_shifts && $rec_in !== null && $mr_shift1_end_hm !== null && $rec_in >= $mr_shift1_end_hm) {
                $target = 2;
            }

            if ($target === 1) {
                if ($shift1_in === null && $shift1_out === null) {
                    $shift1_in = $rec_in;
                    $shift1_out = $rec_out;
                }
            } else {
                if ($shift2_in === null && $shift2_out === null) {
                    $shift2_in = $rec_in;
                    $shift2_out = $rec_out;
                }
            }
        }

        $day_requests = $requests_by_date[$date_str] ?? [];

        // ✅ فیلتر درخواست‌های معتبر برای نمایش
        $valid_requests = [];
        foreach ($day_requests as $req) {
            $is_valid = false;
            if ($req['type'] === 'pass') {
                $is_valid = ($req['status'] !== 'cancelled');
            } else {
                $is_valid = ($req['status'] === 'approved');
            }
            if ($is_valid) {
                $valid_requests[] = $req;
            }
        }

        $initial_shortage_minutes = 0;
        $final_shortage_minutes = 0;
        $covered_minutes = 0;
        $shortage_money = 0;
        $shortage_slots = [];

        if (!$is_holiday && !$is_future_day) {
            if ($user['shift_count'] >= 1 && $user['shift_1_start'] && $user['shift_1_end']) {
                $slots = getShiftShortageSlots($shift1_in, $shift1_out, $user['shift_1_start'], $user['shift_1_end'], $is_past_day);
                $shortage_slots = array_merge($shortage_slots, $slots);
            }
            if ($user['shift_count'] >= 2 && $user['shift_2_start'] && $user['shift_2_end']) {
                $slots = getShiftShortageSlots($shift2_in, $shift2_out, $user['shift_2_start'], $user['shift_2_end'], $is_past_day);
                $shortage_slots = array_merge($shortage_slots, $slots);
            }

            $shortage_data = calculateDailyShortage($shortage_slots, $day_requests, $app_settings);
            $initial_shortage_minutes = $shortage_data['initial_shortage_minutes'];
            $final_shortage_minutes = $shortage_data['final_shortage_minutes'];
            $covered_minutes = $shortage_data['covered_minutes'];

            if ($hourly_rate > 0) {
                $shortage_money = round(($final_shortage_minutes / 60) * $hourly_rate, 0);
            }
        }

        $days[] = [
            'date' => $date_str,
            'jalali_date' => $jalali_date,
            'day_name' => $day_name,
            'is_holiday' => $is_holiday,
            'is_future' => $is_future_day,
            'holiday_title' => $holiday_title,
            'shift1_in' => $shift1_in,
            'shift1_out' => $shift1_out,
            'shift2_in' => $shift2_in,
            'shift2_out' => $shift2_out,
            'shortage_minutes' => $initial_shortage_minutes,
            'shortage_hours' => round($initial_shortage_minutes / 60, 2),
            'shortage_hms' => minutesToHM($initial_shortage_minutes),
            'final_shortage_minutes' => $final_shortage_minutes,
            'final_shortage' => round($final_shortage_minutes / 60, 2),
            'final_shortage_hms' => minutesToHM($final_shortage_minutes),
            'covered_minutes' => $covered_minutes,
            'shortage_money' => $shortage_money,
            'requests' => $day_requests,           // همه درخواست‌ها
            'valid_requests' => $valid_requests,   // ✅ فقط معتبرها برای نمایش
            'shortage_slots' => $shortage_slots
        ];

        $total_initial_shortage += $initial_shortage_minutes / 60;
        $total_final_shortage += $final_shortage_minutes / 60;
        $total_shortage_money += $shortage_money;

        $current_date->modify('+1 day');
    }

    echo json_encode([
        'success' => true,
        'user_id' => $user_id,
        'shift_count' => (int) $user['shift_count'],
        'shift_1_start' => $user['shift_1_start'],
        'shift_1_end' => $user['shift_1_end'],
        'shift_2_start' => $user['shift_2_start'],
        'shift_2_end' => $user['shift_2_end'],
        'monthly_salary' => (float) $user['monthly_salary'],
        'daily_work_hours' => (float) $user['daily_work_hours'],
        'working_days_count' => $working_days_count,
        'hourly_rate' => round($hourly_rate, 0),
        'start_of_month' => $start_of_month,
        'end_of_month' => $end_of_month,
        'total_initial_shortage' => round($total_initial_shortage, 2),
        'total_initial_shortage_hms' => minutesToHM(round($total_initial_shortage * 60)),
        'total_final_shortage' => round($total_final_shortage, 2),
        'total_final_shortage_hms' => minutesToHM(round($total_final_shortage * 60)),
        'total_shortage_money' => $total_shortage_money,
        'days' => $days,
        // ✅ DEBUG: تعداد پاس‌ها
        'debug_pass_count' => count($pass_results)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Monthly report error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>