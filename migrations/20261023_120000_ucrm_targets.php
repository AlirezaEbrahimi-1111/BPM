<?php
/**
 * CRM جدید — تارگت‌ها و قواعد هدف‌گذاری (صفحهٔ pages/ucrm-targets.php).
 *
 * ucrm_targets: تارگت/کف فروش اختصاصی هر مشتری برای یک دوره (تومان)
 *   period_type: monthly | quarterly | yearly
 *   period_key : '1405-07' (ماه)، '1405-Q3' (فصل)، '1405' (سال) — شمسی، قابل مقایسهٔ متنی
 *   مقدار خالی (NULL) = برای این دوره تعیین نشده → از آخرین دورهٔ قبلی همان نوع ادامه پیدا می‌کند،
 *   وگرنه قاعدهٔ پیش‌فرض (ucrm_target_rules).
 *   is_active = 0 → «معلق»: در رنگ وضعیت تارگت و گزارش‌ها حساب نمی‌شود.
 *
 * ucrm_target_rules: قاعدهٔ پیش‌فرض («تنظیمات هدف‌گذاری»):
 *   مثلا «کف فروش ماهانهٔ مشتری‌های کارشناس X = ۱۰۰,۰۰۰,۰۰۰ تومان».
 *   rep_id NULL = همهٔ کارشناسان. اولویت: عدد اختصاصی مشتری > قاعدهٔ همان کارشناس > قاعدهٔ همه.
 *
 * + تارگتی که مدیر هنگام تأیید مشتری گذاشته (ucrm_customers.monthly_target) به‌عنوان
 *   تارگت ماهانهٔ ماهِ تأیید منتقل می‌شود.
 *
 * ⚠️ روی سرور GRANT برای crm_service لازم است (crm-service/deploy/crm_service_db_user.sql).
 */

// ماه شمسی یک تاریخ میلادی 'Y-m-d' → '1405-07' (همان الگوریتم includes/JalaliHelper.php)
$jalaliYM = function (string $ymd): string {
    [$gy, $gm, $gd] = array_map('intval', explode('-', substr($ymd, 0, 10)));
    $gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) - 80 + $gd + $gdm[$gm - 1];
    $jy += 33 * intdiv($days, 12053);
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    $jm = ($days < 186) ? 1 + intdiv($days, 31) : 7 + intdiv($days - 186, 30);
    return sprintf('%04d-%02d', $jy, $jm);
};

return [

    'description' => 'New CRM: ucrm_targets (per-customer period targets/floors) + ucrm_target_rules (default rules)',

    'up' => function (PDO $db) use ($jalaliYM) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_targets` (
                `id`           INT AUTO_INCREMENT PRIMARY KEY,
                `customer_id`  INT NOT NULL,
                `period_type`  VARCHAR(10) NOT NULL COMMENT 'monthly | quarterly | yearly',
                `period_key`   VARCHAR(7)  NOT NULL COMMENT '1405-07 | 1405-Q3 | 1405',
                `target_amount` BIGINT NULL COMMENT 'تارگت (تومان)',
                `floor_amount`  BIGINT NULL COMMENT 'کف فروش (تومان)',
                `is_active`    TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = معلق',
                `updated_by`   INT NULL,
                `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_ucrm_target` (`customer_id`, `period_type`, `period_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_target_rules` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `basis`           VARCHAR(10) NOT NULL COMMENT 'target | floor',
                `period_type`     VARCHAR(10) NOT NULL COMMENT 'monthly | quarterly | yearly',
                `amount`          BIGINT NOT NULL COMMENT 'تومان',
                `rep_id`          INT NULL COMMENT 'NULL = همهٔ کارشناسان',
                `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
                `created_by`      INT NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_ucrm_rule` (`organization_id`, `basis`, `period_type`, `is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // تارگت هنگام تأیید → تارگت ماهانهٔ ماهِ تأیید (اگر هنوز رکوردی نیست)
        $cols = $db->query("SHOW COLUMNS FROM `ucrm_customers`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('monthly_target', $cols, true)) {
            $rows = $db->query("
                SELECT c.id, c.monthly_target,
                       COALESCE((SELECT DATE(MAX(e.created_at)) FROM ucrm_customer_events e
                                 WHERE e.customer_id = c.id AND e.action IN ('manager_approved','created')), DATE(c.created_at)) AS d
                FROM ucrm_customers c
                WHERE c.monthly_target IS NOT NULL AND c.monthly_target > 0 AND c.is_deleted = 0
            ")->fetchAll(PDO::FETCH_ASSOC);
            $ins = $db->prepare("INSERT IGNORE INTO ucrm_targets (customer_id, period_type, period_key, target_amount)
                                 VALUES (?, 'monthly', ?, ?)");
            foreach ($rows as $r) {
                $ins->execute([(int) $r['id'], $jalaliYM((string) $r['d']), (int) $r['monthly_target']]);
            }
        }
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `ucrm_target_rules`");
        $db->exec("DROP TABLE IF EXISTS `ucrm_targets`");
    },

];
