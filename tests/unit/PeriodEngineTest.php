<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  تست واحد: موتور دوره‌ها (period-engine.php) — توابع خالص
 *  محل: /tests/unit/PeriodEngineTest.php
 * ───────────────────────────────────────────────────────────────────
 *  چرا این ماژول؟
 *    محاسبهٔ سررسید دوره‌های کارهای دوره‌ای (روزانه/هفتگی/ماهانه)
 *    پیچیده‌ترین منطق سامانه است. مهم‌ترین دام آن «سرریز تقویمی
 *    ماهانه» است: ۳۱ + ۱ ماه در PHP به ۳ خرداد می‌پرد و لنگر روز
 *    برای همیشه منحرف می‌ماند. pe_addMonthsClamped این را می‌بندد؛
 *    این تست‌ها آن رفتار را قفل نگه می‌دارند.
 *
 *  فقط توابع بدون دیتابیس تست می‌شوند:
 *    pe_addMonthsClamped, pe_monthsBetween, pe_periodDates, pe_periodOf
 *  (pe_state به PDO نیاز دارد و جای تست جداگانه دارد.)
 *
 *  تاریخ‌های مرجع (میلادی ۲۰۲۶):
 *    ۱۰ ژوئیه → جمعه (غیرکاری)   |   ۱۱ ژوئیه → شنبه   |   ۱۳ ژوئیه → دوشنبه
 *    فوریهٔ ۲۰۲۶ → ۲۸ روز (غیرکبیسه)   |   فوریهٔ ۲۰۲۴ → ۲۹ روز (کبیسه)
 * ═══════════════════════════════════════════════════════════════════
 */

require_once dirname(__DIR__, 2) . '/includes/period-engine.php';

