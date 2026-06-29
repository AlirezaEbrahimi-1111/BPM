<?php
/**
 * کتابخانه تبدیل تاریخ شمسی (جلالی) ↔ میلادی
 * مسیر: /attendance_system/includes/date_helper.php
 * 
 * ✅ نسخه اصلاح شده - با قابلیت نمایش ساعت
 */

/**
 * تبدیل تاریخ میلادی (Gregorian) به شمسی (Jalali)
 * 
 * @param int $gy سال میلادی
 * @param int $gm ماه میلادی (1-12)
 * @param int $gd روز میلادی (1-31)
 * @return array [سال شمسی, ماه شمسی, روز شمسی]
 */
if (!function_exists('gregorianToJalali')) {
    function gregorianToJalali($gy, $gm, $gd)
    {
        $gy = (int) $gy;
        $gm = (int) $gm;
        $gd = (int) $gd;

        // الگوریتم دقیق تبدیل - نسخه بهبود یافته
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        // محاسبه روز جولیان
        $jy = ($gy <= 1600) ? 0 : 979;
        $gy -= ($gy <= 1600) ? 621 : 1600;

        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = (365 * $gy) + ((int) (($gy2 + 3) / 4)) - ((int) (($gy2 + 99) / 100)) +
            ((int) (($gy2 + 399) / 400)) - 80 + $gd + $g_d_m[$gm - 1];

        $jy += 33 * ((int) ($days / 12053));
        $days %= 12053;
        $jy += 4 * ((int) ($days / 1461));
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

        return [(int) $jy, (int) $jm, (int) $jd];
    }
}

/**
 * تبدیل تاریخ شمسی (Jalali) به میلادی (Gregorian)
 * 
 * @param int $jy سال شمسی
 * @param int $jm ماه شمسی (1-12)
 * @param int $jd روز شمسی (1-31)
 * @return array [سال میلادی, ماه میلادی, روز میلادی]
 */
