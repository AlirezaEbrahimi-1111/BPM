<?php
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

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
    
    // محاسبه تعداد دوره‌های معوقه
    $start_date = new DateTime($task['start_date']);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    $overdue_count = 0;
    switch ($task['period_type']) {
        case 'daily':
            $interval = new DateInterval('P1D');
            break;
        case 'weekly':
            $interval = new DateInterval('P7D');
            break;
        case 'monthly':
            $interval = new DateInterval('P1M');
            break;
    }
    
    $current = clone $start_date;
    while ($current < $today) {
        $overdue_count++;
        $current->add($interval);
    }
    
    // دریافت تعداد تکمیل‌های انجام شده
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM task_history WHERE task_id = ? AND action = 'completed'");
    $stmt->execute([$task_id]);
    $completed_count = $stmt->fetch()['count'];
    
    $forgiven_credit = (int)($task['overdue_forgiven_credit'] ?? 0);
    $remaining = $overdue_count - $completed_count - $forgiven_credit;
    
    if ($remaining <= 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'این کار به‌روز است و نمی‌توانید آن را تکمیل کنید',
            'overdue_count' => $overdue_count,
            'completed_count' => $completed_count
        ]);
        exit;
    }
    
    // ثبت تکمیل
    $stmt = $db->prepare("INSERT INTO task_history (task_id, from_user_id, action, notes) VALUES (?, ?, 'completed', ?)");
    $stmt->execute([$task_id, $user_id, "تکمیل دوره شماره " . ($completed_count + 1)]);
    
    // اگر همه دوره‌ها تکمیل شد
    if ($remaining == 1) {
        $db->prepare("UPDATE tasks SET status = 'in_progress', updated_at = NOW() WHERE id = ?")->execute([$task_id]);
        $message = 'کار تکمیل شد. همه دوره‌های معوقه انجام شده است.';
    } else {
        $message = "یک دوره تکمیل شد. $remaining دوره دیگر باقی مانده است.";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'remaining' => $remaining - 1,
        'completed_count' => $completed_count + 1,
        'overdue_count' => $overdue_count
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Complete recurring task error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
?>