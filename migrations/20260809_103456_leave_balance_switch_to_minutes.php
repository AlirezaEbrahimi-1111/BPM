<?php
/**
 * اصلاح سیستم سهمیهٔ مرخصی طبق توضیحات دقیق‌تر کارفرما:
 *   - واحد از «روز» به «دقیقه» تغییر می‌کنه (برای محاسبهٔ دقیق با ساعت کاری
 *     روزانهٔ هر فرد که ممکنه بین پرسنل فرق کنه)
 *   - سهمیهٔ مرخصی و پاس مشترکه — یک استخر، نه دو تای جدا — پس یک تایپ
 *     تراکنشی جدید (pass_deduction) و یک ستون نوع ارجاع لازمه تا هم به
 *     leave_requests و هم به pass_requests اشاره کنه (بدون دو ستون FK جدا)
 */

return [

    'description' => 'Switch leave_balance_transactions from days to minutes; support pass requests sharing the same quota pool',

    'up' => function (PDO $db) {
        $db->exec("ALTER TABLE `leave_balance_transactions` DROP FOREIGN KEY `fk_lbt_leave_request`");

        $db->exec("ALTER TABLE `leave_balance_transactions`
            CHANGE COLUMN `related_leave_request_id` `related_request_id` INT NULL,
            ADD COLUMN `related_request_type` ENUM('leave','pass') NULL AFTER `related_request_id`,
            MODIFY COLUMN `amount` INT NOT NULL COMMENT 'دقیقه — مثبت=افزایش، منفی=کسر',
            MODIFY COLUMN `type` ENUM('monthly_accrual','bonus_grant','leave_deduction','pass_deduction','manual_adjustment','year_reset') NOT NULL
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("ALTER TABLE `leave_balance_transactions`
            DROP COLUMN `related_request_type`,
            CHANGE COLUMN `related_request_id` `related_leave_request_id` INT NULL,
            MODIFY COLUMN `amount` DECIMAL(5,2) NOT NULL,
            MODIFY COLUMN `type` ENUM('monthly_accrual','bonus_grant','leave_deduction','manual_adjustment','year_reset') NOT NULL
        ");
        $db->exec("ALTER TABLE `leave_balance_transactions`
            ADD CONSTRAINT `fk_lbt_leave_request` FOREIGN KEY (`related_leave_request_id`) REFERENCES `leave_requests`(`id`) ON DELETE SET NULL
        ");
    },

];
