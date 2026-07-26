<?php
/**
 * فایل مشترک محاسبهٔ کسری و حقوق — منبع واحد محاسبات حقوقی
 * مسیر نصب: /attendance_system/includes/salary_calc.php
 *
 * نکته: توابع با پیشوند sc_ تعریف شده‌اند تا با توابع موجودِ
 * monthly-report.php (timeToMinutes و ...) تداخل نکنند.
 *
 * منطق دقیقاً مطابق نسخهٔ تأییدشده:
 *   - عدم ورود → کل شیفت کسری
 *   - عدم خروج در روز گذشته → کل شیفت کسری (A-1)
 *   - تأخیر ورود / خروج زودهنگام → بازهٔ مربوطه
 *   - پوشش با درخواست‌ها: پاس (غیرلغو) + بقیه (approved)
 *   - کسری نهایی = پوشش‌نشده × shortage_multiplier (از تنظیمات)
 *   - نرخ ساعتی = حقوق ÷ (روزهای غیرجمعهٔ ماه) ÷ ساعت‌کاری‌روزانه
 *   - «تا دیروز» = فقط روزهایی که تاریخشان < امروز
 */

if (!function_exists('sc_timeToMinutes')) {
    function sc_timeToMinutes($time)
    {
        if (empty($time))
            return 0;
        $parts = explode(':', $time);
        return ((int) $parts[0] * 60) + (int) ($parts[1] ?? 0);
    }
}

if (!function_exists('sc_minutesToHM')) {
    function sc_minutesToHM($minutes)
    {
        if ($minutes <= 0)
            return '0:00';
        return sprintf('%d:%02d', floor($minutes / 60), $minutes % 60);
    }
}

if (!function_exists('sc_minutesToSignedHM')) {
    function sc_minutesToSignedHM($minutes)
    {
        $minutes = (int) round($minutes);
        $neg = $minutes < 0;
        return ($neg ? '-' : '') . sc_minutesToHM(abs($minutes));
    }
}

if (!function_exists('sc_overlapMinutes')) {
    function sc_overlapMinutes($start1, $end1, $start2, $end2)
    {
        $s1 = sc_timeToMinutes($start1);
        $e1 = sc_timeToMinutes($end1);
        $s2 = sc_timeToMinutes($start2);
        $e2 = sc_timeToMinutes($end2);
        if ($s1 >= $e2 || $s2 >= $e1)
            return 0;
        return max(0, min($e1, $e2) - max($s1, $s2));
    }
}

if (!function_exists('sc_gregorianToJalali')) {
    function sc_gregorianToJalali($gy, $gm, $gd)
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
}

if (!function_exists('sc_jalaliToGregorian')) {
    function sc_jalaliToGregorian($jy, $jm, $jd)
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
}

/**
 * طول ماهِ شمسی (آخرین روز)
 */
if (!function_exists('sc_jalaliMonthLength')) {
    function sc_jalaliMonthLength($jy, $jm)
    {
        $jy = (int) $jy;
        $jm = (int) $jm;
        if ($jm <= 6)
            return 31;
        if ($jm <= 11)
            return 30;
        $leap = ((($jy + 12) % 33) % 4 === 1);
        return $leap ? 30 : 29;
    }
}

/**
 * بازهٔ میلادیِ یک ماهِ شمسی → ['start'=>Y-m-d, 'end'=>Y-m-d]
 */
if (!function_exists('sc_jalaliMonthRange')) {
    function sc_jalaliMonthRange($jy, $jm)
    {
        list($gsy, $gsm, $gsd) = sc_jalaliToGregorian($jy, $jm, 1);
        $start = sprintf('%04d-%02d-%02d', $gsy, $gsm, $gsd);
        $last = sc_jalaliMonthLength($jy, $jm);
        list($gey, $gem, $ged) = sc_jalaliToGregorian($jy, $jm, $last);
        $end = sprintf('%04d-%02d-%02d', (int) $gey, (int) $gem, (int) $ged);
        return ['start' => $start, 'end' => $end];
    }
}

/**
 * بازه‌های کسریِ یک شیفت (نسخهٔ A-1)
 */
