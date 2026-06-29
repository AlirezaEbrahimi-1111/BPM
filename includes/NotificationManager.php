<?php
// includes/NotificationManager.php
class NotificationManager {
    private $db;
    
    const TYPE_REQUEST_SUBMITTED = 'request_submitted';
    const TYPE_APPROVAL_NEEDED = 'approval_needed';
    const TYPE_APPROVED = 'approved';
    const TYPE_REJECTED = 'rejected';
    const TYPE_DEADLINE_WARNING = 'deadline_warning';
    const TYPE_EXPIRED = 'expired';
    const TYPE_REMINDER = 'reminder';
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * ایجاد نوتیفیکیشن
     */
    public function createNotification($user_id, $request_id, $type, $title, $message, $link = null) {
        try {
            $sql = "INSERT INTO notifications (user_id, request_id, type, title, message, link)
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$user_id, $request_id, $type, $title, $message, $link]);
            
        } catch (Exception $e) {
            error_log("CreateNotification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت نوتیفیکیشن‌های کاربر
     */
    public function getUserNotifications($user_id, $unread_only = false, $limit = 50) {
        try {
            $where = ["user_id = ?"];
            $params = [$user_id];
            
            if ($unread_only) {
                $where[] = "is_read = 0";
            }
            
            $sql = "SELECT n.*,
                           r.request_type,
                           r.title as request_title
                    FROM notifications n
                    LEFT JOIN requests r ON n.request_id = r.id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY n.created_at DESC
                    LIMIT ?";
            
            $params[] = $limit;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("GetUserNotifications error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * علامت‌گذاری به عنوان خوانده شده
     */
    public function markAsRead($notification_id, $user_id) {
        try {
            $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$notification_id, $user_id]);
            
        } catch (Exception $e) {
            error_log("MarkAsRead error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * علامت‌گذاری همه به عنوان خوانده شده
     */
    public function markAllAsRead($user_id) {
        try {
            $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$user_id]);
            
        } catch (Exception $e) {
            error_log("MarkAllAsRead error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تعداد نوتیفیکیشن‌های خوانده نشده
     */
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
    
    /**
     * حذف نوتیفیکیشن
     */
    public function deleteNotification($notification_id, $user_id) {
        try {
            $sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$notification_id, $user_id]);
            
        } catch (Exception $e) {
            error_log("DeleteNotification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف همه نوتیفیکیشن‌های خوانده شده
     */
    public function deleteReadNotifications($user_id) {
        try {
            $sql = "DELETE FROM notifications WHERE user_id = ? AND is_read = 1";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$user_id]);
            
        } catch (Exception $e) {
            error_log("DeleteReadNotifications error: " . $e->getMessage());
            return false;
        }
    }
}
?>