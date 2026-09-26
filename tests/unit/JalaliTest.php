<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  تست واحد: تبدیل تاریخ میلادی ↔ شمسی
 *  محل: /tests/unit/JalaliTest.php
 * ───────────────────────────────────────────────────────────────────
 *  چرا؟
 *    در پروژه چند پیاده‌سازی موازی تبدیل تاریخ هست
 *    (includes/JalaliHelper.php ، assets/jdf.php ،
 *     attendance_system/jalalidate.php ، و تبدیل‌های درون‌خطی در صفحات).
 *    الگوریتم‌های متفاوت = خطر اختلاف یک‌روزه/کبیسه بین صفحه‌ها.
 *
 *  این تست، جفت «canonical» را قفل می‌کند:
 *      includes/JalaliHelper.php  ≡  assets/jdf.php
 *    و اگر روزی یکی از این دو تغییر رفتار بدهد، همین‌جا لو می‌رود.
 *
 *  توجه: attendance_system/jalalidate.php عمدا این‌جا محک تطبیق نمی‌خورد
 *  (نیاز به بررسی جداگانه دارد — به docs بخش «بدهی فنی» مراجعه شود).
 *
 *  لنگرهای مرجع (تأییدشده):
 *    ۲۰۲۱-۰۳-۲۱ → ۱۴۰۰/۰۱/۰۱  (نوروز ۱۴۰۰)
 *    ۲۰۲۴-۰۳-۱۹ → ۱۴۰۲/۱۲/۲۹  (آخرین روز اسفند سال غیرکبیسه)
 *    ۲۰۲۴-۰۳-۲۰ → ۱۴۰۳/۰۱/۰۱  (نوروز ۱۴۰۳)
 *    ۲۰۰۰-۰۱-۰۱ → ۱۳۷۸/۱۰/۱۱
 * ═══════════════════════════════════════════════════════════════════
 */

require_once dirname(__DIR__, 2) . '/includes/JalaliHelper.php';
require_once dirname(__DIR__, 2) . '/assets/jdf.php';

/** خروجی JalaliHelper را به رشته‌ی «YYYY/MM/DD» می‌کند. */
function jt_helper(string $gregorian): string
{
    [$gy, $gm, $gd] = array_map('intval', explode('-', $gregorian));
    [$jy, $jm, $jd] = JalaliHelper::gregorianToJalali($gy, $gm, $gd);
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}

/** خروجی jdf.php را (که بدون صفر ابتدایی است) هم‌قالب بالا می‌کند. */
function jt_jdf(string $gregorian): string
{
    [$gy, $gm, $gd] = array_map('intval', explode('-', $gregorian));
    $raw = gregorian_to_jalali($gy, $gm, $gd, '/');          // "1400/1/1"
    [$jy, $jm, $jd] = array_map('intval', explode('/', $raw));
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}

$SAMPLES = [
    '2021-03-21', '2000-01-01', '2024-03-19', '2024-03-20',
    '2026-01-01', '2026-09-07', '1995-06-15', '2010-11-30',
    '2019-12-31', '2022-08-01',
];

return [

    'name' => 'تبدیل تاریخ میلادی ↔ شمسی',

    'tests' => [

        // ═══════ لنگرهای تأییدشده ═══════

        'نوروز ۱۴۰۰ = ۲۰۲۱-۰۳-۲۱' => function (Assert $a) {
            $a->equals('1400/01/01', jt_helper('2021-03-21'), 'JalaliHelper نوروز ۱۴۰۰ را غلط داد');
        },

        'نوروز ۱۴۰۳ = ۲۰۲۴-۰۳-۲۰' => function (Assert $a) {
            $a->equals('1403/01/01', jt_helper('2024-03-20'));
        },

        'آخرین روز اسفند ۱۴۰۲ (غیرکبیسه) = ۲۰۲۴-۰۳-۱۹' => function (Assert $a) {
            $a->equals('1402/12/29', jt_helper('2024-03-19'));
        },

        '۲۰۰۰-۰۱-۰۱ = ۱۳۷۸/۱۰/۱۱' => function (Assert $a) {
            $a->equals('1378/10/11', jt_helper('2000-01-01'));
        },

        // ═══════ قفل هم‌ارزی دو پیاده‌سازی اصلی ═══════

        'JalaliHelper و jdf.php روی همه‌ی تاریخ‌های نمونه هم‌نظرند' => function (Assert $a) use ($SAMPLES) {
            foreach ($SAMPLES as $d) {
                $a->equals(jt_helper($d), jt_jdf($d), "اختلاف Helper و jdf روی $d");
            }
        },

        // ═══════ رفت‌وبرگشت ═══════

        'رفت‌وبرگشت: میلادی → شمسی → میلادی اصل را برمی‌گرداند' => function (Assert $a) use ($SAMPLES) {
            foreach ($SAMPLES as $d) {
                [$gy, $gm, $gd] = array_map('intval', explode('-', $d));
                $j = gregorian_to_jalali($gy, $gm, $gd, '/');
                [$jy, $jm, $jd] = array_map('intval', explode('/', $j));
                $back = jalali_to_gregorian($jy, $jm, $jd, '-');   // "YYYY-M-D"
                [$by, $bm, $bd] = array_map('intval', explode('-', $back));
                $a->equals(
                    sprintf('%04d-%02d-%02d', $gy, $gm, $gd),
                    sprintf('%04d-%02d-%02d', $by, $bm, $bd),
                    "رفت‌وبرگشت روی $d نشکند"
                );
            }
        },

        // ═══════ مرز ماه ═══════

        'روز بعد از ۱۴۰۲/۱۲/۲۹ می‌شود ۱۴۰۳/۰۱/۰۱' => function (Assert $a) {
            $a->equals('1402/12/29', jt_helper('2024-03-19'));
            $a->equals('1403/01/01', jt_helper('2024-03-20'));
        },
    ],
];