if (!function_exists('sc_getShiftShortageSlots')) {
    function sc_getShiftShortageSlots($check_in_time, $check_out_time, $shift_start, $shift_end, $is_past_day)
    {
        $slots = [];
        $shift_minutes = sc_timeToMinutes($shift_end) - sc_timeToMinutes($shift_start);

        // عدم ورود → کل شیفت کسری
        if (!$check_in_time) {
            $slots[] = ['start' => $shift_start, 'end' => $shift_end, 'minutes' => $shift_minutes, 'type' => 'no_checkin'];
            return $slots;
        }

        // عدم خروج در روز گذشته → کل شیفت کسری (A-1)
        if (!$check_out_time && $is_past_day) {
            $slots[] = ['start' => $shift_start, 'end' => $shift_end, 'minutes' => $shift_minutes, 'type' => 'no_checkout'];
            return $slots;
        }

        // تأخیر ورود
        // تأخیر ورود (با سقفِ پایانِ شیفت: تأخیر هیچ‌وقت از طولِ شیفت بیشتر نمی‌شود)
        if ($check_in_time > $shift_start) {
            $late_end = ($check_in_time < $shift_end) ? $check_in_time : $shift_end;
            $delay = sc_timeToMinutes($late_end) - sc_timeToMinutes($shift_start);
            if ($delay > 0)
                $slots[] = ['start' => $shift_start, 'end' => $late_end, 'minutes' => $delay, 'type' => 'late_arrival'];
        }

        // خروج زودهنگام
        if ($check_out_time && $check_out_time < $shift_end) {
            $early = sc_timeToMinutes($shift_end) - sc_timeToMinutes($check_out_time);
            $slots[] = ['start' => $check_out_time, 'end' => $shift_end, 'minutes' => $early, 'type' => 'early_departure'];
        }

        return $slots;
    }
}

/**
 * کسریِ یک روز (با پوشش درخواست‌ها و ضرب نهایی)
 */
if (!function_exists('sc_calculateDailyShortage')) {
    function sc_calculateDailyShortage($shortage_slots, $day_requests, $app_settings)
    {
        if (empty($shortage_slots)) {
            return ['initial_shortage_minutes' => 0, 'final_shortage_minutes' => 0, 'covered_minutes' => 0, 'uncovered_minutes' => 0];
        }

        $initial = 0;
        foreach ($shortage_slots as $slot) {
            $initial += $slot['minutes'];
        }

        $total_covered = 0;
        foreach ($shortage_slots as $slot) {
            $slot_covered = 0;
            foreach ($day_requests as $req) {
                $is_valid = ($req['type'] === 'pass')
                    ? ($req['status'] !== 'cancelled')
                    : ($req['status'] === 'approved');
                if (!$is_valid)
                    continue;
                $req_start = substr($req['start_time'] ?? '00:00', 0, 5);
                $req_end = substr($req['end_time'] ?? '23:59', 0, 5);
                $overlap = sc_overlapMinutes($slot['start'], $slot['end'], $req_start, $req_end);
                if ($overlap > 0)
                    $slot_covered += $overlap;
            }
            $total_covered += min($slot_covered, $slot['minutes']);
        }

        $uncovered = max(0, $initial - $total_covered);
        $mult = isset($app_settings['shortage_multiplier']) ? (float) $app_settings['shortage_multiplier'] : 2;
        $final = $uncovered * $mult;

        return [
            'initial_shortage_minutes' => $initial,
            'covered_minutes' => $total_covered,
            'uncovered_minutes' => $uncovered,
            'final_shortage_minutes' => $final
        ];
    }
}

/**
 * گزارش حقوقِ یک کاربر برای یک ماهِ شمسی (تا دیروز).
 *
 * @param PDO    $db
 * @param array  $userRow      شامل: id, shift_count, shift_1_start, shift_1_end, shift_2_start, shift_2_end, monthly_salary, daily_work_hours
 * @param string $start_of_month  Y-m-d (اول ماهِ شمسی به میلادی)
 * @param string $end_of_month    Y-m-d (آخر ماهِ شمسی به میلادی)
 * @param string $today           Y-m-d (امروز)
 * @param array  $app_settings    خروجی loadSettings()
 * @param array  $holiday_dates   [Y-m-d => title] تعطیلاتِ بازه (یک‌بار توسط فراخواننده گرفته می‌شود)
 * @return array
 */
