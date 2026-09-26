<?php
/**
 * افزودنِ ستونِ type به chat_messages — برایِ پیام‌هایِ سیستمی/رویدادی
 * (مثلِ «فلان کاربر به گروه اضافه شد») که باید متفاوت از پیامِ معمولی
 * (بدونِ حبابِ چت، بدونِ آواتار، وسط‌چین) نمایش داده بشن — دقیقاً مثلِ
 * تلگرام/واتساپ.
 */

return [

    'description' => "Add type column ('text'/'system') to chat_messages for group-event notices",

    'up' => function (PDO $db) {
        $db->exec("
            ALTER TABLE `chat_messages`
            ADD COLUMN `type` ENUM('text','system') NOT NULL DEFAULT 'text' AFTER `user_id`
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("ALTER TABLE `chat_messages` DROP COLUMN `type`");
    },

];
