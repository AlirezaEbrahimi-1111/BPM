<?php

/**
 * ═══════════════════════════════════════════════════════════════════
 *  period-engine.php — موتور دورهٔ کارهای تکرارشونده
 *  محل: /includes/period-engine.php
 * ───────────────────────────────────────────────────────────────────
 *
 *  ⚠️ این فایل، تنها مرجع محاسبهٔ دوره در کل سامانه است.
 *     هیچ فایل دیگری نباید دوره را خودش بشمارد.
 *
 *  ┌─ چرا ساخته شد؟ ───────────────────────────────────────────────┐
 *  │  پیش از این، چهار جای مختلف سامانه «دوره» را چهار جور         │
 *  │  می‌شمردند:                                                    │
 *  │    • complete-recurring.php  → روز تقویمی (جمعه هم دوره!)     │
 *  │    • maybeStartNextPeriod    → روز کاری، بدون احتساب بخشش     │
 *  │    • enrichTaskDates         → روز کاری، با احتساب بخشش       │
 *  │    • request-overdue-clear   → فرمول سوم                      │
 *  │  نتیجه: دوره‌های معوقهٔ جعلی، بازشدن بی‌پایان کار، و بلعیده     │
 *  │  شدن تلاش کاربر.                                              │
 *  └───────────────────────────────────────────────────────────────┘
 *
 *  ┌─ مدل دوره (تصمیم رسمی) ───────────────────────────────────────┐
 *  │                                                                │
 *  │  ۱) هر دوره یک «تاریخ سررسید» مشخص دارد:                       │
 *  │       روزانه  → هر روز کاری (جمعه و تعطیلات رسمی حذف)          │
 *  │       هفتگی   → هر ۷ روز از تاریخ شروع                         │
 *  │       ماهانه  → هر ۱ ماه از تاریخ شروع                         │
 *  │                                                                │
 *  │  ۲) وقتی کاربر «تکمیل» می‌زند، دورهٔ **همان روز** بسته می‌شود   │
 *  │     — نه قدیمی‌ترین دورهٔ عقب‌افتاده.                           │
 *  │                                                                │
 *  │  ۳) دوره‌های عقب‌افتاده (قبل از امروز) به‌صورت «معوقه» باقی      │
 *  │     می‌مانند و **نمایش داده می‌شوند** تا مدیر در جریان باشد.    │
 *  │                                                                │
 *  │  ۴) معوقه‌ها فقط از یک راه پاک می‌شوند: درخواست رفع معوقه و     │
 *  │     تأیید تعریف‌کنندهٔ کار.                                     │
 *  │                                                                │
 *  └────────────────────────────────────────────────────────────────┘
 * ═══════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/working-days-helper.php';
/** سقف ایمنی برای جلوگیری از حلقهٔ بی‌پایان */
const PE_MAX_PERIODS = 3000;


/* ═══════════════════════════════════════════════════════════════
   بخش ۰ — کمکیِ ماه‌شمار با لنگرِ روزِ ثابت
   ───────────────────────────────────────────────────────────────
   ⚠️ چرا لازم است؟
   فراخوانی پیاپیِ DateTime::modify('+1 month') روی یک cursor باعث
   سرریز تقویمی می‌شود: مثلاً ۳۱ فروردین + ۱ ماه در PHP به‌جای
   «آخرِ اردیبهشت»، به ۳ خرداد می‌رود (چون اردیبهشت ۳۱ روز ندارد و
   PHP آن ۳ روزِ اضافه را به ماهِ بعد سرریز می‌کند) — یعنی کل یک ماه
   پریده می‌شود و از آن به بعد، لنگرِ روز برای همیشه از ۳۱ به ۳
   منحرف می‌ماند. راه‌حل: هر دوره را مستقیماً از تاریخ شروع (نه از
   دورهٔ محاسبه‌شدهٔ قبلی) با شمارهٔ ماه بسازیم و فقط در همان محاسبه
   روز را به آخرِ ماهِ مقصد محدود (clamp) کنیم.
   ═══════════════════════════════════════════════════════════════ */

