/*
 * ag-grid-fa.js — فارسی‌سازیِ نوارِ صفحه‌بندیِ AG Grid
 * ------------------------------------------------------------------
 * AG Grid Community متنِ نوارِ پایین را انگلیسی رندر می‌کند
 * ("Page 1 of 1"، "1 to 2 of 2"). این تابع همان کاری را می‌کند که
 * صفحهٔ tasks.php به‌صورت inline انجام می‌داد؛ حالا یک‌جا جمع شده تا
 * همهٔ گریدهایِ پروژهٔ فاکتور دقیقاً مثلِ tasks.php شوند.
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
        // AG Grid بعد از رویدادِ pagination چند تیک بعد DOM را می‌سازد؛
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

            // span هایِ تنهایِ «از» که بیرون از پنلِ خلاصه‌اند حذف شوند
            scope.querySelectorAll('.ag-paging-panel > span, .ag-paging-panel > div:not(.ag-paging-row-summary-panel):not(.ag-paging-page-size):not(.ag-paging-button-wrapper):not(.ag-paging-page-summary-panel)').forEach(function (el) {
                if (el.textContent.trim() === 'از') el.remove();
            });

            // «از » به ابتدایِ خلاصهٔ ردیف‌ها اضافه شود  →  «از ۱ تا ۲ از ۲»
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
     * تمِ مشترکِ AG Grid — به‌جای کپیِ بلوکِ themeQuartz.withParams({...}) در هر صفحهٔ لیستی.
     * پایه: فونتِ Vazirmatn 13، هدرِ #f8f9fa، هاورِ rgba(142,87,254,.12) (استانداردِ هاورِ سایت).
     * overrides مقادیرِ خاصِ صفحه را جایگزین می‌کند.  استفاده:  theme: AgGridFa.theme()
     * توجه: agGrid فقط هنگامِ فراخوانی لازم است (نه هنگامِ لودِ این فایل).
     */
    function theme(overrides) {
        return agGrid.themeQuartz.withParams(Object.assign({
            fontFamily: "'Vazirmatn', sans-serif",
            fontSize: 13,
            headerBackgroundColor: '#f8f9fa',
            rowHoverColor: 'rgba(142, 87, 254, 0.12)'
        }, overrides || {}));
    }

    w.AgGridFa = { persianizePaging: persianizePaging, toFaDigits: toFaDigits, theme: theme };
})(window);
