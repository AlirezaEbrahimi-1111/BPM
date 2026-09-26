<?php
/**
 * ستون پیوند «پیش‌فاکتور → فاکتور رسمی».
 *
 * وقتی یک پیش‌فاکتور به فاکتور رسمی تبدیل می‌شود، converted_to_id روی خود
 * پیش‌فاکتور به شناسه‌ی فاکتور رسمی تازه اشاره می‌کند. پیش‌فاکتور تبدیل‌شده
 * دیگر موجودی رزرو نمی‌کند (مثل باطل‌شده) ولی وضعیتش دست‌نخورده می‌ماند.
 */

return [

    'description' => 'inv_invoices.converted_to_id — link a proforma to the official invoice it became',

    'up' => function (PDO $db) {
        $col = $db->query("SHOW COLUMNS FROM `inv_invoices` LIKE 'converted_to_id'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE `inv_invoices`
                       ADD COLUMN `converted_to_id` INT NULL
                       COMMENT 'اگر پیش‌فاکتور به فاکتور رسمی تبدیل شده باشد' AFTER `request_id`");
        }
    },

    'down' => function (PDO $db) {
        $col = $db->query("SHOW COLUMNS FROM `inv_invoices` LIKE 'converted_to_id'")->fetch();
        if ($col) {
            $db->exec("ALTER TABLE `inv_invoices` DROP COLUMN `converted_to_id`");
        }
    },

];
