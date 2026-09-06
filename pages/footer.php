<?php
require_once '../includes/version.php';
?>
<!-- توجه: custom.css/dashboard-responsive.css/فونت اینجا دوباره لینک نمی‌شوند —
     همهٔ صفحاتی که footer.php را include می‌کنند، header.php را هم include کرده‌اند
     که همین فایل‌ها را از قبل لود می‌کند. لود دوم اینجا (که آخر از همه اجرا می‌شد)
     باعث می‌شد قواعد قدیمی/بدون تم‌تاریکِ custom.css روی override محلیِ هر صفحه غالب شود. -->

<style>
    /* فوترِ ثابت (فریز) در همهٔ صفحات: همیشه چسبیده به کفِ پنجره، با اسکرول
       جابه‌جا نمی‌شود. body یک padding-bottom می‌گیرد تا آخرین بخشِ محتوا
       زیرِ فوتر پنهان نشود. */
    body {
        min-height: calc(100vh - 3.5rem);
        padding-bottom: 40px;
    }

    .site-footer {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        z-index: 80;
        padding: 7px 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-size: 12px;
        background: var(--surface, #fff);
        border-top: 1px solid var(--border-soft, #eef0f2);
        box-shadow: 0 -3px 14px rgba(0, 0, 0, .12);
    }

    :root[data-theme="dark"] .site-footer {
        box-shadow: 0 -3px 14px rgba(0, 0, 0, .5);
    }

    /* صفحاتی که خودشان یک نوارِ عملیاتِ ثابتِ پایین دارند (مثلِ task-detail.php):
       فوتر بالایِ آن نوار بنشیند، نه رویش. */
    body:has(.TDaction-buttons) .site-footer {
        bottom: 58px;
    }

    body:has(.TDaction-buttons) {
        padding-bottom: 100px;
    }

    .site-footer .company {
        color: var(--primary-dark, #8e57fe);
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
    <span class="company">آوای شرق ملک</span>
        <span> | </span>
        <span class="footer-version" title="آخرین به‌روزرسانی: ۱۴۰۵/۰۶/۱۵ - ۱۴:۳۰">
            نسخه: ۶.۹۶
        </span>
</div>
