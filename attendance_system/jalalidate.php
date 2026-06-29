<?php

/**
 * کلاس تبدیل تاریخ میلادی ↔ شمسی
 * استفاده از jalaali-js
 */

class JalaliDate
{

    /**
     * تبدیل تاریخ میلادی به شمسی
     * 
     * @param string $gregorianDate تاریخ میلادی (YYYY-MM-DD)
     * @return string تاریخ شمسی (YYYY-MM-DD)
     */
    public static function toJalali($gregorianDate)
    {
        if (empty($gregorianDate) || $gregorianDate == '0000-00-00') {
            return null;
        }

        $g = explode('-', $gregorianDate);
        $gy = (int) $g[0];
        $gm = (int) $g[1];
        $gd = (int) $g[2];

        if ($gm > 2) {
            $gy2 = $gy + 1;
        } else {
            $gy2 = $gy;
        }

        $days = (365 * $gy) + ((int) (($gy2 + 3) / 4)) - ((int) (($gy + 99) / 100)) + ((int) (($gy + 399) / 400)) - 80 + $gd + [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334][$gm - 1];

        $jy = -1600 + (400 * (int) ($days / 146097)) + $days % 146097;

        $leap = true;
        if ($jy % 33 != 4 && $jy % 128 != 99) {
            $leap = false;
        }

        $days = $days % 146097;
        if ($days > 36524) {
            $days--;
            $jy += (int) ($days / 36524);
            $days = $days % 36524;

            if ($days >= 365) {
                $days++;
            }
        }

        $jd = 0;
        for ($i = 0; $i < 24; $i++) {
            $v = (int) (($i + 1) * 365.2425);
            if ($days < $v) {
                $jd = $i;
                break;
            }
        }

        $days -= (int) ($jd * 365 + ($jd / 33) - (int) ($jd / 4));

        if ($days >= 366 && $jd == 32) {
            $days = 365;
        }

        $jp = 0;
        for ($i = 1; $i <= 12; $i++) {
            $v = ($i <= 6) ? 31 : 30;
            if ($i == 12) {
                $v = ($jd == 32) ? 30 : 29;
            }
            if ($days < $v) {
                $jp = $i;
                break;
            }
            $days -= $v;
        }

        $jy = 1600 + ($jd * 128) + (int) ($jy / 128);

        return sprintf('%04d-%02d-%02d', $jy, $jp, $days + 1);
    }

