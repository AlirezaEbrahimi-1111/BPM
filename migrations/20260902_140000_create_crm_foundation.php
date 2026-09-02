<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  بسترِ دیتابیسِ ماژولِ CRM.
 * ───────────────────────────────────────────────────────────────────
 *  فقط جدول‌ها ساخته می‌شوند — هیچ صفحه/APIِ PHPِ جدیدی به این مهاجرت
 *  وابسته نیست. منطقِ CRM در سرویسِ جدا (crm-service/, نوشته‌شده با Go)
 *  خواهد بود و همین جدول‌ها را می‌خواند/می‌نویسد.
 *
 *  همه‌ی جدول‌ها با organization_id (جداسازیِ سازمان‌ها).
 *  کلیدِ خارجیِ سخت عمداً گذاشته نشده — هم‌راستا با بقیه‌ی مهاجرت‌های پروژه
 *  (فقط ایندکس). این مهاجرت روی اپِ فعلی هیچ اثری ندارد جز یک ستونِ
 *  جدیدِ users با پیش‌فرضِ ۰.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'CRM module foundation: crm_* tables + users.is_sales_manager',

    'up' => function (PDO $db) {

        // ── نقشِ «مدیر فروش» (تجزیه‌ی نقشِ درشتِ manager) ──
        // نقشِ فعلیِ manager دست نمی‌خورد؛ این فقط یک سوییچِ فردیِ اضافه است.
        $col = $db->query("SHOW COLUMNS FROM `users` LIKE 'is_sales_manager'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE `users`
                       ADD COLUMN `is_sales_manager` TINYINT(1) NOT NULL DEFAULT 0
                       COMMENT 'مدیرِ فروش — تأییدِ تخصیصِ کارشناس، تنظیمِ تارگت، گزارش‌های فروش'");
        }

        // ── مشتری: حقیقی یا حقوقی، هر مشتری فقط یک کارشناسِ فروش ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `crm_customers` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `type`            ENUM('individual','legal') NOT NULL DEFAULT 'legal' COMMENT 'حقیقی / حقوقی',
                `name`            VARCHAR(255) NOT NULL,
                `phone`           VARCHAR(30)  NULL,
                `mobile`          VARCHAR(30)  NULL,
                `email`           VARCHAR(190) NULL,
                `address`         TEXT NULL,
                `national_id`     VARCHAR(20) NULL COMMENT 'کد ملی (حقیقی) یا شناسه ملی (حقوقی)',
                `economic_code`   VARCHAR(20) NULL COMMENT 'کد اقتصادی',
                `assigned_rep_id` INT NULL COMMENT 'کارشناسِ فروشِ مسئول (users.id)',
                `external_source` VARCHAR(40) NULL COMMENT 'نامِ نرم‌افزارِ حسابداریِ مبدأ (فعلاً خالی)',
                `external_ref`    VARCHAR(80) NULL COMMENT 'شناسه‌ی مشتری در آن نرم‌افزار (فعلاً خالی)',
                `note`            TEXT NULL,
                `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
                `created_by`      INT NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_crm_cust_org`  (`organization_id`, `is_deleted`),
                KEY `idx_crm_cust_rep`  (`assigned_rep_id`),
                KEY `idx_crm_cust_ext`  (`external_source`, `external_ref`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── درخواستِ تخصیصِ کارشناس (منتظرِ تأییدِ مدیرِ فروش) ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `crm_assignment_requests` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `customer_id`     INT NOT NULL,
                `proposed_rep_id` INT NOT NULL,
                `requested_by`    INT NOT NULL,
                `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                `decided_by`      INT NULL,
                `decided_at`      DATETIME NULL,
                `note`            VARCHAR(500) NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_crm_asgn_org`    (`organization_id`, `status`),
                KEY `idx_crm_asgn_cust`   (`customer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── تارگت / سقف / کف فروش برای هر مشتری در هر دوره (ماهِ شمسی) ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `crm_targets` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `customer_id`     INT NOT NULL,
                `period_ym`       CHAR(7) NOT NULL COMMENT 'ماهِ شمسی مثل 1405-06',
                `target_amount`   BIGINT NOT NULL DEFAULT 0 COMMENT 'ریال',
                `floor_amount`    BIGINT NOT NULL DEFAULT 0,
                `ceiling_amount`  BIGINT NOT NULL DEFAULT 0,
                `set_by`          INT NULL,
                `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_crm_target` (`customer_id`, `period_ym`),
                KEY `idx_crm_target_org` (`organization_id`, `period_ym`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── برنامه‌ی پیگیری: یا روزِ مشخصِ هفته، یا هر N روز ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `crm_followups` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `customer_id`     INT NOT NULL,
                `kind`            ENUM('weekday','interval') NOT NULL,
                `weekday`         TINYINT NULL COMMENT '0=شنبه .. 6=جمعه (وقتی kind=weekday)',
                `interval_days`   INT NULL COMMENT 'هر چند روز (وقتی kind=interval)',
                `note`            VARCHAR(500) NULL,
                `next_due_date`   DATE NULL,
                `last_done_at`    DATETIME NULL,
                `set_by`          INT NULL,
                `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_crm_followup_cust` (`customer_id`),
                KEY `idx_crm_followup_due` (`organization_id`, `next_due_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── تایم‌لاینِ فعالیت (تماس، بازدید، یادداشت، پیامک) ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `crm_activities` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `customer_id`     INT NOT NULL,
                `kind`            ENUM('call','visit','note','sms','system') NOT NULL DEFAULT 'note',
                `body`            TEXT NULL,
                `by_user_id`      INT NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_crm_act_cust` (`customer_id`, `created_at`),
                KEY `idx_crm_act_org`  (`organization_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── قالب‌های پیامک ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `crm_sms_templates` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `title`           VARCHAR(120) NOT NULL,
                `body`            TEXT NOT NULL,
                `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
                `created_by`      INT NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_crm_smstpl_org` (`organization_id`, `is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── تاریخچه‌ی پیامک‌های ارسالی به مشتری ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `crm_sms_log` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `customer_id`     INT NOT NULL,
                `template_id`     INT NULL,
                `phone`           VARCHAR(30) NOT NULL,
                `body`            TEXT NOT NULL,
                `status`          VARCHAR(30) NOT NULL DEFAULT 'queued',
                `sent_by`         INT NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_crm_smslog_cust` (`customer_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── پیش‌فاکتور (داخلِ همین CRM ساخته می‌شود) ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `crm_proformas` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `customer_id`     INT NOT NULL,
                `number`          VARCHAR(40) NULL,
                `status`          ENUM('open','done','cancelled') NOT NULL DEFAULT 'open',
                `subtotal_amount` BIGINT NOT NULL DEFAULT 0,
                `discount_amount` BIGINT NOT NULL DEFAULT 0,
                `tax_amount`      BIGINT NOT NULL DEFAULT 0,
                `total_amount`    BIGINT NOT NULL DEFAULT 0,
                `followup_date`   DATE NULL,
                `note`            TEXT NULL,
                `created_by`      INT NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_crm_pf_cust`   (`customer_id`, `status`),
                KEY `idx_crm_pf_org`    (`organization_id`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `crm_proforma_items` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `proforma_id` INT NOT NULL,
                `title`       VARCHAR(255) NOT NULL,
                `qty`         DECIMAL(14,3) NOT NULL DEFAULT 1,
                `unit_price`  BIGINT NOT NULL DEFAULT 0,
                `discount`    BIGINT NOT NULL DEFAULT 0,
                `line_total`  BIGINT NOT NULL DEFAULT 0,
                `sort_order`  INT NOT NULL DEFAULT 0,
                KEY `idx_crm_pfitem_pf` (`proforma_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── فروشِ واقعی (تا قبل از اتصالِ حسابداری: دستی یا اکسل) ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `crm_actual_sales` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `customer_id`     INT NOT NULL,
                `period_ym`       CHAR(7) NOT NULL COMMENT 'ماهِ شمسی مثل 1405-06',
                `amount`          BIGINT NOT NULL DEFAULT 0 COMMENT 'ریال',
                `source`          ENUM('manual','excel','accounting') NOT NULL DEFAULT 'manual',
                `note`            VARCHAR(255) NULL,
                `entered_by`      INT NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_crm_actual_cust` (`customer_id`, `period_ym`),
                KEY `idx_crm_actual_org`  (`organization_id`, `period_ym`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── کانفیگِ اتصالِ حسابداریِ هر سازمان (فعلاً خالی، برای توسعه‌ی بعدی) ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `crm_org_accounting_config` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `provider`        VARCHAR(40) NULL COMMENT 'مثل shaygan',
                `base_url`        VARCHAR(255) NULL,
                `database_name`   VARCHAR(120) NULL,
                `auth_mode`       VARCHAR(40) NULL,
                `auth_user`       VARCHAR(120) NULL,
                `auth_pass`       VARCHAR(255) NULL,
                `extra`           TEXT NULL COMMENT 'JSON — تنظیماتِ اضافه',
                `is_enabled`      TINYINT(1) NOT NULL DEFAULT 0,
                `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_crm_acc_org` (`organization_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $db) {
        foreach ([
            'crm_org_accounting_config',
            'crm_actual_sales',
            'crm_proforma_items',
            'crm_proformas',
            'crm_sms_log',
            'crm_sms_templates',
            'crm_activities',
            'crm_followups',
            'crm_targets',
            'crm_assignment_requests',
            'crm_customers',
        ] as $t) {
            $db->exec("DROP TABLE IF EXISTS `{$t}`");
        }

        $col = $db->query("SHOW COLUMNS FROM `users` LIKE 'is_sales_manager'")->fetch();
        if ($col) {
            $db->exec("ALTER TABLE `users` DROP COLUMN `is_sales_manager`");
        }
    },

];
