<?php
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period-engine.php';
try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);

    $task_id = $input['task_id'];

    $database = new Database();
    $db = $database->getConnection();

    // دریافت اطلاعات کار
    $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();

    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'کار یافت نشد']);
        exit;
    }

    // بررسی نوع کار
    if ($task['task_type'] !== 'continuous') {
        echo json_encode(['success' => false, 'message' => 'این کار دوره‌ای نیست']);
        exit;
    }

    // ✅ موتور مشترک دوره
    $holidays = getHolidaySet($db);
    $state    = pe_state($db, $task, $holidays);

    if (!$state['can_complete']) {
        $msg = $state['is_today_done']
            ? 'دورهٔ امروز قبلاً تکمیل شده است'
            : 'این کار در وضعیت قابل تکمیل نیست';
        echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ثبت تکمیل — دورهٔ امروز بسته می‌شود
    $stmt = $db->prepare("
        INSERT INTO task_history (task_id, from_user_id, action, notes)
        VALUES (?, ?, 'completed', ?)
    ");
    $stmt->execute([
        $task_id,
        $user_id,
        'دورهٔ ' . $state['current_period_date'] . ' تکمیل شد'
    ]);

    // وضعیت و تاریخ آخرین تکمیل
    $db->prepare("
        UPDATE tasks
        SET status = 'period_done',
            last_completed_date = ?,
            updated_at = NOW()
        WHERE id = ?
    ")->execute([date('Y-m-d'), $task_id]);

    // وضعیت تازه (بعد از ثبت تکمیل)
    $after = pe_state($db, $task, $holidays);

    echo json_encode([
        'success'         => true,
        'message'         => $after['overdue_periods'] > 0
            ? "دورهٔ امروز تکمیل شد. {$after['overdue_periods']} دورهٔ معوقه باقی است."
            : 'دورهٔ امروز تکمیل شد.',
        'next_due_date'   => $after['next_due_date'],
        'overdue_periods' => $after['overdue_periods'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Complete recurring task error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
