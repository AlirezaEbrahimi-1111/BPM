<?php
/**
 * حذفِ منطقیِ تک‌تک پیام‌هایِ تیکت (نه کلِ تیکت — که از قبل با
 * tickets.deleted_at پشتیبانی می‌شد). لازم شد چون یه پاسخِ اشتباه از
 * اکانتِ یه کاربر توی یه تیکت ارسال شده بود.
 */

return [

    'description' => 'Add deleted_at to ticket_messages for per-message soft delete',

    'up' => function (PDO $db) {
        $db->exec("ALTER TABLE `ticket_messages`
            ADD COLUMN `deleted_at` DATETIME NULL AFTER `created_at`
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("ALTER TABLE `ticket_messages` DROP COLUMN `deleted_at`");
    },

];
