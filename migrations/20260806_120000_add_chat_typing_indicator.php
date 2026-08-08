<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودنِ typing_until به chat_participants — نشانگرِ «در حالِ تایپ»
 *  تاریخ: ۱۴۰۵/۰۵/۱۶
 * ───────────────────────────────────────────────────────────────────
 *  هر بار کاربر توی کادرِ نوشتن تایپ می‌کند، این مقدار به NOW()+۵ثانیه
 *  ست می‌شود. طرفِ مقابل با polling چک می‌کند اگر این مقدار هنوز در
 *  آینده باشد یعنی «هنوز دارد تایپ می‌کند». نیازی به وب‌سوکت نیست —
 *  همون polling ساده‌ی موجودِ پروژه کافیه، فقط با بازه‌ی کوتاه‌تر.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودن ستون typing_until به chat_participants برای نشانگرِ در حالِ تایپ',

    'up' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_participants` LIKE 'typing_until'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE `chat_participants` ADD COLUMN `typing_until` DATETIME NULL DEFAULT NULL AFTER `last_read_message_id`");
        }
    },

    'down' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_participants` LIKE 'typing_until'");
        if ($stmt->fetch()) {
            $db->exec("ALTER TABLE `chat_participants` DROP COLUMN `typing_until`");
        }
    },

];
