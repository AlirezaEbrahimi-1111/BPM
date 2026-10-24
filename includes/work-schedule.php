<?php
/**
 * سابقهٔ ساعت کاری و حقوق هر کاربر (درخواست ۱۴۰۵/۰۸/۰۲).
 * ------------------------------------------------------------------
 * مشکل: ساعت شیفت، «ساعت کار روزانه» و حقوق ماهانه فقط یک نسخه در جدول users داشتند
 * و محاسبهٔ کسری/حقوق برای همهٔ روزهای ماه همان نسخهٔ فعلی را می‌خواند؛ پس اگر سرپرست
 * روز ۲ ماه ساعت کسی را عوض می‌کرد، ماه قبل هم با ساعت جدید دوباره حساب می‌شد.
 *
 * حالا هر تغییر یک ردیف در user_work_schedules است با «تاریخ شروع اعتبار» (همان روز
 * تغییر، به ساعت دیتابیس). محاسبه‌های روزهای گذشته برای هر روز نسخهٔ معتبرِ همان روز
 * را می‌گیرند. users همچنان مقدار «فعلی» را دارد (ثبت ورود/خروج و وضعیت امروز از همان
 * می‌خوانند).
 *
 * کاربری که هیچ ردیف سابقه ندارد = همیشه همان مقدار users (رفتار قبلی). اولین تغییر،
 * مقدار قبلی را با تاریخ 2000-01-01 به‌عنوان «نسخهٔ اولیه» ثبت می‌کند.
 *
 *   ws_recordChange($db, $uid, $oldUsersRow, $newFields, $byUserId)   // در update-user.php
 *   $hist = ws_loadHistory($db, [$uid, ...], $toDate);                 // یک کوئری برای بازه
 *   $s = ws_forDate($hist[$uid] ?? [], '2026-10-20', $usersRow);       // نسخهٔ معتبر یک روز
 */

