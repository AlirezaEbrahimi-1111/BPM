<?php

/**
 * کلاس محاسبات ساعات کار و کسری
 */

class AttendanceCalculator
{

    private $db;
    private $userId;

    public function __construct($db, $userId)
    {
        $this->db = $db;
        $this->userId = $userId;
    }

    /**
     * دریافت تنظیمات کاربر
     */
    public function getUserSettings()
    {
        $stmt = $this->db->prepare("
            SELECT 
                shift_count,
                shift_1_start,
                shift_1_end,
                shift_2_start,
                shift_2_end,
                daily_salary
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$this->userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * محاسبه ساعات کار تک روز
     * @param string $date تاریخ (YYYY-MM-DD)
     * @param string $checkIn ساعت ورود (HH:MM:SS)
     * @param string $checkOut ساعت خروج (HH:MM:SS)
     * @return array
     */
    public function calculateDailyWorkHours($date, $checkIn, $checkOut)
    {
        if (!$checkIn || !$checkOut) {
            return [
                'work_hours' => 0,
                'late_minutes' => 0,
                'overtime_minutes' => 0,
                'shortage_hours' => 0
            ];
        }

        $settings = $this->getUserSettings();
        if (!$settings) {
            return null;
        }

        $checkInTime = new \DateTime($checkIn);
        $checkOutTime = new \DateTime($checkOut);

        // محاسبه ساعات کار
        $interval = $checkInTime->diff($checkOutTime);
        $workHours = $interval->h + ($interval->i / 60);

        // تعیین شیفت
        $checkInHour = $checkInTime->format('H:i:s');
        $shift1Start = $settings['shift_1_start'];
        $shift1End = $settings['shift_1_end'];

        $expectedHours = 0;
        $lateMinutes = 0;

        // بررسی شیفت 1
        if ($checkInHour >= $shift1Start) {
            $shift1StartTime = \DateTime::createFromFormat('H:i:s', $shift1Start);
            $expectedHours = $this->getShiftDuration($shift1Start, $shift1End);

            // محاسبه تأخیر
            if ($checkInHour > $shift1Start) {
                $lateTime = $checkInTime->diff($shift1StartTime);
                $lateMinutes = ($lateTime->h * 60) + $lateTime->i;
            }
        } else if ($settings['shift_count'] == 2 && $settings['shift_2_start']) {
            // شیفت 2
            $shift2Start = $settings['shift_2_start'];
            $shift2End = $settings['shift_2_end'];
            $expectedHours = $this->getShiftDuration($shift2Start, $shift2End);

            $shift2StartTime = \DateTime::createFromFormat('H:i:s', $shift2Start);
            if ($checkInHour > $shift2Start) {
                $lateTime = $checkInTime->diff($shift2StartTime);
                $lateMinutes = ($lateTime->h * 60) + $lateTime->i;
            }
        }

        // محاسبه کسری یا اضافه
        $shortage = 0;
        $overtime = 0;

        if ($workHours < $expectedHours) {
            $shortage = $expectedHours - $workHours;
        } else {
            $overtime = ($workHours - $expectedHours) * 60; // به دقیقه
        }

        return [
            'work_hours' => round($workHours, 2),
            'late_minutes' => $lateMinutes,
            'overtime_minutes' => intval($overtime),
            'shortage_hours' => round($shortage, 2),
            'expected_hours' => $expectedHours
        ];
    }

    /**
     * محاسبه مدت شیفت
     */
    private function getShiftDuration($start, $end)
    {
        $startTime = \DateTime::createFromFormat('H:i:s', $start);
        $endTime = \DateTime::createFromFormat('H:i:s', $end);

        $interval = $startTime->diff($endTime);
        return $interval->h + ($interval->i / 60);
    }

    /**
     * محاسبه کسری تا امروز
     * @return array
     */
    public function calculateTotalShortage()
    {
        $stmt = $this->db->prepare("
            SELECT 
                SUM(shortage_hours) as total_shortage,
                SUM(overtime_minutes) as total_overtime
            FROM attendance_records
            WHERE user_id = ?
            AND date <= CURDATE()
        ");
        $stmt->execute([$this->userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_shortage_hours' => floatval($result['total_shortage'] ?? 0),
            'total_overtime_minutes' => intval($result['total_overtime'] ?? 0),
            'total_overtime_hours' => intval(($result['total_overtime'] ?? 0) / 60)
        ];
    }

    /**
     * محاسبه مزد کسری
     * @param float $shortageHours تعداد ساعات کسری
     * @return float
     */
    public function calculateShortageSalary($shortageHours)
    {
        $settings = $this->getUserSettings();
        if (!$settings || $settings['daily_salary'] == 0) {
            return 0;
        }

        // مزد هر ساعت = حقوق روزانه / ساعات کاری روزانه
        $expectedDailyHours = $this->getExpectedDailyHours();
        $hourlyRate = $settings['daily_salary'] / $expectedDailyHours;

        return round($shortageHours * $hourlyRate, 2);
    }

    /**
     * دریافت ساعات کاری مورد انتظار روزانه
     */
    private function getExpectedDailyHours()
    {
        $settings = $this->getUserSettings();

        if ($settings['shift_count'] == 1) {
            return $this->getShiftDuration($settings['shift_1_start'], $settings['shift_1_end']);
        } else {
            $shift1 = $this->getShiftDuration($settings['shift_1_start'], $settings['shift_1_end']);
            $shift2 = $this->getShiftDuration($settings['shift_2_start'], $settings['shift_2_end']);
            return ($shift1 + $shift2) / 2; // میانگین دو شیفت
        }
    }

    /**
     * محاسبه کسری ماهانه
     * @param int $month ماه شمسی
     * @param int $year سال شمسی
     * @return array
     */
    public function calculateMonthlyShortage($month, $year)
    {
        // تبدیل ماه و سال شمسی به میلادی
        $startJalali = sprintf('%04d-%02d-01', $year, $month);
        $startGregorian = JalaliDate::toGregorian($startJalali);

        $endJalali = sprintf('%04d-%02d-30', $year, $month);
        if ($month == 12) {
            $endJalali = sprintf('%04d-12-29', $year); // آخر اسفند
        }
        $endGregorian = JalaliDate::toGregorian($endJalali);

        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN check_in IS NOT NULL AND check_out IS NOT NULL THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN check_in IS NULL AND check_out IS NULL THEN 1 ELSE 0 END) as absent_days,
                SUM(shortage_hours) as total_shortage,
                SUM(overtime_minutes) as total_overtime
            FROM attendance_records
            WHERE user_id = ?
            AND date BETWEEN ? AND ?
        ");
        $stmt->execute([$this->userId, $startGregorian, $endGregorian]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_days' => $result['total_days'],
            'present_days' => $result['present_days'] ?? 0,
            'absent_days' => $result['absent_days'] ?? 0,
            'total_shortage_hours' => floatval($result['total_shortage'] ?? 0),
            'total_overtime_minutes' => intval($result['total_overtime'] ?? 0),
            'shortage_salary' => $this->calculateShortageSalary(floatval($result['total_shortage'] ?? 0))
        ];
    }

    /**
     * بررسی اینکه آیا کاربر امروز ورود ثبت کرده
     * @return bool
     */
    public function hasCheckedInToday()
    {
        $stmt = $this->db->prepare("
            SELECT id FROM attendance_records
            WHERE user_id = ? AND date = CURDATE()
            LIMIT 1
        ");
        $stmt->execute([$this->userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * دریافت اطلاعات امروز
     * @return array
     */
    public function getTodayStatus()
    {
        $stmt = $this->db->prepare("
            SELECT 
                check_in,
                check_out,
                work_hours,
                shortage_hours,
                overtime_minutes,
                shift_number
            FROM attendance_records
            WHERE user_id = ? AND date = CURDATE()
            LIMIT 1
        ");
        $stmt->execute([$this->userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?? [
            'check_in' => null,
            'check_out' => null,
            'work_hours' => 0,
            'shortage_hours' => 0,
            'overtime_minutes' => 0,
            'shift_number' => 1
        ];
    }

    /**
     * محاسبه روزهای کاری در فاصله تاریخی
     * @param string $startDate
     * @param string $endDate
     * @param array $holidays
     * @return int
     */
    public function countWorkdays($startDate, $endDate, $holidays = [])
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $end->modify('+1 day'); // شامل تاریخ آخر

        $workdayCount = 0;
        while ($start < $end) {
            $dateStr = $start->format('Y-m-d');
            if (JalaliDate::isWorkday($dateStr, $holidays)) {
                $workdayCount++;
            }
            $start->modify('+1 day');
        }

        return $workdayCount;
    }

    /**
     * محاسبه ساعات در فاصله تاریخی با احتساب شیفت
     * @param string $startDate
     * @param string $endDate
     * @return float
     */
    public function calculateHoursBetween($startDate, $startTime, $endDate, $endTime)
    {
        $start = \DateTime::createFromFormat('Y-m-d H:i:s', $startDate . ' ' . $startTime);
        $end = \DateTime::createFromFormat('Y-m-d H:i:s', $endDate . ' ' . $endTime);

        $interval = $start->diff($end);
        return $interval->h + ($interval->i / 60);
    }
}

?>