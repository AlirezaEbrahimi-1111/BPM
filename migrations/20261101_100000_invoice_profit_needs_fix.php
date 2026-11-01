<?php
/**
 * حسابداری رسمی — حالتِ سومِ «ثبت سود همکار»: «نیاز به اصلاح» (۱۴۰۵/۰۸/۱۰).
 *
 * تا امروز partner_profit_recorded فقط دو حالت داشت (ثبت‌نشده/ثبت‌شده). اگر بعد از
 * «ثبت سود»، فاکتور اصلاحیه می‌خورد (برگشت به پیش‌نویس یا تغییر درصد سهم همکار)،
 * سودِ ثبت‌شده در حسابداری دیگر درست نبود ولی سیستم همچنان «ثبت شده» نشان می‌داد.
 * این ستون می‌گوید «قبلاً ثبت شده بود و حالا باید دوباره اصلاح شود». وقتی
 * recorded=1 و needs_fix=1 → وضعیت «نیاز به اصلاح». با ثبتِ دوبارهٔ سود صفر می‌شود.
 * GRANT تازه لازم نیست (همان جدول inv_invoices).
 */
return [

    'description' => 'inv_invoices: partner_profit_needs_fix (حالت «نیاز به اصلاح» سود همکار)',

    'up' => function (PDO $db) {
        $has = $db->query("SHOW COLUMNS FROM `inv_invoices` LIKE 'partner_profit_needs_fix'")->fetch();
        if (!$has) {
            $db->exec("ALTER TABLE `inv_invoices`
                ADD COLUMN `partner_profit_needs_fix` TINYINT(1) NOT NULL DEFAULT 0
                COMMENT 'سود ثبت‌شده بعد از اصلاحیه کهنه شده و باید دوباره در حسابداری اصلاح شود'
                AFTER `partner_profit_recorded`");
        }
    },

    'down' => function (PDO $db) {
        $has = $db->query("SHOW COLUMNS FROM `inv_invoices` LIKE 'partner_profit_needs_fix'")->fetch();
        if ($has) {
            $db->exec("ALTER TABLE `inv_invoices` DROP COLUMN `partner_profit_needs_fix`");
        }
    },

];
