<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودن ایندکس‌های عملکردی به جدول tasks
 *  تاریخ: ۱۴۰۵/۰۴/۲۱
 * ───────────────────────────────────────────────────────────────────
 *  چرا؟
 *    کوئری‌های اصلی سامانه (my-tasks، all-tasks، delegated-tasks) همیشه
 *    بر اساس این ستون‌ها فیلتر می‌کنند:
 *        organization_id, assignee_id, creator_id, is_deleted, status
 *
 *    بدون ایندکس، MySQL مجبور است کل جدول را بخواند (Full Table Scan).
 *    با رشد داده، این کوئری‌ها به‌شدت کند می‌شوند.
 *
 *  اثر:
 *    • سرعت بارگذاری لیست کارها
 *    • کاهش بار سرور دیتابیس
 *    • گامی مستقیم در جهت «مقیاس‌پذیری» که ارزیاب مطرح کرده بود
 *
 *  ⚠️ ایمنی:
 *    افزودن ایندکس، داده را تغییر نمی‌دهد و بی‌خطر است.
 *    روی جدول بزرگ ممکن است چند ثانیه طول بکشد.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودن ایندکس‌های عملکردی به جدول tasks',

    'up' => function (PDO $db) {

        /**
         * تابع کمکی: ایندکس را فقط اگر وجود ندارد بساز.
         * (MySQL دستور CREATE INDEX IF NOT EXISTS ندارد)
         */
        $addIndex = function (string $table, string $index, string $columns) use ($db) {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name   = ?
                  AND index_name   = ?
            ");
            $stmt->execute([$table, $index]);

            if ((int) $stmt->fetchColumn() === 0) {
                $db->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columns})");
            }
        };

        // ── ایندکس‌های اصلی جدول tasks ──────────────────────
        // ترکیبی: سازمان + حذف‌نشده  (پرکاربردترین فیلتر در همهٔ کوئری‌ها)
        $addIndex('tasks', 'idx_tasks_org_deleted', '`organization_id`, `is_deleted`');

        // مسئول انجام کار (my-tasks)
        $addIndex('tasks', 'idx_tasks_assignee', '`assignee_id`, `is_deleted`');

        // سازندهٔ کار (delegated-tasks)
        $addIndex('tasks', 'idx_tasks_creator', '`creator_id`, `is_deleted`');

        // واحد فعالیت (کارهای واگذارشده به یک واحد)
        $addIndex('tasks', 'idx_tasks_section', '`activity_section`, `organization_id`');

        // وضعیت (فیلترهای داشبورد)
        $addIndex('tasks', 'idx_tasks_status', '`status`, `is_deleted`');

        // کارهای فرآیندی
        $addIndex('tasks', 'idx_tasks_workflow', '`workflow_instance_id`');

        // ── جدول task_history (پرخوانده‌ترین جدول جانبی) ────
        $addIndex('task_history', 'idx_history_task_action', '`task_id`, `action`');

        // ── جدول notifications ───────────────────────────────
        $addIndex('notifications', 'idx_notif_user_read', '`user_id`, `is_read`');

        // ── جدول workflow_instances ──────────────────────────
        $addIndex('workflow_instances', 'idx_wi_template_status', '`template_id`, `status`, `is_deleted`');
    },

    'down' => function (PDO $db) {

        /** تابع کمکی: ایندکس را فقط اگر وجود دارد حذف کن */
        $dropIndex = function (string $table, string $index) use ($db) {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name   = ?
                  AND index_name   = ?
            ");
            $stmt->execute([$table, $index]);

            if ((int) $stmt->fetchColumn() > 0) {
                $db->exec("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
            }
        };

        $dropIndex('tasks', 'idx_tasks_org_deleted');
        $dropIndex('tasks', 'idx_tasks_assignee');
        $dropIndex('tasks', 'idx_tasks_creator');
        $dropIndex('tasks', 'idx_tasks_section');
        $dropIndex('tasks', 'idx_tasks_status');
        $dropIndex('tasks', 'idx_tasks_workflow');
        $dropIndex('task_history', 'idx_history_task_action');
        $dropIndex('notifications', 'idx_notif_user_read');
        $dropIndex('workflow_instances', 'idx_wi_template_status');
    },

];
