<?php
/**
 * includes/support-accounts.php
 * ───────────────────────────────────────────────────────────────────
 * حساب‌های «فقط پشتیبان» (درخواست ۱۴۰۵/۰۷/۲۸): id=1 = پشتیبان/توسعه‌دهندهٔ سیستم.
 * نقشش در سازمان ۱ «supervisor» است (برای دسترسی‌ها)، ولی نباید هیچ اعلان یا آلارمی
 * از سازمان بگیرد و نباید در مسیر تأیید درخواست‌های سازمان قرار بگیرد:
 *   • درخواست مرخصی تشویقی، ثبت/دسترسی دستگاه ورود و خروج
 *   • تأیید مرخصی/مأموریت/فراموشی/مشکل فنی (مرحلهٔ «مسئول»)، رفع دورهٔ معوقه
 * و از قانون «مانع خروج با کار معوقه» معاف است (بقیهٔ سرپرست‌ها دیگر معاف نیستند).
 * تیکت پشتیبانی، چت و کاری که مستقیم به خودش ارجاع شود دست نخورده‌اند.
 *
 * فهرست از getSuperAdminIds جداست: id=19 مدیر کل است و همهٔ این‌ها را می‌گیرد.
 */

if (!function_exists('supportOnlyUserIds')) {
    /** @return int[] */
    function supportOnlyUserIds(): array
    {
        return [1];
    }

    /** آیا این کاربر حساب «فقط پشتیبان» است؟ */
    function isSupportOnlyUser($userId): bool
    {
        return in_array((int) $userId, supportOnlyUserIds(), true);
    }

    /**
     * تکهٔ SQL برای کنار گذاشتن حساب‌های پشتیبان از فهرست گیرنده/تأییدکننده.
     * مقادیر عدد صحیح ثابت‌اند (ورودی کاربر نیست) پس درج مستقیم امن است.
     * استفاده:  "... AND " . supportOnlySqlExclude('u.id')
     */
    function supportOnlySqlExclude(string $col = 'id'): string
    {
        $ids = array_map('intval', supportOnlyUserIds());
        return $ids ? $col . ' NOT IN (' . implode(',', $ids) . ')' : '1=1';
    }
}
