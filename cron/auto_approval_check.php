<?php
// cron/auto_approval_check.php
/**
 * Cron Job برای بررسی خودکار تأییدات
 *
 * قوانین:
 * - مهلتِ نهایی (رد خودکار): از تنظیمِ approval_deadline_days
 *   (مدیریت → تنظیمات)، پیش‌فرض ۳ روز
 * - ارجاع به مقامِ بالاتر: یک روز زودتر از مهلتِ نهایی
 *
 * اجرا: هر 6 ساعت یک بار
 * Crontab: 0 6 * * * /usr/bin/php /path/to/cron/auto_approval_check.php */

// جلوگیری از دسترسی مستقیم از مرورگر
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/settings_helper.php';

class AutoApprovalChecker {
    private $db;
    private $logFile;
    private $deadlineDays;
    private $escalationDays;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->logFile = __DIR__ . '/logs/auto_approval_' . date('Y-m') . '.log';

        // ایجاد پوشه logs اگر وجود ندارد
        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }

        // مهلتِ رد خودکار از تنظیماتِ سازمان (قبلاً هاردکد ۵ روز بود و
        // اصلاً به approval_deadline_days گوش نمی‌داد — همون چیزی که
        // باعث می‌شد تغییرِ عدد توی صفحه‌ی تنظیمات هیچ اثری نداشته باشه)
        $settings = loadSettings($this->db);
        $this->deadlineDays = max(1, (int) ($settings['approval_deadline_days'] ?? 3));
        $this->escalationDays = max(1, $this->deadlineDays - 1);
    }
    
    /**
     * اجرای اصلی
     */
    public function run() {
        $this->log("========== Starting Auto Approval Check ==========");
        $this->log("Date: " . date('Y-m-d H:i:s'));
        
        try {
            // بررسی درخواست‌های مرخصی
            $this->checkLeaveRequests();
            
            // بررسی درخواست‌های مأموریت
            $this->checkMissionRequests();
            
            // بررسی درخواست‌های فراموشی
            $this->checkForgetRequests();
            
            // بررسی درخواست‌های منقضی شده
            $this->checkExpiredRequests();
            
            // پاکسازی نوتیفیکیشن‌های قدیمی
            $this->cleanupOldNotifications();
            
            $this->log("========== Auto Approval Check Completed ==========\n");
            
        } catch (Exception $e) {
            $this->log("ERROR: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * بررسی درخواست‌های مرخصی
     */
    private function checkLeaveRequests() {
        $this->log("Checking leave requests...");
        
        // درخواست‌هایی که جانشین 4 روز پاسخ نداده
        $stmt = $this->db->query("
            SELECT lr.*, u.first_name, u.last_name, u.manager_id
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.id
            WHERE lr.status = 'pending'
            AND lr.current_approver_role = 'substitute'
            AND lr.substitute_approval = 'pending'
            AND DATEDIFF(NOW(), lr.created_at) >= {$this->escalationDays}
        ");
        
        $escalated = 0;
        while ($request = $stmt->fetch()) {
            // ارجاع به مدیر
            $this->escalateToManager($request, 'leave_requests');
            $escalated++;
        }
        
        // درخواست‌هایی که مدیر 4 روز پاسخ نداده
        $stmt = $this->db->query("
            SELECT lr.*, u.first_name, u.last_name
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.id
            WHERE lr.status = 'pending'
            AND lr.current_approver_role = 'manager'
            AND lr.manager_approval = 'pending'
            AND lr.substitute_approval = 'approved'
            AND DATEDIFF(NOW(), lr.substitute_date) >= {$this->escalationDays}
        ");
        
        while ($request = $stmt->fetch()) {
            // ارجاع به مسئول
            $this->escalateToSupervisor($request, 'leave_requests');
            $escalated++;
        }
        
        $this->log("Leave requests: $escalated escalated");
    }
    
    /**
     * بررسی درخواست‌های مأموریت
     */
    private function checkMissionRequests() {
        $this->log("Checking mission requests...");
        
        // درخواست‌هایی که مدیر 4 روز پاسخ نداده
        $stmt = $this->db->query("
            SELECT mr.*, u.first_name, u.last_name
            FROM mission_requests mr
            JOIN users u ON mr.user_id = u.id
            WHERE mr.status = 'pending'
            AND mr.current_approver_role = 'manager'
            AND mr.manager_approval = 'pending'
            AND DATEDIFF(NOW(), mr.created_at) >= {$this->escalationDays}
        ");
        
        $escalated = 0;
        while ($request = $stmt->fetch()) {
            // ارجاع به مسئول
            $this->escalateToSupervisor($request, 'mission_requests');
            $escalated++;
        }
        
        $this->log("Mission requests: $escalated escalated");
    }
    
    /**
     * بررسی درخواست‌های فراموشی
     */
    private function checkForgetRequests() {
        $this->log("Checking forget requests...");
        
        // درخواست‌هایی که مدیر 4 روز پاسخ نداده
        $stmt = $this->db->query("
            SELECT fr.*, u.first_name, u.last_name
            FROM forget_requests fr
            JOIN users u ON fr.user_id = u.id
            WHERE fr.status = 'pending'
            AND fr.current_approver_role = 'manager'
            AND fr.manager_approval = 'pending'
            AND DATEDIFF(NOW(), fr.created_at) >= {$this->escalationDays}
        ");
        
        $escalated = 0;
        while ($request = $stmt->fetch()) {
            // ارجاع به مسئول
            $this->escalateToSupervisor($request, 'forget_requests');
            $escalated++;
        }
        
        $this->log("Forget requests: $escalated escalated");
    }
    
    /**
     * بررسی درخواست‌های منقضی شده (بیش از مهلتِ approval_deadline_days)
     */
    private function checkExpiredRequests() {
        $this->log("Checking expired requests... (deadline={$this->deadlineDays}d)");

        $expired = 0;

        // درخواست‌های مرخصی منقضی شده (بیشتر از مهلتِ تنظیم‌شده از ارسال)
        // و تاریخ شروع مرخصی هم گذشته باشد
        $stmt = $this->db->query("
            SELECT lr.*
            FROM leave_requests lr
            WHERE lr.status = 'pending'
            AND DATEDIFF(NOW(), lr.created_at) >= {$this->deadlineDays}
            AND lr.start_date < CURDATE()
        ");
        
        while ($request = $stmt->fetch()) {
            // رد خودکار
            $this->autoReject($request['id'], 'leave_requests', 
                'درخواست به دلیل عدم تأیید در مهلت مقرر، به صورت خودکار رد شد');
            $expired++;
        }
        
        // درخواست‌های مأموریت منقضی شده
        $stmt = $this->db->query("
            SELECT mr.*
            FROM mission_requests mr
            WHERE mr.status = 'pending'
            AND DATEDIFF(NOW(), mr.created_at) >= {$this->deadlineDays}
            AND mr.start_date < NOW()
        ");
        
        while ($request = $stmt->fetch()) {
            $this->autoReject($request['id'], 'mission_requests', 
                'درخواست به دلیل عدم تأیید در مهلت مقرر، به صورت خودکار رد شد');
            $expired++;
        }
        
        // درخواست‌های فراموشی منقضی شده
        $stmt = $this->db->query("
            SELECT fr.*
            FROM forget_requests fr
            WHERE fr.status = 'pending'
            AND DATEDIFF(NOW(), fr.created_at) >= {$this->deadlineDays}
        ");
        
        while ($request = $stmt->fetch()) {
            $this->autoReject($request['id'], 'forget_requests', 
                'درخواست به دلیل عدم تأیید در مهلت مقرر، به صورت خودکار رد شد');
            $expired++;
        }
        
        $this->log("Expired requests: $expired auto-rejected");
    }
    
    /**
     * ارجاع به مدیر
     */
    private function escalateToManager($request, $table) {
        try {
            $this->db->beginTransaction();
            
            // بروزرسانی درخواست
            $stmt = $this->db->prepare("
                UPDATE $table 
                SET current_approver_role = 'manager',
                    manager_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$request['manager_id'], $request['id']]);
            
            // ارسال نوتیفیکیشن به مدیر
            if ($request['manager_id']) {
                $this->sendNotification(
                    $request['manager_id'],
                    'approval_needed',
                    'درخواست ارجاع شده',
                    'درخواستی که جانشین پاسخ نداده به شما ارجاع شد'
                );
            }
            
            // اطلاع به کاربر
            $this->sendNotification(
                $request['user_id'],
                'request_escalated',
                'ارجاع به مدیر',
                'درخواست شما به دلیل عدم پاسخ جانشین، به مدیر ارجاع داده شد'
            );
            
            $this->db->commit();
            $this->log("Request #{$request['id']} escalated to manager");
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->log("Error escalating to manager: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * ارجاع به مسئول
     */
    private function escalateToSupervisor($request, $table) {
        try {
            $this->db->beginTransaction();
            
            // دریافت مسئول (فقط در سازمانِ خودِ درخواست‌دهنده)
            $stmt = $this->db->prepare("SELECT organization_id FROM users WHERE id = ?");
            $stmt->execute([$request['user_id']]);
            $requester_org_id = $stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT id FROM users WHERE is_supervisor = 1 AND organization_id = ? LIMIT 1");
            $stmt->execute([$requester_org_id]);
            $supervisor = $stmt->fetch();
            
            if (!$supervisor) {
                throw new Exception("No supervisor found");
            }
            
            // بروزرسانی درخواست
            $supervisorField = ($table === 'leave_requests') ? 'supervisor_id' : null;
            
            if ($supervisorField) {
                $stmt = $this->db->prepare("
                    UPDATE $table 
                    SET current_approver_role = 'supervisor',
                        $supervisorField = ?
                    WHERE id = ?
                ");
                $stmt->execute([$supervisor['id'], $request['id']]);
            } else {
                $stmt = $this->db->prepare("
                    UPDATE $table 
                    SET current_approver_role = 'supervisor'
                    WHERE id = ?
                ");
                $stmt->execute([$request['id']]);
            }
            
            // ارسال نوتیفیکیشن به مسئول
            $this->sendNotification(
                $supervisor['id'],
                'approval_needed',
                'درخواست ارجاع شده',
                'درخواستی که مدیر پاسخ نداده به شما ارجاع شد'
            );
            
            // اطلاع به کاربر
            $this->sendNotification(
                $request['user_id'],
                'request_escalated',
                'ارجاع به مسئول',
                'درخواست شما به دلیل عدم پاسخ مدیر، به مسئول ارجاع داده شد'
            );
            
            $this->db->commit();
            $this->log("Request #{$request['id']} escalated to supervisor");
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->log("Error escalating to supervisor: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * رد خودکار درخواست
     */
    private function autoReject($request_id, $table, $reason) {
        try {
            $this->db->beginTransaction();
            
            // بروزرسانی وضعیت
            $stmt = $this->db->prepare("
                UPDATE $table 
                SET status = 'rejected',
                    can_edit = 0,
                    can_delete = 0,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$request_id]);
            
            // دریافت اطلاعات درخواست برای اطلاع‌رسانی
            $stmt = $this->db->prepare("SELECT user_id FROM $table WHERE id = ?");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
            
            if ($request) {
                // اطلاع به کاربر
                $this->sendNotification(
                    $request['user_id'],
                    'request_rejected',
                    'درخواست رد شد',
                    $reason
                );
            }
            
            $this->db->commit();
            $this->log("Request #{$request_id} auto-rejected");
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->log("Error auto-rejecting: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * ارسال نوتیفیکیشن
     */
    private function sendNotification($user_id, $type, $title, $message) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, type, title, message)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $type, $title, $message]);
        } catch (Exception $e) {
            $this->log("Error sending notification: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * پاکسازی نوتیفیکیشن‌های قدیمی (بیش از 30 روز)
     */
    private function cleanupOldNotifications() {
        $this->log("Cleaning up old notifications...");
        
        try {
            $stmt = $this->db->query("
                DELETE FROM notifications 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            $deleted = $stmt->rowCount();
            $this->log("Old notifications cleaned: $deleted deleted");
            
        } catch (Exception $e) {
            $this->log("Error cleaning notifications: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * ثبت لاگ
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        
        // نمایش در کنسول
        echo $logMessage;
    }
}

// اجرا
$checker = new AutoApprovalChecker();
$checker->run();
?>