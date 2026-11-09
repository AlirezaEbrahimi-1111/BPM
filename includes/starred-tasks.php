<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  «منتخب» (کارهای ستاره‌دار) — مرجع مشترکِ وب و اپ، روزانه (۱۴۰۵/۰۸/۱۸).
 *
 *  هر ستاره جداگانه تاریخ می‌خورد (به وقت تهران) و از روز بعد دیگر برگردانده
 *  نمی‌شود. (۱۴۰۵/۰۸/۰۳ به درخواست کاربر دائمی شده بود؛ ۱۴۰۵/۰۸/۱۸ دوباره روزانه.)
 *
 *  ذخیره در user_dashboard_prefs (بدون مهاجرت):
 *    starred_tasks → فهرستِ JSON مثل ["task:12","wf:3"] (همان فرمتِ قبلی — اپ بدون تغییر)
 *    starred_dates → نگاشتِ JSON مثل {"task:12":"2026-11-09"}
 *  ستاره‌ی بدون تاریخ (پیش از این تغییر) = قدیمی → برداشته می‌شود.
 *  خواندن: api/dashboard/prefs-get.php و bootstrap.php؛ نوشتن: prefs-set.php.
 * ═══════════════════════════════════════════════════════════════════
 */

function starredToday(): string
{
    return (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
}

/** فهرست و تاریخ‌های خام. $lock = داخلِ ترنزکشن، با FOR UPDATE */
function starredRead(PDO $db, int $userId, bool $lock = false): array
{
    $stmt = $db->prepare("
        SELECT pref_key, pref_value FROM user_dashboard_prefs
        WHERE user_id = ? AND pref_key IN ('starred_tasks', 'starred_dates')" . ($lock ? " FOR UPDATE" : ""));
    $stmt->execute([$userId]);
    $list = [];
    $dates = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $v = json_decode((string) $row['pref_value'], true);
        if ($row['pref_key'] === 'starred_tasks' && is_array($v)) {
            $list = array_values(array_filter($v, 'is_string'));
        } elseif ($row['pref_key'] === 'starred_dates' && is_array($v)) {
            $dates = $v;
        }
    }
    return [$list, $dates];
}

/** فقط ستاره‌های امروز */
function starredOnlyToday(array $list, array $dates, ?string $today = null): array
{
    $today = $today ?? starredToday();
    return array_values(array_filter($list, fn($it) => ($dates[$it] ?? '') === $today));
}

/** ستاره‌های امروزِ کاربر (برای پاسخ‌های خواندنی) */
function starredGetToday(PDO $db, int $userId): array
{
    [$list, $dates] = starredRead($db, $userId);
    return starredOnlyToday($list, $dates);
}

/**
 * add | remove | merge روی ستاره‌های امروز؛ قدیمی‌ها همین‌جا پاک می‌شوند.
 * باید داخلِ ترنزکشن صدا زده شود (قفلِ ردیف‌ها). فهرستِ نهایی را برمی‌گرداند.
 */
function starredApply(PDO $db, int $userId, string $op, array $items): array
{
    $today = starredToday();
    [$list, $dates] = starredRead($db, $userId, true);
    $list = starredOnlyToday($list, $dates, $today);
    if ($op === 'remove') {
        $list = array_values(array_diff($list, $items));
    } else {
        $list = array_values(array_unique(array_merge($list, $items)));
    }
    $list = array_slice($list, -1000);
    $newDates = [];
    foreach ($list as $it) {
        $newDates[$it] = $today;
    }
    $save = $db->prepare("
        INSERT INTO user_dashboard_prefs (user_id, pref_key, pref_value, pref_date)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), pref_date = VALUES(pref_date)
    ");
    $save->execute([$userId, 'starred_tasks', json_encode($list, JSON_UNESCAPED_UNICODE), $today]);
    $save->execute([$userId, 'starred_dates', json_encode((object) $newDates, JSON_UNESCAPED_UNICODE), $today]);
    return $list;
}
