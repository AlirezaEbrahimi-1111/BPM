<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: ساخت جدول chat_message_hidden — «حذف پیام فقط برای من»
 *  تاریخ: ۱۴۰۵/۰۵/۱۵
 * ───────────────────────────────────────────────────────────────────
 *  دو حالت حذف در چت:
 *    ۱) حذف برای همه   → ستون موجود chat_messages.is_deleted=1
 *       (فقط فرستنده مجاز است، پیام واقعا برای هر دو طرف پاک می‌شود)
 *    ۲) حذف فقط برای من → یک ردیف اینجا اضافه می‌شود؛ پیام برای طرف
 *       مقابل دست‌نخورده باقی می‌ماند، فقط از دید همین کاربر پنهان می‌شود
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'ساخت جدول chat_message_hidden برای حذف پیام فقط از دید یک کاربر',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `chat_message_hidden` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `message_id` INT NOT NULL,
                `user_id`    INT NOT NULL,
                `hidden_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_chat_message_hidden` (`message_id`, `user_id`),
                CONSTRAINT `fk_chat_message_hidden_msg` FOREIGN KEY (`message_id`)
                    REFERENCES `chat_messages`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `chat_message_hidden`");
    },

];
