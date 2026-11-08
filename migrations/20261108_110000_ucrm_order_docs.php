<?php
/**
 * CRM جدید — سفارش فروش و سفارش خرید (۱۴۰۵/۰۸/۱۷)، در همان جدولِ پیش‌فاکتورهای CRM.
 *
 * ucrm_proformas.doc_type: proforma (پیش‌فاکتور فروش، شایگان ۱۳) | sales_order (سفارش فروش، ۱۱)
 *                         | purchase_order (سفارش خرید، ۱۲)
 * سفارش خرید طرفش تأمین‌کننده است (تفصیلیِ شایگان، نه مشتری CRM) → customer_id اختیاری و
 * party_job_no / party_guid / party_name. شماره‌گذاری جدا برای هر نوع (PFC / SOC / POC).
 * expected_date = تاریخ تحویل مورد انتظار (سفارش‌ها). source_doc_id = پیش‌فاکتورِ مبدأ
 * (تبدیل پیش‌فاکتور → سفارش فروش). وضعیت سفارش در شایگان از ControlCheck + OrderRemain1 حساب
 * می‌شود (crm-service/ucrm_orders.go).
 */
return [

    'description' => 'New CRM: sales/purchase orders in ucrm_proformas (doc_type, supplier party, expected date)',

    'up' => function (PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM `ucrm_proformas`")->fetchAll(PDO::FETCH_COLUMN);
        $add = [
            'doc_type'      => "ADD COLUMN `doc_type` VARCHAR(20) NOT NULL DEFAULT 'proforma' COMMENT 'proforma | sales_order | purchase_order' AFTER `organization_id`",
            'party_job_no'  => "ADD COLUMN `party_job_no` VARCHAR(50) NULL COMMENT 'تأمین‌کننده (سفارش خرید): JobNo شایگان' AFTER `customer_id`",
            'party_guid'    => "ADD COLUMN `party_guid` CHAR(36) NULL COMMENT 'GuId تفصیلی تأمین‌کننده' AFTER `party_job_no`",
            'party_name'    => "ADD COLUMN `party_name` VARCHAR(255) NULL COMMENT 'نام تأمین‌کننده' AFTER `party_guid`",
            'expected_date' => "ADD COLUMN `expected_date` DATE NULL COMMENT 'تاریخ تحویل مورد انتظار (سفارش)' AFTER `next_followup`",
            'source_doc_id' => "ADD COLUMN `source_doc_id` INT NULL COMMENT 'پیش‌فاکتورِ مبدأ (تبدیل به سفارش فروش)' AFTER `expected_date`",
        ];
        foreach ($add as $c => $sql) {
            if (!in_array($c, $cols, true)) $db->exec("ALTER TABLE `ucrm_proformas` $sql");
        }
        $db->exec("ALTER TABLE `ucrm_proformas` MODIFY `customer_id` INT NULL COMMENT 'ucrm_customers.id (سفارش خرید: NULL)'");
        // شماره‌گذاری جدا برای هر نوع سند
        $idx = $db->query("SHOW INDEX FROM `ucrm_proformas` WHERE Key_name = 'uq_ucrm_pf_no'")->fetchAll();
        if ($idx) $db->exec("ALTER TABLE `ucrm_proformas` DROP INDEX `uq_ucrm_pf_no`");
        $db->exec("ALTER TABLE `ucrm_proformas` ADD UNIQUE KEY `uq_ucrm_pf_no` (`organization_id`, `doc_type`, `jy`, `seq`)");
        $db->exec("ALTER TABLE `ucrm_proformas` ADD KEY `idx_ucrm_pf_type` (`organization_id`, `doc_type`, `is_deleted`)");
    },

    'down' => function (PDO $db) {
        $n = (int) $db->query("SELECT COUNT(*) FROM `ucrm_proformas` WHERE doc_type <> 'proforma'")->fetchColumn();
        if ($n > 0) throw new RuntimeException("$n سفارش CRM ثبت شده؛ برگشت این مهاجرت آن‌ها را پاک می‌کند.");
        $db->exec("ALTER TABLE `ucrm_proformas` DROP INDEX `idx_ucrm_pf_type`");
        $db->exec("ALTER TABLE `ucrm_proformas` DROP INDEX `uq_ucrm_pf_no`");
        $db->exec("ALTER TABLE `ucrm_proformas` ADD UNIQUE KEY `uq_ucrm_pf_no` (`organization_id`, `jy`, `seq`)");
        foreach (['source_doc_id', 'expected_date', 'party_name', 'party_guid', 'party_job_no', 'doc_type'] as $c) {
            $db->exec("ALTER TABLE `ucrm_proformas` DROP COLUMN `$c`");
        }
        $db->exec("ALTER TABLE `ucrm_proformas` MODIFY `customer_id` INT NOT NULL");
    },

];
