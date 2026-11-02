<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودن pinned_at به chat_participants — سنجاق شخصی گفتگو در لیست
 *  تاریخ: ۱۴۰۵/۰۸/۱۱
 * ───────────────────────────────────────────────────────────────────
 *  دقیقا مثل is_muted: تنظیمی شخصی و فقط برای همین کاربر. NULL یعنی
 *  سنجاق نشده؛ زمان سنجاق ترتیب سنجاق‌شده‌ها را هم مشخص می‌کند.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودن ستون pinned_at به chat_participants',

    'up' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_participants` LIKE 'pinned_at'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE `chat_participants` ADD COLUMN `pinned_at` DATETIME NULL DEFAULT NULL AFTER `is_muted`");
        }
    },

    'down' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_participants` LIKE 'pinned_at'");
        if ($stmt->fetch()) {
            $db->exec("ALTER TABLE `chat_participants` DROP COLUMN `pinned_at`");
        }
    },

];
