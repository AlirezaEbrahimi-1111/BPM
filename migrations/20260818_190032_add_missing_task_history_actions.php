<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودنِ ۷ مقدارِ جاافتاده به ENUM ستونِ task_history.action
 *  تاریخ: ۱۴۰۵/۰۵/۲۷
 * ───────────────────────────────────────────────────────────────────
 *  چرا؟
 *    کدِ برنامه از قبل این ۷ مقدار رو می‌نویسه (checklist_sync/
 *    checklist_assigned/checklist_done از api/checklist/*.php،
 *    renewal_requested از TaskManager::requestPeriodRenewal،
 *    termination_requested از api/tasks/request-termination.php،
 *    in_progress/not_started از مسیرِ عمومیِ updateTaskStatus) — ولی
 *    هیچ‌کدوم تویِ ENUMِ فعلیِ ستون نبودن. چون این دیتابیس STRICT نیست،
 *    درجِ مقدارِ خارج از ENUM بی‌صدا به رشتهٔ خالی تبدیل می‌شه (نه خطا).
 *    رویِ پروداکشن همین الان ۱۹۲۰ ردیف با action خالی وجود داره —
 *    بزرگ‌ترین دسته‌یِ کلِ جدول — که همه‌شون قربانیِ همین مشکلن.
 *
 *  ⚠️ این migration فقط جلویِ خرابیِ ردیف‌هایِ جدید رو می‌گیره؛ ۱۹۲۰
 *  ردیفِ خالیِ موجود قابلِ بازیابی نیستن (مقدارِ واقعی‌شون هیچ‌جا ثبت نشده).
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودنِ renewal_requested/termination_requested/checklist_sync/checklist_assigned/checklist_done/in_progress/not_started به ENUM ستون task_history.action',

    'up' => function (PDO $db) {
        $db->exec("
            ALTER TABLE `task_history`
            MODIFY COLUMN `action` ENUM(
                'created','assigned','completed','stopped','delegated','updated',
                'approved','rejected','pending_approval','deadline_extended',
                'renewal_step_approved','renewal_applied','renewal_rejected',
                'workflow_prev_note','deadline_rejected',
                'renewal_requested','termination_requested',
                'checklist_sync','checklist_assigned','checklist_done',
                'in_progress','not_started'
            ) NOT NULL
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("
            ALTER TABLE `task_history`
            MODIFY COLUMN `action` ENUM(
                'created','assigned','completed','stopped','delegated','updated',
                'approved','rejected','pending_approval','deadline_extended',
                'renewal_step_approved','renewal_applied','renewal_rejected',
                'workflow_prev_note','deadline_rejected'
            ) NOT NULL
        ");
    },

];
