<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: جدول چک‌لیست سطح الگوی مرحلهٔ روتین
 *  تاریخ: ۱۴۰۵/۰۵/۱۳
 * ───────────────────────────────────────────────────────────────────
 *  چرا؟
 *    کاربر درخواست داد بتواند هنگام تعریف یک روتین، برای هر مرحله
 *    چک‌لیست تعریف کند؛ مسئول مرحله باید همهٔ آیتم‌ها را تیک بزند
 *    قبل از این‌که بتواند دکمهٔ «تکمیل» مرحله را بزند.
 *
 *    این جدول فقط سطح «الگو»ست (وابسته به workflow_steps.step_id).
 *    وقتی یک نمونهٔ روتین اجرا می‌شود و برای هر مرحله یک ردیف tasks
 *    واقعی ساخته می‌شود (WorkflowManager::startWorkflow)، آیتم‌های
 *    همین جدول در همان لحظه به‌عنوان ردیف جدید در task_checklist_items
 *    (با task_id تازه) کپی می‌شوند — یعنی چک‌لیست خود اجرا از جدول
 *    موجود task_checklist_items استفاده می‌کند، نه جدول جدید.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'ساخت جدول workflow_step_checklist_items',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `workflow_step_checklist_items` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `step_id`     INT NOT NULL COMMENT 'ارجاع به workflow_steps.id',
                `title`       VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `sort_order`  INT DEFAULT 0,
                `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_step_id` (`step_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
              COMMENT='آیتم‌های چک‌لیست الگو برای هر مرحلهٔ روتین'
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `workflow_step_checklist_items`");
    },

];
