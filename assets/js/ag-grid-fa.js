/*
 * ag-grid-fa.js — فارسی‌سازی نوار صفحه‌بندی AG Grid
 * ------------------------------------------------------------------
 * AG Grid Community متن نوار پایین را انگلیسی رندر می‌کند
 * ("Page 1 of 1"، "1 to 2 of 2"). این تابع همان کاری را می‌کند که
 * صفحهٔ tasks.php به‌صورت inline انجام می‌داد؛ حالا یک‌جا جمع شده تا
 * همهٔ گریدهای پروژهٔ فاکتور دقیقا مثل tasks.php شوند.
 *
 * استفاده:  onPaginationChanged: () => AgGridFa.persianizePaging()
 */
(function (w) {
    'use strict';

    var FA = '۰۱۲۳۴۵۶۷۸۹';

    function toFaDigits(s) {
        return String(s).replace(/\d/g, function (d) { return FA[d]; });
    }

    function persianizePaging(root) {
        var scope = root || document;
        // AG Grid بعد از رویداد pagination چند تیک بعد DOM را می‌سازد؛
        // مثل tasks.php کمی صبر می‌کنیم.
        setTimeout(function () {
            scope.querySelectorAll('.ag-paging-panel span, .ag-paging-panel button').forEach(function (el) {
                if (el.childElementCount === 0 && !el.classList.contains('injected-az')) {
                    el.textContent = el.textContent
                        .replace(/Page/g, 'صفحه')
                        .replace(/\bof\b/g, 'از')
                        .replace(/\bto\b/g, 'تا')
                        .replace(/\d+/g, toFaDigits);
                }
            });

            // span های تنهای «از» که بیرون از پنل خلاصه‌اند حذف شوند
            scope.querySelectorAll('.ag-paging-panel > span, .ag-paging-panel > div:not(.ag-paging-row-summary-panel):not(.ag-paging-page-size):not(.ag-paging-button-wrapper):not(.ag-paging-page-summary-panel)').forEach(function (el) {
                if (el.textContent.trim() === 'از') el.remove();
            });

            // «از » به ابتدای خلاصهٔ ردیف‌ها اضافه شود  →  «از ۱ تا ۲ از ۲»
            var summary = scope.querySelector('.ag-paging-row-summary-panel');
            if (summary) {
                summary.querySelectorAll('.injected-az').forEach(function (el) { el.remove(); });
                var az = document.createElement('span');
                az.textContent = 'از ';
                az.className = 'injected-az';
                summary.insertBefore(az, summary.firstChild);
            }
        }, 100);
    }

    /*
     * تم مشترک AG Grid — به‌جای کپی بلوک themeQuartz.withParams({...}) در هر صفحهٔ لیستی.
     * پایه: فونت Vazirmatn 13، هدر #f8f9fa، هاور rgba(142,87,254,.12) (استاندارد هاور سایت).
     * overrides مقادیر خاص صفحه را جایگزین می‌کند.  استفاده:  theme: AgGridFa.theme()
     * توجه: agGrid فقط هنگام فراخوانی لازم است (نه هنگام لود این فایل).
     */
    function theme(overrides) {
        return agGrid.themeQuartz.withParams(Object.assign({
            fontFamily: "'Vazirmatn', sans-serif",
            fontSize: 13,
            headerBackgroundColor: '#f8f9fa',
            rowHoverColor: 'rgba(142, 87, 254, 0.12)'
        }, overrides || {}));
    }

    /**
     * مقایسه‌گر عددیِ ستونی که مقدارش «متن» است ولی معنای عدد دارد (مثل شماره‌ی فاکتور
     * که با پیشوند تنظیمات ساخته می‌شود). AG Grid متن را حرف‌به‌حرف مرتب می‌کند و
     * «۱۰» را قبل از «۲» می‌گذاشت. این تابع عدد را بیرون می‌کشد (رقم فارسی/عربی هم)
     * و عددی مقایسه می‌کند؛ مقدار بی‌عدد (مثل پیش‌نویسِ بی‌شماره) همیشه آخر می‌ماند.
     * استفاده:  { field: 'number', comparator: AgGridFa.numericTextComparator }
     */
    function numericTextComparator(a, b, nodeA, nodeB, isDescending) {
        var n = function (v) {
            var d = String(v == null ? '' : v)
                .replace(/[۰-۹]/g, function (c) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(c); })
                .replace(/[٠-٩]/g, function (c) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(c); })
                .match(/\d+/g);
            return d ? parseInt(d.join(''), 10) : null;
        };
        var x = n(a), y = n(b);
        if (x === null && y === null) return 0;
        // بی‌عدد در هر دو جهت مرتب‌سازی آخر بماند (AG Grid در نزولی نتیجه را برعکس می‌کند)
        if (x === null) return isDescending ? -1 : 1;
        if (y === null) return isDescending ? 1 : -1;
        return x - y;
    }

    /**
     * کپی یک متن در کلیپ‌بورد + اعلام نتیجه با showToast (مثلا کلیک روی کد مؤدیان در جدول).
     * navigator.clipboard فقط در https (یا localhost) هست؛ وگرنه روش قدیمی execCommand.
     * سلولی که این را صدا می‌زند باید در onRowClicked جدول مستثنا شود تا ردیف باز نشود.
     */
    function copyText(text, okMsg) {
        var report = function (ok) {
            if (typeof w.showToast === 'function') {
                w.showToast(ok ? (okMsg || 'در کلیپ‌بورد کپی شد') : 'کپی نشد؛ متن را دستی انتخاب و کپی کنید', ok ? 'success' : 'error');
            }
        };
        var legacy = function () {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            document.body.removeChild(ta);
            return ok;
        };
        if (w.navigator.clipboard && w.isSecureContext) {
            w.navigator.clipboard.writeText(text).then(function () { report(true); }, function () { report(legacy()); });
        } else {
            report(legacy());
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
     * ماندگاری تنظیمات جدول (درخواست ۱۴۰۵/۰۷/۲۷ — «کل سیستم»)
     * -------------------------------------------------------------------
     * agGrid.createGrid یک‌بار این‌جا پیچیده می‌شود؛ پس همهٔ جدول‌های سیستم
     * (و هر جدول آینده) بدون تغییر در صفحه‌شان پوشش داده می‌شوند. این فایل از
     * header.php لود می‌شود و همهٔ صفحه‌های جدول‌دار header را دارند.
     *
     * ذخیره: localStorage، جدا برای هر کاربر + مسیر صفحه + id المنتِ جدول.
     * تکه‌ها جدا ذخیره می‌شوند و هر تکه فقط از «اقدام خود کاربر» به‌روز می‌شود
     * (event.source = ui…)، نه از کد صفحه — تا مثلا ستونی که صفحه روی موبایل
     * خودکار پنهان می‌کند یا سورتی که از ?sort= در URL می‌آید، ماندگار نشود:
     *   widths  ← تغییر اندازه      order  ← جابه‌جایی ستون     pinned ← چسباندن
     *   sort    ← کلیک سورت         filter ← فیلتر ستون          pageSize ← تعداد ردیف
     * بازیابی قبل از onGridReady خود صفحه اجرا می‌شود؛ پس منطق صفحه (پنهان‌سازی
     * واکنش‌گرا، سورت/فیلترِ آمده از URL) همچنان بعد از آن و برنده است.
     * دکمهٔ ↺ «بازنشانی جدول» در نوار صفحه‌بندی (یا گوشهٔ جدول) همه را پاک می‌کند.
     * استثنا: data-grid-persist="off" روی المنتِ جدول.
     * فقط var/function (بدون let/const سطح‌بالا) — مثل بقیهٔ فایل‌های مشترک.
     * ═══════════════════════════════════════════════════════════════════ */
    var PERSIST_PREFIX = 'bpmGrid:v1:';
    var gridSeq = 0;

    function persistUserId() {
        try {
            var u = JSON.parse(w.localStorage.getItem('user_info') || '{}');
            return (u && (u.id || u.user_id)) || 'anon';
        } catch (e) { return 'anon'; }
    }

    function persistKey(el) {
        var id = (el && el.id) || ('grid' + (++gridSeq));
        return PERSIST_PREFIX + 'u' + persistUserId() + ':' + w.location.pathname + ':' + id;
    }

    function loadState(key) {
        try { return JSON.parse(w.localStorage.getItem(key) || 'null') || {}; } catch (e) { return {}; }
    }

    function saveState(key, st) {
        try { w.localStorage.setItem(key, JSON.stringify(st)); } catch (e) { /* حافظه پر/غیرفعال — بی‌صدا */ }
    }

    function isUiSource(src) {
        return typeof src === 'string' && src.indexOf('ui') === 0;
    }

    // ساخت state قابل‌اعمال از تکه‌های ذخیره‌شده (فقط برای ستون‌هایی که الان هستند)
    function buildColumnState(api, st) {
        var cur = api.getColumnState() || [];
        var have = {};
        cur.forEach(function (c) { have[c.colId] = true; });
        var ids = cur.map(function (c) { return c.colId; });
        if (st.order) {
            // ترتیب ذخیره‌شده؛ ستون‌های تازه (که موقع ذخیره نبودند) سر جای پیش‌فرضشان
            var known = st.order.filter(function (id) { return have[id]; });
            var fresh = ids.filter(function (id) { return st.order.indexOf(id) < 0; });
            ids = known.concat(fresh);
        }
        return ids.map(function (id) {
            var s = { colId: id };
            if (st.widths && st.widths[id]) {
                s.width = st.widths[id].width;
                s.flex = st.widths[id].flex == null ? null : st.widths[id].flex;
            }
            if (st.pinned && Object.prototype.hasOwnProperty.call(st.pinned, id)) s.pinned = st.pinned[id];
            if (st.sort) {
                var so = st.sort[id];
                s.sort = so ? so.sort : null;
                s.sortIndex = so ? so.sortIndex : null;
            }
            return s;
        });
    }

    function restoreState(api, ctx) {
        var st = loadState(ctx.key);
        ctx.restoring = true;
        try {
            if (st.order || st.widths || st.pinned || st.sort) {
                api.applyColumnState({ state: buildColumnState(api, st), applyOrder: !!st.order });
            }
            if (st.filter) api.setFilterModel(st.filter);
            if (st.pageSize && api.getGridOption('pagination')) api.setGridOption('paginationPageSize', st.pageSize);
        } catch (e) { /* state ناسازگار — نادیده بگیر */ }
        ctx.restoring = false;
        ctx.restoredCols = (api.getColumnState() || []).map(function (c) { return c.colId; }).join('|');
    }

    function attachPersistence(api, el, ctx) {
        var update = function (fn) {
            if (ctx.restoring) return;
            var st = loadState(ctx.key);
            fn(st, api.getColumnState() || []);
            saveState(ctx.key, st);
        };

        api.addEventListener('columnResized', function (e) {
            if (!e.finished || !isUiSource(e.source)) return;
            update(function (st, cols) {
                st.widths = st.widths || {};
                cols.forEach(function (c) { st.widths[c.colId] = { width: c.width, flex: c.flex == null ? null : c.flex }; });
            });
        });
        api.addEventListener('columnMoved', function (e) {
            // حین کشیدن چندبار با finished=false می‌آید؛ فقط رهاکردن نهایی ذخیره شود
            if (!isUiSource(e.source) || e.finished === false) return;
            update(function (st, cols) { st.order = cols.map(function (c) { return c.colId; }); });
        });
        api.addEventListener('columnPinned', function (e) {
            if (!isUiSource(e.source) && e.source !== 'contextMenu' && e.source !== 'columnMenu') return;
            update(function (st, cols) {
                st.pinned = {};
                cols.forEach(function (c) { st.pinned[c.colId] = c.pinned || null; });
            });
        });
        api.addEventListener('sortChanged', function (e) {
            if (!isUiSource(e.source)) return;
            update(function (st, cols) {
                st.sort = {};
                cols.forEach(function (c) { if (c.sort) st.sort[c.colId] = { sort: c.sort, sortIndex: c.sortIndex }; });
            });
        });
        api.addEventListener('filterChanged', function (e) {
            if (e.source === 'api' || e.source === 'quickFilter') return;
            update(function (st) {
                var m = api.getFilterModel();
                st.filter = (m && Object.keys(m).length) ? m : null;
            });
        });
        api.addEventListener('paginationChanged', function (e) {
            if (!e.newPageSize || ctx.restoring) return;
            update(function (st) { st.pageSize = api.paginationGetPageSize(); });
            injectResetButton(api, el, ctx);
        });
        // ستون‌ها بعدا عوض شدند (setGridOption('columnDefs')) و ستون تازه آمد → دوباره اعمال
        api.addEventListener('gridColumnsChanged', function () {
            var now = (api.getColumnState() || []).map(function (c) { return c.colId; }).join('|');
            if (now && now !== ctx.restoredCols) restoreState(api, ctx);
        });
    }

    function resetState(api, ctx) {
        try { w.localStorage.removeItem(ctx.key); } catch (e) { /* ignore */ }
        ctx.restoring = true;
        try {
            api.resetColumnState();
            api.setFilterModel(null);
            if (api.getGridOption('pagination') && ctx.initialPageSize) api.setGridOption('paginationPageSize', ctx.initialPageSize);
        } catch (e) { /* ignore */ }
        ctx.restoring = false;
        // رویداد paginationChangedِ همین تغییر ناهمگام می‌رسد و pageSize پیش‌فرض را دوباره
        // می‌نوشت → بعد از آن هم پاک شود
        setTimeout(function () { try { w.localStorage.removeItem(ctx.key); } catch (e) { /* ignore */ } }, 120);
        // منطق واکنش‌گرای صفحه (پنهان‌سازی ستون روی صفحهٔ کوچک) دوباره اجرا شود
        try { w.dispatchEvent(new Event('resize')); } catch (e) { /* ignore */ }
        if (typeof w.showToast === 'function') w.showToast('تنظیمات جدول به حالت پیش‌فرض برگشت', 'success');
    }

    function ensureResetStyle() {
        if (document.getElementById('agfa-reset-style')) return;
        var s = document.createElement('style');
        s.id = 'agfa-reset-style';
        s.textContent =
            '.agfa-reset{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;' +
            'border:1px solid var(--border-soft,#e2e2e2);border-radius:8px;background:var(--surface,#fff);' +
            'color:var(--text-muted,#6b7490);cursor:pointer;font-size:14px;line-height:1;padding:0;margin-inline-end:auto;' +
            'opacity:.75;transition:background .12s,opacity .12s}' +
            '.agfa-reset:hover{opacity:1;background:rgba(142,87,254,.12);color:#8e57fe}' +
            ':root[data-theme="dark"] .agfa-reset:hover{background:rgba(142,87,254,.18)}' +
            '.agfa-reset.floating{position:absolute;top:6px;left:6px;z-index:5;width:22px;height:22px;font-size:12px}';
        document.head.appendChild(s);
    }

    function injectResetButton(api, el, ctx) {
        if (!el || el.querySelector('.agfa-reset')) return;
        ensureResetStyle();
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'agfa-reset';
        b.title = 'بازنشانی جدول (اندازه، ترتیب، سورت و فیلتر ستون‌ها به حالت پیش‌فرض)';
        b.setAttribute('aria-label', 'بازنشانی جدول');
        b.textContent = '↺';
        b.addEventListener('click', function (ev) {
            ev.stopPropagation();
            resetState(api, ctx);
        });
        var panel = el.querySelector('.ag-paging-panel');
        if (panel) {
            panel.insertBefore(b, panel.firstChild);
        } else {
            b.classList.add('floating');
            if (w.getComputedStyle(el).position === 'static') el.style.position = 'relative';
            el.appendChild(b);
        }
    }

    function hookCreateGrid() {
        var ag = w.agGrid;
        if (!ag || typeof ag.createGrid !== 'function' || ag.__bpmPersist) return !!(ag && ag.__bpmPersist);
        var orig = ag.createGrid;
        var wrapped = function (el, options, params) {
            if (!el || !options || (el.getAttribute && el.getAttribute('data-grid-persist') === 'off')) {
                return orig.call(this, el, options, params);
            }
            var ctx = { key: persistKey(el), restoring: false, restoredCols: '', initialPageSize: options.paginationPageSize };
            var pageReady = options.onGridReady;
            // بازیابی قبل از onGridReady صفحه (تا منطق صفحه بعد از آن اجرا و برنده شود)
            options.onGridReady = function (p) {
                try { restoreState(p.api, ctx); } catch (e) { /* ignore */ }
                if (typeof pageReady === 'function') return pageReady.apply(this, arguments);
            };
            var api = orig.call(this, el, options, params);
            try {
                if (!ctx.restoredCols) restoreState(api, ctx); // اگر gridReady زودتر/دیرتر بود
                attachPersistence(api, el, ctx);
                injectResetButton(api, el, ctx);
            } catch (e) { /* ماندگاری هرگز نباید جدول را خراب کند */ }
            return api;
        };
        try {
            // در این build، createGrid یک getter بدون setter است (configurable) → تخصیص
            // ساده بی‌صدا نادیده گرفته می‌شود؛ defineProperty لازم است
            Object.defineProperty(ag, 'createGrid', { value: wrapped, configurable: true, enumerable: true, writable: true });
        } catch (e) {
            try { ag.createGrid = wrapped; } catch (e2) { return false; }
        }
        if (ag.createGrid !== wrapped) return false; // شیء فقط‌خواندنی
        ag.__bpmPersist = true;
        return true;
    }

    // ag-grid معمولا در <head> لود شده (قبل از این فایل)؛ اگر نه، قبل از ساخت جدول‌ها
    if (!hookCreateGrid()) {
        document.addEventListener('DOMContentLoaded', hookCreateGrid);
    }

    w.AgGridFa = { persianizePaging: persianizePaging, toFaDigits: toFaDigits, theme: theme, numericTextComparator: numericTextComparator, copyText: copyText };
})(window);
