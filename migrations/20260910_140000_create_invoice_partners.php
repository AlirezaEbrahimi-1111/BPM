<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  «همکار» در فاکتور رسمی + سهمِ سودِ ماهانه (شمسی) + ستون‌های گزارش
 * ───────────────────────────────────────────────────────────────────
 *  بعضی فاکتورهای رسمی به‌درخواستِ یک «شرکتِ همکار» صادر می‌شوند: همکار
 *  مشتریِ خودش را دارد و فاکتور از طریقِ ما صادر می‌شود. همکار در ازای هر
 *  فاکتور، درصدی از «مبلغِ پیش از مالیاتِ» فاکتور را به‌عنوان سهمِ سود
 *  می‌گیرد. این درصد برای هر همکار در هر «ماهِ شمسی» جداگانه تعیین می‌شود
 *  و «زنده» است: تغییرِ درصدِ یک ماه، سودِ همهٔ فاکتورهای همان ماه (صادر‌شده
 *  و بعدی) را بازمحاسبه می‌کند — چیزی snapshot نمی‌شود.
 *
 *  مثال: فاکتور ۱٬۰۰۰٬۰۰۰ ت، مالیاتِ ۱۰٪ = ۱۰۰٬۰۰۰ ت. سهمِ ۳٪:
 *        ۳٪ × ۱٬۰۰۰٬۰۰۰ = ۳۰٬۰۰۰ ت  (۳٪ از مبلغِ پیش از مالیات).
 *
 *  جدول‌ها:
 *    • inv_partners             — فهرستِ همکاران (فعلاً «شخص»).
 *    • inv_partner_month_share  — درصدِ سهمِ هر همکار در هر (سالِ شمسی، ماه).
 *
 *  ستون‌های تازهٔ inv_invoices (همه با پیش‌فرضِ بی‌اثر):
 *    • partner_id                — همکار (NULL = فاکتور به‌درخواستِ خودِ شرکت).
 *    • partner_profit_recorded   — تیکِ «سود در حسابداری شایگان ثبت شد».
 *    • settlement_status         — تسویه: unsettled | settled | partial.
 *    • moadian_status            — مودیان: unregistered | registered.
 *    • moadian_code              — کدِ ثبت در سامانهٔ مودیان (وقتی registered).
 *
 *  منطق در سرویسِ Go است (crm-service/partners.go). این مهاجرت فقط اسکیما.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'Invoice partners + monthly Jalali profit-share % + invoice partner/settlement/moadian columns',

    'up' => function (PDO $db) {

        // ── همکاران ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_partners` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL DEFAULT 1,
                `name`            VARCHAR(255) NOT NULL,
                `phone`           VARCHAR(30)  NULL,
                `note`            TEXT NULL,
                `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
                `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
                `created_by`      INT NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_inv_partner` (`organization_id`, `is_deleted`, `name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── درصدِ سهمِ سود در هر ماهِ شمسی ──
        // percent = درصدِ سهم از «مبلغِ پیش از مالیات» (subtotal - discount).
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_partner_month_share` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `partner_id` INT NOT NULL,
                `jy`         SMALLINT NOT NULL COMMENT 'سالِ شمسی، مثلاً 1405',
                `jm`         TINYINT  NOT NULL COMMENT 'ماهِ شمسی 1..12',
                `percent`    DECIMAL(6,3) NOT NULL DEFAULT 0 COMMENT 'درصدِ سهم از مبلغِ پیش از مالیات',
                `updated_by` INT NULL,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_partner_month` (`partner_id`, `jy`, `jm`),
                KEY `idx_pms_partner` (`partner_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── ستون‌های تازهٔ inv_invoices ──
        $add = function (string $col, string $ddl) use ($db) {
            $has = $db->query("SHOW COLUMNS FROM `inv_invoices` LIKE " . $db->quote($col))->fetch();
            if (!$has) {
                $db->exec("ALTER TABLE `inv_invoices` ADD COLUMN $ddl");
            }
        };

        $add('partner_id',
            "`partner_id` INT NULL COMMENT 'inv_partners.id — NULL = فاکتورِ خودِ شرکت' AFTER `customer_id`");
        $add('partner_profit_recorded',
            "`partner_profit_recorded` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'سودِ همکار در حسابداری شایگان ثبت شد'");
        $add('settlement_status',
            "`settlement_status` ENUM('unsettled','settled','partial') NOT NULL DEFAULT 'unsettled' COMMENT 'وضعیتِ تسویهٔ فاکتور'");
        $add('moadian_status',
            "`moadian_status` ENUM('unregistered','registered') NOT NULL DEFAULT 'unregistered' COMMENT 'وضعیتِ مودیان'");
        $add('moadian_code',
            "`moadian_code` VARCHAR(60) NULL COMMENT 'کدِ ثبت در سامانهٔ مودیان'");

        $hasIdx = $db->query("SHOW INDEX FROM `inv_invoices` WHERE Key_name = 'idx_inv_inv_partner'")->fetch();
        if (!$hasIdx) {
            $db->exec("ALTER TABLE `inv_invoices` ADD KEY `idx_inv_inv_partner` (`partner_id`)");
        }
    },

    'down' => function (PDO $db) {

        foreach ([
            'idx_inv_inv_partner' => "ALTER TABLE `inv_invoices` DROP INDEX `idx_inv_inv_partner`",
        ] as $idx => $sql) {
            $has = $db->query("SHOW INDEX FROM `inv_invoices` WHERE Key_name = " . $db->quote($idx))->fetch();
            if ($has) {
                $db->exec($sql);
            }
        }

        foreach (['partner_id', 'partner_profit_recorded', 'settlement_status', 'moadian_status', 'moadian_code'] as $col) {
            $has = $db->query("SHOW COLUMNS FROM `inv_invoices` LIKE " . $db->quote($col))->fetch();
            if ($has) {
                $db->exec("ALTER TABLE `inv_invoices` DROP COLUMN `$col`");
            }
        }

        $db->exec("DROP TABLE IF EXISTS `inv_partner_month_share`");
        $db->exec("DROP TABLE IF EXISTS `inv_partners`");
    },

];
