<?php
/**
 * CRM جدید — «درخواست تغییر اطلاعات مشتری» (طرح ۱۴۰۵/۰۸/۰۲).
 *
 * ویرایش اطلاعات مشتری فعال (از کادر «اطلاعات پایه» صفحهٔ جزئیات مشتری) فوری اعمال
 * نمی‌شود: یک درخواست می‌سازد که حسابداری (بدون مرحلهٔ مدیر) تأیید یا رد می‌کند؛ با
 * تأیید، تغییرها روی مشتری اعمال می‌شوند. منطق: crm-service/ucrm_changes.go
 *
 *  changes : JSON — [{"field":"name","label":"نام","old":"…","new":"…"}, …]
 *  status  : pending | approved | rejected | cancelled   (برای هر مشتری حداکثر یک pending)
 *
 * ⚠️ روی سرور GRANT دستی لازم دارد (crm-service/deploy/crm_service_db_user.sql).
 */
return [

    'description' => 'New CRM: ucrm_change_requests (customer info edits approved by accounting)',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_change_requests` (
                `id`            INT AUTO_INCREMENT PRIMARY KEY,
                `customer_id`   INT NOT NULL,
                `requested_by`  INT NOT NULL,
                `changes`       TEXT NOT NULL COMMENT 'JSON: [{field,label,old,new}]',
                `status`        VARCHAR(12) NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | rejected | cancelled',
                `reject_reason` TEXT NULL,
                `decided_by`    INT NULL,
                `decided_at`    DATETIME NULL,
                `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_ucrm_chg_customer` (`customer_id`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `ucrm_change_requests`");
    },

];
