/*
 * money-shortcut.js — میانبر «سه صفر» برای فیلدهای مبلغِ فاکتور رسمی.
 * ------------------------------------------------------------------
 * وقتی مکان‌نما داخل یک فیلد مبلغ است و کاربر * را می‌زند (با یا بدون Ctrl)، مبلغ در
 * ۱۰۰۰ ضرب می‌شود (سه صفر به آخرش اضافه می‌شود) و با جداکنندهٔ هزارگان و
 * رقم فارسی نمایش داده می‌شود. مثال: ۲۱۵ → ۲۱۵٬۰۰۰.
 *
 * فیلدهای مبلغ: .it-price و .it-disc (ردیف‌های کالا در فاکتور/خرید) و هر
 * فیلدی که صفت data-money داشته باشد (قیمت واحد کالا، فرم افزودن سریع).
 *
 * یک شنوندهٔ سراسری؛ پس برای هر فرم جدا کد تکرار نمی‌شود. بعد از تغییر،
 * رویدادهای input/change را می‌فرستد تا محاسبهٔ جمع‌ها/قالب‌بندی صفحه هم اجرا شود.
 *
 * فقط function/var (بدون let/const سطح‌بالا) و با گارد ضدِ دوبار بایند شدن.
 */
(function () {
    'use strict';
    if (window.__moneyShortcutBound) return;
    window.__moneyShortcutBound = true;

    var SELECTOR = '.it-price, .it-disc, [data-money]';
    var FA = '۰۱۲۳۴۵۶۷۸۹';

    function toEnDigits(s) {
        return String(s == null ? '' : s)
            .replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
            .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); });
    }

    function toInt(v) {
        var n = parseInt(toEnDigits(v).replace(/[^\d-]/g, ''), 10);
        return isNaN(n) ? 0 : n;
    }

    // عدد صحیح → رشتهٔ فارسی با جداکنندهٔ هزارگان (مثل faMoney صفحات)
    function faMoney(n) {
        return String(Math.round(n).toLocaleString('en-US'))
            .replace(/\d/g, function (d) { return FA[+d]; });
    }

    function isMoneyField(el) {
        return el && typeof el.matches === 'function' && el.matches(SELECTOR);
    }

    // تشخیص *  (هم ستارهٔ ردیف اصلی = Shift+۸، هم ستارهٔ کیبورد عددی). Ctrl لازم نیست
    // (قبلا فقط Ctrl + * کار می‌کرد)؛ با Ctrl هم مثل قبل کار می‌کند. فیلد مبلغ هیچ‌وقت
    // خودِ کاراکتر * را لازم ندارد، پس تایپ‌نشدنش ضرری ندارد. Alt/Meta دست نمی‌خورند.
    function isStar(e) {
        if (e.altKey || e.metaKey) return false;
        return e.key === '*' || e.code === 'NumpadMultiply' || (e.shiftKey && e.code === 'Digit8');
    }

    document.addEventListener('keydown', function (e) {
        if (!isStar(e)) return;
        var el = e.target;
        if (!isMoneyField(el)) return;
        e.preventDefault();

        var cur = toInt(el.value);
        if (cur === 0) return; // چیزی برای سه‌صفرکردن نیست

        el.value = faMoney(cur * 1000);

        // محاسبهٔ جمع‌ها و قالب‌بندی صفحه دوباره اجرا شود
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));

        try {
            var L = el.value.length;
            el.setSelectionRange(L, L);
        } catch (err) { /* بعضی input ها setSelectionRange ندارند */ }
    }, true);
})();
