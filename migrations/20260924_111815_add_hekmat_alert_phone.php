<?php
/**
 * افزودن شماره‌ی هشدار به تنظیمات حکمت روزانه — وقتی ارسال روزانه
 * (خودکار یا دستی) با شکست مواجه بشه، یه پیامک گزارش خطا به این شماره
 * فرستاده می‌شه تا مدیر بی‌خبر نمونه.
 */

return [

    'description' => 'Add alert_phone column to hekmat_settings for daily-send failure notifications',

    'up' => function (PDO $db) {
        $db->exec("
            ALTER TABLE `hekmat_settings`
            ADD COLUMN `alert_phone` VARCHAR(20) NOT NULL DEFAULT '09105255090' AFTER `rotation_mode`
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("ALTER TABLE `hekmat_settings` DROP COLUMN `alert_phone`");
    },

];
