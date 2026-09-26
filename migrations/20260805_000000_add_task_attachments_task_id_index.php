<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: ایندکس روی task_attachments.task_id
 *  تاریخ: ۱۴۰۵/۰۵/۱۴
 * ───────────────────────────────────────────────────────────────────
 *  چرا؟
 *    بررسی کندی صفحات «نظارت بر کارها» و «کارهای من» نشان داد که
 *    task_attachments هیچ ایندکسی جز کلید اصلی ندارد. زیرکوئری جستجوی
 *    نام فایل‌های پیوستی (`WHERE ta.task_id = t.id`، داخل history_text
 *    در TaskManager.php / api/tasks/my-tasks.php / api/tasks/overview.php)
 *    برای هر ردیف تسک، یک جستجوی کامل روی کل جدول انجام می‌دهد.
 *    با رشد تعداد پیوست‌ها، این هزینه به‌شدت بالا می‌رود.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودن ایندکس روی task_attachments.task_id',

    'up' => function (PDO $db) {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'task_attachments'
              AND index_name   = 'idx_task_attachments_task_id'
        ");
        $stmt->execute();

        if ((int) $stmt->fetchColumn() === 0) {
            $db->exec("
                ALTER TABLE `task_attachments`
                ADD INDEX `idx_task_attachments_task_id` (`task_id`)
            ");
        }
    },

    'down' => function (PDO $db) {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = 'task_attachments'
              AND index_name   = 'idx_task_attachments_task_id'
        ");
        $stmt->execute();

        if ((int) $stmt->fetchColumn() > 0) {
            $db->exec("ALTER TABLE `task_attachments` DROP INDEX `idx_task_attachments_task_id`");
        }
    },

];
