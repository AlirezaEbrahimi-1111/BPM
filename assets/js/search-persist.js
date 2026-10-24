/*
 * search-persist.js — متن جستجوی فهرست‌ها ماندگار است (درخواست ۱۴۰۵/۰۸/۰۲).
 * ------------------------------------------------------------------
 * بعد از رفرش صفحه یا رفتن به صفحهٔ دیگر و برگشتن، متن جستجو همان می‌ماند تا کاربر
 * ضربدر (×) کنارش را بزند. ذخیره در localStorage همین مرورگر (برای هر صفحه یک کلید).
 *
 *   SearchPersist.bind(document.getElementById('crmSearch'), 'inv-invoices');
 *     — باید قبل از اولین فیلتر صفحه صدا زده شود (مقدار ذخیره‌شده را در فیلد می‌گذارد).
 *     — با زدن ×: فیلد خالی، کلید پاک، و رویداد input فرستاده می‌شود تا فیلتر صفحه اجرا شود.
 *
 * فقط var/function (بدون let/const سطح‌بالا) و گارد ضدِ دوبار لود — مثل بقیهٔ فایل‌های مشترک.
 */
(function (w) {
    'use strict';
    if (w.SearchPersist) return;

    function injectStyle() {
        if (document.getElementById('sp-style')) return;
        var s = document.createElement('style');
        s.id = 'sp-style';
        s.textContent = [
            '.sp-wrap{position:relative;display:inline-flex;align-items:center}',
            '.sp-wrap > input{padding-left:32px}',
            '.sp-clear{position:absolute;left:6px;top:50%;transform:translateY(-50%);width:22px;height:22px;border:0;border-radius:50%;',
            '  background:rgba(0,0,0,.08);color:#4b5563;display:none;align-items:center;justify-content:center;font-size:14px;line-height:1;padding:0;cursor:pointer}',
            '.sp-clear:hover{background:rgba(220,38,38,.15);color:#dc2626}',
            '.sp-wrap.sp-has .sp-clear{display:inline-flex}',
            ':root[data-theme="dark"] .sp-clear{background:rgba(255,255,255,.12);color:#cbd5e1}'
        ].join('\n');
        document.head.appendChild(s);
    }

    function storeGet(k) { try { return localStorage.getItem(k) || ''; } catch (e) { return ''; } }
    function storeSet(k, v) { try { if (v) localStorage.setItem(k, v); else localStorage.removeItem(k); } catch (e) { /* مرورگر اجازه نداد */ } }

    function bind(input, pageKey) {
        if (!input || input.dataset.spBound) return;
        input.dataset.spBound = '1';
        injectStyle();
        var key = 'search:' + pageKey;

        // قاب + دکمهٔ × (عرض فیلد همان قبلی می‌ماند)
        var wrap = document.createElement('span');
        wrap.className = 'sp-wrap';
        var cs = w.getComputedStyle(input);
        wrap.style.flex = cs.flex;
        wrap.style.width = input.style.width || '';
        wrap.style.maxWidth = cs.maxWidth;
        wrap.style.minWidth = cs.minWidth;
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        input.style.width = '100%';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'sp-clear';
        btn.title = 'پاک کردن جستجو';
        btn.setAttribute('aria-label', 'پاک کردن جستجو');
        btn.innerHTML = '&times;';
        wrap.appendChild(btn);

        function sync() { wrap.classList.toggle('sp-has', !!input.value); }

        var saved = storeGet(key);
        if (saved && !input.value) input.value = saved;
        sync();

        input.addEventListener('input', function () {
            storeSet(key, input.value.trim() ? input.value : '');
            sync();
        });
        btn.addEventListener('click', function () {
            input.value = '';
            storeSet(key, '');
            sync();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
        });
    }

    w.SearchPersist = { bind: bind };
})(window);
