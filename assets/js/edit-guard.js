/*
 * edit-guard.js — محافظ فرم ویرایش (درخواست ۱۴۰۵/۰۷/۳۰ — فاکتور فروش و خرید حسابداری رسمی)
 * ------------------------------------------------------------------
 * ۱) خروج از داخل برنامه (هر لینک: منوی هدر، «بازگشت به فهرست»…) وقتی تغییر ذخیره‌نشده
 *    هست → پنجرهٔ «در حال ویرایش … هستید؛ مطمئنید می‌خواهید خارج شوید؟» با سه دکمه:
 *      ذخیره و خروج  → save() صفحه؛ اگر موفق بود خارج می‌شود (اگر نه، همان‌جا با پیام خطا می‌ماند)
 *      خروج          → بدون ذخیره خارج می‌شود (نسخهٔ موقت هم پاک می‌شود)
 *      انصراف        → همان‌جا می‌ماند
 * ۲) بستن تب / رفرش / دکمهٔ برگشت مرورگر → فقط پیام ثابت خود مرورگر ممکن است (مرورگرها
 *    پنجرهٔ دلخواه اجازه نمی‌دهند).
 * ۳) پیش‌نویس خودکار: هر چند ثانیه وضعیت فرم در localStorage همین مرورگر ذخیره می‌شود؛ پس
 *    حتی بستن اجباری مرورگر یا قطع برق هم کار را از بین نمی‌برد. دفعهٔ بعد که همان فرم باز
 *    شود، پیشنهاد «بازیابی» می‌دهد. نسخه‌های قدیمی‌تر از ۱۴ روز دور ریخته می‌شوند.
 *
 * استفاده (فقط یک‌بار در هر صفحه):
 *   EditGuard.init({
 *     key: 'inv-sale:' + (INV_ID || 'new'),     // جدا برای هر فرم/رکورد
 *     label: () => 'فاکتور فروش ۷۰۴',             // در متن پیام‌ها
 *     snapshot: () => ({...}),                    // وضعیت فرم (JSON‌پذیر)
 *     restore: (state) => {...},                  // برگرداندن وضعیت ذخیره‌شده به فرم
 *     save: async () => true | false,             // ذخیرهٔ واقعی؛ بدون رفتن به صفحهٔ دیگر
 *   });
 *   EditGuard.ready();     // بعد از کامل شدن بارگذاری فرم (مبنای «بدون تغییر» + پیشنهاد بازیابی)
 *   EditGuard.saved();     // بعد از هر ذخیرهٔ موفق خود صفحه (مبنا تازه + پاک شدن نسخهٔ موقت)
 *   EditGuard.leave(url);  // رفتن با کد به صفحهٔ دیگر — با همان پرسش
 *
 * فقط var/function (بدون let/const سطح‌بالا) و گارد ضد دوبار لود — مثل بقیهٔ فایل‌های مشترک.
 */
