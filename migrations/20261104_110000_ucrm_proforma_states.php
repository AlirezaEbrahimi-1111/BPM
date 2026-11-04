<?php
/**
 * CRM جدید — وضعیت پیگیریِ پیش‌فاکتورهای فروشِ شایگان (صفحهٔ «پیش‌فاکتورهای باز»، طرح ۱۴۰۵/۰۸/۱۳).
 *
 * خودِ پیش‌فاکتور (شماره، تاریخ، مشتری، مبلغ) مالِ شایگان است و فقط خوانده می‌شود
 * (InvoiceAbsList نوع ۱۳ — crm-service/ucrm_proformas.go). این جدول فقط چیزهایی
 * را نگه می‌دارد که مالِ CRM است: وضعیت و تاریخِ پیگیریِ بعدی. کلید = InvoiceGuid شایگان.
 * پیش‌فاکتوری که ردیف ندارد = «شروع نشده» بدونِ تاریخ پیگیری.
 * «عقب افتاده» ستون نیست: «شروع نشده» با تاریخ پیگیری گذشته (هنگام خواندن حساب می‌شود).
 *
 * ⚠️ روی سرور GRANT دستی لازم دارد (crm-service/deploy/crm_service_db_user.sql).
 */
return [

    'description' => 'New CRM: ucrm_proforma_states (status + next follow-up per Shygun proforma)',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_proforma_states` (
                `invoice_guid`    CHAR(36) NOT NULL PRIMARY KEY COMMENT 'InvoiceGuid پیش‌فاکتور در شایگان',
                `organization_id` INT NOT NULL DEFAULT 1,
                `status`          VARCHAR(12) NOT NULL DEFAULT 'not_started' COMMENT 'not_started | approved | stopped',
                `next_followup`   DATE NULL COMMENT 'تاریخ پیگیری بعدی',
                `status_by`       INT NULL,
                `status_at`       DATETIME NULL,
                `updated_by`      INT NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_ucrm_pf_status` (`organization_id`, `status`, `next_followup`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $db) {
        $n = (int) $db->query("SELECT COUNT(*) FROM `ucrm_proforma_states`")->fetchColumn();
        if ($n > 0) throw new RuntimeException("$n وضعیت پیش‌فاکتور ثبت شده؛ برگشت این مهاجرت آن‌ها را پاک می‌کند.");
        $db->exec("DROP TABLE IF EXISTS `ucrm_proforma_states`");
    },

];
