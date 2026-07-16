<?php

/**
 * توابع کمکی تسک‌های دوره‌ای (continuous)
 *
 * ⚠️ محاسبهٔ دوره اینجا انجام نمی‌شود.
 *    تنها مرجع: includes/period-engine.php
 */

require_once __DIR__ . '/period-engine.php';

/**
 * اگر دورهٔ جدید فرا رسیده باشد، کار را از period_done به حالت فعال برمی‌گرداند.
 *
 * ✅ اصلاح: حالا از موتور مشترک استفاده می‌کند. پیش از این، بخشش‌ها
 *    (overdue_forgiven_credit) را نادیده می‌گرفت و کار را بی‌پایان باز می‌کرد.
 */
function maybeStartNextPeriod($db, &$task, $user_id, $holidays = null)
{
    if (($task['task_type'] ?? '') !== 'continuous')  return false;
    if (($task['status'] ?? '')    !== 'period_done') return false;
    if (empty($task['start_date']))                   return false;

    if ($holidays === null) {
        $holidays = getHolidaySet($db);
    }

    try {
        $state = pe_state($db, $task, $holidays);

        // دورهٔ امروز هنوز بسته نشده → کار را باز کن
        if (!$state['is_today_done'] && !$state['finished'] && $state['started']) {

            $db->prepare(
                "UPDATE tasks SET status = 'not_started', updated_at = NOW() WHERE id = ?"
            )->execute([$task['id']]);

            $db->prepare(
                "INSERT INTO task_history (task_id, from_user_id, action, notes)
                 VALUES (?, NULL, 'created', ?)"
            )->execute([
                $task['id'],
                'دورهٔ ' . $state['current_period_date'] . ' آغاز شد (خودکار)'
            ]);

            $task['status'] = 'not_started';
            return true;
        }
    } catch (Exception $e) {
        error_log("maybeStartNextPeriod error task#{$task['id']}: " . $e->getMessage());
    }

    return false;
}
