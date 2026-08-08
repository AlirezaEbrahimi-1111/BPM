<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودنِ ستونِ edited_at به chat_messages — برایِ ویرایشِ پیام
 *  تاریخ: ۱۴۰۵/۰۵/۱۵
 * ───────────────────────────────────────────────────────────────────
 *  NULL یعنی هیچ‌وقت ویرایش نشده؛ مقدار داشتن یعنی باید برچسبِ
 *  «ویرایش‌شده» کنارِ پیام نمایش داده شود (مثلِ تلگرام/واتساپ).
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودن ستون edited_at به chat_messages برای پشتیبانی از ویرایشِ پیام',

    'up' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_messages` LIKE 'edited_at'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE `chat_messages` ADD COLUMN `edited_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_deleted`");
        }
    },

    'down' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_messages` LIKE 'edited_at'");
        if ($stmt->fetch()) {
            $db->exec("ALTER TABLE `chat_messages` DROP COLUMN `edited_at`");
        }
    },

];
