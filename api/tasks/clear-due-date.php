<?php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    $task_id = (int) ($input['task_id'] ?? 0);

    if (!$task_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT creator_id, assignee_id, task_type, due_date, deadline, original_deadline, status FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کار یافت نشد']);
        exit;
    }

    // فقط کار مقطعی موعد دارد که برداشتنش معنی داشته باشد
    if ($task['task_type'] !== 'periodic') {
        echo json_encode(['success' => false, 'message' => 'حذف موعد فقط برای کارهای مقطعی امکان‌پذیر است']);
        exit;
    }

    // شرط اصلی: مسئول انجام و تعریف‌کننده باید یک نفر باشند
    if ((int) $task['creator_id'] !== (int) $task['assignee_id']) {
        echo json_encode(['success' => false, 'message' => 'این کار قابل انجام نیست — مسئول انجام و تعریف‌کننده‌ی کار یک نفر نیستند']);
        exit;
    }

    // فقط همان شخص (که هم تعریف‌کننده است هم مسئول) اجازه دارد
    if ((int) $user_id !== (int) $task['creator_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    if (empty($task['due_date']) && empty($task['deadline']) && empty($task['original_deadline'])) {
        echo json_encode(['success' => true, 'message' => 'این کار از قبل موعدی نداشت']);
        exit;
    }

    // هر سه ستون تاریخ موعد پاک می‌شوند — enrichTaskDates() بیشینه‌ی این سه
    // را به‌عنوان موعد می‌گیرد، پس اگر یکی باقی بماند موعد همچنان نمایش داده می‌شود.
    $db->prepare("UPDATE tasks SET due_date = NULL, deadline = NULL, original_deadline = NULL, updated_at = NOW() WHERE id = ?")->execute([$task_id]);
    $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes) VALUES (?, ?, NULL, 'updated', 'موعد انجام حذف شد')")
        ->execute([$task_id, $user_id]);

    echo json_encode(['success' => true, 'message' => 'موعد کار حذف شد']);
} catch (Exception $e) {
    http_response_code(500);
    error_log("tasks/clear-due-date.php failed | user_id=" . ($user_id ?? 'null') . " | task_id=" . ($task_id ?? 'null') . " | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
