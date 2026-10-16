<?php
/**
 * غیرفعال‌سازی سازمان از superAdmin + آزاد کردن شماره/نام‌کاربری کاربرانش.
 *
 * هر بررسی «شمارهٔ تکراری» روی کل جدول users است (بی‌توجه به سازمان، فعال
 * بودن یا حذف)، و ورود هم کاربر را با «username یا phone» پیدا می‌کند. پس
 * برای این‌که کاربرِ یک سازمانِ غیرفعال‌شده (مثلا سازندهٔ آن) بتواند در
 * سازمان دیگری کاربر تازه شود، شماره و نام‌کاربری‌اش «آزاد» می‌شوند:
 *   • released_phone    — شمارهٔ اصلی؛ phone جایش یک مقدار ساختگیِ یکتا
 *                         (x + شناسهٔ ۱۰ رقمی) می‌گیرد که هیچ‌وقت شمارهٔ واقعی نیست
 *                         (ستون phone اجباری است، NULL نمی‌گیرد).
 *   • released_username — نام‌کاربری اصلی؛ username خودش NULL می‌شود.
 *   • org_deactivated   — ۱ = این کاربر «فعال بود» و فقط به‌خاطر غیرفعال‌شدنِ
 *                         سازمان غیرفعال شد → هنگام فعال‌سازی دوبارهٔ سازمان
 *                         فقط همین‌ها دوباره فعال می‌شوند (نه کسی که مدیرش
 *                         قبلا دستی غیرفعالش کرده بود).
 * منطق در api/admin/toggle-organization.php است.
 */

return [

    'description' => 'users — released_phone / released_username / org_deactivated (org deactivation frees phones & usernames)',

    'up' => function (PDO $db) {
        $add = function (string $col, string $ddl) use ($db) {
            $exists = $db->query("SHOW COLUMNS FROM `users` LIKE " . $db->quote($col))->fetch();
            if (!$exists) {
                $db->exec("ALTER TABLE `users` ADD COLUMN {$ddl}");
            }
        };
        $add('released_phone', "`released_phone` VARCHAR(11) NULL COMMENT 'شمارهٔ اصلی وقتی سازمان غیرفعال شد و شماره آزاد شد'");
        $add('released_username', "`released_username` VARCHAR(50) NULL COMMENT 'نام‌کاربری اصلی وقتی سازمان غیرفعال شد'");
        $add('org_deactivated', "`org_deactivated` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '۱ = فعال بود و با غیرفعال‌شدن سازمان غیرفعال شد'");
    },

    'down' => function (PDO $db) {
        // 🔒 اگر کاربری هنوز «آزادشده» است، شمارهٔ واقعی‌اش فقط در released_phone است؛
        // حذف ستون یعنی از دست رفتن همیشگی آن. اول آن سازمان‌ها باید دوباره فعال شوند.
        $hasCol = (bool) $db->query("SHOW COLUMNS FROM `users` LIKE 'released_phone'")->fetch();
        if ($hasCol) {
            $n = (int) $db->query("SELECT COUNT(*) FROM `users` WHERE released_phone IS NOT NULL OR released_username IS NOT NULL")->fetchColumn();
            if ($n > 0) {
                throw new RuntimeException("{$n} کاربر هنوز شماره/نام‌کاربری آزادشده دارند؛ اول سازمان‌هایشان را از superAdmin فعال کنید.");
            }
        }
        foreach (['released_phone', 'released_username', 'org_deactivated'] as $col) {
            $exists = $db->query("SHOW COLUMNS FROM `users` LIKE " . $db->quote($col))->fetch();
            if ($exists) {
                $db->exec("ALTER TABLE `users` DROP COLUMN `{$col}`");
            }
        }
    },

];
