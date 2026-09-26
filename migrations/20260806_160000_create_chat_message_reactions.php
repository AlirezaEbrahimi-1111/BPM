<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: ساخت جدول chat_message_reactions — ری‌اکشن ایموجی روی پیام
 *  تاریخ: ۱۴۰۵/۰۵/۱۵
 * ───────────────────────────────────────────────────────────────────
 *  هر کاربر حداکثر یک ری‌اکشن روی هر پیام دارد (مثل تلگرام غیرپرمیوم) —
 *  UNIQUE(message_id, user_id) → با ری‌اکشن دوباره، ایموجی قبلی جایگزین
 *  می‌شود؛ با کلیک دوباره‌ی همان ایموجی، ری‌اکشن برداشته می‌شود.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'ساخت جدول chat_message_reactions',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `chat_message_reactions` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `message_id` INT NOT NULL,
                `user_id`    INT NOT NULL,
                `emoji`      VARCHAR(8) NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_chat_reaction` (`message_id`, `user_id`),
                INDEX `idx_chat_reaction_message` (`message_id`),
                CONSTRAINT `fk_chat_reactions_message` FOREIGN KEY (`message_id`)
                    REFERENCES `chat_messages`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `chat_message_reactions`");
    },

];
