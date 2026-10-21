/*
 * iran-id-validate.js — اعتبارسنجی کد ملی، شناسهٔ ملی شرکت و کد پستی (ایران).
 * ------------------------------------------------------------------
 * هر تابع یک متن خطای فارسی برمی‌گرداند، یا '' اگر مقدار درست بود. رقم فارسی/عربی
 * و فاصله/خط‌تیره پذیرفته می‌شود (قبل از بررسی حذف/تبدیل می‌شوند).
 *
 *   IranId.nationalCode('0499370899')   // کد ملی اشخاص حقیقی — ۱۰ رقم + رقم کنترل
 *   IranId.legalId('10380284790')       // شناسهٔ ملی اشخاص حقوقی — ۱۱ رقم + رقم کنترل
 *   IranId.postalCode('1193653471')     // کد پستی — ۱۰ رقم با الگوی پست ایران
 *   IranId.checkCustomer({ type: 'legal'|'individual', national_id, postal_code })  // [خطاها]
 *
 * فقط var/function (بدون let/const سطح‌بالا) و گارد ضدِ دوبار لود — مثل بقیهٔ فایل‌های مشترک.
 */
(function (w) {
    'use strict';
    if (w.IranId) return;

    function digits(v) {
        return String(v == null ? '' : v)
            .replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
            .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); })
            .replace(/[\s\-‌]/g, '');
    }

    /* کد ملی: ۱۰ رقم؛ رقم آخر = کنترل. s = Σ d[i]·(10−i) برای i=0..8، r = s mod 11؛
       رقم کنترل = r اگر r<2، وگرنه 11−r. همهٔ رقم‌های یکسان (۱۱۱۱۱۱۱۱۱۱) نامعتبرند. */
    function nationalCode(v) {
        var s = digits(v);
        if (!/^\d{10}$/.test(s)) return 'کد ملی باید ۱۰ رقم باشد.';
        if (/^(\d)\1{9}$/.test(s)) return 'کد ملی معتبر نیست.';
        var sum = 0;
        for (var i = 0; i < 9; i++) sum += (+s[i]) * (10 - i);
        var r = sum % 11;
        var check = r < 2 ? r : 11 - r;
        return check === +s[9] ? '' : 'کد ملی معتبر نیست (رقم کنترل نمی‌خواند).';
    }

    /* شناسهٔ ملی اشخاص حقوقی: ۱۱ رقم؛ رقم آخر = کنترل. k = d[9]+2؛
       s = Σ (d[i]+k)·c[i] با c = 29,27,23,19,17 (تکرار) برای i=0..9؛ r = s mod 11 (10→0). */
    function legalId(v) {
        var s = digits(v);
        if (!/^\d{11}$/.test(s)) return 'شناسهٔ ملی شرکت باید ۱۱ رقم باشد.';
        if (/^(\d)\1{10}$/.test(s)) return 'شناسهٔ ملی شرکت معتبر نیست.';
        var c = [29, 27, 23, 19, 17];
        var k = (+s[9]) + 2;
        var sum = 0;
        for (var i = 0; i < 10; i++) sum += ((+s[i]) + k) * c[i % 5];
        var r = sum % 11;
        if (r === 10) r = 0;
        return r === +s[10] ? '' : 'شناسهٔ ملی شرکت معتبر نیست (رقم کنترل نمی‌خواند).';
    }

    /* کد پستی: ۱۰ رقم؛ پنج رقم اول بدون ۰ و ۲، رقم پنجم ≠ ۵، چهار رقم یکسانِ پشت‌سرهم در
       ابتدا نه (الگوی رایج پست ایران — همان regex متداول اعتبارسنجی). */
    function postalCode(v) {
        var s = digits(v);
        if (!/^\d{10}$/.test(s)) return 'کد پستی باید ۱۰ رقم باشد.';
        if (!/^(?!(\d)\1{3})[13-9]{4}[1346-9][013-9]{5}$/.test(s)) return 'کد پستی معتبر نیست.';
        return '';
    }

    /* خطاهای فرم مشتری (فقط فیلدهای پرشده بررسی می‌شوند؛ الزامی‌بودن را خود فرم می‌سنجد) */
    function checkCustomer(c) {
        var errs = [];
        var nid = digits(c && c.national_id);
        if (nid) {
            var e = (c.type === 'individual') ? nationalCode(nid) : legalId(nid);
            if (e) errs.push(e);
        }
        var pc = digits(c && c.postal_code);
        if (pc) {
            var p = postalCode(pc);
            if (p) errs.push(p);
        }
        return errs;
    }

    /* ─────────────────────────────────────────────────────────────────
     * اعتبارسنجی «هنگام تایپ» (درخواست ۱۴۰۵/۰۷/۲۹) — نه فقط بعد از ذخیره.
     *   IranId.attachLive({ type: <select نوع>, national: <input>, postal: <input> })
     *   IranId.resetLive(root)   // پاک‌کردن پیام‌ها (مثلا هنگام باز شدن مودال)
     * هنگام تایپ: فقط رقم پذیرفته می‌شود (فارسی/انگلیسی)، طول به حداکثر محدود می‌شود
     * (کد ملی ۱۰ / شناسهٔ شرکت ۱۱ / کد پستی ۱۰)، و زیر فیلد پیام زنده می‌آید:
     * ناقص → «N رقم دیگر» (نارنجی)، کامل و نادرست → قرمز، کامل و درست → سبز.
     * بیرون آمدن از فیلدِ ناقص → قرمز. مقدارِ پرشده با کد (ویرایش) تا وقتی کاربر
     * دست نزده پیامی نمی‌گیرد. بررسی نهایی هنگام ذخیره سر جایش می‌ماند.
     * ───────────────────────────────────────────────────────────────── */
    var FA = '۰۱۲۳۴۵۶۷۸۹';
    function faNum(n) { return String(n).replace(/\d/g, function (d) { return FA[+d]; }); }

    function liveStyle() {
        if (document.getElementById('iid-style')) return;
        var s = document.createElement('style');
        s.id = 'iid-style';
        s.textContent =
            '.iid-fb{font-size:11.5px;margin-top:4px;min-height:0;line-height:1.5}' +
            '.iid-fb:empty{display:none}' +
            '.iid-fb.warn{color:#b45309}.iid-fb.err{color:#dc2626}.iid-fb.ok{color:#0f7a57}' +
            // آیکن تیک/ضربدر بوت‌استرپ در فیلد RTL روی رقم آخر می‌افتاد → فقط رنگ کادر بماند
            '[data-iid-bound].is-valid,[data-iid-bound].is-invalid{background-image:none!important;padding-left:.75rem!important;padding-right:.75rem!important}' +
            ':root[data-theme="dark"] .iid-fb.warn{color:#fbbf24}' +
            ':root[data-theme="dark"] .iid-fb.err{color:#f87171}' +
            ':root[data-theme="dark"] .iid-fb.ok{color:#34d399}';
        document.head.appendChild(s);
    }

    function fbOf(input) {
        var fb = input.nextElementSibling;
        if (!fb || !fb.classList.contains('iid-fb')) {
            fb = document.createElement('div');
            fb.className = 'iid-fb';
            fb.setAttribute('aria-live', 'polite');
            input.insertAdjacentElement('afterend', fb);
        }
        return fb;
    }

    function setFb(input, kind, msg) {
        var fb = fbOf(input);
        fb.className = 'iid-fb' + (kind ? ' ' + kind : '');
        fb.textContent = msg || '';
        input.classList.toggle('is-invalid', kind === 'err');
        input.classList.toggle('is-valid', kind === 'ok');
    }

    // مشخصات هر نوع فیلد: طول لازم + تابع بررسی + نام
    function specOf(field, typeVal) {
        if (field === 'postal') return { len: 10, check: postalCode, name: 'کد پستی' };
        return typeVal === 'individual'
            ? { len: 10, check: nationalCode, name: 'کد ملی' }
            : { len: 11, check: legalId, name: 'شناسهٔ ملی شرکت' };
    }

    function attachLive(opts) {
        liveStyle();
        var typeEl = opts.type || null;
        var typeVal = function () { return typeEl ? typeEl.value : 'legal'; };
        var fields = [];
        if (opts.national) fields.push({ el: opts.national, field: 'national' });
        if (opts.postal) fields.push({ el: opts.postal, field: 'postal' });

        function evaluate(f, final) {
            var el = f.el;
            var sp = specOf(f.field, typeVal());
            var d = digits(el.value);
            if (!d) { setFb(el, '', ''); return; }
            if (d.length < sp.len) {
                if (final) setFb(el, 'err', sp.name + ' باید ' + faNum(sp.len) + ' رقم باشد (' + faNum(d.length) + ' رقم وارد شده).');
                else setFb(el, 'warn', sp.name + ' ' + faNum(sp.len) + ' رقمی است — ' + faNum(sp.len - d.length) + ' رقم دیگر.');
                return;
            }
            var e = sp.check(d);
            if (e) setFb(el, 'err', e);
            else setFb(el, 'ok', '✓ ' + sp.name + ' معتبر است.');
        }

        fields.forEach(function (f) {
            var el = f.el;
            if (el.dataset.iidBound) return;
            el.dataset.iidBound = '1';
            el.addEventListener('input', function () {
                // فقط رقم (فارسی/عربی/انگلیسی) + سقف طول؛ اسکریپت رقمی که کاربر تایپ کرده حفظ می‌شود
                var v = el.value.replace(/[^0-9۰-۹٠-٩]/g, '');
                var max = specOf(f.field, typeVal()).len;
                if (f.field === 'national') max = 11; // تا وقتی نوع عوض نشده، ۱۱ رقم شرکت هم جا شود
                if (v.length > max) v = v.slice(0, max);
                if (v !== el.value) el.value = v;
                el.dataset.iidTouched = '1';
                evaluate(f, false);
            });
            el.addEventListener('blur', function () {
                if (el.dataset.iidTouched) evaluate(f, true);
            });
        });
        if (typeEl && !typeEl.dataset.iidBound) {
            typeEl.dataset.iidBound = '1';
            typeEl.addEventListener('change', function () {
                fields.forEach(function (f) {
                    if (f.field === 'national' && digits(f.el.value)) evaluate(f, true);
                });
            });
        }
    }

    function resetLive(root) {
        (root || document).querySelectorAll('.iid-fb').forEach(function (fb) { fb.textContent = ''; fb.className = 'iid-fb'; });
        (root || document).querySelectorAll('[data-iid-bound]').forEach(function (el) {
            el.classList.remove('is-invalid', 'is-valid');
            delete el.dataset.iidTouched;
        });
    }

    w.IranId = {
        nationalCode: nationalCode, legalId: legalId, postalCode: postalCode, checkCustomer: checkCustomer, digits: digits,
        attachLive: attachLive, resetLive: resetLive
    };
})(window);
