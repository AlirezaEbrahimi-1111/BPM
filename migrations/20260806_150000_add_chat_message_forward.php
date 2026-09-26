<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودن forwarded_from_user_id به chat_messages — هدایت پیام
 *  تاریخ: ۱۴۰۵/۰۵/۱۵
 * ───────────────────────────────────────────────────────────────────
 *  فقط شناسه‌ی فرستنده‌ی اصلی نگه‌داشته می‌شود (نه شناسه‌ی پیام اصلی)،
 *  چون تنها چیزی که در UI لازم است برچسب هدایت شده از X» است —
 *  با این کار نیازی به دسترسی متقابل به گفتگوی مبدأ هم نیست.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودن ستون forwarded_from_user_id به chat_messages برای هدایت پیام',

    'up' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_messages` LIKE 'forwarded_from_user_id'");
        if (!$stmt->fetch()) {
            $db->exec("
                ALTER TABLE `chat_messages`
                ADD COLUMN `forwarded_from_user_id` INT NULL DEFAULT NULL AFTER `reply_to_message_id`
            ");
        }
    },

    'down' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_messages` LIKE 'forwarded_from_user_id'");
        if ($stmt->fetch()) {
            $db->exec("ALTER TABLE `chat_messages` DROP COLUMN `forwarded_from_user_id`");
        }
    },

];
