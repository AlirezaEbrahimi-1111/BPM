<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودنِ attachment_added / attachment_removed به ENUM
 *          ستونِ task_history.action
 *  تاریخ: ۱۴۰۵/۰۶/۱۴
 * ───────────────────────────────────────────────────────────────────
 *  چرا؟
 *    api/tasks/upload-attachment.php و delete-attachment.php حالا برایِ
 *    هر بارگذاری/حذفِ فایل یک ردیف در task_history می‌زنند، ولی این دو
 *    مقدار در ENUMِ ستون نبودند. چون دیتابیس STRICT نیست، درجِ مقدارِ
 *    خارج از ENUM بی‌صدا به رشتهٔ خالی تبدیل می‌شود — برای همین در
 *    «تاریخچهٔ فعالیت‌ها» بجِ این ردیف‌ها «—» نمایش داده می‌شد.
 *
 *  up همچنین یک اصلاحِ محدود انجام می‌دهد: ردیف‌هایی که action خالی دارند
 *  ولی notes دقیقاً با قالبِ جملهٔ خودِ همین قابلیت می‌خورد
 *  («فایل «...» را بارگذاری/حذف کرد») → action درستشان بازنویسی می‌شود.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودنِ attachment_added/attachment_removed به ENUM ستون task_history.action',

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
                'in_progress','not_started',
                'attachment_added','attachment_removed'
            ) NOT NULL
        ");

        // اصلاحِ محدودِ ردیف‌های خالیِ اخیرِ همین قابلیت (قالبِ notes یکتاست)
        $db->exec("
            UPDATE `task_history`
            SET `action` = 'attachment_added'
            WHERE `action` = '' AND `notes` LIKE 'فایل «%» را بارگذاری کرد'
        ");
        $db->exec("
            UPDATE `task_history`
            SET `action` = 'attachment_removed'
            WHERE `action` = '' AND `notes` LIKE 'فایل «%» را حذف کرد'
        ");
    },

    'down' => function (PDO $db) {
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

];
