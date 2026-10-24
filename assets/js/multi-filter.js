/*
 * multi-filter.js — فیلتر چندانتخابی (مثلا «وضعیت») برای فهرست‌ها (درخواست ۱۴۰۵/۰۸/۰۲).
 * ------------------------------------------------------------------
 * هم‌شکلِ MonthFilter (month-filter.js): تریگر + منوی تیک‌دار. انتخاب خالی = «همه».
 * گزینه‌ها می‌توانند گروه داشته باشند؛ معنای چند انتخاب:
 *   داخل یک گروه «یا» (پیش‌نویس یا تأییدشده) — بین گروه‌ها «و» (تأییدشده و تسویه‌نشده).
 *
 *   var f = MultiFilter.create({
 *     container: '#stFilter',
 *     allLabel: 'همه‌ی وضعیت‌ها',
 *     unit: 'وضعیت',                        // «۲ وضعیت انتخاب‌شده»
 *     options: [{ value: 'draft', label: 'پیش‌نویس', group: 'status' }, …],
 *     groups: { settle: 'تسویه' },          // عنوان گروه‌ها در منو (اختیاری)
 *     onChange: function (values) { … }
 *   });
 *   f.getSelected();                 // ['draft', 'settled']
 *   f.byGroup();                     // { status: ['draft'], settle: ['settled'] }
 *   f.matches(function (value) { return …آیا این ردیف با این گزینه می‌خواند… });
 *
 * فقط var/function (بدون let/const سطح‌بالا) و گارد ضدِ دوبار لود — مثل بقیهٔ فایل‌های مشترک.
 */
