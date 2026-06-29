<?php
// includes/AttendanceManager.php

class AttendanceManager
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    // ===================================
    // ثبت ورود (Check In)
    // ===================================
    public function checkIn($user_id, $ip_address)
    {
        try {
            // بررسی IP
            if (!$this->isIPAllowed($ip_address)) {
                return ['success' => false, 'message' => 'شما خارج از شبکه داخلی هستید'];
            }

            // دریافت اطلاعات کاربر
            $user = $this->getUserInfo($user_id);
            if (!$user) {
                return ['success' => false, 'message' => 'کاربر یافت نشد'];
            }

            $today = date('Y-m-d');

            // بررسی تعطیلی
            if ($this->isHoliday($today)) {
                return ['success' => false, 'message' => 'امروز تعطیل رسمی است'];
            }

            // بررسی روز جمعه
            if ($this->isFriday($today)) {
                return ['success' => false, 'message' => 'امروز جمعه است'];
            }

            // تعیین شماره شیفت
            $shift_number = $this->determineShiftNumber($user_id, $today, $user);

            if ($shift_number === false) {
                return ['success' => false, 'message' => 'شما قبلاً ورود ثبت کرده‌اید'];
            }

            if ($shift_number === -1) {
                return ['success' => false, 'message' => 'برای ثبت شیفت دوم باید حداقل 1 ساعت و حداکثر 12 ساعت از خروج شیفت اول گذشته باشد'];
            }

            // ثبت ورود
            $stmt = $this->db->prepare("
                INSERT INTO attendance_records (user_id, date, shift_number, check_in, check_in_ip)
                VALUES (?, ?, ?, NOW(), ?)
            ");

            if ($stmt->execute([$user_id, $today, $shift_number, $ip_address])) {
                return [
                    'success' => true,
                    'message' => 'ورود با موفقیت ثبت شد',
                    'shift_number' => $shift_number,
                    'time' => date('H:i:s')
                ];
            }

            return ['success' => false, 'message' => 'خطا در ثبت ورود'];

        } catch (Exception $e) {
            error_log("CheckIn error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    // ===================================
    // ثبت خروج (Check Out)
    // ===================================
    public function checkOut($user_id, $ip_address)
    {
        try {
            // بررسی IP
            if (!$this->isIPAllowed($ip_address)) {
                return ['success' => false, 'message' => 'شما خارج از شبکه داخلی هستید'];
            }

            $today = date('Y-m-d');

            // یافتن آخرین ورود بدون خروج
            $stmt = $this->db->prepare("
                SELECT * FROM attendance_records
                WHERE user_id = ? AND date = ? AND check_out IS NULL
                ORDER BY shift_number DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id, $today]);
            $record = $stmt->fetch();

            if (!$record) {
                return ['success' => false, 'message' => 'ورودی برای ثبت خروج یافت نشد'];
            }

            // محاسبه ساعات کار و تأخیر
            $calculations = $this->calculateWorkHours($record, $user_id);

            // بروزرسانی خروج
            $stmt = $this->db->prepare("
                UPDATE attendance_records
                SET check_out = NOW(),
                    check_out_ip = ?,
                    work_hours = ?,
                    late_minutes = ?,
                    penalty_minutes = ?,
                    overtime_minutes = ?
                WHERE id = ?
            ");

            if (
                $stmt->execute([
                    $ip_address,
                    $calculations['work_hours'],
                    $calculations['late_minutes'],
                    $calculations['penalty_minutes'],
                    $calculations['overtime_minutes'],
                    $record['id']
                ])
            ) {
                return [
                    'success' => true,
                    'message' => 'خروج با موفقیت ثبت شد',
                    'work_hours' => $calculations['work_hours'],
                    'late_minutes' => $calculations['late_minutes'],
                    'penalty_minutes' => $calculations['penalty_minutes']
                ];
            }

            return ['success' => false, 'message' => 'خطا در ثبت خروج'];

        } catch (Exception $e) {
            error_log("CheckOut error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    // ===================================
    // محاسبه ساعات کار و تأخیر
    // ===================================
    private function calculateWorkHours($record, $user_id)
    {
        $user = $this->getUserInfo($user_id);
        $check_in = new DateTime($record['check_in']);
        $check_out = new DateTime();

        // تعیین زمان شروع و پایان شیفت
        $shift_start = $record['shift_number'] == 1 ? $user['shift1_start'] : $user['shift2_start'];
        $shift_end = $record['shift_number'] == 1 ? $user['shift1_end'] : $user['shift2_end'];

        $expected_start = new DateTime($record['date'] . ' ' . $shift_start);
        $expected_end = new DateTime($record['date'] . ' ' . $shift_end);

        // محاسبه ساعات کار واقعی
        $work_interval = $check_in->diff($check_out);
        $work_hours = $work_interval->h + ($work_interval->i / 60);

        // محاسبه تأخیر
        $late_minutes = 0;
        $penalty_minutes = 0;

        if ($check_in > $expected_start) {
            $late_interval = $expected_start->diff($check_in);
            $late_minutes = ($late_interval->h * 60) + $late_interval->i;

            // تا 10 دقیقه بخشوده می‌شود
            if ($late_minutes > 10) {
                $actual_late = $late_minutes - 10;
                // هر دقیقه تأخیر = 2 دقیقه جریمه
                $penalty_minutes = $actual_late * 2;
            } else {
                $late_minutes = 0;
            }
        }

        // محاسبه اضافه‌کار
        $overtime_minutes = 0;
        if ($check_out > $expected_end) {
            $overtime_interval = $expected_end->diff($check_out);
            $overtime_minutes = ($overtime_interval->h * 60) + $overtime_interval->i;
        }

        return [
            'work_hours' => round($work_hours, 2),
            'late_minutes' => $late_minutes,
            'penalty_minutes' => $penalty_minutes,
            'overtime_minutes' => $overtime_minutes
        ];
    }

    // ===================================
    // تعیین شماره شیفت
    // ===================================
    private function determineShiftNumber($user_id, $date, $user)
    {
        // بررسی ثبت قبلی
        $stmt = $this->db->prepare("
            SELECT * FROM attendance_records
            WHERE user_id = ? AND date = ?
            ORDER BY shift_number DESC
        ");
        $stmt->execute([$user_id, $date]);
        $records = $stmt->fetchAll();

        if (empty($records)) {
            return 1; // اولین ورود
        }

        // اگر شیفت دوم ندارد
        if ($user['shift_type'] == 'single') {
            $last_record = $records[0];
            if ($last_record['check_out'] === null) {
                return false; // قبلاً ورود ثبت شده و خروج نزده
            }
            return false; // یک شیفته‌ها نمی‌توانند دوباره ورود بزنند
        }

        // بررسی شیفت دوم
        if ($user['shift_type'] == 'double') {
            $last_record = $records[0];

            if ($last_record['shift_number'] == 1) {
                // بررسی خروج شیفت اول
                if ($last_record['check_out'] === null) {
                    return false; // هنوز خروج نزده
                }

                // بررسی فاصله زمانی (حداقل 1 ساعت، حداکثر 12 ساعت)
                $checkout_time = new DateTime($last_record['check_out']);
                $now = new DateTime();
                $diff = $checkout_time->diff($now);
                $hours_diff = ($diff->h) + ($diff->days * 24);

                if ($hours_diff < 1) {
                    return -1; // کمتر از 1 ساعت
                }
                if ($hours_diff > 12) {
                    return -1; // بیشتر از 12 ساعت
                }

                return 2; // شیفت دوم
            } else {
                // شیفت دوم قبلاً ثبت شده
                if ($last_record['check_out'] === null) {
                    return false;
                }
                return false; // هر دو شیفت تمام شده
            }
        }

        return false;
    }

    // ===================================
    // محاسبه کسرکار روزانه
    // ===================================
    public function getDailyDeficit($user_id, $date)
    {
        try {
            $user = $this->getUserInfo($user_id);
            $expected_hours = $user['daily_work_hours'];

            // دریافت رکوردهای حضور
            $stmt = $this->db->prepare("
                SELECT * FROM attendance_records
                WHERE user_id = ? AND date = ?
            ");
            $stmt->execute([$user_id, $date]);
            $records = $stmt->fetchAll();

            $total_work_hours = 0;
            $total_penalty_minutes = 0;

            foreach ($records as $record) {
                if ($record['check_out']) {
                    $total_work_hours += $record['work_hours'];
                    $total_penalty_minutes += $record['penalty_minutes'];
                }
            }

            // کسر جریمه از ساعات کار
            $penalty_hours = $total_penalty_minutes / 60;
            $effective_hours = $total_work_hours - $penalty_hours;

            $deficit_hours = $expected_hours - $effective_hours;

            return [
                'expected_hours' => $expected_hours,
                'work_hours' => $total_work_hours,
                'penalty_hours' => round($penalty_hours, 2),
                'effective_hours' => round($effective_hours, 2),
                'deficit_hours' => round($deficit_hours, 2)
            ];

        } catch (Exception $e) {
            error_log("GetDailyDeficit error: " . $e->getMessage());
            return null;
        }
    }

    // ===================================
    // محاسبه کسرکار ماهانه
    // ===================================
    public function getMonthlyDeficit($user_id, $year, $month)
    {
        try {
            // دریافت تمام روزهای کاری ماه
            $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
            $end_date = date("Y-m-t", strtotime($start_date));

            $stmt = $this->db->prepare("
                SELECT date, 
                       SUM(work_hours) as total_work,
                       SUM(penalty_minutes) as total_penalty,
                       SUM(overtime_minutes) as total_overtime
                FROM attendance_records
                WHERE user_id = ? 
                AND date BETWEEN ? AND ?
                AND check_out IS NOT NULL
                GROUP BY date
            ");
            $stmt->execute([$user_id, $start_date, $end_date]);
            $daily_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $user = $this->getUserInfo($user_id);
            $expected_daily = $user['daily_work_hours'];

            $total_expected = 0;
            $total_worked = 0;
            $total_penalty = 0;
            $total_overtime = 0;

            // شمارش روزهای کاری
            $current = new DateTime($start_date);
            $end = new DateTime($end_date);

            while ($current <= $end) {
                $date_str = $current->format('Y-m-d');

                // بررسی تعطیلی و جمعه
                if (!$this->isHoliday($date_str) && !$this->isFriday($date_str)) {
                    $total_expected += $expected_daily;
                }

                $current->modify('+1 day');
            }

            // جمع ساعات کار واقعی
            foreach ($daily_records as $record) {
                $total_worked += $record['total_work'];
                $total_penalty += $record['total_penalty'];
                $total_overtime += $record['total_overtime'];
            }

            $penalty_hours = $total_penalty / 60;
            $overtime_hours = $total_overtime / 60;
            $effective_hours = $total_worked - $penalty_hours;
            $deficit_hours = $total_expected - $effective_hours;

            return [
                'expected_hours' => round($total_expected, 2),
                'worked_hours' => round($total_worked, 2),
                'penalty_hours' => round($penalty_hours, 2),
                'overtime_hours' => round($overtime_hours, 2),
                'effective_hours' => round($effective_hours, 2),
                'deficit_hours' => round($deficit_hours, 2)
            ];

        } catch (Exception $e) {
            error_log("GetMonthlyDeficit error: " . $e->getMessage());
            return null;
        }
    }

    // ===================================
    // دریافت وضعیت حضور امروز
    // ===================================
    public function getTodayStatus($user_id)
    {
        try {
            $today = date('Y-m-d');

            $stmt = $this->db->prepare("
                SELECT * FROM attendance_records
                WHERE user_id = ? AND date = ?
                ORDER BY shift_number ASC
            ");
            $stmt->execute([$user_id, $today]);
            $records = $stmt->fetchAll();

            $status = [
                'is_checked_in' => false,
                'can_check_in' => true,
                'can_check_out' => false,
                'shifts' => []
            ];

            if (!empty($records)) {
                foreach ($records as $record) {
                    $shift = [
                        'number' => $record['shift_number'],
                        'check_in' => $record['check_in'],
                        'check_out' => $record['check_out'],
                        'work_hours' => $record['work_hours']
                    ];

                    if ($record['check_out'] === null) {
                        $status['is_checked_in'] = true;
                        $status['can_check_in'] = false;
                        $status['can_check_out'] = true;
                    }

                    $status['shifts'][] = $shift;
                }

                // بررسی امکان ورود مجدد
                $last_record = end($records);
                $user = $this->getUserInfo($user_id);

                if (
                    $user['shift_type'] == 'double' &&
                    $last_record['shift_number'] == 1 &&
                    $last_record['check_out'] !== null
                ) {
                    $status['can_check_in'] = true;
                }
            }

            return $status;

        } catch (Exception $e) {
            error_log("GetTodayStatus error: " . $e->getMessage());
            return null;
        }
    }

    // ===================================
    // دریافت تقویم حضور
    // ===================================
    public function getAttendanceCalendar($user_id, $year, $month)
    {
        try {
            $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
            $end_date = date("Y-m-t", strtotime($start_date));

            $stmt = $this->db->prepare("
                SELECT date, 
                       SUM(work_hours) as work_hours,
                       SUM(late_minutes) as late_minutes,
                       SUM(penalty_minutes) as penalty_minutes,
                       MAX(check_in) as first_check_in,
                       MAX(check_out) as last_check_out
                FROM attendance_records
                WHERE user_id = ? AND date BETWEEN ? AND ?
                GROUP BY date
            ");
            $stmt->execute([$user_id, $start_date, $end_date]);
            $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $calendar = [];
            $current = new DateTime($start_date);
            $end = new DateTime($end_date);

            while ($current <= $end) {
                $date_str = $current->format('Y-m-d');

                $day_data = [
                    'date' => $date_str,
                    'is_holiday' => $this->isHoliday($date_str),
                    'is_friday' => $this->isFriday($date_str),
                    'status' => 'absent',
                    'work_hours' => 0,
                    'late_minutes' => 0,
                    'has_deficit' => false
                ];

                // بررسی حضور
                foreach ($attendance as $att) {
                    if ($att['date'] == $date_str) {
                        $user = $this->getUserInfo($user_id);
                        $expected = $user['daily_work_hours'];
                        $penalty_hours = $att['penalty_minutes'] / 60;
                        $effective = $att['work_hours'] - $penalty_hours;

                        $day_data['work_hours'] = $att['work_hours'];
                        $day_data['late_minutes'] = $att['late_minutes'];
                        $day_data['has_deficit'] = $effective < $expected;

                        if ($att['late_minutes'] > 30) {
                            $day_data['status'] = 'late_major';
                        } elseif ($att['late_minutes'] > 0) {
                            $day_data['status'] = 'late_minor';
                        } else {
                            $day_data['status'] = 'present';
                        }
                        break;
                    }
                }

                // بررسی مرخصی، مأموریت و غیره
                // (این بخش بعداً با کلاس LeaveManager یکپارچه می‌شود)

                $calendar[] = $day_data;
                $current->modify('+1 day');
            }

            return $calendar;

        } catch (Exception $e) {
            error_log("GetAttendanceCalendar error: " . $e->getMessage());
            return [];
        }
    }

    // ===================================
    // پیشنهاد درخواست برای کسرکار
    // ===================================
    public function suggestRequest($user_id, $date)
    {
        try {
            $deficit = $this->getDailyDeficit($user_id, $date);

            if ($deficit['deficit_hours'] <= 0) {
                return null; // کسرکاری وجود ندارد
            }

            $deficit_minutes = $deficit['deficit_hours'] * 60;
            $suggestions = [];

            // پیشنهاد بر اساس میزان کسرکار
            if ($deficit_minutes <= 15) {
                $suggestions[] = [
                    'type' => 'forget',
                    'title' => 'فراموشی ثبت',
                    'description' => 'درخواست فراموشی ثبت ورود یا خروج'
                ];
            } elseif ($deficit_minutes <= 60) {
                $suggestions[] = [
                    'type' => 'pass',
                    'title' => 'پاس 1 ساعته',
                    'description' => 'درخواست پاس برای ' . round($deficit_minutes) . ' دقیقه'
                ];
            } elseif ($deficit_minutes <= 240) {
                $suggestions[] = [
                    'type' => 'pass',
                    'title' => 'پاس ' . round($deficit_minutes / 60, 1) . ' ساعته',
                    'description' => 'درخواست پاس حداکثر 4 ساعت'
                ];
            } else {
                $user = $this->getUserInfo($user_id);
                $days_needed = $deficit['deficit_hours'] / $user['daily_work_hours'];

                $suggestions[] = [
                    'type' => 'leave',
                    'title' => 'مرخصی ' . round($days_needed, 1) . ' روزه',
                    'description' => 'درخواست مرخصی برای جبران کسرکار'
                ];
            }

            return [
                'deficit_hours' => $deficit['deficit_hours'],
                'suggestions' => $suggestions
            ];

        } catch (Exception $e) {
            error_log("SuggestRequest error: " . $e->getMessage());
            return null;
        }
    }

    // ===================================
    // توابع کمکی
    // ===================================

    private function isIPAllowed($ip)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM ip_whitelist WHERE is_active = 1
            ");
            $stmt->execute();
            $whitelist = $stmt->fetchAll();

            foreach ($whitelist as $range) {
                if ($this->ipInRange($ip, $range['ip_address'], $range['subnet_mask'])) {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    private function ipInRange($ip, $network, $mask)
    {
        $ip_long = ip2long($ip);
        $network_long = ip2long($network);
        $mask_long = ip2long($mask);

        return ($ip_long & $mask_long) == ($network_long & $mask_long);
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
        $day_of_week = date('N', strtotime($date));
        return $day_of_week == 5; // جمعه
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