/**
 * افزودن N ماه به یک تاریخِ لنگر با روزِ ثابت — بدون سرریزِ تقویمیِ PHP.
 * اگر روزِ لنگر در ماهِ مقصد وجود نداشت، به آخرین روزِ همان ماه محدود
 * می‌شود؛ به‌جای سرریز به ماهِ بعدتر.
 */
function pe_addMonthsClamped(DateTime $anchor, int $months): DateTime
{
    $day = (int) $anchor->format('d');
    $y   = (int) $anchor->format('Y');
    $m   = (int) $anchor->format('n') + $months;

    $y += intdiv($m - 1, 12);
    $m  = (($m - 1) % 12) + 1;

    $lastDay = (int) (new DateTime(sprintf('%04d-%02d-01', $y, $m)))->format('t');
    $day     = min($day, $lastDay);

    $result = new DateTime(sprintf('%04d-%02d-%02d', $y, $m, $day));
    $result->setTime(0, 0, 0);
    return $result;
}

/** تعداد ماهِ کامل بین دو تاریخ، بر اساس سال/ماه (صرف‌نظر از روز) */
function pe_monthsBetween(DateTime $anchor, DateTime $date): int
{
    $y = (int) $date->format('Y') - (int) $anchor->format('Y');
    $m = (int) $date->format('n') - (int) $anchor->format('n');
    return $y * 12 + $m;
}


/* ═══════════════════════════════════════════════════════════════
   بخش ۱ — تولید تاریخ‌های سررسید دوره‌ها
   ═══════════════════════════════════════════════════════════════ */

/**
 * فهرست تاریخ سررسید همهٔ دوره‌ها، از تاریخ شروع تا سقف مشخص.
 *
 * @param string    $periodType  daily | weekly | monthly
 * @param DateTime  $start       تاریخ شروع کار
 * @param DateTime  $upto        تا این تاریخ (شامل خودش)
 * @param array     $holidays    مجموعهٔ تعطیلات رسمی
 * @param string|null $endDate   تاریخ پایان کار (اختیاری)
 *
 * @return string[]  آرایهٔ تاریخ‌ها به‌صورت 'Y-m-d'، صعودی
 */
function pe_periodDates(
    string $periodType,
    DateTime $start,
    DateTime $upto,
    array $holidays,
    ?string $endDate = null
): array {

    $dates  = [];
    $cursor = clone $start;
    $cursor->setTime(0, 0, 0);

    $limit = clone $upto;
    $limit->setTime(0, 0, 0);

    // ── روزانه: فقط روزهای کاری ─────────────────────────
    if ($periodType === 'daily') {

        // اگر خودِ روز شروع، روز کاری نیست → به اولین روز کاری برو
        while (!isWorkingDay($cursor, $holidays)) {
            $cursor->modify('+1 day');
            if ($cursor > $limit) return [];
        }

        $guard = 0;
        while ($cursor <= $limit && $guard++ < PE_MAX_PERIODS) {

            $d = $cursor->format('Y-m-d');
            if ($endDate !== null && $d > $endDate) break;

            $dates[] = $d;

            // برو به روز کاری بعدی
            do {
                $cursor->modify('+1 day');
            } while (!isWorkingDay($cursor, $holidays));
        }

        return $dates;
    }

    // ── هفتگی: هر ۷ روز، بدون هیچ مشکل تقویمی (۷ روز همیشه ۷ روز است) ──
    if ($periodType === 'weekly') {
        $guard = 0;
        while ($cursor <= $limit && $guard++ < PE_MAX_PERIODS) {

            $d = $cursor->format('Y-m-d');
            if ($endDate !== null && $d > $endDate) break;

            $dates[] = $d;
            $cursor->modify('+1 week');
        }

        return $dates;
    }

    // ── ماهانه: لنگرِ روزِ ثابت — هر دوره مستقیم از start محاسبه می‌شود ──
    // (نه با modify('+1 month') پیاپی؛ دلیل را در توضیح pe_addMonthsClamped ببینید)
    if ($periodType === 'monthly') {
        $guard = 0;
        $i = 0;
        while ($guard++ < PE_MAX_PERIODS) {
            $periodDate = pe_addMonthsClamped($start, $i);
            if ($periodDate > $limit) break;

            $d = $periodDate->format('Y-m-d');
            if ($endDate !== null && $d > $endDate) break;

            $dates[] = $d;
            $i++;
        }

        return $dates;
    }

    return $dates;
}


