<?php
/**
 * API: دریافت عملکرد کاربر در ۶ ماه اخیر
 * برای نمودار در گزارش تیم
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
date_default_timezone_set('Asia/Tehran');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}


$auth = new Auth($db);

// احراز هویت
$current_user_id = $_SESSION['user_id'] ?? null;
if (!$current_user_id) $current_user_id = $auth->getUserFromToken();
if (!$current_user_id && isset($_COOKIE['auth_token'])) {
    $current_user_id = $auth->validateToken($_COOKIE['auth_token']);
}

if (!$current_user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$months_count = isset($_GET['months']) ? min((int)$_GET['months'], 12) : 6;

if (!$target_user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user_id']);
    exit;
}

// توابع تاریخ
function gregorianToJalali($gy, $gm, $gd) {
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365*$gy) + (int)(($gy2+3)/4) - (int)(($gy2+99)/100) + (int)(($gy2+399)/400) - 80 + $gd + $g_d_m[$gm-1];
    $jy += 33 * (int)($days/12053); $days %= 12053;
    $jy += 4 * (int)($days/1461); $days %= 1461;
    if ($days > 365) { $jy += (int)(($days-1)/365); $days = ($days-1) % 365; }
    $jm = ($days < 186) ? 1 + (int)($days/31) : 7 + (int)(($days-186)/30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days-186) % 30));
    return [$jy, $jm, $jd];
}

function jalaliToGregorian($jy, $jm, $jd) {
    $gy = ($jy < 979) ? 621 : 1600;
    if ($jy >= 979) $jy -= 979;
    $days = (365*$jy) + ((int)($jy/33)*8) + (int)((($jy%33)+3)/4) + 78 + $jd;
    if ($jm < 7) $days += ($jm-1)*31; else $days += (($jm-7)*30) + 186;
    $gy += 400 * (int)($days/146097); $days %= 146097;
    if ($days > 36524) { $gy += 100 * (int)(--$days/36524); $days %= 36524; if ($days >= 365) $days++; }
    $gy += 4 * (int)($days/1461); $days %= 1461;
    if ($days > 365) { $gy += (int)(($days-1)/365); $days = ($days-1) % 365; }
    $gd = $days + 1;
    $sal_a = [0,31,(($gy%4==0 && $gy%100!=0)||($gy%400==0))?29:28,31,30,31,30,31,31,30,31,30,31];
    for ($gm = 0; $gm < 13; $gm++) { if ($gd <= $sal_a[$gm]) break; $gd -= $sal_a[$gm]; }
    return [$gy, $gm, $gd];
}

function getJalaliMonthDays($year, $month) {
    if ($month <= 6) return 31;
    if ($month <= 11) return 30;
    return ((($year - ($year > 0 ? 474 : 473)) % 2820 + 474 + 38) * 682 % 2816 < 682) ? 30 : 29;
}

function timeToMinutes($time) {
    if (empty($time)) return 0;
    $parts = explode(':', $time);
    return ((int)$parts[0] * 60) + (int)($parts[1] ?? 0);
}

$persian_months = ['','فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];

// اطلاعات کاربر هدف
$stmt = $db->prepare("SELECT shift_count, shift_1_start, shift_1_end, shift_2_start, shift_2_end FROM users WHERE id = ?");
$stmt->execute([$target_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$shift1_start = $user['shift_1_start'] ? substr($user['shift_1_start'], 0, 5) : '08:00';
$shift1_end = $user['shift_1_end'] ? substr($user['shift_1_end'], 0, 5) : '18:00';
$shift2_start = $user['shift_2_start'] ? substr($user['shift_2_start'], 0, 5) : null;
$shift2_end = $user['shift_2_end'] ? substr($user['shift_2_end'], 0, 5) : null;
$shift_count = (int)($user['shift_count'] ?? 1);

// محاسبه ۶ ماه اخیر
$today = date('Y-m-d');
list($cur_gy, $cur_gm, $cur_gd) = explode('-', $today);
list($cur_jy, $cur_jm, $cur_jd) = gregorianToJalali($cur_gy, $cur_gm, $cur_gd);

$result_months = [];

for ($i = $months_count - 1; $i >= 0; $i--) {
    $jm = $cur_jm - $i;
    $jy = $cur_jy;
    while ($jm < 1) { $jm += 12; $jy--; }
    
    $days_in_month = getJalaliMonthDays($jy, $jm);
    
    list($start_gy, $start_gm, $start_gd) = jalaliToGregorian($jy, $jm, 1);
    list($end_gy, $end_gm, $end_gd) = jalaliToGregorian($jy, $jm, $days_in_month);
    
    $start_date = sprintf('%04d-%02d-%02d', $start_gy, $start_gm, $start_gd);
    $end_date = sprintf('%04d-%02d-%02d', $end_gy, $end_gm, $end_gd);
    
    // تعطیلات این ماه
    $stmt = $db->prepare("SELECT holiday_date FROM holidays WHERE holiday_date >= ? AND holiday_date <= ?");
    $stmt->execute([$start_date, $end_date]);
    $holidays = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $h) {
        $holidays[$h] = true;
    }
    
    // حضور و غیاب این ماه
    $stmt = $db->prepare("SELECT date, shift_number, check_in, check_out FROM attendance_records WHERE user_id = ? AND date >= ? AND date <= ?");
    $stmt->execute([$target_user_id, $start_date, $end_date]);
    $attendance = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $attendance[$r['date']][$r['shift_number']] = [
            'in' => $r['check_in'] ? substr($r['check_in'], 11, 5) : null,
            'out' => $r['check_out'] ? substr($r['check_out'], 11, 5) : null
        ];
    }
    
    $total_work_minutes = 0;
    $total_shortage_minutes = 0;
    $present_days = 0;
    $absent_days = 0;
    
    // حلقه روزها
    for ($d = 1; $d <= $days_in_month; $d++) {
        list($gy, $gm, $gd) = jalaliToGregorian($jy, $jm, $d);
        $date_str = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
        
        // روز آینده را رد کن
        if ($date_str > $today) continue;
        
        // تعطیلات
        $dow = (new DateTime($date_str))->format('w');
        $is_friday = ($dow == 5);
        if ($is_friday || isset($holidays[$date_str])) continue;
        
        $day_att = $attendance[$date_str] ?? [];
        $has_attendance = false;
        
        // شیفت 1
        if (isset($day_att[1]) && $day_att[1]['in']) {
            $has_attendance = true;
            $in = timeToMinutes($day_att[1]['in']);
            $out = $day_att[1]['out'] ? timeToMinutes($day_att[1]['out']) : timeToMinutes($shift1_end);
            $total_work_minutes += max(0, $out - $in);
            
            $expected_start = timeToMinutes($shift1_start);
            $expected_end = timeToMinutes($shift1_end);
            if ($in > $expected_start) $total_shortage_minutes += ($in - $expected_start);
            if ($out < $expected_end && $day_att[1]['out']) $total_shortage_minutes += ($expected_end - $out);
        }
        
        // شیفت 2
        if ($shift_count >= 2 && $shift2_start && $shift2_end && isset($day_att[2]) && $day_att[2]['in']) {
            $in = timeToMinutes($day_att[2]['in']);
            $out = $day_att[2]['out'] ? timeToMinutes($day_att[2]['out']) : timeToMinutes($shift2_end);
            $total_work_minutes += max(0, $out - $in);
            
            $expected_start = timeToMinutes($shift2_start);
            $expected_end = timeToMinutes($shift2_end);
            if ($in > $expected_start) $total_shortage_minutes += ($in - $expected_start);
            if ($out < $expected_end && $day_att[2]['out']) $total_shortage_minutes += ($expected_end - $out);
        }
        
        if ($has_attendance) {
            $present_days++;
        } else {
            $absent_days++;
        }
    }
    
    $result_months[] = [
        'year' => $jy,
        'month' => $jm,
        'month_name' => $persian_months[$jm],
        'work_hours' => round($total_work_minutes / 60, 1),
        'shortage_hours' => round($total_shortage_minutes / 60, 1),
        'present_days' => $present_days,
        'absent_days' => $absent_days
    ];
}

echo json_encode([
    'success' => true,
    'user_id' => $target_user_id,
    'months' => $result_months
], JSON_UNESCAPED_UNICODE);