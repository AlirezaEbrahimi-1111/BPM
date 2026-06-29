<?php
// فایل جدید: includes/NotificationHelper.php
// توابع کمکی برای مدیریت نوتیفیکیشن‌ها

class NotificationHelper {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // ارسال نوتیفیکیشن Push (اختیاری - برای آینده)
    public function sendPushNotification($user_id, $title, $message, $link = null) {
        // این متد می‌تواند با Firebase Cloud Messaging یا سرویس مشابه پیاده‌سازی شود
        // در حال حاضر فقط ساختار آن را آماده می‌کنیم
        
        try {
            // TODO: پیاده‌سازی ارسال push notification
            // برای مثال: FCM, OneSignal, etc.
            
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Push notification error: " . $e->getMessage());
            return ['success' => false];
        }
    }
    
    // ارسال ایمیل نوتیفیکیشن (اختیاری)
    public function sendEmailNotification($user_id, $subject, $message) {
        try {
            // دریافت ایمیل کاربر
            $stmt = $this->db->prepare("SELECT email FROM users WHERE id = ? AND email IS NOT NULL");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || empty($user['email'])) {
                return ['success' => false, 'message' => 'ایمیل کاربر یافت نشد'];
            }
            
            // TODO: پیاده‌سازی ارسال ایمیل
            // استفاده از PHPMailer یا کتابخانه مشابه
            
            /*
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'your-email@gmail.com';
            $mail->Password = 'your-password';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            $mail->setFrom('noreply@yourdomain.com', 'سیستم مدیریت کار');
            $mail->addAddress($user['email']);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            $mail->send();
            */
            
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Email notification error: " . $e->getMessage());
            return ['success' => false];
        }
    }
    
    // دریافت تعداد نوتیفیکیشن‌های خوانده نشده
    public function getUnreadCount($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            return $result['count'];
        } catch (Exception $e) {
            error_log("GetUnreadCount error: " . $e->getMessage());
            return 0;
        }
    }
    
    // علامت‌گذاری همه نوتیفیکیشن‌ها به عنوان خوانده شده
    public function markAllAsRead($user_id) {
        try {
            $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            return ['success' => true];
        } catch (Exception $e) {
            error_log("MarkAllAsRead error: " . $e->getMessage());
            return ['success' => false];
        }
    }
    
    // حذف نوتیفیکیشن‌های قدیمی (بیش از 30 روز)
    public function cleanOldNotifications() {
        try {
            $stmt = $this->db->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            return ['success' => true, 'deleted_count' => $stmt->rowCount()];
        } catch (Exception $e) {
            error_log("CleanOldNotifications error: " . $e->getMessage());
            return ['success' => false];
        }
    }
}
?>