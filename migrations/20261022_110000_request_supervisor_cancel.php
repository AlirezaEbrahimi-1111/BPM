<?php
/**
 * لغو درخواست‌های حضور و غیاب توسط مسئول (سرپرست سازمان) — ۱۴۰۵/۰۷/۳۰.
 *
 * مسئول می‌تواند هر درخواست (مرخصی/پاس/مأموریت/فراموشی/مشکل فنی) را در هر مرحله، حتی
 * بعد از تأیید، با ذکر دلیل «لغو» کند. ردیف حذف نمی‌شود (برخلاف «حذف»)؛ status = 'cancelled'
 * می‌شود و این سه ستون نگه می‌دارند چه کسی، کِی و چرا — تا در مودال تاریخچه نمایش داده شود.
 * مشکل فنی تا الان مقدار 'cancelled' در status نداشت.
 */

$tables = ['leave_requests', 'pass_requests', 'mission_requests', 'forget_requests', 'technical_issues'];

$hasColumn = function (PDO $db, string $table, string $col): bool {
    $st = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $st->execute([$table, $col]);
    return (bool) $st->fetchColumn();
};

return [

    'description' => 'Attendance requests: cancelled_by / cancelled_at / cancel_reason + technical_issues status cancelled',

    'up' => function (PDO $db) use ($tables, $hasColumn) {
        foreach ($tables as $t) {
            if (!$hasColumn($db, $t, 'cancelled_by')) {
                $db->exec("ALTER TABLE `$t`
                    ADD COLUMN `cancelled_by` INT NULL DEFAULT NULL,
                    ADD COLUMN `cancelled_at` DATETIME NULL DEFAULT NULL,
                    ADD COLUMN `cancel_reason` VARCHAR(500) NULL DEFAULT NULL");
            }
        }
        $col = $db->query("SHOW COLUMNS FROM `technical_issues` LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        if ($col && strpos($col['Type'], "'cancelled'") === false) {
            $db->exec("ALTER TABLE `technical_issues`
                       MODIFY COLUMN `status` ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending'");
        }
    },

    'down' => function (PDO $db) use ($tables, $hasColumn) {
        $n = (int) $db->query("SELECT COUNT(*) FROM technical_issues WHERE status = 'cancelled'")->fetchColumn();
        if ($n > 0) {
            throw new RuntimeException("{$n} مشکل فنیِ لغوشده هست؛ برگرداندن ENUM آن‌ها را خراب می‌کند.");
        }
        $db->exec("ALTER TABLE `technical_issues`
                   MODIFY COLUMN `status` ENUM('pending','approved','rejected') DEFAULT 'pending'");
        foreach ($tables as $t) {
            if ($hasColumn($db, $t, 'cancelled_by')) {
                $db->exec("ALTER TABLE `$t` DROP COLUMN `cancelled_by`, DROP COLUMN `cancelled_at`, DROP COLUMN `cancel_reason`");
            }
        }
    },

];
