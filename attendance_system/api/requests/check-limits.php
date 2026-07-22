<?php
/**
 * API بررسی سقف درخواست‌ها
 * بررسی می‌کند آیا کاربر به سقف تعداد/ساعت رسیده یا نه
 * 
 * مسیر: attendance_system/api/requests/check-limits.php
 * متد: GET
 * پارامترها: type (pass|mission|technical|forget), date (Y-m-d)
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings_helper.php';

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
$user_id = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
}
if (!$user_id) {
    $user_id = $auth->getUserFromToken();
}
if (!$user_id && isset($_COOKIE['auth_token'])) {
    $user_id = $auth->validateToken($_COOKIE['auth_token']);
}

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'عدم احراز هویت']);
    exit;
}

$app_settings = loadSettings($db);
$type = $_GET['type'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');

// ============================================
// تابع محاسبه ابتدای ماه شمسی (تبدیل به میلادی)
// ============================================
function getJalaliMonthStartGregorian($gregorian_date) {
    $parts = explode('-', $gregorian_date);
    $gy = intval($parts[0]);
    $gm = intval($parts[1]);
    $gd = intval($parts[2]);
    
    // تبدیل میلادی به جلالی
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy2 = ($gy <= 1600) ? ($gy - 621) : ($gy - 1600);
    $gy3 = ($gm > 2) ? ($gy2 + 1) : $gy2;
    $days = (365 * $gy2) + intval(($gy3 + 3) / 4) - intval(($gy3 + 99) / 100) + intval(($gy3 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * intval($days / 12053);
    $days %= 12053;
    $jy += 4 * intval($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intval(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $jm = ($days < 186) ? 1 + intval($days / 31) : 7 + intval(($days - 186) / 30);
    
    // تبدیل اول ماه جلالی به میلادی
    $jy_s = $jy;
    $jm_s = $jm;
    $jd_s = 1;
    
    $gy_r = ($jy_s < 979) ? 621 : 1600;
    if ($jy_s >= 979) $jy_s -= 979;
    $d = (365 * $jy_s) + (intval($jy_s / 33) * 8) + intval((($jy_s % 33) + 3) / 4) + 78 + $jd_s;
    if ($jm_s < 7)
        $d += ($jm_s - 1) * 31;
    else
        $d += (($jm_s - 7) * 30) + 186;
    $gy_r += 400 * intval($d / 146097);
    $d %= 146097;
    if ($d > 36524) {
        $gy_r += 100 * intval(--$d / 36524);
        $d %= 36524;
        if ($d >= 365) $d++;
    }
    $gy_r += 4 * intval($d / 1461);
    $d %= 1461;
    if ($d > 365) {
        $gy_r += intval(($d - 1) / 365);
        $d = ($d - 1) % 365;
    }
    $gd_r = $d + 1;
    $leap = (($gy_r % 4 == 0 && $gy_r % 100 != 0) || ($gy_r % 400 == 0));
    $sal = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $gm_r = 0;
    for ($gm_r = 0; $gm_r < 13; $gm_r++) {
        if ($gd_r <= $sal[$gm_r]) break;
        $gd_r -= $sal[$gm_r];
    }
    
    return sprintf('%04d-%02d-%02d', $gy_r, $gm_r, $gd_r);
}

$month_start = getJalaliMonthStartGregorian($date);
$today = date('Y-m-d');

$response = ['success' => true, 'limit_reached' => false, 'current_count' => 0, 'max_allowed' => 0];

// ============================================
// بررسی سقف بر اساس نوع درخواست
// ============================================
switch ($type) {
    case 'pass':
        // بررسی تعداد پاس در ماه
        $max_count = intval($app_settings['pass_max_count_monthly'] ?? 0);
        if ($max_count > 0) {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM pass_requests 
                WHERE user_id = ? AND pass_date >= ? AND pass_date <= ? 
                AND (status IS NULL OR status != 'cancelled')
            ");
            $stmt->execute([$user_id, $month_start, $today]);
            $current_count = intval($stmt->fetchColumn());
            
            $response['current_count'] = $current_count;
            $response['max_allowed'] = $max_count;
            
            if ($current_count >= $max_count) {
                $response['limit_reached'] = true;
                $response['message'] = "شما به سقف تعداد پاس در ماه ({$max_count} بار) رسیده‌اید. تعداد فعلی: {$current_count}";
                break;
            }
        }
        
        // بررسی ساعت پاس در ماه
        $max_hours_monthly = intval($app_settings['pass_max_hours_monthly'] ?? 0);
        if ($max_hours_monthly > 0) {
            $stmt = $db->prepare("
                SELECT SUM(
                    TIMESTAMPDIFF(MINUTE, CONCAT(pass_date, ' ', start_time), CONCAT(pass_date, ' ', end_time))
                ) as total_minutes 
                FROM pass_requests 
                WHERE user_id = ? AND pass_date >= ? AND pass_date <= ? 
                AND (status IS NULL OR status != 'cancelled')
            ");
            $stmt->execute([$user_id, $month_start, $today]);
            $total_minutes = intval($stmt->fetchColumn() ?: 0);
            $total_hours = round($total_minutes / 60, 1);
            
            if ($total_hours >= $max_hours_monthly) {
                $response['limit_reached'] = true;
                $response['message'] = "شما به سقف ساعت پاس در ماه ({$max_hours_monthly} ساعت) رسیده‌اید. مجموع فعلی: {$total_hours} ساعت";
            }
        }
        break;
        
    case 'mission':
        $max_hours = intval($app_settings['mission_max_hours_monthly'] ?? 0);
        if ($max_hours > 0) {
            $stmt = $db->prepare("
                SELECT SUM(TIMESTAMPDIFF(MINUTE, start_date, end_date)) as total_minutes 
                FROM mission_requests 
                WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ? 
                AND status != 'rejected'
            ");
            $stmt->execute([$user_id, $month_start, $today]);
            $total_minutes = intval($stmt->fetchColumn() ?: 0);
            $total_hours = round($total_minutes / 60, 1);
            
            $response['current_hours'] = $total_hours;
            $response['max_allowed'] = $max_hours;
            
            if ($total_hours >= $max_hours) {
                $response['limit_reached'] = true;
                $response['message'] = "شما به سقف ساعت مأموریت در ماه ({$max_hours} ساعت) رسیده‌اید. مجموع فعلی: {$total_hours} ساعت";
            }
        }
        break;
        
    case 'technical':
        $max_count = intval($app_settings['technical_max_monthly'] ?? 0);
        if ($max_count > 0) {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM technical_issues 
                WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ? 
                AND status != 'rejected'
            ");
            $stmt->execute([$user_id, $month_start, $today]);
            $current_count = intval($stmt->fetchColumn());
            
            $response['current_count'] = $current_count;
            $response['max_allowed'] = $max_count;
            
            if ($current_count >= $max_count) {
                $response['limit_reached'] = true;
                $response['message'] = "شما به سقف تعداد مشکل فنی در ماه ({$max_count} بار) رسیده‌اید. تعداد فعلی: {$current_count}";
            }
        }
        break;
        
    case 'forget':
        $max_count = intval($app_settings['forget_max_monthly'] ?? 0);
        if ($max_count > 0) {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM forget_requests 
                WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ? 
                AND status != 'rejected'
            ");
            $stmt->execute([$user_id, $month_start, $today]);
            $current_count = intval($stmt->fetchColumn());
            
            $response['current_count'] = $current_count;
            $response['max_allowed'] = $max_count;
            
            if ($current_count >= $max_count) {
                $response['limit_reached'] = true;
                $response['message'] = "شما به سقف تعداد فراموشی در ماه ({$max_count} بار) رسیده‌اید. تعداد فعلی: {$current_count}";
            }
        }
        break;
        
    default:
        $response['message'] = 'نوع درخواست نامعتبر';
        break;
}

echo json_encode($response);
