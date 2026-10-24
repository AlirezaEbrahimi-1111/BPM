<?php
/**
 * حسابداری رسمی — «تبدیل پیش‌فاکتور به فاکتور رسمی» درجا (درخواست ۱۴۰۵/۰۸/۰۲).
 *
 * قبلا: تبدیل یک فاکتور رسمیِ «جدید» (پیش‌نویس، شمارهٔ تازه) می‌ساخت و پیش‌فاکتور با برچسب
 * «تبدیل‌شده» در فهرست می‌ماند. حالا: همان ردیف، با همان شماره، نوعش «فاکتور رسمی» و
 * وضعیتش «تأییدشده» می‌شود (crm-service/invoices.go::convertToOfficial).
 *
 * این مهاجرت:
 *   - حذف نرم برای فاکتورها: is_deleted / deleted_at / deleted_by
 *   - سابقهٔ تبدیل: converted_at / converted_by / proforma_issue_date (تاریخ اصلی پیش‌فاکتور)
 *   - پیش‌فاکتورهایی که قبلا به روش قدیم تبدیل شده‌اند (converted_to_id دارند) حذف نرم می‌شوند؛
 *     فاکتور رسمی‌ای که از آن‌ها ساخته شده دست نمی‌خورد.
 */
return [

    'description' => 'inv_invoices: soft delete + in-place proforma conversion history; soft-delete old converted proformas',

    'up' => function (PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM `inv_invoices`")->fetchAll(PDO::FETCH_COLUMN);
        $add = [
            'is_deleted'          => "TINYINT(1) NOT NULL DEFAULT 0",
            'deleted_at'          => "DATETIME NULL",
            'deleted_by'          => "INT NULL",
            'converted_at'        => "DATETIME NULL COMMENT 'زمان تبدیل درجای پیش‌فاکتور به فاکتور رسمی'",
            'converted_by'        => "INT NULL",
            'proforma_issue_date' => "DATE NULL COMMENT 'تاریخ صدور اصلی پیش‌فاکتور قبل از تبدیل'",
        ];
        foreach ($add as $c => $def) {
            if (!in_array($c, $cols, true)) $db->exec("ALTER TABLE `inv_invoices` ADD COLUMN `$c` $def");
        }
        $idx = $db->query("SHOW INDEX FROM `inv_invoices` WHERE Key_name = 'idx_inv_inv_deleted'")->fetch();
        if (!$idx) $db->exec("ALTER TABLE `inv_invoices` ADD KEY `idx_inv_inv_deleted` (`organization_id`, `is_deleted`)");

        // پیش‌فاکتورهای تبدیل‌شده به روش قدیم → حذف نرم
        $db->exec("UPDATE `inv_invoices` SET is_deleted = 1, deleted_at = NOW()
                   WHERE doc_type = 'proforma' AND converted_to_id IS NOT NULL AND is_deleted = 0");
    },

    'down' => function (PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM `inv_invoices`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('is_deleted', $cols, true)) {
            $n = (int) $db->query("SELECT COUNT(*) FROM inv_invoices WHERE is_deleted = 1 AND converted_to_id IS NULL")->fetchColumn();
            if ($n > 0) throw new RuntimeException("$n فاکتور حذف‌نرم‌شده (غیر از پیش‌فاکتورهای تبدیل‌شده) هست؛ برگشت آن‌ها را دوباره نمایان می‌کند.");
        }
        $idx = $db->query("SHOW INDEX FROM `inv_invoices` WHERE Key_name = 'idx_inv_inv_deleted'")->fetch();
        if ($idx) $db->exec("ALTER TABLE `inv_invoices` DROP KEY `idx_inv_inv_deleted`");
        foreach (['proforma_issue_date', 'converted_by', 'converted_at', 'deleted_by', 'deleted_at', 'is_deleted'] as $c) {
            if (in_array($c, $cols, true)) $db->exec("ALTER TABLE `inv_invoices` DROP COLUMN `$c`");
        }
    },

];
