<?php
/**
 * دسترسی «CRM» جدید (حسابداری غیررسمی) — فقط سازمان ۱.
 *
 * ⚠️ جدا از ماژول «فاکتور رسمی» (includes/crm_access.php). این‌جا سه نقش داریم:
 *   - manager    : مدیر کل (id 1/19) + سرپرست‌های سازمان ۱ (role مدیریتی یا is_supervisor)
 *                  → همهٔ مشتری‌ها، تأیید مرحلهٔ مدیر، تغییر کارشناس، ویرایش/حذف مستقیم، تنظیمات
 *   - accounting : واحد «حسابداری» (accounting — نه «حسابداری رسمی») → همهٔ مشتری‌ها، تأیید مرحلهٔ حسابداری
 *   - sales      : واحد «فروش» (sales) → فقط مشتری‌های خودش؛ ثبت مشتری و ارسال برای تأیید
 *
 * ⚠️ هم‌راستا با ucrmRolesOf در crm-service/ucrm_customers.go نگه دار.
 */

/** @return array{manager:bool,accounting:bool,sales:bool} */
function ucrmRoles(PDO $db, int $userId): array
{
    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];

    $r = ['manager' => false, 'accounting' => false, 'sales' => false];
    if ($userId <= 0) return $cache[$userId] = $r;
    if ($userId === 1 || $userId === 19) {
        $r['manager'] = true;
        return $cache[$userId] = $r;
    }
    try {
        $st = $db->prepare("
            SELECT
              (u.role IN ('supervisor','admin','manager','management') OR COALESCE(u.is_supervisor, 0) = 1) AS is_mgr,
              (u.activity_section = 'accounting' OR EXISTS (SELECT 1 FROM user_activity_sections s
                    WHERE s.user_id = u.id AND s.section_key = 'accounting')) AS is_acc,
              (u.activity_section = 'sales' OR EXISTS (SELECT 1 FROM user_activity_sections s
                    WHERE s.user_id = u.id AND s.section_key = 'sales')) AS is_sales
            FROM users u
            WHERE u.id = ? AND u.is_active = 1 AND u.organization_id = 1
        ");
        $st->execute([$userId]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $r['manager'] = (bool) $row['is_mgr'];
            $r['accounting'] = (bool) $row['is_acc'];
            $r['sales'] = (bool) $row['is_sales'];
        }
    } catch (Throwable $e) {
        // اگر جدول/ستون در دسترس نبود، بدون دسترسی
    }
    return $cache[$userId] = $r;
}

function ucrmAllowed(PDO $db, int $userId): bool
{
    $r = ucrmRoles($db, $userId);
    return $r['manager'] || $r['accounting'] || $r['sales'];
}

/**
 * داشبورد اصلی (pages/dashboard-*.php، ۱۴۰۵/۰۸/۰۳):
 *   'tabs' → مدیر/حسابداری: دکمه‌های «عملیات | فروش» + تب فروش (pages/partials/ucrm-dash-sales.php)
 *   'rep'  → کارشناس فروش: کارت «کارهای کارشناس فروش» کنار «کارها» (pages/partials/ucrm-dash-rep.php)
 *   ''     → بقیه: داشبورد بدون تغییر
 */
function ucrmDashMode(PDO $db, int $userId): string
{
    $r = ucrmRoles($db, $userId);
    if ($r['manager'] || $r['accounting']) return 'tabs';
    return $r['sales'] ? 'rep' : '';
}
