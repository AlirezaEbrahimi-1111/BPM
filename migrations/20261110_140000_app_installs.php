<?php
/**
 * نسخهٔ نصب‌شدهٔ اپِ موبایلِ هر کاربر (۱۴۰۵/۰۸/۱۹).
 *
 * اپ (از build ۱۲۷ به بعد) هنگامِ باز شدن نسخه‌اش را به POST /go/api/app/report-version
 * می‌فرستد (go-api/internal/appinfo). یک ردیف برای هر کاربر = آخرین نسخه‌ای که با آن باز کرده.
 * صفحهٔ https://bpm.itmalek.com/app-releases/ (app-releases.php) برای مدیر کلِ سامانه
 * تعدادِ کاربرانِ هر نسخه را از همین جدول می‌شمارد.
 */
return [

    'description' => 'app_installs: installed mobile app version per user',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `app_installs` (
                `user_id`         INT NOT NULL PRIMARY KEY,
                `organization_id` INT NULL,
                `platform`        VARCHAR(16) NOT NULL DEFAULT 'android',
                `version`         VARCHAR(20) NOT NULL DEFAULT '',
                `build`           INT NOT NULL DEFAULT 0,
                `abi`             VARCHAR(20) NULL,
                `first_seen`      DATETIME NOT NULL,
                `last_seen`       DATETIME NOT NULL,
                KEY `idx_build` (`build`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `app_installs`");
    },

];
