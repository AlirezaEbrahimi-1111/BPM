<?php
/**
 * دسترسیِ موقتِ ماژولِ «فروش / فاکتور».
 *
 * تا وقتی این ماژول در حالِ ساخت است، فقط کاربر id=1 و چند شماره‌ی مشخص
 * صفحه‌ها و منو را می‌بینند. این «دیدن» است، نه «نوشتن» — نوشتن هنوز به
 * مجوزِ users.is_create_official_invoice / is_sales_manager وابسته است
 * (هم در PHP هم در سرویسِ Go).
 *
 * برای افزودن/حذفِ یک نفر: فقط آرایه‌ی $EXTRA_PHONES را عوض کن.
 */

/** @return int[] شناسه‌ی کاربرانی که به ماژول دسترسی دارند */
function crmModuleUserIds(PDO $db): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $ids = [1];
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
            // اگر جدول/ستون در دسترس نبود، فقط id=1 می‌ماند.
        }
    }

    $cache = array_values(array_unique($ids));
    return $cache;
}

function crmModuleAllowed(PDO $db, int $userId): bool
{
    return $userId > 0 && in_array($userId, crmModuleUserIds($db), true);
}
