<?php
/**
 * API گزارش تیم - با سهمیه مرخصی
 * مسیر: api/attendance/team-report.php
 * 
 * 🎯 قوانین:
 * 1. مرخصی و پاس از سهمیه استفاده می‌کنند
 * 2. سهمیه ماهانه: 2 روز
 * 3. اگر سهمیه نداشته باشد، مرخصی/پاس بدون سهمیه × 1 (نه × 2)
 * 4. کسری نهایی = (کسری پوشش نشده × 2) + (مرخصی/پاس بدون سهمیه × 1)
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

    // استخراج توکن
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        echo json_encode(['success' => false, 'message' => 'توکن نامعتبر'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $token = $matches[1];
    $auth = new Auth();
    $user_id = $auth->validateToken($token);
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'توکن نامعتبر است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('خطا در اتصال به دیتابیس');
    }
    
    // دریافت اطلاعات کاربر
    $stmt = $db->prepare("SELECT id, role, is_manager, is_supervisor, organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!isset($user['organization_id'])) {
        echo json_encode(['success' => false, 'message' => 'سازمان کاربر تعریف نشده'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    
    $is_supervisor = ($user['role'] === 'supervisor' || $user['is_supervisor'] == 1);
    $is_manager = ($user['role'] === 'manager' || $user['is_manager'] == 1);
    
    if (!$is_supervisor && !$is_manager) {
        echo json_encode([
            'success' => false, 
            'message' => 'دسترسی غیرمجاز'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // پارامترها
    $year = isset($_GET['year']) ? (int)$_GET['year'] : null;
    $month = isset($_GET['month']) ? (int)$_GET['month'] : null;

    // ============================================
    // توابع کمکی
    // ============================================
    
    function timeToMinutes($time) {
        if (empty($time)) return 0;
        $parts = explode(':', $time);
        return ((int)$parts[0] * 60) + (int)($parts[1] ?? 0);
    }

    function calculateOverlapMinutes($start1, $end1, $start2, $end2) {
        $s1 = timeToMinutes($start1);
        $e1 = timeToMinutes($end1);
        $s2 = timeToMinutes($start2);
        $e2 = timeToMinutes($end2);
        if ($s1 >= $e2 || $s2 >= $e1) return 0;
        return max(0, min($e1, $e2) - max($s1, $s2));
    }
    
    function gregorianToJalali($gy, $gm, $gd) {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $jy = ($gy <= 1600) ? 0 : 979;
        $gy -= ($gy <= 1600) ? 621 : 1600;
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1];
        $jy += 33 * (int)($days / 12053); $days %= 12053;
        $jy += 4 * (int)($days / 1461); $days %= 1461;
        if ($days > 365) { $jy += (int)(($days - 1) / 365); $days = ($days - 1) % 365; }
        $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
        return [$jy, $jm, $jd];
    }

    function jalaliToGregorian($jy, $jm, $jd) {
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
        $sal_a = [0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 0; $gm < 13; $gm++) { if ($gd <= $sal_a[$gm]) break; $gd -= $sal_a[$gm]; }
        return [$gy, $gm, $gd];
    }

    function getJalaliMonthDays($year, $month) {
        if ($month <= 6) return 31;
        if ($month <= 11) return 30;
        return ((($year - ($year > 0 ? 474 : 473)) % 2820 + 474 + 38) * 682 % 2816 < 682) ? 30 : 29;
    }
    
    // ✅ محاسبه سهمیه مرخصی
    function calculateLeaveQuota($db, $user_id, $year, $month, $daily_work_hours) {
        // بازه زمانی: از اول سال تا آخر ماه جاری
        list($start_gy, $start_gm, $start_gd) = jalaliToGregorian($year, 1, 1);
        $year_start = sprintf('%04d-%02d-%02d', $start_gy, $start_gm, $start_gd);
        
        list($end_gy, $end_gm, $end_gd) = jalaliToGregorian($year, $month, 30);
        $month_end = sprintf('%04d-%02d-%02d', $end_gy, $end_gm, $end_gd);
        
        // سهمیه کل = ماه جاری × 2 روز
        $total_quota_minutes = $month * 2 * $daily_work_hours * 60;
        
        // مرخصی استفاده شده
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(
                CASE 
                    WHEN start_time IS NULL OR end_time IS NULL 
                    THEN ? * 60
                    ELSE (TIME_TO_SEC(end_time) - TIME_TO_SEC(start_time)) / 60
                END
            ), 0) as used_minutes
            FROM leave_requests 
            WHERE user_id = ? AND start_date >= ? AND start_date <= ? AND status = 'approved'
        ");
        $stmt->execute([$daily_work_hours, $user_id, $year_start, $month_end]);
        $leave_used = $stmt->fetchColumn();
        
        // پاس استفاده شده
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(
                (TIME_TO_SEC(end_time) - TIME_TO_SEC(start_time)) / 60
            ), 0) as used_minutes
            FROM pass_requests 
            WHERE user_id = ? AND pass_date >= ? AND pass_date <= ? 
            AND (status IS NULL OR status != 'cancelled')
        ");
        $stmt->execute([$user_id, $year_start, $month_end]);
        $pass_used = $stmt->fetchColumn();
        
        $total_used = $leave_used + $pass_used;
        $remaining = $total_quota_minutes - $total_used;
        
        return [
            'total_quota' => $total_quota_minutes,
            'used' => $total_used,
            'remaining' => $remaining
        ];
    }

    // تاریخ امروز
    date_default_timezone_set('Asia/Tehran');
    $today = date('Y-m-d');
    list($today_gy, $today_gm, $today_gd) = explode('-', $today);
    list($today_jy, $today_jm, $today_jd) = gregorianToJalali($today_gy, $today_gm, $today_gd);

    $j_y = $year ?? $today_jy;
    $j_m = $month ?? $today_jm;

    if ($j_m < 1 || $j_m > 12) $j_m = $today_jm;
    if ($j_y < 1400 || $j_y > $today_jy + 1) $j_y = $today_jy;

    $is_current_month = ($j_y == $today_jy && $j_m == $today_jm);
    $actual_day = $is_current_month ? $today_jd : getJalaliMonthDays($j_y, $j_m);
    $display_days = max($actual_day, 10);
    $max_month_days = getJalaliMonthDays($j_y, $j_m);
    $display_days = min($display_days, $max_month_days);

    list($start_gy, $start_gm, $start_gd) = jalaliToGregorian($j_y, $j_m, 1);
    list($end_gy, $end_gm, $end_gd) = jalaliToGregorian($j_y, $j_m, $actual_day);
    $start_of_month = sprintf('%04d-%02d-%02d', $start_gy, $start_gm, $start_gd);
    $end_of_month = sprintf('%04d-%02d-%02d', $end_gy, $end_gm, $end_gd);

    // دریافت لیست کاربران
    if ($user['role'] === 'supervisor') {
        $stmt = $db->prepare("SELECT id, first_name, last_name, shift_count, shift_1_start, shift_1_end, shift_2_start, shift_2_end, monthly_salary, daily_work_hours FROM users WHERE is_active = 1 AND organization_id = ? AND is_deleted = 0 ORDER BY first_name, last_name");
        $stmt->execute([$user['organization_id']]);
        $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE manager_id = ? AND is_active = 1 AND organization_id = ? AND is_deleted = 0");
        $stmt->execute([$user_id, $user['organization_id']]);
        $direct_subordinates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $indirect_subordinates = [];
        if (!empty($direct_subordinates)) {
            $string_ids = array_map('strval', $direct_subordinates);
            $placeholders = str_repeat('?,', count($string_ids) - 1) . '?';
            $stmt = $db->prepare("SELECT id FROM users WHERE manager_id IN ($placeholders) AND is_active = 1 AND organization_id = ? AND is_deleted = 0");
            $stmt->execute(array_merge($string_ids, [$user['organization_id']]));
            $indirect_subordinates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        $all_subordinates = array_unique(array_merge($direct_subordinates, $indirect_subordinates));
        
        if (empty($all_subordinates)) {
            echo json_encode([
                'success' => true,
                'members' => [],
                'summaries' => [],
                'month_days' => [],
                'attendance' => [],
                'requests' => [],
                'year' => $j_y,
                'month' => $j_m,
                'display_days' => $display_days,
                'subordinates_count' => 0
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $placeholders = str_repeat('?,', count($all_subordinates) - 1) . '?';
        $stmt = $db->prepare("SELECT id, first_name, last_name, shift_count, shift_1_start, shift_1_end, shift_2_start, shift_2_end, monthly_salary, daily_work_hours FROM users WHERE id IN ($placeholders) AND is_active = 1 AND organization_id = ? AND is_deleted = 0 ORDER BY first_name, last_name");
        $stmt->execute(array_merge($all_subordinates, [$user['organization_id']]));

        $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // تعطیلات
    $stmt = $db->prepare("SELECT holiday_date, title FROM holidays WHERE holiday_date >= ? AND holiday_date <= ?");
    $stmt->execute([$start_of_month, $end_of_month]);
    $holidays = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
        $holidays[$h['holiday_date']] = $h['title'];
    }

    $member_ids = array_column($team_members, 'id');
    $attendance_data = [];
    $requests_data = [];

    if (!empty($member_ids)) {
        $ph = implode(',', array_fill(0, count($member_ids), '?'));
        $params = array_merge($member_ids, [$start_of_month, $end_of_month]);

        // حضور و غیاب
        $stmt = $db->prepare("SELECT user_id, date, shift_number, check_in, check_out FROM attendance_records WHERE user_id IN ($ph) AND date >= ? AND date <= ? ORDER BY shift_number");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $attendance_data[$r['user_id']][$r['date']][$r['shift_number']] = [
                'in' => $r['check_in'] ? substr($r['check_in'], 11, 5) : null,
                'out' => $r['check_out'] ? substr($r['check_out'], 11, 5) : null
            ];
        }

        // درخواست‌ها
        $stmt = $db->prepare("SELECT user_id, DATE(start_date) as request_date, 'mission' as type, TIME(start_date) as start_time, TIME(end_date) as end_time, status FROM mission_requests WHERE user_id IN ($ph) AND DATE(start_date) >= ? AND DATE(start_date) <= ?");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $requests_data[$r['user_id']][$r['request_date']][] = [
                'type' => $r['type'],
                'status' => $r['status'],
                'start_time' => $r['start_time'],
                'end_time' => $r['end_time']
            ];
        }

        $stmt = $db->prepare("SELECT user_id, start_date as request_date, 'leave' as type, COALESCE(start_time, '00:00:00') as start_time, COALESCE(end_time, '23:59:59') as end_time, status FROM leave_requests WHERE user_id IN ($ph) AND start_date >= ? AND start_date <= ?");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $requests_data[$r['user_id']][$r['request_date']][] = [
                'type' => $r['type'],
                'status' => $r['status'],
                'start_time' => $r['start_time'],
                'end_time' => $r['end_time']
            ];
        }

        $stmt = $db->prepare("SELECT user_id, pass_date as request_date, 'pass' as type, start_time, end_time, CASE WHEN status = 'cancelled' THEN 'cancelled' ELSE 'approved' END as status FROM pass_requests WHERE user_id IN ($ph) AND pass_date >= ? AND pass_date <= ?");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $requests_data[$r['user_id']][$r['request_date']][] = [
                'type' => $r['type'],
                'status' => $r['status'],
                'start_time' => $r['start_time'],
                'end_time' => $r['end_time']
            ];
        }

        $stmt = $db->prepare("SELECT user_id, DATE(start_date) as request_date, 'technical' as type, start_time, end_time, status FROM technical_issues WHERE user_id IN ($ph) AND DATE(start_date) >= ? AND DATE(start_date) <= ?");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $requests_data[$r['user_id']][$r['request_date']][] = [
                'type' => $r['type'],
                'status' => $r['status'],
                'start_time' => $r['start_time'],
                'end_time' => $r['end_time']
            ];
        }

        $stmt = $db->prepare("SELECT user_id, DATE(start_date) as request_date, 'forget' as type, TIME(start_date) as start_time, TIME(end_date) as end_time, status FROM forget_requests WHERE user_id IN ($ph) AND DATE(start_date) >= ? AND DATE(start_date) <= ?");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $requests_data[$r['user_id']][$r['request_date']][] = [
                'type' => $r['type'],
                'status' => $r['status'],
                'start_time' => $r['start_time'],
                'end_time' => $r['end_time']
            ];
        }
    }

    // روزهای ماه
    $month_days = [];
    $day_names_short = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];

    for ($day = 1; $day <= $display_days; $day++) {
        list($gy, $gm, $gd) = jalaliToGregorian($j_y, $j_m, $day);
        $date_str = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
        $dow = (new DateTime($date_str))->format('w');
        $dow_persian = ($dow + 1) % 7;
        $is_friday = ($dow == 5);
        $is_future = ($day > $actual_day);

        $month_days[] = [
            'day' => $day,
            'date' => $date_str,
            'dow' => $day_names_short[$dow_persian],
            'is_holiday' => isset($holidays[$date_str]) || $is_friday,
            'holiday_title' => $holidays[$date_str] ?? ($is_friday ? 'جمعه' : ''),
            'is_today' => ($date_str === $today),
            'is_future' => $is_future
        ];
    }

    // ✅ محاسبه کسری با لحاظ سهمیه
    $user_summaries = [];
    
    foreach ($team_members as $member) {
        $member_id = $member['id'];
        $daily_work_hours = (float)$member['daily_work_hours'];
        
        // محاسبه سهمیه مرخصی
        $quota = calculateLeaveQuota($db, $member_id, $j_y, $j_m, $daily_work_hours);
        
        $total_shortage_minutes = 0;
        $absent_days = 0;
        $late_days = 0;
        $without_quota_minutes = 0; // مرخصی/پاس بدون سهمیه

        $shift1_start = $member['shift_1_start'] ? substr($member['shift_1_start'], 0, 5) : '08:00';
        $shift1_end = $member['shift_1_end'] ? substr($member['shift_1_end'], 0, 5) : '18:00';
        $shift2_start = $member['shift_2_start'] ? substr($member['shift_2_start'], 0, 5) : null;
        $shift2_end = $member['shift_2_end'] ? substr($member['shift_2_end'], 0, 5) : null;
        $shift_count = (int)($member['shift_count'] ?? 1);
        
        $monthly_salary = (float)$member['monthly_salary'];
        $hourly_rate = ($monthly_salary > 0 && $daily_work_hours > 0) 
            ? $monthly_salary / 30 / $daily_work_hours 
            : 0;

        foreach ($month_days as $d) {
            if ($d['is_holiday'] || $d['is_future']) continue;

            $att = $attendance_data[$member_id][$d['date']] ?? [];
            $day_requests = $requests_data[$member_id][$d['date']] ?? [];

            // محاسبه کسری روز
            $shortage_slots = [];
            $shift1_in = isset($att[1]) ? $att[1]['in'] : null;
            $shift1_out = isset($att[1]) ? $att[1]['out'] : null;
            
            $is_past_day = !$d['is_today'] && !$d['is_future'];
            
            if (!$shift1_in && $is_past_day) {
                $shift_minutes = timeToMinutes($shift1_end) - timeToMinutes($shift1_start);
                $shortage_slots[] = ['start' => $shift1_start, 'end' => $shift1_end, 'minutes' => $shift_minutes];
                $absent_days++;
            } else if ($shift1_in) {
                if ($shift1_in > $shift1_start) {
                    $delay = timeToMinutes($shift1_in) - timeToMinutes($shift1_start);
                    $shortage_slots[] = ['start' => $shift1_start, 'end' => $shift1_in, 'minutes' => $delay];
                    $late_days++;
                }
                // 🔒 اگر خروج/ورود بدون خروج قبل از شروع شیفت باشد، بازه از
                // شروع شیفت حساب شود نه از خود خروج (وگرنه دقایق قبل از شیفت
                // هم به‌غلط کسری حساب می‌شوند)
                if ($shift1_out && $shift1_out < $shift1_end) {
                    $early_start = max($shift1_out, $shift1_start);
                    $early = timeToMinutes($shift1_end) - timeToMinutes($early_start);
                    $shortage_slots[] = ['start' => $early_start, 'end' => $shift1_end, 'minutes' => $early];
                }
                if ($shift1_in && !$shift1_out && $is_past_day) {
                    $no_checkout_start = max($shift1_in, $shift1_start);
                    $no_checkout = timeToMinutes($shift1_end) - timeToMinutes($no_checkout_start);
                    $shortage_slots[] = ['start' => $no_checkout_start, 'end' => $shift1_end, 'minutes' => $no_checkout];
                }
            }
            
            // شیفت 2
            if ($shift_count >= 2 && $shift2_start && $shift2_end) {
                $shift2_in = isset($att[2]) ? $att[2]['in'] : null;
                $shift2_out = isset($att[2]) ? $att[2]['out'] : null;
                
                if (!$shift2_in && $is_past_day) {
                    $shift_minutes = timeToMinutes($shift2_end) - timeToMinutes($shift2_start);
                    $shortage_slots[] = ['start' => $shift2_start, 'end' => $shift2_end, 'minutes' => $shift_minutes];
                } else if ($shift2_in) {
                    if ($shift2_in > $shift2_start) {
                        $delay = timeToMinutes($shift2_in) - timeToMinutes($shift2_start);
                        $shortage_slots[] = ['start' => $shift2_start, 'end' => $shift2_in, 'minutes' => $delay];
                    }
                    if ($shift2_out && $shift2_out < $shift2_end) {
                        $early_start = max($shift2_out, $shift2_start);
                        $early = timeToMinutes($shift2_end) - timeToMinutes($early_start);
                        $shortage_slots[] = ['start' => $early_start, 'end' => $shift2_end, 'minutes' => $early];
                    }
                    if ($shift2_in && !$shift2_out && $is_past_day) {
                        $no_checkout_start = max($shift2_in, $shift2_start);
                        $no_checkout = timeToMinutes($shift2_end) - timeToMinutes($no_checkout_start);
                        $shortage_slots[] = ['start' => $no_checkout_start, 'end' => $shift2_end, 'minutes' => $no_checkout];
                    }
                }
            }

            // ✅ محاسبه پوشش با لحاظ سهمیه
            $initial_shortage = 0;
            $total_covered = 0;
            $day_without_quota = 0;

            foreach ($shortage_slots as $slot) {
                $initial_shortage += $slot['minutes'];
                $slot_covered = 0;
                
                foreach ($day_requests as $req) {
                    $is_valid = ($req['type'] === 'pass') ? ($req['status'] !== 'cancelled') : ($req['status'] === 'approved');
                    if (!$is_valid) continue;

                    $req_start = substr($req['start_time'] ?? '00:00', 0, 5);
                    $req_end = substr($req['end_time'] ?? '23:59', 0, 5);

                    $overlap = calculateOverlapMinutes($slot['start'], $slot['end'], $req_start, $req_end);
                    
                    if ($overlap > 0) {
                        // ✅ بررسی سهمیه (فقط برای مرخصی و پاس)
                        if (in_array($req['type'], ['leave', 'pass'])) {
                            if ($quota['remaining'] > 0) {
                                // دارای سهمیه - عادی پوشش بده
                                $slot_covered += $overlap;
                            } else {
                                // بدون سهمیه - به کسری بدون سهمیه اضافه کن
                                $day_without_quota += $overlap;
                            }
                        } else {
                            // مأموریت، مشکل فنی، فراموشی - عادی پوشش بده
                            $slot_covered += $overlap;
                        }
                    }
                }
                
                $total_covered += min($slot_covered, $slot['minutes']);
            }
            
            $without_quota_minutes += $day_without_quota;

            $uncovered = max(0, $initial_shortage - $total_covered);
            $final_shortage_with_multiplier = $uncovered * 2; // کسری پوشش نشده × 2
            $final_shortage_without_quota = $day_without_quota; // مرخصی/پاس بدون سهمیه × 1
            
            $total_shortage_minutes += $final_shortage_with_multiplier + $final_shortage_without_quota;
        }
        
        // محاسبه کسر کار ریالی
        $shortage_money = 0;
        if ($hourly_rate > 0 && $total_shortage_minutes > 0) {
            $shortage_money = round(($total_shortage_minutes / 60) * $hourly_rate, 0);
        }

        $user_summaries[$member_id] = [
            'shortage_minutes' => $total_shortage_minutes,
            'shortage_money' => $shortage_money,
            'absent_days' => $absent_days,
            'late_days' => $late_days,
            'without_quota_minutes' => $without_quota_minutes,
            'quota_info' => $quota,
            'name' => $member['first_name'] . ' ' . $member['last_name']
        ];
    }

    echo json_encode([
        'success' => true,
        'members' => $team_members,
        'summaries' => $user_summaries,
        'month_days' => $month_days,
        'attendance' => $attendance_data,
        'requests' => $requests_data,
        'year' => $j_y,
        'month' => $j_m,
        'display_days' => $display_days,
        'subordinates_count' => count($team_members),
        'user_role' => $user['role'],
        'current_user_id' => $user_id
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("❌ Team Report API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور',
        'error' => 'internal_error'
    ], JSON_UNESCAPED_UNICODE);
}
?>