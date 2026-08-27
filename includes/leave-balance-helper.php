<?php
/**
 * leave-balance-helper.php
 * محل: /includes/leave-balance-helper.php
 *
 * سیستمِ سهمیهٔ ماهانهٔ «مرخصی + پاس» — یک استخرِ مشترک، بر‌حسبِ دقیقه:
 *   - هر ماه معادلِ ۲ روزِ کاریِ خودِ فرد (بر اساسِ daily_work_hours همون
 *     کاربر) به‌صورتِ خودکار به موجودی اضافه می‌شه (تعلق)
 *   - هم مرخصی و هم پاس از همین یک استخر کم می‌شن
 *   - مدیر می‌تونه سهمیهٔ تشویقی (بونس) به یک کارمندِ خاص اضافه کنه
 *   - سهمیهٔ استفاده‌نشده به ماهِ بعد منتقل می‌شه (چون هیچ‌چیزی reset نمی‌شه)
 *   - ثبتِ درخواست، موجودی رو کم می‌کنه (اگه کافی نباشه، رد می‌شه)
 *   - ریستِ سالانه: دستی (طبقِ خواستِ کارفرما) — نه در این فایل
 */

require_once __DIR__ . '/JalaliHelper.php';

const LEAVE_MONTHLY_ACCRUAL_WORK_DAYS = 2.0;

/**
 * ساعتِ کاریِ روزانهٔ یک کاربر (برایِ تبدیلِ «روز» به «دقیقه»)
 */
