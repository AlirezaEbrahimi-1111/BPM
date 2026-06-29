<?php
// includes/LeaveManager.php

class LeaveManager
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    // ===================================
    // ثبت درخواست مرخصی
    // ===================================
    public function submitLeaveRequest($user_id, $data)
    {
        try {
            // اعتبارسنجی
            $validation = $this->validateLeaveRequest($user_id, $data);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }

            // محاسبه مدت
            $duration = $this->calculateLeaveDuration($data);

            // بررسی موجودی
            $balance_check = $this->checkLeaveBalance($user_id, $duration['hours']);
            if (!$balance_check['sufficient']) {
                return ['success' => false, 'message' => 'موجودی مرخصی کافی نیست'];
            }

            // دریافت جانشین‌ها
            $substitutes = $this->getUserSubstitutes($user_id);
            if (empty($substitutes)) {
                return ['success' => false, 'message' => 'شما جانشین تعریف نکرده‌اید'];
            }

            // تولید کد یکتا
            $request_code = $this->generateRequestCode('LV');

            // ثبت درخواست
            $stmt = $this->db->prepare("
                INSERT INTO leave_requests (
                    request_code, user_id, leave_type, start_date, end_date,
                    start_time, end_time, duration_days, duration_hours, reason,
                    substitute_id, current_approver_role
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'substitute')
            ");

            $result = $stmt->execute([
                $request_code,
                $user_id,
                $data['leave_type'],
                $data['start_date'],
                $data['end_date'],
                $data['start_time'] ?? null,
                $data['end_time'] ?? null,
                $duration['days'],
                $duration['hours'],
                $data['reason'],
                $substitutes[0]['substitute_user_id'] // اولین جانشین
            ]);

            if ($result) {
                $request_id = $this->db->lastInsertId();

                // ارسال نوتیفیکیشن
                $this->sendNotification(
                    $substitutes[0]['substitute_user_id'],
                    'approval_needed',
                    'درخواست مرخصی جدید',
                    'درخواست مرخصی جدیدی منتظر تأیید شماست',
                    '../pages/approvals.php?id=' . $request_id
                );

                return [
                    'success' => true,
                    'message' => 'درخواست با موفقیت ثبت شد',
                    'request_code' => $request_code,
                    'request_id' => $request_id
                ];
            }

            return ['success' => false, 'message' => 'خطا در ثبت درخواست'];

        } catch (Exception $e) {
            error_log("SubmitLeaveRequest error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    // ===================================
    // ثبت درخواست پاس
    // ===================================
    public function submitPassRequest($user_id, $data)
    {
        try {
            // اعتبارسنجی
            $validation = $this->validatePassRequest($user_id, $data);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }

            // محاسبه مدت
            $start = new DateTime($data['pass_date'] . ' ' . $data['start_time']);
            $end = new DateTime($data['pass_date'] . ' ' . $data['end_time']);
            $duration_hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;

            // بررسی موجودی
            $balance_check = $this->checkPassBalance($user_id, $duration_hours, $data['pass_date']);
            if (!$balance_check['sufficient']) {
                return ['success' => false, 'message' => $balance_check['message']];
            }

            // تولید کد یکتا
            $request_code = $this->generateRequestCode('PS');

            // ثبت درخواست (خودکار تأیید است)
            $stmt = $this->db->prepare("
                INSERT INTO pass_requests (
                    request_code, user_id, pass_date, start_time, end_time,
                    duration_hours, reason, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'approved')
            ");

            $result = $stmt->execute([
                $request_code,
                $user_id,
                $data['pass_date'],
                $data['start_time'],
                $data['end_time'],
                $duration_hours,
                $data['reason']
            ]);

            if ($result) {
                // کسر از موجودی
                $this->deductPassBalance($user_id, $duration_hours, $data['pass_date']);

                return [
                    'success' => true,
                    'message' => 'پاس با موفقیت ثبت شد',
                    'request_code' => $request_code
                ];
            }

            return ['success' => false, 'message' => 'خطا در ثبت پاس'];

        } catch (Exception $e) {
            error_log("SubmitPassRequest error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    // ===================================
    // تأیید/رد درخواست
    // ===================================
    public function approveRequest($request_id, $request_type, $approver_id, $action, $notes = '')
    {
        try {
            $table_map = [
                'leave' => 'leave_requests',
                'mission' => 'mission_requests',
                'forget' => 'forget_requests',
                'technical' => 'technical_issues'
            ];

            $table = $table_map[$request_type] ?? null;
            if (!$table) {
                return ['success' => false, 'message' => 'نوع درخواست نامعتبر'];
            }

            // دریافت درخواست
            $stmt = $this->db->prepare("SELECT * FROM $table WHERE id = ?");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();

            if (!$request) {
                return ['success' => false, 'message' => 'درخواست یافت نشد'];
            }

            // تعیین نقش تأییدکننده
            $approver_role = $this->getApproverRole($approver_id, $request['user_id']);

            // بروزرسانی تأیید
            $update_result = $this->updateApproval($table, $request_id, $approver_role, $approver_id, $action, $notes);

            if ($update_result) {
                // ارسال نوتیفیکیشن به درخواست‌دهنده
                $status_text = $action == 'approved' ? 'تأیید' : 'رد';
                $this->sendNotification(
                    $request['user_id'],
                    'request_' . $action,
                    'درخواست ' . $status_text . ' شد',
                    'درخواست شما توسط ' . $approver_role . ' ' . $status_text . ' شد',
                    '../pages/my-requests.php?id=' . $request_id
                );

                // اگر تأیید شد و مرحله بعدی دارد
                if ($action == 'approved') {
                    $next_approver = $this->getNextApprover($table, $request);
                    if ($next_approver) {
                        $this->sendNotification(
                            $next_approver['id'],
                            'approval_needed',
                            'درخواست منتظر تأیید',
                            'درخواست جدیدی منتظر تأیید شماست',
                            '../pages/approvals.php?id=' . $request_id
                        );
                    }
                }

                // اگر همه تأیید کردند، نهایی کردن
                if ($this->isFullyApproved($table, $request_id)) {
                    $this->finalizeRequest($table, $request_id, $request);
                }

                return ['success' => true, 'message' => 'عملیات با موفقیت انجام شد'];
            }

            return ['success' => false, 'message' => 'خطا در انجام عملیات'];

        } catch (Exception $e) {
            error_log("ApproveRequest error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    // ===================================
    // محاسبه مدت مرخصی
    // ===================================
    private function calculateLeaveDuration($data)
    {
        if ($data['leave_type'] == 'hourly') {
            $start = new DateTime($data['start_date'] . ' ' . $data['start_time']);
            $end = new DateTime($data['start_date'] . ' ' . $data['end_time']);
            $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;

            return [
                'days' => round($hours / 8, 2),
                'hours' => $hours
            ];
        } else {
            $start = new DateTime($data['start_date']);
            $end = new DateTime($data['end_date']);
            $interval = $start->diff($end);
            $days = $interval->days + 1; // شامل روز آخر

            // کم کردن جمعه‌ها و تعطیلات
            $working_days = 0;
            $current = clone $start;

            while ($current <= $end) {
                $date_str = $current->format('Y-m-d');
                if (!$this->isHoliday($date_str) && !$this->isFriday($date_str)) {
                    $working_days++;
                }
                $current->modify('+1 day');
            }

            return [
                'days' => $working_days,
                'hours' => $working_days * 8 // فرض 8 ساعت کاری
            ];
        }
    }

    // ===================================
    // اعتبارسنجی درخواست مرخصی
    // ===================================
    private function validateLeaveRequest($user_id, $data)
    {
        // بررسی فیلدهای ضروری
        if (empty($data['start_date']) || empty($data['end_date']) || empty($data['reason'])) {
            return ['valid' => false, 'message' => 'اطلاعات کامل نیست'];
        }

        // بررسی تاریخ (حداکثر 5 روز قبل)
        $start_date = new DateTime($data['start_date']);
        $today = new DateTime();
        $diff = $today->diff($start_date);

        if ($diff->days > 5 && $start_date > $today) {
            return ['valid' => false, 'message' => 'حداکثر 5 روز قبل می‌توانید درخواست ثبت کنید'];
        }

        // بررسی مدت (حداقل 1 روز، حداکثر 24 روز)
        $duration = $this->calculateLeaveDuration($data);
        if ($duration['days'] < 1 || $duration['days'] > 24) {
            return ['valid' => false, 'message' => 'مدت مرخصی باید بین 1 تا 24 روز باشد'];
        }

        // بررسی تداخل با درخواست‌های قبلی
        if ($this->hasOverlappingLeave($user_id, $data['start_date'], $data['end_date'])) {
            return ['valid' => false, 'message' => 'درخواست شما با مرخصی دیگری تداخل دارد'];
        }

        return ['valid' => true];
    }

    // ===================================
    // اعتبارسنجی درخواست پاس
    // ===================================
    private function validatePassRequest($user_id, $data)
    {
        // بررسی فیلدهای ضروری
        if (empty($data['pass_date']) || empty($data['start_time']) || empty($data['end_time'])) {
            return ['valid' => false, 'message' => 'اطلاعات کامل نیست'];
        }

        // محاسبه مدت
        $start = new DateTime($data['pass_date'] . ' ' . $data['start_time']);
        $end = new DateTime($data['pass_date'] . ' ' . $data['end_time']);
        $duration_hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;

        // بررسی مدت (حداکثر 4 ساعت)
        if ($duration_hours <= 0 || $duration_hours > 4) {
            return ['valid' => false, 'message' => 'مدت پاس باید بین 0 تا 4 ساعت باشد'];
        }

        // بررسی تعداد پاس در روز
        $stmt = $this->db->prepare("
            SELECT SUM(duration_hours) as total 
            FROM pass_requests 
            WHERE user_id = ? AND pass_date = ? AND status = 'approved'
        ");
        $stmt->execute([$user_id, $data['pass_date']]);
        $result = $stmt->fetch();
        $today_total = $result['total'] ?? 0;

        if ($today_total + $duration_hours > 4) {
            return ['valid' => false, 'message' => 'حداکثر 4 ساعت پاس در روز مجاز است'];
        }

        return ['valid' => true];
    }

    // ===================================
    // بررسی موجودی مرخصی
    // ===================================
    private function checkLeaveBalance($user_id, $hours_needed)
    {
        $year = date('Y');
        $month = date('n');

        // دریافت یا ایجاد موجودی
        $balance = $this->getOrCreateBalance($user_id, $year, $month);

        $user = $this->getUserInfo($user_id);
        $daily_hours = $user['daily_work_hours'];

        // محاسبه موجودی کل (ماه جاری + مانده ماه قبل)
        $monthly_hours = $balance['monthly_quota'] * $daily_hours;
        $carry_forward_hours = $balance['carry_forward'] * $daily_hours;
        $used_hours = $balance['used_leave'] * $daily_hours + $balance['used_pass'] * $daily_hours;

        $total_available = $monthly_hours + $carry_forward_hours - $used_hours;

        return [
            'sufficient' => $total_available >= $hours_needed,
            'available' => $total_available,
            'needed' => $hours_needed
        ];
    }

    // ===================================
    // بررسی موجودی پاس
    // ===================================
    private function checkPassBalance($user_id, $hours_needed, $date)
    {
        $year = date('Y', strtotime($date));
        $month = date('n', strtotime($date));

        $balance = $this->getOrCreateBalance($user_id, $year, $month);
        $user = $this->getUserInfo($user_id);
        $daily_hours = $user['daily_work_hours'];

        // محاسبه موجودی (سهمیه مشترک مرخصی و پاس)
        $monthly_hours = $balance['monthly_quota'] * $daily_hours;
        $carry_forward_hours = $balance['carry_forward'] * $daily_hours;
        $used_hours = $balance['used_leave'] * $daily_hours + $balance['used_pass'] * $daily_hours;

        $total_available = $monthly_hours + $carry_forward_hours - $used_hours;

        // بررسی تعداد دفعات پاس (حداکثر 6 بار در ماه)
        if ($balance['pass_count_used'] >= 6) {
            return [
                'sufficient' => false,
                'message' => 'حداکثر 6 بار پاس در ماه مجاز است'
            ];
        }

        // بررسی موجودی ساعتی
        if ($total_available < $hours_needed) {
            return [
                'sufficient' => false,
                'message' => 'موجودی کافی نیست. موجودی شما: ' . round($total_available, 2) . ' ساعت'
            ];
        }

        return ['sufficient' => true];
    }

    // ===================================
    // کسر از موجودی پاس
    // ===================================
    private function deductPassBalance($user_id, $hours, $date)
    {
        $year = date('Y', strtotime($date));
        $month = date('n', strtotime($date));

        $user = $this->getUserInfo($user_id);
        $daily_hours = $user['daily_work_hours'];
        $days = $hours / $daily_hours;

        $stmt = $this->db->prepare("
            UPDATE leave_balances 
            SET used_pass = used_pass + ?, 
                pass_count_used = pass_count_used + 1,
                updated_at = NOW()
            WHERE user_id = ? AND year = ? AND month = ?
        ");

        return $stmt->execute([$days, $user_id, $year, $month]);
    }

    // ===================================
    // دریافت یا ایجاد موجودی
    // ===================================
    private function getOrCreateBalance($user_id, $year, $month)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM leave_balances 
            WHERE user_id = ? AND year = ? AND month = ?
        ");
        $stmt->execute([$user_id, $year, $month]);
        $balance = $stmt->fetch();

        if (!$balance) {
            // محاسبه مانده ماه قبل
            $prev_month = $month - 1;
            $prev_year = $year;
            if ($prev_month < 1) {
                $prev_month = 12;
                $prev_year--;
            }

            $stmt = $this->db->prepare("
                SELECT * FROM leave_balances 
                WHERE user_id = ? AND year = ? AND month = ?
            ");
            $stmt->execute([$user_id, $prev_year, $prev_month]);
            $prev_balance = $stmt->fetch();

            $carry_forward = 0;
            if ($prev_balance) {
                $user = $this->getUserInfo($user_id);
                $daily_hours = $user['daily_work_hours'];

                $prev_monthly = $prev_balance['monthly_quota'] * $daily_hours;
                $prev_carry = $prev_balance['carry_forward'] * $daily_hours;
                $prev_used = $prev_balance['used_leave'] * $daily_hours + $prev_balance['used_pass'] * $daily_hours;

                $prev_remaining_hours = $prev_monthly + $prev_carry - $prev_used;
                $carry_forward = $prev_remaining_hours / $daily_hours;
            }

            // ایجاد موجودی جدید
            $stmt = $this->db->prepare("
                INSERT INTO leave_balances (user_id, year, month, carry_forward)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $year, $month, $carry_forward]);

            return $this->getOrCreateBalance($user_id, $year, $month);
        }

        return $balance;
    }

    // ===================================
    // دریافت جانشین‌ها
    // ===================================
    private function getUserSubstitutes($user_id)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM substitutes 
            WHERE user_id = ? AND is_active = 1
            ORDER BY id ASC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    // ===================================
    // تعیین نقش تأییدکننده
    // ===================================
    private function getApproverRole($approver_id, $user_id)
    {
        // بررسی آیا جانشین است
        $stmt = $this->db->prepare("
            SELECT id FROM substitutes 
            WHERE user_id = ? AND substitute_user_id = ? AND is_active = 1
        ");
        $stmt->execute([$user_id, $approver_id]);
        if ($stmt->fetch()) {
            return 'substitute';
        }

        // بررسی آیا مدیر است
        $stmt = $this->db->prepare("SELECT manager_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user && $user['manager_id'] == $approver_id) {
            return 'manager';
        }

        // بررسی آیا مسئول است
        $stmt = $this->db->prepare("SELECT is_supervisor FROM users WHERE id = ?");
        $stmt->execute([$approver_id]);
        $approver = $stmt->fetch();
        if ($approver && $approver['is_supervisor']) {
            return 'supervisor';
        }

        return null;
    }

    // ===================================
    // بروزرسانی تأیید
    // ===================================
    private function updateApproval($table, $request_id, $role, $approver_id, $action, $notes)
    {
        $field_approval = $role . '_approval';
        $field_id = $role . '_id';
        $field_date = $role . '_date';
        $field_notes = $role . '_notes';

        $sql = "UPDATE $table SET 
                $field_approval = ?,
                $field_id = ?,
                $field_date = NOW(),
                $field_notes = ?";

        // اگر رد شد، وضعیت کل را رد کن
        if ($action == 'rejected') {
            $sql .= ", status = 'rejected', can_edit = 0, can_delete = 0";
        } else {
            // به مرحله بعدی برو
            $next_role = $this->getNextRole($table, $role);
            if ($next_role) {
                $sql .= ", current_approver_role = '$next_role'";
            }
        }

        $sql .= " WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$action, $approver_id, $notes, $request_id]);
    }

    // ===================================
    // مرحله بعدی تأیید
    // ===================================
    private function getNextRole($table, $current_role)
    {
        if ($table == 'leave_requests') {
            if ($current_role == 'substitute')
                return 'manager';
            if ($current_role == 'manager')
                return 'supervisor';
        } elseif (in_array($table, ['mission_requests', 'forget_requests'])) {
            if ($current_role == 'manager')
                return 'supervisor';
        }

        return null;
    }

    // ===================================
    // دریافت تأییدکننده بعدی
    // ===================================
    private function getNextApprover($table, $request)
    {
        $next_role = $this->getNextRole($table, $request['current_approver_role']);

        if ($next_role == 'manager') {
            $stmt = $this->db->prepare("SELECT manager_id FROM users WHERE id = ?");
            $stmt->execute([$request['user_id']]);
            $user = $stmt->fetch();
            if ($user && $user['manager_id']) {
                return ['id' => $user['manager_id'], 'role' => 'manager'];
            }
        } elseif ($next_role == 'supervisor') {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE is_supervisor = 1 LIMIT 1");
            $stmt->execute();
            $supervisor = $stmt->fetch();
            if ($supervisor) {
                return ['id' => $supervisor['id'], 'role' => 'supervisor'];
            }
        }

        return null;
    }

    // ===================================
    // بررسی تأیید کامل
    // ===================================
    private function isFullyApproved($table, $request_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();

        if (!$request)
            return false;

        if ($table == 'leave_requests') {
            return $request['substitute_approval'] == 'approved' &&
                $request['manager_approval'] == 'approved' &&
                $request['supervisor_approval'] == 'approved';
        } elseif (in_array($table, ['mission_requests', 'forget_requests'])) {
            return $request['manager_approval'] == 'approved' &&
                $request['supervisor_approval'] == 'approved';
        } elseif ($table == 'technical_issues') {
            return $request['status'] == 'approved';
        }

        return false;
    }

    // ===================================
    // نهایی کردن درخواست
    // ===================================
    private function finalizeRequest($table, $request_id, $request)
    {
        // وضعیت را به تأیید شده تغییر بده
        $stmt = $this->db->prepare("
            UPDATE $table 
            SET status = 'approved', can_edit = 0, can_delete = 0 
            WHERE id = ?
        ");
        $stmt->execute([$request_id]);

        // اگر مرخصی بود، از موجودی کسر کن
        if ($table == 'leave_requests') {
            $year = date('Y', strtotime($request['start_date']));
            $month = date('n', strtotime($request['start_date']));

            $user = $this->getUserInfo($request['user_id']);
            $daily_hours = $user['daily_work_hours'];
            $days = $request['duration_hours'] / $daily_hours;

            $stmt = $this->db->prepare("
                UPDATE leave_balances 
                SET used_leave = used_leave + ?, updated_at = NOW()
                WHERE user_id = ? AND year = ? AND month = ?
            ");
            $stmt->execute([$days, $request['user_id'], $year, $month]);
        }

        // اگر مأموریت بود، در جدول حضور ثبت کن
        if ($table == 'mission_requests') {
            $this->recordMissionAttendance($request);
        }

        // اگر فراموشی بود، حضور را اصلاح کن
        if ($table == 'forget_requests') {
            $this->correctForgottenAttendance($request);
        }

        return true;
    }

    // ===================================
    // ثبت حضور مأموریت
    // ===================================
    private function recordMissionAttendance($mission)
    {
        $user = $this->getUserInfo($mission['user_id']);
        $start = new DateTime($mission['start_date']);
        $end = new DateTime($mission['end_date']);

        $current = clone $start;
        while ($current <= $end) {
            $date_str = $current->format('Y-m-d');

            if (!$this->isHoliday($date_str) && !$this->isFriday($date_str)) {
                // ثبت به عنوان حضور کامل
                $stmt = $this->db->prepare("
                    INSERT INTO attendance_records 
                    (user_id, date, shift_number, check_in, check_out, is_mission, work_hours, notes)
                    VALUES (?, ?, 1, ?, ?, 1, ?, 'مأموریت')
                    ON DUPLICATE KEY UPDATE is_mission = 1, notes = 'مأموریت'
                ");

                $check_in = $date_str . ' ' . $user['shift1_start'];
                $check_out = $date_str . ' ' . $user['shift1_end'];

                $stmt->execute([
                    $mission['user_id'],
                    $date_str,
                    $check_in,
                    $check_out,
                    $user['daily_work_hours']
                ]);
            }

            $current->modify('+1 day');
        }
    }

    // ===================================
    // اصلاح حضور فراموش شده
    // ===================================
    private function correctForgottenAttendance($forget)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM attendance_records 
            WHERE user_id = ? AND date = ? AND shift_number = ?
        ");
        $stmt->execute([$forget['user_id'], $forget['forget_date'], $forget['shift_number']]);
        $record = $stmt->fetch();

        if ($forget['forget_type'] == 'check_in' || $forget['forget_type'] == 'both') {
            $check_in = $forget['forget_date'] . ' ' . $forget['suggested_check_in'];

            if ($record) {
                $stmt = $this->db->prepare("UPDATE attendance_records SET check_in = ? WHERE id = ?");
                $stmt->execute([$check_in, $record['id']]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO attendance_records (user_id, date, shift_number, check_in)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$forget['user_id'], $forget['forget_date'], $forget['shift_number'], $check_in]);
            }
        }

        if ($forget['forget_type'] == 'check_out' || $forget['forget_type'] == 'both') {
            $check_out = $forget['forget_date'] . ' ' . $forget['suggested_check_out'];

            if ($record) {
                $stmt = $this->db->prepare("UPDATE attendance_records SET check_out = ? WHERE id = ?");
                $stmt->execute([$check_out, $record['id']]);
            }
        }

        // محاسبه مجدد ساعات کار
        // (این بخش باید با AttendanceManager یکپارچه شود)
    }

    // ===================================
    // ارسال نوتیفیکیشن
    // ===================================
    private function sendNotification($user_id, $type, $title, $message, $link = null)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, type, title, message, link)
                VALUES (?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$user_id, $type, $title, $message, $link]);
        } catch (Exception $e) {
            error_log("SendNotification error: " . $e->getMessage());
            return false;
        }
    }

    // ===================================
    // تولید کد یکتای درخواست
    // ===================================
    private function generateRequestCode($prefix)
    {
        return $prefix . date('ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    // ===================================
    // توابع کمکی
    // ===================================

    private function hasOverlappingLeave($user_id, $start, $end)
    {
        $stmt = $this->db->prepare("
            SELECT id FROM leave_requests 
            WHERE user_id = ? 
            AND status IN ('pending', 'approved')
            AND (
                (start_date <= ? AND end_date >= ?) OR
                (start_date <= ? AND end_date >= ?) OR
                (start_date >= ? AND end_date <= ?)
            )
        ");
        $stmt->execute([$user_id, $start, $start, $end, $end, $start, $end]);
        return $stmt->fetch() !== false;
    }

    private function isHoliday($date)
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM holidays WHERE holiday_date = ?");
            $stmt->execute([$date]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    private function isFriday($date)
    {
        return date('N', strtotime($date)) == 5;
    }

    private function getUserInfo($user_id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
}
?>