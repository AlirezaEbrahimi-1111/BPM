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
    $title   = trim($input['title'] ?? '');

    if (!$task_id || $title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار و عنوان آیتم الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $task = getTaskForChecklist($db, $task_id, $user_id);
    if (!$task || !$task['_is_creator']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط تعریف‌کننده کار می‌تواند آیتم اضافه کند']);
        exit;
    }
// 🔒 اگر کار تکمیل/تأیید/متوقف/لغو شده، چک‌لیست قفل است
    if (isChecklistLocked($task)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'این کار به پایان رسیده و چک‌لیست آن قفل شده است']);
        exit;
    }
    // sort_order = آخرین + 1
    $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM task_checklist_items WHERE task_id = ?");
    $stmt->execute([$task_id]);
    $order = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("INSERT INTO task_checklist_items (task_id, title, sort_order, created_by)
                          VALUES (?, ?, ?, ?)");
    $stmt->execute([$task_id, $title, $order, $user_id]);
    try {
        syncTaskStatusWithChecklist($db, $task, $user_id);
    } catch (Exception $syncErr) {
        error_log("sync after add-item failed: " . $syncErr->getMessage());
    }
    echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'آیتم اضافه شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("checklist/add-item error: " . $e->getMessage());
}
