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
</style>
<div class="footer-9">
    <span>تهیه شده با</span>
    <span class="heart" style="color:red;">♥</span>
    <span>در شرکت</span>
    <span class="company">یکتا همراهان ملک</span>
        <span> | </span>
        <span class="footer-version" style="cursor:help; border-bottom:1px dotted currentColor;"
              data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="top"
              title="<b>تازه‌های این نسخه:</b><br>
                     • حالت تاریک برای داشبورد، تیکت‌ها و صفحات مرتبط<br>
                     • بازطراحی صفحه‌ی تیکت به‌شکل پیام‌رسان (امکان ارسال عکس با چسباندن مستقیم)<br>
                     • امکان تعریف چک‌لیست برای هر مرحله از کارهای روتین<br>
                     • جستجوی هوشمندتر در کارها (شامل تاریخچه و فایل‌های پیوستی)<br>
                     • افزایش سرعت بارگذاری صفحات کارهای من و نظارت بر کارها<br>
                     • نمایش «در حال بارگذاری» به‌جای پیام خالی هنگام بارگذاری جدول‌ها">
            نسخه: ۴.۱
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