<?php
/**
 * نشانِ «این ورود/خروج از بیرون شبکهٔ سازمان ثبت شده» روی هر رکورد حضور
 *
 * همراه با امکان ثبت ورود/خروج از بیرون شبکه (مایگریشن قبلی)، گزارش حضور باید
 * نشان بدهد هر ثبت از محل کار (شبکهٔ مجاز سازمان) بوده یا از بیرون.
 *
 *   check_in_outside   ۱ = ورود از بیرون شبکهٔ مجاز ثبت شده
 *   check_out_outside  ۱ = خروج از بیرون شبکهٔ مجاز ثبت شده
 *
 * رکوردهای قبلی ۰ می‌مانند — تا امروز ثبت فقط از داخل شبکه ممکن بود.
 */

if (!function_exists('attRecordColumnExists')) {
    function attRecordColumnExists(PDO $db, string $column): bool
    {
        // (SHOW COLUMNS با placeholder قابل prepare نیست → quote)
        $st = $db->query("SHOW COLUMNS FROM `attendance_records` LIKE " . $db->quote($column));
        return (bool) $st->fetch();
    }
}

return [

    'description' => 'Flag attendance check-ins/check-outs made from outside the allowed network',

    'up' => function (PDO $db) {
        if (!attRecordColumnExists($db, 'check_in_outside')) {
            $db->exec("ALTER TABLE `attendance_records`
                ADD COLUMN `check_in_outside` TINYINT(1) NOT NULL DEFAULT 0,
                ADD COLUMN `check_out_outside` TINYINT(1) NOT NULL DEFAULT 0");
        }
    },

    'down' => function (PDO $db) {
        if (attRecordColumnExists($db, 'check_in_outside')) {
            $db->exec("ALTER TABLE `attendance_records`
                DROP COLUMN `check_in_outside`,
                DROP COLUMN `check_out_outside`");
        }
    },

];
