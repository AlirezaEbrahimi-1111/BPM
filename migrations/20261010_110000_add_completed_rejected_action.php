<?php
/**
 * افزودن completed_rejected به ENUM ستون task_history.action
 *
 * کار دوره‌ای: وقتی تکمیل یک دوره برای تأیید می‌رود، همان لحظه یک ردیف
 * 'completed' ثبت می‌شود و موتور دوره (pe_completionDates) دوره را بسته حساب
 * می‌کند. اگر تأییدکننده رد کند، آن ردیف سر جایش می‌ماند و دوره «انجام‌شده»
 * باقی می‌ماند — رد عملا مثل تأیید رفتار می‌کرد. از این نسخه، هنگام رد نوع آن
 * ردیف 'completed_rejected' می‌شود (TaskManager::approveOrRejectTask).
 *
 * ردهای قبلی عمدا دست نمی‌خورند (تصمیم کاربر): اصلاحشان برای بعضی کاربران
 * یک‌باره دورهٔ معوقهٔ قدیمی می‌ساخت.
 *
 * فهرست ENUM هاردکد نشده: تعریف فعلی ستون خوانده و فقط مقدار جدید اضافه می‌شود.
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

    'description' => 'Add completed_rejected to the task_history.action ENUM',

    'up' => function (PDO $db) {
        $values = taskHistoryActionEnumValues($db);
        if (!in_array('completed_rejected', $values, true)) {
            $values[] = 'completed_rejected';
            taskHistorySetActionEnum($db, $values);
        }
    },

    'down' => function (PDO $db) {
        $values = taskHistoryActionEnumValues($db);
        if (in_array('completed_rejected', $values, true)) {
            // ردیف‌ها به نوع قبلی‌شان برمی‌گردند (همان رفتار قبل از این نسخه)
            $db->exec("UPDATE `task_history` SET `action` = 'completed' WHERE `action` = 'completed_rejected'");
            taskHistorySetActionEnum($db, array_values(array_diff($values, ['completed_rejected'])));
        }
    },

];
