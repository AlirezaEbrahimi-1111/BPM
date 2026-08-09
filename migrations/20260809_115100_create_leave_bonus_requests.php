<?php
/**
 * ردیابیِ درخواستِ سهمیهٔ تشویقیِ مرخصی/پاس — قبلاً فقط یک نوتیفیکیشنِ
 * زودگذر بود که با سین‌شدن/فراموش‌شدنش، هیچ اثری ازش نمی‌موند و کارمند
 * برایِ همیشه در انتظار می‌ماند. حالا هر درخواست یک ردیفِ ماندگار داره
 * (pending/granted/declined) که در فهرستِ سرپرست تا حل‌نشدن باقی می‌مونه.
 */

return [

    'description' => 'Create leave_bonus_requests table to persistently track leave bonus requests until resolved',

    'up' => function (PDO $db) {
        $db->exec("CREATE TABLE `leave_bonus_requests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `organization_id` INT NOT NULL,
            `note` VARCHAR(500) NULL,
            `balance_at_request` INT NOT NULL COMMENT 'دقیقه — موجودی در لحظهٔ ثبتِ درخواست',
            `status` ENUM('pending','granted','declined') NOT NULL DEFAULT 'pending',
            `resolved_by` INT NULL,
            `resolved_amount` INT NULL COMMENT 'دقیقه — فقط برایِ status=granted',
            `resolve_note` VARCHAR(500) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `resolved_at` TIMESTAMP NULL,
            CONSTRAINT `fk_lbr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_lbr_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_lbr_org_status` (`organization_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `leave_bonus_requests`");
    },

];
