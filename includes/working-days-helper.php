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
 * دریافت تاریخ‌های تعطیل «یک‌روزه» (type=date) از دیتابیس و cache در حافظه
 * (کلید کش بر اساس organization_id — تا در یک request که چندین سازمان رو
 * پردازش می‌کنه، کش سازمان اول اشتباهی برای سازمان دوم استفاده نشه)
 *
 * دو مدل تعطیلی وجود داره:
 *   ۱) سراسری (organization_id = NULL در دیتابیس) — همیشه برگردونده می‌شه
 *   ۲) مخصوص سازمان — فقط اگر $organizationId داده بشه و مطابقت داشته باشه
 *
 * @param PDO      $db
 * @param int|null $organizationId اگر null باشه، فقط تعطیلات سراسری برمی‌گرده
 *                                 (سازگار با فراخوانی‌های قدیمی‌تر بدون این پارامتر)
 */
function getHolidaySet(PDO $db, ?int $organizationId = null): array {
    static $cache = [];
    $cacheKey = $organizationId ?? 'global';

    if (!isset($cache[$cacheKey])) {
        $holidaySet = [];
        try {
            $stmt = $db->prepare("
                SELECT holiday_date FROM holidays
                WHERE type = 'date' AND holiday_date IS NOT NULL
                  AND (organization_id IS NULL OR organization_id = :org_id)
            ");
            $stmt->execute(['org_id' => $organizationId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $holidaySet[$row['holiday_date']] = true;
            }
        } catch (Exception $e) {
            // اگر جدول holidays وجود نداشت، فقط جمعه‌ها حذف می‌شن
            error_log("holidays table error: " . $e->getMessage());
        }
        $cache[$cacheKey] = $holidaySet;
    }

    return $cache[$cacheKey];
}

/**
 * دریافت روزهای هفتهٔ تعطیل «تکرارشونده» (type=weekly، مثلا هر پنج‌شنبه)
 * برای یک سازمان خاص + تعطیلات هفتگی سراسری. مقادیر بر اساس PHP
 * date('w') هستن: ۰=یکشنبه، ۱=دوشنبه، ... ۵=جمعه، ۶=شنبه
 *
 * @return int[] لیست اعداد روز هفته (بدون تکرار)
 */
function getRecurringHolidayWeekdays(PDO $db, ?int $organizationId = null): array {
    static $cache = [];
    $cacheKey = $organizationId ?? 'global';

    if (!isset($cache[$cacheKey])) {
        $days = [];
        try {
            $stmt = $db->prepare("
                SELECT DISTINCT day_of_week FROM holidays
                WHERE type = 'weekly' AND day_of_week IS NOT NULL
                  AND (organization_id IS NULL OR organization_id = :org_id)
            ");
            $stmt->execute(['org_id' => $organizationId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $days[] = (int) $row['day_of_week'];
            }
        } catch (Exception $e) {
            error_log("holidays table error (weekly): " . $e->getMessage());
        }
        $cache[$cacheKey] = $days;
    }

    return $cache[$cacheKey];
}

/**
 * بررسی اینکه یک تاریخ روز کاری هست یا نه
 *
 * @param DateTime $date             تاریخ مورد بررسی
 * @param array    $holidays         آرایه تعطیلات یک‌روزه (کلید = 'Y-m-d')
 * @param int[]    $recurringWeekdays روزهای هفتهٔ تعطیل تکرارشونده (خروجی getRecurringHolidayWeekdays)
 * @return bool
 */
function isWorkingDay(DateTime $date, array $holidays, array $recurringWeekdays = []): bool {
    $dayOfWeek = (int) $date->format('w');

    // جمعه = 5 در PHP (0=یکشنبه، 5=جمعه، 6=شنبه)
    if ($dayOfWeek === 5) {
        return false;
    }

    // تعطیلی هفتگی تکرارشونده (مثلا هر پنج‌شنبه)
    if (in_array($dayOfWeek, $recurringWeekdays, true)) {
        return false;
    }

    // بررسی تعطیلات رسمی
    if (isset($holidays[$date->format('Y-m-d')])) {
        return false;
    }

    return true;
}

/**
 * افزودن N «ساعت کاری» به یک لحظه — روزهای غیرکاری (جمعه/تعطیلات) کاملا
 * نادیده گرفته می‌شوند (نه فقط کم‌شمرده)، یعنی اگر بازه‌ای از ساعت‌شمار با یک
 * روز تعطیل تلاقی کند، آن روز به‌طور کامل به مهلت اضافه می‌شود.
 *
 * مثال: پنج‌شنبه ساعت ۲۰:۰۰ + ۲۴ ساعت کاری = شنبه ساعت ۲۰:۰۰
 * (نه جمعه ساعت ۲۰:۰۰، چون کل جمعه صفر ساعت محسوب می‌شود)
 *
 * @param DateTime $start   لحظه‌ی شروع
 * @param int      $hours   تعداد ساعت کاری که باید اضافه شود
 * @param array    $holidays آرایه‌ی تعطیلات (کلید = 'Y-m-d')
 * @param int[]    $recurringWeekdays روزهای هفتهٔ تعطیل تکرارشونده (اختیاری)
 * @return DateTime لحظه‌ی نتیجه (یک شی DateTime جدید — ورودی تغییر نمی‌کند)
 */
function addWorkingHours(DateTime $start, int $hours, array $holidays, array $recurringWeekdays = []): DateTime {
    $cursor = clone $start;
    $remainingSeconds = $hours * 3600;

    while ($remainingSeconds > 0) {
        if (isWorkingDay($cursor, $holidays, $recurringWeekdays)) {
            $midnight = (clone $cursor)->modify('tomorrow midnight');
            $secondsLeftToday = $midnight->getTimestamp() - $cursor->getTimestamp();

            if ($remainingSeconds <= $secondsLeftToday) {
                $cursor->modify('+' . $remainingSeconds . ' seconds');
                $remainingSeconds = 0;
            } else {
                $remainingSeconds -= $secondsLeftToday;
                $cursor = $midnight;
            }
        } else {
            // روز غیرکاری — کاملا رد می‌شود، هیچ ساعتی از آن کم نمی‌شود
            $cursor->modify('tomorrow midnight');
        }
    }

    return $cursor;
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
 * @param int[]    $recurringWeekdays روزهای هفتهٔ تعطیل تکرارشونده (اختیاری)
 * @return int تعداد روزهای کاری (عدد مثبت یعنی end > start)
 */
function countWorkingDaysBetween(DateTime $start, DateTime $end, array $holidays, array $recurringWeekdays = []): int {
    $count  = 0;
    $cursor = clone $start;
    $cursor->setTime(0, 0, 0);

    $endClean = clone $end;
    $endClean->setTime(0, 0, 0);

    // اگر بازه معکوس بود (تاخیر)، جهت رو برعکس حساب می‌کنیم
    $forward = ($endClean >= $cursor);

    if ($forward) {
        while ($cursor < $endClean) {
            if (isWorkingDay($cursor, $holidays, $recurringWeekdays)) {
                $count++;
            }
            $cursor->modify('+1 day');
        }
    } else {
        // end < start یعنی گذشته از موعد → عدد منفی برمی‌گردونه
        while ($cursor > $endClean) {
            if (isWorkingDay($cursor, $holidays, $recurringWeekdays)) {
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

    // از لحظه‌ای که موعد (start_date) فرا رسیده، حداقل یک دوره سررسیده محسوب می‌شود
    // (هم‌راستا با next_due_date که همان start_date است تا قبل از اولین انجام)
    if (in_array($period_type, ['daily', 'weekly', 'monthly'], true)) {
        $expected = max($expected, 1);
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

/**
 * معادل ساعتی calcPeriodicDelayWorkingDays — مخصوص کارهای روتین/فرآیندی
 * (is_workflow_task=1). برخلاف کارهای معمولی، اینجا جمعه/تعطیلات کسر
 * نمی‌شه — چون مهلت روتین (t.deadline) از قبل با زمان مجاز همون مرحله
 * (ws.time_limit_hours، طبق همون منطقی که api/reports/bottleneck-report.php
 * و includes/WorkflowManager.php استفاده می‌کنن) محاسبه شده، پس خود مهلت
 * از قبل «تنظیم‌شده» است — فقط باید فاصله‌ی ساعتی تا الان رو حساب کرد.
 *
 * @param string $deadline تاریخ‌وساعت مهلت ('Y-m-d H:i:s')
 * @param string|null $now لحظه‌ی «الان» (پیش‌فرض: ساعت سرور)
 * @return int تعداد ساعت تأخیر (0 = هنوز به موقع)
 */
function calcHourDelay(string $deadline, ?string $now = null): int {
    $now = $now ?: date('Y-m-d H:i:s');
    $d = new DateTime($deadline);
    $n = new DateTime($now);
    if ($n <= $d) return 0;
    return (int) floor(($n->getTimestamp() - $d->getTimestamp()) / 3600);
}

/**
 * ساعت باقی‌مانده تا مهلت یک کار روتین/فرآیندی (هنوز نرسیده به موعد).
 *
 * @param string $deadline تاریخ‌وساعت مهلت ('Y-m-d H:i:s')
 * @param string|null $now لحظه‌ی «الان» (پیش‌فرض: ساعت سرور)
 * @return int تعداد ساعت باقی‌مانده (0 = مهلت گذشته یا همین الان)
 */
function calcHourRemaining(string $deadline, ?string $now = null): int {
    $now = $now ?: date('Y-m-d H:i:s');
    $d = new DateTime($deadline);
    $n = new DateTime($now);
    if ($d <= $n) return 0;
    return (int) ceil(($d->getTimestamp() - $n->getTimestamp()) / 3600);
}
