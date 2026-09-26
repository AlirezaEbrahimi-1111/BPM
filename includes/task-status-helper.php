<?php
/**
 * طبقه‌بندی‌کننده‌ی کاننیک «معوقه/امروز/برچسب وضعیت» در سمت PHP —
 * دقیقا هم‌معنی با TF.isOverdue/TF.isDueToday/TF.statusBadge در
 * assets/js/task-filters.js (تنها مرجع این منطق تا امروز، فقط سمت
 * مرورگر). چون دستیار هوش‌مصنوعی (api/ai-assistant/data/task-summary.php)
 * باید دقیقا همون چیزی رو بگه که کاربر روی داشبورد می‌بینه، این فایل
 * ساخته شد تا همون منطق رو، بدون بازنویسی ساده‌شده/ناقص، سمت سرور هم
 * در دسترس باشه.
 *
 * ورودی همه‌ی توابع این فایل، آرایه‌ی یک تسک است که از قبل با
 * enrichTaskDates() (includes/task-dates-helper.php) غنی شده — یعنی
 * next_due_date/overdue_periods از همون‌جا میان، نه این‌جا دوباره
 * محاسبه بشن. علاوه‌براین، ستون‌های زیر باید در کوئری اصلی JOIN شده
 * باشن (دقیقا مثل api/tasks/my-tasks.php):
 *   dr.current_approver_id, dr.created_at AS deadline_request_date,
 *   ph.last_pending_date
 * (dr = deadline_requests با status='pending', ph = آخرین رویداد
 * pending_approval که بعدش rejected نشده)
 */

require_once __DIR__ . '/period-engine.php';

/** معادل isDone در task-filters.js */
function taskIsDone(array $t): bool
{
    return $t['status'] === 'completed' || $t['status'] === 'approved';
}

/** معادل isExpired — یعنی بازه‌ی کار دوره‌ای تمام شده */
function taskIsExpired(array $t, string $today): bool
{
    return !empty($t['end_date']) && substr($t['end_date'], 0, 10) < $today;
}

/**
 * آیا این کار دوره‌ای نیازمند تصمیم تمدید است؟ — از قبل توسط
 * enrichTaskDates() (includes/task-dates-helper.php) محاسبه و روی خود
 * کار نشسته؛ این‌جا فقط خونده می‌شه (نه بازمحاسبه، چون باید بر پایه‌ی
 * ساعت سرور باشه، نه فراخوانی دوباره)
 */
function taskNeedsRenewalDecision(array $t): bool
{
    return !empty($t['needs_renewal_decision']);
}

/** معادل isWaitingMyApproval */
function taskIsWaitingMyApproval(array $t, int $userId): bool
{
    if (empty($t['is_pending_approval'])) return false;
    return $userId === (int) ($t['creator_id'] ?? 0) || $userId === (int) ($t['current_approver_id'] ?? 0);
}

/** معادل isWaitingMyDeadline */
function taskIsWaitingMyDeadline(array $t, int $userId): bool
{
    if (empty($t['has_pending_deadline_request'])) return false;
    return $userId === (int) ($t['current_approver_id'] ?? 0);
}

/**
 * معادل TF.isOverdue — دقیقا همون ترتیب و همون قانون‌ها، از جمله
 * لیست سفید وضعیت‌های کار مقطعی (نه لیست سیاه؛ TaskManager::getTaskStats
 * از یک لیست سیاه متفاوت استفاده می‌کنه که عمدا این‌جا دنبال نشده،
 * چون منبع صحت همینه، نه اون)
 */
