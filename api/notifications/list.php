<?php
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
    $user_id = requireAuth();
    
    if (!$user_id) {
        throw new Exception('کاربر احراز هویت نشده است');
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $notification = new Notification($db);
    
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20;
    $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] == '1';
    
    $notifications = $notification->getUserNotifications($user_id, $limit, $unread_only);
    
    // ✅ فیلتر کردن نوتیفیکیشن‌ها
    $filtered_notifications = [];
    
    foreach ($notifications as $notif) {
        $should_show = true;
        
        if (!empty($notif['related_id']) && $notif['related_type'] === 'task') {
            $stmt = $db->prepare("
                SELECT creator_id, assignee_id 
                FROM tasks 
                WHERE id = ?
            ");
            $stmt->execute([$notif['related_id']]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($task && $task['creator_id'] == $user_id && $task['assignee_id'] == $user_id) {
                $should_show = false;
            }
            
            // چک نام در متن
            if (strpos($notif['message'], 'توسط') !== false) {
                preg_match('/توسط\s*["\"]([^"\"]+)["\"]/', $notif['message'], $matches);
                if (!empty($matches[1])) {
                    $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($current_user && trim($matches[1]) == $current_user['full_name']) {
                        $should_show = false;
                    }
                }
            }
        }
        
        if ($should_show) {
            $filtered_notifications[] = $notif;
        }
    }
    
    $unread_count = $notification->getUnreadCount($user_id);
    
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'notifications' => $filtered_notifications,
        'unread_count' => $unread_count
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit;
?>