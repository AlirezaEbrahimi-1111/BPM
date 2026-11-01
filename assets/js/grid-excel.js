/**
 * grid-excel.js — خروجی اکسل از جدول‌های AG Grid.
 *
 * ردیف‌های «فیلتر و مرتب‌شدهٔ فعلی» جدول را با ستون‌هایی که مشخص می‌کنید به یک
 * فایل .xlsx می‌دهد (همان SheetJS که فایل‌های شایگان را می‌خواند). مبلغ‌ها به‌صورت
 * «عدد» با قالب هزارگان نوشته می‌شوند (نه متن) تا در اکسل جمع بخورند، و آخرِ جدول
 * یک ردیف «جمع» برای ستون‌های مبلغ می‌آید. برگه راست‌به‌چپ است.
 *
 * وابستگی: assets/js/cdn/xlsx.full.min.js (باید قبل از این فایل لود شود).
 *
 * استفاده:
 *   GridExcel.export({
 *     api: gridApi,
 *     filename: 'گزارش.xlsx',
 *     sheet: 'گزارش',
 *     cols: [
 *       { h: 'نام', v: function (row) { return row.name; }, w: 28 },
 *       { h: 'مبلغ', v: function (row) { return row.amount; }, money: true, w: 18 },
 *     ],
 *     totals: true,   // پیش‌فرض true؛ ردیف «جمع» برای ستون‌های money
 *   });
 */
var GridExcel = (function () {

    // aoa → worksheet؛ ستون‌های مبلغ را عددی با قالب #,##0 می‌کند و عرض ستون‌ها را می‌گذارد
    function toSheet(aoa, moneyCols, widths) {
        var ws = XLSX.utils.aoa_to_sheet(aoa);
        if (ws['!ref']) {
            var range = XLSX.utils.decode_range(ws['!ref']);
            for (var r = range.s.r; r <= range.e.r; r++) {
                moneyCols.forEach(function (c) {
                    var cell = ws[XLSX.utils.encode_cell({ r: r, c: c })];
                    if (cell && cell.t === 'n') cell.z = '#,##0';
                });
            }
        }
        ws['!cols'] = widths.map(function (w) { return { wch: w || 16 }; });
        return ws;
    }

    // سطرهای جدول به ترتیب و فیلترِ فعلی + ردیف عنوان + (اختیاری) ردیف جمع
    function buildAoa(api, cols, withTotals) {
        var aoa = [cols.map(function (c) { return c.h; })];
        var totals = cols.map(function () { return 0; });
        var hasMoney = cols.some(function (c) { return c.money; });
        if (api && api.forEachNodeAfterFilterAndSort) {
            api.forEachNodeAfterFilterAndSort(function (n) {
                aoa.push(cols.map(function (c, i) {
                    var v = c.v ? c.v(n.data) : '';
                    if (c.money) {
                        if (v === null || v === undefined || v === '' || isNaN(v)) return '';
                        v = Math.round(Number(v));
                        totals[i] += v;
                        return v;
                    }
                    return (v === null || v === undefined) ? '' : v;
                }));
            });
        }
        if (withTotals !== false && hasMoney) {
            aoa.push(cols.map(function (c, i) { return i === 0 ? 'جمع' : (c.money ? totals[i] : ''); }));
        }
        return aoa;
    }

    function exportGrid(opts) {
        opts = opts || {};
        if (typeof XLSX === 'undefined') {
            if (window.showToast) showToast('کتابخانهٔ اکسل بارگذاری نشده است.', 'error');
            return;
        }
        var cols = opts.cols || [];
        var aoa = buildAoa(opts.api, cols, opts.totals);
        if (aoa.length <= 1) {
            if (window.showToast) showToast('ردیفی برای خروجی گرفتن نیست.', 'warning');
            return;
        }
        var moneyCols = [];
        cols.forEach(function (c, i) { if (c.money) moneyCols.push(i); });
        var ws = toSheet(aoa, moneyCols, cols.map(function (c) { return c.w; }));
        var wb = XLSX.utils.book_new();
        wb.Workbook = { Views: [{ RTL: true }] }; // برگه راست‌به‌چپ
        XLSX.utils.book_append_sheet(wb, ws, opts.sheet || 'گزارش');
        XLSX.writeFile(wb, opts.filename || 'export.xlsx');
    }

    return { export: exportGrid };
})();
