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

    w.AgGridFa = { persianizePaging: persianizePaging, toFaDigits: toFaDigits, theme: theme, numericTextComparator: numericTextComparator, copyText: copyText };
})(window);