(function (w) {
    'use strict';
    if (w.MultiFilter) return;

    var FA = '۰۱۲۳۴۵۶۷۸۹';
    function fa(s) { return String(s).replace(/\d/g, function (d) { return FA[+d]; }); }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    function injectStyle() {
        if (document.getElementById('msf-style')) return;
        var s = document.createElement('style');
        s.id = 'msf-style';
        s.textContent = [
            '.msf-wrap{position:relative;display:inline-block;min-width:180px}',
            '.msf-trigger{display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;height:38px;',
            '  padding:.375rem .75rem;font-size:13px;font-family:inherit;color:var(--bs-body-color,#212529);',
            '  background:var(--bs-body-bg,#fff);border:1px solid var(--bs-border-color,#dee2e6);border-radius:6px;cursor:pointer;text-align:start}',
            '.msf-trigger:focus,.msf-wrap.msf-open .msf-trigger{outline:0;border-color:#8e57fe;box-shadow:0 0 0 .2rem rgba(142,87,254,.18)}',
            '.msf-trigger .msf-label{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
            '.msf-trigger .bi{color:#9ca3af;font-size:12px}',
            '.msf-menu{position:absolute;z-index:1060;top:calc(100% + 4px);right:0;min-width:100%;max-height:340px;overflow-y:auto;display:none;',
            '  background:var(--bs-body-bg,#fff);border:1px solid var(--bs-border-color,#dee2e6);border-radius:.5rem;box-shadow:0 6px 24px rgba(0,0,0,.13);padding:4px}',
            '.msf-wrap.msf-open .msf-menu{display:block}',
            '.msf-opt{display:flex;align-items:center;gap:8px;padding:7px 10px;font-size:13px;border-radius:6px;cursor:pointer;white-space:nowrap;color:var(--bs-body-color,#212529);user-select:none}',
            '.msf-opt:hover{background:rgba(142,87,254,.12)}',
            '.msf-opt input{margin:0;width:15px;height:15px;accent-color:#8e57fe;cursor:pointer;flex-shrink:0}',
            '.msf-opt.msf-all{font-weight:700;border-bottom:1px solid var(--bs-border-color,#eee);border-radius:6px 6px 0 0;margin-bottom:2px}',
            '.msf-grp{font-size:11px;font-weight:700;color:#9ca3af;padding:8px 10px 3px}',
            ':root[data-theme="dark"] .msf-trigger{background:var(--surface);border-color:var(--border-soft);color:var(--text-strong)}',
            ':root[data-theme="dark"] .msf-menu{background:var(--surface);border-color:var(--border-soft);box-shadow:0 6px 24px rgba(0,0,0,.5)}',
            ':root[data-theme="dark"] .msf-opt{color:var(--text-strong)}',
            ':root[data-theme="dark"] .msf-opt:hover{background:rgba(142,87,254,.18)}',
            ':root[data-theme="dark"] .msf-opt.msf-all{border-color:var(--border-soft)}'
        ].join('\n');
        document.head.appendChild(s);
    }

    function create(opts) {
        injectStyle();
        var host = typeof opts.container === 'string' ? document.querySelector(opts.container) : opts.container;
        if (!host) return null;
        var options = opts.options || [];
        var groups = opts.groups || {};
        var allLabel = opts.allLabel || 'همه';
        var unit = opts.unit || 'مورد';
        var selected = {};
        var onChange = opts.onChange || function () {};

        host.classList.add('msf-wrap');
        host.innerHTML = '<button type="button" class="msf-trigger" aria-haspopup="listbox"><span class="msf-label"></span><i class="bi bi-funnel"></i></button>' +
            '<div class="msf-menu" role="listbox" aria-multiselectable="true"></div>';
        var trigger = host.querySelector('.msf-trigger');
        var label = host.querySelector('.msf-label');
        var menu = host.querySelector('.msf-menu');

        function selectedValues() {
            return options.filter(function (o) { return selected[o.value]; }).map(function (o) { return o.value; });
        }
        function labelOf(v) {
            for (var i = 0; i < options.length; i++) if (options[i].value === v) return options[i].label;
            return v;
        }
        function renderLabel() {
            var vals = selectedValues();
            if (!vals.length) label.textContent = allLabel;
            else if (vals.length === 1) label.textContent = labelOf(vals[0]);
            else label.textContent = fa(vals.length) + ' ' + unit + ' انتخاب‌شده';
            trigger.title = vals.length ? vals.map(labelOf).join('، ') : allLabel;
        }
        function renderMenu() {
            var h = '<label class="msf-opt msf-all"><input type="checkbox" data-all="1"' + (selectedValues().length ? '' : ' checked') + '> ' + esc(allLabel) + '</label>';
            var lastGroup = null;
            options.forEach(function (o) {
                var g = o.group || '';
                if (g !== lastGroup && groups[g]) h += '<div class="msf-grp">' + esc(groups[g]) + '</div>';
                lastGroup = g;
                h += '<label class="msf-opt"><input type="checkbox" value="' + esc(o.value) + '"' + (selected[o.value] ? ' checked' : '') + '> ' + esc(o.label) + '</label>';
            });
            menu.innerHTML = h;
        }

        menu.addEventListener('change', function (e) {
            var cb = e.target;
            if (!cb || cb.type !== 'checkbox') return;
            if (cb.dataset.all) selected = {};
            else if (cb.checked) selected[cb.value] = true;
            else delete selected[cb.value];
            renderMenu();
            renderLabel();
            onChange(selectedValues());
        });
        trigger.addEventListener('click', function () { host.classList.toggle('msf-open'); });
        document.addEventListener('click', function (e) { if (!host.contains(e.target)) host.classList.remove('msf-open'); });
        host.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { host.classList.remove('msf-open'); trigger.focus(); }
        });

        var api = {
            getSelected: selectedValues,
            /* { گروه: [مقدارها] } — فقط گروه‌هایی که چیزی از آن‌ها انتخاب شده */
            byGroup: function () {
                var out = {};
                options.forEach(function (o) {
                    if (!selected[o.value]) return;
                    var g = o.group || '';
                    (out[g] = out[g] || []).push(o.value);
                });
                return out;
            },
            /* ردیف می‌خواند؟ test(value) = آیا ردیف با این یک گزینه جور است. داخل گروه «یا»، بین گروه‌ها «و».
               انتخاب خالی = همه (فیلتر پیش‌فرض صفحه را خود صفحه اعمال می‌کند). */
            matches: function (test) {
                var bg = api.byGroup();
                for (var g in bg) {
                    if (!bg[g].some(test)) return false;
                }
                return true;
            },
            setSelected: function (vals) {
                selected = {};
                (vals || []).forEach(function (v) { selected[v] = true; });
                renderMenu();
                renderLabel();
            }
        };
        renderMenu();
        renderLabel();
        return api;
    }

    w.MultiFilter = { create: create };
})(window);
