<?php
/**
 * افزودنِ عنوانِ سفارشیِ مدیرِ گروه (مثلِ تلگرام: «پشتیبان»، «ناظر» به‌جایِ
 * «مدیر» عمومی) — فقط سازنده‌ی گروه موقعِ ارتقا/ویرایشِ یک مدیر تنظیمش می‌کنه.
 */

return [

    'description' => 'Add admin_title column to chat_participants for custom Telegram-style admin badges',

    'up' => function (PDO $db) {
        $db->exec("
            ALTER TABLE `chat_participants`
            ADD COLUMN `admin_title` VARCHAR(30) NULL DEFAULT NULL AFTER `permissions`
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("ALTER TABLE `chat_participants` DROP COLUMN `admin_title`");
    },

];
