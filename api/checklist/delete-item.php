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

    if (!$item_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه آیتم الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT task_id, is_done FROM task_checklist_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'آیتم یافت نشد']);
        exit;
    }

    // آیتم تیک‌خورده قابل حذف نیست
    if ((int)$row['is_done'] === 1) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'این آیتم انجام شده و قابل حذف نیست']);
        exit;
    }

    $task = getTaskForChecklist($db, $row['task_id'], $user_id);
    if (!$task || !$task['_is_creator']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط تعریف‌کننده کار می‌تواند آیتم را حذف کند']);
        exit;
    }

    $db->prepare("DELETE FROM task_checklist_items WHERE id = ?")->execute([$item_id]);

    // بعد از حذف، شاید بقیه آیتم‌ها همه تیک‌خورده باشند → auto-complete
    $auto = maybeAutoComplete($db, $task, $user_id);

    $p = checklistProgress($db, $row['task_id']);
    echo json_encode([
        'success' => true,
        'message' => 'آیتم حذف شد',
        'auto_completed' => $auto,
        'percent' => $p['percent'], 'done' => $p['done'], 'total' => $p['total']
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("checklist/delete-item error: " . $e->getMessage());
}
