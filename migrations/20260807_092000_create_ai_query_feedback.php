<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: ساختِ جدولِ ai_query_feedback
 *  تاریخ: ۱۴۰۵/۰۵/۱۶
 * ───────────────────────────────────────────────────────────────────
 *  طبقِ بندِ ۱۷ سندِ docs/ai-assistant/spec-v1.md — ثبتِ 👍/👎 برایِ هر
 *  پاسخ، برایِ بهبودِ آینده‌ی دستیار.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'ساخت جدول ai_query_feedback',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ai_query_feedback` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `log_id`     INT NOT NULL,
                `user_id`    INT NOT NULL,
                `rating`     ENUM('up','down') NOT NULL,
                `comment`    VARCHAR(500) NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT `fk_ai_feedback_log` FOREIGN KEY (`log_id`)
                    REFERENCES `ai_query_logs`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `ai_query_feedback`");
    },

];
