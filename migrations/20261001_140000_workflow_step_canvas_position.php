<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: ذخیره‌سازیِ جایِ گره‌هایِ هر مرحله رویِ بومِ نموداریِ
 *  (Drawflow) تا با خروج/ورود دوباره یا افزودنِ یک مرحله، چینشِ دستیِ
 *  کاربر به‌هم نریزد.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'Add canvas_x/canvas_y position columns to workflow_steps for Drawflow layout persistence',

    'up' => function (PDO $db) {
        $colExists = function (string $col) use ($db): bool {
            $s = $db->prepare("
                SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'workflow_steps' AND COLUMN_NAME = ?
            ");
            $s->execute([$col]);
            return (bool) $s->fetchColumn();
        };

        if (!$colExists('canvas_x')) {
            $db->exec("ALTER TABLE `workflow_steps` ADD COLUMN `canvas_x` INT NULL COMMENT 'جایِ افقیِ گره رویِ بومِ Drawflow (null = هنوز تعیین نشده)'");
        }
        if (!$colExists('canvas_y')) {
            $db->exec("ALTER TABLE `workflow_steps` ADD COLUMN `canvas_y` INT NULL COMMENT 'جایِ عمودیِ گره رویِ بومِ Drawflow'");
        }
    },

    'down' => function (PDO $db) {
        $colExists = function (string $col) use ($db): bool {
            $s = $db->prepare("
                SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'workflow_steps' AND COLUMN_NAME = ?
            ");
            $s->execute([$col]);
            return (bool) $s->fetchColumn();
        };
        if ($colExists('canvas_x')) $db->exec("ALTER TABLE `workflow_steps` DROP COLUMN `canvas_x`");
        if ($colExists('canvas_y')) $db->exec("ALTER TABLE `workflow_steps` DROP COLUMN `canvas_y`");
    },

];
