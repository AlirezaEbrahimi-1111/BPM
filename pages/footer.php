<?php
require_once '../includes/version.php';
?>
<!-- توجه: custom.css/dashboard-responsive.css/فونت اینجا دوباره لینک نمی‌شوند —
     همهٔ صفحاتی که footer.php را include می‌کنند، header.php را هم include کرده‌اند
     که همین فایل‌ها را از قبل لود می‌کند. لود دوم اینجا (که آخر از همه اجرا می‌شد)
     باعث می‌شد قواعد قدیمی/بدون تم‌تاریکِ custom.css روی override محلیِ هر صفحه غالب شود. -->

<style>
    /* sticky نه fixed — فقط به کفِ ناحیه‌یِ محتوایِ خودِ صفحه می‌چسبه و
       هیچ‌وقت رویِ اکشن‌بارهایِ fixed دیگه (مثلِ نوارِ عملیاتِ پایینِ
       task-detail.php) نمی‌افته. کلاس عمداً footer-9 نیست چون آن نام با
       یک قاعدهٔ ریسپانسیوِ باقی‌مانده در custom.css (مخفی‌کردنِ فوتر زیرِ
       768px) تداخل داشت */
    .site-footer {
        position: sticky;
        bottom: 0;
        width: 100%;
        margin-top: 24px;
        padding: 7px 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-size: 12px;
        background: var(--surface, #fff);
        border-top: 1px solid var(--border-soft, #eef0f2);
    }

    .site-footer .company {
        color: var(--primary-dark, #744ca4);
        font-weight: 700;
    }

    .site-footer .heart {
        padding-top: 4px;
        font-size: 14px;
        animation: footerHeartbeat 2s ease-in-out infinite;
    }

    @keyframes footerHeartbeat {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.2); }
    }
</style>
<div class="site-footer">
    <span>تهیه شده با</span>
    <span class="heart" style="color:red;">♥</span>
    <span>در شرکت</span>
    <span class="company">یکتا همراهان ملک</span>
        <span> | </span>
        <span class="footer-version" title="آخرین به‌روزرسانی: ۱۴۰۵/۰۵/۲۶ - ۰۶:۱۲">
            نسخه: ۵.۱۵
        </span>
</div>
