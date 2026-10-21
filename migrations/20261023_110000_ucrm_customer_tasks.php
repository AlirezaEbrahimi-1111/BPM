<?php
/**
 * CRM جدید — اتصال «تسک تأیید» به مشتری.
 *
 * هر مرحلهٔ مسیر تأیید مشتری یک تسک واقعی در «کارهای من» می‌سازد
 * (api/ucrm/notify-events.php):
 *   manager    → برای هر سرپرست یک نسخه «تأیید انتساب مشتری: X» (در انتظار تأیید)
 *   accounting → برای هر عضو حسابداری یک نسخه «تأیید حسابداری مشتری: X» (در انتظار تأیید)
 *   rep_fix    → برای کارشناس «اصلاح مشتری: X» بعد از رد
 * وقتی یک نفر اقدام کرد، بقیهٔ نسخه‌های همان مرحله بسته می‌شوند (closed_at).
 * کلیک روی این تسک‌ها صفحهٔ pages/ucrm-approval.php را باز می‌کند.
 *
 * فقط PHP این جدول را می‌خواند/می‌نویسد — GRANT برای crm_service لازم نیست.
 *
 * + تارگت ماهانه (تومان) روی خود مشتری:
 *   proposed_target = پیشنهاد کارشناس هنگام ثبت
 *   monthly_target  = تارگتی که مدیر هنگام تأیید ثبت می‌کند (پیش‌فرض همان پیشنهاد)
 *   (ستون جدید در ucrm_customers — GRANT جدولی crm_service همین ستون‌ها را هم پوشش می‌دهد)
 */
return [

    'description' => 'New CRM: ucrm_customer_tasks (approval steps as real tasks) + proposed/monthly target',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_customer_tasks` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `customer_id` INT NOT NULL,
                `task_id`     INT NOT NULL,
                `stage`       VARCHAR(20) NOT NULL COMMENT 'manager | accounting | rep_fix',
                `user_id`     INT NOT NULL COMMENT 'مسئول همین نسخه از تسک',
                `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `closed_at`   DATETIME NULL,
                UNIQUE KEY `uq_ucrm_ct_task` (`task_id`),
                KEY `idx_ucrm_ct_cust` (`customer_id`, `stage`, `closed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $cols = $db->query("SHOW COLUMNS FROM `ucrm_customers`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('proposed_target', $cols, true)) {
            $db->exec("ALTER TABLE `ucrm_customers`
                       ADD COLUMN `proposed_target` BIGINT NULL COMMENT 'تارگت ماهانهٔ پیشنهادی کارشناس (تومان)' AFTER `note`");
        }
        if (!in_array('monthly_target', $cols, true)) {
            $db->exec("ALTER TABLE `ucrm_customers`
                       ADD COLUMN `monthly_target` BIGINT NULL COMMENT 'تارگت ماهانهٔ تأییدشدهٔ مدیر (تومان)' AFTER `proposed_target`");
        }
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `ucrm_customer_tasks`");
        $cols = $db->query("SHOW COLUMNS FROM `ucrm_customers`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('monthly_target', $cols, true)) $db->exec("ALTER TABLE `ucrm_customers` DROP COLUMN `monthly_target`");
        if (in_array('proposed_target', $cols, true)) $db->exec("ALTER TABLE `ucrm_customers` DROP COLUMN `proposed_target`");
    },

];