if (!function_exists('sc_computeUserSalaryReport')) {
    function sc_computeUserSalaryReport($db, $userRow, $start_of_month, $end_of_month, $today, $app_settings, $holiday_dates, $with_days = false)
    {
        $userId = (int) $userRow['id'];
        $monthly_salary = (float) ($userRow['monthly_salary'] ?? 0);
        $daily_work_hours = (float) ($userRow['daily_work_hours'] ?? 0);
        $shift_count = (int) ($userRow['shift_count'] ?? 0);

        // مخرجِ غیرجمعهٔ کلِ ماه
        $divisor = 0;
        $d1 = new DateTime($start_of_month);
        $d2 = new DateTime($end_of_month);
        while ($d1 <= $d2) {
            if ($d1->format('l') !== 'Friday')
                $divisor++;
            $d1->modify('+1 day');
        }
        if ($divisor < 1)
            $divisor = 1;

        $hourly_rate = ($monthly_salary > 0 && $daily_work_hours > 0)
            ? $monthly_salary / $divisor / $daily_work_hours : 0;

        // رکوردهای حضور
        $stmt = $db->prepare("SELECT date, shift_number, check_in, check_out FROM attendance_records WHERE user_id = ? AND date >= ? AND date <= ? ORDER BY date, shift_number");
        $stmt->execute([$userId, $start_of_month, $end_of_month]);
        $records_by_date = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rec) {
            $records_by_date[$rec['date']][] = $rec;
        }

        // درخواست‌ها (همان ۵ کوئریِ monthly-report)
        $all_requests = [];
        $run = function ($sql) use ($db, $userId, $start_of_month, $end_of_month) {
            $s = $db->prepare($sql);
            $s->execute([$userId, $start_of_month, $end_of_month]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        };
        $all_requests = array_merge($all_requests, $run("SELECT 'mission' as type, DATE(start_date) as request_date, TIME(start_date) as start_time, TIME(end_date) as end_time, status FROM mission_requests WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ?"));
        $all_requests = array_merge($all_requests, $run("SELECT 'leave' as type, start_date as request_date, COALESCE(start_time,'00:00:00') as start_time, COALESCE(end_time,'23:59:59') as end_time, status FROM leave_requests WHERE user_id = ? AND start_date >= ? AND start_date <= ?"));
        $all_requests = array_merge($all_requests, $run("SELECT 'pass' as type, pass_date as request_date, start_time, end_time, CASE WHEN status='cancelled' THEN 'cancelled' ELSE 'approved' END as status FROM pass_requests WHERE user_id = ? AND pass_date >= ? AND pass_date <= ?"));
        $all_requests = array_merge($all_requests, $run("SELECT 'technical' as type, DATE(start_date) as request_date, start_time, end_time, status FROM technical_issues WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ?"));
        $all_requests = array_merge($all_requests, $run("SELECT 'forget' as type, DATE(start_date) as request_date, TIME(start_date) as start_time, TIME(end_date) as end_time, status FROM forget_requests WHERE user_id = ? AND DATE(start_date) >= ? AND DATE(start_date) <= ?"));

        $requests_by_date = [];
        foreach ($all_requests as $req) {
            $requests_by_date[$req['request_date']][] = $req;
        }

        // حلقهٔ روزها — جمعِ حقوقی فقط روی «روزهای قبل از امروزِ غیرتعطیل».
        // در صورت $collect_days، جزئیاتِ همهٔ روزها برای نمایش در مودال جمع می‌شود.
        $total_final = 0;
        $total_before = 0;
        $total_money = 0;
        $days = [];

        $day_names_fa = [
            'Saturday' => 'شنبه', 'Sunday' => 'یکشنبه', 'Monday' => 'دوشنبه',
            'Tuesday' => 'سه‌شنبه', 'Wednesday' => 'چهارشنبه', 'Thursday' => 'پنج‌شنبه', 'Friday' => 'جمعه'
        ];

        $cur = new DateTime($start_of_month);
        $end = new DateTime($end_of_month);
        while ($cur <= $end) {
            $date_str = $cur->format('Y-m-d');
            $dow_en = $cur->format('l');
            $is_friday = ($dow_en === 'Friday');
            $is_holiday = isset($holiday_dates[$date_str]) || $is_friday;
            $is_past_day = ($date_str < $today);
            $is_counted = (!$is_holiday && $is_past_day);

            // ورود/خروجِ روز (برای نمایش، حتی در روزهای محاسبه‌نشده)
            $day_records = $records_by_date[$date_str] ?? [];
            // ورود/خروج شیفت‌ها — تطبیقِ هوشمند بر اساسِ ساعتِ ورود (قانون گزینه ۳)
            // ورودِ بعد از پایانِ شیفت ۱ ⟵ متعلق به شیفت ۲، در غیر این صورت شیفت ۱
            $s1in = $s1out = $s2in = $s2out = null;
            $day_records = $records_by_date[$date_str] ?? [];

            $has_two_shifts = ($shift_count >= 2 && $userRow['shift_2_start'] && $userRow['shift_2_end']);
            $shift1_end_hm = $userRow['shift_1_end'] ? substr($userRow['shift_1_end'], 0, 5) : null;

            foreach ($day_records as $rec) {
                $rec_in = $rec['check_in'] ? substr($rec['check_in'], 11, 5) : null;
                $rec_out = $rec['check_out'] ? substr($rec['check_out'], 11, 5) : null;

                // تعیینِ شیفتِ مقصد بر اساسِ ساعتِ ورود
                $target = 1;
                if ($has_two_shifts && $rec_in !== null && $shift1_end_hm !== null && $rec_in >= $shift1_end_hm) {
                    $target = 2;
                }

                if ($target === 1) {
                    // اگر قبلاً پر شده، خالیِ بعدی را پر نکن (اولویت با اولین رکوردِ شیفت ۱)
                    if ($s1in === null && $s1out === null) {
                        $s1in = $rec_in;
                        $s1out = $rec_out;
                    }
                } else {
                    if ($s2in === null && $s2out === null) {
                        $s2in = $rec_in;
                        $s2out = $rec_out;
                    }
                }
            }

            $d_initial = 0;
            $d_covered = 0;
            $d_uncovered = 0;
            $d_final = 0;
            $d_money = 0;

            if ($is_counted) {
                $slots = [];
                if ($shift_count >= 1 && $userRow['shift_1_start'] && $userRow['shift_1_end']) {
                    $slots = array_merge($slots, sc_getShiftShortageSlots($s1in, $s1out, $userRow['shift_1_start'], $userRow['shift_1_end'], $is_past_day));
                }
                if ($shift_count >= 2 && $userRow['shift_2_start'] && $userRow['shift_2_end']) {
                    $slots = array_merge($slots, sc_getShiftShortageSlots($s2in, $s2out, $userRow['shift_2_start'], $userRow['shift_2_end'], $is_past_day));
                }

                $day_requests = $requests_by_date[$date_str] ?? [];
                $sd = sc_calculateDailyShortage($slots, $day_requests, $app_settings);

                $d_initial = $sd['initial_shortage_minutes'];
                $d_covered = $sd['covered_minutes'];
                $d_uncovered = $sd['uncovered_minutes'];
                $d_final = $sd['final_shortage_minutes'];
                $d_money = ($hourly_rate > 0) ? round(($d_final / 60) * $hourly_rate, 0) : 0;

                $total_before += $d_uncovered;
                $total_final += $d_final;
                $total_money += $d_money;
            }

            if ($with_days) {
                list($gy2, $gm2, $gd2) = explode('-', $date_str);
                list($jy2, $jm2, $jd2) = sc_gregorianToJalali((int) $gy2, (int) $gm2, (int) $gd2);

                // درخواست‌های معتبرِ روز (پاسِ غیرلغو + بقیه approved)
                $valid_reqs = [];
                foreach (($requests_by_date[$date_str] ?? []) as $req) {
                    $ok = ($req['type'] === 'pass') ? ($req['status'] !== 'cancelled') : ($req['status'] === 'approved');
                    if ($ok) {
                        $valid_reqs[] = [
                            'type' => $req['type'],
                            'start_time' => substr($req['start_time'] ?? '', 0, 5),
                            'end_time' => substr($req['end_time'] ?? '', 0, 5),
                        ];
                    }
                }

                $days[] = [
                    'date' => $date_str,
                    'jalali_date' => sprintf('%04d/%02d/%02d', $jy2, $jm2, $jd2),
                    'day_name' => $day_names_fa[$dow_en] ?? '',
                    'is_holiday' => $is_holiday,
                    'holiday_title' => $holiday_dates[$date_str] ?? ($is_friday ? 'جمعه' : null),
                    'is_counted' => $is_counted,
                    'shift1_in' => $s1in,
                    'shift1_out' => $s1out,
                    'shift2_in' => $s2in,
                    'shift2_out' => $s2out,
                    'initial_minutes' => (int) round($d_initial),
                    'initial_hms' => sc_minutesToHM((int) round($d_initial)),
                    'covered_minutes' => (int) round($d_covered),
                    'covered_hms' => sc_minutesToHM((int) round($d_covered)),
                    'uncovered_minutes' => (int) round($d_uncovered),
                    'uncovered_hms' => sc_minutesToHM((int) round($d_uncovered)),
                    'final_minutes' => (int) round($d_final),
                    'final_hms' => sc_minutesToHM((int) round($d_final)),
                    'money' => $d_money,
                    'requests' => $valid_reqs,
                ];
            }

            $cur->modify('+1 day');
        }

        $salary_received = $monthly_salary - $total_money;

        return [
            'user_id' => $userId,
            'monthly_salary' => $monthly_salary,         // ریال
            'hourly_rate' => round($hourly_rate, 0),     // ریال/ساعت
            'divisor_days' => $divisor,                  // روزهای غیرجمعهٔ ماه
            'final_minutes' => (int) round($total_final),     // کسری ×ضریب (دقیقه) تا دیروز
            'before_minutes' => (int) round($total_before),   // کسری قبل از ضرب (دقیقه)
            'final_hms' => sc_minutesToHM((int) round($total_final)),
            'before_hms' => sc_minutesToHM((int) round($total_before)),
            'shortage_money' => $total_money,            // ریال (جریمه تا دیروز)
            'salary_received' => $salary_received,       // ریال (حقوق − جریمه)
            'days' => $days,                             // جزئیات روزانه (فقط وقتی $collect_days=true پر می‌شود)
        ];
    }
}

