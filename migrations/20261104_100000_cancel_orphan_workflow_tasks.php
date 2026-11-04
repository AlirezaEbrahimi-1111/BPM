<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: لغوِ تسک‌هایِ یتیمِ روتین
 *  تاریخ: ۱۴۰۵/۰۸/۱۳
 * ───────────────────────────────────────────────────────────────────
 *  چند بار زدنِ «تکمیل» روی یک مرحله، مرحله‌ی بعد را چند بار ساخته بود؛
 *  فقط آخری به workflow_instance_steps وصل شد و بقیه بی‌صاحب (in_progress،
 *  بی‌مسئول) در «کارهای واگذار شده»ی تعریف‌کننده ماندند.
 *  ریشه در WorkflowManager::lockInstanceForTask بسته شد.
 *  فقط همین شناسه‌هایِ بررسی‌شده (۱۴۰۵/۰۸/۱۳) لغو می‌شوند، حذف نه.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'لغو تسک‌های یتیم روتین (مرحله‌ی تکراری ساخته‌شده)',

    'up' => function (PDO $db) {
        $db->exec("
            UPDATE tasks t
            SET t.status = 'cancelled', t.updated_at = NOW()
            WHERE t.id IN (3867, 3986, 3987, 3988, 4472, 4473, 4474, 4475, 4476, 4629)
              AND t.is_workflow_task = 1
              AND t.status NOT IN ('completed', 'cancelled')
              AND NOT EXISTS (SELECT 1 FROM workflow_instance_steps wis WHERE wis.task_id = t.id)
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("
            UPDATE tasks t
            SET t.status = 'in_progress', t.updated_at = NOW()
            WHERE t.id IN (3867, 3986, 3987, 3988, 4472, 4473, 4474, 4475, 4476, 4629)
              AND t.status = 'cancelled'
              AND NOT EXISTS (SELECT 1 FROM workflow_instance_steps wis WHERE wis.task_id = t.id)
        ");
    },

];
