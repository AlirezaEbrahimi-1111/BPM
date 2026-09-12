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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php'; // ← اضافه شد
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/checklist-search-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/task-dates-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/recurring-helper.php';

try {
    $user_id = requireAuth();

    $database = new Database();
    $db       = $database->getConnection();
    $taskManager = new TaskManager($db);

    // ─── بارگذاری تعطیلات (یک‌بار برای کل request) ─────────────────────────
    $holidays = getHolidaySet($db);

    $today_str = date('Y-m-d');

    // دریافت فیلترها از query string
    $filters = [];
    if (!empty($_GET['status']))    $filters['status']    = $_GET['status'];
    if (!empty($_GET['priority']))  $filters['priority']  = $_GET['priority'];
    if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
    if (!empty($_GET['date_to']))   $filters['date_to']   = $_GET['date_to'];

    $tasks = $taskManager->getAllTasks($user_id, $filters);

    // 🆕 پیش‌واکشیِ دسته‌ایِ تاریخ‌های تکمیل برای کارهای دوره‌ای (بدونِ کوئریِ
    // جدا به‌ازای هر کار) — برای maybeStartNextPeriod/enrichTaskDates
    $continuousIds = [];
    foreach ($tasks as $t) {
        if (($t['task_type'] ?? '') === 'continuous') $continuousIds[] = $t['id'];
    }
    $completionMap = pe_preloadCompletionDates($db, $continuousIds);

    foreach ($tasks as &$task) {
        // 🔒 رفع ناهماهنگی: این برگشت‌ازِ period_done به دوره‌ی بعدی قبلاً
        // فقط در overview.php/delegated-tasks.php انجام می‌شد، نه اینجا —
        // یعنی وضعیتِ یک کارِ تکرارشونده بسته به صفحه‌ای که کاربر باز
        // می‌کرد فرق داشت. حالا همه‌جا یکسان اجرا می‌شود.
        if (($task['task_type'] ?? '') === 'continuous' && !empty($task['start_date'])) {
            maybeStartNextPeriod($db, $task, $user_id, $holidays, $completionMap);
        }
        // ✅ محاسبهٔ مشترک: موعد بعدی، مهلت، معوقه‌ها
        $task = enrichTaskDates($task, $db, $holidays, $today_str, $completionMap);
    }
    unset($task); // رفع reference leak
    attachChecklistTitles($db, $tasks);
    echo json_encode(['success' => true, 'tasks' => $tasks], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور'], JSON_UNESCAPED_UNICODE);
    error_log("Get all tasks error: " . $e->getMessage());
}
