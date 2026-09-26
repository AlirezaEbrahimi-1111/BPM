<?php
/**
 * فیلدهای لازم برای قالب رسمی «صورتحساب فروش کالا و خدمات».
 *
 *  • inv_seller  → استان، شهرستان، شهر، شماره شبا، شماره کارت، نام بانک
 *  • inv_invoices→ نحوه‌ی فروش (نقدی / غیرنقدی)
 *  • crm_customers→ استان، شهر، کد پستی  (برای بلوک «مشخصات خریدار»)
 *
 * همه idempotent — با SHOW COLUMNS چک می‌شوند.
 */

return [

    'description' => 'Invoice template fields: seller bank/geo, invoice payment_type, customer geo',

    'up' => function (PDO $db) {

        $addCol = function (string $table, string $col, string $ddl) use ($db) {
            $exists = $db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($col))->fetch();
            if (!$exists) {
                $db->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
            }
        };

        // ── فروشنده ──
        $addCol('inv_seller', 'province', "`province` VARCHAR(60) NULL COMMENT 'استان'");
        $addCol('inv_seller', 'shahrestan', "`shahrestan` VARCHAR(60) NULL COMMENT 'شهرستان'");
        $addCol('inv_seller', 'city', "`city` VARCHAR(60) NULL COMMENT 'شهر'");
        $addCol('inv_seller', 'iban', "`iban` VARCHAR(34) NULL COMMENT 'شماره شبا (بدون IR هم قابل قبول)'");
        $addCol('inv_seller', 'card_number', "`card_number` VARCHAR(30) NULL COMMENT 'شماره کارت'");
        $addCol('inv_seller', 'bank_name', "`bank_name` VARCHAR(60) NULL COMMENT 'نام بانک'");

        // ── فاکتور: نحوه‌ی فروش ──
        $addCol(
            'inv_invoices',
            'payment_type',
            "`payment_type` ENUM('cash','credit') NULL COMMENT 'نقدی / غیرنقدی' AFTER `source`"
        );

        // ── مشتری: استان/شهر/کدپستی ──
        $addCol('crm_customers', 'province', "`province` VARCHAR(60) NULL COMMENT 'استان'");
        $addCol('crm_customers', 'city', "`city` VARCHAR(60) NULL COMMENT 'شهر'");
        $addCol('crm_customers', 'postal_code', "`postal_code` VARCHAR(15) NULL COMMENT 'کد پستی'");
    },

    'down' => function (PDO $db) {
        $dropCol = function (string $table, string $col) use ($db) {
            $exists = $db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($col))->fetch();
            if ($exists) {
                $db->exec("ALTER TABLE `$table` DROP COLUMN `$col`");
            }
        };
        foreach (['province', 'shahrestan', 'city', 'iban', 'card_number', 'bank_name'] as $c) {
            $dropCol('inv_seller', $c);
        }
        $dropCol('inv_invoices', 'payment_type');
        foreach (['province', 'city', 'postal_code'] as $c) {
            $dropCol('crm_customers', $c);
        }
    },

];
