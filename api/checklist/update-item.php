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
    $item_id = intval($input['item_id'] ?? 0);
    $title   = trim($input['title'] ?? '');

    // آیا ارجاع هم در این درخواست آمده؟ (اگر نیامد، ارجاع را دست نمی‌زنیم)
    $has_assignee = array_key_exists('assignee_type', $input);
    $assignee_type  = $input['assignee_type']  ?? null;
    $assignee_value = $input['assignee_value'] ?? null;
    if ($assignee_type !== 'user' && $assignee_type !== 'section') {
        $assignee_type  = null;
        $assignee_value = null;
    }
    if ($assignee_type !== null && ($assignee_value === null || $assignee_value === '')) {
        $assignee_type  = null;
        $assignee_value = null;
    }

    if (!$item_id || $title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه آیتم و عنوان الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT task_id, is_done, assignee_type, assignee_value FROM task_checklist_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'آیتم یافت نشد']);
        exit;
    }

    // آیتم تیک‌خورده قابل ویرایش نیست
    if ((int)$row['is_done'] === 1) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'این آیتم انجام شده و قابل ویرایش نیست']);
        exit;
    }

    $task = getTaskForChecklist($db, $row['task_id'], $user_id);
    if (!$task || !$task['_is_creator']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط تعریف‌کننده کار می‌تواند آیتم را ویرایش کند']);
        exit;
    }
// 🔒 اگر کار تکمیل/تأیید/متوقف/لغو شده، چک‌لیست قفل است
    if (isChecklistLocked($task)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'این کار به پایان رسیده و چک‌لیست آن قفل شده است']);
        exit;
    }
    if ($has_assignee) {
        // هم عنوان، هم ارجاع آپدیت می‌شود
        $stmt = $db->prepare("UPDATE task_checklist_items
                              SET title = ?, assignee_type = ?, assignee_value = ?
                              WHERE id = ?");
        $stmt->execute([$title, $assignee_type, $assignee_value, $item_id]);
    } else {
        // فقط عنوان (رفتار قبلی، بدون دست‌زدن به ارجاع)
        $stmt = $db->prepare("UPDATE task_checklist_items SET title = ? WHERE id = ?");
        $stmt->execute([$title, $item_id]);
    }
    // 🆕 اگر ارجاع تغییر کرده و مقصدِ جدید معتبر است → اعلان بفرست
    if ($has_assignee) {
        try {
            $oldType  = $row['assignee_type']  ?? null;
            $oldValue = $row['assignee_value'] ?? null;

            $changed = ($oldType !== $assignee_type) || ((string)$oldValue !== (string)$assignee_value);

            if ($changed && ($assignee_type === 'user' || $assignee_type === 'section') && $assignee_value) {
                notifyChecklistAssignee($db, $assignee_type, $assignee_value, $task, $user_id, $title);
            }
        } catch (Exception $notifyErr) {
            error_log("checklist update notify failed: " . $notifyErr->getMessage());
        }
    }
    echo json_encode(['success' => true, 'message' => 'آیتم به‌روزرسانی شد'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("checklist/update-item error: " . $e->getMessage());
}
