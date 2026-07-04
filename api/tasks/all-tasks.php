<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php'; // ← اضافه شد
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/checklist-search-helper.php';
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

    foreach ($tasks as &$task) {

        // تعداد دوره‌های تکمیل‌شده (اگر قبلاً join نشده)
        if (!isset($task['completed_count'])) {
            $stmt = $db->prepare(
                "SELECT COUNT(*) as count FROM task_history WHERE task_id = ? AND action = 'completed'"
            );
            $stmt->execute([$task['id']]);
            $task['completed_count'] = (int) $stmt->fetch()['count'];
        }

        $task['working_days_delayed'] = 0; // مقدار پیش‌فرض

        // ══════════════════════════════════════════════════════
        //  کار دوره‌ای (continuous)
        // ══════════════════════════════════════════════════════
        if ($task['task_type'] === 'continuous' && !empty($task['start_date'])) {

            $start_date = new DateTime($task['start_date']);
            $today_dt   = new DateTime($today_str);
            $today_dt->setTime(0, 0, 0);

            // ✅ محاسبه با روزهای کاری
            $task['overdue_periods'] = calcOverduePeriods(
                $task['period_type'],
                $start_date,
                $today_dt,
                (int) $task['completed_count'],
                $holidays
            );

            // اگر تاریخ پایان گذشته، دیگر یادآوری نکن
            if (!empty($task['end_date']) && $today_str > $task['end_date']) {
                $task['overdue_periods'] = 0;
            }

        }
        // ══════════════════════════════════════════════════════
        //  کار مقطعی (periodic)
        // ══════════════════════════════════════════════════════
        elseif ($task['task_type'] === 'periodic') {
            $task['overdue_periods'] = 0;

            // پیدا کردن بزرگ‌ترین تاریخ سررسید
            $dates = [];
            if (!empty($task['due_date']))          $dates[] = new DateTime($task['due_date']);
            if (!empty($task['deadline']))          $dates[] = new DateTime($task['deadline']);
            if (!empty($task['original_deadline'])) $dates[] = new DateTime($task['original_deadline']);

            if (!empty($dates)) {
                $max_date    = max($dates);
                $today_dt    = new DateTime($today_str);

                // ✅ تاخیر به روز کاری
                if ($today_dt > $max_date &&
                    ($task['status'] ?? '') !== 'completed' &&
                    ($task['status'] ?? '') !== 'approved'
                ) {
                    $task['working_days_delayed'] = calcPeriodicDelayWorkingDays(
                        $max_date->format('Y-m-d'),
                        $today_str,
                        $holidays
                    );
                }
            }
        } else {
            $task['overdue_periods'] = 0;
        }
    }
    unset($task); // رفع reference leak
attachChecklistTitles($db, $tasks);
    echo json_encode(['success' => true, 'tasks' => $tasks], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور'], JSON_UNESCAPED_UNICODE);
    error_log("Get all tasks error: " . $e->getMessage());
}