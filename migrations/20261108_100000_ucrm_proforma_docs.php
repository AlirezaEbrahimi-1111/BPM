<?php
/**
 * CRM جدید — صدور پیش‌فاکتور فروش در CRM (مرحلهٔ ۱، ۱۴۰۵/۰۸/۱۷).
 *
 * پیش‌فاکتورِ صادرشده در CRM اول همین‌جا ذخیره می‌شود (قابل ویرایش)؛ در مرحلهٔ ۲
 * به شایگان (Invoice/Put نوع ۱۳) فرستاده و قفل می‌شود (shygun_guid پر می‌شود).
 * کالاها فقط از کالاهای شایگان (کد + GuId). مبلغ‌ها تومان. شماره = PFC-سال-ترتیب
 * (جدا از شمارهٔ شایگان). وضعیت مثل گزارش: not_started | approved | stopped؛
 * «عقب افتاده» محاسبه‌ای. منطق: crm-service/ucrm_proforma_docs.go
 *
 * ⚠️ روی سرور GRANT دستی لازم دارد (crm-service/deploy/crm_service_db_user.sql).
 */
return [

    'description' => 'New CRM: ucrm_proformas + ucrm_proforma_items (issue sale proformas in CRM)',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_proformas` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL DEFAULT 1,
                `jy`              INT NOT NULL COMMENT 'سال شمسی صدور (شماره‌گذاری)',
                `seq`             INT NOT NULL COMMENT 'ترتیب در همان سال',
                `customer_id`     INT NOT NULL COMMENT 'ucrm_customers.id',
                `rep_id`          INT NULL COMMENT 'کارشناس مشتری هنگام صدور',
                `issue_date`      DATE NOT NULL,
                `next_followup`   DATE NULL,
                `status`          VARCHAR(12) NOT NULL DEFAULT 'not_started' COMMENT 'not_started | approved | stopped',
                `status_by`       INT NULL,
                `status_at`       DATETIME NULL,
                `note`            TEXT NULL,
                `subtotal`        BIGINT NOT NULL DEFAULT 0 COMMENT 'تومان، پیش از تخفیف',
                `discount`        BIGINT NOT NULL DEFAULT 0 COMMENT 'تومان، جمع تخفیف ردیف‌ها',
                `total`           BIGINT NOT NULL DEFAULT 0 COMMENT 'تومان، قابل پرداخت',
                `send_status`     VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft | pending_accounting | sent | rejected — ارسال: کارشناس درخواست، حسابداری تأیید (مرحلهٔ ۲)',
                `send_requested_by` INT NULL,
                `send_requested_at` DATETIME NULL,
                `send_reject_reason` TEXT NULL,
                `shygun_guid`     CHAR(36) NULL COMMENT 'پس از ارسال به شایگان (مرحلهٔ ۲)',
                `sent_at`         DATETIME NULL,
                `sent_by`         INT NULL,
                `created_by`      INT NULL,
                `updated_by`      INT NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
                `deleted_by`      INT NULL,
                `deleted_at`      DATETIME NULL,
                UNIQUE KEY `uq_ucrm_pf_no` (`organization_id`, `jy`, `seq`),
                KEY `idx_ucrm_pf_customer` (`customer_id`, `is_deleted`),
                KEY `idx_ucrm_pf_date` (`organization_id`, `is_deleted`, `issue_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_proforma_items` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `proforma_id` INT NOT NULL,
                `sort`        INT NOT NULL DEFAULT 0,
                `item_code`   VARCHAR(50) NOT NULL COMMENT 'ItemCode شایگان',
                `item_guid`   CHAR(36) NULL COMMENT 'GuId کالا در شایگان',
                `item_name`   VARCHAR(255) NOT NULL,
                `unit`        VARCHAR(50) NULL,
                `qty`         DECIMAL(14,3) NOT NULL DEFAULT 1,
                `unit_price`  BIGINT NOT NULL DEFAULT 0 COMMENT 'تومان',
                `discount`    BIGINT NOT NULL DEFAULT 0 COMMENT 'تومان، کل ردیف',
                `line_total`  BIGINT NOT NULL DEFAULT 0 COMMENT 'تومان',
                KEY `idx_ucrm_pfi_pf` (`proforma_id`, `sort`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $db) {
        $n = (int) $db->query("SELECT COUNT(*) FROM `ucrm_proformas`")->fetchColumn();
        if ($n > 0) throw new RuntimeException("$n پیش‌فاکتور CRM ثبت شده؛ برگشت این مهاجرت آن‌ها را پاک می‌کند.");
        $db->exec("DROP TABLE IF EXISTS `ucrm_proforma_items`");
        $db->exec("DROP TABLE IF EXISTS `ucrm_proformas`");
    },

];
