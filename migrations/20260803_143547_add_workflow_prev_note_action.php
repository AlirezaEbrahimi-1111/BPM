<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودن مقدار workflow_prev_note به ENUM ستون task_history.action
 *  تاریخ: ۱۴۰۵/۰۵/۱۲
 * ───────────────────────────────────────────────────────────────────
 *  چرا؟
 *    برای نمایش توضیحات مرحلهٔ قبل به مسئول مرحلهٔ بعد (کارهای روتین)،
 *    یک ردیف جدید در task_history با action = 'workflow_prev_note' ثبت
 *    می‌شود (در WorkflowManager::completeStep). چون این مقدار در ENUM
 *    فعلی نیست و پایگاه‌داده در حالت STRICT هم نیست، درج آن بی‌سروصدا به
 *    رشتهٔ خالی تبدیل می‌شد (نه خطا) — یعنی خرابی خاموش داده.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودن workflow_prev_note به ENUM ستون task_history.action',

    'up' => function (PDO $db) {
        $db->exec("
            ALTER TABLE `task_history`
            MODIFY COLUMN `action` ENUM(
                'created','assigned','completed','stopped','delegated','updated',
                'approved','rejected','pending_approval','deadline_extended',
                'renewal_step_approved','renewal_applied','renewal_rejected',
                'workflow_prev_note'
            ) NOT NULL
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("
            ALTER TABLE `task_history`
            MODIFY COLUMN `action` ENUM(
                'created','assigned','completed','stopped','delegated','updated',
                'approved','rejected','pending_approval','deadline_extended',
                'renewal_step_approved','renewal_applied','renewal_rejected'
            ) NOT NULL
        ");
    },

];
