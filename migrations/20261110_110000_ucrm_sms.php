<?php
/**
 * CRM جدید — پیامک به مشتری (بخش ۹ طرح CRM، ۱۴۰۵/۰۸/۱۹).
 *
 * قالب‌ها = الگوهای تأییدشدهٔ ملی‌پیامک (خط خدماتی): هر قالب کدِ الگو (body_id) دارد که اول در
 * پنل ملی‌پیامک ساخته و تأیید می‌شود؛ preview متنِ الگو با {0} {1}… و var_labels برچسبِ هر متغیر.
 * مدیریت قالب: فقط مدیر. ارسال: کارشناسِ همان مشتری یا مدیر.
 * ارسال واقعی در PHP (api/ucrm/sms-deliver.php ← includes/sms.php) تا اطلاعات حساب پیامک فقط
 * همان‌جا بماند؛ crm-service/ucrm_sms.go ردیفِ ucrm_sms_log را می‌سازد و همان را صدا می‌زند.
 * (جدول‌های قدیمی crm_sms_* مالِ ماژول رسمی‌اند و استفاده نمی‌شوند.)
 *
 * ⚠️ روی سرور GRANT دستی لازم دارد (crm-service/deploy/crm_service_db_user.sql).
 */
return [

    'description' => 'New CRM: ucrm_sms_templates + ucrm_sms_log (pattern SMS to customers)',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_sms_templates` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL DEFAULT 1,
                `title`           VARCHAR(150) NOT NULL,
                `body_id`         INT NOT NULL COMMENT 'کد الگوی تأییدشده در ملی‌پیامک',
                `preview`         TEXT NOT NULL COMMENT 'متن الگو با {0} {1} … برای پیش‌نمایش',
                `var_labels`      TEXT NULL COMMENT 'JSON: برچسب هر متغیر به ترتیب',
                `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
                `created_by`      INT NULL,
                `updated_by`      INT NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
                KEY `idx_ucrm_smstpl_org` (`organization_id`, `is_deleted`, `is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_sms_log` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `customer_id` INT NOT NULL,
                `template_id` INT NULL,
                `phone`       VARCHAR(20) NOT NULL,
                `args`        TEXT NULL COMMENT 'JSON: مقدار متغیرها',
                `text`        TEXT NULL COMMENT 'متن نهایی (پیش‌نمایشِ پرشده) برای تاریخچه',
                `status`      VARCHAR(10) NOT NULL DEFAULT 'queued' COMMENT 'queued | sent | failed',
                `error`       VARCHAR(255) NULL,
                `sent_by`     INT NULL,
                `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `sent_at`     DATETIME NULL,
                KEY `idx_ucrm_sms_customer` (`customer_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $db) {
        $n = (int) $db->query("SELECT COUNT(*) FROM `ucrm_sms_log`")->fetchColumn();
        if ($n > 0) throw new RuntimeException("$n پیامک ثبت شده؛ برگشت این مهاجرت آن‌ها را پاک می‌کند.");
        $db->exec("DROP TABLE IF EXISTS `ucrm_sms_log`");
        $db->exec("DROP TABLE IF EXISTS `ucrm_sms_templates`");
    },

];
