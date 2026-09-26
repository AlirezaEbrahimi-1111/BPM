<?php
/**
 * وقتی یه آیتم چک‌لیست به فردی ارجاع می‌شه، اون فرد باید بتونه موقع
 * تأیید آیتم، یه فایل هم پیوست کنه (مثلا عکس رسید انبار). سیستم
 * پیوست تسک از قبل آماده‌ست (step_ids برای مراحل روتین)؛ فقط یه ستون
 * مشابه برای تگ‌کردن پیوست به یه آیتم چک‌لیست خاص لازمه.
 */

return [

    'description' => 'Add checklist_item_id to task_attachments',

    'up' => function (PDO $db) {
        $db->exec("ALTER TABLE `task_attachments`
            ADD COLUMN `checklist_item_id` INT NULL AFTER `task_id`,
            ADD INDEX `idx_checklist_item_id` (`checklist_item_id`)
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("ALTER TABLE `task_attachments` DROP INDEX `idx_checklist_item_id`, DROP COLUMN `checklist_item_id`");
    },

];
