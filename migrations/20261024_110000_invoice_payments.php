<?php
/**
 * حسابداری رسمی — واریزهای هر فاکتور (تسویهٔ یک‌جا یا چندمرحله‌ای) — درخواست ۱۴۰۵/۰۸/۰۲.
 *
 * قبلا فقط وضعیت تسویه (unsettled/partial/settled) دستی انتخاب می‌شد و مبلغی ثبت نمی‌شد.
 * حالا هر واریز یک ردیف این جدول است و inv_invoices.settlement_status از جمع
 * واریزها خودکار حساب می‌شود (crm-service/invoice_payments.go).
 *
 * حذف واریز نرم است (is_deleted/deleted_by/deleted_at) تا سابقهٔ ثبت و حذف بماند.
 *
 * ⚠️ بعد از اجرا روی سرور، crm_service به این جدول GRANT لازم دارد
 *    (crm-service/deploy/crm_service_db_user.sql).
 */
return [

    'description' => 'inv_invoice_payments: per-invoice payments (partial/multi-step settlement)',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_invoice_payments` (
                `id`              INT NOT NULL AUTO_INCREMENT,
                `organization_id` INT NOT NULL,
                `invoice_id`      INT NOT NULL,
                `amount`          DECIMAL(18,0) NOT NULL COMMENT 'ریال',
                `pay_date`        DATE NOT NULL,
                `method`          VARCHAR(20) NULL COMMENT 'cash | card | cheque | transfer',
                `note`            VARCHAR(500) NULL,
                `created_by`      INT NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
                `deleted_by`      INT NULL,
                `deleted_at`      DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `idx_inv_pay_invoice` (`invoice_id`, `is_deleted`),
                KEY `idx_inv_pay_org` (`organization_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $db) {
        $n = (int) $db->query("SELECT COUNT(*) FROM `inv_invoice_payments`")->fetchColumn();
        if ($n > 0) throw new RuntimeException("$n واریز ثبت شده؛ برگشت این مهاجرت آن‌ها را پاک می‌کند.");
        $db->exec("DROP TABLE IF EXISTS `inv_invoice_payments`");
    },

];
