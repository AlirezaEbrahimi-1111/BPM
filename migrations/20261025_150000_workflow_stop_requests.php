<?php
/**
 * درخواست توقف روتین
 *
 * مسئولِ مرحلهٔ جاری یک روتین (workflow instance) می‌تواند درخواست توقف بدهد؛
 * درخواست به تعریف‌کنندهٔ روتین (workflow_instances.created_by) می‌رود و با
 * تأیید او روتین متوقف می‌شود (WorkflowManager::cancelWorkflow).
 *
 * ۱) جدول workflow_stop_requests
 * ۲) سه مقدار تازه در ENUM ستون task_history.action:
 *    workflow_stop_requested / workflow_stopped / workflow_stop_rejected
 *
 * فهرست ENUM هاردکد نشده: تعریف فعلی ستون خوانده و فقط مقدارهای جدید اضافه می‌شود.
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

    'description' => 'Workflow stop requests table + task_history actions',

    'up' => function (PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `workflow_stop_requests` (
                `id`               INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id`  INT NOT NULL,
                `instance_id`      INT NOT NULL,
                `task_id`          INT NOT NULL COMMENT 'کارِ مرحله‌ای که درخواست از آن ثبت شد',
                `requested_by`     INT NOT NULL,
                `approver_id`      INT NOT NULL,
                `reason`           TEXT NOT NULL,
                `status`           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                `rejection_reason` TEXT NULL,
                `reviewed_by`      INT NULL,
                `reviewed_at`      TIMESTAMP NULL DEFAULT NULL,
                `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_wsr_instance_status` (`instance_id`, `status`),
                KEY `idx_wsr_task` (`task_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");

        $values = taskHistoryActionEnumValues($db);
        $added = false;
        foreach (['workflow_stop_requested', 'workflow_stopped', 'workflow_stop_rejected'] as $v) {
            if (!in_array($v, $values, true)) {
                $values[] = $v;
                $added = true;
            }
        }
        if ($added) taskHistorySetActionEnum($db, $values);
    },

    'down' => function (PDO $db) {
        $new = ['workflow_stop_requested', 'workflow_stopped', 'workflow_stop_rejected'];
        $values = taskHistoryActionEnumValues($db);
        if (array_intersect($new, $values)) {
            $db->exec("DELETE FROM `task_history` WHERE `action` IN ('workflow_stop_requested','workflow_stopped','workflow_stop_rejected')");
            taskHistorySetActionEnum($db, array_values(array_diff($values, $new)));
        }
        $db->exec("DROP TABLE IF EXISTS `workflow_stop_requests`");
    },

];
