<?php
// api/tasks/terminate-period.php
// اتمام کار دوره‌ای توسط تعریف‌کننده

ob_start();
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
ob_clean();

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once '../../includes/Notification.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['task_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);

    $task_id = (int) $input['task_id'];
    $task = $taskManager->getTask($task_id);

    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کار یافت نشد']);
        exit;
    }

    // فقط تعریف‌کننده مجاز است
    if ($task['creator_id'] != $user_id) {
        error_log("terminate-period.php denied | user_id={$user_id} | task_id={$task_id}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط تعریف‌کننده کار مجاز به اتمام آن است']);
        exit;
    }

    // فقط کارهای دوره‌ای (periodic یا continuous)
    if (!in_array($task['task_type'], ['periodic', 'continuous'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این عملیات فقط برای کارهای دوره‌ای مجاز است']);
        exit;
    }

    // کار نباید قبلاً تکمیل یا تأیید شده باشد
    if (in_array($task['status'], ['completed', 'approved'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این کار قبلاً تکمیل شده است']);
        exit;
    }

    // تغییر وضعیت به rejected (اتمام اجباری)
    $stmt = $db->prepare("
        UPDATE tasks 
        SET status = 'rejected', 
            is_pending_approval = FALSE,
            updated_at = NOW()
        WHERE id = ?
    ");
    $result = $stmt->execute([$task_id]);

    if (!$result) {
        error_log("terminate-period.php UPDATE failed | user_id={$user_id} | task_id={$task_id}");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در اتمام کار']);
        exit;
    }

    // ثبت در تاریخچه
    $stmt = $db->prepare("
        INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
        VALUES (?, ?, ?, 'rejected', ?)
    ");
    $stmt->execute([
        $task_id,
        $user_id,
        $task['assignee_id'],
        'اتمام دوره توسط تعریف‌کننده'
    ]);

    // نوتیفیکیشن به assignee (اگر با creator فرق دارد)
    if ($task['assignee_id'] != $user_id) {
        try {
            $notification = new Notification($db);

            $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $creatorRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $creatorName = $creatorRow['full_name'] ?? 'تعریف‌کننده';

            $notification->create([
                'to_user_id' => $task['assignee_id'],
                'title'      => 'کار «' . $task['title'] . '» اتمام یافت',
                'message'    => 'کار «' . $task['title'] . '» توسط ' . $creatorName . ' به پایان رسید.',
                'type'       => 'warning',
                'link'       => '/pages/task-detail.php?id=' . $task_id,
                'related_type' => 'task',
                'related_id' => $task_id,
                'is_read'    => 0
            ]);
        } catch (Exception $notifError) {
            error_log("terminate-period notification error: " . $notifError->getMessage());
        }
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'کار با موفقیت اتمام یافت'
    ]);

} catch (Exception $e) {
    ob_end_clean();
    error_log("terminate-period error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
?>