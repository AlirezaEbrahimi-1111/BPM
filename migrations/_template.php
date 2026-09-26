<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  قالب مهاجرت — این فایل اجرا نمی‌شود (نامش با _ شروع می‌شود)
 * ───────────────────────────────────────────────────────────────────
 *  برای ساخت مهاجرت جدید:
 *
 *  ۱) یک کپی از این فایل بگیرید
 *  ۲) نامش را به این شکل بگذارید:
 *         YYYYMMDD_HHMMSS_شرح_کوتاه.php
 *     مثال:
 *         20260715_143000_add_email_to_users.php
 *
 *     ⚠️ نام فایل، ترتیب اجرا را تعیین می‌کند. پس تاریخ/ساعت مهم است.
 *
 *  ۳) کد را در up() و down() بنویسید
 *  ۴) اول با «پیش‌نمایش اجرا» تست کنید
 *  ۵) بعد اجرا کنید
 *
 *  ═══ قوانین طلایی ═══
 *
 *  • هر مهاجرت باید down() داشته باشد که دقیقا کار up() را برگرداند.
 *  • هرگز یک مهاجرت اجراشده را ویرایش نکنید. مهاجرت جدید بسازید.
 *  • قبل از اجرا روی سرور زنده، حتما پشتیبان بگیرید.
 *  • یک مهاجرت = یک تغییر منطقی. چند تغییر بی‌ربط را قاطی نکنید.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'شرح کوتاه این تغییر',

    /**
     * اعمال تغییر
     */
    'up' => function (PDO $db) {

        // ── نمونه ۱: افزودن ستون ─────────────────────────
        // $db->exec("ALTER TABLE `users` ADD COLUMN `national_code` VARCHAR(10) NULL COMMENT 'کد ملی'");

        // ── نمونه ۲: افزودن مقدار به ENUM ────────────────
        // $db->exec("ALTER TABLE `tasks` MODIFY COLUMN `status`
        //            ENUM('not_started','in_progress','completed','period_done','archived')
        //            DEFAULT 'not_started'");

        // ── نمونه ۳: ساخت جدول جدید ──────────────────────
        // $db->exec("
        //     CREATE TABLE IF NOT EXISTS `example` (
        //         `id`         INT AUTO_INCREMENT PRIMARY KEY,
        //         `title`      VARCHAR(255) NOT NULL,
        //         `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        //     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        // ");

        // ── نمونه ۴: افزودن کلید خارجی ───────────────────
        // $db->exec("ALTER TABLE `tasks`
        //            ADD CONSTRAINT `fk_tasks_org`
        //            FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`)
        //            ON DELETE RESTRICT");

    },

    /**
     * بازگرداندن تغییر — دقیقا عکس up()
     */
    'down' => function (PDO $db) {

        // $db->exec("ALTER TABLE `users` DROP COLUMN `national_code`");
        // $db->exec("DROP TABLE IF EXISTS `example`");
        // $db->exec("ALTER TABLE `tasks` DROP FOREIGN KEY `fk_tasks_org`");

    },

];