if (!function_exists('jalaliToGregorian')) {
    function jalaliToGregorian($jy, $jm, $jd)
    {
        $jy = (int) $jy;
        $jm = (int) $jm;
        $jd = (int) $jd;

        $gy = ($jy <= 979) ? 621 : 1600;
        $jy -= ($jy <= 979) ? 0 : 979;

        if ($jm < 7) {
            $days = ($jm - 1) * 31;
        } else {
            $days = (($jm - 7) * 30) + 186;
        }
        $days += $jd;

        $gy += 33 * ((int) ($days / 12053));
        $days %= 12053;

        $leap = 1;
        if ($days >= 366) {
            $leap = 0;
            $days--;
            $gy += 4 * ((int) ($days / 1461));
            $days %= 1461;
        }

        if ($days >= 366) {
            $leap = 0;
            $days--;
            $gy += (int) ($days / 365);
            $days = $days % 365;
        }

        $g_d_m = [0, 31, ($leap ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        $gm = 0;
        foreach ($g_d_m as $v) {
            $gm++;
            if ($days < $v)
                break;
            $days -= $v;
        }

        $gd = $days + 1;

        return [(int) $gy, (int) $gm, (int) $gd];
    }
}

/**
 * فرمت کردن تاریخ میلادی به شمسی با نام ماه فارسی و نمایش ساعت
 * Input: "2025-10-01" یا "2025-10-01 09:30:45"
 * Output: "9 مهر 1404" یا "9 مهر 1404 ساعت 09:30:45"
 */
if (!function_exists('formatDateJalali')) {
    function formatDateJalali($gregorian_date, $show_time = true, $time_format = 'H:i:s')
    {
        if (!$gregorian_date || trim($gregorian_date) === '') {
            return 'نامشخص';
        }

        // استخراج تاریخ و ساعت
        $parts = explode(' ', $gregorian_date);
        $date_part = trim($parts[0]);
        $time_part = isset($parts[1]) ? trim($parts[1]) : '';

        // جدا کردن سال، ماه، روز میلادی
        $date_components = explode('-', $date_part);
        if (count($date_components) != 3) {
            return 'نامشخص';
        }

        list($gy, $gm, $gd) = $date_components;

        // تبدیل تاریخ میلادی به شمسی
        list($jy, $jm, $jd) = gregorianToJalali((int) $gy, (int) $gm, (int) $gd);

        // نام ماه‌های شمسی
        $month_names = [
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

        // تبدیل اعداد به فارسی
        $jd_persian = englishToFarsiNumber($jd);
        $jy_persian = englishToFarsiNumber($jy);

        $result = $jd_persian . ' ' . $month_names[$jm] . ' ' . $jy_persian;

        // اگر ساعت موجود باشد و نمایش ساعت فعال باشد
        if ($show_time && $time_part) {
            // فرمت کردن ساعت
            $time_formatted = formatTime($time_part, $time_format);
            $result .= ' ساعت ' . englishToFarsiNumber($time_formatted);
        }

        return $result;
    }
}

/**
 * فرمت کردن ساعت (اختیاری)
 */
if (!function_exists('formatTime')) {
    function formatTime($time_string, $format = 'H:i:s')
    {
        if (!$time_string)
            return '';

        // استخراج ساعت، دقیقه، ثانیه
        $time_parts = explode(':', $time_string);
        $hour = isset($time_parts[0]) ? (int) $time_parts[0] : 0;
        $minute = isset($time_parts[1]) ? (int) $time_parts[1] : 0;
        $second = isset($time_parts[2]) ? (int) $time_parts[2] : 0;

        // فرمت کردن بر اساس فرمت مورد نظر
        switch ($format) {
            case 'H:i':
                return sprintf('%02d:%02d', $hour, $minute);
            case 'H:i:s':
                return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
            case 'g:i A':
                $ampm = ($hour < 12) ? 'ق.ظ' : 'ب.ظ';
                $hour12 = $hour % 12;
                $hour12 = ($hour12 == 0) ? 12 : $hour12;
                return sprintf('%d:%02d %s', $hour12, $minute, $ampm);
            default:
                return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
        }
    }
}

/**
 * تبدیل رشته‌ای تاریخ میلادی به شمسی (با ساعت)
 * Input: "2025-10-01" یا "2025-10-01 09:30:45"
 * Output: "1404/07/09" یا "1404/07/09 09:30:45"
 */
if (!function_exists('convertToJalaliDateString')) {
    function convertToJalaliDateString($gregorian_date, $include_time = true)
    {
        if (!$gregorian_date)
            return '';

        // استخراج تاریخ و ساعت اگر موجود باشد
        $parts = explode(' ', $gregorian_date);
        $date_part = trim($parts[0]);
        $time_part = isset($parts[1]) ? trim($parts[1]) : '';

        // جدا کردن سال، ماه، روز
        $date_components = explode('-', $date_part);
        if (count($date_components) != 3) {
            return '';
        }

        list($gy, $gm, $gd) = $date_components;

        // تبدیل به تاریخ شمسی
        list($jy, $jm, $jd) = gregorianToJalali((int) $gy, (int) $gm, (int) $gd);

        $result = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

        // اضافه کردن ساعت اگر مورد نیاز باشد
        if ($include_time && $time_part) {
            $result .= ' ' . $time_part;
        }

        return $result;
    }
}

/**
 * تبدیل رشته‌ای تاریخ شمسی به میلادی (با ساعت)
 * Input: "1404/07/09" یا "1404/07/09 09:30:45"
 * Output: "2025-10-01" یا "2025-10-01 09:30:45"
 */
if (!function_exists('convertToGregorianDateString')) {
    function convertToGregorianDateString($jalali_date, $include_time = true)
    {
        if (!$jalali_date)
            return '';

        // استخراج تاریخ و ساعت
        $parts = explode(' ', $jalali_date);
        $date_part = trim($parts[0]);
        $time_part = isset($parts[1]) ? trim($parts[1]) : '';

        // جدا کردن سال، ماه، روز
        $date_components = explode('/', $date_part);
        if (count($date_components) != 3) {
            return '';
        }

        list($jy, $jm, $jd) = $date_components;

        // تبدیل به تاریخ میلادی
        list($gy, $gm, $gd) = jalaliToGregorian((int) $jy, (int) $jm, (int) $jd);

        $result = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);

        // اضافه کردن ساعت اگر مورد نیاز باشد
        if ($include_time && $time_part) {
            $result .= ' ' . $time_part;
        }

        return $result;
    }
}

/**
 * فرمت کردن تاریخ و زمان شروع و پایان
 * برای استفاده در گزارش‌ها و نمایش‌ها
 */
if (!function_exists('formatStartEndDateTime')) {
    function formatStartEndDateTime($start_date, $end_date = null)
    {
        if (!$start_date)
            return '';

        $formatted_start = formatDateJalali($start_date, true, 'H:i');

        if ($end_date) {
            $formatted_end = formatDateJalali($end_date, true, 'H:i');

            // اگر تاریخ شروع و پایان یکسان باشند
            $start_date_only = explode(' ', $start_date)[0];
            $end_date_only = explode(' ', $end_date)[0];

            if ($start_date_only === $end_date_only) {
                // فقط یک بار تاریخ نمایش داده شود
                $start_time = explode(' ', $start_date)[1] ?? '';
                $end_time = explode(' ', $end_date)[1] ?? '';

                $date_part = formatDateJalali($start_date, false);
                $time_part = englishToFarsiNumber(formatTime($start_time, 'H:i') . ' - ' . formatTime($end_time, 'H:i'));

                return $date_part . ' ساعت ' . $time_part;
            } else {
                // تاریخ‌ها متفاوت هستند
                return $formatted_start . ' تا ' . $formatted_end;
            }
        }

        return $formatted_start;
    }
}

/**
 * دریافت تاریخ و زمان فعلی به شمسی با فرمت کامل
 */
if (!function_exists('getCurrentJalaliDateTime')) {
    function getCurrentJalaliDateTime($include_time = true)
    {
        $now = time();
        $date = date('Y-m-d H:i:s', $now);
        return convertToJalaliDateString($date, $include_time);
    }
}

/**
 * دریافت تاریخ فعلی به شمسی
 */
if (!function_exists('getCurrentJalaliDate')) {
    function getCurrentJalaliDate()
    {
        $now = time();
        $date = date('Y-m-d', $now);
        return convertToJalaliDateString($date, false);
    }
}
/**
 * محاسبه کسری نهایی با اعمال ضریب 2
 * 
 * @param array $initial_slots    بازه‌های کسری اولیه (خروج از getShiftShortageSlots)
 * @param array $approved_requests درخواست‌های تأیید شده روز
 * @param array $user_info       اطلاعات کاربر
 * @return array [کل کسری اولیه, کل کسر شده, کل کسری نهایی, کسری نهایی با ضریب]
 */
if (!function_exists('calculateFinalShortage')) {
    function calculateFinalShortage($initial_slots, $approved_requests, $user_info)
    {
        // 1. کپی از اسلات‌ها برای محاسبه باقیمانده
        $remaining_slots = [];
        foreach ($initial_slots as $slot) {
            $remaining_slots[] = [
                'start' => $slot['start'],
                'end' => $slot['end'],
                'minutes' => $slot['minutes'],
                'type' => $slot['type'],
                'remaining' => $slot['minutes'] // مقدار اولیه برابر با کل
            ];
        }

        // 2. محاسبه کل کسری اولیه
        $total_initial_minutes = 0;
        foreach ($initial_slots as $slot) {
            $total_initial_minutes += $slot['minutes'];
        }

        // 3. کسر درخواست‌های تأیید شده
        $total_deducted_minutes = 0;
        foreach ($approved_requests as $request) {
            // فقط درخواست‌های تأیید شده را اعمال کن
            $should_consider = false;

            // پاس همیشه در نظر گرفته می‌شود
            if ($request['type'] === 'pass') {
                $should_consider = true;
            }
            // سایر درخواست‌ها فقط اگر تأیید شده باشند
            elseif (isset($request['status']) && $request['status'] === 'approved') {
                $should_consider = true;
            }

            if ($should_consider && isset($request['start_time']) && isset($request['end_time'])) {
                $req_start = substr($request['start_time'], 0, 5);
                $req_end = substr($request['end_time'], 0, 5);

                // اعمال روی هر اسلات باقیمانده
                foreach ($remaining_slots as &$slot) {
                    if ($slot['remaining'] <= 0)
                        continue;

                    $overlap = calculateOverlapMinutes(
                        $req_start,
                        $req_end,
                        $slot['start'],
                        $slot['end']
                    );

                    if ($overlap > 0) {
                        $deduction = min($overlap, $slot['remaining']);
                        $slot['remaining'] -= $deduction;
                        $total_deducted_minutes += $deduction;
                    }
                }
            }
        }

        // 4. محاسبه کسری باقیمانده (برای اعمال ضریب 2)
        $remaining_after_deduction_minutes = 0;
        foreach ($remaining_slots as $slot) {
            $remaining_after_deduction_minutes += $slot['remaining'];
        }

        // 5. اعمال ضریب 2 روی کسری باقیمانده
        $final_with_multiplier_minutes = $remaining_after_deduction_minutes * 2;

        // 6. محاسبه کل کسری نهایی (برای نمایش)
        $total_final_minutes = $total_deducted_minutes + $final_with_multiplier_minutes;

        return [
            'total_initial_minutes' => $total_initial_minutes,
            'total_deducted_minutes' => $total_deducted_minutes,
            'remaining_after_deduction_minutes' => $remaining_after_deduction_minutes,
            'final_with_multiplier_minutes' => $final_with_multiplier_minutes,
            'total_final_minutes' => $total_final_minutes,
            'initial_slots' => $initial_slots,
            'remaining_slots' => $remaining_slots
        ];
    }
}

/**
 * محاسبه همپوشانی دو بازه زمانی (برحسب دقیقه)
 * 
 * @param string $start1 شروع بازه اول (HH:MM)
 * @param string $end1   پایان بازه اول (HH:MM)
 * @param string $start2 شروع بازه دوم (HH:MM)
 * @param string $end2   پایان بازه دوم (HH:MM)
 * @return int تعداد دقیقه همپوشانی
 */
if (!function_exists('calculateOverlapMinutes')) {
    function calculateOverlapMinutes($start1, $end1, $start2, $end2)
    {
        // تبدیل به دقیقه
        $timeToMinutes = function ($time) {
            if (empty($time))
                return 0;
            $parts = explode(':', $time);
            $hours = (int) $parts[0];
            $minutes = (int) ($parts[1] ?? 0);
            return ($hours * 60) + $minutes;
        };

        $s1 = $timeToMinutes($start1);
        $e1 = $timeToMinutes($end1);
        $s2 = $timeToMinutes($start2);
        $e2 = $timeToMinutes($end2);

        // اگر همپوشانی ندارند
        if ($s1 >= $e2 || $s2 >= $e1) {
            return 0;
        }

        // محاسبه همپوشانی
        $overlap_start = max($s1, $s2);
        $overlap_end = min($e1, $e2);

        return max(0, $overlap_end - $overlap_start);
    }
}

/**
 * استخراج بازه‌های کسری یک شیفت (با درنظرگرفتن حالت‌های مختلف)
 * 
 * @param string|null $check_in   زمان ورود (HH:MM یا null)
 * @param string|null $check_out  زمان خروج (HH:MM یا null)
 * @param string $shift_start     شروع شیفت (HH:MM)
 * @param string $shift_end       پایان شیفت (HH:MM)
 * @return array لیست بازه‌های کسری
 */
if (!function_exists('getShiftShortageSlots')) {
    function getShiftShortageSlots($check_in, $check_out, $shift_start, $shift_end)
    {
        $slots = [];

        // فقط ساعت:دقیقه
        $shift_start = substr($shift_start, 0, 5);
        $shift_end = substr($shift_end, 0, 5);

        // اگر check_in داریم، زمانش را برش بده
        $check_in_time = $check_in ? substr($check_in, 0, 5) : null;
        $check_out_time = $check_out ? substr($check_out, 0, 5) : null;

        // تابع تبدیل زمان به دقیقه
        $timeToMinutes = function ($time) {
            if (empty($time))
                return 0;
            $parts = explode(':', $time);
            $hours = (int) $parts[0];
            $minutes = (int) ($parts[1] ?? 0);
            return ($hours * 60) + $minutes;
        };

        // حالت ۱: تأخیر در ورود
        if ($check_in_time && $check_in_time > $shift_start) {
            $slots[] = [
                'start' => $shift_start,
                'end' => $check_in_time,
                'minutes' => $timeToMinutes($check_in_time) - $timeToMinutes($shift_start),
                'type' => 'late_arrival'
            ];
        }
        // حالت ۲: عدم ورود
        elseif (!$check_in_time) {
            $slots[] = [
                'start' => $shift_start,
                'end' => $shift_end,
                'minutes' => $timeToMinutes($shift_end) - $timeToMinutes($shift_start),
                'type' => 'no_checkin'
            ];
        }

        // حالت ۳: خروج زودهنگام
        if ($check_out_time && $check_out_time < $shift_end) {
            $slots[] = [
                'start' => $check_out_time,
                'end' => $shift_end,
                'minutes' => $timeToMinutes($shift_end) - $timeToMinutes($check_out_time),
                'type' => 'early_departure'
            ];
        }
        // حالت ۴: عدم خروج (اگر ورود داشته اما خروج ندارد)
        elseif ($check_in_time && !$check_out_time) {
            $slots[] = [
                'start' => $shift_start,
                'end' => $shift_end,
                'minutes' => $timeToMinutes($shift_end) - $timeToMinutes($shift_start),
                'type' => 'no_checkout'
            ];
        }

        return $slots;
    }
}

/**
 * محاسبه دقیق کسری روز با اعمال تمام قوانین
 * 
 * @param array $day_records     رکوردهای حضور روز
 * @param array $day_requests    درخواست‌های روز
 * @param array $user_info       اطلاعات کاربر
 * @param bool $is_holiday       آیا روز تعطیل است
 * @return array اطلاعات کامل کسری
 */
if (!function_exists('calculateDailyShortage')) {
    function calculateDailyShortage($day_records, $day_requests, $user_info, $is_holiday = false)
    {
        if ($is_holiday) {
            return [
                'initial_shortage_minutes' => 0,
                'initial_shortage_hours' => 0,
                'deducted_minutes' => 0,
                'remaining_minutes' => 0,
                'final_with_multiplier_minutes' => 0,
                'final_shortage_minutes' => 0,
                'final_shortage_hours' => 0,
                'initial_hms' => '0:00',
                'final_hms' => '0:00',
                'shortage_slots' => [],
                'remaining_slots' => []
            ];
        }

        // 1. استخراج زمان‌های ورود/خروج
        $shift1_in = null;
        $shift1_out = null;
        $shift2_in = null;
        $shift2_out = null;

        foreach ($day_records as $rec) {
            if ($rec['shift_number'] == 1) {
                $shift1_in = $rec['check_in'] ? substr($rec['check_in'], 11, 5) : null;
                $shift1_out = $rec['check_out'] ? substr($rec['check_out'], 11, 5) : null;
            } elseif ($rec['shift_number'] == 2) {
                $shift2_in = $rec['check_in'] ? substr($rec['check_in'], 11, 5) : null;
                $shift2_out = $rec['check_out'] ? substr($rec['check_out'], 11, 5) : null;
            }
        }

        // 2. ایجاد بازه‌های کسری اولیه
        $initial_slots = [];

        // برای شیفت اول
        if ($user_info['shift_count'] >= 1) {
            $slots = getShiftShortageSlots(
                $shift1_in,
                $shift1_out,
                $user_info['shift_1_start'],
                $user_info['shift_1_end']
            );
            $initial_slots = array_merge($initial_slots, $slots);
        }

        // برای شیفت دوم
        if ($user_info['shift_count'] == 2) {
            $slots = getShiftShortageSlots(
                $shift2_in,
                $shift2_out,
                $user_info['shift_2_start'],
                $user_info['shift_2_end']
            );
            $initial_slots = array_merge($initial_slots, $slots);
        }

        // 3. محاسبه کسری نهایی با اعمال ضریب 2
        $result = calculateFinalShortage($initial_slots, $day_requests, $user_info);

        // 4. تبدیل به ساعت:دقیقه برای نمایش
        $initial_hms = minutesToHourMinute($result['total_initial_minutes']);
        $final_hms = minutesToHourMinute($result['total_final_minutes']);

        return [
            'initial_shortage_minutes' => $result['total_initial_minutes'],
            'initial_shortage_hours' => $result['total_initial_minutes'] / 60,
            'deducted_minutes' => $result['total_deducted_minutes'],
            'remaining_minutes' => $result['remaining_after_deduction_minutes'],
            'final_with_multiplier_minutes' => $result['final_with_multiplier_minutes'],
            'final_shortage_minutes' => $result['total_final_minutes'],
            'final_shortage_hours' => $result['total_final_minutes'] / 60,
            'initial_hms' => $initial_hms,
            'final_hms' => $final_hms,
            'shortage_slots' => $initial_slots,
            'remaining_slots' => $result['remaining_slots']
        ];
    }
}

/**
 * تبدیل دقیقه به فرمت ساعت:دقیقه
 * 
 * @param int $minutes تعداد دقیقه
 * @return string فرمت شده (H:MM)
 */
if (!function_exists('minutesToHourMinute')) {
    function minutesToHourMinute($minutes)
    {
        if ($minutes <= 0) {
            return '0:00';
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        // تبدیل به فارسی اگر تابع موجود باشد
        $persian_hours = $hours;
        $persian_mins = str_pad($mins, 2, '0', STR_PAD_LEFT);

        if (function_exists('englishToFarsiNumber')) {
            $persian_hours = englishToFarsiNumber($hours);
            $persian_mins = englishToFarsiNumber($persian_mins);
        }

        return $persian_hours . ':' . $persian_mins;
    }
}

/**
 * بررسی کامل کسری‌ها با منطق جدید
 * برای استفاده در گزارش ماهانه
 */
if (!function_exists('validateShortageLogic')) {
    function validateShortageLogic()
    {
        $tests = [];

        // تست 1: 1 ساعت تأخیر + 30 دقیقه درخواست تأیید شده
        $slots = [
            ['start' => '09:00', 'end' => '10:00', 'minutes' => 60, 'type' => 'late_arrival', 'remaining' => 60]
        ];

        $requests = [
            ['type' => 'pass', 'status' => 'approved', 'start_time' => '09:30', 'end_time' => '10:00']
        ];
        $result = calculateFinalShortage($slots, $requests, ['shift_count' => 1]);
        $tests[] = [
            'name' => 'تأخیر 60 دقیقه + 30 دقیقه پاس',
            'initial' => 60,
            'deducted' => 30,
            'remaining' => 30,
            'final' => 90, // 30 (کسری باقیمانده) × 2 + 30 (کسری کسر شده)
            'actual' => $result['total_final_minutes'],
            'passed' => $result['total_final_minutes'] == 90
        ];

        // تست 2: تأخیر 2 ساعت + 1.5 ساعت مرخصی تأیید شده
        $slots = [
            ['start' => '09:00', 'end' => '11:00', 'minutes' => 120, 'type' => 'late_arrival', 'remaining' => 120]
        ];

        $requests = [
            ['type' => 'leave', 'status' => 'approved', 'start_time' => '09:30', 'end_time' => '11:00']
        ];

        $result = calculateFinalShortage($slots, $requests, ['shift_count' => 1]);
        $tests[] = [
            'name' => 'تأخیر 120 دقیقه + 90 دقیقه مرخصی',
            'initial' => 120,
            'deducted' => 90,
            'remaining' => 30,
            'final' => 120, // 30 × 2 + 90
            'actual' => $result['total_final_minutes'],
            'passed' => $result['total_final_minutes'] == 120
        ];

        // تست 3: ورود 9:21 بدون خروج (کل روز کسری)
        $slots = [
            ['start' => '09:00', 'end' => '18:00', 'minutes' => 540, 'type' => 'no_checkout', 'remaining' => 540]
        ];

        $requests = [];

        $result = calculateFinalShortage($slots, $requests, ['shift_count' => 1]);
        $tests[] = [
            'name' => 'ورود 9:21 بدون خروج',
            'initial' => 540,
            'deducted' => 0,
            'remaining' => 540,
            'final' => 1080, // 540 × 2
            'actual' => $result['total_final_minutes'],
            'passed' => $result['total_final_minutes'] == 1080
        ];

        return $tests;
    }
}
/**
 * تبدیل اعداد انگلیسی به فارسی
 */
if (!function_exists('englishToFarsiNumber')) {
    function englishToFarsiNumber($number)
    {
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return str_replace($english, $persian, (string) $number);
    }
}

/**
 * تبدیل اعداد فارسی به انگلیسی
 */
if (!function_exists('farsiToEnglishNumber')) {
    function farsiToEnglishNumber($number)
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($persian, $english, (string) $number);
    }
}

/**
 * تست توابع جدید
 */
if (!function_exists('testDateTimeFunctions')) {
    function testDateTimeFunctions()
    {
        $tests = [
            [
                'input' => '2025-01-01 08:30:00',
                'expected_date' => '۱۱ دی ۱۴۰۳',
                'expected_full' => '۱۱ دی ۱۴۰۳ ساعت ۰۸:۳۰:۰۰'
            ],
            [
                'input' => '2025-01-10 14:45:30',
                'expected_date' => '۲۰ دی ۱۴۰۳',
                'expected_full' => '۲۰ دی ۱۴۰۳ ساعت ۱۴:۴۵:۳۰'
            ],
            [
                'input' => '2024-12-22 09:15:00',
                'expected_date' => '۲ دی ۱۴۰۳',
                'expected_full' => '۲ دی ۱۴۰۳ ساعت ۰۹:۱۵:۰۰'
            ],
            [
                'input' => '2025-10-01 17:20:45',
                'expected_date' => '۹ مهر ۱۴۰۴',
                'expected_full' => '۹ مهر ۱۴۰۴ ساعت ۱۷:۲۰:۴۵'
            ]
        ];

        $results = [];
        foreach ($tests as $test) {
            $date_only = formatDateJalali($test['input'], false);
            $with_time = formatDateJalali($test['input'], true);

            $results[] = [
                'input' => $test['input'],
                'date_only' => $date_only,
                'with_time' => $with_time,
                'date_match' => $date_only === $test['expected_date'],
                'time_match' => $with_time === $test['expected_full']
            ];
        }

        return $results;
    }
}
// تست توابع جدید
if (!function_exists('runShortageTests')) {
    function runShortageTests()
    {
        echo "<pre>";
        echo "=== تست منطق محاسبه کسری با ضریب 2 ===\n\n";

        $tests = validateShortageLogic();

        foreach ($tests as $test) {
            echo "تست: {$test['name']}\n";
            echo "  کسری اولیه: {$test['initial']} دقیقه\n";
            echo "  کسر شده: {$test['deducted']} دقیقه\n";
            echo "  باقیمانده: {$test['remaining']} دقیقه\n";
            echo "  انتظار: {$test['final']} دقیقه\n";
            echo "  محاسبه شده: {$test['actual']} دقیقه\n";
            echo "  نتیجه: " . ($test['passed'] ? "✅ PASS" : "❌ FAIL") . "\n\n";
        }

        echo "</pre>";
    }
}
?>