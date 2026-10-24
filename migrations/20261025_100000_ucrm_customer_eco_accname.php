<?php
/**
 * CRM جدید — صفحهٔ جزئیات مشتری (طرح ۱۴۰۵/۰۸/۰۲): دو فیلد تازه برای مشتری
 *   economic_code   : کد اقتصادی (اختیاری، ۱۰ تا ۱۲ رقم) — در فرم و اکسل
 *   accounting_name : نام مشتری در نرم‌افزار حسابداری — فعلا دستی (مدیر/حسابداری)،
 *                     بعدا از اتصال شایگان پر می‌شود
 * GRANT تازه لازم نیست (همان جدول ucrm_customers).
 */
return [

    'description' => 'ucrm_customers: economic_code + accounting_name',

    'up' => function (PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM `ucrm_customers`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('economic_code', $cols, true)) {
            $db->exec("ALTER TABLE `ucrm_customers` ADD COLUMN `economic_code` VARCHAR(20) NULL COMMENT 'کد اقتصادی (۱۰ تا ۱۲ رقم)' AFTER `national_id`");
        }
        if (!in_array('accounting_name', $cols, true)) {
            $db->exec("ALTER TABLE `ucrm_customers` ADD COLUMN `accounting_name` VARCHAR(255) NULL COMMENT 'نام در نرم‌افزار حسابداری' AFTER `name`");
        }
    },

    'down' => function (PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM `ucrm_customers`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('economic_code', $cols, true)) $db->exec("ALTER TABLE `ucrm_customers` DROP COLUMN `economic_code`");
        if (in_array('accounting_name', $cols, true)) $db->exec("ALTER TABLE `ucrm_customers` DROP COLUMN `accounting_name`");
    },

];