function getUserDailyWorkMinutes(PDO $db, int $userId): int {
    $stmt = $db->prepare("SELECT daily_work_hours FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $hours = (float) ($stmt->fetchColumn() ?: 8); // ۸ ساعت، اگر تعریف نشده بود
    return (int) round($hours * 60);
}

/**
 * موجودیِ فعلیِ سهمیه به دقیقه (مجموعِ همهٔ تراکنش‌ها)
 */
function getLeaveBalance(PDO $db, int $userId): int {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM leave_balance_transactions WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * رنگِ وضعیتِ موجودی، بر اساسِ نسبت به سهمیهٔ پایهٔ یک‌ماهه (خودِ فرد):
 *   green  → موجودی ≥ یک ماهِ کامل
 *   yellow → بینِ ۲۰٪ تا ۱۰۰٪ یک ماه
 *   red    → زیرِ ۲۰٪ یا منفی
 */
function getLeaveBalanceColor(int $balanceMinutes, int $dailyWorkMinutes): string {
    $monthlyBase = LEAVE_MONTHLY_ACCRUAL_WORK_DAYS * $dailyWorkMinutes;
    if ($monthlyBase <= 0) return 'yellow';
    if ($balanceMinutes >= $monthlyBase) return 'green';
    if ($balanceMinutes >= $monthlyBase * 0.2) return 'yellow';
    return 'red';
}

/**
 * نمایشِ خوانا: تعدادِ دقیقه → "H:MM" با اعدادِ فارسی (منفی هم پشتیبانی می‌شه)
 */
function formatMinutesHM(int $minutes): string {
    $sign = $minutes < 0 ? '-' : '';
    $abs = abs($minutes);
    $h = intdiv($abs, 60);
    $m = $abs % 60;
    $str = $sign . $h . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    return strtr($str, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

/**
 * کلیدِ ماهِ شمسیِ یک تاریخِ میلادی (YYYY-MM-DD) به‌شکلِ "YYYY-MM" — مثل "1405-06".
 * مبنایِ تعلقِ ماهانه، ماهِ شمسیه نه میلادی.
 */
function jalaliPeriodKey(string $gregorianDate): string {
    $ts = strtotime($gregorianDate);
    list($jy, $jm) = JalaliHelper::gregorianToJalali(
        (int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts)
    );
    return sprintf('%04d-%02d', $jy, $jm);
}

/**
 * ماهِ شمسیِ بعدی برایِ کلیدِ "YYYY-MM" (بعد از اسفند می‌ره فروردینِ سالِ بعد).
 */
function nextJalaliPeriod(string $ym): string {
    list($y, $m) = array_map('intval', explode('-', $ym));
    if (++$m > 12) { $m = 1; $y++; }
    return sprintf('%04d-%02d', $y, $m);
}

/**
 * مطمئن می‌شه تعلقِ ماهانه تا همین ماهِ شمسیِ جاری برایِ این کاربر ثبت شده.
 *
 * توجه: برایِ کاربری که تا حالا هیچ تعلقی نداشته (یعنی این سیستم تازه براش
 * فعال می‌شه)، فقط از همین ماهِ شمسیِ جاری شروع می‌شه — نه بازگشتی. اگه قبلاً
 * تعلق داشته ولی چند ماهِ شمسی رد شده، از همون ماهِ بعدِ آخرین تعلق جبران می‌شه.
 */
function ensureMonthlyLeaveAccrual(PDO $db, int $userId): void {
    $stmt = $db->prepare("
        SELECT MAX(period_ym) FROM leave_balance_transactions
        WHERE user_id = ? AND type = 'monthly_accrual'
    ");
    $stmt->execute([$userId]);
    $lastPeriod = $stmt->fetchColumn();

    $currentYm = jalaliPeriodKey(date('Y-m-d')); // ماهِ شمسیِ جاری
    $dailyMinutes = getUserDailyWorkMinutes($db, $userId);
    $accrualMinutes = (int) round(LEAVE_MONTHLY_ACCRUAL_WORK_DAYS * $dailyMinutes);

    if (!$lastPeriod) {
        insertLeaveAccrual($db, $userId, $currentYm, $accrualMinutes);
        return;
    }

    if ($lastPeriod >= $currentYm) {
        return; // تا همین ماهِ شمسی به‌روزه
    }

    // جبرانِ ماه‌هایِ شمسیِ جا‌افتاده: از ماهِ بعدِ آخرین تعلق تا ماهِ شمسیِ جاری
    $cursor = $lastPeriod;
    $guard  = 0;
    while ($cursor < $currentYm && ++$guard < 240) {
        $cursor = nextJalaliPeriod($cursor);
        insertLeaveAccrual($db, $userId, $cursor, $accrualMinutes);
    }
}

function insertLeaveAccrual(PDO $db, int $userId, string $periodYm, int $minutes): void {
    // جلوگیری از تعلقِ تکراری برایِ یک ماه (در صورتِ درخواستِ هم‌زمان)
    $stmt = $db->prepare("
        SELECT id FROM leave_balance_transactions
        WHERE user_id = ? AND type = 'monthly_accrual' AND period_ym = ?
    ");
    $stmt->execute([$userId, $periodYm]);
    if ($stmt->fetch()) return;

    $stmt = $db->prepare("
        INSERT INTO leave_balance_transactions (user_id, type, amount, period_ym, note)
        VALUES (?, 'monthly_accrual', ?, ?, 'تعلقِ خودکارِ ماهانه (معادلِ ۲ روزِ کاری)')
    ");
    $stmt->execute([$userId, $minutes, $periodYm]);
}

/**
 * محاسبهٔ دقیقهٔ مصرفی برایِ یک درخواستِ مرخصی/پاس:
 *   - اگه تاریخِ شروع و پایان یکی باشه (تک‌روزه/پاس): دقیقاً از رویِ اختلافِ ساعت
 *   - اگه چندروزه باشه: هر روز معادلِ یک روزِ کاملِ کاریِ فرد حساب می‌شه
 *     (بدونِ محاسبهٔ جزئیِ ساعتِ شروع/پایانِ روزِ اول/آخر)
 */
function computeLeaveRequestMinutes(string $startDate, string $startTime, string $endDate, string $endTime, int $dailyWorkMinutes): int {
    if ($startDate === $endDate) {
        $startMin = (int) substr($startTime, 0, 2) * 60 + (int) substr($startTime, 3, 2);
        $endMin = (int) substr($endTime, 0, 2) * 60 + (int) substr($endTime, 3, 2);
        return max(0, $endMin - $startMin);
    }
    $days = (int) ((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;
    return max(0, $days) * $dailyWorkMinutes;
}

/**
 * ثبتِ کسر بابتِ یک درخواستِ مرخصی/پاس
 */
function deductLeaveBalance(PDO $db, int $userId, int $minutes, string $requestType, int $requestId, string $note): void {
    $type = $requestType === 'pass' ? 'pass_deduction' : 'leave_deduction';
    $stmt = $db->prepare("
        INSERT INTO leave_balance_transactions (user_id, type, amount, related_request_id, related_request_type, note)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $type, -$minutes, $requestId, $requestType, $note]);
}

/**
 * بازگرداندنِ (یا اصلاحِ) کسرِ ثبت‌شده برایِ یک درخواستِ خاص — برایِ ردشدن/حذف/ویرایش
 * @return int|null مقدارِ کسرِ قبلی (منفی) اگر پیدا شد، وگرنه null
 */
function findLeaveDeduction(PDO $db, string $requestType, int $requestId): ?int {
    $type = $requestType === 'pass' ? 'pass_deduction' : 'leave_deduction';
    $stmt = $db->prepare("
        SELECT amount FROM leave_balance_transactions
        WHERE related_request_id = ? AND related_request_type = ? AND type = ?
    ");
    $stmt->execute([$requestId, $requestType, $type]);
    $val = $stmt->fetchColumn();
    return $val === false ? null : (int) $val;
}

/**
 * تاریخچهٔ تراکنش‌هایِ یک کاربر (جدیدترین اول)
 */
function getLeaveBalanceHistory(PDO $db, int $userId, int $limit = 50): array {
    $stmt = $db->prepare("
        SELECT lbt.*, CONCAT(u.first_name, ' ', u.last_name) AS granted_by_name
        FROM leave_balance_transactions lbt
        LEFT JOIN users u ON lbt.granted_by = u.id
        WHERE lbt.user_id = ?
        ORDER BY lbt.created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
