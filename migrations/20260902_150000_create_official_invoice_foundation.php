<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  بستر دیتابیس سیستم «فاکتور رسمی» (درون‌سازمانی — فقط سازمان ۱).
 * ───────────────────────────────────────────────────────────────────
 *  فقط جدول‌ها + دو ردیف seed. هیچ صفحه/API PHP جدیدی به این وابسته
 *  نیست. منطق در سرویس Go (crm-service/) خواهد بود.
 *
 *  • مشتری از CRM به‌اشتراک گذاشته می‌شود (crm_customers) — این‌جا جدول
 *    مشتری جدا ساخته نمی‌شود.
 *  • کالا/انبار جدا از CRM است. دو انبار ثابت: official و crm.
 *  • فاکتور فعلا فقط چاپی (بدون ارسال به سامانه‌ی مودیان)؛ ستون‌ها طوری
 *    چیده شده که بعدا اتصال مودیان بدون تغییر اسکیما ممکن باشد.
 *  • واحد پول: ریال، ذخیره به‌صورت BIGINT.
 *  • اثر روی اپ فعلی: فقط یک ستون جدید users با پیش‌فرض ۰.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'Official-invoice module foundation: inv_* tables + users.is_create_official_invoice (org 1 only)',

    'up' => function (PDO $db) {

        // ── مجوز «ثبت فاکتور رسمی» ──
        $col = $db->query("SHOW COLUMNS FROM `users` LIKE 'is_create_official_invoice'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE `users`
                       ADD COLUMN `is_create_official_invoice` TINYINT(1) NOT NULL DEFAULT 0
                       COMMENT 'اجازهٔ ساخت و تأیید فاکتور رسمی (عملا فقط اعضای سازمان ۱)'");
        }

        // ── کاتالوگ کالا/خدمت ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_products` (
                `id`            INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL DEFAULT 1,
                `code`          VARCHAR(60)  NULL,
                `name`          VARCHAR(255) NOT NULL,
                `unit`          VARCHAR(30)  NOT NULL DEFAULT 'عدد' COMMENT 'واحد سنجش',
                `unit_price`    BIGINT NOT NULL DEFAULT 0 COMMENT 'ریال',
                `is_service`    TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'خدمت (باز هم موجودی دارد، فقط برچسب)',
                `is_tax_exempt` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'معاف از مالیات بر ارزش افزوده',
                `is_deleted`    TINYINT(1) NOT NULL DEFAULT 0,
                `created_by`    INT NULL,
                `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_inv_prod` (`organization_id`, `is_deleted`, `name`),
                KEY `idx_inv_prod_code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── انبارها (دقیقا دو ردیف) ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_warehouses` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `code`       VARCHAR(30) NOT NULL,
                `name`       VARCHAR(120) NOT NULL,
                `kind`       ENUM('official','crm') NOT NULL,
                `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_inv_wh_code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("INSERT IGNORE INTO `inv_warehouses` (`code`,`name`,`kind`) VALUES
            ('official','انبار فاکتور رسمی','official'),
            ('crm','انبار CRM','crm')");

        // ── موجودی کش‌شده به‌ازای (کالا، انبار) ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_stock` (
                `product_id`   INT NOT NULL,
                `warehouse_id` INT NOT NULL,
                `qty`          DECIMAL(16,3) NOT NULL DEFAULT 0,
                `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`product_id`, `warehouse_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── دفتر حرکت انبار (منبع حقیقت موجودی) ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_stock_moves` (
                `id`           INT AUTO_INCREMENT PRIMARY KEY,
                `product_id`   INT NOT NULL,
                `warehouse_id` INT NOT NULL,
                `qty`          DECIMAL(16,3) NOT NULL COMMENT 'مثبت = ورود، منفی = خروج',
                `reason`       ENUM('opening','purchase','invoice','adjustment','return') NOT NULL,
                `ref_type`     VARCHAR(30) NULL,
                `ref_id`       INT NULL,
                `note`         VARCHAR(255) NULL,
                `by_user_id`   INT NULL,
                `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_inv_move_pw`  (`product_id`, `warehouse_id`, `created_at`),
                KEY `idx_inv_move_ref` (`ref_type`, `ref_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── تأمین‌کننده‌ها (CRUD) ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_suppliers` (
                `id`            INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL DEFAULT 1,
                `name`          VARCHAR(255) NOT NULL,
                `phone`         VARCHAR(30) NULL,
                `mobile`        VARCHAR(30) NULL,
                `national_id`   VARCHAR(20) NULL COMMENT 'کد/شناسه ملی',
                `economic_code` VARCHAR(20) NULL,
                `address`       TEXT NULL,
                `note`          TEXT NULL,
                `is_deleted`    TINYINT(1) NOT NULL DEFAULT 0,
                `created_by`    INT NULL,
                `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_inv_sup` (`organization_id`, `is_deleted`, `name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── فاکتور خرید (تأیید = افزایش موجودی) ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_purchase_invoices` (
                `id`                  INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id`     INT NOT NULL DEFAULT 1,
                `supplier_id`         INT NOT NULL,
                `supplier_ref_number` VARCHAR(60) NULL COMMENT 'شماره‌ی فاکتور خود تأمین‌کننده',
                `warehouse_id`        INT NOT NULL,
                `issue_date`          DATE NULL,
                `subtotal_amount`     BIGINT NOT NULL DEFAULT 0,
                `discount_amount`     BIGINT NOT NULL DEFAULT 0,
                `tax_amount`          BIGINT NOT NULL DEFAULT 0,
                `total_amount`        BIGINT NOT NULL DEFAULT 0,
                `status`              ENUM('draft','confirmed','cancelled') NOT NULL DEFAULT 'draft',
                `note`                TEXT NULL,
                `created_by`          INT NULL,
                `confirmed_by`        INT NULL,
                `confirmed_at`        DATETIME NULL,
                `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_inv_pur` (`organization_id`, `status`, `issue_date`),
                KEY `idx_inv_pur_sup` (`supplier_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_purchase_items` (
                `id`                  INT AUTO_INCREMENT PRIMARY KEY,
                `purchase_invoice_id` INT NOT NULL,
                `product_id`          INT NOT NULL,
                `qty`                 DECIMAL(16,3) NOT NULL DEFAULT 1,
                `unit_price`          BIGINT NOT NULL DEFAULT 0,
                `discount`            BIGINT NOT NULL DEFAULT 0,
                `tax_amount`          BIGINT NOT NULL DEFAULT 0,
                `line_total`          BIGINT NOT NULL DEFAULT 0,
                `sort_order`          INT NOT NULL DEFAULT 0,
                KEY `idx_inv_puritem` (`purchase_invoice_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── سربرگ فروشنده (یک ردیف — سازمان ۱) ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_seller` (
                `id`            INT PRIMARY KEY,
                `company_name`  VARCHAR(255) NULL,
                `national_id`   VARCHAR(20) NULL COMMENT 'شناسه ملی',
                `economic_code` VARCHAR(20) NULL COMMENT 'کد اقتصادی',
                `reg_number`    VARCHAR(30) NULL COMMENT 'شماره ثبت',
                `branch_code`   VARCHAR(10) NULL COMMENT 'کد شعبه (برای سامانه‌ی مودیان — بعدا)',
                `address`       TEXT NULL,
                `postal_code`   VARCHAR(15) NULL,
                `phone`         VARCHAR(40) NULL,
                `logo_path`     VARCHAR(255) NULL,
                `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("INSERT IGNORE INTO `inv_seller` (`id`) VALUES (1)");

        // ── تنظیمات (یک ردیف) ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_settings` (
                `id`                  INT PRIMARY KEY,
                `vat_rate`            DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT 'درصد مالیات بر ارزش افزوده',
                `currency`            VARCHAR(10) NOT NULL DEFAULT 'IRR',
                `number_prefix`       VARCHAR(20) NOT NULL DEFAULT '',
                `invoice_footer_note` TEXT NULL,
                `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("INSERT IGNORE INTO `inv_settings` (`id`) VALUES (1)");

        // ── فاکتور رسمی ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_invoices` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL DEFAULT 1,
                `doc_type`        ENUM('official','proforma') NOT NULL DEFAULT 'official',
                `seq_year`        SMALLINT NULL COMMENT 'سال شمسی — مبنای ریست شماره',
                `seq_no`          INT NULL COMMENT 'شماره‌ی ترتیبی از ۱ در هر سال',
                `number`          VARCHAR(40) NULL COMMENT 'شماره‌ی نمایشی',
                `customer_id`     INT NOT NULL COMMENT 'crm_customers.id',
                `warehouse_id`    INT NOT NULL,
                `issue_date`      DATE NULL,
                `status`          ENUM('draft','approved','cancelled') NOT NULL DEFAULT 'draft',
                `source`          ENUM('staff','guest') NOT NULL DEFAULT 'staff',
                `request_id`      INT NULL COMMENT 'اگر از درخواست مهمان تبدیل شده',
                `subtotal_amount` BIGINT NOT NULL DEFAULT 0,
                `discount_amount` BIGINT NOT NULL DEFAULT 0,
                `tax_amount`      BIGINT NOT NULL DEFAULT 0,
                `total_amount`    BIGINT NOT NULL DEFAULT 0,
                `note`            TEXT NULL,
                `created_by`      INT NULL,
                `approved_by`     INT NULL,
                `approved_at`     DATETIME NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_inv_num` (`seq_year`, `seq_no`),
                KEY `idx_inv_inv_cust`   (`customer_id`),
                KEY `idx_inv_inv_status` (`status`, `issue_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_invoice_items` (
                `id`            INT AUTO_INCREMENT PRIMARY KEY,
                `invoice_id`    INT NOT NULL,
                `product_id`    INT NULL COMMENT 'NULL = قلم متنی آزاد',
                `title`         VARCHAR(255) NOT NULL,
                `qty`           DECIMAL(16,3) NOT NULL DEFAULT 1,
                `unit_price`    BIGINT NOT NULL DEFAULT 0,
                `discount`      BIGINT NOT NULL DEFAULT 0,
                `is_tax_exempt` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'اسنپ‌شات',
                `tax_rate`      DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'اسنپ‌شات نرخ در لحظه',
                `tax_amount`    BIGINT NOT NULL DEFAULT 0,
                `line_total`    BIGINT NOT NULL DEFAULT 0,
                `sort_order`    INT NOT NULL DEFAULT 0,
                KEY `idx_inv_item` (`invoice_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── درخواست مهمان (بدون لاگین) — فرم بعدا، الان فقط اسکیما ──
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_invoice_requests` (
                `id`                   INT AUTO_INCREMENT PRIMARY KEY,
                `status`               ENUM('pending','approved','rejected','converted') NOT NULL DEFAULT 'pending',
                `customer_id`          INT NULL COMMENT 'اگر مهمان مشتری موجود را انتخاب کرد',
                `guest_name`           VARCHAR(255) NULL,
                `guest_phone`          VARCHAR(30) NULL,
                `guest_national_id`    VARCHAR(20) NULL,
                `guest_address`        TEXT NULL,
                `note`                 TEXT NULL,
                `submitted_ip`         VARCHAR(45) NULL,
                `reviewed_by`          INT NULL,
                `reviewed_at`          DATETIME NULL,
                `converted_invoice_id` INT NULL,
                `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_inv_req` (`status`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `inv_invoice_request_items` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `request_id` INT NOT NULL,
                `product_id` INT NOT NULL,
                `qty`        DECIMAL(16,3) NOT NULL DEFAULT 1,
                `note`       VARCHAR(255) NULL,
                KEY `idx_inv_reqitem` (`request_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $db) {
        foreach ([
            'inv_invoice_request_items',
            'inv_invoice_requests',
            'inv_invoice_items',
            'inv_invoices',
            'inv_settings',
            'inv_seller',
            'inv_purchase_items',
            'inv_purchase_invoices',
            'inv_suppliers',
            'inv_stock_moves',
            'inv_stock',
            'inv_warehouses',
            'inv_products',
        ] as $t) {
            $db->exec("DROP TABLE IF EXISTS `{$t}`");
        }

        $col = $db->query("SHOW COLUMNS FROM `users` LIKE 'is_create_official_invoice'")->fetch();
        if ($col) {
            $db->exec("ALTER TABLE `users` DROP COLUMN `is_create_official_invoice`");
        }
    },

];
