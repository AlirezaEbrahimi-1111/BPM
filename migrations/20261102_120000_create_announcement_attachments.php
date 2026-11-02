<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: جدول announcement_attachments — فایل‌های پیوست اطلاعیه‌ها
 *  تاریخ: ۱۴۰۵/۰۸/۱۱
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'ساخت جدول announcement_attachments',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `announcement_attachments` (
                `id`              INT(11) NOT NULL AUTO_INCREMENT,
                `announcement_id` INT(11) NOT NULL,
                `uploaded_by`     INT(11) NOT NULL,
                `stored_name`     VARCHAR(64) NOT NULL,
                `original_name`   VARCHAR(255) NOT NULL,
                `file_size`       BIGINT NOT NULL,
                `mime_type`       VARCHAR(127) NOT NULL,
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_announcement` (`announcement_id`),
                CONSTRAINT `fk_ann_att_announcement` FOREIGN KEY (`announcement_id`)
                    REFERENCES `announcements` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `announcement_attachments`");
    },

];
