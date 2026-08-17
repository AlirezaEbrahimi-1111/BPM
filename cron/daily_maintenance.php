<?php
// cron/daily_maintenance.php
/**
 * Cron Job نگهداری روزانه
 * اجرا: هر روز ساعت 2 صبح
 * Crontab: 0 2 * * * /usr/bin/php /path/to/cron/daily_maintenance.php
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

require_once __DIR__ . '/../config/database.php';

class DailyMaintenance {
    private $db;
    private $logFile;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->logFile = __DIR__ . '/logs/maintenance_' . date('Y-m') . '.log';
    }
    
    public function run() {
        $this->log("========== Daily Maintenance Started ==========");
        
        try {
            // 1. پاکسازی کدهای تأیید منقضی شده
            $this->cleanupVerificationCodes();
            
            // 2. محاسبه و انتقال موجودی ماه جدید
            $this->transferMonthlyBalance();
            
            // 3. بروزرسانی وضعیت ویرایش/حذف درخواست‌ها
            $this->updateRequestEditability();
            
            // 4. ارسال یادآوری به تأییدکنندگان
            $this->sendPendingReminders();
            
            // 5. آمارگیری روزانه
            $this->generateDailyStats();

            // 6. پاکسازی تنظیماتِ روزانه‌ی داشبورد (ستاره/پین) که دیگه امروز نیستن
            $this->cleanupDashboardPrefs();

            $this->log("========== Daily Maintenance Completed ==========\n");
            
        } catch (Exception $e) {
            $this->log("ERROR: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * پاکسازی کدهای تأیید منقضی شده
     */
    private function cleanupVerificationCodes() {
        $this->log("Cleaning verification codes...");
        
        $stmt = $this->db->query("
            DELETE FROM verification_codes 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");
        
        $deleted = $stmt->rowCount();
        $this->log("Verification codes cleaned: $deleted deleted");
    }
    
    /**
     * انتقال موجودی به ماه جدید
     */
    private function transferMonthlyBalance() {
        $this->log("Checking monthly balance transfer...");
        
        // فقط در روز اول ماه اجرا شود
        if (date('d') != '01') {
            $this->log("Not first day of month, skipping...");
            return;
        }
        
        $year = date('Y');
        $month = date('n');
        $prev_month = $month - 1;
        $prev_year = $year;
        
        if ($prev_month < 1) {
            $prev_month = 12;
            $prev_year--;
        }
        
        // دریافت کاربران فعال
        $stmt = $this->db->query("SELECT id, daily_work_hours FROM users WHERE is_active = 1");
        $users = $stmt->fetchAll();
        
        $created = 0;
        foreach ($users as $user) {
            // بررسی موجودی ماه قبل
            $stmt = $this->db->prepare("
                SELECT * FROM leave_balances 
                WHERE user_id = ? AND year = ? AND month = ?
            ");
            $stmt->execute([$user['id'], $prev_year, $prev_month]);
            $prev_balance = $stmt->fetch();
            
            $carry_forward = 0;
            if ($prev_balance) {
                // محاسبه مانده
                $daily_hours = $user['daily_work_hours'];
                $prev_monthly = $prev_balance['monthly_quota'] * $daily_hours;
                $prev_carry = $prev_balance['carry_forward'] * $daily_hours;
                $prev_used = ($prev_balance['used_leave'] + $prev_balance['used_pass']) * $daily_hours;
                
                $prev_remaining = $prev_monthly + $prev_carry - $prev_used;
                $carry_forward = max(0, $prev_remaining / $daily_hours);
            }
            
            // ایجاد موجودی ماه جدید
            $stmt = $this->db->prepare("
                INSERT INTO leave_balances (user_id, year, month, carry_forward)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE carry_forward = ?
            ");
            $stmt->execute([$user['id'], $year, $month, $carry_forward, $carry_forward]);
            $created++;
        }
        
        $this->log("Monthly balances created: $created");
    }
    
    /**
     * بروزرسانی قابلیت ویرایش/حذف درخواست‌ها
     */
    private function updateRequestEditability() {
        $this->log("Updating request editability...");
        
        // غیرفعال کردن ویرایش درخواست‌هایی که تأیید اول گرفته‌اند
        $tables = ['leave_requests', 'mission_requests', 'forget_requests'];
        $updated = 0;
        
        foreach ($tables as $table) {
            if ($table === 'leave_requests') {
                $stmt = $this->db->query("
                    UPDATE $table 
                    SET can_edit = 0, can_delete = 0
                    WHERE substitute_approval = 'approved'
                    AND (can_edit = 1 OR can_delete = 1)
                ");
            } else {
                $stmt = $this->db->query("
                    UPDATE $table 
                    SET can_edit = 0, can_delete = 0
                    WHERE manager_approval = 'approved'
                    AND (can_edit = 1 OR can_delete = 1)
                ");
            }
            $updated += $stmt->rowCount();
        }
        
        // غیرفعال کردن ویرایش/حذف پاس بعد از 12 ساعت
        $stmt = $this->db->query("
            UPDATE pass_requests 
            SET can_edit = 0, can_delete = 0
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 12 HOUR)
            AND (can_edit = 1 OR can_delete = 1)
        ");
        $updated += $stmt->rowCount();
        
        $this->log("Request editability updated: $updated requests");
    }
    
    /**
     * ارسال یادآوری به تأییدکنندگان
     */
    private function sendPendingReminders() {
        $this->log("Sending pending reminders...");
        
        // یادآوری برای درخواست‌هایی که 2 روز در انتظار هستند
        $sent = 0;
        
        // جانشین‌ها
        $stmt = $this->db->query("
            SELECT DISTINCT lr.substitute_id, COUNT(*) as count
            FROM leave_requests lr
            WHERE lr.status = 'pending'
            AND lr.substitute_approval = 'pending'
            AND DATEDIFF(NOW(), lr.created_at) = 2
            GROUP BY lr.substitute_id
        ");
        
        while ($row = $stmt->fetch()) {
            $this->sendNotification(
                $row['substitute_id'],
                'reminder',
                'یادآوری تأیید درخواست',
                "شما {$row['count']} درخواست در انتظار تأیید دارید"
            );
            $sent++;
        }
        
        // مدیران
        $stmt = $this->db->query("
            SELECT manager_id, COUNT(*) as count
            FROM (
                SELECT manager_id FROM leave_requests 
                WHERE status = 'pending' AND manager_approval = 'pending' AND DATEDIFF(NOW(), substitute_date) = 2
                UNION ALL
                SELECT manager_id FROM mission_requests 
                WHERE status = 'pending' AND manager_approval = 'pending' AND DATEDIFF(NOW(), created_at) = 2
                UNION ALL
                SELECT manager_id FROM forget_requests 
                WHERE status = 'pending' AND manager_approval = 'pending' AND DATEDIFF(NOW(), created_at) = 2
            ) as pending_requests
            WHERE manager_id IS NOT NULL
            GROUP BY manager_id
        ");
        
        while ($row = $stmt->fetch()) {
            $this->sendNotification(
                $row['manager_id'],
                'reminder',
                'یادآوری تأیید درخواست',
                "شما {$row['count']} درخواست در انتظار تأیید دارید"
            );
            $sent++;
        }
        
        $this->log("Reminders sent: $sent");
    }
    
    /**
     * آمارگیری روزانه
     */
    private function generateDailyStats() {
        $this->log("Generating daily stats...");
        
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // تعداد ورود/خروج
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT user_id) as total_users,
                COUNT(DISTINCT CASE WHEN check_out IS NOT NULL THEN user_id END) as completed_users,
                SUM(late_minutes) as total_late_minutes,
                SUM(penalty_minutes) as total_penalty_minutes
            FROM attendance_records
            WHERE date = ?
        ");
        $stmt->execute([$yesterday]);
        $stats = $stmt->fetch();
        
        $this->log("Yesterday stats: " . json_encode($stats));
    }
    
    /**
     * پاکسازیِ تنظیماتِ روزانه‌ی داشبورد (ستاره‌ها + تبِ/فیلترِ پیش‌فرض) —
     * خودِ bootstrap.php هم با شرطِ pref_date=CURDATE() این ردیف‌هایِ
     * قدیمی رو نادیده می‌گیره (پس درستیِ منطق به این cron وابسته نیست)،
     * این فقط برایِ جلوگیری از تجمعِ بی‌نهایتِ ردیف‌هایِ کهنه در جدوله
     */
    private function cleanupDashboardPrefs() {
        $this->log("Cleaning stale dashboard prefs...");

        $stmt = $this->db->query("
            DELETE FROM user_dashboard_prefs WHERE pref_date < CURDATE()
        ");

        $deleted = $stmt->rowCount();
        $this->log("Dashboard prefs cleaned: $deleted deleted");
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
    
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        echo $logMessage;
    }
}

$maintenance = new DailyMaintenance();
$maintenance->run();
?>
// ===================================
// cron/setup_cron.sh
// اسکریپت نصب Cron Jobs
// ===================================
#!/bin/bash

# مسیر پروژه را اینجا وارد کنید
PROJECT_PATH="/var/www/html/todo_system"

echo "Setting up cron jobs for attendance system..."

# ایجاد فایل crontab موقت
CRON_FILE="/tmp/attendance_cron"

cat > $CRON_FILE << EOF
# Attendance System Cron Jobs

# بررسی خودکار تأییدات - هر 6 ساعت
0 */6 * * * /usr/bin/php $PROJECT_PATH/cron/auto_approval_check.php >> $PROJECT_PATH/cron/logs/auto_approval.log 2>&1

# نگهداری روزانه - هر روز ساعت 2 صبح
0 2 * * * /usr/bin/php $PROJECT_PATH/cron/daily_maintenance.php >> $PROJECT_PATH/cron/logs/maintenance.log 2>&1

# پاکسازی لاگ‌های قدیمی - هر هفته یکشنبه ساعت 3 صبح
0 3 * * 0 find $PROJECT_PATH/cron/logs -name "*.log" -mtime +30 -delete

# پشتیبان‌گیری خودکار - هر روز ساعت 1 صبح
0 1 * * * $PROJECT_PATH/backup/auto_backup.sh >> $PROJECT_PATH/backup/logs/backup.log 2>&1

EOF

# نصب cron jobs
crontab $CRON_FILE

# حذف فایل موقت
rm $CRON_FILE

echo "Cron jobs installed successfully!"
echo ""
echo "Installed cron jobs:"
crontab -l

# ایجاد پوشه‌های لاگ
mkdir -p $PROJECT_PATH/cron/logs
chmod 755 $PROJECT_PATH/cron/logs

echo ""
echo "Setup completed!"