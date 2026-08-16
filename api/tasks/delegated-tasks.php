<?php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/checklist-search-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/recurring-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/task-dates-helper.php';

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);

    // ✅ بررسی فیلتر
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

    if ($filter === 'previous_delegations') {
        // نمایش ارجاعات سابق
        $tasks = $taskManager->getPreviousDelegations($user_id);
    } else {
        // نمایش کارهای واگذار شده عادی (فقط کارهایی که خودش ایجاد کرده)
        $tasks = $taskManager->getCreatedTasks($user_id);
    }

    $holidays = getHolidaySet($db);
    $today = date('Y-m-d');

    // اضافه کردن تعداد تکمیل‌ها + اعتبار بخشش معوقه به هر کار
    foreach ($tasks as &$task) {
        if (!isset($task['completed_count'])) {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM task_history WHERE task_id = ? AND action = 'completed'");
            $stmt->execute([$task['id']]);
            $task['completed_count'] = $stmt->fetch()['count'];
        }
        // اگر کوئری مبدأ این فیلد را نداده، از جدول tasks بخوان
        if (!array_key_exists('overdue_forgiven_credit', $task)) {
            $f = $db->prepare("SELECT overdue_forgiven_credit FROM tasks WHERE id = ?");
            $f->execute([$task['id']]);
            $task['overdue_forgiven_credit'] = (int) $f->fetchColumn();
        }

        // ══════════════════════════════════════════════════════
        //  برگشت از period_done به دوره‌ی بعدی (اگر موعدش رسیده)
        // ══════════════════════════════════════════════════════
        if ($task['task_type'] === 'continuous' && !empty($task['start_date'])) {
            maybeStartNextPeriod($db, $task, $user_id, $holidays);
        }

        // ✅ محاسبهٔ مشترک: موعد بعدی، مهلت، معوقه‌ها
        $task = enrichTaskDates($task, $db, $holidays, $today);
    }
    unset($task);
    attachChecklistTitles($db, $tasks);
    echo json_encode(['success' => true, 'tasks' => $tasks], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور'], JSON_UNESCAPED_UNICODE);
    error_log("Get delegated tasks error: " . $e->getMessage());
}
