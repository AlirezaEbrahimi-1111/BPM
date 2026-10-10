<?php
/**
 * اجازهٔ «ثبت ورود/خروج از بیرون شبکهٔ سازمان» برای هر دستگاه
 *
 * تا امروز ثبت ورود/خروج فقط از IPهای مجاز سازمان (attendance_allowed_ips)
 * ممکن بود. از این نسخه، دستگاهی که بیرون از شبکه تلاش کند یک «درخواست ثبت از
 * بیرون» می‌گذارد؛ سرپرست همان دستگاه را یک بار تأیید می‌کند و بعد از آن با هر
 * اینترنتی کار می‌کند. دستگاه‌هایی که فقط داخل شبکه تأیید شده‌اند خودکار این
 * اجازه را نمی‌گیرند (پیش‌فرض none).
 *
 *   outside_status       none | pending | approved | rejected
 *   outside_requested_at زمان آخرین درخواست از بیرون شبکه
 *   outside_decided_by   سرپرستی که تأیید/رد/لغو کرده
 *   outside_decided_at   زمان همان تصمیم
 */

if (!function_exists('attDeviceColumnExists')) {
    function attDeviceColumnExists(PDO $db, string $column): bool
    {
        // (SHOW COLUMNS با placeholder قابل prepare نیست → quote)
        $st = $db->query("SHOW COLUMNS FROM `attendance_devices` LIKE " . $db->quote($column));
        return (bool) $st->fetch();
    }
}

return [

    'description' => 'Per-device approval for attendance check-in from outside the allowed network',

    'up' => function (PDO $db) {
        if (!attDeviceColumnExists($db, 'outside_status')) {
            $db->exec("ALTER TABLE `attendance_devices`
                ADD COLUMN `outside_status` ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
                ADD COLUMN `outside_requested_at` DATETIME NULL DEFAULT NULL,
                ADD COLUMN `outside_decided_by` INT NULL DEFAULT NULL,
                ADD COLUMN `outside_decided_at` DATETIME NULL DEFAULT NULL");
        }
    },

    'down' => function (PDO $db) {
        if (attDeviceColumnExists($db, 'outside_status')) {
            $db->exec("ALTER TABLE `attendance_devices`
                DROP COLUMN `outside_status`,
                DROP COLUMN `outside_requested_at`,
                DROP COLUMN `outside_decided_by`,
                DROP COLUMN `outside_decided_at`");
        }
    },

];