if (!function_exists('ws_normalize')) {

    /** فیلدهای سابقه‌دار — همان نام ستون‌های users */
    function ws_fields(): array
    {
        return ['shift_count', 'shift_1_start', 'shift_1_end', 'shift_2_start', 'shift_2_end',
            'daily_work_hours', 'monthly_salary'];
    }

    /** یکسان‌سازی برای مقایسه/ذخیره: ساعت‌ها HH:MM:SS یا null، اعداد float/int */
    function ws_normalize(array $r): array
    {
        $t = function ($v) {
            $v = trim((string) ($v ?? ''));
            if ($v === '') return null;
            if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $v, $m)) {
                return sprintf('%02d:%02d:%02d', $m[1], $m[2], $m[3] ?? 0);
            }
            return null;
        };
        return [
            'shift_count'      => max(1, (int) ($r['shift_count'] ?? 1)),
            'shift_1_start'    => $t($r['shift_1_start'] ?? null),
            'shift_1_end'      => $t($r['shift_1_end'] ?? null),
            'shift_2_start'    => $t($r['shift_2_start'] ?? null),
            'shift_2_end'      => $t($r['shift_2_end'] ?? null),
            'daily_work_hours' => round((float) ($r['daily_work_hours'] ?? 0), 2),
            'monthly_salary'   => round((float) ($r['monthly_salary'] ?? 0), 2),
        ];
    }

    function ws_same(array $a, array $b): bool
    {
        return ws_normalize($a) === ws_normalize($b);
    }

    /**
     * اگر ساعت کاری/حقوق عوض شده، نسخهٔ تازه را از «امروز» (CURDATE دیتابیس) ثبت می‌کند.
     * چند تغییر در یک روز = آخرین‌شان (همان ردیف امروز به‌روز می‌شود).
     * داخل تراکنش فراخواننده اجرا می‌شود.
     */
    function ws_recordChange(PDO $db, int $userId, array $old, array $new, int $byUserId): void
    {
        $o = ws_normalize($old);
        $n = ws_normalize(array_merge($old, $new));
        if ($o === $n) return;

        $cols = ws_fields();
        $ins = $db->prepare('INSERT INTO user_work_schedules (user_id, effective_from, ' . implode(', ', $cols) . ', created_by)
            VALUES (?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ', ?)
            ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "$c = VALUES($c)", $cols)) . ', created_by = VALUES(created_by), created_at = NOW()');

        $has = $db->prepare('SELECT COUNT(*) FROM user_work_schedules WHERE user_id = ?');
        $has->execute([$userId]);
        if ((int) $has->fetchColumn() === 0) {
            // نسخهٔ اولیه = مقدار قبل از این تغییر، برای همهٔ روزهای گذشته
            $ins->execute(array_merge([$userId, '2000-01-01'], array_values($o), [$byUserId]));
        }
        $today = $db->query('SELECT CURDATE()')->fetchColumn();
        $ins->execute(array_merge([$userId, $today], array_values($n), [$byUserId]));
    }

    /**
     * سابقهٔ کاربران تا $toDate (Y-m-d)، مرتب صعودی بر اساس effective_from.
     * @return array [user_id => [row, ...]]
     */
    function ws_loadHistory(PDO $db, array $userIds, string $toDate): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) return [];
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $st = $db->prepare("SELECT user_id, effective_from, " . implode(', ', ws_fields()) . "
            FROM user_work_schedules WHERE user_id IN ($ph) AND effective_from <= ?
            ORDER BY user_id, effective_from");
        $st->execute(array_merge($userIds, [$toDate]));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['user_id']][] = $r;
        }
        return $out;
    }

    /**
     * نسخهٔ معتبر روز $date: آخرین ردیف با effective_from <= $date؛ اگر سابقه‌ای نیست،
     * همان $fallback (ردیف users). خروجی کلیدهای ستون‌های users را دارد (ساعت HH:MM:SS).
     */
    /** تغییرهای داخل بازه (effective_from بین from و to) — برای نمایش «ساعت از فلان روز عوض شد» */
    function ws_changesInRange(array $rows, string $from, string $to): array
    {
        $out = [];
        foreach ($rows as $r) {
            if ($r['effective_from'] > $from && $r['effective_from'] <= $to) {
                $jf = '';
                if (function_exists('sc_gregorianToJalali')) {
                    [$gy, $gm, $gd] = array_map('intval', explode('-', $r['effective_from']));
                    [$jy, $jm, $jd] = sc_gregorianToJalali($gy, $gm, $gd);
                    $jf = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
                }
                $out[] = ['effective_from' => $r['effective_from'], 'jalali_from' => $jf, 'shift_label' => sc_shiftLabel(ws_normalize($r)),
                    'daily_work_hours' => (float) $r['daily_work_hours'], 'monthly_salary' => (float) $r['monthly_salary']];
            }
        }
        return $out;
    }

    /** «۰۸:۰۰–۱۷:۳۰» یا «۰۹:۰۰–۱۴:۰۰ و ۱۷:۰۰–۲۱:۰۰» (رقم لاتین؛ صفحه فارسی‌اش می‌کند) */
    if (!function_exists('sc_shiftLabel')) {
        function sc_shiftLabel(array $s): string
        {
            $h = fn($v) => $v ? substr((string) $v, 0, 5) : '';
            $l = $h($s['shift_1_start'] ?? null) . '–' . $h($s['shift_1_end'] ?? null);
            if ((int) ($s['shift_count'] ?? 1) >= 2 && !empty($s['shift_2_start']) && !empty($s['shift_2_end'])) {
                $l .= ' و ' . $h($s['shift_2_start']) . '–' . $h($s['shift_2_end']);
            }
            return $l;
        }
    }

    function ws_forDate(array $rows, string $date, array $fallback): array
    {
        $pick = null;
        foreach ($rows as $r) {
            if ($r['effective_from'] <= $date) $pick = $r;
            else break;
        }
        return array_merge($fallback, ws_normalize($pick ?? $fallback));
    }
}
