<?php
/**
 * working-days-helper.php
 * 
 * محل قرارگیری: /includes/working-days-helper.php
 * 
 * توابع کمکی برای محاسبه روزهای کاری
 * (بدون جمعه‌ها و بدون تعطیلات رسمی از جدول holidays)
 */

/**
 * دریافت تمام تاریخ‌های تعطیل از دیتابیس و cache در حافظه
 * تا در یک request یک‌بار به دیتابیس بزنیم
 */
function getHolidaySet(PDO $db): array {
    static $holidaySet = null;

    if ($holidaySet === null) {
        $holidaySet = [];
        try {
            $stmt = $db->query("SELECT holiday_date FROM holidays");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $holidaySet[$row['holiday_date']] = true;
            }
        } catch (Exception $e) {
            // اگر جدول holidays وجود نداشت، فقط جمعه‌ها حذف می‌شن
            error_log("holidays table error: " . $e->getMessage());
        }
    }

    return $holidaySet;
}

/**
 * بررسی اینکه یک تاریخ روز کاری هست یا نه
 * 
 * @param DateTime $date تاریخ مورد بررسی
 * @param array    $holidays آرایه تعطیلات (کلید = 'Y-m-d')
 * @return bool
 */
function isWorkingDay(DateTime $date, array $holidays): bool {
    // جمعه = 5 در PHP (0=یکشنبه، 5=جمعه، 6=شنبه)
    if ((int)$date->format('w') === 5) {
        return false;
    }

    // بررسی تعطیلات رسمی
    if (isset($holidays[$date->format('Y-m-d')])) {
        return false;
    }

    return true;
}

/**
 * شمارش تعداد روزهای کاری بین دو تاریخ (شامل start، نه شامل end)
 * 
 * مثال: از 2026-05-01 تا 2026-05-10 
 * → روزهای پنجشنبه تا پنجشنبه، جمعه‌ها و تعطیلات کم می‌شن
 * 
 * @param DateTime $start تاریخ شروع
 * @param DateTime $end   تاریخ پایان
 * @param array    $holidays آرایه تعطیلات
 * @return int تعداد روزهای کاری (عدد مثبت یعنی end > start)
 */
function countWorkingDaysBetween(DateTime $start, DateTime $end, array $holidays): int {
    $count  = 0;
    $cursor = clone $start;
    $cursor->setTime(0, 0, 0);

    $endClean = clone $end;
    $endClean->setTime(0, 0, 0);

    // اگر بازه معکوس بود (تاخیر)، جهت رو برعکس حساب می‌کنیم
    $forward = ($endClean >= $cursor);

    if ($forward) {
        while ($cursor < $endClean) {
            if (isWorkingDay($cursor, $holidays)) {
                $count++;
            }
            $cursor->modify('+1 day');
        }
    } else {
        // end < start یعنی گذشته از موعد → عدد منفی برمی‌گردونه
        while ($cursor > $endClean) {
            if (isWorkingDay($cursor, $holidays)) {
                $count++;
            }
            $cursor->modify('-1 day');
        }
        $count = -$count;
    }

    return $count;
}

/**
 * شمارش دوره‌های معوقه برای کارهای continuous
 * با در نظر گرفتن فقط روزهای کاری
 * 
 * @param string   $period_type  'daily' | 'weekly' | 'monthly'
 * @param DateTime $start_date   تاریخ شروع کار
 * @param DateTime $today        امروز
 * @param int      $completed    تعداد دوره‌های انجام شده
 * @param array    $holidays     آرایه تعطیلات
 * @return int تعداد دوره‌های معوقه
 */
function calcOverduePeriods(
    string   $period_type,
    DateTime $start_date,
    DateTime $today,
    int      $completed,
    array    $holidays
): int {
    if ($today < $start_date) {
        return 0;
    }

    $expected = 0;

    switch ($period_type) {

        case 'daily':
            // هر روز کاری یک دوره
            $expected = countWorkingDaysBetween($start_date, $today, $holidays);
            break;

        case 'weekly':
            // هفتگی: جمعه و تعطیلات تأثیری در تعداد هفته ندارن
            // اما اگر موعد هفتگی روی جمعه/تعطیل افتاد، به روز کاری بعدی منتقل می‌شه
            $diff_days = (int)$start_date->diff($today)->days;
            $expected  = (int)floor($diff_days / 7);
            break;

        case 'monthly':
            // ماهانه: جمعه و تعطیلات تأثیری در تعداد ماه ندارن
            $diff       = $start_date->diff($today);
            $expected   = ($diff->y * 12) + $diff->m;
            break;

        default:
            $expected = 0;
    }

    return max(0, $expected - $completed);
}

/**
 * محاسبه تاخیر یک کار مقطعی (periodic) بر اساس روزهای کاری
 * 
 * @param string $due_date_str   تاریخ سررسید ('Y-m-d')
 * @param string $today_str      امروز ('Y-m-d')
 * @param array  $holidays       آرایه تعطیلات
 * @return int   تعداد روزهای کاری تاخیر (0 = به موقع یا زودتر)
 */
function calcPeriodicDelayWorkingDays(
    string $due_date_str,
    string $today_str,
    array  $holidays
): int {
    $due   = new DateTime($due_date_str);
    $today = new DateTime($today_str);
    $due->setTime(0, 0, 0);
    $today->setTime(0, 0, 0);

    if ($today <= $due) {
        return 0; // هنوز موعد نرسیده
    }

    // از due+1 تا today حساب می‌کنیم
    $cursor = clone $due;
    $cursor->modify('+1 day');

    $working_delay = 0;
    while ($cursor <= $today) {
        if (isWorkingDay($cursor, $holidays)) {
            $working_delay++;
        }
        $cursor->modify('+1 day');
    }

    return $working_delay;
}
