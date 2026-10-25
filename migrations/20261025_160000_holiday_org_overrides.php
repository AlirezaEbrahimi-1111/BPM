<?php
/**
 * تعطیلی رسمی → روز کاری، فقط برای یک سازمان
 *
 * تعطیلات سراسری (holidays.organization_id IS NULL) برای همهٔ سازمان‌ها تعطیل‌اند.
 * سرپرست هر سازمان می‌تواند یک تاریخ از این تعطیلات را برای سازمان خودش «روز کاری»
 * کند؛ برای بقیهٔ سازمان‌ها همان تاریخ تعطیل می‌ماند. یک ردیف = «سازمان X تاریخ Y را
 * کاری حساب می‌کند». خواندن در getHolidaySet() (includes/working-days-helper.php) و
 * core.HolidaySet (go-api) لحاظ می‌شود. کلید = تاریخ (نه id تعطیلی) تا حذف و افزودن
 * دوبارهٔ همان تعطیلی سراسری استثنا را از بین نبرد.
 */

return [

    'description' => 'Per-organization overrides that turn an official holiday into a working day',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `holiday_org_overrides` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `holiday_date`    DATE NOT NULL,
                `created_by`      INT NOT NULL,
                `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_hoo_org_date` (`organization_id`, `holiday_date`),
                CONSTRAINT `fk_hoo_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `holiday_org_overrides`");
    },

];
