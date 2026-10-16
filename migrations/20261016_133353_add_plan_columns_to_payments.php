<?php
/**
 * اضافه‌کردنِ ستون‌هایِ «پلن» و «تعدادِ کاربر» به جدولِ payments — تا
 * رکوردِ پرداخت بداند کاربر دقیقاً برایِ چه پلنی پول داده (نه فقط چند
 * ماه)، چون callback بعداً از همین دو ستون برایِ ارتقایِ واقعیِ سازمان
 * استفاده می‌کند.
 */

return [

    'description' => 'Add plan/plan_users columns to payments table for ZarinPal plan purchase flow',

    'up' => function (PDO $db) {
        $addCol = function (string $table, string $col, string $ddl) use ($db) {
            $exists = $db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($col))->fetch();
            if (!$exists) {
                $db->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
            }
        };

        $addCol('payments', 'plan', "`plan` ENUM('silver','gold') NULL COMMENT 'پلنی که خریده شده' AFTER `organization_id`");
        $addCol('payments', 'plan_users', "`plan_users` INT NULL COMMENT 'سقف کاربر خریداری‌شده (برای طلایی)' AFTER `plan`");
    },

    'down' => function (PDO $db) {
        $dropCol = function (string $table, string $col) use ($db) {
            $exists = $db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($col))->fetch();
            if ($exists) {
                $db->exec("ALTER TABLE `$table` DROP COLUMN `$col`");
            }
        };
        $dropCol('payments', 'plan_users');
        $dropCol('payments', 'plan');
    },

];
