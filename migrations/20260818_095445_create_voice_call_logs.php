<?php
/**
 * هشدار تماس صوتی برای تیکت‌های «بحرانی» (زرین‌کال) — includes/VoiceCall.php.
 * لاگ این جدول دقیقا موازی sms_logs است، فقط برای تماس به‌جای پیامک.
 */

return [

    'description' => 'Create voice_call_logs table for ZarinCall critical-ticket alert calls',

    'up' => function (PDO $db) {
        $db->exec("CREATE TABLE `voice_call_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `numbers` VARCHAR(255) NOT NULL,
            `voice_id` VARCHAR(64) NOT NULL,
            `context` VARCHAR(64) NULL COMMENT 'مثلا ticket:123',
            `status` ENUM('sent','failed') NOT NULL,
            `api_response` TEXT NULL,
            `error_message` VARCHAR(255) NULL,
            `sent_at` DATETIME NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_context` (`context`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `voice_call_logs`");
    },

];
