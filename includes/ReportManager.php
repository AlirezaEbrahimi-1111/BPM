<?php
require_once __DIR__ . '/task-status-helper.php';

class ReportManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // تولید گزارش روزانه خودکار
    public function generateDailyReport($user_id, $activity_unit, $date = null) {
        if (!$date) $date = date('Y-m-d');
        
        try {
            // دریافت اطلاعات کاربر
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'کاربر یافت نشد'];
            }
            
            // دریافت کارهای روز
            $stmt = $this->db->prepare("
                SELECT t.*, 
                       creator.first_name as creator_first_name,
                       creator.last_name as creator_last_name
                FROM tasks t 
                LEFT JOIN users creator ON t.creator_id = creator.id 
                WHERE t.assignee_id = ? 
                AND (t.due_date = ? OR t.task_type = 'continuous')
                ORDER BY t.priority DESC, t.due_date ASC
            ");
            $stmt->execute([$user_id, $date]);
            $tasks = $stmt->fetchAll();
            
            // تولید محتوای گزارش
            $content = $this->buildReportContent($user, $tasks, $date, $activity_unit);
            
            return [
                'success' => true,
                'content' => $content,
                'tasks_count' => count($tasks)
            ];
            
        } catch (Exception $e) {
            error_log("Generate daily report error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطا در تولید گزارش'];
        }
    }
    
    // ساخت محتوای گزارش
    private function buildReportContent($user, $tasks, $date, $activity_unit) {
        $content = '';
        
        // پیشوند
        if ($user['report_prefix']) {
            $content .= $user['report_prefix'] . "\n\n";
        }
        
        // عنوان گزارش
        $persian_date = $this->convertToJalali($date);
        $unit_names = [
            'RS' => 'کامپیوتر',
            'ATM' => 'فضای مجازی + رسانه', 
            'AM' => 'نوجوانان',
            'AC' => 'حسابداری',
            'PR' => 'روابط عمومی',
            'HE' => 'تربیتی'
        ];
        
        $content .= "گزارش روزانه " . ($unit_names[$activity_unit] ?? $activity_unit) . "\n";
        $content .= "تاریخ: " . $persian_date . "\n\n";
        
        // فعالیت‌ها
        if (empty($tasks)) {
            $content .= "امروز کار خاصی در این واحد انجام نداده‌ام.\n\n";
        } else {
            $content .= "فعالیت‌های انجام شده:\n\n";
            
            $completed_tasks = array_filter($tasks, function($task) {
                return $task['status'] === 'completed';
            });
            
            $pending_tasks = array_filter($tasks, function($task) {
                return $task['status'] !== 'completed';
            });
            
            // کارهای تکمیل شده
            if (!empty($completed_tasks)) {
                $content .= "✅ کارهای تکمیل شده:\n";
                foreach ($completed_tasks as $index => $task) {
                    $content .= ($index + 1) . ". " . $task['title'];
                    if ($task['description']) {
                        $content .= " - " . $task['description'];
                    }
                    $content .= "\n";
                }
                $content .= "\n";
            }
            
            // کارهای در حال انجام
            if (!empty($pending_tasks)) {
                $content .= "🔄 کارهای در حال انجام:\n";
                foreach ($pending_tasks as $index => $task) {
                    // 🔒 لیستِ محلیِ ناقصِ برچسب‌ها حذف شد — TASK_STATUS_LABELS
                    // (includes/task-status-helper.php) تنها مرجعِ سمتِ PHP ئه؛
                    // نسخه‌ی قبلی فقط ۴ از ۱۰ وضعیت رو داشت و برایِ بقیه
                    // (pending_approval/approved/rejected/period_done/
                    // termination_requested) متنِ خامِ انگلیسی نشون می‌داد
                    $content .= ($index + 1) . ". " . $task['title'] . " - " . (TASK_STATUS_LABELS[$task['status']] ?? $task['status']);
                    if ($task['description']) {
                        $content .= " (" . $task['description'] . ")";
                    }
                    $content .= "\n";
                }
                $content .= "\n";
            }
        }
        
        // پسوند
        if ($user['report_suffix']) {
            $content .= $user['report_suffix'];
        }
        
        return trim($content);
    }
    
    // تبدیل تاریخ میلادی به شمسی
    private function convertToJalali($date) {
        // اینجا باید از کتابخانه تبدیل تاریخ استفاده کنید
        // برای سادگی، فقط فرمت ساده برمی‌گردانیم
        return date('Y/m/d', strtotime($date));
    }
    
    // جستجو در گزارش‌ها
    public function searchReports($user_id, $query, $filters = []) {
        try {
            $where_conditions = ["user_id = ?"];
            $params = [$user_id];
            
            // جستجوی متنی
            if (!empty($query)) {
                $where_conditions[] = "MATCH(content) AGAINST(? IN NATURAL LANGUAGE MODE)";
                $params[] = $query;
            }
            
            // فیلترهای اضافی
            if (!empty($filters['unit'])) {
                $where_conditions[] = "activity_unit = ?";
                $params[] = $filters['unit'];
            }
            
            if (!empty($filters['date_from'])) {
                $where_conditions[] = "report_date >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where_conditions[] = "report_date <= ?";
                $params[] = $filters['date_to'];
            }
            
            $sql = "SELECT *, MATCH(content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM reports 
                    WHERE " . implode(' AND ', $where_conditions) . "
                    ORDER BY relevance DESC, created_at DESC
                    LIMIT 50";
            
            array_unshift($params, $query ?: '');
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Search reports error: " . $e->getMessage());
            return [];
        }
    }
    
    // آمار گزارش‌ها
    public function getReportsStats($user_id) {
        try {
            $stats = [];
            
            // کل گزارش‌ها
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM reports WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats['total'] = $stmt->fetch()['count'];
            
            // گزارش‌های این ماه
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM reports WHERE user_id = ? AND MONTH(report_date) = MONTH(NOW()) AND YEAR(report_date) = YEAR(NOW())");
            $stmt->execute([$user_id]);
            $stats['this_month'] = $stmt->fetch()['count'];
            
            // گزارش‌های این هفته
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM reports WHERE user_id = ? AND YEARWEEK(report_date) = YEARWEEK(NOW())");
            $stmt->execute([$user_id]);
            $stats['this_week'] = $stmt->fetch()['count'];
            
            // میانگین گزارش‌ها در ماه
            $stmt = $this->db->prepare("SELECT AVG(monthly_count) as avg_monthly FROM (SELECT COUNT(*) as monthly_count FROM reports WHERE user_id = ? GROUP BY YEAR(report_date), MONTH(report_date)) as monthly_reports");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            $stats['avg_monthly'] = round($result['avg_monthly'] ?? 0, 1);
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Reports stats error: " . $e->getMessage());
            return [];
        }
    }
}