<?php
/**
 * افزودن deadline_requested به ENUM ستون task_history.action
 *
 * از این نسخه، ثبت «درخواست تمدید موعد» هم یک ردیف در تاریخچهٔ کار می‌گذارد
 * (api/tasks/request-deadline.php) تا معلوم باشد چه کسی و کِی درخواست داده.
 * قبلا فقط نتیجه (deadline_extended / deadline_rejected) ثبت می‌شد.
 *
 * فهرست مقادیر ENUM هاردکد نشده: تعریف فعلی ستون خوانده می‌شود و فقط مقدار
 * جدید به انتهایش اضافه می‌شود — تا اگر دیتابیسی مقدار بیشتری داشت، چیزی از
 * قلم نیفتد.
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

    'description' => 'Add deadline_requested to the task_history.action ENUM',

    'up' => function (PDO $db) {
        $values = taskHistoryActionEnumValues($db);
        if (!in_array('deadline_requested', $values, true)) {
            $values[] = 'deadline_requested';
            taskHistorySetActionEnum($db, $values);
        }
    },

    'down' => function (PDO $db) {
        $values = taskHistoryActionEnumValues($db);
        if (in_array('deadline_requested', $values, true)) {
            // ردیف‌های این نوع بدون مقدار ENUM نمی‌توانند بمانند
            $db->exec("DELETE FROM `task_history` WHERE `action` = 'deadline_requested'");
            taskHistorySetActionEnum($db, array_values(array_diff($values, ['deadline_requested'])));
        }
    },

];
