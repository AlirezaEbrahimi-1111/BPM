<?php

/**
 * ═══════════════════════════════════════════════════════════════════
 *  includes/delayed-tasks-helper.php
 *  منبع مشترکِ «کدوم کارها تأخیردارن» — سه نوع کار (مقطعی/دوره‌ای/روتین).
 * ───────────────────────────────────────────────────────────────────
 *  🔒 طبق درسِ همیشگیِ پروژه («یک مفهوم نباید با دو روش جدا محاسبه بشه»،
 *  دقیقا همون چیزی که با باگِ تعدادِ اعضایِ گروهِ چت گرفتیم): این سه
 *  کوئری قبلا فقط داخلِ api/reports/top-delayed-users.php (به‌صورتِ
 *  inline، برای شمارشِ تجمیعی) وجود داشتن. حالا اینجا استخراج شدن تا
 *  api/reports/delayed-tasks-for.php (که ردیف‌هایِ واقعیِ کارِ یک
 *  کاربر/واحدِ خاص رو می‌خواد، نه فقط شمارش) هم دقیقا همون تعریفِ
 *  «تأخیردار» رو استفاده کنه — نه یک تعریفِ دومِ جدا که ممکنه کم‌کم
 *  از اولی جدا بیفته.
 *
 *  هر تابع فقط ردیف‌هایِ خامِ کار رو برمی‌گردونه (id/title/status/
 *  priority/assignee_id/activity_section + تاریخِ مؤثر)؛ محاسبه‌ی
 *  «چقدر تأخیر» (روزِ کاری یا ساعت) با calcPeriodicDelayWorkingDays/
 *  pe_state/calcHourDelay به عهده‌ی صدازننده‌ست (چون aggregate و detail
 *  به شکلِ متفاوتی از نتیجه استفاده می‌کنن).
 * ═══════════════════════════════════════════════════════════════════
 */

/**
 * کارهای مقطعیِ تأخیردار (due_date/deadline/original_deadline گذشته و
 * تکمیل‌نشده). effective_due با HAVING محاسبه می‌شه (نه max سمتِ PHP) —
 * دلیلش: کامنتِ همین کوئری در top-delayed-users.php.
 */
function getDelayedPeriodicTasks(PDO $db, int $org_id, string $today): array
{
    $stmt = $db->prepare("
        SELECT id, title, status, priority, assignee_id, activity_section,
            GREATEST(
                COALESCE(CAST(due_date AS DATE), CAST('1000-01-01' AS DATE)),
                COALESCE(CAST(deadline AS DATE), CAST('1000-01-01' AS DATE)),
                COALESCE(CAST(original_deadline AS DATE), CAST('1000-01-01' AS DATE))
            ) AS effective_due
        FROM tasks
        WHERE organization_id = ?
          AND is_deleted = 0
          AND task_type = 'periodic'
          AND status NOT IN ('completed', 'approved', 'stopped', 'rejected')
        HAVING effective_due > '1000-01-01' AND effective_due < ?
    ");
    $stmt->execute([$org_id, $today]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * کارهای دوره‌ایِ باز (بدون فیلترِ تأخیر — تشخیصِ overdue_periods > 0 با
 * pe_state سمتِ صدازننده انجام می‌شه، چون موتورِ دوره‌ای فقط با اجرایِ
 * pe_state روی هر ردیف قابل‌محاسبه‌ست، نه با SQL خام).
 */
function getOpenContinuousTasks(PDO $db, int $org_id): array
{
    $stmt = $db->prepare("
        SELECT * FROM tasks
        WHERE organization_id = ?
          AND is_deleted = 0
          AND task_type = 'continuous'
          AND status NOT IN ('completed', 'approved', 'stopped', 'rejected')
    ");
    $stmt->execute([$org_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * کارهای روتینِ تأخیردار (مرحله‌ی active/pending/delayed و از موعد گذشته).
 */
function getDelayedWorkflowTasks(PDO $db, int $org_id, string $now): array
{
    $stmt = $db->prepare("
        SELECT t.id, t.title, t.status, t.priority, t.assignee_id, t.activity_section,
            GREATEST(
                COALESCE(CAST(CONCAT(t.due_date, ' 23:59:59') AS DATETIME), CAST('1000-01-01 00:00:00' AS DATETIME)),
                COALESCE(CAST(t.deadline AS DATETIME), CAST('1000-01-01 00:00:00' AS DATETIME)),
                COALESCE(CAST(t.original_deadline AS DATETIME), CAST('1000-01-01 00:00:00' AS DATETIME))
            ) AS effective_deadline
        FROM tasks t
        JOIN workflow_instance_steps wis ON wis.task_id = t.id
        WHERE t.organization_id = ?
          AND t.is_deleted = 0
          AND t.is_workflow_task = 1
          AND wis.status IN ('active', 'pending', 'delayed')
          AND (t.due_date IS NOT NULL OR t.deadline IS NOT NULL OR t.original_deadline IS NOT NULL)
          AND t.status NOT IN ('completed', 'approved', 'stopped', 'rejected')
        HAVING effective_deadline > '1000-01-01 00:00:00' AND effective_deadline < ?
    ");
    $stmt->execute([$org_id, $now]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
