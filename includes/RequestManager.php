<?php
// includes/RequestManager.php

class RequestManager {
    private $db;
    
    // انواع درخواست
    const TYPE_MISSION = 'mission';
    const TYPE_LEAVE = 'leave';
    const TYPE_PASS = 'pass';
    const TYPE_TECHNICAL = 'technical';
    const TYPE_FORGET = 'forget';
    
    // وضعیت‌ها
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_WAITING_SUBSTITUTE = 'waiting_substitute';
    const STATUS_WAITING_MANAGER = 'waiting_manager';
    const STATUS_WAITING_SUPERVISOR = 'waiting_supervisor';
    const STATUS_WAITING_IT = 'waiting_it';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * ایجاد درخواست جدید
     */
    public function createRequest($data, $user_id) {
        try {
            // اعتبارسنجی داده‌ها
            $validation = $this->validateRequest($data, $user_id);
            if (!$validation['success']) {
                return $validation;
            }
            
            // بررسی محدودیت 3 روز بعد از تاریخ
            if (!$this->checkDateLimit($data['start_datetime'])) {
                return [
                    'success' => false, 
                    'message' => 'امکان ارسال درخواست بیشتر از 3 روز بعد از تاریخ رویداد وجود ندارد.'
                ];
            }
            
            // بررسی محدودیت‌های ماهانه
            if (in_array($data['request_type'], [self::TYPE_PASS, self::TYPE_FORGET])) {
                $limitCheck = $this->checkMonthlyLimit(
                    $user_id, 
                    $data['request_type'], 
                    $data['start_datetime'],
                    $data['duration_hours'] ?? 0
                );
                
                if (!$limitCheck['can_submit']) {
                    return [
                        'success' => false,
                        'message' => $limitCheck['message']
                    ];
                }
            }
            
            // تعیین وضعیت و تأییدکننده اولیه
            $status = $this->determineInitialStatus($data['request_type'], $data);
            $currentApprover = $this->determineCurrentApprover($data['request_type'], $data);
            
            // درج درخواست
            $sql = "INSERT INTO requests (
                        request_type, user_id, title, description, location,
                        start_datetime, end_datetime, substitute_id, manager_id,
                        status, current_approver_role, duration_hours
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['request_type'],
                $user_id,
                $data['title'],
                $data['description'] ?? null,
                $data['location'] ?? null,
                $data['start_datetime'],
                $data['end_datetime'],
                $data['substitute_id'] ?? null,
                $this->getUserManager($user_id),
                $status,
                $currentApprover,
                $data['duration_hours'] ?? null
            ]);
            
            if ($result) {
                $request_id = $this->db->lastInsertId();
                
                return [
                    'success' => true,
                    'request_id' => $request_id,
                    'message' => 'درخواست با موفقیت ایجاد شد.'
                ];
            }
            