return [

    'name' => 'موتور دوره‌ها (period-engine)',

    'tests' => [

        // ═══════════════════════════════════════════════
        //  pe_addMonthsClamped
        // ═══════════════════════════════════════════════

        'افزودن ماه — حالت عادی' => function (Assert $a) {
            $r = pe_addMonthsClamped(new DateTime('2026-01-15'), 1);
            $a->equals('2026-02-15', $r->format('Y-m-d'));
        },

        'افزودن ماه — روز ۳۱ به آخر ماه کوتاه‌تر محدود می‌شود (نه سرریز)' => function (Assert $a) {
            $r = pe_addMonthsClamped(new DateTime('2026-01-31'), 1);
            $a->equals('2026-02-28', $r->format('Y-m-d'), 'باید به آخر اسفند برود، نه ۳ خرداد');
        },

        'افزودن ماه — بعد از محدودشدن، ماه بعدی دوباره ۳۱ می‌شود (بدون انحراف)' => function (Assert $a) {
            $r = pe_addMonthsClamped(new DateTime('2026-01-31'), 2);
            $a->equals('2026-03-31', $r->format('Y-m-d'), 'لنگر روز نباید روی ۲۸ بماند');
        },

        'افزودن ماه — عبور از مرز سال' => function (Assert $a) {
            $r = pe_addMonthsClamped(new DateTime('2026-11-30'), 3);
            $a->equals('2027-02-28', $r->format('Y-m-d'));
        },

        'افزودن ماه — سال کبیسه ۲۹ فوریه' => function (Assert $a) {
            $r = pe_addMonthsClamped(new DateTime('2024-01-31'), 1);
            $a->equals('2024-02-29', $r->format('Y-m-d'));
        },

        'افزودن ماه — صفر ماه = همان تاریخ' => function (Assert $a) {
            $r = pe_addMonthsClamped(new DateTime('2026-06-07'), 0);
            $a->equals('2026-06-07', $r->format('Y-m-d'));
        },

        // ═══════════════════════════════════════════════
        //  pe_monthsBetween
        // ═══════════════════════════════════════════════

        'فاصلهٔ ماه — همان ماه = ۰' => function (Assert $a) {
            $a->equals(0, pe_monthsBetween(new DateTime('2026-03-01'), new DateTime('2026-03-28')));
        },

        'فاصلهٔ ماه — روز نادیده گرفته می‌شود' => function (Assert $a) {
            $a->equals(3, pe_monthsBetween(new DateTime('2026-01-15'), new DateTime('2026-04-01')));
        },

        'فاصلهٔ ماه — عبور از سال' => function (Assert $a) {
            $a->equals(3, pe_monthsBetween(new DateTime('2025-11-01'), new DateTime('2026-02-01')));
        },

        // ═══════════════════════════════════════════════
        //  pe_periodDates — روزانه
        // ═══════════════════════════════════════════════

        'روزانه — جمعه از دوره‌ها حذف می‌شود' => function (Assert $a) {
            $dates = pe_periodDates('daily', new DateTime('2026-07-11'), new DateTime('2026-07-18'), []);
            $a->equals(
                ['2026-07-11', '2026-07-12', '2026-07-13', '2026-07-14', '2026-07-15', '2026-07-16', '2026-07-18'],
                $dates,
                '۱۷ ژوئیه (جمعه) نباید در لیست باشد'
            );
        },

        'روزانه — اگر روز شروع جمعه باشد، از اولین روز کاری شروع می‌شود' => function (Assert $a) {
            $dates = pe_periodDates('daily', new DateTime('2026-07-10'), new DateTime('2026-07-13'), []);
            $a->equals(['2026-07-11', '2026-07-12', '2026-07-13'], $dates);
        },

        'روزانه — تعطیلی رسمی وسط بازه حذف می‌شود' => function (Assert $a) {
            $holidays = ['2026-07-13' => true];
            $dates = pe_periodDates('daily', new DateTime('2026-07-11'), new DateTime('2026-07-14'), $holidays);
            $a->equals(['2026-07-11', '2026-07-12', '2026-07-14'], $dates);
        },

        'روزانه — endDate بازه را زودتر می‌بندد' => function (Assert $a) {
            $dates = pe_periodDates('daily', new DateTime('2026-07-11'), new DateTime('2026-07-31'), [], '2026-07-14');
            $a->equals(['2026-07-11', '2026-07-12', '2026-07-13', '2026-07-14'], $dates);
        },

        // ═══════════════════════════════════════════════
        //  pe_periodDates — هفتگی
        // ═══════════════════════════════════════════════

        'هفتگی — هر ۷ روز، صرف‌نظر از جمعه' => function (Assert $a) {
            $dates = pe_periodDates('weekly', new DateTime('2026-07-11'), new DateTime('2026-08-01'), []);
            $a->equals(['2026-07-11', '2026-07-18', '2026-07-25', '2026-08-01'], $dates);
        },

        'هفتگی — endDate بازه را می‌بندد' => function (Assert $a) {
            $dates = pe_periodDates('weekly', new DateTime('2026-07-11'), new DateTime('2026-09-01'), [], '2026-07-25');
            $a->equals(['2026-07-11', '2026-07-18', '2026-07-25'], $dates);
        },

        // ═══════════════════════════════════════════════
        //  pe_periodDates — ماهانه
        // ═══════════════════════════════════════════════

        'ماهانه — لنگر روز ثابت' => function (Assert $a) {
            $dates = pe_periodDates('monthly', new DateTime('2026-01-15'), new DateTime('2026-04-30'), []);
            $a->equals(['2026-01-15', '2026-02-15', '2026-03-15', '2026-04-15'], $dates);
        },

        'ماهانه — روز ۳۱ در ماه کوتاه محدود می‌شود ولی ماه بعد دوباره ۳۱' => function (Assert $a) {
            $dates = pe_periodDates('monthly', new DateTime('2026-01-31'), new DateTime('2026-04-30'), []);
            $a->equals(
                ['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'],
                $dates,
                'مارس باید ۳۱ باشد نه ۲۸ — اثبات نبود انحراف'
            );
        },

        'ماهانه — دوره‌های بعد از upto نمی‌آیند' => function (Assert $a) {
            $dates = pe_periodDates('monthly', new DateTime('2026-01-15'), new DateTime('2026-03-14'), []);
            $a->equals(['2026-01-15', '2026-02-15'], $dates);
        },

        // ═══════════════════════════════════════════════
        //  pe_periodOf
        // ═══════════════════════════════════════════════

        'تعلق دوره — آخرین سررسید ≤ تاریخ' => function (Assert $a) {
            $periods = ['2026-07-01', '2026-07-02', '2026-07-05', '2026-07-06'];
            $a->equals('2026-07-02', pe_periodOf($periods, '2026-07-04'));
        },

        'تعلق دوره — تاریخ دقیقا روی یک سررسید' => function (Assert $a) {
            $periods = ['2026-07-01', '2026-07-02', '2026-07-05', '2026-07-06'];
            $a->equals('2026-07-05', pe_periodOf($periods, '2026-07-05'));
        },

        'تعلق دوره — پیش از اولین دوره → null' => function (Assert $a) {
            $periods = ['2026-07-01', '2026-07-02', '2026-07-05', '2026-07-06'];
            $a->equals(null, pe_periodOf($periods, '2026-06-30'));
        },

        'تعلق دوره — بعد از همهٔ دوره‌ها → آخرین دوره' => function (Assert $a) {
            $periods = ['2026-07-01', '2026-07-02', '2026-07-05', '2026-07-06'];
            $a->equals('2026-07-06', pe_periodOf($periods, '2026-07-20'));
        },

        // ═══════════════════════════════════════════════
        //  روزهای خاص هفته (weekdays) — ۰=یکشنبه ۱=دوشنبه ۴=پنجشنبه
        //  ۲۰۲۶-۰۷-۱۵ چهارشنبه | ۱۶ پنجشنبه | ۱۹ یکشنبه | ۲۰ دوشنبه
        // ═══════════════════════════════════════════════

        'روزهای خاص هفته — شروع چهارشنبه، اولین دوره پنجشنبه' => function (Assert $a) {
            $dates = pe_periodDates('weekdays', new DateTime('2026-07-15'), new DateTime('2026-07-27'), [], null, ['weekdays' => [0, 1, 4]]);
            $a->equals(['2026-07-16', '2026-07-19', '2026-07-20', '2026-07-23', '2026-07-26', '2026-07-27'], $dates);
        },

        'روزهای خاص هفته — روز انتخابیِ تعطیل دوره ندارد' => function (Assert $a) {
            $dates = pe_periodDates('weekdays', new DateTime('2026-07-15'), new DateTime('2026-07-23'), ['2026-07-19' => true], null, ['weekdays' => [0, 1, 4]]);
            $a->equals(['2026-07-16', '2026-07-20', '2026-07-23'], $dates);
        },

        'روزهای خاص هفته — endDate و بدون روز انتخابی' => function (Assert $a) {
            $a->equals(['2026-07-16', '2026-07-19'], pe_periodDates('weekdays', new DateTime('2026-07-15'), new DateTime('2026-07-31'), [], '2026-07-19', ['weekdays' => [0, 1, 4]]));
            $a->equals([], pe_periodDates('weekdays', new DateTime('2026-07-15'), new DateTime('2026-07-31'), [], null, ['weekdays' => []]));
        },

        'روزهای خاص هفته — دورهٔ بعد از تعطیلی رد می‌شود' => function (Assert $a) {
            $o = ['weekdays' => [0, 1, 4]];
            $a->equals('2026-07-19', pe_nextPeriodAfter('weekdays', '2026-07-16', [], null, $o));
            $a->equals('2026-07-20', pe_nextPeriodAfter('weekdays', '2026-07-16', ['2026-07-19' => true], null, $o));
        },

        'روزهای خاص هفته — جمعه و مقدار نامعتبر کنار گذاشته می‌شوند' => function (Assert $a) {
            $a->equals([0, 1, 4], pe_parseWeekdays('4,0,5,1,1,9,x'));
        },

        // ═══════════════════════════════════════════════
        //  هر N روز (interval)
        // ═══════════════════════════════════════════════

        'هر ۹ روز — از تاریخ شروع، روز تقویمی' => function (Assert $a) {
            $dates = pe_periodDates('interval', new DateTime('2026-07-11'), new DateTime('2026-08-10'), [], null, ['interval_days' => 9]);
            $a->equals(['2026-07-11', '2026-07-20', '2026-07-29', '2026-08-07'], $dates);
        },

        'هر N روز — بیرون از ۲..۳۶۵ هیچ دوره‌ای نمی‌سازد' => function (Assert $a) {
            $a->equals([], pe_periodDates('interval', new DateTime('2026-07-11'), new DateTime('2026-08-10'), [], null, ['interval_days' => 1]));
            $a->equals([], pe_periodDates('interval', new DateTime('2026-07-11'), new DateTime('2026-08-10'), [], null, ['interval_days' => 400]));
            $a->equals('2026-07-29', pe_nextPeriodAfter('interval', '2026-07-20', [], null, ['interval_days' => 9]));
        },

        // ═══════════════════════════════════════════════
        //  pe_state با دو الگوی تازه (تکمیل‌ها از نقشهٔ ازپیش‌واکشی‌شده — بدون دیتابیس)
        // ═══════════════════════════════════════════════

        'وضعیت weekdays — پیش از اولین روز انتخابی: موعد = پنجشنبه، تکمیل ممنوع' => function (Assert $a) {
            $task = ['id' => 1, 'task_type' => 'continuous', 'start_date' => '2026-07-15', 'period_type' => 'weekdays', 'period_weekdays' => '0,1,4'];
            $st = pe_state(new PDO('sqlite::memory:'), $task, [], '2026-07-15', [1 => []]);
            $a->equals('2026-07-16', $st['next_due_date']);
            $a->equals(false, $st['can_complete']);
        },

        'وضعیت weekdays — یکشنبهٔ جامانده معوقه، دورهٔ دوشنبه انجام‌شده' => function (Assert $a) {
            $task = ['id' => 1, 'task_type' => 'continuous', 'start_date' => '2026-07-15', 'period_type' => 'weekdays', 'period_weekdays' => '0,1,4'];
            $st = pe_state(new PDO('sqlite::memory:'), $task, [], '2026-07-21', [1 => ['2026-07-16', '2026-07-20']]);
            $a->equals('2026-07-20', $st['current_period_date']);
            $a->equals(1, $st['overdue_periods']);
            $a->equals(true, $st['is_today_done']);
            $a->equals('2026-07-23', $st['next_due_date']);
            $a->equals(false, $st['can_complete']);
        },

        'وضعیت interval — تکمیل داخل بازهٔ ۹ روزه دوره را می‌بندد' => function (Assert $a) {
            $task = ['id' => 2, 'task_type' => 'continuous', 'start_date' => '2026-07-11', 'period_type' => 'interval', 'period_interval_days' => 9];
            $st = pe_state(new PDO('sqlite::memory:'), $task, [], '2026-07-25', [2 => ['2026-07-14']]);
            $a->equals('2026-07-20', $st['current_period_date']);
            $a->equals(0, $st['overdue_periods']);
            $a->equals(true, $st['can_complete']);
        },

        // ═══════════════════════════════════════════════
        //  اعتبارسنجی ورودی و برچسب
        // ═══════════════════════════════════════════════

        'اعتبارسنجی الگو — خطاها و یکدست‌سازی' => function (Assert $a) {
            $a->equals(null, pe_normalizePeriodInput(['period_type' => 'weekdays', 'period_weekdays' => [4, 0, 1]])['error']);
            $a->equals('0,1,4', pe_normalizePeriodInput(['period_type' => 'weekdays', 'period_weekdays' => '4,0,1,5'])['period_weekdays']);
            $a->equals(true, pe_normalizePeriodInput(['period_type' => 'weekdays', 'period_weekdays' => '5'])['error'] !== null);
            $a->equals(true, pe_normalizePeriodInput(['period_type' => 'interval', 'period_interval_days' => 1])['error'] !== null);
            $r = pe_normalizePeriodInput(['period_type' => 'daily', 'period_weekdays' => '0,1', 'period_interval_days' => 9]);
            $a->equals([null, null], [$r['period_weekdays'], $r['period_interval_days']]);
            $a->equals(true, pe_normalizePeriodInput(['period_type' => 'hourly'])['error'] !== null);
        },

        'برچسب الگو — ترتیب هفتهٔ ایرانی' => function (Assert $a) {
            $a->equals('یکشنبه، دوشنبه و پنجشنبه', pe_periodLabel(['period_type' => 'weekdays', 'period_weekdays' => '4,1,0']));
            $a->equals('شنبه و یکشنبه', pe_periodLabel(['period_type' => 'weekdays', 'period_weekdays' => '0,6']));
            $a->equals('هر 9 روز', pe_periodLabel(['period_type' => 'interval', 'period_interval_days' => 9]));
        },
    ],
];
