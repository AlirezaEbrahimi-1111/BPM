<?php
/**
 * دسترسیِ موقتِ ماژولِ «فروش / فاکتور».
 *
 * تا وقتی این ماژول در حالِ ساخت است، فقط افرادِ زیر منو و صفحه‌ها را می‌بینند:
 *   - کاربر id = 1
 *   - کاربر id = 19 (رضا فضایلی)
 *   - کلِ تیمِ حسابداریِ سازمانِ ۱  →  هر کاربرِ فعالی که «بخشِ فعالیتش»
 *     حسابداری باشد: users.activity_section = 'accounting' یا یک ردیف در
 *     user_activity_sections با section_key = 'accounting'.
 *     (توجه: در این سیستم حسابداری با «بخش/section» مشخص می‌شود نه
 *      «واحد/unit»؛ ستونِ users.activity_unit عملاً برای همه 'all' است.
 *      چکِ قدیمیِ 'AC' هم نگه داشته شده تا اگر جایی از آن استفاده شد نشکند.)
 *   - شماره‌موبایل‌هایِ موقتِ فهرستِ $EXTRA_PHONES
 *
 * این «دیدن» است، نه «نوشتن» — نوشتن هنوز به مجوزِ
 * users.is_create_official_invoice / is_sales_manager وابسته است
 * (هم در PHP هم در سرویسِ Go).
 *
 * برای افزودن/حذفِ یک نفرِ خاص: id را در آرایه‌ی $ids یا موبایل را در
 * $EXTRA_PHONES عوض کن.
 */

/** @return int[] شناسه‌ی کاربرانی که به ماژول دسترسی دارند */
function crmModuleUserIds(PDO $db): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $ids = [1, 19]; // id=1 + رضا فضایلی (id=19)

    $EXTRA_PHONES = [
        '09927949376', // خانم بادپر — موقت (موبایل)
    ];

    if ($EXTRA_PHONES) {
        try {
            $in = implode(',', array_fill(0, count($EXTRA_PHONES), '?'));
            $st = $db->prepare("SELECT id FROM users WHERE phone IN ($in) AND is_active = 1");
            $st->execute($EXTRA_PHONES);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pid) {
                $ids[] = (int) $pid;
            }
        } catch (Throwable $e) {
            // اگر جدول/ستون در دسترس نبود، فقط id‌هایِ ثابت می‌مانند.
        }
    }

    // کلِ تیمِ حسابداریِ سازمانِ ۱ — بر پایهٔ «بخشِ فعالیت» (نه واحد).
    try {
        $st = $db->query("
            SELECT u.id
            FROM users u
            WHERE u.is_active = 1
              AND u.organization_id = 1
              AND (
                    u.activity_section = 'accounting'
                    OR EXISTS (
                        SELECT 1 FROM user_activity_sections uas
                        WHERE uas.user_id = u.id AND uas.section_key = 'accounting'
                    )
                    OR u.activity_unit = 'AC'
                    OR EXISTS (
                        SELECT 1 FROM user_activity_units uau
                        WHERE uau.user_id = u.id AND uau.activity_unit = 'AC'
                    )
                  )
        ");
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            $ids[] = (int) $pid;
        }
    } catch (Throwable $e) {
        // اگر جدول/ستون در دسترس نبود، بی‌صدا رد شو.
    }

    $cache = array_values(array_unique($ids));
    return $cache;
}

function crmModuleAllowed(PDO $db, int $userId): bool
{
    return $userId > 0 && in_array($userId, crmModuleUserIds($db), true);
}
