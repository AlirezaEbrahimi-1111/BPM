<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  ارسال روزانه‌ی خودکار حکمت‌های نهج‌البلاغه به لیستی از شماره‌تلفن‌ها
 *  (پیامک، ساعت مشخص، از طریق cron) — ۴ جدول جدید، مستقل از بقیه‌ی
 *  اسکیما (بدون FK به users/tasks و ...) تا این ویژگی کاملاً ایزوله
 *  و قابل حذف/تغییر باشه.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'Create hekmat_quotes, hekmat_recipients, hekmat_settings, hekmat_send_log tables for the daily Nahj al-Balagha SMS broadcast feature',

    'up' => function (PDO $db) {

        $db->exec("
            CREATE TABLE IF NOT EXISTS `hekmat_quotes` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `text`       TEXT NOT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_hekmat_quotes_order` (`sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `hekmat_recipients` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `phone`      VARCHAR(20) NOT NULL,
                `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_hekmat_recipients_phone` (`phone`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");

        // 🔒 تک‌ردیفی (id همیشه ۱) — تنظیماتِ سراسریِ این فیچر؛ next_index و
        // last_sent_date وضعیتِ چرخشِ روزانه رو نگه می‌دارن تا cron هربار از
        // صفر شروع نکنه و دوباره‌کاریِ همون روز رو تشخیص بده
        $db->exec("
            CREATE TABLE IF NOT EXISTS `hekmat_settings` (
                `id`             INT PRIMARY KEY DEFAULT 1,
                `send_hour`      TINYINT NOT NULL DEFAULT 8,
                `send_minute`    TINYINT NOT NULL DEFAULT 0,
                `closing_text`   VARCHAR(500) NOT NULL DEFAULT '',
                `is_enabled`     TINYINT(1) NOT NULL DEFAULT 0,
                `rotation_mode`  ENUM('sequential','random') NOT NULL DEFAULT 'sequential',
                `next_index`     INT NOT NULL DEFAULT 0,
                `last_sent_date` DATE NULL DEFAULT NULL,
                `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");
        $db->exec("INSERT IGNORE INTO `hekmat_settings` (`id`) VALUES (1)");

        // 🔒 quote_text به‌صورتِ متنِ کامل (نه FK به hekmat_quotes) ذخیره می‌شه —
        // چون لیستِ جملات با paste دسته‌جمعی کاملاً جایگزین می‌شه؛ اگه FK
        // می‌بود، تاریخچه‌ی ارسال‌هایِ قدیمی با حذفِ اون جمله بی‌معنی می‌شد
        $db->exec("
            CREATE TABLE IF NOT EXISTS `hekmat_send_log` (
                `id`               INT AUTO_INCREMENT PRIMARY KEY,
                `send_date`        DATE NOT NULL,
                `quote_text`       TEXT NOT NULL,
                `recipient_phone`  VARCHAR(20) NOT NULL,
                `status`           ENUM('sent','failed') NOT NULL,
                `is_test`          TINYINT(1) NOT NULL DEFAULT 0,
                `error_message`    TEXT NULL,
                `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_hekmat_log_date` (`send_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");

    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `hekmat_send_log`");
        $db->exec("DROP TABLE IF EXISTS `hekmat_settings`");
        $db->exec("DROP TABLE IF EXISTS `hekmat_recipients`");
        $db->exec("DROP TABLE IF EXISTS `hekmat_quotes`");
    },

];
