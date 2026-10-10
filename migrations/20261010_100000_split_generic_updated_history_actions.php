<?php
/**
 * جداکردن رویدادهایی که با action عمومی 'updated' در task_history ثبت می‌شدند.
 *
 * برچسب 'updated' در تاریخچهٔ کار «یادآوری» است (اولین استفاده‌اش دکمهٔ یادآوری
 * بود)، ولی چند رویداد دیگر هم همین action را می‌نوشتند و همه «یادآوری» دیده
 * می‌شدند:
 *   • رفع دوره‌های معوقه (تأیید / تأیید خودکار)  → overdue_cleared
 *   • حذف موعد کار                               → due_date_cleared
 *   • ویرایش مشخصات کار                          → task_edited
 * و دو رویدادی که اصلا ثبت نمی‌شدند:
 *   • ثبت درخواست رفع معوقه                      → overdue_clear_requested
 *   • رد درخواست رفع معوقه                       → overdue_clear_rejected
 *
 * این مایگریشن (۱) پنج مقدار را به ENUM اضافه می‌کند و (۲) ردیف‌های قدیمی را
 * از روی متن notes به نوع درست منتقل می‌کند. متن notes دست نمی‌خورد.
 *
 * ردیف‌های «رفع معوقه تأیید شد» قبلا from_user = درخواست‌دهنده و
 * to_user = تأییدکننده داشتند (برعکس واقعیت)؛ این‌جا جابه‌جا می‌شوند تا مثل
 * ردیف‌های جدید from_user = تأییدکننده باشد.
 *
 * فهرست ENUM هاردکد نشده: تعریف فعلی ستون خوانده و فقط مقدارهای جدید به
 * انتهایش اضافه می‌شود.
 */

if (!function_exists('taskHistoryActionEnumValues')) {
    /** مقادیر فعلی ENUM ستون action، به همان ترتیب */
    function taskHistoryActionEnumValues(PDO $db): array
    {
        $col = $db->query("SHOW COLUMNS FROM `task_history` LIKE 'action'")->fetch(PDO::FETCH_ASSOC);
        if (!$col || stripos($col['Type'], 'enum(') !== 0) {
            throw new RuntimeException('task_history.action is not an ENUM column');
        }
        preg_match_all("/'((?:[^'\\\\]|\\\\.|'')*)'/", $col['Type'], $m);
        return $m[1];
    }

    function taskHistorySetActionEnum(PDO $db, array $values): void
    {
        $list = implode(',', array_map(fn($v) => $db->quote($v), $values));
        $db->exec("ALTER TABLE `task_history` MODIFY COLUMN `action` ENUM({$list}) NOT NULL");
    }
}

$splitUpdatedNewActions = [
    'task_edited', 'due_date_cleared',
    'overdue_clear_requested', 'overdue_cleared', 'overdue_clear_rejected',
];

return [

    'description' => 'Give overdue-clear, due-date-clear and task-edit history rows their own action instead of the generic "updated"',

    'up' => function (PDO $db) use ($splitUpdatedNewActions) {
        $values = taskHistoryActionEnumValues($db);
        $missing = array_values(array_diff($splitUpdatedNewActions, $values));
        if ($missing) {
            taskHistorySetActionEnum($db, array_merge($values, $missing));
        }

        // رفع معوقهٔ تأییدشده: نوع جدید + جابه‌جایی from/to (تأییدکننده = from)
        $db->exec("
            UPDATE `task_history` h
            JOIN (SELECT `id`, `from_user_id` AS f, `to_user_id` AS t
                  FROM `task_history`
                  WHERE `action` = 'updated'
                    AND `notes` LIKE 'رفع دوره%معوقه%تأیید شد%'
                    AND `to_user_id` IS NOT NULL) s ON s.id = h.id
            SET h.`action` = 'overdue_cleared', h.`from_user_id` = s.t, h.`to_user_id` = s.f
        ");
        // بقیهٔ ردیف‌های رفع معوقه (تأیید خودکار: from = to)
        $db->exec("
            UPDATE `task_history` SET `action` = 'overdue_cleared'
            WHERE `action` = 'updated' AND `notes` LIKE 'رفع دوره%معوقه%'
        ");
        $db->exec("
            UPDATE `task_history` SET `action` = 'due_date_cleared'
            WHERE `action` = 'updated' AND `notes` LIKE 'موعد%انجام حذف شد%'
        ");
        $db->exec("
            UPDATE `task_history` SET `action` = 'task_edited'
            WHERE `action` = 'updated' AND `notes` LIKE 'کار ب%روزرسانی شد%'
        ");
    },

    'down' => function (PDO $db) use ($splitUpdatedNewActions) {
        $values = taskHistoryActionEnumValues($db);
        if (!array_intersect($splitUpdatedNewActions, $values)) {
            return;
        }
        // ردیف‌های منتقل‌شده به حالت قبل؛ جابه‌جایی from/to فقط برای ردیف‌های قدیمی
        // «تأیید شد» (ردیف‌های ساخته‌شده با کد جدید متن دیگری دارند و همان‌طور می‌مانند)
        $db->exec("
            UPDATE `task_history` h
            JOIN (SELECT `id`, `from_user_id` AS f, `to_user_id` AS t
                  FROM `task_history`
                  WHERE `action` = 'overdue_cleared'
                    AND `notes` LIKE 'رفع دوره%معوقه%تأیید شد%'
                    AND `to_user_id` IS NOT NULL) s ON s.id = h.id
            SET h.`from_user_id` = s.t, h.`to_user_id` = s.f
        ");
        $db->exec("
            UPDATE `task_history` SET `action` = 'updated'
            WHERE `action` IN ('overdue_cleared', 'due_date_cleared', 'task_edited')
        ");
        // این دو نوع قبلا اصلا ثبت نمی‌شدند و بدون مقدار ENUM نمی‌توانند بمانند
        $db->exec("DELETE FROM `task_history` WHERE `action` IN ('overdue_clear_requested', 'overdue_clear_rejected')");
        taskHistorySetActionEnum($db, array_values(array_diff($values, $splitUpdatedNewActions)));
    },

];