/**
 * تاریخِ سررسیدِ دوره‌ای که یک تاریخ مشخص «به آن تعلق دارد».
 *
 * مثال: اگر دوره‌ها ۱، ۲، ۵، ۶ باشند و تاریخ ۴ باشد،
 *       دورهٔ متعلقه ۲ است (آخرین سررسیدی که ≤ ۴ است).
 *
 * @return string|null  تاریخ دوره، یا null اگر پیش از اولین دوره باشد
 */
function pe_periodOf(array $periodDates, string $date): ?string
{
    $found = null;
    foreach ($periodDates as $d) {
        if ($d <= $date) {
            $found = $d;
        } else {
            break;   // آرایه صعودی است
        }
    }
    return $found;
}


/* ═══════════════════════════════════════════════════════════════
   بخش ۲ — خواندن تکمیل‌ها از تاریخچه
   ═══════════════════════════════════════════════════════════════ */

/**
 * تاریخ‌هایی که کاربر روی این کار «تکمیل» زده است (یکتا).
 *
 * ⚠️ تاریخ مهم است، نه تعداد. چون طبق مدل، تکمیلِ روز X دورهٔ
 *    متعلق به روز X را می‌بندد.
 *
 * @return string[]  آرایهٔ تاریخ 'Y-m-d'، صعودی
 */
