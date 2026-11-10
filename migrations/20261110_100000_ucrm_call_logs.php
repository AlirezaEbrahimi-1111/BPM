<?php
/**
 * CRM جدید — گزارش تماس‌های مشتری (تب «تاریخچهٔ پیگیری ← تماس‌ها»، ۱۴۰۵/۰۸/۱۹).
 *
 * هر گزارش: نتیجهٔ تماس، متن گزارش، پیوست (اختیاری)، و اگر از «انجام شد»ِ یک پیگیریِ تماس
 * ثبت شده باشد، شناسهٔ همان پیگیری؛ «پیگیری بعدی» یک ردیفِ تازه در ucrm_followups است
 * (next_followup_id). نمایش مثل تاریخچهٔ تسک. منطق: crm-service/ucrm_calls.go
 * فایل‌ها در /opt/bmp-crm/uploads/ucrm-calls (بیرون از وب) و فقط از راه سرویس دانلود می‌شوند.
 *
 * ⚠️ روی سرور GRANT دستی لازم دارد (crm-service/deploy/crm_service_db_user.sql).
 */
return [

    'description' => 'New CRM: ucrm_call_logs + ucrm_call_files (call reports with attachments)',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_call_logs` (
                `id`               INT AUTO_INCREMENT PRIMARY KEY,
                `customer_id`      INT NOT NULL,
                `followup_id`      INT NULL COMMENT 'پیگیریِ تماسی که با این گزارش انجام شد',
                `result`           VARCHAR(20) NOT NULL COMMENT 'answered | no_answer | busy | wrong_number',
                `note`             TEXT NULL,
                `next_followup_id` INT NULL COMMENT 'پیگیریِ بعدیِ ساخته‌شده از همین گزارش',
                `created_by`       INT NULL,
                `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `is_deleted`       TINYINT(1) NOT NULL DEFAULT 0,
                `deleted_by`       INT NULL,
                `deleted_at`       DATETIME NULL,
                KEY `idx_ucrm_call_customer` (`customer_id`, `is_deleted`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_call_files` (
                `id`            INT AUTO_INCREMENT PRIMARY KEY,
                `call_id`       INT NOT NULL,
                `stored_name`   VARCHAR(80) NOT NULL COMMENT 'نامِ تصادفی روی دیسک',
                `original_name` VARCHAR(255) NOT NULL,
                `mime`          VARCHAR(100) NULL,
                `size`          INT NOT NULL DEFAULT 0,
                `uploaded_by`   INT NULL,
                `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_ucrm_callfile_call` (`call_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $db) {
        $n = (int) $db->query("SELECT COUNT(*) FROM `ucrm_call_logs`")->fetchColumn();
        if ($n > 0) throw new RuntimeException("$n گزارش تماس ثبت شده؛ برگشت این مهاجرت آن‌ها را پاک می‌کند.");
        $db->exec("DROP TABLE IF EXISTS `ucrm_call_files`");
        $db->exec("DROP TABLE IF EXISTS `ucrm_call_logs`");
    },

];
