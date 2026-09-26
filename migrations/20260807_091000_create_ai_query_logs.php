<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: ساخت جدول ai_query_logs
 *  تاریخ: ۱۴۰۵/۰۵/۱۶
 * ───────────────────────────────────────────────────────────────────
 *  طبق بند ۱۶ سند docs/ai-assistant/spec-v1.md — لاگ کامل هر
 *  پرسش: شناسه‌ی کاربر، متن سؤال، منابع استفاده‌شده، پاسخ، مدت‌زمان،
 *  و وضعیت (شامل حالت «نیازمند روشن‌سازی» طبق بند ۸).
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'ساخت جدول ai_query_logs',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ai_query_logs` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `user_id`         INT NOT NULL,
                `organization_id` INT NOT NULL,
                `conversation_id` INT NULL,
                `question`        TEXT NOT NULL,
                `sources_used`    JSON NULL,
                `answer`          MEDIUMTEXT NULL,
                `status`          ENUM('success','no_info','clarification_needed','error') NOT NULL,
                `latency_ms`      INT NOT NULL,
                `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_ai_logs_user` (`user_id`, `created_at`),
                INDEX `idx_ai_logs_org` (`organization_id`, `created_at`),
                CONSTRAINT `fk_ai_logs_conv` FOREIGN KEY (`conversation_id`)
                    REFERENCES `ai_conversations`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `ai_query_logs`");
    },

];
