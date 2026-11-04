<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// CRM جدید — «گزارشات | پیش‌فاکتورهای باز و پیگیری‌ها» (بخش ۱۰ طرح CRM، طرح ۱۴۰۵/۰۸/۰۳).
// داده (۱۴۰۵/۰۸/۱۳): پیش‌فاکتورهای فروشِ شایگان (نوع ۱۳، پیش‌بارگذاری در crm-service/ucrm_proformas.go)
// + وضعیت/تاریخ پیگیریِ CRM (ucrm_proforma_states). مبلغ شایگان ریال است؛ این‌جا تومان (÷۱۰).
// فیلتر کارشناس + بازهٔ تاریخ (پیش‌فرض ماه جاری) + خروجی اکسل؛ ?customer=شناسه (از منوی «⋯» صفحهٔ
// مشتری) = همهٔ پیش‌فاکتورهای همان مشتری، بدون بازهٔ تاریخ.
// کارت‌ها (توافق ۱۴۰۵/۰۸/۰۳): معوق = پیش‌فاکتور باز با «تاریخ پیگیری بعدی» بیش از ۷ روز گذشته؛
// نرخ تبدیل = درصد پیش‌فاکتورهای «تأیید شده» در همین فهرست.
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

        <!-- از منوی «⋯» صفحهٔ مشتری (?customer=شناسه) → فقط پیش‌فاکتورهای همان مشتری -->
        <div class="uc-chips mb-2" id="pfCustChip"></div>

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

    <!-- تغییر تاریخ پیگیری -->
    <div class="modal fade" id="pfFuModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-event ms-2"></i>تغییر تاریخ پیگیری</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2" id="pfFuFor"></div>
                    <label class="form-label small mb-1">تاریخ پیگیری بعدی</label>
                    <div class="persian-datepicker-wrapper">
                        <input type="text" class="persian-datepicker-input form-control" id="pfFuDate" readonly>
                        <div class="persian-datepicker">
                            <div class="datepicker-header"><button type="button" class="datepicker-nav" data-action="prev">►</button><span class="datepicker-current"></span><button type="button" class="datepicker-nav" data-action="next">◄</button></div>
                            <div class="datepicker-weekdays"><div class="datepicker-weekday">ش</div><div class="datepicker-weekday">ی</div><div class="datepicker-weekday">د</div><div class="datepicker-weekday">س</div><div class="datepicker-weekday">چ</div><div class="datepicker-weekday">پ</div><div class="datepicker-weekday">ج</div></div>
                            <div class="datepicker-days"></div><button type="button" class="datepicker-today-btn">امروز</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" id="pfFuSave"><i class="bi bi-check-lg ms-1"></i>ثبت</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../../assets/js/ucrm-common.js') ?>"></script>
    <script>
        const SEES_ALL = <?= $__seesAll ? 'true' : 'false' ?>;
        // وضعیت‌ها طبق طرح (۱۴۰۵/۰۸/۱۳): شروع نشده = باز؛ «تکمیل» → تأیید شده؛ «لغو» → متوقف شده؛
        // عقب افتاده = شروع‌نشده با تاریخ پیگیری گذشته (سرور حساب می‌کند)
        const PF_STATUS = {
            approved: ['ok', 'bi-check-lg', 'تأیید شده'],
            stopped: ['stop', 'bi-dash-circle-fill', 'متوقف شده'],
            not_started: ['wait', 'bi-hourglass-split', 'شروع نشده'],
            overdue: ['late', 'bi-exclamation-circle-fill', 'عقب افتاده'],
        };
        let gridApi = null, rows = [], range = { from: '', to: '' };
        // ?customer=شناسه → فقط پیش‌فاکتورهای همین مشتری (از منوی صفحهٔ مشتری)
        let CUST = parseInt(new URLSearchParams(location.search).get('customer'), 10) || 0;
        let fuModal = null, fuGuid = '';

        function statusCell(p) {
            const s = PF_STATUS[p.value];
            return s ? `<span class="uc-st ${s[0]}"><i class="bi ${s[1]}"></i>${s[2]}</span>` : '';
        }

        // منوی «⋮» هر ردیف (طرح): تکمیل / لغو / تغییر تاریخ پیگیری — فقط برای کسی که اجازه دارد
        // (مدیر/حسابداری همه؛ کارشناس فقط مشتری خودش). تأییدشده/متوقف‌شده → «بازگشایی».
        function actionCell(p) {
            const r = p.data;
            if (!r || !r.can_act) return '';
            const g = r.guid;
            const fu = `<button class="ag-action-btn" style="color:#8e57fe" title="تغییر تاریخ پیگیری" onclick="pfFollowup('${g}')"><i class="bi bi-calendar-event"></i></button>`;
            if (r.status === 'approved' || r.status === 'stopped') {
                return `<button class="ag-action-btn" style="color:#b45309" title="بازگشایی (شروع نشده)" onclick="pfStatus('${g}','not_started')"><i class="bi bi-arrow-counterclockwise"></i></button>` + fu;
            }
            return `<button class="ag-action-btn" style="color:#0f7a57" title="تکمیل" onclick="pfStatus('${g}','approved')"><i class="bi bi-check-circle-fill"></i></button>
                <button class="ag-action-btn del" title="لغو" onclick="pfStatus('${g}','stopped')"><i class="bi bi-x-circle-fill"></i></button>` + fu;
        }

        async function pfStatus(guid, status) {
            try {
                const res = await UCRM.send('POST', '/proformas/' + guid + '/status', { status });
                showToast(res.message || 'ثبت شد.', 'success');
                load();
            } catch (e) {
                showToast(e.message || 'خطا در ثبت وضعیت', 'error');
            }
        }

        function pfFollowup(guid) {
            const r = rows.find(x => x.guid === guid);
            if (!r) return;
            fuGuid = guid;
            document.getElementById('pfFuFor').textContent = faDigits(r.number) + ' — ' + r.customer_name;
            const el = document.getElementById('pfFuDate');
            const g = r.next_followup || TimeSync.serverToday();
            el.setAttribute('data-date', g);
            el.value = convertToJalali(g);
            fuModal.show();
        }

        async function saveFollowup() {
            const date = document.getElementById('pfFuDate').getAttribute('data-date') || '';
            if (!date) return showToast('تاریخ پیگیری را انتخاب کنید.', 'warning');
            const btn = document.getElementById('pfFuSave');
            btn.disabled = true;
            try {
                const res = await UCRM.send('PUT', '/proformas/' + fuGuid + '/followup', { date });
                fuModal.hide();
                showToast(res.message || 'ثبت شد.', 'success');
                load();
            } catch (e) {
                showToast(e.message || 'خطا در ثبت تاریخ پیگیری', 'error');
            } finally {
                btn.disabled = false;
            }
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
            { headerName: 'عملیات', colId: 'actions', width: 80, sortable: false, filter: false, cellRenderer: AgGridFa.actionMenuCell(actionCell, { insideGrid: true }) },
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

        // پیش‌فاکتورها از crm-service (کشِ پس‌زمینهٔ شایگان — منتظر شایگان نمی‌ماند)
        let loadSeq = 0;
        async function load() {
            const seq = ++loadSeq;
            const qs = new URLSearchParams();
            if (CUST) {
                qs.set('customer', CUST);
            } else {
                qs.set('from', range.from);
                qs.set('to', range.to);
            }
            const rep = SEES_ALL ? document.getElementById('fRep').value : '';
            if (rep) qs.set('rep', rep);
            gridApi.showLoadingOverlay();
            try {
                const res = await UCRM.get('/proformas?' + qs.toString());
                if (seq !== loadSeq) return;
                rows = res.items || [];
                gridApi.setGridOption('rowData', rows);
                if (!rows.length) gridApi.showNoRowsOverlay(); else gridApi.hideOverlay();
                const c = res.cards || {};
                document.getElementById('cOpen').textContent = faDigits(c.open || 0);
                document.getElementById('cSum').textContent = UCRM.money(c.sum || 0) + ' تومان';
                document.getElementById('cLate').textContent = faDigits(c.late || 0);
                document.getElementById('cRate').textContent = faDigits(c.rate || 0) + '٪';
                if (res.loading) showToast('فهرست پیش‌فاکتورها هنوز از حسابداری خوانده نشده؛ چند لحظهٔ دیگر صفحه را تازه کنید.', 'info');
            } catch (e) {
                if (seq !== loadSeq) return;
                rows = [];
                gridApi.setGridOption('rowData', rows);
                gridApi.showNoRowsOverlay();
                showToast(e.message || 'خطا در دریافت پیش‌فاکتورها', 'error');
            }
        }

        // ارتفاع جدول: از ارتفاع CSS (grid-fill) شروع می‌کند و اگر صفحه با آن اسکرول بخورد،
        // دقیقا به اندازهٔ همان سرریز کوتاه می‌شود — جدول تا پایین، بدون اسکرول صفحه
        function fitGrid() {
            const el = document.getElementById('pfGrid');
            el.style.height = '';
            const over = document.documentElement.scrollHeight - window.innerHeight;
            if (over > 0) el.style.height = Math.max(300, Math.floor(el.getBoundingClientRect().height - over)) + 'px';
        }

        // نوار «مشتری: X ✕» — برداشتن = برگشت به همهٔ پیش‌فاکتورها
        async function renderCustChip() {
            const box = document.getElementById('pfCustChip');
            if (!CUST) {
                box.innerHTML = '';
                return;
            }
            let name = '#' + faDigits(CUST);
            try {
                name = ((await UCRM.get('/customers/' + CUST)).customer || {}).name || name;
            } catch (e) {}
            box.innerHTML = `<span class="uc-chip"><i class="bi bi-funnel"></i>فقط پیش‌فاکتورهای مشتری: <b>${esc(name)}</b><button type="button" title="نمایش همه" id="pfCustClear">×</button></span>`;
            document.getElementById('pfCustClear').addEventListener('click', () => {
                CUST = 0;
                history.replaceState(null, '', 'ucrm-proformas.php');
                renderCustChip().then(fitGrid);
                load();
            });
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
            if (!data.length) showToast('پیش‌فاکتوری در این فهرست نیست؛ فایل فقط سرستون‌ها را دارد.', 'info');
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
                overlayNoRowsTemplate: '<span class="text-muted">پیش‌فاکتوری در این بازه پیدا نشد.</span>',
            });
            // کلیک راست روی ردیف = همان منوی عملیات (قاعدهٔ جدول‌های حسابداری)
            AgGridFa.rowContextMenu(document.getElementById('pfGrid'), gridApi, null, { insideGrid: true });

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
            fuModal = new bootstrap.Modal(document.getElementById('pfFuModal'));
            document.getElementById('pfFuSave').addEventListener('click', saveFollowup);
            renderCustChip().then(fitGrid);
            fitGrid();
            let fitT = 0;
            window.addEventListener('resize', () => { clearTimeout(fitT); fitT = setTimeout(fitGrid, 120); });
            window.addEventListener('load', fitGrid); // بعد از فونت‌ها/فوتر
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
