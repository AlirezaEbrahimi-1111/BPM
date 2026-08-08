<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودنِ reply_to_message_id به chat_messages — پاسخ به پیام خاص
 *  تاریخ: ۱۴۰۵/۰۵/۱۵
 * ───────────────────────────────────────────────────────────────────
 *  NULL یعنی پیامِ عادی؛ مقدار داشتن یعنی این پیام پاسخِ یک پیامِ
 *  دیگر است — در حباب، خلاصه‌ی پیامِ اصلی بالای متنِ پاسخ نمایش
 *  داده می‌شود (دقیقاً مثلِ تلگرام/واتساپ).
 *
 *  ON DELETE SET NULL: اگر پیامِ اصلی (که به آن پاسخ داده شده) بعداً
 *  «حذف برای همه» شود، پاسخ باقی می‌ماند ولی دیگر ارجاعی ندارد —
 *  به‌جای شکستنِ رفرنس یا حذفِ زنجیره‌ای پاسخ‌ها.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودن ستون reply_to_message_id به chat_messages برای پشتیبانی از پاسخ به پیام',

    'up' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_messages` LIKE 'reply_to_message_id'");
        if (!$stmt->fetch()) {
            $db->exec("
                ALTER TABLE `chat_messages`
                ADD COLUMN `reply_to_message_id` INT NULL DEFAULT NULL AFTER `conversation_id`,
                ADD CONSTRAINT `fk_chat_messages_reply_to` FOREIGN KEY (`reply_to_message_id`)
                    REFERENCES `chat_messages`(`id`) ON DELETE SET NULL
            ");
        }
    },

    'down' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_messages` LIKE 'reply_to_message_id'");
        if ($stmt->fetch()) {
            $db->exec("ALTER TABLE `chat_messages` DROP FOREIGN KEY `fk_chat_messages_reply_to`");
            $db->exec("ALTER TABLE `chat_messages` DROP COLUMN `reply_to_message_id`");
        }
    },

];