(function (w) {
    'use strict';
    if (w.EditGuard) return;

    var cfg = null, baseline = null, isReady = false, bypass = false, timer = null, askingRestore = false;
    var PREFIX = 'bpm-edit-draft:';
    var MAX_AGE = 14 * 24 * 3600 * 1000;

    function nowMs() { return (w.TimeSync && TimeSync.serverNowMs) ? TimeSync.serverNowMs() : Date.now(); }

    function userId() {
        try {
            var t = localStorage.getItem('auth_token') || '';
            var p = JSON.parse(atob(t.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')));
            return p.user_id || 0;
        } catch (e) { return 0; }
    }

    function storeKey() { return PREFIX + userId() + ':' + cfg.key; }

    function snap() {
        try { return JSON.stringify(cfg.snapshot()); } catch (e) { return baseline; }
    }

    function isDirty() { return isReady && baseline !== null && snap() !== baseline; }

    function writeLocal() {
        try {
            if (isDirty()) {
                localStorage.setItem(storeKey(), JSON.stringify({ ts: nowMs(), label: label(), state: JSON.parse(snap()) }));
            } else if (!askingRestore) {
                localStorage.removeItem(storeKey());
            }
        } catch (e) { /* حالت خصوصی/پر بودن حافظه — بی‌صدا */ }
    }

    function clearLocal() { try { localStorage.removeItem(storeKey()); } catch (e) { } }

    function label() { try { return cfg.label() || 'این فرم'; } catch (e) { return 'این فرم'; } }

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // پنجرهٔ ساده با چند دکمه (هم‌شکل uiConfirm پروژه، با فوکوس روی دکمهٔ اول)
    function dialog(html, buttons) {
        var ov = document.createElement('div');
        ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px';
        ov.className = 'eg-overlay';
        ov.innerHTML = '<div class="eg-dialog" role="dialog" aria-modal="true" style="background:var(--surface,#fff);color:var(--text-strong,#2d3748);border-radius:12px;padding:20px;width:100%;max-width:460px;box-shadow:0 10px 40px rgba(0,0,0,.25);direction:rtl;text-align:right">' +
            '<p style="margin:0 0 16px;font-size:15px;line-height:1.9">' + html + '</p>' +
            '<div class="eg-btns" style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap"></div><div class="eg-err" style="color:#dc2626;font-size:12.5px;margin-top:10px" hidden></div></div>';
        var box = ov.querySelector('.eg-btns');
        var api = {
            close: function () { ov.remove(); document.removeEventListener('keydown', onKey, true); },
            error: function (m) { var e = ov.querySelector('.eg-err'); e.textContent = m; e.hidden = !m; },
            busy: function (b) { box.querySelectorAll('button').forEach(function (x) { x.disabled = b; }); }
        };
        var cancelFn = null;
        buttons.forEach(function (b) {
            var el = document.createElement('button');
            el.type = 'button';
            el.className = 'btn ' + b.cls;
            // استایل عمومی .btn کادر را برمی‌دارد → دکمهٔ «خروج» (outline) کادر قرمز داشته باشد
            if (/outline/.test(b.cls)) el.style.cssText = 'border:1px solid currentColor!important;background:transparent';
            el.textContent = b.text;
            el.addEventListener('click', function () { b.fn(api); });
            box.appendChild(el);
            if (b.cancel) cancelFn = b.fn;
        });
        function onKey(e) {
            if (e.key === 'Escape' && cancelFn) { e.preventDefault(); e.stopPropagation(); cancelFn(api); }
        }
        document.addEventListener('keydown', onKey, true);
        ov.addEventListener('click', function (e) { if (e.target === ov && cancelFn) cancelFn(api); });
        document.body.appendChild(ov);
        var first = box.querySelector('button');
        if (first) first.focus();
        return api;
    }

    function go(url) {
        bypass = true;
        if (url) location.href = url;
    }

    // پرسش خروج (لینک‌ها یا EditGuard.leave)
    function askLeave(url) {
        dialog('در حال ویرایش <b>' + esc(label()) + '</b> هستید و تغییرات ذخیره نشده است.<br>مطمئنید می‌خواهید خارج شوید؟', [
            {
                text: 'ذخیره و خروج', cls: 'btn-primary', fn: function (d) {
                    d.busy(true);
                    d.error('');
                    Promise.resolve().then(function () { return cfg.save(); }).then(function (ok) {
                        if (ok) {
                            baseline = snap();
                            clearLocal();
                            d.close();
                            go(url);
                        } else {
                            // ذخیره ناموفق (مثلا مشتری انتخاب نشده): پیام خطای خود صفحه دیده شود
                            d.close();
                        }
                    }, function (e) {
                        d.busy(false);
                        d.error((e && e.message) || 'ذخیره نشد.');
                    });
                }
            },
            { text: 'خروج', cls: 'btn-outline-danger', fn: function (d) { d.close(); clearLocal(); go(url); } },
            { text: 'انصراف', cls: 'btn-secondary', cancel: true, fn: function (d) { d.close(); } }
        ]);
    }

    function onLinkClick(e) {
        if (!isDirty() || bypass || e.defaultPrevented) return;
        if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return; // تب جدید → خروجی نیست
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a || a.target === '_blank' || a.hasAttribute('download')) return;
        var href = a.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#' || /^javascript:/i.test(href)) return;
        var url = new URL(href, location.href);
        if (url.origin !== location.origin) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return; // لنگر همین صفحه
        e.preventDefault();
        e.stopPropagation();
        askLeave(url.href);
    }

    function offerRestore() {
        var saved = null;
        try { saved = JSON.parse(localStorage.getItem(storeKey()) || 'null'); } catch (e) { }
        if (!saved || !saved.state) return;
        if (!saved.ts || nowMs() - saved.ts > MAX_AGE) { clearLocal(); return; }
        if (JSON.stringify(saved.state) === baseline) { clearLocal(); return; } // همان چیزی که الان روی فرم است
        askingRestore = true;
        var when = (w.TimeSync && TimeSync.formatJalaliTime) ? TimeSync.formatJalaliTime(saved.ts) : '';
        dialog('یک نسخهٔ ذخیره‌نشده از <b>' + esc(saved.label || label()) + '</b>' + (when ? ' (' + when + ')' : '') +
            ' پیدا شد — احتمالا صفحه قبلا بدون ذخیره بسته شده است.<br>بازیابی شود؟', [
            {
                text: 'بازیابی', cls: 'btn-primary', fn: function (d) {
                    d.close();
                    askingRestore = false;
                    try { cfg.restore(saved.state); } catch (e) { }
                    // مبنا همان نسخهٔ سرور می‌ماند → فرم «تغییر ذخیره‌نشده» دارد تا کاربر ذخیره کند
                    writeLocal();
                    if (w.showToast) showToast('نسخهٔ ذخیره‌نشده بازیابی شد؛ برای ثبت، «ذخیره» را بزنید.', 'success');
                }
            },
            { text: 'نادیده گرفتن', cls: 'btn-secondary', cancel: true, fn: function (d) { d.close(); askingRestore = false; clearLocal(); } }
        ]);
    }

    w.EditGuard = {
        init: function (c) {
            if (cfg) return;
            cfg = c;
            document.addEventListener('click', onLinkClick, true);
            w.addEventListener('beforeunload', function (e) {
                if (bypass || !isDirty()) return;
                writeLocal(); // آخرین وضعیت، همین لحظه
                e.preventDefault();
                e.returnValue = '';
                return '';
            });
            w.addEventListener('pagehide', function () { if (!bypass) writeLocal(); });
            document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden' && !bypass) writeLocal(); });
            timer = setInterval(function () { if (isReady && !bypass) writeLocal(); }, 3000);
        },
        ready: function () {
            if (!cfg) return;
            baseline = snap();
            isReady = true;
            offerRestore();
        },
        saved: function () {
            if (!cfg) return;
            baseline = snap();
            clearLocal();
        },
        // بعد از ذخیرهٔ موفق، رفتن به صفحهٔ دیگر بدون پرسش
        go: function (url) { if (cfg) clearLocal(); go(url); },
        leave: function (url) { if (isDirty()) askLeave(url); else go(url); },
        isDirty: isDirty
    };
})(window);
