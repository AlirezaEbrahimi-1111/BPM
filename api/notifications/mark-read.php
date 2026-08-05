<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    
$database = new Database();
    $db = $database->getConnection();

    // حالت ۱: mark read با شناسه نوتیفیکیشن
    if (!empty($input['id'])) {
        $notification = new Notification($db);
        $result = $notification->markAsRead($input['id'], $user_id);
    }
    // حالت ۲: mark read همه نوتیفیکیشن‌های یک تسک
    elseif (!empty($input['task_id'])) {
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE user_id = ? AND related_id = ? AND related_type = 'task' AND is_read = 0
        ");
        $result = $stmt->execute([$user_id, (int)$input['task_id']]);
    }
    else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه اعلان یا تسک الزامی است']);
        exit;
    }

    echo json_encode([
        'success' => $result,
        'message' => $result ? 'اعلان خوانده شد' : 'خطا در علامت‌گذاری'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("notifications/mark-read.php failed | user_id=" . ($user_id ?? 'null') . " | id=" . ($input['id'] ?? 'null') . " | task_id=" . ($input['task_id'] ?? 'null') . " | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>