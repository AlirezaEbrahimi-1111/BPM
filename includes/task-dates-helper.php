<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  task-dates-helper.php
 *  محل: /includes/task-dates-helper.php
 * ───────────────────────────────────────────────────────────────────
 *  تابع مشترک محاسبهٔ تاریخ‌های یک کار:
 *      • next_due_date        موعد بعدی
 *      • days_remaining       مهلت (روز مانده؛ منفی = گذشته)
 *      • overdue_periods      دوره‌های معوقه (فقط کار دوره‌ای)
 *      • working_days_delayed تأخیر به روز کاری
 *
 *  چرا؟ چون تا امروز این محاسبه در my-tasks.php، all-tasks.php و
 *  delegated-tasks.php جداگانه (و ناهماهنگ) انجام می‌شد. نتیجه:
 *  کارهای دوره‌ای در بعضی جدول‌ها اصلاً موعد نداشتند.
 *
 *  از این به بعد: هر تغییری در این منطق، فقط همین‌جا.
 * ═══════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/working-days-helper.php';

/**
 * تاریخ‌های یک کار را محاسبه و به آرایهٔ کار اضافه می‌کند.
 *
 * @param array  $task     آرایهٔ کار (از دیتابیس)
 * @param PDO    $db       اتصال دیتابیس
 * @param array  $holidays آرایهٔ تعطیلات (از getHolidaySet)
 * @param string $today    تاریخ امروز 'Y-m-d'
 * @return array           همان کار، به‌همراه فیلدهای محاسبه‌شده
 */
function enrichTaskDates(array $task, PDO $db, array $holidays, string $today): array
{
    // مقادیر پیش‌فرض
    $task['overdue_periods']      = 0;
    $task['next_due_date']        = null;
    $task['days_remaining']       = null;
    $task['working_days_delayed'] = 0;

    $current = new DateTime($today);
    $current->setTime(0, 0, 0);

    // ══════════════════════════════════════════════════
    //  کار دوره‌ای (continuous)
    // ══════════════════════════════════════════════════
    if (($task['task_type'] ?? '') === 'continuous' && !empty($task['start_date'])) {

        try {
            $start = new DateTime($task['start_date']);
            $start->setTime(0, 0, 0);

            // هنوز شروع نشده
            if ($current < $start) {
                $task['next_due_date']  = $start->format('Y-m-d');
                $task['days_remaining'] = (int) $current->diff($start)->format('%r%a');
                return $task;
            }

            // تعداد دوره‌های انجام‌شده
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM task_history WHERE task_id = ? AND action = 'completed'"
            );
            $stmt->execute([$task['id']]);
            $completed = (int) $stmt->fetchColumn();

            // دوره‌های بخشیده‌شده (رفع معوقه)
            $forgiven = (int) ($task['overdue_forgiven_credit'] ?? 0);

            // ── معوقه‌ها (بر اساس روزهای کاری) ──────────
            $overdue = calcOverduePeriods(
                $task['period_type'],
                $start,
                $current,
                $completed,
                $holidays
            );
            $task['overdue_periods'] = max(0, $overdue - $forgiven);

            // ── موعد بعدی ───────────────────────────────
            // دوره‌های انجام‌شده + بخشیده‌شده، هر دو موعد را جلو می‌برند
            $advance  = $completed + $forgiven;
            $next_due = clone $start;

            for ($i = 0; $i < $advance; $i++) {
                switch ($task['period_type']) {
                    case 'daily':
                        // اولین روز کاری بعدی
                        do {
                            $next_due->modify('+1 day');
                        } while (!isWorkingDay($next_due, $holidays));
                        break;

                    case 'weekly':
                        $next_due->modify('+1 week');
                        break;

                    case 'monthly':
                        $next_due->modify('+1 month');
                        break;
                }
            }

            $task['next_due_date'] = $next_due->format('Y-m-d');

            // ── مهلت (روز مانده) ────────────────────────
            $task['days_remaining'] = (int) $current->diff($next_due)->format('%r%a');

            // ── تأخیر به روز کاری ───────────────────────
            if ($next_due < $current) {
                $task['working_days_delayed'] = calcPeriodicDelayWorkingDays(
                    $next_due->format('Y-m-d'),
                    $today,
                    $holidays
                );
            }

            // بازهٔ کار تمام شده؟ دیگر معوقه‌ای نیست
            if (!empty($task['end_date']) && $today > $task['end_date']) {
                $task['overdue_periods'] = 0;
            }

        } catch (Exception $e) {
            error_log("enrichTaskDates (continuous) task#{$task['id']}: " . $e->getMessage());
        }

        return $task;
    }

    // ══════════════════════════════════════════════════
    //  کار مقطعی (periodic)
    // ══════════════════════════════════════════════════
    if (($task['task_type'] ?? '') === 'periodic') {

        try {
            // بزرگ‌ترین تاریخ سررسید (تمدید موعد ممکن است جلوترش برده باشد)
            $dates = [];
            foreach (['due_date', 'deadline', 'original_deadline'] as $f) {
                if (!empty($task[$f])) {
                    $dates[] = new DateTime(substr($task[$f], 0, 10));
                }
            }

            if (!empty($dates)) {
                $max = max($dates);
                $max->setTime(0, 0, 0);

                $task['next_due_date']  = $max->format('Y-m-d');
                $task['days_remaining'] = (int) $current->diff($max)->format('%r%a');

                $done = in_array($task['status'] ?? '', ['completed', 'approved'], true);

                if ($current > $max && !$done) {
                    $task['working_days_delayed'] = calcPeriodicDelayWorkingDays(
                        $max->format('Y-m-d'),
                        $today,
                        $holidays
                    );
                }
            }

        } catch (Exception $e) {
            error_log("enrichTaskDates (periodic) task#{$task['id']}: " . $e->getMessage());
        }
    }

    return $task;
}