<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: ساخت جدولِ chat_presence — آخرین باری که هر کاربر صفحهٔ چت را باز کرده
 *  تاریخ: ۱۴۰۵/۰۵/۱۴
 * ───────────────────────────────────────────────────────────────────
 *  چرا جدولِ جدا و نه ستون روی users؟
 *    users جدولِ پرمصرف و مشترکِ کل پروژه است؛ اضافه‌کردنِ یک ستونِ
 *    به‌شدت پرنویس (هر بار که کسی صفحهٔ چت را باز می‌کند) به آن، باعثِ
 *    نویزِ غیرضروری روی جدولی می‌شود که جاهای دیگر هم زیاد خوانده می‌شود.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'ساخت جدول chat_presence برای «آخرین بازدید صفحه‌ی چت»',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `chat_presence` (
                `user_id`      INT PRIMARY KEY,
                `last_seen_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `chat_presence`");
    },

];
