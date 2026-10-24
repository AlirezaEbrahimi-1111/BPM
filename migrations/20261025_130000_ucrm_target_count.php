<?php
/**
 * CRM جدید — «تارگت تعداد فروش (مورد)» کنار تارگت مبلغی (پنجرهٔ «ویرایش گروهی تارگت‌ها»، طرح ۱۴۰۵/۰۸/۰۲).
 * همان قاعدهٔ تارگت مبلغی: عدد اختصاصی دوره، وگرنه ادامهٔ آخرین دورهٔ قبلی هم‌نوع.
 * فروش تعدادی واقعی بعدا از فاکتورهای CRM حساب می‌شود. GRANT تازه لازم نیست (همان جدول ucrm_targets).
 */
return [

    'description' => 'ucrm_targets: target_count (sales count target)',

    'up' => function (PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM `ucrm_targets`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('target_count', $cols, true)) {
            $db->exec("ALTER TABLE `ucrm_targets` ADD COLUMN `target_count` INT NULL COMMENT 'تارگت تعداد فروش (مورد)' AFTER `target_amount`");
        }
    },

    'down' => function (PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM `ucrm_targets`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('target_count', $cols, true)) $db->exec("ALTER TABLE `ucrm_targets` DROP COLUMN `target_count`");
    },

];
