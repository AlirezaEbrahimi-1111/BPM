<?php
/**
 * سابقه‌ی «برگرداندن فاکتور تأییدشده به پیش‌نویس».
 *
 * فاکتور نهایی برای ویرایش به پیش‌نویس برمی‌گردد (موجودی انبارش برمی‌گردد،
 * شماره‌اش می‌ماند). این سه ستون ردّ همان کار را نگه می‌دارند:
 *   • reverted_at   — آخرین بار کِی برگردانده شد
 *   • reverted_by   — چه کسی (users.id)
 *   • revert_count  — در مجموع چند بار
 *
 * عمدا ستون روی خود inv_invoices است (نه جدول جدید): کاربر دیتابیس
 * crm_service روی این جدول از قبل دسترسی دارد؛ جدول جدید GRANT دستی روی
 * سرور لازم داشت.
 */

return [

    'description' => 'inv_invoices — reverted_at / reverted_by / revert_count (approved invoice sent back to draft)',

    'up' => function (PDO $db) {
        $add = function (string $col, string $ddl) use ($db) {
            $exists = $db->query("SHOW COLUMNS FROM `inv_invoices` LIKE " . $db->quote($col))->fetch();
            if (!$exists) {
                $db->exec("ALTER TABLE `inv_invoices` ADD COLUMN {$ddl}");
            }
        };
        $add('reverted_at', "`reverted_at` DATETIME NULL COMMENT 'آخرین برگرداندن از تأییدشده به پیش‌نویس'");
        $add('reverted_by', "`reverted_by` INT NULL COMMENT 'کاربری که به پیش‌نویس برگرداند'");
        $add('revert_count', "`revert_count` INT NOT NULL DEFAULT 0 COMMENT 'تعداد دفعات برگرداندن به پیش‌نویس'");
    },

    'down' => function (PDO $db) {
        foreach (['reverted_at', 'reverted_by', 'revert_count'] as $col) {
            $exists = $db->query("SHOW COLUMNS FROM `inv_invoices` LIKE " . $db->quote($col))->fetch();
            if ($exists) {
                $db->exec("ALTER TABLE `inv_invoices` DROP COLUMN `{$col}`");
            }
        }
    },

];
