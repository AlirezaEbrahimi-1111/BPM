<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: انتقالِ «تعلقِ ماهانهٔ سهمیهٔ مرخصی» از ماهِ میلادی به ماهِ شمسی
 *  تاریخ: ۱۴۰۵/۰۶/۰۵
 * ───────────────────────────────────────────────────────────────────
 *  مشکل:
 *    ensureMonthlyLeaveAccrual() ماهِ جاری را با date('Y-m') (میلادی) می‌گرفت
 *    و در ستونِ period_ym ذخیره می‌کرد. در نتیجه سهمیهٔ ۲ روز، اولِ هر ماهِ
 *    میلادی اضافه می‌شد نه اولِ ماهِ شمسی؛ و ماهِ شمسیِ جاری (شهریور ۱۴۰۵)
 *    برایِ هیچ‌کس تعلق نگرفته بود (چون هنوز ماهِ میلادیِ بعدی شروع نشده).
 *
 *  این مهاجرت:
 *    ۱) period_ymِ همهٔ ردیف‌هایِ monthly_accrual را از «ماهِ میلادیِ created_at»
 *       به «ماهِ شمسیِ همان تاریخ» بازنویسی می‌کند (amount دست‌نخورده می‌ماند).
 *    ۲) برایِ هر کاربری که حداقل یک تعلقِ ماهانه دارد، ماه‌هایِ شمسیِ جا‌افتاده
 *       تا ماهِ شمسیِ جاری را می‌سازد:
 *          amount = round(۲ × daily_work_hours خودِ فرد × ۶۰)  [دقیقه]
 *
 *  دامنه: همهٔ کاربران، همهٔ سازمان‌ها. کاملاً per-user است و هیچ کوئریِ
 *  بین‌سازمانی ندارد؛ سهمیهٔ سازمان‌ها با هم قاطی نمی‌شود.
 *
 *  همراهِ این مهاجرت، منطقِ ensureMonthlyLeaveAccrual() هم به ماهِ شمسی
 *  تغییر کرده — این دو باید با هم دیپلوی شوند.
 * ═══════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../includes/JalaliHelper.php';

/** یادداشتِ ثابتِ ردیف‌هایِ جبرانی — برایِ شناساییِ آن‌ها در down() */
if (!defined('LBT_JALALI_BACKFILL_NOTE')) {
    define('LBT_JALALI_BACKFILL_NOTE', 'تعلقِ خودکارِ ماهانه (جبرانیِ انتقال به ماهِ شمسی)');
}

if (!function_exists('lbt_jalali_key')) {
    /** کلیدِ ماهِ شمسیِ یک تاریخِ میلادی (YYYY-MM-DD) → "YYYY-MM" */
    function lbt_jalali_key(string $gregorianDate): string {
        $ts = strtotime($gregorianDate);
        list($jy, $jm) = JalaliHelper::gregorianToJalali(
            (int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts)
        );
        return sprintf('%04d-%02d', $jy, $jm);
    }
}

if (!function_exists('lbt_next_jalali_key')) {
    /** ماهِ شمسیِ بعدی برایِ کلیدِ "YYYY-MM" */
    function lbt_next_jalali_key(string $ym): string {
        list($y, $m) = array_map('intval', explode('-', $ym));
        if (++$m > 12) { $m = 1; $y++; }
        return sprintf('%04d-%02d', $y, $m);
    }
}

return [

    'description' => 'Switch monthly leave-quota accrual period from Gregorian month to Jalali month; backfill missing Jalali months for all users',

    'up' => function (PDO $db) {

        $ownsTx = !$db->inTransaction();
        if ($ownsTx) $db->beginTransaction();

        try {
            // ── ۱) بازنویسیِ period_ym از میلادی به شمسی ──────────────────
            //     فقط ردیف‌هایی که هنوز کلیدِ میلادی دارند (سالِ ۲۰xx) —
            //     تا اجرا دوباره (در صورتِ نیاز) به مقادیرِ شمسی دست نزند.
            $rows = $db->query("
                SELECT id, DATE(created_at) AS d
                FROM leave_balance_transactions
                WHERE type = 'monthly_accrual' AND period_ym LIKE '20%'
            ")->fetchAll(PDO::FETCH_ASSOC);

            $upd = $db->prepare("UPDATE leave_balance_transactions SET period_ym = ? WHERE id = ?");
            foreach ($rows as $r) {
                $upd->execute([lbt_jalali_key($r['d']), (int) $r['id']]);
            }

            // ── ۲) جبرانِ ماه‌هایِ شمسیِ جا‌افتاده برایِ هر کاربر ─────────────
            $currentKey = lbt_jalali_key(date('Y-m-d'));

            $users = $db->query("
                SELECT lbt.user_id AS user_id,
                       MAX(lbt.period_ym) AS last_period,
                       COALESCE(u.daily_work_hours, 8) AS dwh
                FROM leave_balance_transactions lbt
                JOIN users u ON u.id = lbt.user_id
                WHERE lbt.type = 'monthly_accrual'
                GROUP BY lbt.user_id, u.daily_work_hours
            ")->fetchAll(PDO::FETCH_ASSOC);

            $exists = $db->prepare("
                SELECT 1 FROM leave_balance_transactions
                WHERE user_id = ? AND type = 'monthly_accrual' AND period_ym = ?
            ");
            $ins = $db->prepare("
                INSERT INTO leave_balance_transactions (user_id, type, amount, period_ym, note)
                VALUES (?, 'monthly_accrual', ?, ?, ?)
            ");

            $insertedCount = 0;
            foreach ($users as $u) {
                $amount = (int) round(2 * (float) $u['dwh'] * 60);
                $cursor = (string) $u['last_period'];
                $guard  = 0;
                while ($cursor < $currentKey && ++$guard < 240) {
                    $cursor = lbt_next_jalali_key($cursor);
                    $exists->execute([(int) $u['user_id'], $cursor]);
                    if ($exists->fetchColumn()) continue; // قبلاً هست — رد شو
                    $ins->execute([(int) $u['user_id'], $amount, $cursor, LBT_JALALI_BACKFILL_NOTE]);
                    $insertedCount++;
                }
            }

            if ($ownsTx) $db->commit();

        } catch (Throwable $e) {
            if ($ownsTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    },

    /**
     * بازگرداندن:
     *   - ردیف‌هایِ جبرانی که در up ساخته شدند حذف می‌شوند (با یادداشتِ ثابت).
     *   - period_ymِ باقی‌مانده به ماهِ میلادیِ created_at برمی‌گردد — که همان
     *     مقدارِ اولیه است، چون در کدِ قدیمی period_ym = date('Y-m') در همان
     *     لحظهٔ درج (= ماهِ created_at) ثبت می‌شد.
     */
    'down' => function (PDO $db) {

        $ownsTx = !$db->inTransaction();
        if ($ownsTx) $db->beginTransaction();

        try {
            $db->prepare("
                DELETE FROM leave_balance_transactions
                WHERE type = 'monthly_accrual' AND note = ?
            ")->execute([LBT_JALALI_BACKFILL_NOTE]);

            $db->exec("
                UPDATE leave_balance_transactions
                SET period_ym = DATE_FORMAT(created_at, '%Y-%m')
                WHERE type = 'monthly_accrual'
            ");

            if ($ownsTx) $db->commit();

        } catch (Throwable $e) {
            if ($ownsTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    },

];
