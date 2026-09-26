<?php
/**
 * پین‌کردن تب/فیلتر پیش‌فرض و ستاره‌زدن کارها در داشبورد باید فقط تا
 * پایان همون روز معتبر بمونه — قبلا این توی localStorage با مقایسه‌ی
 * تاریخ خود سیستم کلاینت (new Date()) نگه‌داری می‌شد. کاربرهایی که
 * ساعت سیستم‌شون درست تنظیم نیست (دقیقا همون مشکلی که توی تاریخ‌گزین
 * هم دیدیم)، هیچ‌وقت این ریست رو نمی‌بینن. این جدول این سه مقدار رو
 * سمت سرور نگه می‌داره؛ تاریخ معتبربودن هم CURDATE() سروره، نه ساعت
 * کلاینت.
 */

return [

    'description' => 'Create user_dashboard_prefs table for server-anchored star/pin daily reset',

    'up' => function (PDO $db) {
        $db->exec("CREATE TABLE `user_dashboard_prefs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `pref_key` VARCHAR(50) NOT NULL,
            `pref_value` TEXT NULL,
            `pref_date` DATE NOT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_user_pref` (`user_id`, `pref_key`),
            INDEX `idx_pref_date` (`pref_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `user_dashboard_prefs`");
    },

];
