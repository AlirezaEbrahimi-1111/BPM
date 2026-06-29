<?php
// ==================================================
// includes/JalaliHelper.php
// توابع کمکی برای تبدیل تاریخ میلادی به شمسی و اعداد فارسی
// ==================================================

class JalaliHelper
{
    /**
     * تبدیل تاریخ میلادی به شمسی
     */
    public static function gregorianToJalali($gy, $gm, $gd)
    {
        $g_d_n = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
        $jy = ($gy <= 1600) ? 0 : 979;
        $gy -= ($gy <= 1600) ? 621 : 1600;
        
        if ($gm > 2) {
            $gy2 = $gy + 1;
        } else {
            $gy2 = $gy;
        }
        
        $days = (365 * $gy) + (intval(($gy2 + 3) / 4)) - (intval(($gy2 + 99) / 100)) + 
                (intval(($gy2 + 399) / 400)) - 80 + $gd + $g_d_n[$gm - 1];
        
        $jy += 33 * intval($days / 12053);
        $days %= 12053;
        $jy += 4 * intval($days / 1461);
        $days %= 1461;
        
        if ($days > 365) {
            $jy += intval(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        
        if ($days < 186) {
            $jm = 1 + intval($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intval(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        
        return array($jy, $jm, $jd);
    }

    /**
     * تبدیل اعداد انگلیسی به فارسی
     */
    public static function Persian($num)
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($english, $persian, $num);
    }

    /**
     * تبدیل اعداد فارسی به انگلیسی
     */
    public static function toEnglish($str)
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($persian, $english, $str);
    }

    /**
     * فرمت کردن تاریخ میلادی (YYYY-MM-DD) به شمسی با اعداد فارسی
     */
    public static function formatJalaliDate($gregorian_date)
    {
        if (empty($gregorian_date)) {
            return '';
        }

        $parts = explode('-', $gregorian_date);
        if (count($parts) != 3) {
            return $gregorian_date;
        }
        
        list($jy, $jm, $jd) = self::gregorianToJalali(
            (int)$parts[0], 
            (int)$parts[1], 
            (int)$parts[2]
        );
        
        return self::Persian($jy) . '/' . 
               self::Persian(str_pad($jm, 2, '0', STR_PAD_LEFT)) . '/' . 
               self::Persian(str_pad($jd, 2, '0', STR_PAD_LEFT));
    }

    /**
     * نام ماه‌های شمسی
     */
    public static function getJalaliMonthName($month)
    {
        $months = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
            4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
            7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
            10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
        ];
        
        return isset($months[$month]) ? $months[$month] : '';
    }
}
?>