<?php
require_once '../includes/version.php';
?>
<!-- توجه: custom.css/dashboard-responsive.css/فونت اینجا دوباره لینک نمی‌شوند —
     همهٔ صفحاتی که footer.php را include می‌کنند، header.php را هم include کرده‌اند
     که همین فایل‌ها را از قبل لود می‌کند. لود دوم اینجا (که آخر از همه اجرا می‌شد)
     باعث می‌شد قواعد قدیمی/بدون تم‌تاریکِ custom.css روی override محلیِ هر صفحه غالب شود. -->

<style>
    /* تولتیپ نسخه‌ی فوتر — راست‌چین و ریزتر از حالت پیش‌فرض بوت‌استرپ.
       نکته: بوت‌استرپ ویژگی style را از HTML داخل title پاک‌سازی می‌کند،
       پس تراز/فونت باید از راه یک کلاس اختصاصی (customClass) اعمال شود. */
    .footer-version-tooltip .tooltip-inner {
        text-align: right;
        font-size: .74rem;
        line-height: 2;
        max-width: 300px;
    }
    /* رنگ پس‌زمینه/متن تولتیپ از راه متغیرهای CSS خودِ بوت‌استرپ (bs-tooltip-bg/color)
       ست می‌شود تا هم بدنه و هم فلش تولتیپ با هم هماهنگ بمانند */
    .footer-version-tooltip {
        --bs-tooltip-bg: #222;
        --bs-tooltip-color: #fff;
    }
    :root[data-theme="dark"] .footer-version-tooltip {
        --bs-tooltip-bg: var(--surface);
        --bs-tooltip-color: var(--text-strong);
    }
    :root[data-theme="dark"] .footer-version-tooltip .tooltip-inner {
        border: 1px solid var(--border-soft);
    }

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
        <span class="footer-version" style="border-bottom:1px currentColor;"
              data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="top"
              title="تغییراتِ این نسخه:<br>• امکانِ دادنِ دسترسیِ مشاهدهٔ یک کار به افرادِ خاص، با کنترلِ جداگانهٔ دیدنِ پیوست/تاریخچه<br>• ارجاعِ یک کارِ مقطعی/دوره‌ای به چند نفر هم‌زمان<br>• اطلاعیه‌هایِ خوانده‌شده دیگر در لیستِ زنگِ اطلاعیه‌ها نمایش داده نمی‌شوند<br>• هدایتِ خودکارِ داشبورد بر اساسِ نقشِ کاربر (مدیر/کارمند)<br>• رفعِ چند اشکالِ نمایشِ تمِ تاریک (پیوست‌ها، چک‌لیست، فرمِ ورود، سربرگِ داشبورد)<br>• بهبودِ نمایشِ برنامهٔ ماهانه روی صفحه‌نمایش‌هایِ کوچک‌تر">
            نسخه: ۴.۵
        </span>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.querySelector('.footer-version');
        if (el && window.bootstrap && bootstrap.Tooltip) {
            new bootstrap.Tooltip(el, { customClass: 'footer-version-tooltip' });
        }
    });
</script>
