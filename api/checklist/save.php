<?php

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once __DIR__ . '/_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    $task_id = intval($input['task_id'] ?? 0);
    $items   = $input['items'] ?? [];

    if (!$task_id || !is_array($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار و آیتم‌ها الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $task = getTaskForChecklist($db, $task_id, $user_id);
    if (!$task || !$task['_is_creator']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط تعریف‌کننده کار مجاز است']);
        exit;
    }

    // ── آماده‌سازی لیست شناسه‌های معتبر برای اعتبارسنجی ارجاع ──
    $org_id = intval($task['organization_id'] ?? 0);

    // شناسه‌های معتبر کاربرانِ همین سازمان
    $valid_user_ids = [];
    if ($org_id) {
        $uStmt = $db->prepare("SELECT id FROM users WHERE organization_id = ?");
        $uStmt->execute([$org_id]);
        $valid_user_ids = array_map('strval', $uStmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // کلیدهای معتبر واحدهای همین سازمان (فقط فعال‌ها)
    $valid_section_keys = [];
    if ($org_id) {
        $sStmt = $db->prepare("SELECT section_key FROM organization_activity_sections
                               WHERE organization_id = ? AND is_active = 1");
        $sStmt->execute([$org_id]);
        $valid_section_keys = $sStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $stmt = $db->prepare("INSERT INTO task_checklist_items
                            (task_id, title, description, assignee_type, assignee_value, sort_order, created_by)
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $count = 0;
    foreach ($items as $i => $it) {
        $title = trim($it['title'] ?? '');
        if ($title === '') continue;
        $description = trim($it['description'] ?? '');
        $order = isset($it['sort_order']) ? intval($it['sort_order']) : $i;

        // خواندن ارجاع
        $assignee_type  = $it['assignee_type']  ?? null;
        $assignee_value = $it['assignee_value'] ?? null;

        // ۱) نوع باید مجاز باشد
        if ($assignee_type !== 'user' && $assignee_type !== 'section') {
            $assignee_type  = null;
            $assignee_value = null;
        }
        // ۲) مقدار نباید خالی باشد
        if ($assignee_type !== null && ($assignee_value === null || $assignee_value === '')) {
            $assignee_type  = null;
            $assignee_value = null;
        }
        // ۳) شناسه باید واقعاً در همین سازمان وجود داشته باشد
        if ($assignee_type === 'user' && !in_array((string)$assignee_value, $valid_user_ids, true)) {
            $assignee_type  = null;
            $assignee_value = null;
        }
        if ($assignee_type === 'section' && !in_array($assignee_value, $valid_section_keys, true)) {
            $assignee_type  = null;
            $assignee_value = null;
        }

        $stmt->execute([$task_id, $title, $description, $assignee_type, $assignee_value, $order, $user_id]);
        $count++;
    }
    try{
        // ── جمع‌آوری ارجاع‌های یکتا و ارسال اعلان (یکی برای هر گیرنده) ──
        $assignSet = []; // کلید یکتا => [type, value]
        foreach ($items as $it) {
            $type  = $it['assignee_type']  ?? null;
            $value = $it['assignee_value'] ?? null;
            if (($type === 'user' || $type === 'section') && $value) {
                $assignSet["{$type}:{$value}"] = ['type' => $type, 'value' => $value];
            }
        }
        foreach ($assignSet as $a) {
            // عنوان آیتم را خالی می‌گذاریم چون ممکن است چند آیتم باشد
            notifyChecklistAssignee($db, $a['type'], $a['value'], $task, $user_id, '');
        }
    } catch (Exception $notifyErr) {
        error_log("checklist save notify failed: " . $notifyErr->getMessage());
    }
    echo json_encode(['success' => true, 'message' => "{$count} آیتم ذخیره شد", 'count' => $count], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("checklist/save error: " . $e->getMessage());
}
