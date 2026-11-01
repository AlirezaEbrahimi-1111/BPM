/**
 * black-tooltip.js — تولتیپِ مشکیِ یکدستِ سراسری (هر دو تمِ روشن/تاریک).
 *
 * درخواست کاربر (۱۴۰۵/۰۸/۱۰): «همهٔ تولتیپ‌های پروژه بکگراند مشکی باشند».
 * تولتیپِ بومیِ مرورگر (attribute `title`) سفید و غیرقابل‌استایل است؛ این فایل
 * آن را روی hover با یک تولتیپِ مشکیِ خودمان جایگزین می‌کند. عناصری که
 * `data-tip` دارند هم همین را می‌گیرند (برای متن‌های چندخطی، مثل لیست واریزها).
 *
 * به body می‌چسبد تا هیچ overflowِ کانتینری (مثل سلول AG Grid) برشش ندهد.
 * تولتیپ‌های Bootstrap (data-bs-toggle="tooltip") دست‌نخورده می‌مانند — خودشان
 * مشکی‌اند و منطق خودشان را دارند. `title` موقتِ hover برداشته و روی خروج
 * برگردانده می‌شود تا هم تولتیپ سفید نیاید و هم کدهای دیگر که title را می‌خوانند نشکنند.
 *
 * سراسری از pages/header.php لود می‌شود. وابستگی ندارد.
 */
(function () {
    if (window.__blackTip) return;
    window.__blackTip = true;

    var SHOW_DELAY = 200; // مثل تأخیرِ ملایمِ تولتیپ بومی، تا هر تکان‌خوردن تولتیپ نیاورد

    function injectStyle() {
        if (document.getElementById('bt-tip-style')) return;
        var s = document.createElement('style');
        s.id = 'bt-tip-style';
        s.textContent =
            '.bt-tip{position:fixed;z-index:4000;max-width:340px;background:#111827;color:#fff;' +
            'font-size:.72rem;line-height:1.75;padding:7px 11px;border-radius:8px;' +
            'box-shadow:0 8px 28px rgba(0,0,0,.45);white-space:pre-line;pointer-events:none;' +
            'opacity:0;transition:opacity .12s;direction:rtl;text-align:right}' +
            '.bt-tip.show{opacity:1}';
        document.head.appendChild(s);
    }

    var tip = null, cur = null, timer = null, lastEvt = null;

    function ensureTip() {
        if (tip) return tip;
        injectStyle();
        tip = document.createElement('div');
        tip.className = 'bt-tip';
        document.body.appendChild(tip);
        return tip;
    }

    function place(e) {
        if (!tip) return;
        var pad = 12, w = tip.offsetWidth, h = tip.offsetHeight;
        var x = e.clientX - w - pad;            // RTL: سمت چپِ نشانگر
        if (x < 6) x = e.clientX + pad;
        if (x + w > window.innerWidth - 6) x = window.innerWidth - w - 6;
        var y = e.clientY + pad;
        if (y + h > window.innerHeight - 6) y = e.clientY - h - pad;
        if (y < 6) y = 6;
        tip.style.left = x + 'px';
        tip.style.top = y + 'px';
    }

    // متنِ تولتیپ: data-tip اولویت دارد؛ وگرنه title (که موقتاً کنار گذاشته می‌شود).
    function textOf(el) {
        var d = el.getAttribute('data-tip');
        if (d) return d;
        var saved = el.getAttribute('data-bt-title');
        if (saved != null) return saved;
        var ti = el.getAttribute('title');
        if (ti) {
            el.setAttribute('data-bt-title', ti);
            el.removeAttribute('title'); // تا تولتیپ سفیدِ بومی نیاید
            return ti;
        }
        return '';
    }

    function restore(el) {
        if (!el) return;
        var saved = el.getAttribute('data-bt-title');
        if (saved != null) {
            el.setAttribute('title', saved);
            el.removeAttribute('data-bt-title');
        }
    }

    function skip(el) {
        // تولتیپ‌های Bootstrap را خودشان مدیریت می‌کنند
        return el.getAttribute('data-bs-toggle') === 'tooltip' || el.hasAttribute('data-bs-original-title');
    }

    function hide() {
        if (timer) { clearTimeout(timer); timer = null; }
        if (tip) tip.classList.remove('show');
        if (cur) { restore(cur); cur = null; }
    }

    function showFor(el) {
        var txt = textOf(el);
        if (!txt) return;
        ensureTip();
        cur = el;
        tip.textContent = txt;
        tip.classList.add('show');
        if (lastEvt) place(lastEvt);
    }

    document.addEventListener('mouseover', function (e) {
        var el = e.target.closest ? e.target.closest('[data-tip],[title]') : null;
        if (!el || el === cur || skip(el)) return;
        if (cur) hide();
        lastEvt = e;
        timer = setTimeout(function () { timer = null; showFor(el); }, SHOW_DELAY);
    });
    document.addEventListener('mousemove', function (e) {
        lastEvt = e;
        if (cur) place(e);
    });
    document.addEventListener('mouseout', function (e) {
        if (!cur && !timer) return;
        var to = e.relatedTarget;
        if (to && to.closest && to.closest('[data-tip],[data-bt-title],[title]') === cur) return;
        hide();
    });
    // اسکرول/کلیک/خروج از پنجره → تولتیپ بسته شود
    document.addEventListener('scroll', hide, true);
    document.addEventListener('click', hide, true);
    window.addEventListener('blur', hide);
})();
