<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: زیرساختِ «شرطی‌سازیِ مراحلِ کارهای روتین» (فاز ۱ — فقط اسکیما)
 *  تاریخ: ۱۴۰۵/۰۶/۰۵
 * ───────────────────────────────────────────────────────────────────
 *  هدف: امکانِ تعریفِ «مرحلهٔ تصمیم» در قالبِ روتین که در صورتِ تأیید به
 *  یک مرحله و در صورتِ رد به مرحله‌ای دیگر (یا بازگشت به تعریف‌کنندهٔ
 *  نمونه) پرش کند. منطقِ موتور و رابطِ کاربری در فازهای بعدی.
 *
 *  تغییرات:
 *   ۱) workflow_steps:
 *        + is_decision           (این مرحله نقطهٔ تصمیم است؟)
 *        + on_approve_step_order  (در صورت تأیید → این step_order؛ NULL = مرحلهٔ بعدی)
 *        + on_reject_mode         ('step' | 'creator')
 *        + on_reject_step_order   (اگر mode=step → این step_order)
 *   ۲) workflow_instance_steps.status: افزودنِ مقدارِ 'dormant'
 *        (مرحلهٔ پرش‌خورده که خفته می‌ماند — بدون تسک/نوتیفیکیشن)
 *        نکته: 'cancelled' از قبل در کد استفاده می‌شود و اضافه نمی‌شود.
 *   ۳) workflow_instance_steps.task_id: nullable می‌شود
 *        (برای «ساختِ تنبلِ تسک» در فاز ۲ — مراحلِ خفته task_id ندارند)
 *
 *  همه‌چیز idempotent و امن در برابرِ اجرا دوباره است.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'Phase 1: schema for conditional branching in routine (workflow_*) steps — decision steps, approve/reject targets, dormant status, nullable task_id',

    'up' => function (PDO $db) {

        $colExists = function (string $table, string $col) use ($db): bool {
            $s = $db->prepare("
                SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ");
            $s->execute([$table, $col]);
            return (bool) $s->fetchColumn();
        };

        $colInfo = function (string $table, string $col) use ($db): ?array {
            $s = $db->prepare("
                SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ");
            $s->execute([$table, $col]);
            $r = $s->fetch(PDO::FETCH_ASSOC);
            return $r ?: null;
        };

        // ── ۱) ستون‌های جدیدِ workflow_steps ─────────────────────────────
        if (!$colExists('workflow_steps', 'is_decision')) {
            $db->exec("ALTER TABLE `workflow_steps`
                ADD COLUMN `is_decision` TINYINT(1) NOT NULL DEFAULT 0
                COMMENT 'مرحلهٔ تصمیم (تأیید/رد توسط تعریف‌کنندهٔ نمونه)'");
        }
        if (!$colExists('workflow_steps', 'on_approve_step_order')) {
            $db->exec("ALTER TABLE `workflow_steps`
                ADD COLUMN `on_approve_step_order` INT NULL DEFAULT NULL
                COMMENT 'در صورت تأیید → این step_order؛ NULL = مرحلهٔ بعدیِ خطی'");
        }
        if (!$colExists('workflow_steps', 'on_reject_mode')) {
            $db->exec("ALTER TABLE `workflow_steps`
                ADD COLUMN `on_reject_mode` ENUM('step','creator') NULL DEFAULT NULL
                COMMENT 'رفتارِ رد: پرش به یک مرحله، یا بازگشت به تعریف‌کنندهٔ نمونه'");
        }
        if (!$colExists('workflow_steps', 'on_reject_step_order')) {
            $db->exec("ALTER TABLE `workflow_steps`
                ADD COLUMN `on_reject_step_order` INT NULL DEFAULT NULL
                COMMENT 'اگر on_reject_mode=step → این step_order'");
        }

        // مقادیرِ یک تعریفِ enum را به آرایه تبدیل می‌کند (با پشتیبانی از '' داخلِ مقدار)
        $enumVals = function (string $columnType): ?array {
            if (!preg_match('/^enum\((.*)\)$/i', $columnType, $m)) return null;
            preg_match_all("/'((?:[^']|'')*)'/", $m[1], $vm);
            return array_map(fn($v) => str_replace("''", "'", $v), $vm[1]);
        };

        // ── ۲) افزودنِ مقدارِ 'dormant' به enum ستونِ status ─────────────
        $st = $colInfo('workflow_instance_steps', 'status');
        $vals = $st ? $enumVals($st['COLUMN_TYPE']) : null;
        if ($vals !== null) {
            if (!in_array('dormant', $vals, true)) {
                $vals[] = 'dormant';
                $enumList = implode(',', array_map([$db, 'quote'], $vals));
                $nullSql = ($st['IS_NULLABLE'] === 'YES') ? 'NULL' : 'NOT NULL';
                $defSql  = '';
                if ($st['COLUMN_DEFAULT'] !== null) {
                    $defSql = ' DEFAULT ' . $db->quote($st['COLUMN_DEFAULT']);
                } elseif ($st['IS_NULLABLE'] === 'YES') {
                    $defSql = ' DEFAULT NULL';
                }
                $db->exec("ALTER TABLE `workflow_instance_steps`
                    MODIFY COLUMN `status` ENUM($enumList) $nullSql$defSql");
            }
        }
        // اگر status از نوعِ enum نبود (varchar/text)، هیچ کاری لازم نیست.

        // ── ۳) nullable کردنِ task_id (برای ساختِ تنبلِ تسک در فاز ۲) ─────
        $ti = $colInfo('workflow_instance_steps', 'task_id');
        if ($ti && $ti['IS_NULLABLE'] === 'NO') {
            $db->exec("ALTER TABLE `workflow_instance_steps`
                MODIFY COLUMN `task_id` {$ti['COLUMN_TYPE']} NULL DEFAULT NULL");
        }
    },

    'down' => function (PDO $db) {

        $colExists = function (string $table, string $col) use ($db): bool {
            $s = $db->prepare("
                SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ");
            $s->execute([$table, $col]);
            return (bool) $s->fetchColumn();
        };
        $colInfo = function (string $table, string $col) use ($db): ?array {
            $s = $db->prepare("
                SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ");
            $s->execute([$table, $col]);
            $r = $s->fetch(PDO::FETCH_ASSOC);
            return $r ?: null;
        };
        $enumVals = function (string $columnType): ?array {
            if (!preg_match('/^enum\((.*)\)$/i', $columnType, $m)) return null;
            preg_match_all("/'((?:[^']|'')*)'/", $m[1], $vm);
            return array_map(fn($v) => str_replace("''", "'", $v), $vm[1]);
        };

        // ۳') برگرداندنِ task_id به NOT NULL — فقط اگر هیچ ردیفِ NULL نمانده باشد
        $ti = $colInfo('workflow_instance_steps', 'task_id');
        if ($ti && $ti['IS_NULLABLE'] === 'YES') {
            $nulls = (int) $db->query("SELECT COUNT(*) FROM workflow_instance_steps WHERE task_id IS NULL")->fetchColumn();
            if ($nulls === 0) {
                $db->exec("ALTER TABLE `workflow_instance_steps`
                    MODIFY COLUMN `task_id` {$ti['COLUMN_TYPE']} NOT NULL");
            } else {
                error_log("workflow branching down(): task_id nullable نگه داشته شد چون $nulls ردیفِ NULL دارد");
            }
        }

        // ۲') حذفِ 'dormant' از enum ستونِ status
        $st = $colInfo('workflow_instance_steps', 'status');
        $vals = $st ? $enumVals($st['COLUMN_TYPE']) : null;
        if ($vals !== null) {
            if (in_array('dormant', $vals, true)) {
                $db->exec("UPDATE workflow_instance_steps SET status = 'cancelled' WHERE status = 'dormant'");
                $vals = array_values(array_filter($vals, fn($v) => $v !== 'dormant'));
                $enumList = implode(',', array_map([$db, 'quote'], $vals));
                $nullSql = ($st['IS_NULLABLE'] === 'YES') ? 'NULL' : 'NOT NULL';
                $defSql  = '';
                if ($st['COLUMN_DEFAULT'] !== null) {
                    $defSql = ' DEFAULT ' . $db->quote($st['COLUMN_DEFAULT']);
                } elseif ($st['IS_NULLABLE'] === 'YES') {
                    $defSql = ' DEFAULT NULL';
                }
                $db->exec("ALTER TABLE `workflow_instance_steps`
                    MODIFY COLUMN `status` ENUM($enumList) $nullSql$defSql");
            }
        }

        // ۱') حذفِ ستون‌های workflow_steps
        foreach (['on_reject_step_order', 'on_reject_mode', 'on_approve_step_order', 'is_decision'] as $col) {
            if ($colExists('workflow_steps', $col)) {
                $db->exec("ALTER TABLE `workflow_steps` DROP COLUMN `$col`");
            }
        }
    },

];