function pe_completionDates(PDO $db, int $taskId, ?array $preloadedMap = null): array
{
    // 🆕 اگر نقشهٔ از‌پیش‌واکشی‌شده داده شده (برای صفحات لیستی که چند تسک را
    // یک‌جا پردازش می‌کنند)، به‌جای یک کوئری جداگانه به‌ازای هر تسک، مستقیم
    // از همان نقشه بخوان — رفع مشکلِ N+1 در overview.php/my-tasks.php.
    // فراخوانی‌های تک‌تسکی (بدون این پارامتر) دقیقاً مثل قبل کار می‌کنند.
    if ($preloadedMap !== null) {
        return $preloadedMap[$taskId] ?? [];
    }

    $stmt = $db->prepare("
        SELECT DISTINCT DATE(created_at) AS d
        FROM task_history
        WHERE task_id = ?
          AND action = 'completed'
        ORDER BY d ASC
    ");
    $stmt->execute([$taskId]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * 🆕 نسخهٔ دسته‌ای pe_completionDates — تاریخ‌های تکمیلِ همهٔ تسک‌های داده‌شده
 * را با یک کوئری واحد برمی‌گرداند (به‌جای یک کوئری به‌ازای هر تسک).
 * خروجی مستقیماً به pe_completionDates/pe_state/enrichTaskDates به‌عنوان
 * $preloadedMap داده می‌شود.
 *
 * @return array<int,string[]>  task_id => آرایهٔ تاریخ 'Y-m-d' (صعودی، یکتا)
 */
function pe_preloadCompletionDates(PDO $db, array $taskIds): array
{
    $taskIds = array_values(array_unique(array_filter(array_map('intval', $taskIds))));
    if (empty($taskIds)) return [];

    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
    $stmt = $db->prepare("
        SELECT DISTINCT task_id, DATE(created_at) AS d
        FROM task_history
        WHERE task_id IN ($placeholders)
          AND action = 'completed'
        ORDER BY task_id ASC, d ASC
    ");
    $stmt->execute($taskIds);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int) $row['task_id']][] = $row['d'];
    }
    return $map;
}


/* ═══════════════════════════════════════════════════════════════
   بخش ۳ — محاسبهٔ وضعیت کامل دوره
   ═══════════════════════════════════════════════════════════════ */

/**
 * وضعیت کامل دوره‌های یک کار تکرارشونده.
 *
 * @return array {
 *     started              bool    آیا کار شروع شده؟
 *     finished             bool    آیا بازهٔ کار تمام شده؟ (end_date گذشته)
 *     current_period_date  ?string سررسید دورهٔ جاری (دوره‌ای که امروز به آن تعلق دارد)
 *     is_today_done        bool    آیا دورهٔ جاری تکمیل شده؟
 *     next_due_date        ?string موعد بعدی که باید انجام شود
 *     days_remaining       ?int    روز تا موعد بعدی (منفی = گذشته)
 *     overdue_periods      int     دوره‌های معوقهٔ واقعی (پس از کسر بخشش)
 *     overdue_raw          int     دوره‌های عقب‌افتاده پیش از کسر بخشش
 *     completed_periods    int     دوره‌های تکمیل‌شده
 *     forgiven             int     دوره‌های بخشیده‌شده
 *     working_days_delayed int     تأخیر به روز کاری
 *     can_complete         bool    آیا کاربر الان می‌تواند «تکمیل» بزند؟
 * }
 */
function pe_state(PDO $db, array $task, array $holidays, ?string $today = null, ?array $preloadedCompletionMap = null): array
{
    $today = $today ?: date('Y-m-d');

    // مقادیر پیش‌فرض (کار غیردوره‌ای یا ناقص)
    $out = [
        'started'              => false,
        'finished'             => false,
        'current_period_date'  => null,
        'is_today_done'        => false,
        'next_due_date'        => null,
        'days_remaining'       => null,
        'overdue_periods'      => 0,
        'overdue_raw'          => 0,
        'completed_periods'    => 0,
        'forgiven'             => (int) ($task['overdue_forgiven_credit'] ?? 0),
        'working_days_delayed' => 0,
        'can_complete'         => false,
    ];

    if (($task['task_type'] ?? '') !== 'continuous') return $out;
    if (empty($task['start_date']))                  return $out;
    if (empty($task['period_type']))                 return $out;

    try {
        $start = new DateTime($task['start_date']);
        $start->setTime(0, 0, 0);
        $now   = new DateTime($today);
        $now->setTime(0, 0, 0);

        $endDate = !empty($task['end_date']) ? $task['end_date'] : null;

        // ── کار هنوز شروع نشده ──────────────────────────
        if ($now < $start) {
            $out['next_due_date']  = $start->format('Y-m-d');
            $out['days_remaining'] = (int) $now->diff($start)->format('%r%a');
            return $out;
        }

        $out['started'] = true;

        // ── بازهٔ کار تمام شده ──────────────────────────
        if ($endDate !== null && $today > $endDate) {
            $out['finished'] = true;
            // معوقه‌ای گزارش نمی‌شود؛ کار بسته است
            return $out;
        }

        // ── تولید تاریخ دوره‌ها تا امروز ────────────────
        $periods = pe_periodDates($task['period_type'], $start, $now, $holidays, $endDate);

        if (empty($periods)) {
            // امروز هنوز به هیچ دوره‌ای نرسیده (مثلاً شروع در تعطیلات)
            return $out;
        }

        // ── دورهٔ جاری = آخرین سررسیدی که ≤ امروز است ───
        $currentPeriod = pe_periodOf($periods, $today);
        $out['current_period_date'] = $currentPeriod;

        // ── نگاشت تکمیل‌ها به دوره‌ها ───────────────────
        $completionDates  = pe_completionDates($db, (int) $task['id'], $preloadedCompletionMap);
        $completedPeriods = [];

        foreach ($completionDates as $cd) {
            $p = pe_periodOf($periods, $cd);
            if ($p !== null) {
                $completedPeriods[$p] = true;   // کلید یکتا
            }
        }

        $out['completed_periods'] = count($completedPeriods);
        $out['is_today_done']     = isset($completedPeriods[$currentPeriod]);

        // ── معوقه: دوره‌های *قبل از* دورهٔ جاری که تکمیل نشده‌اند ──
        $overdueRaw = 0;
        foreach ($periods as $p) {
            if ($p >= $currentPeriod) break;          // دورهٔ جاری معوقه نیست
            if (!isset($completedPeriods[$p])) {
                $overdueRaw++;
            }
        }

        $forgiven = (int) ($task['overdue_forgiven_credit'] ?? 0);

        $out['overdue_raw']     = $overdueRaw;
        $out['overdue_periods'] = max(0, $overdueRaw - $forgiven);

        // ── موعد بعدی ──────────────────────────────────
        if ($out['is_today_done']) {
            // دورهٔ امروز انجام شده → موعد بعدی، دورهٔ بعدی است
            $next = pe_nextPeriodAfter($task['period_type'], $currentPeriod, $holidays, $start);

            if ($endDate !== null && $next > $endDate) {
                $out['next_due_date'] = null;   // دورهٔ دیگری نمانده
            } else {
                $out['next_due_date'] = $next;
            }
        } else {
            // دورهٔ امروز هنوز باز است
            $out['next_due_date'] = $currentPeriod;
        }

        // ── مهلت (روز مانده) ───────────────────────────
        if ($out['next_due_date'] !== null) {
            $nd = new DateTime($out['next_due_date']);
            $nd->setTime(0, 0, 0);
            $out['days_remaining'] = (int) $now->diff($nd)->format('%r%a');

            if ($nd < $now) {
                $out['working_days_delayed'] = calcPeriodicDelayWorkingDays(
                    $out['next_due_date'],
                    $today,
                    $holidays
                );
            }
        }

        // ── آیا می‌تواند تکمیل بزند؟ ────────────────────
        // فقط اگر دورهٔ جاری باز باشد و کار تمام نشده باشد
        $out['can_complete'] = !$out['is_today_done'] && !$out['finished'];
    } catch (Exception $e) {
        error_log("pe_state error task#{$task['id']}: " . $e->getMessage());
    }

    return $out;
}


/**
 * تاریخ سررسید دورهٔ بعدی، پس از یک تاریخ مشخص.
 *
 * @param DateTime|null $anchor  برای ماهانه: تاریخ شروع اصلیِ کار (لنگرِ روز).
 *                               اگر داده نشود، $afterDate خودش لنگر فرض می‌شود —
 *                               اما در این حالت اگر $afterDate قبلاً clamp شده
 *                               باشد (مثلاً ۲۸ اسفند به‌جای ۳۱)، لنگر گم می‌شود.
 */
function pe_nextPeriodAfter(string $periodType, string $afterDate, array $holidays, ?DateTime $anchor = null): string
{
    $d = new DateTime($afterDate);
    $d->setTime(0, 0, 0);

    switch ($periodType) {
        case 'daily':
            do {
                $d->modify('+1 day');
            } while (!isWorkingDay($d, $holidays));
            break;

        case 'weekly':
            $d->modify('+1 week');
            break;

        case 'monthly':
            // ⚠️ به‌جای «+۱ ماه» روی afterDate (که ممکن است قبلاً به آخرِ یک
            // ماهِ کوتاه‌تر clamp شده باشد)، از لنگرِ اصلیِ start_date محاسبه
            // می‌کنیم تا روزِ تکرار برای همیشه ثابت بماند (نگاه کنید به
            // pe_addMonthsClamped بالاتر برای دلیل کامل).
            $anchorDate  = $anchor ?? $d;
            $monthsSoFar = pe_monthsBetween($anchorDate, $d);
            $d = pe_addMonthsClamped($anchorDate, $monthsSoFar + 1);
            break;
    }

    return $d->format('Y-m-d');
}
