<?php
/**
 * افزودن سطح دسترسی ریزتر به بیننده‌های تسک — پیش‌فرض همه‌چیز روشنه
 * (چون معنای پایه‌ای «بیننده» دیدن کامل هست)، ولی موقع افزودن می‌شه
 * پیوست‌ها/تاریخچه رو برای یک نفر خاص خاموش کرد.
 */

return [

    'description' => 'Add per-viewer visibility flags (attachments/history) to task_viewers',

    'up' => function (PDO $db) {
        $db->exec("ALTER TABLE `task_viewers`
            ADD COLUMN `can_view_attachments` TINYINT(1) NOT NULL DEFAULT 1 AFTER `user_id`,
            ADD COLUMN `can_view_history` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_view_attachments`
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("ALTER TABLE `task_viewers` DROP COLUMN `can_view_attachments`, DROP COLUMN `can_view_history`");
    },

];