    /**
     * تبدیل تاریخ شمسی به میلادی
     * 
     * @param string $jalaliDate تاریخ شمسی (YYYY-MM-DD)
     * @return string تاریخ میلادی (YYYY-MM-DD)
     */
    public static function toGregorian($jalaliDate)
    {
        if (empty($jalaliDate)) {
            return null;
        }

        $j = explode('-', $jalaliDate);
        $jy = (int) $j[0];
        $jm = (int) $j[1];
        $jd = (int) $j[2];

        $jy += 1595;
        $days = (365 * $jy) + ((int) (($jy / 33) * 8)) + ((int) (($jy % 33 + 3) / 4)) + 78 + $jd;

        for ($i = 0; $i < $jm - 1; $i++) {
            if ($i < 6) {
                $days += 31;
            } else {
                $days += 30;
            }
        }

        $gy = 400 * (int) ($days / 146097);
        $days %= 146097;

        $flag = true;
        if ($days >= 36525) {
            $days--;
            $gy += 100 * (int) ($days / 36524);
            $days %= 36524;

            if ($days >= 365) {
                $days++;
            }
            $flag = false;
        }

        $gy += 4 * (int) ($days / 1461);
        $days %= 1461;

        if ($flag) {
            if ($days >= 365) {
                $days--;
                $gy += (int) ($days / 365);
                $days = ($days % 365);
            }
        } else {
            $gy += (int) ($days / 365);
            $days = $days % 365;
        }

        $gm = 0;
        $gd = $days + 1;

        if ($gy % 400 == 0 || ($gy % 100 != 0 && $gy % 4 == 0)) {
            $v = [0, 31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        } else {
            $v = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        }

        for ($i = 1; $i <= 12; $i++) {
            if ($gd <= $v[$i]) {
                $gm = $i;
                break;
            }
            $gd -= $v[$i];
        }

        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    /**
     * دریافت شماره روز در هفته
     * 0 = شنبه، 6 = جمعه
     * 
     * @param string $date تاریخ (YYYY-MM-DD)
     * @return int
     */
    public static function getDayOfWeek($date)
    {
        $timestamp = strtotime($date);
        return date('w', $timestamp);
    }

    /**
     * بررسی اینکه روز جمعه است یا نه
     * 
     * @param string $date تاریخ (YYYY-MM-DD)
     * @return bool
     */
    public static function isFriday($date)
    {
        return self::getDayOfWeek($date) == 5;
    }

    /**
     * بررسی اینکه روز کاری است یا نه (بدون احتساب جمعه و تعطیلات)
     * 
     * @param string $date تاریخ (YYYY-MM-DD)
     * @param array $holidays لیست تعطیلات
     * @return bool
     */
    public static function isWorkday($date, $holidays = [])
    {
        if (self::isFriday($date)) {
            return false;
        }

        if (in_array($date, $holidays)) {
            return false;
        }

        return true;
    }

    /**
     * تبدیل اعداد فارسی به انگلیسی
     * 
     * @param string $text
     * @return string
     */
    public static function persianToEnglish($text)
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($persian, $english, $text);
    }

    /**
     * تبدیل اعداد انگلیسی به فارسی
     * 
     * @param string $text
     * @return string
     */
    public static function englishToPersian($text)
    {
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return str_replace($english, $persian, $text);
    }

    /**
     * نمایش تاریخ فارسی
     * 
     * @param string $jalaliDate
     * @param string $format
     * @return string
     */
    public static function formatJalali($jalaliDate, $format = 'Y/m/d')
    {
        if (empty($jalaliDate)) {
            return '';
        }

        $months = [
            'فروردین',
            'اردیبهشت',
            'خرداد',
            'تیر',
            'مرداد',
            'شهریور',
            'مهر',
            'آبان',
            'آذر',
            'دی',
            'بهمن',
            'اسفند'
        ];

        $days = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];

        $gregorianDate = self::toGregorian($jalaliDate);
        $timestamp = strtotime($gregorianDate);

        $j = explode('-', $jalaliDate);
        $jy = $j[0];
        $jm = (int) $j[1];
        $jd = (int) $j[2];

        $result = str_replace(
            ['Y', 'm', 'd', 'l', 'F', 'n', 'j'],
            [$jy, str_pad($jm, 2, '0', STR_PAD_LEFT), str_pad($jd, 2, '0', STR_PAD_LEFT), $days[date('w', $timestamp)], $months[$jm - 1], $jm, $jd],
            $format
        );

        return self::englishToPersian($result);
    }

    /**
     * محاسبه تعداد روزهای میان دو تاریخ
     * 
     * @param string $startDate
     * @param string $endDate
     * @return int
     */
    public static function daysBetween($startDate, $endDate)
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $interval = $start->diff($end);
        return $interval->days;
    }

    /**
     * افزودن روز به تاریخ
     * 
     * @param string $date
     * @param int $days
     * @return string
     */
    public static function addDays($date, $days)
    {
        $date = new \DateTime($date);
        $date->modify("+{$days} days");
        return $date->format('Y-m-d');
    }

    /**
     * کم کردن روز از تاریخ
     * 
     * @param string $date
     * @param int $days
     * @return string
     */
    public static function subtractDays($date, $days)
    {
        $date = new \DateTime($date);
        $date->modify("-{$days} days");
        return $date->format('Y-m-d');
    }
}

?>