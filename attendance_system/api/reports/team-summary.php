<?php
/**
 * 📊 گزارش جامع تیم برای مدیران
 * نمایش کسرکار، حقوق و آمار کلی تمام پرسنل
 * 
 * مسیر: /attendance_system/api/reports/team-summary.php
 * متد: GET
 * پارامترها: ?year=1403&month=11 (اختیاری - پیش‌فرض ماه جاری)
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
$auth = new Auth($db);


// ============================================
// احراز هویت (مشابه requests.php)
// ============================================


$user_id = null;

// روش 1: SESSION
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    error_log("✅ User ID from SESSION: " . $user_id);
}

// روش 2: JWT Token از header
if (!$user_id) {
    $user_id = $auth->getUserFromToken();
    if ($user_id) {
        error_log("✅ User ID from JWT header: " . $user_id);
    }
}

// روش 3: JWT Token از Cookie
if (!$user_id && isset($_COOKIE['auth_token'])) {
    error_log("🔍 Trying cookie token...");
    $token = $_COOKIE['auth_token'];
    $user_id = $auth->validateToken($token);
    if ($user_id) {
        error_log("✅ User ID from cookie: " . $user_id);
    }
}

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

error_log("🔍 Final User ID: " . $user_id);

// ============================================
// بررسی دسترسی مدیریتی - دقیقاً مثل team-report.php
// ============================================
$stmt = $db->prepare("SELECT id, role, is_manager, is_supervisor, organization_id  FROM users WHERE id = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
// ✅ چک کردن organization_id
if (empty($user['organization_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'کاربر فاقد سازمان است']);
    exit;
}
$is_supervisor = ($user['role'] === 'supervisor' || $user['is_supervisor'] == 1);
$is_manager = ($user['role'] === 'manager' || $user['is_manager'] == 1);

if (!$is_supervisor && !$is_manager) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'شما دسترسی مدیریتی ندارید']);
    exit;
}

// ============================================
// توابع کمکی
// ============================================

function timeToMinutes($time) {
    if (empty($time)) return 0;
    $parts = explode(':', $time);
    return ((int)$parts[0] * 60) + (int)($parts[1] ?? 0);
}

function minutesToHM($minutes) {
    if ($minutes <= 0) return '0:00';
    $h = floor($minutes / 60);
    $m = $minutes % 60;
    return sprintf('%d:%02d', $h, $m);
}

function gregorianToJalali($gy, $gm, $gd) {
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) +
        (int)(($gy2 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * (int)($days / 12053);
    $days %= 12053;
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $days -= 186;
        $jm = 7 + (int)($days / 30);
        $jd = 1 + ($days % 30);
    }
    return array($jy, $jm, $jd);
}

function jalaliToGregorian($jy, $jm, $jd) {
    $jy = (int)$jy; $jm = (int)$jm; $jd = (int)$jd;
    $gy = ($jy < 979) ? 621 : 1600;
    if ($jy >= 979) $jy -= 979;
    $days = (365 * $jy) + ((int)($jy / 33) * 8) + (int)((($jy % 33) + 3) / 4) + 78 + $jd;
    if ($jm < 7) $days += ($jm - 1) * 31;
    else $days += (($jm - 7) * 30) + 186;
    $gy += 400 * (int)($days / 146097); $days %= 146097;
    if ($days > 36524) { $gy += 100 * (int)(--$days / 36524); $days %= 36524; if ($days >= 365) $days++; }
    $gy += 4 * (int)($days / 1461); $days %= 1461;
    if ($days > 365) { $gy += (int)(($days - 1) / 365); $days = ($days - 1) % 365; }
    $gd = $days + 1;
    $sal_a = array(0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    for ($gm = 0; $gm < 13; $gm++) { if ($gd <= $sal_a[$gm]) break; $gd -= $sal_a[$gm]; }
    return array($gy, $gm, $gd);
}

// ============================================
// تعیین بازه زمانی
// ============================================

// دریافت ماه و سال از پارامترها (شمسی)
$jalali_year = isset($_GET['year']) ? (int)$_GET['year'] : null;
$jalali_month = isset($_GET['month']) ? (int)$_GET['month'] : null;

// اگر پارامتر نداده شد، ماه جاری
if (!$jalali_year || !$jalali_month) {
    $today = date('Y-m-d');
    list($g_y, $g_m, $g_d) = explode('-', $today);
    list($jalali_year, $jalali_month, $jalali_day) = gregorianToJalali($g_y, $g_m, $g_d);
}

// تبدیل اول و آخر ماه شمسی به میلادی
list($g_start_y, $g_start_m, $g_start_d) = jalaliToGregorian($jalali_year, $jalali_month, 1);
$start_date = sprintf('%04d-%02d-%02d', $g_start_y, $g_start_m, $g_start_d);

// آخر ماه شمسی
$days_in_month = ($jalali_month <= 6) ? 31 : (($jalali_month <= 11) ? 30 : 29);
list($g_end_y, $g_end_m, $g_end_d) = jalaliToGregorian($jalali_year, $jalali_month, $days_in_month);
$end_date = sprintf('%04d-%02d-%02d', $g_end_y, $g_end_m, $g_end_d);

// محدود کردن به امروز
$today = date('Y-m-d');
if ($end_date > $today) {
    $end_date = $today;
}

error_log("📅 Period: $start_date to $end_date (Jalali: $jalali_year/$jalali_month)");

// ============================================
// دریافت لیست پرسنل بر اساس نقش
// دقیقاً مثل team-report.php ولی با اضافه کردن کاربر جاری
// ============================================

$all_user_ids = [];

// ========================================
// 🔵 SUPERVISOR: همه کاربران
// ========================================
if ($user['role'] === 'supervisor') {
    $stmt = $db->prepare("
        SELECT id
        FROM users
        WHERE is_active = 1
        AND organization_id = ?
        AND is_deleted = 0
        ORDER BY first_name, last_name

    ");
    $stmt->execute([$user['organization_id']]);

    $all_user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    error_log("👥 Team Summary - Supervisor viewing all " . count($all_user_ids) . " users");
} 
// ========================================
// 🟢 MANAGER: خودش + زیردستان مستقیم و غیرمستقیم
// ========================================
else {
    // مرحله 1: پیدا کردن زیردستان مستقیم
    $stmt = $db->prepare("
SELECT id
FROM users
WHERE manager_code = ?
  AND is_active = 1
  AND organization_id = ?
  AND is_deleted = 0

    ");
$stmt->execute([$user['id'], $user['organization_id']]);
    $direct_subordinates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    error_log("👥 Team Summary - User $user_id - Direct subordinates: " . (empty($direct_subordinates) ? 'NONE' : implode(',', $direct_subordinates)));
    
    // مرحله 2: پیدا کردن زیردستان غیرمستقیم (سطح دوم)
    $indirect_subordinates = [];
    if (!empty($direct_subordinates)) {
        $string_ids = array_map('strval', $direct_subordinates);
        $placeholders = str_repeat('?,', count($string_ids) - 1) . '?';
        
        $stmt = $db->prepare("
SELECT id
FROM users
WHERE manager_code IN ($placeholders)
  AND is_active = 1
  AND organization_id = ?
  AND is_deleted = 0

        ");
$params = array_merge($string_ids, [$user['organization_id']]);
        $indirect_subordinates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    error_log("👥 Team Summary - User $user_id - Indirect subordinates: " . (empty($indirect_subordinates) ? 'NONE' : implode(',', $indirect_subordinates)));
    
    // ترکیب: خود کاربر + زیردستان مستقیم + زیردستان غیرمستقیم
    $all_user_ids = array_unique(array_merge([$user_id], $direct_subordinates, $indirect_subordinates));
    
    error_log("👥 Team Summary - User $user_id - Total users (self + subordinates): " . implode(',', $all_user_ids));
}

// دریافت اطلاعات کامل کاربران
$users = [];
if (!empty($all_user_ids)) {
    $placeholders = str_repeat('?,', count($all_user_ids) - 1) . '?';
    $stmt = $db->prepare("
        SELECT 
            id,
            CONCAT(first_name, ' ', last_name) as full_name,
            activity_section,
            shift_count,
            shift_1_start,
            shift_1_end,
            shift_2_start,
            shift_2_end,
            daily_work_hours,
            monthly_salary,
            daily_salary
        FROM users
        WHERE id IN ($placeholders)
        AND is_active = 1
        AND organization_id = ?
  AND is_deleted = 0
        ORDER BY first_name, last_name
    ");
    $params = array_merge($all_user_ids, [$user['organization_id']]);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

error_log("👥 Found " . count($users) . " active users");

// تعطیلات
$stmt = $db->prepare("SELECT holiday_date, title FROM holidays WHERE holiday_date >= ? AND holiday_date <= ?");
$stmt->execute([$start_date, $end_date]);
$holiday_dates = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
    $holiday_dates[$h['holiday_date']] = $h['title'];
}

// تابع محاسبه overlap بین دو بازه زمانی
function calculateOverlapMinutes($slot_start, $slot_end, $req_start, $req_end) {
    $slot_start_mins = timeToMinutes($slot_start);
    $slot_end_mins = timeToMinutes($slot_end);
    $req_start_mins = timeToMinutes($req_start);
    $req_end_mins = timeToMinutes($req_end);
    
    $overlap_start = max($slot_start_mins, $req_start_mins);
    $overlap_end = min($slot_end_mins, $req_end_mins);
    
    return max(0, $overlap_end - $overlap_start);
}

// ============================================
// پردازش هر کاربر
// ============================================

$team_data = [];
$total_shortage_minutes = 0;
$total_shortage_money = 0;

foreach ($users as $user_item) {
    $uid = $user_item['id'];
    
    // دریافت رکوردهای حضور
    $stmt = $db->prepare("SELECT date, shift_number, check_in, check_out FROM attendance_records WHERE user_id = ? AND date >= ? AND date <= ? ORDER BY date, shift_number");
    $stmt->execute([$uid, $start_date, $end_date]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // گروه‌بندی بر اساس تاریخ
    $records_by_date = [];
    foreach ($records as $rec) {
        $records_by_date[$rec['date']][] = $rec;
    }
    
    // درخواست‌های تأییدشده
    $approved_requests = [];
    
    // مرخصی
    $stmt = $db->prepare("SELECT start_date, end_date, start_time, end_time FROM leave_requests WHERE user_id = ? AND status = 'approved' AND start_date >= ? AND start_date <= ?");
    $stmt->execute([$uid, $start_date, $end_date]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $req) {
        $approved_requests[$req['start_date']][] = [
            'type' => 'leave',
            'start_time' => $req['start_time'] ?? '00:00:00',
            'end_time' => $req['end_time'] ?? '23:59:59'
        ];
    }
    
    // مأموریت
    $stmt = $db->prepare("SELECT DATE(start_date) as date, TIME(start_date) as start_time, TIME(end_date) as end_time FROM mission_requests WHERE user_id = ? AND status = 'approved' AND DATE(start_date) >= ? AND DATE(start_date) <= ?");
    $stmt->execute([$uid, $start_date, $end_date]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $req) {
        $approved_requests[$req['date']][] = [
            'type' => 'mission',
            'start_time' => $req['start_time'],
            'end_time' => $req['end_time']
        ];
    }
    
    // پاس
    $stmt = $db->prepare("SELECT pass_date, start_time, end_time FROM pass_requests WHERE user_id = ? AND status = 'approved' AND pass_date >= ? AND pass_date <= ?");
    $stmt->execute([$uid, $start_date, $end_date]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $req) {
        $approved_requests[$req['pass_date']][] = [
            'type' => 'pass',
            'start_time' => $req['start_time'],
            'end_time' => $req['end_time']
        ];
    }
    
    // فراموشی
    $stmt = $db->prepare("SELECT DATE(start_date) as date, TIME(start_date) as start_time, TIME(end_date) as end_time FROM forget_requests WHERE user_id = ? AND status = 'approved' AND DATE(start_date) >= ? AND DATE(start_date) <= ?");
    $stmt->execute([$uid, $start_date, $end_date]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $req) {
        $approved_requests[$req['date']][] = [
            'type' => 'forget',
            'start_time' => $req['start_time'],
            'end_time' => $req['end_time']
        ];
    }
    
    // مشکل فنی
    $stmt = $db->prepare("SELECT DATE(start_date) as date, start_time, end_time FROM technical_issues WHERE user_id = ? AND status = 'approved' AND DATE(start_date) >= ? AND DATE(start_date) <= ?");
    $stmt->execute([$uid, $start_date, $end_date]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $req) {
        $approved_requests[$req['date']][] = [
            'type' => 'technical',
            'start_time' => $req['start_time'],
            'end_time' => $req['end_time']
        ];
    }
    
    // محاسبه آمار
    $present_days = 0;
    $total_work_minutes = 0;
    $late_count = 0;
    $early_leave_count = 0;
    $shortage_minutes = 0;
    $shortage_money = 0;
    $leave_days = 0;
    $mission_days = 0;
    $pass_count = 0;
    $forget_count = 0;
    $technical_count = 0;
    
    // نرخ ساعتی (طبق فرمول requests.php)
    $hourly_rate = ($user_item['monthly_salary'] > 0 && $user_item['daily_work_hours'] > 0) 
        ? $user_item['monthly_salary'] / 30 / $user_item['daily_work_hours'] : 0;
    
    $current_date = new DateTime($start_date);
    $end_date_obj = new DateTime($end_date);
    $today_obj = new DateTime($today);
    
    while ($current_date <= $end_date_obj) {
        $date_str = $current_date->format('Y-m-d');
        $day_name = $current_date->format('l');
        
        // جمعه و تعطیلات
        $is_holiday = ($day_name === 'Friday') || isset($holiday_dates[$date_str]);
        $is_past_day = ($current_date < $today_obj);
        
        // فقط روزهای کاری و گذشته
        if ($current_date > $today_obj || $is_holiday) {
            $current_date->modify('+1 day');
            continue;
        }
        
        $day_records = $records_by_date[$date_str] ?? [];
        $day_requests = $approved_requests[$date_str] ?? [];
        
        // استخراج زمان ورود و خروج شیفت 1
        $shift1_in = $shift1_out = null;
        foreach ($day_records as $rec) {
            if ($rec['shift_number'] == 1) {
                $shift1_in = $rec['check_in'] ? substr($rec['check_in'], 11, 5) : null;
                $shift1_out = $rec['check_out'] ? substr($rec['check_out'], 11, 5) : null;
            }
        }
        
        // شمارش همه انواع درخواست‌ها
        $has_leave = false;
        $has_mission = false;
        $has_pass = false;
        $has_forget = false;
        $has_technical = false;
        
        foreach ($day_requests as $req) {
            if ($req['type'] === 'leave') $has_leave = true;
            if ($req['type'] === 'mission') $has_mission = true;
            if ($req['type'] === 'pass') $has_pass = true;
            if ($req['type'] === 'forget') $has_forget = true;
            if ($req['type'] === 'technical') $has_technical = true;
        }
        
        if ($has_leave) $leave_days++;
        if ($has_mission) $mission_days++;
        if ($has_pass) $pass_count++;
        if ($has_forget) $forget_count++;
        if ($has_technical) $technical_count++;
        
        // محاسبه ساعات کار واقعی
        $day_work_minutes = 0;
        if ($shift1_in && $shift1_out) {
            $day_work_minutes = timeToMinutes($shift1_out) - timeToMinutes($shift1_in);
            $total_work_minutes += max(0, $day_work_minutes);
        }
        
        // شمارش روزهای حضور
        if ($shift1_in) {
            $present_days++;
            
            // شمارش تأخیر
            $shift_start = substr($user_item['shift_1_start'] ?? '08:00:00', 0, 5);
            if ($shift1_in > $shift_start) {
                $late_count++;
            }
            
            // شمارش زودتر رفتن
            $shift_end = substr($user_item['shift_1_end'] ?? '18:00:00', 0, 5);
            if ($shift1_out && $shift1_out < $shift_end) {
                $early_leave_count++;
            }
        }
        
        // ============================================
        // محاسبه کسری روز (طبق فرمول requests.php)
        // ============================================
        $shift_start = substr($user_item['shift_1_start'] ?? '08:00:00', 0, 5);
        $shift_end = substr($user_item['shift_1_end'] ?? '18:00:00', 0, 5);
        
        $shortage_slots = [];
        
        if (!$shift1_in && $is_past_day) {
            // عدم ورود - کل شیفت کسری
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
                // pass ها همیشه تأیید شده حساب می‌شن (مگر cancelled باشن)
                // بقیه باید approved باشن
                $is_valid = ($req['type'] === 'pass') ? true : true; // همه approved هستن چون از approved_requests می‌خونیم
                if (!$is_valid) continue;
                
                $req_start = substr($req['start_time'] ?? '00:00', 0, 5);
                $req_end = substr($req['end_time'] ?? '23:59', 0, 5);
                $overlap = calculateOverlapMinutes($slot['start'], $slot['end'], $req_start, $req_end);
                if ($overlap > 0) $slot_covered += $overlap;
            }
            $total_covered += min($slot_covered, $slot['minutes']);
        }
        
        // کسری پوشش نشده
        $uncovered = max(0, $initial_shortage - $total_covered);
        
        // کسری نهایی = دوبرابر! (طبق requests.php)
        $final_shortage = $uncovered * 2;
        
        // اضافه به کل کسری
        $shortage_minutes += $uncovered; // برای نمایش ساعتی
        
        // محاسبه کسری ریالی
        if ($hourly_rate > 0) {
            $shortage_money += round(($final_shortage / 60) * $hourly_rate, 0);
        }
        
        $current_date->modify('+1 day');
    }
    
    // گرد کردن کسری ریالی به نزدیک‌ترین 100,000 ریال (طبق requests.php)
    $shortage_money = floor($shortage_money / 100000) * 100000;
    
    // محاسبه کسرکار ساعتی
    $shortage_hours = $shortage_minutes / 60;
    
    // حقوق دریافتی (طبق requests.php)
    $net_salary = max(0, $user_item['monthly_salary'] - $shortage_money);
    
    // گرد کردن حقوق نهایی به نزدیک‌ترین 100,000 ریال
    $net_salary = floor($net_salary / 100000) * 100000;
    
    $team_data[] = [
        'user_id' => $uid,
        'name' => $user_item['full_name'],
        'section' => $user_item['activity_section'],
        'present_days' => $present_days,
        'total_work_hours' => round($total_work_minutes / 60, 2),
        'avg_daily_hours' => $present_days > 0 ? round(($total_work_minutes / $present_days) / 60, 2) : 0,
        'late_count' => $late_count,
        'early_leave_count' => $early_leave_count,
        'leave_days' => $leave_days,
        'mission_days' => $mission_days,
        'pass_count' => $pass_count,
        'forget_count' => $forget_count,
        'technical_count' => $technical_count,
        'total_requests' => $leave_days + $mission_days + $pass_count + $forget_count + $technical_count,
        'shortage_hours' => round($shortage_hours, 2),
        'shortage_money' => $shortage_money,
        'monthly_salary' => $user_item['monthly_salary'],
        'net_salary' => $net_salary
    ];
    
    $total_shortage_minutes += $shortage_minutes;
    $total_shortage_money += $shortage_money;
}

error_log("✅ Processed " . count($team_data) . " employees");

// ============================================
// خروجی
// ============================================

echo json_encode([
    'success' => true,
    'period' => [
        'jalali' => sprintf('%04d/%02d', $jalali_year, $jalali_month),
        'start_date' => $start_date,
        'end_date' => $end_date
    ],
    'summary' => [
        'total_employees' => count($team_data),
        'total_shortage_hours' => round($total_shortage_minutes / 60, 2),
        'total_shortage_money' => round($total_shortage_money, 0),
        'total_salary' => array_sum(array_column($team_data, 'monthly_salary')),
        'total_net_salary' => array_sum(array_column($team_data, 'net_salary'))
    ],
    'employees' => $team_data
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>