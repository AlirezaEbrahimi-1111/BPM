<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودن is_muted به chat_participants — بی‌صداکردن شخصی گفتگو
 *  تاریخ: ۱۴۰۵/۰۵/۱۵
 * ───────────────────────────────────────────────────────────────────
 *  دقیقا مثل is_archived: تنظیمی شخصی و فقط برای همین کاربر، بدون
 *  اثر روی طرف مقابل یا بقیه‌ی اعضای گروه.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودن ستون is_muted به chat_participants',

    'up' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_participants` LIKE 'is_muted'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE `chat_participants` ADD COLUMN `is_muted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_archived`");
        }
    },

    'down' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_participants` LIKE 'is_muted'");
        if ($stmt->fetch()) {
            $db->exec("ALTER TABLE `chat_participants` DROP COLUMN `is_muted`");
        }
    },

];
