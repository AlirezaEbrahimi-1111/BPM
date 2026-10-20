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

    w.IranId = { nationalCode: nationalCode, legalId: legalId, postalCode: postalCode, checkCustomer: checkCustomer, digits: digits };
})(window);