/**
 * جمع کل ساعتِ مرخصی + پاسِ معتبرِ یک کاربر در کلِ بازهٔ ماه (بدون محدودیتِ «تا دیروز»)
 * و مقایسه با سهمیهٔ ماهانه (۲ روزِ کاریِ همان کاربر، بر اساسِ daily_work_hours).
 *
 * قراردادِ اعتبارِ درخواست‌ها هماهنگ با sc_calculateDailyShortage است:
 *   - مرخصی: فقط status = 'approved'
 *   - پاس: هر چیزی جز status = 'cancelled'
 * مرخصیِ بدون start_time/end_time (تمام‌روز) معادلِ یک روزِ کاریِ کامل (daily_work_hours) حساب می‌شود.
 * فقط تاریخ شروع (start_date / pass_date) ملاک است — هماهنگ با کوئریِ مشابه در sc_computeUserSalaryReport.
 */
if (!function_exists('sc_computeLeavePassQuota')) {
    function sc_computeLeavePassQuota($db, $userRow, $start_of_month, $end_of_month)
    {
        $userId = (int) $userRow['id'];
        $daily_work_hours = (float) ($userRow['daily_work_hours'] ?? 0);
        $full_day_minutes = $daily_work_hours * 60;

        $total_minutes = 0;

        $stmt = $db->prepare("SELECT start_time, end_time FROM leave_requests WHERE user_id = ? AND start_date >= ? AND start_date <= ? AND status = 'approved'");
        $stmt->execute([$userId, $start_of_month, $end_of_month]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (empty($r['start_time']) || empty($r['end_time'])) {
                $total_minutes += $full_day_minutes;
            } else {
                $total_minutes += max(0, sc_timeToMinutes(substr($r['end_time'], 0, 5)) - sc_timeToMinutes(substr($r['start_time'], 0, 5)));
            }
        }

        $stmt = $db->prepare("SELECT start_time, end_time FROM pass_requests WHERE user_id = ? AND pass_date >= ? AND pass_date <= ? AND status != 'cancelled'");
        $stmt->execute([$userId, $start_of_month, $end_of_month]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (!empty($r['start_time']) && !empty($r['end_time'])) {
                $total_minutes += max(0, sc_timeToMinutes(substr($r['end_time'], 0, 5)) - sc_timeToMinutes(substr($r['start_time'], 0, 5)));
            }
        }

        $quota_minutes = 2 * $full_day_minutes;
        $remaining_minutes = $quota_minutes - $total_minutes;

        return [
            'used_minutes' => (int) round($total_minutes),
            'used_hms' => sc_minutesToHM((int) round($total_minutes)),
            'quota_minutes' => (int) round($quota_minutes),
            'remaining_minutes' => (int) round($remaining_minutes),
            'remaining_hms' => sc_minutesToSignedHM($remaining_minutes),
        ];
    }
}
?>
