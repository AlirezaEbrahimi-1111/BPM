<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: ساخت جداول پایه‌ی چتِ داخلیِ سازمان (فاز اول: یک‌به‌یک)
 *  تاریخ: ۱۴۰۵/۰۵/۱۴
 * ───────────────────────────────────────────────────────────────────
 *  مدل «مکالمه + شرکت‌کننده» به‌جای «فرستنده/گیرنده روی هر پیام»،
 *  تا در فازهای بعدی (گروه/کانال) نیازی به تغییرِ اسکیما نباشد —
 *  فقط type در chat_conversations مقدارِ دیگری می‌گیرد و تعدادِ
 *  ردیف‌های chat_participants برای همان conversation بیشتر می‌شود.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'ساخت جداول chat_conversations / chat_participants / chat_messages / chat_attachments',

    'up' => function (PDO $db) {

        $db->exec("
            CREATE TABLE IF NOT EXISTS `chat_conversations` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `type`            ENUM('direct','group','channel') NOT NULL DEFAULT 'direct',
                `title`           VARCHAR(255) NULL COMMENT 'فقط برای group/channel در آینده',
                `created_by`      INT NOT NULL,
                `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    COMMENT 'برای مرتب‌سازیِ لیستِ مکالمه‌ها بر اساسِ آخرین فعالیت',
                INDEX `idx_chat_conv_org` (`organization_id`, `type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `chat_participants` (
                `id`                    INT AUTO_INCREMENT PRIMARY KEY,
                `conversation_id`       INT NOT NULL,
                `user_id`               INT NOT NULL,
                `joined_at`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_read_message_id`  INT NULL,
                `is_archived`           TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT 'بایگانیِ شخصی — فقط برای همین کاربر، بدون اثر روی طرفِ مقابل',
                `archived_at`           TIMESTAMP NULL,
                UNIQUE KEY `uq_chat_participant` (`conversation_id`, `user_id`),
                INDEX `idx_chat_participant_user` (`user_id`),
                CONSTRAINT `fk_chat_participants_conv` FOREIGN KEY (`conversation_id`)
                    REFERENCES `chat_conversations`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `chat_messages` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `conversation_id` INT NOT NULL,
                `user_id`         INT NOT NULL COMMENT 'فرستنده',
                `message`         TEXT NULL,
                `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
                `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_chat_messages_conv` (`conversation_id`, `created_at`),
                CONSTRAINT `fk_chat_messages_conv` FOREIGN KEY (`conversation_id`)
                    REFERENCES `chat_conversations`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `chat_attachments` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `message_id`      INT NOT NULL,
                `conversation_id` INT NOT NULL COMMENT 'دنرمالایز شده تا چکِ دسترسی بدونِ JOIN به پیام انجام شود',
                `user_id`         INT NOT NULL,
                `original_name`   VARCHAR(255) NOT NULL,
                `stored_name`     VARCHAR(255) NOT NULL,
                `mime_type`       VARCHAR(100) NOT NULL,
                `file_size`       INT NOT NULL,
                `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_chat_att_message` (`message_id`),
                INDEX `idx_chat_att_conv` (`conversation_id`),
                CONSTRAINT `fk_chat_att_message` FOREIGN KEY (`message_id`)
                    REFERENCES `chat_messages`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");

    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `chat_attachments`");
        $db->exec("DROP TABLE IF EXISTS `chat_messages`");
        $db->exec("DROP TABLE IF EXISTS `chat_participants`");
        $db->exec("DROP TABLE IF EXISTS `chat_conversations`");
    },

];
