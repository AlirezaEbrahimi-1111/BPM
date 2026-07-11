<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
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
        //  کار دوره‌ای (continuous): overdue_periods + موعد بعدی
        //  (هم‌راستا با محاسبه‌ی api/tasks/my-tasks.php)
        // ══════════════════════════════════════════════════════
        $task['overdue_periods'] = 0;
        $task['next_due_date'] = null;

        if ($task['task_type'] === 'continuous' && !empty($task['start_date'])) {
            try {
                $start_date = new DateTime($task['start_date']);
                $current_date = new DateTime($today);
                $current_date->setTime(0, 0, 0);

                maybeStartNextPeriod($db, $task, $user_id, $holidays);

                if ($current_date < $start_date) {
                    $task['next_due_date'] = $task['start_date'];
                } else {
                    $completed_count = (int) ($task['completed_count'] ?? 0);

                    $task['overdue_periods'] = calcOverduePeriods(
                        $task['period_type'],
                        $start_date,
                        $current_date,
                        $completed_count,
                        $holidays
                    );
                    $forgiven_credit = (int) ($task['overdue_forgiven_credit'] ?? 0);
                    $task['overdue_periods'] = max(0, $task['overdue_periods'] - $forgiven_credit);

                    $next_due = clone $start_date;
                    for ($i = 0; $i < $completed_count; $i++) {
                        switch ($task['period_type']) {
                            case 'daily':
                                do {
                                    $next_due->modify('+1 day');
                                } while (!isWorkingDay($next_due, $holidays));
                                break;
                            case 'weekly':
                                $next_due->modify('+1 week');
                                break;
                            case 'monthly':
                                $next_due->modify('+1 month');
                                break;
                        }
                    }
                    $task['next_due_date'] = $next_due->format('Y-m-d');
                }
            } catch (Exception $e) {
                error_log("delegated overdue calc error task#{$task['id']}: " . $e->getMessage());
            }
        }
    }
    unset($task);
    attachChecklistTitles($db, $tasks);
    echo json_encode(['success' => true, 'tasks' => $tasks], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور'], JSON_UNESCAPED_UNICODE);
    error_log("Get delegated tasks error: " . $e->getMessage());
}
?>