<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: ساخت جداول ai_conversations / ai_conversation_messages
 *  تاریخ: ۱۴۰۵/۰۵/۱۶
 * ───────────────────────────────────────────────────────────────────
 *  حافظه‌ی کوتاه‌مدت دستیار هوش‌مصنوعی (طبق بند ۱۰ سند
 *  docs/ai-assistant/spec-v1.md) — آخرین چند پیام همین conversation
 *  به‌عنوان Context برای سؤالات دنباله‌دار استفاده می‌شود؛ بعد از
 *  ۹۰ دقیقه بی‌فعالیتی، conversation «بسته» تلقی می‌شود.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'ساخت جداول ai_conversations و ai_conversation_messages',

    'up' => function (PDO $db) {

        $db->exec("
            CREATE TABLE IF NOT EXISTS `ai_conversations` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `user_id`         INT NOT NULL,
                `organization_id` INT NOT NULL,
                `started_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_active_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_ai_conv_user` (`user_id`, `last_active_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `ai_conversation_messages` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `conversation_id` INT NOT NULL,
                `role`            ENUM('user','assistant') NOT NULL,
                `content`         MEDIUMTEXT NOT NULL,
                `sources_json`    JSON NULL,
                `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_ai_conv_msg_conv` (`conversation_id`, `created_at`),
                CONSTRAINT `fk_ai_conv_msg_conv` FOREIGN KEY (`conversation_id`)
                    REFERENCES `ai_conversations`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");

    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `ai_conversation_messages`");
        $db->exec("DROP TABLE IF EXISTS `ai_conversations`");
    },

];
