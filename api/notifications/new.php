<?php
// ==================================================
// api/notifications/new.php (برای Real-time)
// ==================================================
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
try {
    $user_id = requireAuth();
    
    $since_id = isset($_GET['since']) ? (int)$_GET['since'] : 0;
    
    $database = new Database();
    $db = $database->getConnection();
    
    $notification = new Notification($db);
    $new_notifications = $notification->getNewNotifications($user_id, $since_id);
    
    // ✅ فیلتر کردن نوتیفیکیشن‌هایی که مربوط به کارهای خودم است
    $filtered_notifications = [];
    
    foreach ($new_notifications as $notif) {
        $should_show = true;
        
        // اگر نوتیفیکیشن مربوط به یک کار است
        if (!empty($notif['related_id']) && $notif['related_type'] === 'task') {
            // بررسی کنیم آیا creator و assignee هر دو خودم هستند
            $stmt = $db->prepare("
                SELECT creator_id, assignee_id 
                FROM tasks 
                WHERE id = ?
            ");
            $stmt->execute([$notif['related_id']]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($task) {
                // ✅ اگر هم creator و هم assignee خودم باشم، نوتیفیکیشن نشان نده
                if ($task['creator_id'] == $user_id && $task['assignee_id'] == $user_id) {
                    $should_show = false;
                }
                
                // ✅ یا اگر متن نوتیفیکیشن نشان دهد که خودم تکمیل کرده‌ام
                if (strpos($notif['message'], 'توسط') !== false) {
                    // استخراج نام از متن: "توسط «نام»"
                    preg_match('/توسط\s*["\"]([^"\"]+)["\"]/', $notif['message'], $matches);
                    if (!empty($matches[1])) {
                        $performer_name = trim($matches[1]);
                        
                        // دریافت نام کامل کاربر فعلی
                        $stmt = $db->prepare("
                            SELECT CONCAT(first_name, ' ', last_name) as full_name 
                            FROM users 
                            WHERE id = ?
                        ");
                        $stmt->execute([$user_id]);
                        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($current_user && $performer_name == $current_user['full_name']) {
                            $should_show = false;
                        }
                    }
                }
            }
        }
        
        if ($should_show) {
            $filtered_notifications[] = $notif;
        }
    }
    
    $response = [
        'success' => true,
        'new_count' => count($filtered_notifications),
        'notifications' => $filtered_notifications
    ];
    
    if (count($filtered_notifications) > 0) {
        $response['latest_id'] = $filtered_notifications[0]['id'];
        $response['latest_notification'] = $filtered_notifications[0];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("notifications/new.php failed | user_id=" . ($user_id ?? 'null') . " | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
?>