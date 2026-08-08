<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودنِ عکسِ پروفایل (کاربر) و عکسِ گروه
 *  تاریخ: ۱۴۰۵/۰۵/۱۵
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودن avatar_path به users و chat_conversations',

    'up' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `users` LIKE 'avatar_path'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `avatar_path` VARCHAR(255) NULL DEFAULT NULL AFTER `last_name`");
        }

        $stmt = $db->query("SHOW COLUMNS FROM `chat_conversations` LIKE 'avatar_path'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE `chat_conversations` ADD COLUMN `avatar_path` VARCHAR(255) NULL DEFAULT NULL AFTER `title`");
        }
    },

    'down' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `users` LIKE 'avatar_path'");
        if ($stmt->fetch()) {
            $db->exec("ALTER TABLE `users` DROP COLUMN `avatar_path`");
        }
        $stmt = $db->query("SHOW COLUMNS FROM `chat_conversations` LIKE 'avatar_path'");
        if ($stmt->fetch()) {
            $db->exec("ALTER TABLE `chat_conversations` DROP COLUMN `avatar_path`");
        }
    },

];
