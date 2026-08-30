<?php
require_once '../includes/version.php';
?>
<!-- توجه: custom.css/dashboard-responsive.css/فونت اینجا دوباره لینک نمی‌شوند —
     همهٔ صفحاتی که footer.php را include می‌کنند، header.php را هم include کرده‌اند
     که همین فایل‌ها را از قبل لود می‌کند. لود دوم اینجا (که آخر از همه اجرا می‌شد)
     باعث می‌شد قواعد قدیمی/بدون تم‌تاریکِ custom.css روی override محلیِ هر صفحه غالب شود. -->

<style>
    /* الگویِ sticky-footer بدونِ دست‌زدن به layoutِ body: body حداقل به‌اندازهٔ
       ارتفاعِ صفحه بلند می‌شود و فوتر با position:sticky + top:100vh همیشه به
       کفِ پنجره می‌چسبد — حتی وقتی محتوا کم است (قبلاً وسطِ صفحه می‌ماند). روی
       صفحاتِ بلند، طبیعی ته صفحه قرار می‌گیرد. */
    body {
        min-height: calc(100vh - 3.5rem);
    }

    /* sticky نه fixed — هیچ‌وقت رویِ اکشن‌بارهایِ fixed دیگه (مثلِ نوارِ
       عملیاتِ پایینِ task-detail.php) نمی‌افته. */
    .site-footer {
        position: sticky;
        top: 100vh;
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
        <span class="footer-version" title="آخرین به‌روزرسانی: ۱۴۰۵/۰۶/۰۸ - ۰۹:۳۱">
            نسخه: ۶.۰۰
        </span>
</div>
