<?php
/**
 * CRM جدید — «هشدار عدم پیگیری» داشبورد فروش (۱۴۰۵/۰۸/۰۳).
 * مشتری فعالی که بیش از این تعداد روز پیگیری «انجام‌شده» نداشته در بخش «مشتریان نیازمند پیگیری» می‌آید.
 * پیش‌فرض ۱۴ روز؛ مدیر در «تنظیمات تارگت» (pages/ucrm-targets.php) عوضش می‌کند.
 * GRANT تازه لازم نیست (همان جدول ucrm_settings).
 */
return [

    'description' => 'ucrm_settings: followup_alert_days (sales dashboard)',

    'up' => function (PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM `ucrm_settings`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('followup_alert_days', $cols, true)) {
            $db->exec("ALTER TABLE `ucrm_settings` ADD COLUMN `followup_alert_days` INT NOT NULL DEFAULT 14
                       COMMENT 'هشدار عدم پیگیری بعد از چند روز' AFTER `target_green_from`");
        }
    },

    'down' => function (PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM `ucrm_settings`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('followup_alert_days', $cols, true)) $db->exec("ALTER TABLE `ucrm_settings` DROP COLUMN `followup_alert_days`");
    },

];
