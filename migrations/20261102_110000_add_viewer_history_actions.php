<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودن viewer_added / viewer_updated / viewer_removed به ENUM
 *          ستون task_history.action
 *  تاریخ: ۱۴۰۵/۰۸/۱۱
 * ───────────────────────────────────────────────────────────────────
 *  افزودن، تغییر مجوز و حذف دسترسی بیننده‌ها حالا در تاریخچهٔ کار ثبت
 *  می‌شود (includes/task-viewer-history-helper.php). چون دیتابیس STRICT
 *  نیست، بدون این مقدارها action بی‌صدا خالی ذخیره می‌شد و بج ردیف «—»
 *  نمایش داده می‌شد.
 *
 *  فهرست ENUM هاردکد نشده: تعریف فعلی ستون خوانده و فقط مقدارهای جدید اضافه می‌شود.
 * ═══════════════════════════════════════════════════════════════════
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

return [

    'description' => 'افزودن viewer_added/viewer_updated/viewer_removed به ENUM ستون task_history.action',

    'up' => function (PDO $db) {
        $current = taskHistoryActionEnumValues($db);
        $missing = array_diff(['viewer_added', 'viewer_updated', 'viewer_removed'], $current);
        if ($missing) {
            taskHistorySetActionEnum($db, array_merge($current, array_values($missing)));
        }
    },

    'down' => function (PDO $db) {
        $added = ['viewer_added', 'viewer_updated', 'viewer_removed'];
        $db->exec("DELETE FROM `task_history` WHERE `action` IN ('viewer_added','viewer_updated','viewer_removed')");
        taskHistorySetActionEnum($db, array_values(array_diff(taskHistoryActionEnumValues($db), $added)));
    },

];
