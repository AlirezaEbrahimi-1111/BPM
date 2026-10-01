<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  رفعِ باگ: enumِ workflow_instance_steps.status فاقدِ 'rejected' و
 *  'cancelled' بود، با این‌که کدِ WorkflowManager (هم موتورِ قدیمی، هم
 *  موتورِ جدیدِ گراف‌محور) از قبل این دو مقدار رو می‌نویسه:
 *    - resolveStepDecision() موقعِ رد یک مرحله → status='rejected'
 *    - completeInstance() برایِ مراحلِ باقی‌مانده → status='cancelled'
 *
 *  چون sql_mode این پروژه STRICT_TRANS_TABLES نداره، MySQL به‌جایِ
 *  خطادادن، این مقدارهایِ نامعتبر رو بی‌سروصدا به رشته‌ی خالی تبدیل
 *  می‌کنه — یعنی وضعیتِ واقعیِ مرحله گم می‌شه، بدونِ هیچ خطایی. این باگ
 *  حین تستِ محلیِ فازِ ۶ (موتورِ گراف‌محور) با داده‌ی واقعی کشف شد: مسیرِ
 *  «رد» باعث شد وضعیتِ مرحله خالی بمونه و مرحله‌ی بعدی هم فعال نشه.
 *
 *  میگریشنِ ۲۰۲۶۰۸۲۷ (که 'dormant' رو اضافه کرد) فرض کرده بود 'cancelled'
 *  از قبل وجود داره — این فرض غلط بوده؛ این فایل هر دو مقدارِ جاافتاده
 *  رو اضافه می‌کنه.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => "Fix workflow_instance_steps.status enum: add missing 'rejected' and 'cancelled' values",

    'up' => function (PDO $db) {
        $colInfo = $db->query("SHOW COLUMNS FROM workflow_instance_steps LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        if (!preg_match('/^enum\((.*)\)$/i', $colInfo['Type'], $m)) {
            return; // ستون دیگه enum نیست، کاری لازم نیست
        }
        preg_match_all("/'((?:[^']|'')*)'/", $m[1], $vm);
        $vals = array_map(fn($v) => str_replace("''", "'", $v), $vm[1]);

        $changed = false;
        foreach (['rejected', 'cancelled'] as $v) {
            if (!in_array($v, $vals, true)) {
                $vals[] = $v;
                $changed = true;
            }
        }
        if (!$changed) return;

        $enumList = implode(',', array_map([$db, 'quote'], $vals));
        $nullSql = ($colInfo['Null'] === 'YES') ? 'NULL' : 'NOT NULL';
        $defSql = ($colInfo['Default'] !== null) ? (' DEFAULT ' . $db->quote($colInfo['Default'])) : '';
        $db->exec("ALTER TABLE `workflow_instance_steps` MODIFY COLUMN `status` ENUM($enumList) $nullSql$defSql");
    },

    'down' => function (PDO $db) {
        // برگردوندن enum به حالتِ قبلی فقط اگه هیچ ردیفی از این دو مقدار استفاده نکنه
        $inUse = $db->query("
            SELECT COUNT(*) FROM workflow_instance_steps WHERE status IN ('rejected','cancelled')
        ")->fetchColumn();
        if ((int) $inUse > 0) {
            error_log("fix_workflow_instance_steps_status_enum down(): skipped — $inUse ردیف از rejected/cancelled استفاده می‌کنه");
            return;
        }
        $colInfo = $db->query("SHOW COLUMNS FROM workflow_instance_steps LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        if (!preg_match('/^enum\((.*)\)$/i', $colInfo['Type'], $m)) return;
        preg_match_all("/'((?:[^']|'')*)'/", $m[1], $vm);
        $vals = array_map(fn($v) => str_replace("''", "'", $v), $vm[1]);
        $vals = array_values(array_filter($vals, fn($v) => !in_array($v, ['rejected', 'cancelled'], true)));
        $enumList = implode(',', array_map([$db, 'quote'], $vals));
        $nullSql = ($colInfo['Null'] === 'YES') ? 'NULL' : 'NOT NULL';
        $defSql = ($colInfo['Default'] !== null) ? (' DEFAULT ' . $db->quote($colInfo['Default'])) : '';
        $db->exec("ALTER TABLE `workflow_instance_steps` MODIFY COLUMN `status` ENUM($enumList) $nullSql$defSql");
    },

];
