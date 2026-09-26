<?php
/**
 * افزودن سومین سطح دسترسی ریزتر به بیننده‌های تسک: چک‌لیست — تا الان
 * چک‌لیست همیشه برای هر بیننده‌ای قابل‌مشاهده بود (بدون هیچ کنترلی)،
 * درست مثل پیوست/تاریخچه، پیش‌فرض روشنه.
 */

return [

    'description' => 'Add can_view_checklist flag to task_viewers',

    'up' => function (PDO $db) {
        $db->exec("ALTER TABLE `task_viewers`
            ADD COLUMN `can_view_checklist` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_view_history`
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("ALTER TABLE `task_viewers` DROP COLUMN `can_view_checklist`");
    },

];
