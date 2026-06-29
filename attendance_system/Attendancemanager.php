<?php

/**
 * کلاس مدیریت حضور و غیاب
 */

class AttendanceManager
{

    private $db;
    private $calculator;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * ثبت ورود کاربر
     * @param int $userId
     * @param string $checkInTime
     * @param string $ipAddress
     * @return array
     */
    public function checkIn($userId, $ipAddress = null, $organizationId = null)
    {

        try {
            $date = date('Y-m-d');

            // بررسی اینکه آیا رکورد امروز موجود است
            $stmt = $this->db->prepare("
                SELECT id FROM attendance_records
                WHERE user_id = ? AND date = ?
                LIMIT 1
            ");
            $stmt->execute([$userId, $date]);
            $existingRecord = $stmt->fetch();

            if (!$existingRecord) {
                // ایجاد رکورد جدید
                $stmt = $this->db->prepare("
    INSERT INTO attendance_records (user_id, organization_id, date, shift_number, check_in, check_in_ip)
    VALUES (?, ?, ?, ?, NOW(), ?)
");


                $shiftNumber = $this->determinShift($userId, $checkInTime);
                $stmt->execute([$userId, $organizationId, $date, $shiftNumber, $ipAddress]);

                return [
                    'success' => true,
                    'message' => 'ورود با موفقیت ثبت شد',
                    'shift_number' => $shiftNumber,
                    'check_in_time' => date('H:i')
                ];
            } else {
                // به روزرسانی رکورد موجود
                $stmt = $this->db->prepare("
                    UPDATE attendance_records
                    SET check_in = ?, check_in_ip = ?
                    WHERE id = ?
                ");
                $stmt->execute([$checkInTime, $ipAddress, $existingRecord['id']]);

                return [
                    'success' => true,
                    'message' => 'ورود به روزرسانی شد',
                    'check_in_time' => date('H:i')
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در ثبت ورود: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ثبت خروج کاربر
     * @param int $userId
     * @param string $checkOutTime
     * @param string $ipAddress
     * @return array
     */
    public function checkOut($userId, $checkOutTime, $ipAddress = null)
    {
        try {
            $date = date('Y-m-d');

            // بررسی اینکه آیا رکورد امروز موجود است
            $stmt = $this->db->prepare("
                SELECT id, check_in, shift_number FROM attendance_records
                WHERE user_id = ? AND date = ?
                LIMIT 1
            ");
            $stmt->execute([$userId, $date]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return [
                    'success' => false,
                    'message' => 'ابتدا باید ورود ثبت کنید'
                ];
            }

            // محاسبه ساعات کار
            $this->calculator = new AttendanceCalculator($this->db, $userId);
            $calculation = $this->calculator->calculateDailyWorkHours($date, $record['check_in'], $checkOutTime);

            // به روزرسانی رکورد
            $stmt = $this->db->prepare("
                UPDATE attendance_records
                SET 
                    check_out = ?,
                    check_out_ip = ?,
                    work_hours = ?,
                    late_minutes = ?,
                    overtime_minutes = ?,
                    shortage_hours = ?,
                    shortage_calculated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $checkOutTime,
                $ipAddress,
                $calculation['work_hours'],
                $calculation['late_minutes'],
                $calculation['overtime_minutes'],
                $calculation['shortage_hours'],
                $record['id']
            ]);

            return [
                'success' => true,
                'message' => 'خروج با موفقیت ثبت شد',
                'work_hours' => $calculation['work_hours'],
                'check_out_time' => date('H:i'),
                'shortage_hours' => $calculation['shortage_hours'],
                'overtime_minutes' => $calculation['overtime_minutes']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در ثبت خروج: ' . $e->getMessage()
            ];
        }
    }

    /**
     * تعیین شماره شیفت بر اساس زمان ورود
     * @param int $userId
     * @param string $checkInTime
     * @return int
     */
    private function determinShift($userId, $checkInTime)
    {
        $stmt = $this->db->prepare("
            SELECT shift_count, shift_1_start, shift_2_start
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return 1;
        }

        if ($user['shift_count'] == 1) {
            return 1;
        }

        // شیفت 2
        $checkInHour = substr($checkInTime, 0, 5);
        $shift2Start = substr($user['shift_2_start'], 0, 5);

        return ($checkInHour >= $shift2Start) ? 2 : 1;
    }

    /**
     * دریافت تقویم ماه
     * @param int $year سال شمسی
     * @param int $month ماه شمسی
     * @param int $userId
     * @return array
     */
    public function getMonthlyCalendar($year, $month, $userId)
    {
        $jalaliDate = sprintf('%04d-%02d-01', $year, $month);
        $gregorianDate = JalaliDate::toGregorian($jalaliDate);

        // دریافت تعطیلات
        $stmt = $this->db->prepare("
            SELECT date FROM holidays
            WHERE YEAR(date) = YEAR(?)
            AND MONTH(date) = MONTH(?)
        ");
        $stmt->execute([$gregorianDate, $gregorianDate]);
        $holidays = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // دریافت رکوردهای حضور
        $stmt = $this->db->prepare("
            SELECT 
                date,
                check_in,
                check_out,
                work_hours,
                shortage_hours,
                overtime_minutes,
                is_mission
            FROM attendance_records
            WHERE user_id = ?
            AND YEAR(date) = YEAR(?)
            AND MONTH(date) = MONTH(?)
            ORDER BY date ASC
        ");
        $stmt->execute([$userId, $gregorianDate, $gregorianDate]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // تنظیم آرایه
        $calendar = [];
        $startGregorian = $gregorianDate;
        $endGregorian = JalaliDate::toGregorian(sprintf('%04d-%02d-30', $year, $month));

        $start = new \DateTime($startGregorian);
        $end = new \DateTime($endGregorian);
        $end->modify('+1 day');

        while ($start < $end) {
            $dateStr = $start->format('Y-m-d');
            $jalaliDateStr = JalaliDate::toJalali($dateStr);

            $dayData = [
                'jalali_date' => $jalaliDateStr,
                'gregorian_date' => $dateStr,
                'day_of_week' => JalaliDate::getDayOfWeek($dateStr),
                'is_friday' => JalaliDate::isFriday($dateStr),
                'is_holiday' => in_array($dateStr, $holidays),
                'check_in' => null,
                'check_out' => null,
                'work_hours' => 0,
                'shortage_hours' => 0,
                'overtime_minutes' => 0,
                'status' => 'absent' // absent, present, incomplete, holiday, mission
            ];

            // پیدا کردن رکورد
            foreach ($records as $record) {
                if ($record['date'] == $dateStr) {
                    $dayData['check_in'] = $record['check_in'];
                    $dayData['check_out'] = $record['check_out'];
                    $dayData['work_hours'] = $record['work_hours'];
                    $dayData['shortage_hours'] = $record['shortage_hours'];
                    $dayData['overtime_minutes'] = $record['overtime_minutes'];

                    if ($record['is_mission']) {
                        $dayData['status'] = 'mission';
                    } elseif ($record['check_in'] && $record['check_out']) {
                        $dayData['status'] = 'present';
                    } elseif ($record['check_in'] && !$record['check_out']) {
                        $dayData['status'] = 'incomplete';
                    }
                    break;
                }
            }

            // تعیین وضعیت
            if ($dayData['is_friday'] || $dayData['is_holiday']) {
                $dayData['status'] = 'holiday';
            }

            $calendar[] = $dayData;
            $start->modify('+1 day');
        }

        return $calendar;
    }

    /**
     * دریافت اطلاعات رکورد خاص
     * @param int $userId
     * @param string $date
     * @return array
     */
    public function getAttendanceRecord($userId, $date)
    {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                user_id,
                date,
                shift_number,
                check_in,
                check_out,
                check_in_ip,
                check_out_ip,
                is_mission,
                work_hours,
                late_minutes,
                penalty_minutes,
                overtime_minutes,
                shortage_hours,
                notes,
                created_at,
                updated_at
            FROM attendance_records
            WHERE user_id = ? AND date = ?
        ");
        $stmt->execute([$userId, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * دریافت وضعیت امروز
     * @param int $userId
     * @return array
     */
    public function getTodayStatus($userId)
    {
        $today = date('Y-m-d');
        $record = $this->getAttendanceRecord($userId, $today);

        if (!$record) {
            return [
                'check_in_status' => 'not_recorded',
                'check_out_status' => 'not_applicable',
                'work_hours' => 0,
                'shortage_hours' => 0,
                'overtime_minutes' => 0,
                'check_in_time' => null,
                'check_out_time' => null
            ];
        }

        return [
            'check_in_status' => $record['check_in'] ? 'recorded' : 'not_recorded',
            'check_out_status' => $record['check_out'] ? 'recorded' : 'not_recorded',
            'work_hours' => $record['work_hours'],
            'shortage_hours' => $record['shortage_hours'],
            'overtime_minutes' => $record['overtime_minutes'],
            'check_in_time' => $record['check_in'],
            'check_out_time' => $record['check_out']
        ];
    }

    /**
     * محاسبه خلاصه حضور و غیاب کارمند
     * @param int $userId
     * @param int $month
     * @param int $year
     * @return array
     */
    public function getAttendanceSummary($userId, $month = null, $year = null)
    {
        if ($month === null) {
            $month = (int) date('m');
        }
        if ($year === null) {
            $year = (int) date('Y');
        }

        // تبدیل میلادی به شمسی اگر لازم باشد
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $startGregorian = JalaliDate::toGregorian($startDate);

        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_records,
                SUM(CASE WHEN check_in IS NOT NULL AND check_out IS NOT NULL THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN check_in IS NULL AND check_out IS NULL THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN check_in IS NOT NULL AND check_out IS NULL THEN 1 ELSE 0 END) as incomplete_days,
                SUM(work_hours) as total_work_hours,
                SUM(shortage_hours) as total_shortage_hours,
                SUM(overtime_minutes) as total_overtime_minutes,
                SUM(late_minutes) as total_late_minutes
            FROM attendance_records
            WHERE user_id = ?
            AND YEAR(date) = YEAR(?)
            AND MONTH(date) = MONTH(?)
        ");
        $stmt->execute([$userId, $startGregorian, $startGregorian]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_records' => $result['total_records'] ?? 0,
            'present_days' => $result['present_days'] ?? 0,
            'absent_days' => $result['absent_days'] ?? 0,
            'incomplete_days' => $result['incomplete_days'] ?? 0,
            'total_work_hours' => floatval($result['total_work_hours'] ?? 0),
            'total_shortage_hours' => floatval($result['total_shortage_hours'] ?? 0),
            'total_overtime_hours' => intval(($result['total_overtime_minutes'] ?? 0) / 60),
            'total_late_minutes' => intval($result['total_late_minutes'] ?? 0)
        ];
    }
}

?>