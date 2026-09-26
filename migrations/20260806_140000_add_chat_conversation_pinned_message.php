<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودن pinned_message_id به chat_conversations — سنجاق‌کردن پیام
 *  تاریخ: ۱۴۰۵/۰۵/۱۵
 * ───────────────────────────────────────────────────────────────────
 *  هر گفتگو حداکثر یک پیام سنجاق‌شده دارد (نه هر پیام یک فلگ) —
 *  ساده‌تر برای کوئری و همیشه واضح که «پیام فعلا سنجاق‌شده» کدام است.
 *
 *  ON DELETE SET NULL: اگر پیام سنجاق‌شده بعدا «حذف برای همه» شود،
 *  سنجاق به‌طور خودکار برداشته می‌شود.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودن ستون pinned_message_id به chat_conversations برای سنجاق‌کردن پیام',

    'up' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_conversations` LIKE 'pinned_message_id'");
        if (!$stmt->fetch()) {
            $db->exec("
                ALTER TABLE `chat_conversations`
                ADD COLUMN `pinned_message_id` INT NULL DEFAULT NULL AFTER `title`,
                ADD CONSTRAINT `fk_chat_conv_pinned_message` FOREIGN KEY (`pinned_message_id`)
                    REFERENCES `chat_messages`(`id`) ON DELETE SET NULL
            ");
        }
    },

    'down' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_conversations` LIKE 'pinned_message_id'");
        if ($stmt->fetch()) {
            $db->exec("ALTER TABLE `chat_conversations` DROP FOREIGN KEY `fk_chat_conv_pinned_message`");
            $db->exec("ALTER TABLE `chat_conversations` DROP COLUMN `pinned_message_id`");
        }
    },

];
