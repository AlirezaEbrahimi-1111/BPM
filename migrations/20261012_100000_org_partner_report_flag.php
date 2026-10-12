<?php
/**
 * پرچمِ «گزارش همکاران» در سطح سازمان.
 *
 * تا امروز دسترسی به گزارش همکاران (pages/inv-partner-report.php) هاردکد بود و
 * به هر سوپروایزر/مدیرِ *هر سازمانی* داده می‌شد؛ پس در شرکت‌های تازه هم دیده
 * می‌شد. از این نسخه، این گزینه فقط برای سازمان‌هایی نمایش داده می‌شود که
 * سوپرادمین (صفحهٔ superAdmin.php) آن را روشن کرده باشد.
 *
 *   partner_report_enabled  0 = خاموش (پیش‌فرض)، 1 = روشن
 *
 * سازمان ۱ (شرکت اصلی) پیش‌فرض روشن می‌شود چون همکاران و فاکتور رسمی همان‌جاست.
 */

if (!function_exists('orgColumnExists')) {
    function orgColumnExists(PDO $db, string $column): bool
    {
        $st = $db->query("SHOW COLUMNS FROM `organizations` LIKE " . $db->quote($column));
        return (bool) $st->fetch();
    }
}

return [

    'description' => 'Per-organization toggle for the partner (commission) report',

    'up' => function (PDO $db) {
        if (!orgColumnExists($db, 'partner_report_enabled')) {
            $db->exec("ALTER TABLE `organizations`
                ADD COLUMN `partner_report_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `plan_source`");
        }
        // شرکت اصلی (سازمان ۱) از همان ابتدا روشن
        $db->exec("UPDATE `organizations` SET `partner_report_enabled` = 1 WHERE id = 1");
    },

    'down' => function (PDO $db) {
        if (orgColumnExists($db, 'partner_report_enabled')) {
            $db->exec("ALTER TABLE `organizations` DROP COLUMN `partner_report_enabled`");
        }
    },

];
