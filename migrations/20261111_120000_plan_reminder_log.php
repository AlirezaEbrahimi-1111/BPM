<?php
/**
 * گزارش یادآوری‌های پایان پلن/دورهٔ رایگان (cron/plan_reminders.php)
 *
 * یک ردیف = «برای سازمان X با تاریخ پایان Y، یادآوری N روز مانده فرستاده شد».
 * کلید یکتا جلوی ارسال تکراری را می‌گیرد (حتی اگر cron چند بار در روز اجرا شود).
 * با تمدید پلن، تاریخ پایان عوض می‌شود و یادآوری‌های دورهٔ بعد دوباره فرستاده می‌شوند.
 * days_before: ۷، ۳، ۱ = روز مانده؛ ۰ = روز قفل شدن.
 */

return [

    'description' => 'Log of plan/trial expiry reminders (7/3/1 days before + lock day) to avoid duplicates',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `plan_reminder_log` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `end_date`        DATE NOT NULL,
                `days_before`     TINYINT NOT NULL,
                `recipients`      INT NOT NULL DEFAULT 0,
                `sent_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_prl_org_end_days` (`organization_id`, `end_date`, `days_before`),
                CONSTRAINT `fk_prl_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `plan_reminder_log`");
    },

];
