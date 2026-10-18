<?php
/**
 * واحدِ مرحلهٔ روتین و واحدِ کار: از ENUM ثابت به متنِ آزاد.
 *
 * واحدها دیگر ثابت نیستند — هر سازمان واحدِ خودش را می‌سازد
 * (organization_activity_sections.section_key، VARCHAR(50)) — ولی این دو ستون
 * هنوز فقط ۱۰ واحدِ قدیمی را قبول می‌کردند. چون SQL mode سخت‌گیر نیست، کلیدِ
 * واحدِ سفارشی (مثلا l_accounting = «حسابداری رسمی») بی‌صدا '' ذخیره می‌شد:
 * مرحلهٔ روتین بی‌واحد می‌ماند و کارِ ساخته‌شده از آن را هیچ‌کس نمی‌دید.
 *
 * ردیف‌های موجود تغییری نمی‌کنند (همان مقادیرِ قبلی در متنِ آزاد جا می‌شوند).
 * collation و NULL/NOT NULLِ فعلیِ هر ستون حفظ می‌شود.
 *
 * + اصلاحِ داده: روتینِ ۴۲ (سازمان ۱) که با واحدِ «حسابداری رسمی» بازتعریف شده
 *   بود و هر سه مرحله‌اش خالی ذخیره شده بود. فقط اگر آن واحد دقیقا یکی باشد و
 *   مرحله‌ها هنوز خالی باشند؛ وگرنه کاری نمی‌کند.
 */

$OLD_SECTIONS = ['sales', 'purchase', 'warehouse', 'technical', 'accounting', 'colleague', 'offices', 'virtual', 'public', 'management'];

$column = function (PDO $db, string $table) {
    $st = $db->prepare("
        SELECT COLUMN_TYPE, COLLATION_NAME, IS_NULLABLE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'activity_section'
    ");
    $st->execute([$table]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
};

// tasks: NULL مجاز (پیش‌فرض NULL) — workflow_steps: NOT NULL (کد برای «بدون واحد» '' می‌فرستد)
$nullSpec = function (string $table) {
    return $table === 'tasks' ? 'NULL DEFAULT NULL' : "NOT NULL DEFAULT ''";
};

return [

    'description' => 'tasks/workflow_steps.activity_section: ENUM → VARCHAR(50) (custom sections were saved empty) + fix routine 42',

    'up' => function (PDO $db) use ($column, $nullSpec) {
        foreach (['workflow_steps', 'tasks'] as $table) {
            $col = $column($db, $table);
            if (!$col || stripos($col['COLUMN_TYPE'], 'enum(') !== 0) continue; // قبلا تبدیل شده
            $coll = preg_replace('/[^a-z0-9_]/i', '', (string) $col['COLLATION_NAME']);
            $charset = explode('_', $coll)[0] ?: 'utf8mb4';
            $db->exec("ALTER TABLE `{$table}` MODIFY COLUMN `activity_section`
                       VARCHAR(50) CHARACTER SET {$charset} COLLATE {$coll} " . $nullSpec($table));
        }

        // ── اصلاحِ دادهٔ روتینِ ۴۲ ──
        $tpl = $db->query("SELECT id, organization_id FROM workflow_templates WHERE id = 42")->fetch(PDO::FETCH_ASSOC);
        if (!$tpl) return;

        $st = $db->prepare("SELECT section_key FROM organization_activity_sections
                            WHERE organization_id = ? AND section_label = 'حسابداری رسمی'");
        $st->execute([(int) $tpl['organization_id']]);
        $keys = $st->fetchAll(PDO::FETCH_COLUMN);
        if (count($keys) !== 1) {
            error_log('migration activity_section_free_text: routine 42 not fixed — «حسابداری رسمی» found ' . count($keys) . ' times');
            return;
        }
        $key = $keys[0];

        $db->prepare("UPDATE workflow_steps SET activity_section = ?
                      WHERE template_id = 42 AND assignee_type = 'section'
                        AND (activity_section IS NULL OR activity_section = '')")
           ->execute([$key]);

        // کارهای در جریانِ همین روتین که بی‌واحد (و بی‌مسئولِ مستقیم) ساخته شده‌اند
        $db->prepare("UPDATE tasks t
                      JOIN workflow_instance_steps wis ON wis.task_id = t.id
                      JOIN workflow_steps ws ON ws.id = wis.step_id
                      SET t.activity_section = ?
                      WHERE ws.template_id = 42 AND ws.assignee_type = 'section'
                        AND t.is_workflow_task = 1
                        AND (t.activity_section IS NULL OR t.activity_section = '')
                        AND (t.assignee_id IS NULL OR t.assignee_id = 0)")
           ->execute([$key]);
    },

    'down' => function (PDO $db) use ($column, $OLD_SECTIONS) {
        // 🔒 برگشت به ENUM، واحدهای سفارشی را دوباره بی‌صدا '' می‌کند → اگر هست، رد کن
        $in = "'" . implode("','", $OLD_SECTIONS) . "'";
        foreach (['workflow_steps', 'tasks'] as $table) {
            $n = (int) $db->query("SELECT COUNT(*) FROM `{$table}`
                                   WHERE activity_section IS NOT NULL AND activity_section <> ''
                                     AND activity_section NOT IN ({$in})")->fetchColumn();
            if ($n > 0) {
                throw new RuntimeException("{$n} ردیف در {$table} واحدِ سفارشی دارد؛ برگشت به ENUM آن‌ها را خالی می‌کند.");
            }
        }
        foreach (['workflow_steps', 'tasks'] as $table) {
            $col = $column($db, $table);
            if (!$col || stripos($col['COLUMN_TYPE'], 'varchar(') !== 0) continue;
            $coll = preg_replace('/[^a-z0-9_]/i', '', (string) $col['COLLATION_NAME']);
            $charset = explode('_', $coll)[0] ?: 'utf8mb4';
            $null = $table === 'tasks' ? 'NULL DEFAULT NULL' : 'NOT NULL';
            $db->exec("ALTER TABLE `{$table}` MODIFY COLUMN `activity_section`
                       ENUM({$in}) CHARACTER SET {$charset} COLLATE {$coll} {$null}");
        }
    },

];
