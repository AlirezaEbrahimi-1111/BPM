/*
 * month-filter.js — فیلتر چندانتخابیِ «ماه» (شمسی) برای فهرست‌ها.
 * ------------------------------------------------------------------
 * یک تریگر هم‌شکلِ SelectBox پروژه + منوی تیک‌دار. کلیدِ هر ماه 'YYYY-MM' شمسی است
 * (مثلا '1405-07' = مهر ۱۴۰۵). انتخابِ خالی = «همه‌ی ماه‌ها».
 *
 *   var mf = MonthFilter.create({ container: '#monthFilter', onChange: function (keys) { … } });
 *   mf.setMonths(['1405-07', '1405-06'], ['1405-07']);   // فهرست ماه‌ها + انتخاب اولیه
 *   mf.getSelected();                                     // ['1405-07']
 *   MonthFilter.keyOf('2026-10-19')                       // '1405-07'  (تاریخ میلادی → کلید شمسی)
 *   MonthFilter.currentKey()                              // ماهِ جاریِ سرور (TimeSync)، نه ساعت دستگاه
 *
 * فقط var/function (بدون let/const سطح‌بالا) و گارد ضدِ دوبار لود — مثل بقیهٔ فایل‌های مشترک.
 */
(function (w) {
    'use strict';
    if (w.MonthFilter) return;

    var FA = '۰۱۲۳۴۵۶۷۸۹';
    var MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    function fa(s) { return String(s).replace(/\d/g, function (d) { return FA[+d]; }); }
    function pad2(n) { return (n < 10 ? '0' : '') + n; }

    function g2j(gy, gm, gd) {
        if (w.TimeSync && typeof w.TimeSync.gregorianToJalali === 'function') return w.TimeSync.gregorianToJalali(gy, gm, gd);
        return null;
    }

    /* 'YYYY-MM-DD…' میلادی → 'YYYY-MM' شمسی (یا '' اگر تاریخ نامعتبر بود) */
    function keyOf(dateStr) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(dateStr || ''));
        if (!m) return '';
        var j = g2j(+m[1], +m[2], +m[3]);
        return j ? j[0] + '-' + pad2(j[1]) : '';
    }

    function currentKey() {
        var j = w.TimeSync && w.TimeSync.serverJalali ? w.TimeSync.serverJalali() : null;
        return j ? j[0] + '-' + pad2(j[1]) : '';
    }

    function labelOf(key) {
        var p = String(key).split('-');
        return (MONTHS[(+p[1]) - 1] || '') + ' ' + fa(p[0]);
    }

    function injectStyle() {
        if (document.getElementById('mf-style')) return;
        var s = document.createElement('style');
        s.id = 'mf-style';
        s.textContent = [
            '.mf-wrap{position:relative;display:inline-block;min-width:170px}',
            '.mf-trigger{display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;height:38px;',
            '  padding:.375rem .75rem;font-size:13px;font-family:inherit;color:var(--bs-body-color,#212529);',
            '  background:var(--bs-body-bg,#fff);border:1px solid var(--bs-border-color,#dee2e6);border-radius:6px;cursor:pointer;text-align:start}',
            '.mf-trigger:focus,.mf-wrap.mf-open .mf-trigger{outline:0;border-color:#8e57fe;box-shadow:0 0 0 .2rem rgba(142,87,254,.18)}',
            '.mf-trigger .mf-label{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
            '.mf-trigger .bi{color:#9ca3af;font-size:12px}',
            '.mf-menu{position:absolute;z-index:1060;top:calc(100% + 4px);right:0;min-width:100%;max-height:300px;overflow-y:auto;display:none;',
            '  background:var(--bs-body-bg,#fff);border:1px solid var(--bs-border-color,#dee2e6);border-radius:.5rem;box-shadow:0 6px 24px rgba(0,0,0,.13);padding:4px}',
            '.mf-wrap.mf-open .mf-menu{display:block}',
            '.mf-opt{display:flex;align-items:center;gap:8px;padding:7px 10px;font-size:13px;border-radius:6px;cursor:pointer;white-space:nowrap;color:var(--bs-body-color,#212529);user-select:none}',
            '.mf-opt:hover{background:rgba(142,87,254,.12)}',
            '.mf-opt input{margin:0;width:15px;height:15px;accent-color:#8e57fe;cursor:pointer;flex-shrink:0}',
            '.mf-opt.mf-all{font-weight:700;border-bottom:1px solid var(--bs-border-color,#eee);border-radius:6px 6px 0 0;margin-bottom:2px}',
            ':root[data-theme="dark"] .mf-trigger{background:var(--surface);border-color:var(--border-soft);color:var(--text-strong)}',
            ':root[data-theme="dark"] .mf-menu{background:var(--surface);border-color:var(--border-soft);box-shadow:0 6px 24px rgba(0,0,0,.5)}',
            ':root[data-theme="dark"] .mf-opt{color:var(--text-strong)}',
            ':root[data-theme="dark"] .mf-opt:hover{background:rgba(142,87,254,.18)}',
            ':root[data-theme="dark"] .mf-opt.mf-all{border-color:var(--border-soft)}'
        ].join('\n');
        document.head.appendChild(s);
    }

    function create(opts) {
        injectStyle();
        var host = typeof opts.container === 'string' ? document.querySelector(opts.container) : opts.container;
        if (!host) return null;
        var months = [];          // کلیدها، جدید → قدیم
        var selected = {};        // key → true
        var onChange = opts.onChange || function () {};

        host.classList.add('mf-wrap');
        host.innerHTML = '<button type="button" class="mf-trigger" aria-haspopup="listbox"><span class="mf-label"></span><i class="bi bi-calendar3"></i></button><div class="mf-menu" role="listbox" aria-multiselectable="true"></div>';
        var trigger = host.querySelector('.mf-trigger');
        var label = host.querySelector('.mf-label');
        var menu = host.querySelector('.mf-menu');

        function selectedKeys() {
            return months.filter(function (k) { return selected[k]; });
        }

        function renderLabel() {
            var keys = selectedKeys();
            if (!keys.length || keys.length === months.length) label.textContent = 'همه‌ی ماه‌ها';
            else if (keys.length === 1) label.textContent = labelOf(keys[0]);
            else label.textContent = fa(keys.length) + ' ماه انتخاب‌شده';
            trigger.title = keys.length ? keys.map(labelOf).join('، ') : 'همه‌ی ماه‌ها';
        }

        function renderMenu() {
            var keys = selectedKeys();
            var all = !keys.length;
            var h = '<label class="mf-opt mf-all"><input type="checkbox" data-all="1"' + (all ? ' checked' : '') + '> همه‌ی ماه‌ها</label>';
            months.forEach(function (k) {
                h += '<label class="mf-opt"><input type="checkbox" value="' + k + '"' + (selected[k] ? ' checked' : '') + '> ' + labelOf(k) + '</label>';
            });
            menu.innerHTML = h;
        }

        function emit() {
            renderLabel();
            onChange(selectedKeys());
        }

        menu.addEventListener('change', function (e) {
            var cb = e.target;
            if (!cb || cb.type !== 'checkbox') return;
            if (cb.dataset.all) {
                selected = {};               // «همه» = بدون محدودیت
            } else {
                if (cb.checked) selected[cb.value] = true; else delete selected[cb.value];
            }
            renderMenu();
            emit();
        });
        trigger.addEventListener('click', function () {
            host.classList.toggle('mf-open');
        });
        document.addEventListener('click', function (e) {
            if (!host.contains(e.target)) host.classList.remove('mf-open');
        });
        host.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { host.classList.remove('mf-open'); trigger.focus(); }
        });

        var api = {
            /* فهرست ماه‌ها را عوض کن؛ initial (اختیاری) = انتخاب جدید. ماه‌های انتخاب‌شده‌ای که
               در فهرست تازه نیستند حذف می‌شوند. onChange صدا زده نمی‌شود (صفحه خودش فیلتر کند). */
            setMonths: function (keys, initial) {
                var seen = {};
                months = (keys || []).filter(function (k) {
                    if (!k || seen[k]) return false;
                    seen[k] = true;
                    return true;
                }).sort().reverse();
                var next = {};
                (initial || selectedKeys()).forEach(function (k) { if (seen[k]) next[k] = true; });
                selected = next;
                renderMenu();
                renderLabel();
            },
            getSelected: selectedKeys,
            /* آیا تاریخ میلادی داده‌شده در ماه‌های انتخاب‌شده است؟ (انتخاب خالی = همه) */
            matches: function (dateStr) {
                var keys = selectedKeys();
                return !keys.length || !!selected[keyOf(dateStr)];
            }
        };
        renderLabel();
        return api;
    }

    w.MonthFilter = { create: create, keyOf: keyOf, currentKey: currentKey, labelOf: labelOf };
})(window);
