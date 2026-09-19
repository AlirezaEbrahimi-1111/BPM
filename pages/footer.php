<?php
require_once '../includes/version.php';
?>
<!-- توجه: custom.css/dashboard-responsive.css/فونت اینجا دوباره لینک نمی‌شوند —
     همهٔ صفحاتی که footer.php را include می‌کنند، header.php را هم include کرده‌اند
     که همین فایل‌ها را از قبل لود می‌کند. لود دوم اینجا (که آخر از همه اجرا می‌شد)
     باعث می‌شد قواعد قدیمی/بدون تم‌تاریکِ custom.css روی override محلیِ هر صفحه غالب شود. -->

<style>
    /* فوترِ ثابت (فریز) در همهٔ صفحات — حالا به شکلِ «زبانه» (rounded tab):
       وسط‌چین، چسبیده به کفِ پنجره، عرضش دقیقاً به‌اندازهٔ متنِ داخلش
       (width:fit-content). رنگِ پس‌زمینه همان رنگِ قبلی (var(--surface)) است؛
       فقط فُرم تغییر کرده. تکنیک: border-radius بیضوی + ماسکِ radial-gradient
       برای «گوشِ» مقعرِ پایین‌چپ/پایین‌راست. */
    body {
        min-height: calc(100vh - 3.5rem);
        padding-bottom: 40px;
    }

    /* لایهٔ بیرونی فقط برای «سایه»: چون خودِ .site-footer ماسک دارد و ماسک هر
       چیزی بیرونِ padding-box (از جمله drop-shadow) را می‌بُرد، سایه باید روی یک
       عنصرِ بی‌ماسک باشد. drop-shadow روی این والدِ بی‌ماسک، سایه را دقیقاً به
       شکلِ زبانهٔ ماسک‌شدهٔ فرزند (با گوش‌های مقعر) می‌اندازد. */
    .site-footer-shadow {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 80;
        display: flex;
        justify-content: center;
        pointer-events: none;   /* نوارِ نامرئیِ تمام‌عرض جلوی کلیکِ محتوای صفحه را نگیرد */
        filter:
            drop-shadow(0 -1px 2px rgba(0, 0, 0, .01))
            drop-shadow(0 -1px 10px rgba(0, 0, 0, .01))
            drop-shadow(0 -1px 30px rgba(0, 0, 0, .01));
    }

    :root[data-theme="dark"] .site-footer-shadow {
        filter:
            drop-shadow(0 -6px 20px rgba(0, 0, 0, .5))
            drop-shadow(0 -2px 6px rgba(0, 0, 0, .34));
    }

    .site-footer {
        pointer-events: auto;
        width: fit-content;
        margin-inline: auto;

        --r: 16px;              /* شعاعِ انحنا */
        line-height: 2.4;       /* ارتفاع را کنترل می‌کند */
        padding-inline: 1.1em;
        border-inline: var(--r) solid transparent;
        border-radius: calc(2 * var(--r)) calc(2 * var(--r)) 0 0 / var(--r);
        -webkit-mask:
            radial-gradient(var(--r) at var(--r) 0, #0000 98%, #000 101%)
                calc(-1 * var(--r)) 100% / 100% var(--r) repeat-x,
            conic-gradient(#000 0 0) padding-box;
        mask:
            radial-gradient(var(--r) at var(--r) 0, #0000 98%, #000 101%)
                calc(-1 * var(--r)) 100% / 100% var(--r) repeat-x,
            conic-gradient(#000 0 0) padding-box;
        /* ته‌رنگِ فیلیِ خیلی ملایم تا از پس‌زمینهٔ سفیدِ صفحه متمایز بماند */
        background: color-mix(in srgb, var(--surface, #fff) 94%, #6b6472) border-box;

        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-size: 12px;
    }

    :root[data-theme="dark"] .site-footer {
        background: color-mix(in srgb, var(--surface, #1b2130) 90%, #ffffff) border-box;
    }

    /* صفحاتی که خودشان یک نوارِ عملیاتِ ثابتِ پایین دارند (مثلِ task-detail.php):
       زبانه بالایِ آن نوار بنشیند، نه رویش. */
    body:has(.TDaction-buttons) .site-footer-shadow {
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
<div class="site-footer-shadow">
    <div class="site-footer">
        <span>تهیه شده با</span>
        <span class="heart" style="color:red;">♥</span>
        <span>در شرکت</span>
        <span class="company">آوای شرق ملک</span>
        <span> | </span>
        <span class="footer-version" title="آخرین به‌روزرسانی: ۱۴۰۵/۰۶/۲۸ - ۱۵:۱۲">
            نسخه: ۷.۹۷
        </span>
    </div>
</div>
