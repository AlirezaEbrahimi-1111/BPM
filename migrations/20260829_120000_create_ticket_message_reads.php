<?php
/**
 * ردیابیِ «آخرین‌بار که هر کاربر یک تیکت را دید».
 * برای نمایشِ بجِ «پیامِ دیده‌نشده» کنارِ تب تیکت‌ها در هدر و روی هر ردیفِ تیکت:
 * تیکت وقتی «دیده‌نشده» است که پیامی از کسِ دیگری بعد از last_read_at ثبت شده باشد.
 */

return [

    'description' => 'Per-user last-read timestamp for ticket message threads (unseen badge)',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ticket_message_reads` (
                `ticket_id`    INT NOT NULL,
                `user_id`      INT NOT NULL,
                `last_read_at` DATETIME NOT NULL,
                PRIMARY KEY (`ticket_id`, `user_id`),
                KEY `idx_tmr_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `ticket_message_reads`");
    },

];
