<?php
/**
 * دو الگوی تازهٔ تکرار برای کار دوره‌ای (continuous):
 *   weekdays → روزهای خاص هفته، مثلا یکشنبه/دوشنبه/پنجشنبه  (ستون period_weekdays = '0,1,4')
 *   interval → هر N روز یک‌بار، مثلا هر ۹ روز               (ستون period_interval_days = 9)
 *
 * موتور: includes/period-engine.php و go-api/internal/core/periodengine.go
 * (هر دو باید هم‌زمان عوض شوند). فهرست ENUM هاردکد نشده: تعریف فعلی ستون خوانده و فقط
 * مقدارهای جدید اضافه می‌شود — prod در حالت non-strict است و مقدار ناشناختهٔ ENUM بی‌صدا
 * '' ذخیره می‌شد (نگاه کنید به مایگریشن 20261018_100000).
 */

if (!function_exists('taskPeriodTypeEnumValues')) {
    function taskPeriodTypeEnumValues(PDO $db): array
    {
        $col = $db->query("SHOW COLUMNS FROM `tasks` LIKE 'period_type'")->fetch(PDO::FETCH_ASSOC);
        if (!$col || stripos($col['Type'], 'enum(') !== 0) {
            throw new RuntimeException('tasks.period_type is not an ENUM column');
        }
        preg_match_all("/'((?:[^'\\\\]|\\\\.|'')*)'/", $col['Type'], $m);
        return $m[1];
    }

    function taskPeriodTypeSetEnum(PDO $db, array $values): void
    {
        $list = implode(',', array_map(fn($v) => $db->quote($v), $values));
        $db->exec("ALTER TABLE `tasks` MODIFY COLUMN `period_type` ENUM({$list}) NULL DEFAULT NULL");
    }
}

return [

    'description' => 'Task recurrence: specific weekdays and every-N-days',

    'up' => function (PDO $db) {
        $values = taskPeriodTypeEnumValues($db);
        $changed = false;
        foreach (['weekdays', 'interval'] as $v) {
            if (!in_array($v, $values, true)) {
                $values[] = $v;
                $changed = true;
            }
        }
        if ($changed) taskPeriodTypeSetEnum($db, $values);

        $has = fn($c) => (bool) $db->query("SHOW COLUMNS FROM `tasks` LIKE " . $db->quote($c))->fetch();
        if (!$has('period_weekdays')) {
            $db->exec("ALTER TABLE `tasks` ADD COLUMN `period_weekdays` VARCHAR(20) NULL DEFAULT NULL
                       COMMENT 'weekdays: روزهای هفته با date(w) — 0=یکشنبه..6=شنبه، مثلا 0,1,4' AFTER `period_type`");
        }
        if (!$has('period_interval_days')) {
            $db->exec("ALTER TABLE `tasks` ADD COLUMN `period_interval_days` SMALLINT NULL DEFAULT NULL
                       COMMENT 'interval: هر چند روز تقویمی یک دوره (2..365)' AFTER `period_weekdays`");
        }
    },

    'down' => function (PDO $db) {
        $used = (int) $db->query("SELECT COUNT(*) FROM `tasks` WHERE period_type IN ('weekdays','interval')")->fetchColumn();
        if ($used > 0) {
            throw new RuntimeException("{$used} کار از الگوی weekdays/interval استفاده می‌کنند؛ بازگرداندن داده را خراب می‌کند");
        }
        $db->exec("ALTER TABLE `tasks` DROP COLUMN `period_interval_days`");
        $db->exec("ALTER TABLE `tasks` DROP COLUMN `period_weekdays`");
        taskPeriodTypeSetEnum($db, array_values(array_diff(taskPeriodTypeEnumValues($db), ['weekdays', 'interval'])));
    },

];
