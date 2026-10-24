/*
 * row-arrow-nav.js — حرکت بین ردیف‌های جدول اقلام با فلش بالا/پایین (درخواست ۱۴۰۵/۰۸/۰۲).
 * ------------------------------------------------------------------
 * روی هر <tbody data-arrow-nav> کار می‌کند (فاکتور فروش و فاکتور خرید). فلش ↓ / ↑ فوکوس را
 * به «همان ستون» در ردیف بعد/قبل می‌برد؛ در ردیف اول/آخر کاری نمی‌کند (ردیف تازه نمی‌سازد —
 * ساختن ردیف کار Enter است).
 *
 * فیلد دارای فهرست پیشنهادی (<input list="…">، مثل «نام کالا»): وقتی کاربر از لحظهٔ ورود به
 * فیلد چیزی تایپ کرده و هنوز انتخاب نکرده، فلش‌ها مال فهرست پیشنهادهای مرورگر است؛ بعد از
 * انتخاب (change) یا وقتی چیزی تایپ نشده، فلش‌ها دوباره بین ردیف‌ها حرکت می‌دهند.
 *
 * یک شنوندهٔ سراسری (مثل money-shortcut.js) — ردیف‌هایی که بعدا اضافه می‌شوند هم کار می‌کنند.
 * فقط function/var و گارد ضدِ دوبار بایند شدن.
 */
(function () {
    'use strict';
    if (window.__rowArrowNavBound) return;
    window.__rowArrowNavBound = true;

    function focusable(td) {
        return td ? td.querySelector('input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled])') : null;
    }

    document.addEventListener('input', function (e) {
        var el = e.target;
        if (el && el.hasAttribute && el.hasAttribute('list') && el.closest('tbody[data-arrow-nav]')) el.dataset.navTyping = '1';
    }, true);
    ['change', 'focusin'].forEach(function (ev) {
        document.addEventListener(ev, function (e) {
            var el = e.target;
            if (el && el.dataset && el.dataset.navTyping) delete el.dataset.navTyping;
        }, true);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
        if (e.altKey || e.ctrlKey || e.shiftKey || e.metaKey || e.isComposing) return;
        var el = e.target;
        if (!el || !el.closest) return;
        var tbody = el.closest('tbody[data-arrow-nav]');
        if (!tbody) return;
        if (el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') return; // فلش در این‌ها کار خودش را دارد
        if (el.hasAttribute('list') && el.dataset.navTyping && el.value.trim()) return; // فهرست پیشنهادی

        var td = el.closest('td');
        var tr = td && td.parentElement;
        if (!tr || tr.parentElement !== tbody) return;
        var target = e.key === 'ArrowDown' ? tr.nextElementSibling : tr.previousElementSibling;
        e.preventDefault(); // در ردیف اول/آخر هم مکان‌نما به ابتدا/انتهای متن نپرد
        if (!target) return;
        var next = focusable(target.children[td.cellIndex]);
        if (!next) return;
        next.focus();
        if (typeof next.select === 'function' && next.type !== 'checkbox') next.select();
    });
})();
