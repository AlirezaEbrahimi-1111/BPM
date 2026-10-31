<?php
/**
 * CRM جدید — پیوند مشتری به «تفصیلی شناور» نرم‌افزار حسابداری شایگان (۱۴۰۵/۰۸/۰۹).
 *   shygun_job_no : شمارهٔ تفصیلی (JobNo) در شایگان — برای نمایش و دد‌آپ
 *   shygun_guid   : شناسهٔ یکتای تفصیلی (GuId) در شایگان — کلید پیوند پایدار
 * وقتی مشتری از تب «انتخاب از حسابداری» وارد می‌شود، این دو پر می‌شوند و نام
 * حسابداری در accounting_name می‌نشیند. مشتری دستی این دو را NULL دارد.
 * GRANT تازه لازم نیست (همان جدول ucrm_customers، سرویس از قبل دسترسی دارد).
 */
return [

    'description' => 'ucrm_customers: shygun_job_no + shygun_guid (پیوند به حسابداری شایگان)',

    'up' => function (PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM `ucrm_customers`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('shygun_job_no', $cols, true)) {
            $db->exec("ALTER TABLE `ucrm_customers` ADD COLUMN `shygun_job_no` VARCHAR(50) NULL COMMENT 'شمارهٔ تفصیلی شایگان (JobNo)' AFTER `accounting_name`");
        }
        if (!in_array('shygun_guid', $cols, true)) {
            $db->exec("ALTER TABLE `ucrm_customers` ADD COLUMN `shygun_guid` CHAR(36) NULL COMMENT 'GuId تفصیلی شایگان' AFTER `shygun_job_no`");
        }
        // ایندکس برای تشخیص سریع «قبلا وارد شده؟» هنگام نمایش فهرست حسابداری
        $idx = $db->query("SHOW INDEX FROM `ucrm_customers` WHERE Key_name = 'idx_shygun_guid'")->fetchAll();
        if (!$idx) {
            $db->exec("ALTER TABLE `ucrm_customers` ADD INDEX `idx_shygun_guid` (`organization_id`, `shygun_guid`)");
        }
    },

    'down' => function (PDO $db) {
        $idx = $db->query("SHOW INDEX FROM `ucrm_customers` WHERE Key_name = 'idx_shygun_guid'")->fetchAll();
        if ($idx) $db->exec("ALTER TABLE `ucrm_customers` DROP INDEX `idx_shygun_guid`");
        $cols = $db->query("SHOW COLUMNS FROM `ucrm_customers`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('shygun_guid', $cols, true))   $db->exec("ALTER TABLE `ucrm_customers` DROP COLUMN `shygun_guid`");
        if (in_array('shygun_job_no', $cols, true)) $db->exec("ALTER TABLE `ucrm_customers` DROP COLUMN `shygun_job_no`");
    },

];
