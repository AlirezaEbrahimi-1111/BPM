<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// CRM جدید — فهرست سفارش‌های فروش (?type=sales) / خرید (?type=purchase) (۱۴۰۵/۰۸/۱۷؛ بدون طرح —
// هم‌شکل صفحهٔ پیش‌فاکتورها). داده: سفارش‌های خودِ شایگان (۱۱/۱۲، پیش‌بارگذاری) + سفارش‌های صادرشده
// در CRM (crm-service/ucrm_orders.go). وضعیتِ شایگان: کنترل نشده / باز / تحویل ناقص / تحویل کامل.
// سفارش خرید فعلا فقط مدیر/سوپروایزر. ?customer=شناسه (سفارش فروش) = همان مشتری، بدون بازهٔ تاریخ.
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ucrm_access.php';
if (!ucrmAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}
$__ucrmRoles = ucrmRoles($db, (int) $user_id);
$__purchase = ($_GET['type'] ?? '') === 'purchase';
if ($__purchase && !$__ucrmRoles['manager']) {
    header('Location: ucrm-orders.php?type=sales');
    exit;
}
$__seesAll = $__ucrmRoles['manager'] || $__ucrmRoles['accounting'];
$__title = $__purchase ? 'سفارش‌های خرید' : 'سفارش‌های فروش';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $__title ?> - سامانه مدیریت فرآیندها</title>
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

    <div class="overview-container uc-top-gap">
        <div class="uc-card uc-pf-head">
            <h1><span class="uc-tg-ico"><i class="bi bi-<?= $__purchase ? 'cart' : 'cart-check' ?>"></i></span><?= $__title ?></h1>
            <div class="spacer"></div>
            <div class="uc-pf-tools">
                <?php if ($__seesAll && !$__purchase): ?>
                    <div class="uc-tg-f"><i class="bi bi-person"></i>
                        <select id="fRep" class="form-select form-select-sm" title="کارشناس">
                            <option value="">همهٔ کارشناسان</option>
                        </select>
                    </div>
                <?php endif; ?>
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
                <button type="button" class="btn btn-outline-primary uc-pf-xl" id="btnExcel"><i class="bi bi-download ms-1"></i>خروجی اکسل</button>
                <button type="button" class="btn btn-primary uc-pf-xl" id="btnNew"><i class="bi bi-plus-lg ms-1"></i><?= $__purchase ? 'سفارش خرید جدید' : 'سفارش فروش جدید' ?></button>
            </div>
        </div>

        <div class="uc-chips mb-2" id="custChip"></div>

        <div class="uc-pf-cards">
            <div class="uc-pf-card c-open"><div><div class="k">همهٔ سفارش‌ها</div><div class="v" id="cTotal">۰</div></div><span class="ic"><i class="bi bi-list-ul"></i></span></div>
            <div class="uc-pf-card c-late"><div><div class="k">باز (تحویل نشده)</div><div class="v" id="cOpen">۰</div></div><span class="ic"><i class="bi bi-hourglass-split"></i></span></div>
            <div class="uc-pf-card c-sum"><div><div class="k">مبلغ سفارش‌های باز</div><div class="v" id="cSum">۰ تومان</div></div><span class="ic"><i class="bi bi-cash-stack"></i></span></div>
            <div class="uc-pf-card c-rate"><div><div class="k">تحویل کامل</div><div class="v" id="cDone">۰</div></div><span class="ic"><i class="bi bi-check2-all"></i></span></div>
        </div>

        <div class="uc-card uc-pf-list">
            <h2><i class="bi bi-<?= $__purchase ? 'cart' : 'cart-check' ?> ms-1"></i><?= $__title ?></h2>
            <div id="ordGrid" class="ag-theme-alpine inv-grid grid-fill" style="width:100%;"></div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../../assets/js/ucrm-common.js') ?>"></script>
    <script>
        const PURCHASE = <?= $__purchase ? 'true' : 'false' ?>;
        const SEES_ALL = <?= ($__seesAll && !$__purchase) ? 'true' : 'false' ?>;
        const PERSIST_KEY = PURCHASE ? 'ucrm-orders-purchase' : 'ucrm-orders-sales';
        const ORD_STATUS = {
            unchecked: ['wait', 'bi-hourglass-split', 'کنترل نشده'],
            open: ['wait', 'bi-circle', 'باز'],
            partial: ['late', 'bi-pie-chart-fill', 'تحویل ناقص'],
            delivered: ['ok', 'bi-check-lg', 'تحویل کامل'],
            crm_draft: ['stop', 'bi-pencil', 'ثبت در CRM'],
            pending_accounting: ['wait', 'bi-send', 'در انتظار حسابداری'],
            rejected: ['stop', 'bi-x-circle-fill', 'رد حسابداری'],
        };
        let gridApi = null, rows = [], range = { from: '', to: '' };
        let CUST = PURCHASE ? 0 : (parseInt(new URLSearchParams(location.search).get('customer'), 10) || 0);
        const jd = v => v ? TimeSync.formatJalali(v) : '—';

        function statusCell(p) {
            const s = ORD_STATUS[p.value];
            return s ? `<span class="uc-st ${s[0]}"><i class="bi ${s[1]}"></i>${s[2]}</span>` : '';
        }

        const colDefs = [
            { headerName: 'شمارهٔ سفارش', field: 'number', width: 150,
              cellRenderer: p => p.data.source === 'crm'
                  ? `<a class="uc-name-link" href="ucrm-proforma-edit.php?id=${p.data.doc_id}" title="صادرشده در CRM — هنوز به حسابداری نرفته">${faDigits(p.value || '')}</a>`
                  : faDigits(p.value || '') },
            { headerName: PURCHASE ? 'تأمین‌کننده' : 'نام مشتری', field: 'party_name', flex: 1.4, minWidth: 140,
              cellRenderer: p => p.data.customer_id ? `<a class="uc-name-link" href="ucrm-customer.php?id=${p.data.customer_id}">${esc(p.value)}</a>` : esc(p.value) },
            { headerName: 'کارشناس فروش', field: 'rep_name', flex: 1, minWidth: 110, hide: !SEES_ALL },
            { headerName: 'مبلغ (تومان)', field: 'amount', width: 130, cellRenderer: p => UCRM.money(p.value) },
            { headerName: 'تاریخ ثبت', field: 'issue_date', width: 105, cellRenderer: p => jd(p.value) },
            { headerName: 'تحویل مورد انتظار', field: 'expected_date', width: 125, cellRenderer: p => jd(p.value) },
            { headerName: 'وضعیت', field: 'status', width: 150, cellRenderer: statusCell },
        ];

        function pad2(n) { return String(n).padStart(2, '0'); }
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
            SearchPersist.setValue(PERSIST_KEY, 'range', JSON.stringify(r));
            document.getElementById('rangeLbl').textContent = faDigits(convertToJalali(r.from) + ' - ' + convertToJalali(r.to));
            [['dFrom', r.from], ['dTo', r.to]].forEach(([id, g]) => {
                const el = document.getElementById(id);
                el.setAttribute('data-date', g);
                el.value = convertToJalali(g);
            });
        }

        // ارتفاع جدول تا پایین صفحه، بدون اسکرول (همان روش صفحهٔ پیش‌فاکتورها)
        function fitGrid() {
            const el = document.getElementById('ordGrid');
            el.style.height = '';
            const over = document.documentElement.scrollHeight - window.innerHeight;
            if (over > 0) el.style.height = Math.max(300, Math.floor(el.getBoundingClientRect().height - over)) + 'px';
        }

        let loadSeq = 0;
        async function load() {
            const seq = ++loadSeq;
            const qs = new URLSearchParams({ type: PURCHASE ? 'purchase' : 'sales' });
            if (CUST) qs.set('customer', CUST);
            else { qs.set('from', range.from); qs.set('to', range.to); }
            const rep = SEES_ALL ? document.getElementById('fRep').value : '';
            if (rep) qs.set('rep', rep);
            gridApi.showLoadingOverlay();
            try {
                const res = await UCRM.get('/orders?' + qs.toString());
                if (seq !== loadSeq) return;
                rows = res.items || [];
                gridApi.setGridOption('rowData', rows);
                if (!rows.length) gridApi.showNoRowsOverlay(); else gridApi.hideOverlay();
                const c = res.cards || {};
                document.getElementById('cTotal').textContent = faDigits(c.total || 0);
                document.getElementById('cOpen').textContent = faDigits(c.open || 0);
                document.getElementById('cSum').textContent = UCRM.money(c.open_sum || 0) + ' تومان';
                document.getElementById('cDone').textContent = faDigits(c.delivered || 0);
                if (res.loading) showToast('فهرست سفارش‌ها هنوز از حسابداری خوانده نشده؛ چند لحظهٔ دیگر صفحه را تازه کنید.', 'info');
            } catch (e) {
                if (seq !== loadSeq) return;
                rows = [];
                gridApi.setGridOption('rowData', rows);
                gridApi.showNoRowsOverlay();
                showToast(e.message || 'خطا در دریافت سفارش‌ها', 'error');
            }
        }

        async function renderCustChip() {
            const box = document.getElementById('custChip');
            if (!CUST) { box.innerHTML = ''; return; }
            let name = '#' + faDigits(CUST);
            try { name = ((await UCRM.get('/customers/' + CUST)).customer || {}).name || name; } catch (e) {}
            box.innerHTML = `<span class="uc-chip"><i class="bi bi-funnel"></i>فقط سفارش‌های مشتری: <b>${esc(name)}</b><button type="button" title="نمایش همه" id="custClear">×</button></span>`;
            document.getElementById('custClear').addEventListener('click', () => {
                CUST = 0;
                history.replaceState(null, '', 'ucrm-orders.php?type=sales');
                renderCustChip().then(fitGrid);
                load();
            });
        }

        function exportExcel() {
            const head = ['شمارهٔ سفارش', PURCHASE ? 'تأمین‌کننده' : 'نام مشتری'].concat(SEES_ALL ? ['کارشناس فروش'] : [])
                .concat(['مبلغ (تومان)', 'تاریخ ثبت', 'تحویل مورد انتظار', 'وضعیت']);
            const data = [];
            gridApi.forEachNodeAfterFilterAndSort(n => {
                const r = n.data;
                data.push([r.number, r.party_name].concat(SEES_ALL ? [r.rep_name] : [])
                    .concat([r.amount, jd(r.issue_date), jd(r.expected_date), (ORD_STATUS[r.status] || [])[2] || '']));
            });
            const ws = XLSX.utils.aoa_to_sheet([head].concat(data));
            ws['!cols'] = head.map(() => ({ wch: 20 }));
            const wb = XLSX.utils.book_new();
            wb.Workbook = { Views: [{ RTL: true }] };
            XLSX.utils.book_append_sheet(wb, ws, PURCHASE ? 'سفارش‌های خرید' : 'سفارش‌های فروش');
            XLSX.writeFile(wb, (PURCHASE ? 'purchase-orders-' : 'sales-orders-') + range.from + '_' + range.to + '.xlsx');
        }

        document.addEventListener('DOMContentLoaded', async () => {
            if (!UCRM.tok()) { location.href = '../index.php'; return; }
            gridApi = agGrid.createGrid(document.getElementById('ordGrid'), {
                theme: AgGridFa.theme(), columnDefs: colDefs, rowData: [], enableRtl: true,
                pagination: true, paginationPageSize: 20, paginationPageSizeSelector: [20, 50, 100],
                onPaginationChanged: () => AgGridFa.persianizePaging(),
                defaultColDef: { resizable: true, sortable: true },
                overlayNoRowsTemplate: '<span class="text-muted">سفارشی در این بازه پیدا نشد.</span>',
            });
            let saved = null;
            try { saved = JSON.parse(SearchPersist.getValue(PERSIST_KEY, 'range') || 'null'); } catch (e) {}
            setRange(saved && saved.from && saved.to ? saved : quickRange('month'));
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
            document.getElementById('btnNew').addEventListener('click', () =>
                location.href = 'ucrm-proforma-edit.php?type=' + (PURCHASE ? 'purchase_order' : 'sales_order') + (CUST ? '&customer=' + CUST : ''));
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
                    SearchPersist.bindSelect(sel, PERSIST_KEY);
                    sel.addEventListener('change', load);
                } catch (e) {
                    showToast(e.message || 'خطا در دریافت کارشناسان', 'error');
                }
            }
            renderCustChip().then(fitGrid);
            fitGrid();
            let fitT = 0;
            window.addEventListener('resize', () => { clearTimeout(fitT); fitT = setTimeout(fitGrid, 120); });
            window.addEventListener('load', fitGrid);
            load();
        });
    </script>
</body>

</html>
