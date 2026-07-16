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
require_once __DIR__ . '/period-engine.php';
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
    if (($task['task_type'] ?? '') === 'continuous') {

        // ✅ موتور مشترک — تنها مرجع محاسبهٔ دوره
        $s = pe_state($db, $task, $holidays, $today);

        $task['overdue_periods']      = $s['overdue_periods'];
        $task['next_due_date']        = $s['next_due_date'];
        $task['days_remaining']       = $s['days_remaining'];
        $task['working_days_delayed'] = $s['working_days_delayed'];

        // فیلدهای جدید — رابط کاربری از این‌ها استفاده می‌کند
        $task['is_today_done']        = $s['is_today_done'];
        $task['can_complete']         = $s['can_complete'];
        $task['current_period_date']  = $s['current_period_date'];

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
