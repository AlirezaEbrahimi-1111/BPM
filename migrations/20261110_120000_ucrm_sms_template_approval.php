<?php
/**
 * CRM جدید — وضعیتِ تأییدِ قالب‌های پیامک در ملی‌پیامک (۱۴۰۵/۰۸/۱۹).
 *
 * قالبِ تازه از CRM مستقیم با REST ملی‌پیامک (ShareService/AddBody) ساخته و برای تأیید فرستاده
 * می‌شود؛ وضعیت با GetBodies خوانده می‌شود (برای قالب‌هایی که دستی در پنل ساخته شده‌اند هم).
 *   approval_status: NULL = هنوز خوانده نشده | 0 در انتظار | 1 تأیید | 2 رد | 3 اصلاح‌شده و در انتظار
 *                    | 4 نیاز به اصلاح | 5 حذف‌شده | 6 رد توسط مدیر
 *   review_note: توضیحِ کارشناسِ ملی‌پیامک. فقط قالبِ «تأییدشده» در ارسال به مشتری دیده می‌شود.
 * منطق: crm-service/ucrm_sms.go + api/ucrm/sms-pattern.php (includes/ucrm-sms-send.php).
 */
return [

    'description' => 'ucrm_sms_templates: Melli Payamak approval status + reviewer note',

    'up' => function (PDO $db) {
        $cols = $db->query("SHOW COLUMNS FROM `ucrm_sms_templates`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('approval_status', $cols, true)) {
            $db->exec("ALTER TABLE `ucrm_sms_templates` ADD COLUMN `approval_status` TINYINT NULL COMMENT 'وضعیت در ملی‌پیامک (BodyStatus)' AFTER `is_active`");
        }
        if (!in_array('review_note', $cols, true)) {
            $db->exec("ALTER TABLE `ucrm_sms_templates` ADD COLUMN `review_note` TEXT NULL COMMENT 'توضیح کارشناس ملی‌پیامک' AFTER `approval_status`");
        }
        if (!in_array('crm_created', $cols, true)) {
            // فقط الگوهایی که خودِ CRM در ملی‌پیامک ساخته، با حذفِ قالب از پنل هم حذف می‌شوند
            $db->exec("ALTER TABLE `ucrm_sms_templates` ADD COLUMN `crm_created` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'الگو را همین CRM در ملی‌پیامک ساخته' AFTER `review_note`");
        }
        if (!in_array('status_checked_at', $cols, true)) {
            $db->exec("ALTER TABLE `ucrm_sms_templates` ADD COLUMN `status_checked_at` DATETIME NULL AFTER `review_note`");
        }
    },

    'down' => function (PDO $db) {
        foreach (['status_checked_at', 'crm_created', 'review_note', 'approval_status'] as $c) {
            $db->exec("ALTER TABLE `ucrm_sms_templates` DROP COLUMN `$c`");
        }
    },

];
