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
 *  کارهای دوره‌ای در بعضی جدول‌ها اصلا موعد نداشتند.
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
 * @param array|null $preloadedCompletionMap  🆕 خروجی pe_preloadCompletionDates —
 *        وقتی صفحه‌ای چند تسک را یک‌جا پردازش می‌کند (my-tasks.php، overview.php و...)
 *        به‌جای یک کوئری به‌ازای هر تسک دوره‌ای، همه را از این نقشه می‌خواند.
 * @return array           همان کار، به‌همراه فیلدهای محاسبه‌شده
 */
function enrichTaskDates(array $task, PDO $db, array $holidays, string $today, ?array $preloadedCompletionMap = null): array
{
    // مقادیر پیش‌فرض
    $task['overdue_periods']      = 0;
    $task['next_due_date']        = null;
    $task['days_remaining']       = null;
    $task['working_days_delayed'] = 0;
    // 🆕 کارهای روتین/فرآیندی (is_workflow_task=1) ساعتی نمایش داده می‌شن،
    // نه روزانه — چون task_type این‌ها هم زیر پوست همیشه 'periodic'ه، این دو
    // فیلد این‌جا (نه شاخه‌ی جداگانه) کنار همون فیلدهای روزانه ست می‌شن، تا
    // لایه‌ی نمایش بر اساس is_workflow_task انتخاب کنه کدومو نشون بده
    $task['hours_delayed']   = 0;
    $task['hours_remaining'] = null;

    $current = new DateTime($today);
    $current->setTime(0, 0, 0);

    // ══════════════════════════════════════════════════
    //  کار دوره‌ای (continuous)
    // ══════════════════════════════════════════════════
    if (($task['task_type'] ?? '') === 'continuous') {

        // ✅ موتور مشترک — تنها مرجع محاسبهٔ دوره
        $s = pe_state($db, $task, $holidays, $today, $preloadedCompletionMap);

        $task['overdue_periods']      = $s['overdue_periods'];
        $task['next_due_date']        = $s['next_due_date'];
        $task['days_remaining']       = $s['days_remaining'];
        $task['working_days_delayed'] = $s['working_days_delayed'];

        // فیلدهای جدید — رابط کاربری از این‌ها استفاده می‌کند
        $task['is_today_done']        = $s['is_today_done'];
        $task['can_complete']         = $s['can_complete'];
        $task['current_period_date']  = $s['current_period_date'];

        // 🆕 نیازمند تصمیم تمدید؟ — دقیقا هم‌معنی TaskManager::isReadyForRenewal()
        // (end_date < امروز + بدون تأیید در جریان/درخواست تمدید در جریان)،
        // به‌اضافه‌ی حذف کارهای از قبل بسته‌شده (تکمیل/تأیید/متوقف‌شده). این‌جا
        // (نه سمت جاوااسکریپت) محاسبه می‌شه تا بر پایه‌ی ساعت سرور باشه، نه
        // ساعت مرورگر
        //
        // 🔒 قبلاً اینجا <= بود (یعنی از خودِ روزِ end_date، نه فردایِ آن).
        // این با pe_state() (خطِ «بازهٔ کار تمام شده» در period-engine.php)
        // که کار را فقط وقتی «تمام‌شده» می‌داند که $today > $endDate،
        // یک‌روز ناهماهنگ بود: دقیقاً روزِ end_date، موتورِ دوره می‌گفت
        // «هنوز فعاله، چک‌لیستِ امروز را انجام بده» و چک‌لیست را هم برایِ
        // همان روز تازه می‌ساخت، ولی این‌جا هم‌زمان می‌گفت «تمام شد، تمدید کن».
        // با < ، این‌جا هم دقیقاً همان مرزِ pe_state() را رعایت می‌کند.
        $task['needs_renewal_decision'] = !empty($task['end_date'])
            && substr($task['end_date'], 0, 10) < $today
            && (int) ($task['is_pending_approval'] ?? 0) !== 1
            && (int) ($task['has_pending_renewal_request'] ?? 0) !== 1
            && !in_array($task['status'] ?? '', ['completed', 'approved', 'rejected'], true);

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

            // 🔒 کار روتین/فرآیندی: ساعتی، از روی effective deadline (بزرگ‌ترین
            // due_date/deadline/original_deadline) — قبلا فقط از روی خود
            // deadline محاسبه می‌شد، با این فرض که due_date/original_deadline
            // برای این‌ها «معمولا خالیه». این فرض همیشه درست نبود: چندتا کار
            // روتین واقعی پیدا شدن که برعکسش بود — deadline خالی، ولی due_date
            // پر (و ماه‌ها گذشته). با شرط قبلی، hours_delayed برای این‌ها روی
            // مقدار پیش‌فرض ۰ می‌موند، حتی وقتی ماه‌ها تأخیر داشتن — دقیقا همون
            // «۰ ساعت» ی که توی لیست تأخیردارها گزارش شد (که isOverdue سمت
            // جاوااسکریپت، با معیار مستقل خودش، درست تشخیص می‌داد تأخیرداره،
            // ولی عدد نمایش‌داده‌شده هیچ‌وقت محاسبه نمی‌شد).
            // اگه فقط due_date (بدون ساعت) موجود بود، انتهای همون روز
            // (۲۳:۵۹:۵۹) در نظر گرفته می‌شه — هم‌راستا با قاعده‌ی «کار امروز
            // تا فردا تأخیردار نیست» که working_days_delayed بالاتر هم ازش
            // پیروی می‌کنه.
            if (!empty($task['is_workflow_task'])) {
                $done = in_array($task['status'] ?? '', ['completed', 'approved'], true);
                if (!$done) {
                    $candidates = [];
                    foreach (['due_date', 'deadline', 'original_deadline'] as $f) {
                        if (!empty($task[$f])) {
                            $v = $task[$f];
                            $candidates[] = (strlen($v) <= 10) ? ($v . ' 23:59:59') : $v;
                        }
                    }
                    if ($candidates) {
                        $effectiveDeadline = max($candidates);
                        $nowDateTime = date('Y-m-d H:i:s');
                        $task['hours_delayed']   = calcHourDelay($effectiveDeadline, $nowDateTime);
                        $task['hours_remaining'] = calcHourRemaining($effectiveDeadline, $nowDateTime);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("enrichTaskDates (periodic) task#{$task['id']}: " . $e->getMessage());
        }
    }

    return $task;
}
