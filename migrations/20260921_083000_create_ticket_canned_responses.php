<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: پاسخ‌های آماده‌ی تیکت (canned responses)
 *  تاریخ: ۱۴۰۵/۰۶/۳۰
 * ───────────────────────────────────────────────────────────────────
 *  چرا؟
 *    بخشی از تیکت‌های کاربران تکراری‌ان (رمز، دسترسی، «چطور فلان کار رو
 *    ثبت کنم»). تا حالا هر بار باید از اول تایپ می‌شد. این جدول یک
 *    کتابخانه‌ی پاسخ آماده‌ی سازمانی می‌ده که با یک کلیک داخل کادر
 *    پاسخ درج می‌شه.
 *
 *  نکته: عمدا سازمانی‌ست (organization_id)، نه سراسری — هر سازمان
 *  لحن/فرآیند خودش رو داره، دقیقا مثل بقیه‌ی داده‌های این اپ.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'ساخت جدول ticket_canned_responses (پاسخ‌های آماده‌ی تیکت، سازمانی)',

    'up' => function (PDO $db) {
        $stmt = $db->query("SHOW TABLES LIKE 'ticket_canned_responses'");
        if ($stmt->fetch()) {
            return;
        }

        $db->exec("
            CREATE TABLE `ticket_canned_responses` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `title`           VARCHAR(150) NOT NULL,
                `body`            TEXT NOT NULL,
                `sort_order`      INT NOT NULL DEFAULT 0,
                `created_by`      INT NULL,
                `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_tcr_org` (`organization_id`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `ticket_canned_responses`");
    },

];
