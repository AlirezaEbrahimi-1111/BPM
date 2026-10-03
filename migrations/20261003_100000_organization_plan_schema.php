<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرتِ ساختاری: مدل پلنِ سازمان (free | silver | gold) + تست وب
 *  — فقط ستون‌ها و جدول ممیزی. داده‌ی موجود در مهاجرت بعدی جابه‌جا می‌شود.
 *
 *  ستون plan_type قدیمیِ جدول subscriptions دست‌نخورده می‌ماند.
 *  web_access ذخیره نمی‌شود؛ از روی plan و تاریخ‌ها محاسبه می‌شود.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'Org plan model: plan/plan_users/plan_expires_at/web trial columns on organizations, plan columns on subscriptions, plan audit table',

    'up' => function (PDO $db) {
        $colExists = function (string $table, string $col) use ($db): bool {
            $s = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $s->execute([$table, $col]);
            return (bool) $s->fetchColumn();
        };

        $orgCols = [
            'plan'               => "ADD COLUMN `plan` ENUM('free','silver','gold') NOT NULL DEFAULT 'free' COMMENT 'سطح پلن سازمان؛ منبع حقیقت برای اپ و وب'",
            'plan_users'         => "ADD COLUMN `plan_users` INT NOT NULL DEFAULT 1 COMMENT 'سقف تعداد کاربر طبق پلن'",
            'plan_expires_at'    => "ADD COLUMN `plan_expires_at` DATE NULL COMMENT 'تاریخ انقضا؛ NULL = دائمی (فقط برای gold)'",
            'web_trial_ends_at'  => "ADD COLUMN `web_trial_ends_at` DATE NULL COMMENT 'پایان تست ۱۴ روزه‌ی وب؛ NULL = تست فعال نیست'",
            'web_trial_used'     => "ADD COLUMN `web_trial_used` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'تست وب فقط یک‌بار برای هر سازمان'",
            'plan_source'        => "ADD COLUMN `plan_source` ENUM('legacy_web_default','app_purchase','web_purchase','admin_manual') NULL COMMENT 'منبع آخرین تغییرِ پلن'",
        ];
        foreach ($orgCols as $col => $ddl) {
            if (!$colExists('organizations', $col)) {
                $db->exec("ALTER TABLE `organizations` $ddl");
            }
        }

        $subCols = [
            'plan'            => "ADD COLUMN `plan` ENUM('silver','gold') NULL COMMENT 'سطحِ این خرید/تمدید'",
            'plan_users'      => "ADD COLUMN `plan_users` INT NULL COMMENT 'سقف کاربرِ این خرید'",
            'duration_months' => "ADD COLUMN `duration_months` TINYINT NULL COMMENT '3 | 6 | 12 | NULL = دائمی یا سفارشی'",
        ];
        foreach ($subCols as $col => $ddl) {
            if (!$colExists('subscriptions', $col)) {
                $db->exec("ALTER TABLE `subscriptions` $ddl");
            }
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS `organization_plan_audit` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `old_value`       JSON NULL,
                `new_value`       JSON NULL,
                `source`          ENUM('migration','app_purchase','web_purchase','admin_manual','web_trial','expiry_job') NOT NULL,
                `created_at`      DATETIME NOT NULL COMMENT 'به وقت تهران',
                INDEX `idx_opa_org` (`organization_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `organization_plan_audit`");

        $colExists = function (string $table, string $col) use ($db): bool {
            $s = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $s->execute([$table, $col]);
            return (bool) $s->fetchColumn();
        };
        foreach (['plan', 'plan_users', 'plan_expires_at', 'web_trial_ends_at', 'web_trial_used', 'plan_source'] as $col) {
            if ($colExists('organizations', $col)) $db->exec("ALTER TABLE `organizations` DROP COLUMN `$col`");
        }
        foreach (['plan', 'plan_users', 'duration_months'] as $col) {
            if ($colExists('subscriptions', $col)) $db->exec("ALTER TABLE `subscriptions` DROP COLUMN `$col`");
        }
    },

];