            return ['success' => false, 'message' => 'خطا در ایجاد درخواست'];
            
        } catch (Exception $e) {
            error_log("CreateRequest error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }
    
    /**
     * ارسال درخواست (تبدیل از draft به pending)
     */
    public function submitRequest($request_id, $user_id) {
        try {
            // بررسی مالکیت
            $request = $this->getRequest($request_id);
            if (!$request || $request['user_id'] != $user_id) {
                return ['success' => false, 'message' => 'درخواست یافت نشد'];
            }
            
            if ($request['status'] != self::STATUS_DRAFT) {
                return ['success' => false, 'message' => 'درخواست قبلاً ارسال شده است'];
            }
            
            // تعیین وضعیت بعدی
            $nextStatus = $this->determineInitialStatus($request['request_type'], $request);
            $currentApprover = $this->determineCurrentApprover($request['request_type'], $request);
            
            // بروزرسانی درخواست
            $sql = "UPDATE requests 
                    SET status = ?, 
                        current_approver_role = ?,
                        submitted_at = NOW(),
                        approval_deadline = CASE 
                            WHEN ? != 'pass' THEN DATE_ADD(NOW(), INTERVAL 48 HOUR)
                            ELSE NULL
                        END
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            if ($stmt->execute([$nextStatus, $currentApprover, $request['request_type'], $request_id])) {
                return ['success' => true, 'message' => 'درخواست با موفقیت ارسال شد'];
            }
            
            return ['success' => false, 'message' => 'خطا در ارسال درخواست'];
            
        } catch (Exception $e) {
            error_log("SubmitRequest error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }
    
    /**
     * ویرایش درخواست
     */
    public function updateRequest($request_id, $data, $user_id) {
        try {
            $request = $this->getRequest($request_id);
            
            if (!$request || $request['user_id'] != $user_id) {
                return ['success' => false, 'message' => 'درخواست یافت نشد'];
            }
            
            if (!$request['can_edit']) {
                return ['success' => false, 'message' => 'امکان ویرایش این درخواست وجود ندارد'];
            }
            
            // بررسی مهلت ویرایش برای پاس (24 ساعت)
            if ($request['request_type'] == self::TYPE_PASS) {
                $created = strtotime($request['created_at']);
                if (time() - $created > 86400) { // 24 ساعت
                    return ['success' => false, 'message' => 'مهلت ویرایش پاس (24 ساعت) گذشته است'];
                }
            }
            
            // اعتبارسنجی
            $validation = $this->validateRequest($data, $user_id);
            if (!$validation['success']) {
                return $validation;
            }
            
            // بروزرسانی
            $fields = [];
            $values = [];
            
            $allowedFields = ['title', 'description', 'location', 'start_datetime', 'end_datetime', 'substitute_id'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                return ['success' => false, 'message' => 'هیچ تغییری اعمال نشد'];
            }
            
            $values[] = $request_id;
            $sql = "UPDATE requests SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            
            if ($stmt->execute($values)) {
                return ['success' => true, 'message' => 'درخواست با موفقیت بروزرسانی شد'];
            }
            
            return ['success' => false, 'message' => 'خطا در بروزرسانی'];
            
        } catch (Exception $e) {
            error_log("UpdateRequest error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }
    
    /**
     * تأیید یا رد درخواست
     */
    public function approveRequest($request_id, $user_id, $action, $notes = null) {
        try {
            $request = $this->getRequest($request_id);
            
            if (!$request) {
                return ['success' => false, 'message' => 'درخواست یافت نشد'];
            }
            
            // بررسی مجوز تأیید
            $canApprove = $this->canUserApprove($request, $user_id);
            if (!$canApprove['can_approve']) {
                return ['success' => false, 'message' => $canApprove['message']];
            }
            
            // ثبت تأیید/رد
            $sql = "INSERT INTO request_approvals (request_id, approver_id, approver_role, action, notes)
                    VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $request_id,
                $user_id,
                $canApprove['role'],
                $action,
                $notes
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => $action == 'approved' ? 'درخواست تأیید شد' : 'درخواست رد شد'
                ];
            }
            
            return ['success' => false, 'message' => 'خطا در ثبت تأیید'];
            
        } catch (Exception $e) {
            error_log("ApproveRequest error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }
    
    /**
     * لغو درخواست
     */
    public function cancelRequest($request_id, $user_id) {
        try {
            $request = $this->getRequest($request_id);
            
            if (!$request || $request['user_id'] != $user_id) {
                return ['success' => false, 'message' => 'درخواست یافت نشد'];
            }
            
            if ($request['final_status'] != 'pending') {
                return ['success' => false, 'message' => 'امکان لغو درخواست تأیید یا رد شده وجود ندارد'];
            }
            
            $sql = "UPDATE requests SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            
            if ($stmt->execute([$request_id])) {
                return ['success' => true, 'message' => 'درخواست لغو شد'];
            }
            
            return ['success' => false, 'message' => 'خطا در لغو درخواست'];
            
        } catch (Exception $e) {
            error_log("CancelRequest error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }
    
    /**
     * دریافت لیست درخواست‌های کاربر
     */
    public function getUserRequests($user_id, $filters = []) {
        try {
            $where = ["r.user_id = ?"];
            $params = [$user_id];
            
            // فیلتر نوع درخواست
            if (!empty($filters['request_type'])) {
                $where[] = "r.request_type = ?";
                $params[] = $filters['request_type'];
            }
            
            // فیلتر وضعیت
            if (!empty($filters['status'])) {
                $where[] = "r.status = ?";
                $params[] = $filters['status'];
            }
            
            // فیلتر تاریخ
            if (!empty($filters['date_from'])) {
                $where[] = "DATE(r.start_datetime) >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "DATE(r.start_datetime) <= ?";
                $params[] = $filters['date_to'];
            }
            
            $sql = "SELECT r.*, 
                           manager.first_name as manager_first_name,
                           manager.last_name as manager_last_name,
                           substitute.first_name as substitute_first_name,
                           substitute.last_name as substitute_last_name
                    FROM requests r
                    LEFT JOIN users manager ON r.manager_id = manager.id
                    LEFT JOIN users substitute ON r.substitute_id = substitute.id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY r.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("GetUserRequests error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت درخواست‌های نیازمند تأیید توسط کاربر
     */
    public function getPendingApprovalsForUser($user_id) {
        try {
            $user = $this->getUserRole($user_id);
            
            if (!$user) {
                return [];
            }
            
            $conditions = [];
            
            // اگر جانشین است
            $conditions[] = "(r.substitute_id = ? AND r.status = 'waiting_substitute')";
            
            // اگر مدیر است
            if ($user['role'] == 'manager') {
                $conditions[] = "(r.manager_id = ? AND r.status = 'waiting_manager')";
            }
            
            // اگر مسئول است
            if ($user['role'] == 'supervisor') {
                $conditions[] = "(r.status = 'waiting_supervisor')";
            }
            
            // اگر مسئول IT است
            if ($user['role'] == 'it_manager') {
                $conditions[] = "(r.status = 'waiting_it')";
            }
            
            if (empty($conditions)) {
                return [];
            }
            
            $sql = "SELECT r.*,
                           requester.first_name as requester_first_name,
                           requester.last_name as requester_last_name,
                           requester.phone as requester_phone,
                           requester.activity_section as requester_section
                    FROM requests r
                    JOIN users requester ON r.user_id = requester.id
                    WHERE (" . implode(' OR ', $conditions) . ")
                    ORDER BY r.approval_deadline ASC, r.created_at ASC";
            
            $stmt = $this->db->prepare($sql);
            // تکرار user_id به تعداد شرایط
            $params = array_fill(0, count($conditions), $user_id);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("GetPendingApprovalsForUser error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت جزئیات درخواست
     */
    public function getRequest($request_id) {
        try {
            $sql = "SELECT r.*,
                           requester.first_name as requester_first_name,
                           requester.last_name as requester_last_name,
                           requester.phone as requester_phone,
                           requester.activity_section as requester_section,
                           requester.role as requester_role,
                           manager.first_name as manager_first_name,
                           manager.last_name as manager_last_name,
                           substitute.first_name as substitute_first_name,
                           substitute.last_name as substitute_last_name
                    FROM requests r
                    JOIN users requester ON r.user_id = requester.id
                    LEFT JOIN users manager ON r.manager_id = manager.id
                    LEFT JOIN users substitute ON r.substitute_id = substitute.id
                    WHERE r.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$request_id]);
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("GetRequest error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * دریافت تاریخچه تأییدات
     */
    public function getRequestApprovals($request_id) {
        try {
            $sql = "SELECT ra.*,
                           approver.first_name as approver_first_name,
                           approver.last_name as approver_last_name,
                           approver.role as approver_user_role
                    FROM request_approvals ra
                    JOIN users approver ON ra.approver_id = approver.id
                    WHERE ra.request_id = ?
                    ORDER BY ra.created_at ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$request_id]);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("GetRequestApprovals error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت آمار درخواست‌ها
     */
    public function getRequestStats($user_id) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN final_status = 'approved' THEN 1 END) as approved,
                        COUNT(CASE WHEN final_status = 'rejected' THEN 1 END) as rejected,
                        COUNT(CASE WHEN status LIKE 'waiting%' OR status = 'pending' THEN 1 END) as pending,
                        COUNT(CASE WHEN request_type = 'mission' THEN 1 END) as mission,
                        COUNT(CASE WHEN request_type = 'leave' THEN 1 END) as leave,
                        COUNT(CASE WHEN request_type = 'pass' THEN 1 END) as pass,
                        COUNT(CASE WHEN request_type = 'technical' THEN 1 END) as technical,
                        COUNT(CASE WHEN request_type = 'forget' THEN 1 END) as forget,
                        COUNT(CASE WHEN YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW()) THEN 1 END) as this_month
                    FROM requests
                    WHERE user_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id]);
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("GetRequestStats error: " . $e->getMessage());
            return [];
        }
    }
    
    // ============= توابع کمکی =============
    
    private function validateRequest($data, $user_id) {
        // عنوان الزامی
        if (empty($data['title'])) {
            return ['success' => false, 'message' => 'عنوان درخواست الزامی است'];
        }
        
        // تاریخ شروع و پایان
        if (empty($data['start_datetime']) || empty($data['end_datetime'])) {
            return ['success' => false, 'message' => 'تاریخ شروع و پایان الزامی است'];
        }
        
        $start = strtotime($data['start_datetime']);
        $end = strtotime($data['end_datetime']);
        
        if ($start >= $end) {
            return ['success' => false, 'message' => 'تاریخ پایان باید بعد از تاریخ شروع باشد'];
        }
        
        // بررسی جانشین برای مرخصی
        if ($data['request_type'] == self::TYPE_LEAVE) {
            if (empty($data['substitute_id'])) {
                return ['success' => false, 'message' => 'انتخاب جانشین برای مرخصی الزامی است'];
            }
            
            if ($data['substitute_id'] == $user_id) {
                return ['success' => false, 'message' => 'شما نمی‌توانید خود را به عنوان جانشین انتخاب کنید'];
            }
            
            // بررسی همان بخش
            $userSection = $this->getUserSection($user_id);
            $substituteSection = $this->getUserSection($data['substitute_id']);
            
            if ($userSection != $substituteSection) {
                return ['success' => false, 'message' => 'جانشین باید از همان بخش باشد'];
            }
        }
        
        // بررسی پاس در یک روز
        if ($data['request_type'] == self::TYPE_PASS) {
            if (date('Y-m-d', $start) != date('Y-m-d', $end)) {
                return ['success' => false, 'message' => 'درخواست پاس باید در یک روز باشد'];
            }
        }
        
        return ['success' => true];
    }
    
    private function checkDateLimit($start_datetime) {
        $start = strtotime($start_datetime);
        $now = time();
        $diff_days = ($start - $now) / 86400;
        
        // نمی‌تواند بیشتر از 3 روز بعد از رویداد درخواست دهد
        return $diff_days >= -3;
    }
    
    private function checkMonthlyLimit($user_id, $request_type, $start_datetime, $hours) {
        $stmt = $this->db->prepare("CALL check_monthly_limit(?, ?, ?, ?, @can_submit, @message)");
        $stmt->execute([$user_id, $request_type, $start_datetime, $hours]);
        
        $result = $this->db->query("SELECT @can_submit as can_submit, @message as message")->fetch();
        
        return [
            'can_submit' => (bool)$result['can_submit'],
            'message' => $result['message']
        ];
    }
    
    private function determineInitialStatus($request_type, $data) {
        if ($request_type == self::TYPE_PASS) {
            return self::STATUS_APPROVED; // پاس نیاز به تأیید ندارد
        }
        
        if ($request_type == self::TYPE_LEAVE && !empty($data['substitute_id'])) {
            return self::STATUS_WAITING_SUBSTITUTE;
        }
        
        if ($request_type == self::TYPE_TECHNICAL) {
            return self::STATUS_WAITING_IT;
        }
        
        return self::STATUS_WAITING_MANAGER;
    }
    
    private function determineCurrentApprover($request_type, $data) {
        if ($request_type == self::TYPE_PASS) {
            return null;
        }
        
        if ($request_type == self::TYPE_LEAVE && !empty($data['substitute_id'])) {
            return 'substitute';
        }
        
        if ($request_type == self::TYPE_TECHNICAL) {
            return 'it_manager';
        }
        
        return 'manager';
    }
    
    private function getUserManager($user_id) {
        $stmt = $this->db->prepare("SELECT manager_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result ? $result['manager_id'] : null;
    }
    
    private function getUserRole($user_id) {
        $stmt = $this->db->prepare("SELECT id, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
    
    private function getUserSection($user_id) {
        $stmt = $this->db->prepare("SELECT activity_section FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result ? $result['activity_section'] : null;
    }
    
    private function canUserApprove($request, $user_id) {
        $user = $this->getUserRole($user_id);
        
        if (!$user) {
            return ['can_approve' => false, 'message' => 'کاربر یافت نشد'];
        }
        
        // جانشین
        if ($request['substitute_id'] == $user_id && $request['status'] == self::STATUS_WAITING_SUBSTITUTE) {
            return ['can_approve' => true, 'role' => 'substitute'];
        }
        
        // مدیر
        if ($request['manager_id'] == $user_id && $request['status'] == self::STATUS_WAITING_MANAGER) {
            return ['can_approve' => true, 'role' => 'manager'];
        }
        
        // مسئول
        if ($user['role'] == 'supervisor' && $request['status'] == self::STATUS_WAITING_SUPERVISOR) {
            return ['can_approve' => true, 'role' => 'supervisor'];
        }
        
        // مسئول IT
        if ($user['role'] == 'it_manager' && $request['status'] == self::STATUS_WAITING_IT) {
            return ['can_approve' => true, 'role' => 'it_manager'];
        }
        
        return ['can_approve' => false, 'message' => 'شما مجاز به تأیید این درخواست نیستید'];
    }
    
    /**
     * دریافت عنوان فارسی نوع درخواست
     */
    public static function getRequestTypeTitle($type) {
        $titles = [
            self::TYPE_MISSION => 'مأموریت',
            self::TYPE_LEAVE => 'مرخصی',
            self::TYPE_PASS => 'پاس',
            self::TYPE_TECHNICAL => 'مشکل فنی',
            self::TYPE_FORGET => 'فراموشی'
        ];
        
        return $titles[$type] ?? $type;
    }
    
    /**
     * دریافت عنوان فارسی وضعیت
     */
    public static function getStatusTitle($status) {
        $titles = [
            self::STATUS_DRAFT => 'پیش‌نویس',
            self::STATUS_PENDING => 'در انتظار',
            self::STATUS_WAITING_SUBSTITUTE => 'در انتظار تأیید جانشین',
            self::STATUS_WAITING_MANAGER => 'در انتظار تأیید مدیر',
            self::STATUS_WAITING_SUPERVISOR => 'در انتظار تأیید مسئول',
            self::STATUS_WAITING_IT => 'در انتظار تأیید مسئول IT',
            self::STATUS_APPROVED => 'تأیید شده',
            self::STATUS_REJECTED => 'رد شده',
            self::STATUS_CANCELLED => 'لغو شده',
            self::STATUS_EXPIRED => 'منقضی شده'
        ];
        
        return $titles[$status] ?? $status;
    }
    
    /**
     * دریافت رنگ وضعیت
     */
    public static function getStatusColor($status) {
        $colors = [
            self::STATUS_DRAFT => 'secondary',
            self::STATUS_PENDING => 'info',
            self::STATUS_WAITING_SUBSTITUTE => 'warning',
            self::STATUS_WAITING_MANAGER => 'warning',
            self::STATUS_WAITING_SUPERVISOR => 'warning',
            self::STATUS_WAITING_IT => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELLED => 'dark',
            self::STATUS_EXPIRED => 'danger'
        ];
        
        return $colors[$status] ?? 'secondary';
    }
}
?>