<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// CRM جدید — «گزارشات | پیش‌فاکتورهای باز و پیگیری‌ها» (بخش ۱۰ طرح CRM، طرح ۱۴۰۵/۰۸/۰۳).
// فعلا فقط ظاهر صفحه: فیلتر کارشناس + بازهٔ تاریخ (پیش‌فرض ماه جاری) + خروجی اکسل، چهار کارت
// (پیش‌فاکتورهای باز، مجموع مبلغ، معوق بیش از ۷ روز، نرخ تبدیل) و جدول خالی — پیش‌فاکتور CRM
// هنوز ساخته نشده؛ با ساختنش همین صفحه پر می‌شود. مبلغ به تومان (مثل بقیهٔ CRM).
// تعریف کارت‌ها (توافق ۱۴۰۵/۰۸/۰۳): معوق = پیش‌فاکتور باز با «تاریخ پیگیری بعدی» بیش از ۷ روز گذشته؛
// نرخ تبدیل = درصد پیش‌فاکتورهای همین بازه که به فاکتور/فروش تبدیل شده‌اند.
// مدیر و حسابداری همه را می‌بینند (با فیلتر کارشناس)؛ کارشناس فقط مال خودش.
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ucrm_access.php';
if (!ucrmAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}
$__ucrmRoles = ucrmRoles($db, (int) $user_id);
$__seesAll = $__ucrmRoles['manager'] || $__ucrmRoles['accounting'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیش‌فاکتورهای باز - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
    <script src="<?= asset('../../assets/js/search-persist.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/xlsx.full.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <script src="<?= asset('../../assets/js/persian-date-utils.js') ?>"></script>
    <script src="<?= asset('../../assets/js/persian-datepicker.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/ucrm.css') ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <!-- چیدمان و اندازهٔ کادر صفحه هم‌شکل pages/tasks.php (همهٔ صفحه‌های CRM) -->
    <div class="overview-container uc-top-gap">

        <div class="uc-card uc-pf-head">
            <h1><span class="uc-tg-ico"><i class="bi bi-graph-up-arrow"></i></span>گزارشات | پیش‌فاکتورهای باز و پیگیری‌ها</h1>
            <div class="spacer"></div>
            <div class="uc-pf-tools">
                <?php if ($__seesAll): ?>
                    <div class="uc-tg-f"><i class="bi bi-person"></i>
                        <select id="fRep" class="form-select form-select-sm" title="کارشناس">
                            <option value="">همهٔ کارشناسان</option>
                        </select>
                    </div>
                <?php endif; ?>
                <!-- بازهٔ تاریخ: یک دکمه + پنجرهٔ کوچک «از / تا» با میان‌برها -->
                <div class="uc-pf-range dropdown">
                    <button type="button" class="btn uc-pf-range-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <i class="bi bi-calendar3"></i><span id="rangeLbl">…</span><i class="bi bi-chevron-down small"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-3 uc-pf-range-menu">
                        <div class="uc-pf-quick">
                            <button type="button" class="btn btn-sm" data-q="month">ماه جاری</button>
                            <button type="button" class="btn btn-sm" data-q="last">ماه قبل</button>
                            <button type="button" class="btn btn-sm" data-q="7">۷ روز اخیر</button>
                            <button type="button" class="btn btn-sm" data-q="30">۳۰ روز اخیر</button>
                        </div>
                        <label class="form-label small mt-2 mb-1">از تاریخ</label>
                        <div class="persian-datepicker-wrapper">
                            <input type="text" class="persian-datepicker-input form-control form-control-sm" id="dFrom" readonly>
                            <div class="persian-datepicker">
                                <div class="datepicker-header"><button type="button" class="datepicker-nav" data-action="prev">►</button><span class="datepicker-current"></span><button type="button" class="datepicker-nav" data-action="next">◄</button></div>
                                <div class="datepicker-weekdays"><div class="datepicker-weekday">ش</div><div class="datepicker-weekday">ی</div><div class="datepicker-weekday">د</div><div class="datepicker-weekday">س</div><div class="datepicker-weekday">چ</div><div class="datepicker-weekday">پ</div><div class="datepicker-weekday">ج</div></div>
                                <div class="datepicker-days"></div><button type="button" class="datepicker-today-btn">امروز</button>
                            </div>
                        </div>
                        <label class="form-label small mt-2 mb-1">تا تاریخ</label>
                        <div class="persian-datepicker-wrapper">
                            <input type="text" class="persian-datepicker-input form-control form-control-sm" id="dTo" readonly>
                            <div class="persian-datepicker">
                                <div class="datepicker-header"><button type="button" class="datepicker-nav" data-action="prev">►</button><span class="datepicker-current"></span><button type="button" class="datepicker-nav" data-action="next">◄</button></div>
                                <div class="datepicker-weekdays"><div class="datepicker-weekday">ش</div><div class="datepicker-weekday">ی</div><div class="datepicker-weekday">د</div><div class="datepicker-weekday">س</div><div class="datepicker-weekday">چ</div><div class="datepicker-weekday">پ</div><div class="datepicker-weekday">ج</div></div>
                                <div class="datepicker-days"></div><button type="button" class="datepicker-today-btn">امروز</button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm w-100 mt-3" id="rangeApply">اعمال بازه</button>
                    </div>
                </div>
                <button type="button" class="btn btn-primary uc-pf-xl" id="btnExcel"><i class="bi bi-download ms-1"></i>خروجی اکسل</button>
            </div>
        </div>

        <div class="uc-pf-cards">
            <div class="uc-pf-card c-open"><div><div class="k">پیش‌فاکتورهای باز</div><div class="v" id="cOpen">۰</div></div><span class="ic"><i class="bi bi-file-earmark-text"></i></span></div>
            <div class="uc-pf-card c-sum"><div><div class="k">مجموع مبلغ</div><div class="v" id="cSum">۰ تومان</div></div><span class="ic"><i class="bi bi-cash-stack"></i></span></div>
            <div class="uc-pf-card c-late"><div><div class="k">معوق (بیش از ۷ روز)</div><div class="v" id="cLate">۰</div></div><span class="ic"><i class="bi bi-alarm"></i></span></div>
            <div class="uc-pf-card c-rate"><div><div class="k">نرخ تبدیل</div><div class="v" id="cRate">۰٪</div></div><span class="ic"><i class="bi bi-graph-up-arrow"></i></span></div>
        </div>

        <div class="uc-card uc-pf-list">
            <h2><i class="bi bi-file-earmark-text ms-1"></i>پیش‌فاکتورهای باز</h2>
            <div id="pfGrid" class="ag-theme-alpine inv-grid grid-fill" style="width:100%;"></div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../../assets/js/ucrm-common.js') ?>"></script>
    <script>
        const SEES_ALL = <?= $__seesAll ? 'true' : 'false' ?>;
        // وضعیت‌ها طبق طرح — معنای دقیق هر کدام با ساخت پیش‌فاکتور CRM مشخص می‌شود
        const PF_STATUS = {
            approved: ['ok', 'bi-check-lg', 'تأیید شده'],
            stopped: ['stop', 'bi-dash-circle-fill', 'متوقف شده'],
            not_started: ['wait', 'bi-hourglass-split', 'شروع نشده'],
            overdue: ['late', 'bi-exclamation-circle-fill', 'عقب افتاده'],
        };
        let gridApi = null, rows = [], range = { from: '', to: '' };

        function statusCell(p) {
            const s = PF_STATUS[p.value];
            return s ? `<span class="uc-st ${s[0]}"><i class="bi ${s[1]}"></i>${s[2]}</span>` : '';
        }

        // منوی «⋮» هر ردیف (طرح): تکمیل / لغو / تغییر تاریخ پیگیری — با ساخت پیش‌فاکتور فعال می‌شود
        function actionCell() {
            return `<button class="ag-action-btn" style="color:#0f7a57" title="تکمیل" onclick="pfSoon()"><i class="bi bi-check-circle-fill"></i></button>
                <button class="ag-action-btn del" title="لغو" onclick="pfSoon()"><i class="bi bi-x-circle-fill"></i></button>
                <button class="ag-action-btn" style="color:#8e57fe" title="تغییر تاریخ پیگیری" onclick="pfSoon()"><i class="bi bi-calendar-event"></i></button>`;
        }

        function pfSoon() {
            showToast('عملیات پیش‌فاکتور با ساخت بخش پیش‌فاکتور CRM فعال می‌شود.', 'info');
        }

        const jd = v => v ? TimeSync.formatJalali(v) : '—';
        const colDefs = [
            { headerName: 'شمارهٔ پیش‌فاکتور', field: 'number', width: 140, cellRenderer: p => faDigits(p.value || '') },
            { headerName: 'نام مشتری', field: 'customer_name', flex: 1.4, minWidth: 130,
              cellRenderer: p => p.data.customer_id ? `<a class="uc-name-link" href="ucrm-customer.php?id=${p.data.customer_id}">${esc(p.value)}</a>` : esc(p.value) },
            { headerName: 'کارشناس فروش', field: 'rep_name', flex: 1, minWidth: 110, hide: !SEES_ALL },
            { headerName: 'مبلغ (تومان)', field: 'amount', width: 130, cellRenderer: p => UCRM.money(p.value) },
            { headerName: 'تاریخ صدور', field: 'issue_date', width: 105, cellRenderer: p => jd(p.value) },
            { headerName: 'پیگیری بعدی', field: 'next_followup', width: 115, cellRenderer: p => jd(p.value) },
            { headerName: 'وضعیت', field: 'status', width: 130, cellRenderer: statusCell },
            { headerName: 'عملیات', colId: 'actions', width: 80, sortable: false, filter: false, cellRenderer: AgGridFa.actionMenuCell(actionCell) },
        ];

        function pad2(n) { return String(n).padStart(2, '0'); }

        // بازهٔ تاریخ — همه با «امروزِ» سرور (TimeSync)، نه ساعت دستگاه
        function jMonthRange(jy, jm) {
            const days = jm <= 6 ? 31 : (jm <= 11 ? 30 : (isValidPersianDate(jy + '/12/30') ? 30 : 29));
            return { from: convertToGregorian(jy + '/' + pad2(jm) + '/01'), to: convertToGregorian(jy + '/' + pad2(jm) + '/' + pad2(days)) };
        }

        function addDays(g, n) {
            const d = new Date(g + 'T12:00:00Z');
            d.setUTCDate(d.getUTCDate() + n);
            return d.toISOString().slice(0, 10);
        }

        function quickRange(q) {
            const [jy, jm] = TimeSync.serverJalali();
            const today = TimeSync.serverToday();
            if (q === 'month') return jMonthRange(jy, jm);
            if (q === 'last') return jm === 1 ? jMonthRange(jy - 1, 12) : jMonthRange(jy, jm - 1);
            return { from: addDays(today, -(+q - 1)), to: today };
        }

        function setRange(r) {
            range = r;
            SearchPersist.setValue('ucrm-proformas', 'range', JSON.stringify(r)); // بازهٔ تاریخ ماندگار
            document.getElementById('rangeLbl').textContent = faDigits(convertToJalali(r.from) + ' - ' + convertToJalali(r.to));
            [['dFrom', r.from], ['dTo', r.to]].forEach(([id, g]) => {
                const el = document.getElementById(id);
                el.setAttribute('data-date', g);
                el.value = convertToJalali(g);
            });
        }

        // فعلا داده‌ای نیست (پیش‌فاکتور CRM ساخته نشده) — کارت‌ها صفر و جدول خالی
        function load() {
            rows = [];
            gridApi.setGridOption('rowData', rows);
            gridApi.showNoRowsOverlay();
            document.getElementById('cOpen').textContent = faDigits(0);
            document.getElementById('cSum').textContent = UCRM.money(0) + ' تومان';
            document.getElementById('cLate').textContent = faDigits(0);
            document.getElementById('cRate').textContent = faDigits(0) + '٪';
        }

        function exportExcel() {
            const head = ['شمارهٔ پیش‌فاکتور', 'نام مشتری'].concat(SEES_ALL ? ['کارشناس فروش'] : [])
                .concat(['مبلغ پیش‌فاکتور (تومان)', 'تاریخ صدور', 'تاریخ پیگیری بعدی', 'وضعیت']);
            const data = [];
            gridApi.forEachNodeAfterFilterAndSort(n => {
                const r = n.data;
                data.push([r.number, r.customer_name].concat(SEES_ALL ? [r.rep_name] : [])
                    .concat([r.amount, jd(r.issue_date), jd(r.next_followup), (PF_STATUS[r.status] || [])[2] || '']));
            });
            const ws = XLSX.utils.aoa_to_sheet([head].concat(data));
            ws['!cols'] = head.map(() => ({ wch: 20 }));
            const wb = XLSX.utils.book_new();
            wb.Workbook = { Views: [{ RTL: true }] };
            XLSX.utils.book_append_sheet(wb, ws, 'پیش‌فاکتورهای باز');
            XLSX.writeFile(wb, 'pishfactor-baz-' + range.from + '_' + range.to + '.xlsx');
            if (!data.length) showToast('فعلا پیش‌فاکتوری نیست؛ فایل فقط سرستون‌ها را دارد.', 'info');
        }

        document.addEventListener('DOMContentLoaded', async () => {
            if (!UCRM.tok()) {
                location.href = '../index.php';
                return;
            }
            gridApi = agGrid.createGrid(document.getElementById('pfGrid'), {
                theme: AgGridFa.theme(), columnDefs: colDefs, rowData: [], enableRtl: true,
                pagination: true, paginationPageSize: 20, paginationPageSizeSelector: [20, 50, 100],
                onPaginationChanged: () => AgGridFa.persianizePaging(),
                defaultColDef: { resizable: true, sortable: true },
                overlayNoRowsTemplate: '<span class="text-muted">هنوز پیش‌فاکتوری صادر نشده است — با ساخت بخش پیش‌فاکتور CRM این گزارش پر می‌شود.</span>',
            });
            // کلیک راست روی ردیف = همان منوی عملیات (قاعدهٔ جدول‌های حسابداری)
            AgGridFa.rowContextMenu(document.getElementById('pfGrid'), gridApi);

            let savedRange = null;
            try {
                savedRange = JSON.parse(SearchPersist.getValue('ucrm-proformas', 'range') || 'null');
            } catch (e) {}
            setRange(savedRange && savedRange.from && savedRange.to ? savedRange : quickRange('month'));
            document.querySelectorAll('.uc-pf-quick button').forEach(b => b.addEventListener('click', () => {
                setRange(quickRange(b.dataset.q));
                bootstrap.Dropdown.getOrCreateInstance(document.querySelector('.uc-pf-range-btn')).hide();
                load();
            }));
            document.getElementById('rangeApply').addEventListener('click', () => {
                const f = document.getElementById('dFrom').getAttribute('data-date'), t = document.getElementById('dTo').getAttribute('data-date');
                if (!f || !t) return showToast('هر دو تاریخ را انتخاب کنید.', 'warning');
                setRange(f <= t ? { from: f, to: t } : { from: t, to: f });
                bootstrap.Dropdown.getOrCreateInstance(document.querySelector('.uc-pf-range-btn')).hide();
                load();
            });
            document.getElementById('btnExcel').addEventListener('click', exportExcel);
            if (SEES_ALL) {
                try {
                    const rp = await UCRM.get('/reps');
                    const sel = document.getElementById('fRep');
                    (rp.items || []).forEach(r => {
                        const o = document.createElement('option');
                        o.value = r.id;
                        o.textContent = r.name;
                        sel.appendChild(o);
                    });
                    SearchPersist.bindSelect(sel, 'ucrm-proformas');
                    sel.addEventListener('change', load);
                } catch (e) {
                    showToast(e.message || 'خطا در دریافت کارشناسان', 'error');
                }
            }
            load();
        });
    </script>
</body>

</html>
