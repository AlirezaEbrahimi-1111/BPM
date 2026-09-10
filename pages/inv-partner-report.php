<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/crm_access.php';
// این گزارش را — علاوه بر تیمِ حسابداری — مدیران و سوپروایزرها هم می‌بینند.
if (!crmReportAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشِ همکاران - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
    <script src="<?= asset('../../assets/js/persian-date-utils.js') ?>"></script>

    <style>
        .crm-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
            margin: 1rem 0 .5rem;
        }

        .crm-toolbar .fld {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .crm-toolbar .fld label {
            font-size: 11px;
            color: var(--text-muted, #6b7280);
        }

        .crm-toolbar .form-select {
            min-width: 130px;
        }

        .crm-toolbar .spacer {
            flex: 1;
        }

        .report-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            font-size: .9rem;
            margin: .25rem 0 .5rem;
            padding: 8px 12px;
            border-radius: 10px;
            background: rgba(142, 87, 254, .07);
        }

        .report-summary b {
            letter-spacing: 0;
            direction: ltr;
            unicode-bidi: isolate;
        }

        .num-ltr {
            direction: ltr;
            display: inline-block;
            letter-spacing: 0;
        }

        .st-badge {
            display: inline-block;
            padding: 0 10px;
            line-height: 1.7;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 500;
        }

        .st-badge.draft {
            background: rgba(107, 114, 128, .16);
            color: #4b5563;
        }

        .st-badge.approved {
            background: rgba(16, 122, 87, .14);
            color: #0f7a57;
        }

        .st-badge.cancelled {
            background: rgba(220, 38, 38, .14);
            color: #dc2626;
        }

        :root[data-theme="dark"] .st-badge.draft {
            color: #cbd5e1;
        }

        /* کنترل‌های ویرایشِ درجا داخلِ جدول */
        .pm-chk {
            width: 17px;
            height: 17px;
            cursor: pointer;
        }

        .pm-sel,
        .pm-mo-code {
            font-size: 12px;
            padding: 2px 4px;
            border: 1px solid var(--border-soft, #d1d5db);
            border-radius: 6px;
            background: var(--surface, #fff);
            color: inherit;
            max-width: 100%;
        }

        .pm-moadian-cell {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        .pm-mo-code {
            width: 92px;
        }

        .pm-months {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px 14px;
        }

        .pm-months .m {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .pm-months .m label {
            width: 62px;
            font-size: 12px;
        }

        .pm-months .m input {
            flex: 1;
        }

        :root[data-theme="dark"] .pm-sel,
        :root[data-theme="dark"] .pm-mo-code {
            background: var(--surface);
            border-color: var(--border-soft);
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .report-summary {
            background: rgba(142, 87, 254, .14);
        }

        .ag-theme-alpine,
        .ag-theme-alpine .ag-cell,
        .ag-theme-alpine .ag-header-cell,
        .ag-theme-alpine .ag-paging-panel {
            letter-spacing: 0 !important;
            word-spacing: 0 !important;
        }

        .site-footer { position: fixed !important; left: 0; right: 0; bottom: 0; top: auto !important; margin-top: 0 !important; z-index: 80; }
        body { padding-bottom: 46px; }
        .grid-fill { height: calc(100vh - 400px); min-height: 240px; }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container" style="margin-top:70px">

        <div class="filters-wrapper two-col">
            <div class="filters-title-col">
                <h1><i class="bi bi-people"></i> گزارشِ همکاران</h1>
                <p>فاکتورهایی که به‌درخواستِ همکاران صادر شده — درصد و مبلغِ سود، وضعیتِ ثبت/تسویه/مودیان</p>
            </div>
        </div>

        <div class="crm-toolbar">
            <div class="fld">
                <label>سالِ شمسی</label>
                <select id="fYear" class="form-select"></select>
            </div>
            <div class="fld">
                <label>ماه</label>
                <select id="fMonth" class="form-select">
                    <option value="">همهٔ ماه‌ها</option>
                </select>
            </div>
            <div class="fld">
                <label>همکار</label>
                <select id="fPartner" class="form-select">
                    <option value="">همهٔ همکاران</option>
                </select>
            </div>
            <div class="fld">
                <label>نوعِ سند</label>
                <select id="fDocType" class="form-select">
                    <option value="">فاکتور و پیش‌فاکتور</option>
                    <option value="official">فقط فاکتور رسمی</option>
                    <option value="proforma">فقط پیش‌فاکتور</option>
                </select>
            </div>
            <div class="fld">
                <label>وضعیتِ فاکتور</label>
                <select id="fStatus" class="form-select">
                    <option value="">به‌جز باطل‌شده</option>
                    <option value="draft">پیش‌نویس</option>
                    <option value="approved">تأییدشده</option>
                    <option value="cancelled">باطل‌شده</option>
                </select>
            </div>
            <div class="spacer"></div>
            <button class="btn btn-outline-secondary" id="btnManagePartners"><i class="bi bi-gear ms-1"></i> مدیریت همکاران و درصدها</button>
        </div>

        <div class="report-summary" id="summary">
            <span>مجموعِ مبلغِ فاکتور: <b id="sInvoice">۰</b></span>
            <span>مجموعِ مبلغِ سود: <b id="sProfit">۰</b></span>
            <span>تعداد ردیف: <b id="sCount">۰</b></span>
        </div>

        <div id="repGrid" class="ag-theme-alpine grid-fill" style="width:100%;"></div>
    </div>

    <!-- ── مودالِ مدیریتِ همکاران و درصدهای ماهانه ── -->
    <div class="modal fade" id="pmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">مدیریت همکاران و درصدِ سودِ ماهانه</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body">
                    <div id="pmAlert"></div>

                    <div class="row g-2 align-items-end mb-3">
                        <div class="col"><label class="form-label">نامِ همکارِ جدید</label><input id="pmNewName" class="form-control"></div>
                        <div class="col-4"><label class="form-label">تلفن (اختیاری)</label><input id="pmNewPhone" class="form-control"></div>
                        <div class="col-auto"><button id="pmAddBtn" class="btn btn-primary">افزودن</button></div>
                    </div>
                    <hr>

                    <div class="row g-2 align-items-end mb-2">
                        <div class="col"><label class="form-label">همکار</label><select id="pmSelPartner" class="form-select"></select></div>
                        <div class="col-auto"><label class="form-label">سالِ شمسی</label><select id="pmSelYear" class="form-select"></select></div>
                    </div>

                    <div id="pmPartnerEdit" hidden>
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col"><label class="form-label">نام</label><input id="pmEditName" class="form-control"></div>
                            <div class="col-4"><label class="form-label">تلفن</label><input id="pmEditPhone" class="form-control"></div>
                            <div class="col-auto">
                                <div class="form-check mt-4">
                                    <input type="checkbox" id="pmEditActive" class="form-check-input">
                                    <label class="form-check-label" for="pmEditActive">فعال</label>
                                </div>
                            </div>
                            <div class="col-auto"><button id="pmSaveBtn" class="btn btn-outline-primary">ذخیرهٔ مشخصات</button></div>
                        </div>

                        <label class="form-label">درصدِ سهمِ سود در هر ماه <span class="text-muted">(٪ از مبلغِ پیش از مالیات)</span></label>
                        <div id="pmMonths" class="pm-months"></div>
                        <p class="text-muted mt-2" style="font-size:12px">
                            درصدِ هر ماه روی همهٔ فاکتورهای همان ماه اثر می‌گذارد — صادرشده و بعدی. تغییر با از دست دادنِ فوکوس ذخیره می‌شود.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        /* ───────────────────────────────────────────────────────────────
         *  راهنمای مبتدی (لایه‌ها):
         *   • این صفحه فقط «نمایش» است؛ داده از سرویسِ Go می‌آید:
         *       GET  /crm/api/inv/partner-report      → ردیف‌های گزارش
         *       GET  /crm/api/inv/partners            → فهرستِ همکاران
         *       GET/PUT /crm/api/inv/partners/{id}/shares  → درصدِ ماه‌ها
         *       PATCH /crm/api/inv/invoices/{id}/partner-status → ۳ ستونِ وضعیت
         *   • «مبلغِ سود» در سرور حساب می‌شود: درصدِ ماهِ صدور × (جمع منهای تخفیف).
         *     پس با تغییرِ درصدِ یک ماه، سودِ همهٔ فاکتورهای آن ماه عوض می‌شود.
         *   • اگر چیزی نمایش داده نشد: F12 → Console و Network را ببین؛
         *     پاسخِ ۴۰۳ یعنی دسترسی نداری، ۵۰۰ یعنی خطای سرور.
         * ─────────────────────────────────────────────────────────────── */
        const API = '/crm/api';
        const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
        ];
        const DOC_LABEL = {
            official: 'فاکتور رسمی',
            proforma: 'پیش‌فاکتور'
        };
        const ST_LABEL = {
            draft: 'پیش‌نویس',
            approved: 'تأییدشده',
            cancelled: 'باطل'
        };

        function tok() {
            return localStorage.getItem('auth_token');
        }

        function ahj() {
            return {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + tok()
            };
        }

        function money(n) {
            return faDigits(String(Math.round(Number(n) || 0).toLocaleString('en-US')));
        }

        function jDate(g) {
            if (!g) return '—';
            try {
                return new Date(g).toLocaleDateString('fa-IR', {
                    timeZone: 'Asia/Tehran'
                });
            } catch (e) {
                return faDigits(g);
            }
        }

        async function apiGet(path) {
            const r = await fetch(API + path, {
                headers: {
                    'Authorization': 'Bearer ' + tok()
                }
            });
            const d = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(d.message || ('خطای ' + r.status));
            return d;
        }

        async function apiSend(method, path, body) {
            const r = await fetch(API + path, {
                method,
                headers: ahj(),
                body: body ? JSON.stringify(body) : undefined
            });
            const d = await r.json().catch(() => ({}));
            if (!r.ok || d.success === false) throw new Error(d.message || ('خطای ' + r.status));
            return d;
        }

        // سالِ شمسیِ امروز
        function currentJY() {
            try {
                const t = new Date();
                const g = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' +
                    String(t.getDate()).padStart(2, '0');
                const j = (typeof convertToJalali === 'function') ? convertToJalali(g) : '';
                const y = parseInt(String(j).replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).split(/[\/\-]/)[0], 10);
                return (y > 1300 && y < 1500) ? y : 1404;
            } catch (e) {
                return 1404;
            }
        }

        const CUR_JY = currentJY();
        let gridApi = null,
            allRows = [],
            partners = [];

        function yearOptions(sel, from, to, selected) {
            sel.innerHTML = '';
            for (let y = to; y >= from; y--) {
                const o = document.createElement('option');
                o.value = y;
                o.textContent = faDigits(y);
                if (y === selected) o.selected = true;
                sel.appendChild(o);
            }
        }

        // ───────── ستون‌های ویرایش‌پذیرِ درجا ─────────
        function chkCell(p) {
            return `<input type="checkbox" class="pm-chk" ${p.value ? 'checked' : ''} onchange="pmToggleProfit(${p.data.id}, this.checked)">`;
        }

        function settleCell(p) {
            const v = p.value || 'unsettled';
            return `<select class="pm-sel" onchange="pmSetSettlement(${p.data.id}, this.value)">
                <option value="unsettled" ${v==='unsettled'?'selected':''}>تسویه نشده</option>
                <option value="settled" ${v==='settled'?'selected':''}>تسویه شده</option>
                <option value="partial" ${v==='partial'?'selected':''}>بخشی تسویه شده</option>
            </select>`;
        }

        function moadianCell(p) {
            const d = p.data;
            const reg = d.moadian_status === 'registered';
            const code = String(d.moadian_code || '').replace(/"/g, '&quot;');
            return `<div class="pm-moadian-cell">
                <select class="pm-sel pm-mo-status" data-id="${d.id}" onchange="pmSetMoadian(${d.id})">
                    <option value="unregistered" ${!reg?'selected':''}>ثبت نشده</option>
                    <option value="registered" ${reg?'selected':''}>ثبت شده</option>
                </select>
                <input class="pm-mo-code" data-id="${d.id}" value="${code}" placeholder="کدِ سامانه"
                    ${reg?'':'hidden'} onchange="pmSetMoadian(${d.id})">
            </div>`;
        }

        const colDefs = [{
                headerName: 'نوعِ سند',
                field: 'doc_type',
                width: 110,
                cellRenderer: p => DOC_LABEL[p.value] || p.value
            },
            {
                headerName: 'شمارهٔ فاکتور',
                field: 'number',
                width: 130,
                cellRenderer: p => p.value ? faDigits(p.value) :
                    `<span class="st-badge draft">${ST_LABEL[p.data.status]||'—'}</span>`
            },
            {
                headerName: 'مشتری',
                field: 'customer_name',
                flex: 1.4,
                minWidth: 140
            },
            {
                headerName: 'تاریخِ صدور',
                field: 'issue_date',
                width: 115,
                cellRenderer: p => jDate(p.value)
            },
            {
                headerName: 'همکار',
                field: 'partner_name',
                flex: 1.2,
                minWidth: 130
            },
            {
                headerName: 'درصدِ سود',
                field: 'percent',
                width: 100,
                type: 'rightAligned',
                cellRenderer: p => faDigits(p.value || 0) + '٪'
            },
            {
                headerName: 'مبلغِ فاکتور (ریال)',
                field: 'invoice_amount',
                width: 150,
                type: 'rightAligned',
                cellRenderer: p => money(p.value)
            },
            {
                headerName: 'مبلغِ سود (ریال)',
                field: 'profit_amount',
                width: 145,
                type: 'rightAligned',
                cellRenderer: p => money(p.value)
            },
            {
                headerName: 'ثبتِ سود',
                field: 'partner_profit_recorded',
                width: 90,
                sortable: false,
                cellRenderer: chkCell
            },
            {
                headerName: 'تسویه',
                field: 'settlement_status',
                width: 150,
                sortable: false,
                cellRenderer: settleCell
            },
            {
                headerName: 'مودیان',
                field: 'moadian_status',
                width: 210,
                sortable: false,
                cellRenderer: moadianCell
            },
        ];

        const gridOptions = {
            theme: AgGridFa.theme(),
            columnDefs: colDefs,
            rowData: [],
            enableRtl: true,
            animateRows: true,
            pagination: true,
            paginationPageSize: 20,
            paginationPageSizeSelector: [20, 50, 100],
            onPaginationChanged: () => AgGridFa.persianizePaging(),
            defaultColDef: {
                resizable: true,
                sortable: true
            },
            overlayNoRowsTemplate: '<span class="text-muted">ردیفی برای این فیلترها نیست</span>',
        };

        function qs() {
            const p = new URLSearchParams();
            const y = document.getElementById('fYear').value;
            const m = document.getElementById('fMonth').value;
            const pr = document.getElementById('fPartner').value;
            const dt = document.getElementById('fDocType').value;
            const st = document.getElementById('fStatus').value;
            if (y) p.set('jy', y);
            if (m) p.set('jm', m);
            if (pr) p.set('partner_id', pr);
            if (dt) p.set('doc_type', dt);
            if (st) p.set('status', st);
            return p.toString();
        }

        async function loadReport() {
            gridApi.showLoadingOverlay();
            try {
                const d = await apiGet('/inv/partner-report?' + qs());
                allRows = d.items || [];
                gridApi.setGridOption('rowData', allRows);
                let inv = 0,
                    prof = 0;
                allRows.forEach(r => {
                    inv += Number(r.invoice_amount) || 0;
                    prof += Number(r.profit_amount) || 0;
                });
                document.getElementById('sInvoice').textContent = money(inv);
                document.getElementById('sProfit').textContent = money(prof);
                document.getElementById('sCount').textContent = faDigits(allRows.length);
            } catch (e) {
                showToast(e.message || 'خطا در بارگذاری', 'error');
                gridApi.setGridOption('rowData', []);
            }
        }

        async function apiPatch(path, body) {
            return apiSend('PATCH', path, body);
        }

        async function pmToggleProfit(id, checked) {
            try {
                await apiPatch('/inv/invoices/' + id + '/partner-status', {
                    partner_profit_recorded: checked
                });
                const row = allRows.find(r => r.id === id);
                if (row) row.partner_profit_recorded = checked;
                showToast('ذخیره شد', 'success');
            } catch (e) {
                showToast(e.message || 'خطا', 'error');
                loadReport();
            }
        }

        async function pmSetSettlement(id, val) {
            try {
                await apiPatch('/inv/invoices/' + id + '/partner-status', {
                    settlement_status: val
                });
                const row = allRows.find(r => r.id === id);
                if (row) row.settlement_status = val;
                showToast('ذخیره شد', 'success');
            } catch (e) {
                showToast(e.message || 'خطا', 'error');
                loadReport();
            }
        }

        async function pmSetMoadian(id) {
            const sel = document.querySelector('.pm-mo-status[data-id="' + id + '"]');
            const inp = document.querySelector('.pm-mo-code[data-id="' + id + '"]');
            if (!sel || !inp) return;
            const status = sel.value;
            inp.hidden = status !== 'registered';
            if (status === 'registered' && !inp.value.trim()) {
                inp.focus();
                return; // منتظرِ کدِ سامانه بمان
            }
            try {
                await apiPatch('/inv/invoices/' + id + '/partner-status', {
                    moadian_status: status,
                    moadian_code: status === 'registered' ? inp.value.trim() : ''
                });
                const row = allRows.find(r => r.id === id);
                if (row) {
                    row.moadian_status = status;
                    row.moadian_code = status === 'registered' ? inp.value.trim() : '';
                }
                showToast('ذخیره شد', 'success');
            } catch (e) {
                showToast(e.message || 'خطا', 'error');
                loadReport();
            }
        }

        // ───────── همکاران ─────────
        async function loadPartners() {
            const out = [];
            try {
                let page = 1,
                    total = Infinity;
                while (out.length < total) {
                    const d = await apiGet('/inv/partners?per=200&page=' + page);
                    out.push(...(d.items || []));
                    total = d.total || 0;
                    if (!d.items || !d.items.length) break;
                    page++;
                }
            } catch (e) {}
            partners = out;

            const fp = document.getElementById('fPartner');
            const keep = fp.value;
            fp.innerHTML = '<option value="">همهٔ همکاران</option>' +
                partners.map(p => `<option value="${p.id}">${(p.name||'').replace(/</g,'&lt;')}</option>`).join('');
            fp.value = keep;

            const sp = document.getElementById('pmSelPartner');
            const keep2 = sp.value;
            sp.innerHTML = '<option value="">— انتخابِ همکار —</option>' +
                partners.map(p => `<option value="${p.id}">${(p.name||'').replace(/</g,'&lt;')}${p.is_active?'':' (غیرفعال)'}</option>`).join('');
            sp.value = keep2;
        }

        function pmAlert(msg, kind = 'danger') {
            document.getElementById('pmAlert').innerHTML =
                msg ? `<div class="alert alert-${kind} py-2">${msg}</div>` : '';
        }

        async function pmAddPartner() {
            const name = document.getElementById('pmNewName').value.trim();
            const phone = document.getElementById('pmNewPhone').value.trim();
            if (!name) {
                pmAlert('نامِ همکار را وارد کنید.');
                return;
            }
            try {
                const d = await apiSend('POST', '/inv/partners', {
                    name,
                    phone
                });
                document.getElementById('pmNewName').value = '';
                document.getElementById('pmNewPhone').value = '';
                pmAlert('همکار افزوده شد.', 'success');
                await loadPartners();
                document.getElementById('pmSelPartner').value = d.id;
                pmOnSelectPartner();
            } catch (e) {
                pmAlert(e.message || 'خطا');
            }
        }

        function pmCurrentPartner() {
            const id = +document.getElementById('pmSelPartner').value || 0;
            return partners.find(p => p.id === id) || null;
        }

        async function pmOnSelectPartner() {
            const p = pmCurrentPartner();
            const box = document.getElementById('pmPartnerEdit');
            if (!p) {
                box.hidden = true;
                return;
            }
            box.hidden = false;
            document.getElementById('pmEditName').value = p.name || '';
            document.getElementById('pmEditPhone').value = p.phone || '';
            document.getElementById('pmEditActive').checked = !!p.is_active;
            await pmLoadShares();
        }

        async function pmSavePartner() {
            const p = pmCurrentPartner();
            if (!p) return;
            try {
                await apiSend('PUT', '/inv/partners/' + p.id, {
                    name: document.getElementById('pmEditName').value.trim(),
                    phone: document.getElementById('pmEditPhone').value.trim(),
                    is_active: document.getElementById('pmEditActive').checked,
                });
                pmAlert('ذخیره شد.', 'success');
                await loadPartners();
                document.getElementById('pmSelPartner').value = p.id;
            } catch (e) {
                pmAlert(e.message || 'خطا');
            }
        }

        async function pmLoadShares() {
            const p = pmCurrentPartner();
            const jy = +document.getElementById('pmSelYear').value || CUR_JY;
            const wrap = document.getElementById('pmMonths');
            wrap.innerHTML = '';
            if (!p) return;
            let months = [];
            try {
                const d = await apiGet('/inv/partners/' + p.id + '/shares?jy=' + jy);
                months = d.months || [];
            } catch (e) {
                pmAlert(e.message || 'خطا در خواندنِ درصدها');
            }
            const byM = {};
            months.forEach(m => byM[m.jm] = m.percent);
            for (let m = 1; m <= 12; m++) {
                const div = document.createElement('div');
                div.className = 'm';
                div.innerHTML = `<label>${MONTHS[m-1]}</label>
                    <input type="number" step="0.001" min="0" max="100" class="form-control form-control-sm pm-month-inp"
                        data-jm="${m}" value="${byM[m] != null ? byM[m] : ''}" placeholder="۰">`;
                wrap.appendChild(div);
            }
            wrap.querySelectorAll('.pm-month-inp').forEach(inp => {
                inp.addEventListener('change', () => pmSaveShare(inp));
            });
        }

        async function pmSaveShare(inp) {
            const p = pmCurrentPartner();
            if (!p) return;
            const jy = +document.getElementById('pmSelYear').value || CUR_JY;
            const jm = +inp.dataset.jm;
            let percent = parseFloat(String(inp.value).replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)));
            if (isNaN(percent)) percent = 0;
            if (percent < 0 || percent > 100) {
                pmAlert('درصد باید بینِ ۰ تا ۱۰۰ باشد.');
                return;
            }
            try {
                await apiSend('PUT', '/inv/partners/' + p.id + '/shares', {
                    jy,
                    jm,
                    percent
                });
                pmAlert('درصدِ ' + MONTHS[jm - 1] + ' ذخیره شد.', 'success');
            } catch (e) {
                pmAlert(e.message || 'خطا');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (!tok()) {
                location.href = '../index.php';
                return;
            }

            yearOptions(document.getElementById('fYear'), CUR_JY - 3, CUR_JY + 1, CUR_JY);
            yearOptions(document.getElementById('pmSelYear'), CUR_JY - 3, CUR_JY + 1, CUR_JY);
            const fm = document.getElementById('fMonth');
            for (let m = 1; m <= 12; m++) {
                const o = document.createElement('option');
                o.value = m;
                o.textContent = MONTHS[m - 1];
                fm.appendChild(o);
            }

            gridApi = agGrid.createGrid(document.getElementById('repGrid'), gridOptions);

            ['fYear', 'fMonth', 'fPartner', 'fDocType', 'fStatus'].forEach(id =>
                document.getElementById(id).addEventListener('change', loadReport));

            document.getElementById('btnManagePartners').addEventListener('click', () => {
                pmAlert('');
                new bootstrap.Modal(document.getElementById('pmModal')).show();
            });
            document.getElementById('pmAddBtn').addEventListener('click', pmAddPartner);
            document.getElementById('pmSelPartner').addEventListener('change', pmOnSelectPartner);
            document.getElementById('pmSelYear').addEventListener('change', pmLoadShares);
            document.getElementById('pmSaveBtn').addEventListener('click', pmSavePartner);
            document.getElementById('pmModal').addEventListener('hidden.bs.modal', loadReport);

            loadPartners().then(loadReport);
        });
    </script>
</body>

</html>
