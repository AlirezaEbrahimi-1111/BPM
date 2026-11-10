<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: وضعیتِ «cancelled» برای تسک‌ها
 *  تاریخ: ۱۴۰۵/۰۸/۱۹
 * ───────────────────────────────────────────────────────────────────
 *  WorkflowManager::completeInstance (مراحلِ باقی‌ماندهٔ روتینِ تمام‌شده) و مهاجرتِ
 *  20261104_100000 (تسک‌های یتیمِ کلیکِ تکراری، مثلِ ۴۴۷۲..۴۴۷۶) تسک را 'cancelled'
 *  می‌کردند، اما این مقدار در ENUMِ tasks.status نبود → MySQL رشتهٔ خالی ذخیره می‌کرد و
 *  تسک در فهرستِ کارها باز دیده می‌شد. مقدار اضافه می‌شود و تسک‌های روتینیِ وضعیت‌خالی
 *  همان «لغوشده»ای می‌شوند که قرار بود باشند.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => "tasks.status: add 'cancelled'; fix workflow tasks stored with empty status",

    'up' => function (PDO $db) {
        $col = $db->query("SHOW COLUMNS FROM `tasks` LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        if ($col && stripos($col['Type'], "'cancelled'") === false) {
            preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $col['Type'], $m);
            $vals = $m[1];
            $vals[] = 'cancelled';
            $enumList = implode(',', array_map(fn($v) => $db->quote($v), $vals));
            $def = $col['Default'] !== null ? ' DEFAULT ' . $db->quote($col['Default']) : '';
            $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
            $db->exec("ALTER TABLE `tasks` MODIFY COLUMN `status` ENUM($enumList) $null$def");
        }

        // فقط تسک‌های روتین با وضعیتِ خالی، که دیگر به هیچ مرحلهٔ فعالی وصل نیستند
        $db->exec("
            UPDATE tasks t
            SET t.status = 'cancelled', t.updated_at = NOW()
            WHERE t.status = ''
              AND t.is_workflow_task = 1
              AND NOT EXISTS (
                  SELECT 1 FROM workflow_instance_steps wis
                  WHERE wis.task_id = t.id AND wis.status IN ('active', 'pending')
              )
        ");
    },

    'down' => function (PDO $db) {
        // مقدارِ ENUM برداشته نمی‌شود (تسک‌های لغوشده خراب می‌شوند)؛ عمداً بدون تغییر
    },

];
