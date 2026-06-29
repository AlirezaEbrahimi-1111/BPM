<?php

/**
 * کلاس مدیریت درخواست‌ها
 * شامل: مأموریت، مرخصی، پاس، مشکل فنی، فراموشی
 */

class RequestManager {

    private $db;
    const TABLE_MISSION = 'mission_requests';
    const TABLE_LEAVE = 'leave_requests';
    const TABLE_PASS = 'pass_requests';
    const TABLE_TECHNICAL = 'technical_issues';
    const TABLE_FORGET = 'forget_requests';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * ایجاد درخواست مأموریت
     */
    public function createMissionRequest($userId, $date, $startTime, $endTime, $description) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO " . self::TABLE_MISSION . "
                (user_id, date, start_time, end_time, description, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$userId, $date, $startTime, $endTime, $description]);
            
            return [
                'success' => true,
                'message' => 'درخواست مأموریت با موفقیت ایجاد شد',
                'request_id' => $this->db->lastInsertId()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در ایجاد درخواست: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ایجاد درخواست مرخصی
     */
    public function createLeaveRequest($userId, $startDate, $endDate, $startTime, $endTime, $reason, $substituteId = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO " . self::TABLE_LEAVE . "
                (user_id, start_date, end_date, start_time, end_time, reason, substitute_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$userId, $startDate, $endDate, $startTime, $endTime, $reason, $substituteId]);
            
            return [
                'success' => true,
                'message' => 'درخواست مرخصی با موفقیت ایجاد شد',
                'request_id' => $this->db->lastInsertId()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در ایجاد درخواست: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ایجاد درخواست پاس
     */
    public function createPassRequest($userId, $date, $startTime, $endTime, $reason = null) {
        try {
            // محاسبه تعداد ساعات
            $start = \DateTime::createFromFormat('H:i:s', $startTime);
            $end = \DateTime::createFromFormat('H:i:s', $endTime);
            $interval = $start->diff($end);
            $hours = $interval->h + ($interval->i / 60);

            $stmt = $this->db->prepare("
                INSERT INTO " . self::TABLE_PASS . "
                (user_id, date, start_time, end_time, hours, reason, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())
            ");
            $stmt->execute([$userId, $date, $startTime, $endTime, $hours, $reason]);
            
            return [
                'success' => true,
                'message' => 'درخواست پاس با موفقیت ایجاد شد',
                'request_id' => $this->db->lastInsertId()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در ایجاد درخواست: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ایجاد درخواست مشکل فنی
     */
    public function createTechnicalRequest($userId, $date, $startTime, $endTime, $description) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO " . self::TABLE_TECHNICAL . "
                (user_id, date, start_time, end_time, description, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$userId, $date, $startTime, $endTime, $description]);
            
            return [
                'success' => true,
                'message' => 'درخواست مشکل فنی با موفقیت ایجاد شد',
                'request_id' => $this->db->lastInsertId()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در ایجاد درخواست: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ایجاد درخواست فراموشی
     */
    public function createForgetRequest($userId, $date, $startTime, $endTime, $description) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO " . self::TABLE_FORGET . "
                (user_id, date, start_time, end_time, description, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$userId, $date, $startTime, $endTime, $description]);
            
            return [
                'success' => true,
                'message' => 'درخواست فراموشی با موفقیت ایجاد شد',
                'request_id' => $this->db->lastInsertId()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در ایجاد درخواست: ' . $e->getMessage()
            ];
        }
    }

    /**
     * به روزرسانی درخواست
     */
    public function updateRequest($requestType, $requestId, $data) {
        try {
            $table = $this->getTableByType($requestType);
            if (!$table) {
                return ['success' => false, 'message' => 'نوع درخواست نامشخص'];
            }

            $fields = [];
            $values = [];

            foreach ($data as $key => $value) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
            $values[] = $requestId;

            $sql = "UPDATE $table SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            return [
                'success' => true,
                'message' => 'درخواست با موفقیت به روزرسانی شد'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در به روزرسانی: ' . $e->getMessage()
            ];
        }
    }

    /**
     * لغو درخواست
     */
    public function cancelRequest($requestType, $requestId) {
        try {
            $table = $this->getTableByType($requestType);
            
            if ($requestType === 'pass') {
                // برای پاس، تنظیم is_cancelled بجای حذف
                $stmt = $this->db->prepare("
                    UPDATE $table 
                    SET is_cancelled = 1, cancelled_at = NOW() 
                    WHERE id = ?
                ");
            } else {
                // برای دیگری، تنظیم status
                $stmt = $this->db->prepare("
                    UPDATE $table 
                    SET status = 'cancelled' 
                    WHERE id = ?
                ");
            }
            
            $stmt->execute([$requestId]);

            return [
                'success' => true,
                'message' => 'درخواست با موفقیت لغو شد'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در لغو درخواست: ' . $e->getMessage()
            ];
        }
    }

    /**
     * دریافت درخواست‌های یک تاریخ خاص
     */
    public function getRequestsByDate($userId, $date) {
        $requests = [];

        // مأموریت
        $stmt = $this->db->prepare("
            SELECT 'mission' as type, id,request_code, date as start_date, date as end_date, start_time, end_time, description, 
                   status, manager_status, superior_status, manager_id, superior_id, NULL as substitute_id, 
                   manager_notes, superior_notes, NULL as substitute_notes, NULL as it_manager_id, NULL as it_manager_status
            FROM mission_requests
            WHERE user_id = ? AND (date = ? OR (? BETWEEN date AND date))
        ");
        $stmt->execute([$userId, $date, $date]);
        $requests = array_merge($requests, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // مرخصی
        $stmt = $this->db->prepare("
            SELECT 'leave' as type, id,request_code, start_date, end_date, start_time, end_time, reason as description,
                   status, manager_status, superior_status, manager_id, superior_id, substitute_id,
                   manager_notes, superior_notes, substitute_notes, NULL as it_manager_id, substitute_status as it_manager_status
            FROM leave_requests
            WHERE user_id = ? AND (start_date <= ? AND end_date >= ?)
        ");
        $stmt->execute([$userId, $date, $date]);
        $requests = array_merge($requests, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // پاس
        $stmt = $this->db->prepare("
            SELECT 'pass' as type, id,request_code, date as start_date, date as end_date, start_time, end_time, reason as description,
                   status, NULL as manager_status, NULL as superior_status, NULL as manager_id, NULL as superior_id, NULL as substitute_id,
                   NULL as manager_notes, NULL as superior_notes, NULL as substitute_notes, NULL as it_manager_id, NULL as it_manager_status
            FROM pass_requests
            WHERE user_id = ? AND date = ? AND is_cancelled = 0
        ");
        $stmt->execute([$userId, $date]);
        $requests = array_merge($requests, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // مشکل فنی
        $stmt = $this->db->prepare("
            SELECT 'technical' as type, id,request_code, date as start_date, date as end_date, start_time, end_time, description,
                   status, NULL as manager_status, NULL as superior_status, NULL as manager_id, NULL as superior_id, NULL as substitute_id,
                   NULL as manager_notes, NULL as superior_notes, NULL as substitute_notes, it_manager_id, it_manager_status
            FROM technical_issues
            WHERE user_id = ? AND date = ?
        ");
        $stmt->execute([$userId, $date]);
        $requests = array_merge($requests, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // فراموشی
        $stmt = $this->db->prepare("
            SELECT 'forget' as type, id, request_code,date as start_date, date as end_date, start_time, end_time, description,
                   status, manager_status, superior_status, manager_id, superior_id, NULL as substitute_id,
                   manager_notes, superior_notes, NULL as substitute_notes, NULL as it_manager_id, NULL as it_manager_status
            FROM forget_requests
            WHERE user_id = ? AND date = ?
        ");
        $stmt->execute([$userId, $date]);
        $requests = array_merge($requests, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // افزودن نام‌های مرتبط
        foreach ($requests as &$request) {
            if ($request['manager_id']) {
                $request['manager_name'] = $this->getUserName($request['manager_id']);
            }
            if ($request['superior_id']) {
                $request['superior_name'] = $this->getUserName($request['superior_id']);
            }
            if ($request['substitute_id']) {
                $request['substitute_name'] = $this->getUserName($request['substitute_id']);
            }
            if ($request['it_manager_id']) {
                $request['it_manager_name'] = $this->getUserName($request['it_manager_id']);
            }
        }

        return $requests;
    }

    /**
     * دریافت درخواست‌های در انتظار برای مدیر
     */
    public function getPendingRequestsForManager($managerId) {
        $stmt = $this->db->prepare("
            SELECT 
                'mission' as type,
                id,
                user_id,
                date as start_date,
                date as end_date,
                start_time,
                end_time,
                description,
                manager_status as approval_status,
                created_at
            FROM mission_requests
            WHERE manager_id = ? AND manager_status = 'pending'
            
            UNION ALL
            
            SELECT 
                'leave' as type,
                id,
                user_id,
                start_date,
                end_date,
                start_time,
                end_time,
                reason as description,
                manager_status as approval_status,
                created_at
            FROM leave_requests
            WHERE manager_id = ? AND manager_status = 'pending'
            
            UNION ALL
            
            SELECT 
                'forget' as type,
                id,
                user_id,
                date as start_date,
                date as end_date,
                start_time,
                end_time,
                description,
                manager_status as approval_status,
                created_at
            FROM forget_requests
            WHERE manager_id = ? AND manager_status = 'pending'
            
            ORDER BY created_at DESC
        ");
        $stmt->execute([$managerId, $managerId, $managerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * دریافت نام کاربر
     */
    private function getUserName($userId) {
        $stmt = $this->db->prepare("
            SELECT CONCAT(first_name, ' ', last_name) as full_name
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['full_name'] ?? 'نامشخص';
    }

    /**
     * تعیین جدول بر اساس نوع درخواست
     */
    private function getTableByType($type) {
        $tables = [
            'mission' => self::TABLE_MISSION,
            'leave' => self::TABLE_LEAVE,
            'pass' => self::TABLE_PASS,
            'technical' => self::TABLE_TECHNICAL,
            'forget' => self::TABLE_FORGET
        ];
        return $tables[$type] ?? null;
    }
}

?>