function taskIsOverdue(array $t, int $userId, string $today): bool
{
    if (taskIsDone($t)) return false;

    if (
        taskIsWaitingMyApproval($t, $userId)
        && !empty($t['last_pending_date'])
        && substr($t['last_pending_date'], 0, 10) < $today
    ) {
        return true;
    }

    if (
        taskIsWaitingMyDeadline($t, $userId)
        && !empty($t['deadline_request_date'])
        && substr($t['deadline_request_date'], 0, 10) < $today
    ) {
        return true;
    }

    if (!empty($t['is_workflow_task'])) {
        $due = $t['next_due_date'] ?? null;
        if ($due && $due < $today) return true;
    }

    if ($t['task_type'] === 'continuous') {
        if (taskIsExpired($t, $today)) return false;
        return (int) ($t['overdue_periods'] ?? 0) > 0;
    }

    if ($t['task_type'] === 'periodic') {
        $due = $t['next_due_date'] ?? null;
        return !empty($due) && $due < $today
            && in_array($t['status'], ['not_started', 'in_progress', 'delegated'], true);
    }

    return false;
}

/** معادل TF.isDueToday */
function taskIsDueToday(array $t, int $userId, string $today): bool
{
    if (taskIsDone($t)) return false;
    if (taskIsWaitingMyApproval($t, $userId)) return true;
    if (taskIsWaitingMyDeadline($t, $userId)) return true;

    if (!empty($t['is_workflow_task']) && in_array($t['status'], ['in_progress', 'not_started'], true)) {
        $wfDue = $t['next_due_date'] ?? null;
        if ($wfDue && $wfDue > $today) return false;
        return true;
    }

    if ($t['task_type'] === 'continuous') {
        if (taskNeedsRenewalDecision($t)) return true;
        if (taskIsExpired($t, $today)) return false;
        if ((int) ($t['overdue_periods'] ?? 0) > 0) return true;
        return ($t['next_due_date'] ?? null) === $today;
    }

    if ($t['task_type'] === 'periodic') {
        $due = $t['next_due_date'] ?? null;
        return !empty($due) && $due <= $today;
    }

    return false;
}

/**
 * جدول برچسب‌ها — طبق بررسی، سه نسخه‌ی مختلف و ناقص از این جدول در
 * جاهای مختلف پروژه بود (TF.statusCfg در جاوااسکریپت، یک ثابت محلی در
 * api/chat/link-preview.php، و نسخه‌ی قبلی همین فایل در task-summary.php)
 * که با هم اختلاف داشتن. این‌جا کامل‌ترین نسخه (اجتماع هر سه) است.
 */
const TASK_STATUS_LABELS = [
    'not_started'           => 'شروع نشده',
    'in_progress'           => 'در حال انجام',
    'pending_approval'      => 'در انتظار تأیید',
    'completed'             => 'تکمیل شده',
    'approved'              => 'تأیید شده',
    'delegated'             => 'ارجاع شده',
    'rejected'              => 'متوقف شده(کارهای عادی)',
    'stopped'               => 'متوقف شده(فرآیندها)',
    'period_done'           => 'دوره انجام شد',
    'termination_requested' => 'در انتظار اتمام',
];

/**
 * معادل TF.statusBadge — اولویت: در انتظار تأیید > معوقه > برچسب خام وضعیت.
 * خروجی: ['label' => رشته‌ی فارسی, 'is_overdue' => bool, 'is_due_today' => bool]
 */
function taskStatusInfo(array $t, int $userId, string $today): array
{
    $isOverdue = taskIsOverdue($t, $userId, $today);
    $isDueToday = !$isOverdue && taskIsDueToday($t, $userId, $today);

    if (taskNeedsRenewalDecision($t)) {
        $label = 'نیازمند تمدید';
    } elseif ($t['status'] === 'pending_approval') {
        $label = TASK_STATUS_LABELS['pending_approval'];
    } elseif (taskIsWaitingMyDeadline($t, $userId)) {
        // مثل pending_approval بالا: وقتی کاربر جاری تأییدکننده‌ی یک
        // درخواست تمدید موعد است، این چیزیه که واقعا باید ببینه —
        // نه وضعیت خام کار یا صرفا «عقب افتاده»
        $label = 'درخواست تمدید موعد';
    } elseif ($isOverdue) {
        $label = 'عقب افتاده';
    } else {
        $label = TASK_STATUS_LABELS[$t['status']] ?? $t['status'];
    }

    return ['label' => $label, 'is_overdue' => $isOverdue, 'is_due_today' => $isDueToday];
}
