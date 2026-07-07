<?php
/**
 * توابع کمکی برای مدیریت تسک‌های دوره‌ای (continuous)
 */

/**
 * بررسی و برگرداندن یک تسک دوره‌ای از period_done به حالت فعال،
 * اگر موعد دوره‌ی بعدی فرا رسیده باشد.
 *
 * ورودی:
 *   $db       : اتصال دیتابیس (PDO)
 *   $task     : آرایه‌ی تسک (باید شامل id, status, task_type, period_type, start_date باشد)
 *   $user_id  : شناسه‌ی کاربر جاری (برای ثبت در تاریخچه)
 *   $holidays : (اختیاری) مجموعه‌ی تعطیلات، اگر در دسترس باشد
 *
 * خروجی:
 *   true  اگر وضعیت تغییر کرد (دوره‌ی جدید آغاز شد)
 *   false اگر تغییری لازم نبود
 */
function maybeStartNextPeriod($db, &$task, $user_id, $holidays = null)
{
    // فقط برای تسک دوره‌ایِ در وضعیت period_done
    if (($task['task_type'] ?? '') !== 'continuous') return false;
    if (($task['status'] ?? '') !== 'period_done') return false;
    if (empty($task['start_date'])) return false;

    try {
        $start_date = new DateTime($task['start_date']);
        $current_date = new DateTime(date('Y-m-d'));
        $current_date->setTime(0, 0, 0);

        // تعداد دوره‌های انجام‌شده تا الان
        $count_stmt = $db->prepare(
            "SELECT COUNT(*) FROM task_history WHERE task_id = ? AND action = 'completed'"
        );
        $count_stmt->execute([$task['id']]);
        $completed = (int) $count_stmt->fetchColumn();

        // محاسبه‌ی موعد دوره‌ی بعدی (بر اساس start_date و تعداد دوره‌های انجام‌شده)
        $next_due = clone $start_date;
        for ($i = 0; $i < $completed; $i++) {
            switch ($task['period_type']) {
                case 'daily':
                    // اگر تابع روز کاری در دسترس بود، از آن استفاده کن
                    if (function_exists('isWorkingDay') && $holidays !== null) {
                        do {
                            $next_due->modify('+1 day');
                        } while (!isWorkingDay($next_due, $holidays));
                    } else {
                        $next_due->modify('+1 day');
                    }
                    break;
                case 'weekly':
                    $next_due->modify('+1 week');
                    break;
                case 'monthly':
                    $next_due->modify('+1 month');
                    break;
            }
        }

        // اگر موعد دوره‌ی بعدی رسیده یا گذشته → کار را فعال کن
        if ($current_date >= $next_due) {
            $db->prepare(
                "UPDATE tasks SET status = 'not_started', updated_at = NOW() WHERE id = ?"
            )->execute([$task['id']]);

            // ثبت در تاریخچه
            $db->prepare(
                "INSERT INTO task_history (task_id, from_user_id, action, notes)
                 VALUES (?, ?, 'created', ?)"
            )->execute([$task['id'], $user_id, 'دوره‌ی جدید آغاز شد']);

            // به‌روزرسانی وضعیت در حافظه (تا نمایش درست باشد)
            $task['status'] = 'not_started';
            return true;
        }
    } catch (Exception $e) {
        error_log("maybeStartNextPeriod error task#{$task['id']}: " . $e->getMessage());
    }

    return false;
}