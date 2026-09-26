<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودن deadline_rejected به ENUM ستون task_history.action
 *  تاریخ: ۱۴۰۵/۰۵/۱۲
 * ───────────────────────────────────────────────────────────────────
 *  چرا؟
 *    وقتی درخواست تمدید موعد رد می‌شود (api/tasks/reject-deadline.php)،
 *    هیچ ردیفی در task_history ثبت نمی‌شد — برخلاف تأیید که همیشه
 *    action='deadline_extended' ثبت می‌کند. برای نمایش رویداد رد
 *    (به‌همراه دلیل، اگر وارد شده) در تاریخچهٔ کار، این مقدار جدید لازم
 *    است. چون پایگاه‌داده STRICT نیست، درج مقدار خارج از ENUM بی‌صدا
 *    به رشتهٔ خالی تبدیل می‌شود (نه خطا) — یعنی بدون این مهاجرت،
 *    ردیف جدید با action خالی/نادرست ذخیره می‌شد.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودن deadline_rejected به ENUM ستون task_history.action',

    'up' => function (PDO $db) {
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

    'down' => function (PDO $db) {
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

];
