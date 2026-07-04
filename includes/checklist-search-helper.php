<?php
/**
 * افزودن فیلد checklist_titles به هر تسک
 * این فیلد شامل عنوان همه آیتم‌های چک‌لیست آن تسک است (برای جستجو)
 */
function attachChecklistTitles($db, &$tasks) {
    if (empty($tasks)) return;

    $task_ids = array_unique(array_filter(array_column($tasks, 'id')));
    if (empty($task_ids)) return;

    $placeholders = str_repeat('?,', count($task_ids) - 1) . '?';

    $sql = "SELECT task_id, GROUP_CONCAT(title SEPARATOR ' ') AS titles
            FROM task_checklist_items
            WHERE task_id IN ($placeholders)
            GROUP BY task_id";

    $stmt = $db->prepare($sql);
    $stmt->execute(array_values($task_ids));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $r) {
        $map[$r['task_id']] = $r['titles'];
    }

    foreach ($tasks as &$task) {
        $task['checklist_titles'] = $map[$task['id']] ?? '';
    }
    unset($task);
}