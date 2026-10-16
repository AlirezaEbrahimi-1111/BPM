<?php
/**
 * درصد سهم همکار «اختصاصیِ یک فاکتور» (استثنا بر درصد ماهانه).
 *
 * درصد سهم همکار به‌طور عادی از inv_partner_month_share (همکار × ماه شمسی)
 * و «زنده» خوانده می‌شود. گاهی برای یک فاکتور خاص درصد دیگری توافق می‌شود؛
 * این ستون همان استثناست:
 *   • partner_percent_override = NULL  → درصد ماه (رفتار قبلی، بدون تغییر)
 *   • partner_percent_override = عدد   → همین درصد، مستقل از تغییرات درصد ماه
 *
 * عمدا ستون روی خود inv_invoices است (نه جدول جدید): کاربر دیتابیس
 * crm_service روی این جدول از قبل دسترسی دارد؛ جدول جدید GRANT دستی روی
 * سرور لازم داشت.
 */

return [

    'description' => 'inv_invoices — partner_percent_override (per-invoice exception to the monthly partner share %)',

    'up' => function (PDO $db) {
        $exists = $db->query("SHOW COLUMNS FROM `inv_invoices` LIKE 'partner_percent_override'")->fetch();
        if (!$exists) {
            $db->exec("ALTER TABLE `inv_invoices` ADD COLUMN `partner_percent_override` DECIMAL(6,3) NULL
                       COMMENT 'درصد سهم همکار اختصاصی این فاکتور؛ NULL = درصد ماه' AFTER `partner_id`");
        }
    },

    'down' => function (PDO $db) {
        $exists = $db->query("SHOW COLUMNS FROM `inv_invoices` LIKE 'partner_percent_override'")->fetch();
        if ($exists) {
            $db->exec("ALTER TABLE `inv_invoices` DROP COLUMN `partner_percent_override`");
        }
    },

];
