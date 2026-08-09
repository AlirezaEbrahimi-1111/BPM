<?php
/**
 * دفترکلِ تراکنشیِ سهمیهٔ مرخصیِ استحقاقی:
 *   - هر ردیف یک رویداد (تعلقِ ماهانه، اعطایِ تشویقیِ مدیر، کسر بابتِ مرخصی، اصلاحِ دستی)
 *   - موجودیِ فعلیِ هر کاربر = مجموعِ amount تمامِ ردیف‌هاش (نه یک عددِ خامِ قابلِ‌بازنویسی)
 *   - این‌طوری هم انتقالِ خودکارِ سهمیهٔ استفاده‌نشده به ماهِ بعد جواب می‌ده (چیزی reset نمی‌شه)،
 *     هم تاریخچه برایِ مدیر/کارمند قابلِ‌نمایشه
 */

return [

    'description' => 'Create leave_balance_transactions ledger table for the monthly leave-quota system',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `leave_balance_transactions` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `user_id` INT NOT NULL,
                `type` ENUM('monthly_accrual', 'bonus_grant', 'leave_deduction', 'manual_adjustment', 'year_reset') NOT NULL,
                `amount` DECIMAL(5,2) NOT NULL COMMENT 'مثبت = افزایشِ موجودی، منفی = کسر',
                `related_leave_request_id` INT NULL,
                `granted_by` INT NULL COMMENT 'برایِ bonus_grant/manual_adjustment: چه کسی ثبت کرد',
                `note` VARCHAR(255) NULL,
                `period_ym` VARCHAR(7) NULL COMMENT 'برایِ monthly_accrual: ماهِ مربوطه به‌شکلِ YYYY-MM، برایِ جلوگیری از تعلقِ تکراری',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_user` (`user_id`),
                INDEX `idx_user_period` (`user_id`, `period_ym`),
                CONSTRAINT `fk_lbt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_lbt_leave_request` FOREIGN KEY (`related_leave_request_id`) REFERENCES `leave_requests`(`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_lbt_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `leave_balance_transactions`");
    },

];
