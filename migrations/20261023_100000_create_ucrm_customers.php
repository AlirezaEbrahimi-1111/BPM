<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  CRM جدید (حسابداری غیررسمی) — مرحلهٔ ۱: مشتریان.
 * ───────────────────────────────────────────────────────────────────
 *  ⚠️ کاملا جدا از ماژول «فاکتور رسمی» (crm_customers / inv_*): هیچ جدولی
 *  مشترک نیست. همهٔ جدول‌های CRM جدید پیشوند ucrm_ دارند.
 *  منطق در crm-service (Go) زیر /crm/api/ucrm/... است.
 *
 *  مسیر تأیید مشتری:
 *    کارشناس ثبت می‌کند → pending_manager → (مدیر تأیید، شاید با تغییر کارشناس)
 *    → pending_accounting → (حسابداری تأیید) → active
 *    هر ردی (با دلیل اجباری) → rejected → کارشناس اصلاح و دوباره ارسال می‌کند.
 *
 *  ⚠️ روی سرور، یوزر دیتابیس crm_service برای این جدول‌ها GRANT دستی لازم دارد
 *  (crm-service/deploy/crm_service_db_user.sql).
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'New CRM (unofficial accounting) phase 1: ucrm_customers + phones + events + settings',

    'up' => function (PDO $db) {

        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_customers` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `type`            ENUM('individual','legal') NOT NULL DEFAULT 'legal' COMMENT 'حقیقی / حقوقی',
                `name`            VARCHAR(255) NOT NULL,
                `national_id`     VARCHAR(20)  NULL COMMENT 'کد ملی (حقیقی) یا شناسه ملی (حقوقی) — اختیاری، یکتا',
                `province`        VARCHAR(60)  NULL,
                `city`            VARCHAR(80)  NULL,
                `address`         TEXT NULL,
                `note`            TEXT NULL COMMENT 'توضیحات / یادداشت اولیه',
                `rep_id`          INT NULL COMMENT 'کارشناس فروش (users.id)',
                `status`          VARCHAR(30) NOT NULL DEFAULT 'pending_manager'
                                  COMMENT 'pending_manager | pending_accounting | rejected | active',
                `rejected_stage`  VARCHAR(20) NULL COMMENT 'manager | accounting — آخرین رد در کدام مرحله',
                `reject_reason`   TEXT NULL,
                `source`          VARCHAR(20) NOT NULL DEFAULT 'manual' COMMENT 'manual | excel | accounting',
                `external_ref`    VARCHAR(80) NULL COMMENT 'شناسهٔ مشتری در حسابداری (اتصال بعدی)',
                `last_followup_at` DATETIME NULL COMMENT 'آخرین پیگیری — از مرحلهٔ پیگیری‌ها پر می‌شود',
                `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
                `deleted_by`      INT NULL,
                `deleted_at`      DATETIME NULL,
                `created_by`      INT NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_ucrm_cust_org`    (`organization_id`, `is_deleted`, `status`),
                KEY `idx_ucrm_cust_rep`    (`rep_id`),
                KEY `idx_ucrm_cust_nid`    (`organization_id`, `national_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // هر مشتری چند شماره تماس (جست‌وجو روی همه)
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_customer_phones` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `customer_id` INT NOT NULL,
                `phone`       VARCHAR(30) NOT NULL,
                `sort`        INT NOT NULL DEFAULT 0,
                KEY `idx_ucrm_phone_cust`  (`customer_id`, `sort`),
                KEY `idx_ucrm_phone_phone` (`phone`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // تاریخچه (ثبت، تأیید، رد، ارسال دوباره، ویرایش…) — note = جملهٔ کامل فارسی
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_customer_events` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `customer_id` INT NOT NULL,
                `action`      VARCHAR(30) NOT NULL,
                `actor_id`    INT NULL,
                `note`        TEXT NULL,
                `notified_at` DATETIME NULL COMMENT 'اعلانش فرستاده شد (api/ucrm/notify-events.php) — جلوی ارسال دوباره',
                `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_ucrm_ev_cust` (`customer_id`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // تنظیمات CRM هر سازمان — بازه‌های رنگ تارگت (درصد تحقق)
        //   کمتر از target_red_below → قرمز، از target_green_from به بالا → سبز، بین این دو → نارنجی
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ucrm_settings` (
                `organization_id`   INT PRIMARY KEY,
                `target_red_below`  INT NOT NULL DEFAULT 80,
                `target_green_from` INT NOT NULL DEFAULT 100,
                `updated_by`        INT NULL,
                `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $db) {
        $n = (int) $db->query("SELECT COUNT(*) FROM `ucrm_customers`")->fetchColumn();
        if ($n > 0) {
            throw new RuntimeException("{$n} مشتری در ucrm_customers ثبت شده؛ برگشت آن‌ها را پاک می‌کند.");
        }
        foreach (['ucrm_settings', 'ucrm_customer_events', 'ucrm_customer_phones', 'ucrm_customers'] as $t) {
            $db->exec("DROP TABLE IF EXISTS `{$t}`");
        }
    },

];
