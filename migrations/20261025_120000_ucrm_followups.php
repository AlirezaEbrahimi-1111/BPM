<?php
/**
 * CRM جدید — پیگیری‌های هر مشتری (پنجرهٔ «ثبت پیگیری جدید» + تب «برنامهٔ پیگیری»، طرح ۱۴۰۵/۰۸/۰۲).
 *
 * هر پیگیری یک ردیف: عنوان، نوع، تاریخ و زمان، یادداشت، وضعیت (در انتظار / انجام شده / لغو شده).
 * «عقب‌افتاده» ستون نیست: پیگیری «در انتظار» که زمانش گذشته (هنگام خواندن حساب می‌شود).
 * کارشناس همان مشتری یا مدیر ثبت/ویرایش/حذف می‌کند؛ تأیید حسابداری ندارد.
 * منطق: crm-service/ucrm_followups.go
 *
 * ⚠️ روی سرور GRANT دستی لازم دارد (crm-service/deploy/crm_service_db_user.sql).
 */
return [

    'description' => 'New CRM: ucrm_followups (per-customer follow-ups with date/time, type, status)',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_followups` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `customer_id` INT NOT NULL,
                `title`       VARCHAR(255) NOT NULL,
                `type`        VARCHAR(20) NOT NULL COMMENT 'call | sms | meeting | email | proforma | other',
                `due_at`      DATETIME NOT NULL COMMENT 'تاریخ و زمان پیگیری (وقت تهران)',
                `note`        TEXT NULL,
                `status`      VARCHAR(12) NOT NULL DEFAULT 'pending' COMMENT 'pending | done | cancelled',
                `done_at`     DATETIME NULL,
                `done_by`     INT NULL,
                `created_by`  INT NULL,
                `updated_by`  INT NULL,
                `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `is_deleted`  TINYINT(1) NOT NULL DEFAULT 0,
                `deleted_by`  INT NULL,
                `deleted_at`  DATETIME NULL,
                KEY `idx_ucrm_fu_customer` (`customer_id`, `is_deleted`, `due_at`),
                KEY `idx_ucrm_fu_due` (`status`, `due_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $db) {
        $n = (int) $db->query("SELECT COUNT(*) FROM `ucrm_followups`")->fetchColumn();
        if ($n > 0) throw new RuntimeException("$n پیگیری ثبت شده؛ برگشت این مهاجرت آن‌ها را پاک می‌کند.");
        $db->exec("DROP TABLE IF EXISTS `ucrm_followups`");
    },

];
