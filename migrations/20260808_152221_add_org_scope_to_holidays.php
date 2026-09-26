<?php
/**
 * افزودن دو مدل تعطیلی به جدول holidays:
 *   ۱) سراسری (organization_id = NULL) — فقط کاربر id=1 می‌سازه/حذف می‌کنه، برای همهٔ سازمان‌ها اعمال می‌شه
 *   ۲) مخصوص سازمان (organization_id = X) — هر سوپروایزر برای سازمان خودش می‌سازه، فقط همون سازمان می‌بینه
 * همچنین نوع «تعطیلی هفتگی تکرارشونده» (مثلا هر پنج‌شنبه) اضافه می‌شه که
 * به‌جای یک تاریخ مشخص، روز هفته (day_of_week، بر اساس PHP date('w'): ۰=یکشنبه..۶=شنبه) رو ذخیره می‌کنه
 */

return [

    'description' => 'Add organization_id + weekly-recurring support to holidays table',

    'up' => function (PDO $db) {
        $db->exec("ALTER TABLE `holidays`
            ADD COLUMN `organization_id` INT NULL DEFAULT NULL COMMENT 'NULL = سراسری، در غیر این صورت فقط برای این سازمان' AFTER `id`,
            ADD COLUMN `type` ENUM('date','weekly') NOT NULL DEFAULT 'date' COMMENT 'date = یک روز مشخص، weekly = تکرارشوندهٔ هفتگی' AFTER `title`,
            ADD COLUMN `day_of_week` TINYINT NULL DEFAULT NULL COMMENT '۰=یکشنبه..۶=شنبه (PHP date(w)) — فقط برای type=weekly' AFTER `type`,
            MODIFY COLUMN `holiday_date` DATE NULL COMMENT 'فقط برای type=date'
        ");

        $db->exec("ALTER TABLE `holidays`
            ADD CONSTRAINT `fk_holidays_org`
            FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`)
            ON DELETE CASCADE
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("ALTER TABLE `holidays` DROP FOREIGN KEY `fk_holidays_org`");
        $db->exec("ALTER TABLE `holidays`
            DROP COLUMN `organization_id`,
            DROP COLUMN `type`,
            DROP COLUMN `day_of_week`,
            MODIFY COLUMN `holiday_date` DATE NOT NULL
        ");
    },

];
