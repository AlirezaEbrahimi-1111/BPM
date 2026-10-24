<?php
/**
 * سابقهٔ ساعت کاری و حقوق (درخواست ۱۴۰۵/۰۸/۰۲) — includes/work-schedule.php
 *
 * تغییر ساعت کاری/حقوق از «همان روز تغییر» اعتبار دارد و روزهای قبل (ماه قبل و
 * روزهای گذشتهٔ ماه جاری) با مقدار قبلی حساب می‌شوند.
 *
 * داده‌های سازمان ۱ — تغییر ۱ آبان ۱۴۰۵ (۲۰۲۶-۱۰-۲۳)، از مقایسهٔ پشتیبان‌های شبانهٔ سرور و
 * تأیید کارفرما. «ساعت کار روزانه»ی جدید هر سه نفر ۹.۵ (= طول شیفت جدید؛ قبلا ۱۴/۱۴/۱۰
 * ثبت شده بود — تصمیم «الف» کارفرما):
 *   ۱۷ محمدرضا فروغی   : دوشیفت ۹–۱۴ و ۱۷–۲۱، روزانه ۹  →  تک‌شیفت ۸:۰۰–۱۷:۳۰، روزانه ۹.۵
 *   ۲۵ سبحان محمدزاده : دوشیفت ۹–۱۴ و ۱۷–۲۱، روزانه ۹  →  تک‌شیفت ۹:۰۰–۱۸:۳۰، روزانه ۹.۵
 *   ۲۹ محمد سینا فروغی: ۸:۰۰–۱۷:۰۰، روزانه ۹            →  ۸:۰۰–۱۷:۳۰، روزانه ۹.۵
 * هر کاربر فقط اگر در سازمان ۱ است و ساعت فعلی‌اش دقیقا همان «ساعت جدید» است اعمال
 * می‌شود (روی دیتابیس دیگر — مثلا محلی — که این شناسه‌ها آدم‌های دیگری‌اند، کاری نمی‌کند).
 */
return [

    'description' => 'user_work_schedules: dated history of shift hours / daily hours / salary; org 1 change of 2026-10-23',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `user_work_schedules` (
                `id`               INT NOT NULL AUTO_INCREMENT,
                `user_id`          INT NOT NULL,
                `effective_from`   DATE NOT NULL COMMENT 'از این روز به بعد معتبر است',
                `shift_count`      TINYINT NOT NULL DEFAULT 1,
                `shift_1_start`    TIME NULL,
                `shift_1_end`      TIME NULL,
                `shift_2_start`    TIME NULL,
                `shift_2_end`      TIME NULL,
                `daily_work_hours` DECIMAL(4,2) NOT NULL DEFAULT 0,
                `monthly_salary`   DECIMAL(15,2) NOT NULL DEFAULT 0,
                `created_by`       INT NULL,
                `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_uws_user_from` (`user_id`, `effective_from`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $fix = [
            17 => ['old' => [2, '09:00:00', '14:00:00', '17:00:00', '21:00:00', 9.00], 'new' => ['08:00:00', '17:30:00', 14.00]],
            25 => ['old' => [2, '09:00:00', '14:00:00', '17:00:00', '21:00:00', 9.00], 'new' => ['09:00:00', '18:30:00', 14.00]],
            29 => ['old' => [1, '08:00:00', '17:00:00', null, null, 9.00],             'new' => ['08:00:00', '17:30:00', 10.00]],
        ];
        $get = $db->prepare("SELECT organization_id, shift_count, shift_1_start, shift_1_end, daily_work_hours, monthly_salary
                             FROM users WHERE id = ?");
        $ins = $db->prepare("INSERT IGNORE INTO user_work_schedules
            (user_id, effective_from, shift_count, shift_1_start, shift_1_end, shift_2_start, shift_2_end, daily_work_hours, monthly_salary)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $upd = $db->prepare("UPDATE users SET daily_work_hours = 9.50 WHERE id = ?");
        foreach ($fix as $uid => $f) {
            $get->execute([$uid]);
            $u = $get->fetch(PDO::FETCH_ASSOC);
            if (!$u || (int) $u['organization_id'] !== 1 || (int) $u['shift_count'] !== 1
                || $u['shift_1_start'] !== $f['new'][0] || $u['shift_1_end'] !== $f['new'][1]
                || abs((float) $u['daily_work_hours'] - $f['new'][2]) > 0.001) {
                continue; // شخص دیگری است یا ساعتش باز عوض شده — دست نمی‌زنیم
            }
            $salary = (float) $u['monthly_salary'];
            [$sc, $s1s, $s1e, $s2s, $s2e, $dh] = $f['old'];
            $ins->execute([$uid, '2000-01-01', $sc, $s1s, $s1e, $s2s, $s2e, $dh, $salary]);
            $ins->execute([$uid, '2026-10-23', 1, $f['new'][0], $f['new'][1], null, null, 9.50, $salary]);
            $upd->execute([$uid]);
        }
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `user_work_schedules`");
    },

];
