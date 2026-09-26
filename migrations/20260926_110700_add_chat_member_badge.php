<?php
/**
 * بج «مدیر» به‌عنوان یک فلگ کاملا مستقل از role/permissions — طبق
 * درخواست صریح، باید برای هر عضو گروه (نه فقط مدیرها) قابل‌فعال‌سازی
 * باشه، بدون اینکه هیچ اختیار واقعی‌ای بده یا بگیره. همین‌جا یه ستون
 * جدا داره تا هیچ‌وقت با سیستم اختیارات واقعی مدیرها قاطی نشه.
 */

return [

    'description' => 'Add show_admin_badge column to chat_participants — a purely cosmetic per-member flag, independent of role/permissions',

    'up' => function (PDO $db) {
        $db->exec("
            ALTER TABLE `chat_participants`
            ADD COLUMN `show_admin_badge` TINYINT(1) NOT NULL DEFAULT 0 AFTER `permissions`
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("ALTER TABLE `chat_participants` DROP COLUMN `show_admin_badge`");
    },

];
