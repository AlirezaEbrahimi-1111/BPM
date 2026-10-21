/*
 * ucrm-common.js — کمکی‌های مشترک صفحه‌های CRM جدید (حسابداری غیررسمی):
 * pages/ucrm-customers.php و pages/ucrm-customer.php.
 * API: crm-service زیر /crm/api/ucrm (crm-service/ucrm_customers.go).
 * فقط var/function (بدون let/const سطح‌بالا) و گارد ضدِ دوبار لود.
 */
(function (w) {
    'use strict';
    if (w.UCRM) return;

    var BASE = '/crm/api/ucrm';

    function tok() { return localStorage.getItem('auth_token'); }

    function e(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fa(s) { return w.faDigits ? w.faDigits(s) : String(s == null ? '' : s); }

    function toEn(s) {
        return String(s == null ? '' : s)
            .replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
            .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); });
    }

    function get(path) {
        return fetch(BASE + path, { headers: { 'Authorization': 'Bearer ' + tok() } }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (d) {
                if (!r.ok) throw new Error(d.message || ('خطای ' + r.status));
                return d;
            });
        });
    }

    function send(method, path, body) {
        return fetch(BASE + path, {
            method: method,
            headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + tok() },
            body: JSON.stringify(body || {})
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (d) {
                if (!r.ok || d.success === false) throw new Error(d.message || ('خطای ' + r.status));
                return d;
            });
        });
    }

    var TYPE_LABEL = { legal: 'حقوقی', individual: 'حقیقی' };
    var STATUS_LABEL = {
        pending_manager: 'در انتظار مدیر',
        pending_accounting: 'در انتظار حسابداری',
        rejected: 'رد شده',
        active: 'فعال'
    };

    function typeBadge(t) {
        return '<span class="uc-badge ' + (t === 'individual' ? 'individual' : 'legal') + '">' + (TYPE_LABEL[t] || '') + '</span>';
    }

    function statusBadge(c) {
        var label = STATUS_LABEL[c.status] || '';
        var tip = '';
        if (c.status === 'rejected' && c.reject_reason) {
            label = 'رد شده توسط ' + (c.rejected_stage === 'accounting' ? 'حسابداری' : 'مدیر');
            tip = ' title="دلیل: ' + e(c.reject_reason) + '"';
        }
        return '<span class="uc-badge st-' + e(c.status) + '"' + tip + '>' + label + '</span>';
    }

    function money(n) { return fa(Math.round(n || 0).toLocaleString('en-US')); }

    // درصد تحقق تارگت ماه جاری (یا null وقتی تارگت/فروش هنوز تعریف نشده)
    function targetPct(c) {
        if (!c || c.target_amount == null || !(c.target_amount > 0) || c.sales_amount == null) return null;
        return Math.round(c.sales_amount * 100 / c.target_amount);
    }

    function targetBadge(c, set) {
        var p = targetPct(c);
        // تارگت تعیین شده ولی فروش ماه هنوز از فاکتورهای CRM نمی‌آید → فقط خود تارگت (خاکستری)
        if (p == null && c && c.target_amount > 0) {
            return '<span class="uc-target gray" title="تارگت ماهانه؛ فروش این ماه هنوز ثبت نمی‌شود">' + money(c.target_amount) + '</span>';
        }
        if (p == null) return '<span class="text-muted small">تعیین نشده</span>';
        var red = (set && set.target_red_below) || 80, green = (set && set.target_green_from) || 100;
        var cls = p < red ? 'red' : (p >= green ? 'green' : 'orange');
        return '<span class="uc-target ' + cls + '" title="' + fa(p) + '٪ تارگت ماه (تارگت: ' + money(c.target_amount) + ')">' + money(c.sales_amount) + '</span>';
    }

    // «امروز»، «دیروز»، «۳ روز پیش»، «۳ هفته پیش»، «۴ ماه پیش»، «۲ سال پیش» — با روزِ سرور (نه ساعت دستگاه)
    function lastFollowup(v) {
        if (!v) return '<span class="text-muted small">بدون پیگیری</span>';
        var d = w.TimeSync ? -TimeSync.daysFromToday(v) : NaN;
        var full = w.TimeSync ? TimeSync.formatJalali(v) : fa(v);
        var txt;
        if (isNaN(d)) txt = full;
        else if (d <= 0) txt = 'امروز';
        else if (d === 1) txt = 'دیروز';
        else if (d < 7) txt = fa(d) + ' روز پیش';
        else if (d < 30) txt = fa(Math.floor(d / 7)) + ' هفته پیش';
        else if (d < 365) txt = fa(Math.floor(d / 30)) + ' ماه پیش';
        else txt = fa(Math.floor(d / 365)) + ' سال پیش';
        return '<span title="' + e(full) + '">' + txt + '</span>';
    }

    function phonesCell(list) {
        list = list || [];
        if (!list.length) return '<span class="text-muted small">ثبت نشده</span>';
        var more = list.length > 1
            ? ' <span class="uc-more" title="' + e(list.slice(1).map(fa).join('، ')) + '">+' + fa(list.length - 1) + '</span>'
            : '';
        return '<span class="num-ltr">' + fa(list[0]) + '</span>' + more;
    }

    function fullAddress(c) {
        return [c.province, c.city && c.city !== c.province ? c.city : '', c.address].filter(Boolean).join('، ');
    }

    // فیلد مبلغ (تومان): هنگام تایپ رقم فارسی + جداکنندهٔ هزارگان. میان‌بر «*» = سه صفر
    // از assets/js/money-shortcut.js می‌آید (صفت data-money).
    function bindMoney(el) {
        if (!el || el.dataset.ucMoney) return;
        el.dataset.ucMoney = '1';
        el.setAttribute('data-money', '');
        el.setAttribute('inputmode', 'numeric');
        el.addEventListener('input', function () {
            var n = moneyVal(el);
            var v = n ? money(n) : '';
            if (el.value !== v) el.value = v;
        });
    }

    function moneyVal(el) {
        var n = parseInt(toEn(el && el.value).replace(/[^\d]/g, ''), 10);
        return isNaN(n) ? 0 : n;
    }

    function setMoney(el, n) { el.value = n > 0 ? money(n) : ''; }

    w.UCRM = {
        bindMoney: bindMoney, moneyVal: moneyVal, setMoney: setMoney,
        tok: tok, get: get, send: send, toEn: toEn, money: money,
        TYPE_LABEL: TYPE_LABEL, STATUS_LABEL: STATUS_LABEL,
        typeBadge: typeBadge, statusBadge: statusBadge, targetPct: targetPct, targetBadge: targetBadge,
        lastFollowup: lastFollowup, phonesCell: phonesCell, fullAddress: fullAddress,
        myName: ''
    };
})(window);
