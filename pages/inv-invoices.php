<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// فعلاً فقط کاربر id=1 — ماژولِ فاکتور در حالِ ساخت است.
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/crm_access.php';
if (!crmModuleAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاکتورهای فروش - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>

    <style>
        .crm-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin: 1rem 0 .75rem;
        }

        .crm-toolbar .crm-search {
            flex: 1;
            min-width: 200px;
            max-width: 320px;
        }

        .crm-toolbar .spacer {
            flex: 1;
        }

        .ag-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 9px;
            border: 1px solid #e9e9e9;
            background: #fff;
            cursor: pointer;
            font-size: .8rem;
            margin: 0 2px;
            transition: background .12s, border-color .12s;
        }

        .ag-action-btn:hover {
            background: rgba(142, 87, 254, .12);
            border-color: rgba(142, 87, 254, .12);
        }

        .ag-action-btn.edit {
            color: #8e57fe;
        }

        .ag-action-btn.approve {
            color: #0f7a57;
        }

        .ag-action-btn.cancel {
            color: #d97706;
        }

        .ag-action-btn.del {
            color: #dc2626;
        }

        .ag-action-btn.print {
            color: #2563eb;
        }

        .ag-action-btn.view {
            color: #0f7a57;
        }

        /* هر ردیفِ فاکتور با کلیک باز می‌شود (به‌جز کلیک روی دکمه‌های عملیات) */
        .ag-theme-alpine .ag-row {
            cursor: pointer;
        }

        :root[data-theme="dark"] .ag-action-btn {
            background: var(--surface);
            border-color: var(--border-soft);
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .ag-action-btn:hover {
            background: rgba(142, 87, 254, .18);
        }

        .st-badge {
            display: inline-block;
            padding: 0 10px;
            line-height: 1.7;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 500;
        }

        .num-ltr {
            direction: ltr;
            display: inline-block;
            letter-spacing: 0;
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

        .ag-theme-alpine,
        .ag-theme-alpine .ag-cell,
        .ag-theme-alpine .ag-header-cell,
        .ag-theme-alpine .ag-paging-panel {
            letter-spacing: 0 !important;
            word-spacing: 0 !important;
        }

        .crm-toolbar .sb-trigger {
            height: 38px !important;
        }
        /* فوترِ ثابت ته پنجره (نه sticky) تا همیشه دیده شود و صفحه اسکرول نخورد.
           جدول دقیقاً فضایِ بینِ تولبار و فوتر را می‌گیرد؛ ردیف‌هایِ زیاد
           داخلِ خودِ جدول اسکرول می‌شوند، نه کلِ صفحه. */
        .site-footer { position: fixed !important; left: 0; right: 0; bottom: 0; top: auto !important; margin-top: 0 !important; z-index: 80; }
        body { padding-bottom: 46px; }
        .grid-fill { height: calc(100vh - 340px); min-height: 260px; padding-top: 1rem; }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container" style="margin-top:70px">

        <div class="filters-wrapper two-col">
            <div class="filters-title-col">
                <h1><i class="bi bi-receipt"></i> فاکتورهای فروش</h1>
                <p>صدور، تأیید و چاپِ فاکتورِ فروش — سازمانِ ۱</p>
            </div>
        </div>

        <div class="crm-toolbar">
            <input type="text" id="crmSearch" class="form-control crm-search" placeholder="جست‌وجو در شماره یا نامِ مشتری…">
            <select id="stFilter" class="form-select" style="max-width:170px">
                <option value="">همه‌ی وضعیت‌ها</option>
                <option value="draft">پیش‌نویس</option>
                <option value="approved">تأییدشده</option>
                <option value="cancelled">باطل‌شده</option>
            </select>
            <div class="spacer"></div>
            <a class="btn btn-primary" href="/pages/inv-invoice-edit.php"><i class="bi bi-plus-lg ms-1"></i> فاکتور جدید</a>
        </div>

        <div id="invGrid" class="ag-theme-alpine grid-fill" style="width:100%;"></div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        const API = '/crm/api';

        function tok() {
            return localStorage.getItem('auth_token');
        }

        function ahj() {
            return {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + tok()
            };
        }

        // تاریخِ میلادیِ "YYYY-MM-DD" → شمسی با رقمِ فارسی
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

        function money(n) {
            return faDigits(String(Math.round(Number(n) || 0).toLocaleString('en-US')));
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

        const ST_LABEL = {
            draft: 'پیش‌نویس',
            approved: 'تأییدشده',
            cancelled: 'باطل'
        };
        const TYPE_LABEL = {
            official: 'رسمی',
            proforma: 'پیش‌فاکتور'
        };
        let gridApi = null,
            allRows = [],
            searchTimer;

        function actionCell(p) {
            const d = p.data;
            let h = `<button class="ag-action-btn view" title="مشاهدهٔ فاکتور" onclick="location.href='/pages/inv-invoice-print.php?id=${d.id}'"><i class="bi bi-eye"></i></button>`;
            h += `<button class="ag-action-btn print" title="چاپ" onclick="location.href='/pages/inv-invoice-print.php?id=${d.id}&print=1'"><i class="bi bi-printer"></i></button>`;
            if (d.status === 'draft') {
                h += `<button class="ag-action-btn edit" title="ویرایش" onclick="location.href='/pages/inv-invoice-edit.php?id=${d.id}'"><i class="bi bi-pencil"></i></button>`;
                h += `<button class="ag-action-btn approve" title="تأیید و صدور" onclick="approveInv(${d.id})"><i class="bi bi-check2-circle"></i></button>`;
                h += `<button class="ag-action-btn del" title="حذف" onclick="deleteInv(${d.id})"><i class="bi bi-trash"></i></button>`;
            } else if (d.status === 'approved') {
                h += `<button class="ag-action-btn cancel" title="ابطال" onclick="cancelInv(${d.id})"><i class="bi bi-x-octagon"></i></button>`;
            }
            if (d.doc_type === 'proforma' && d.status !== 'cancelled') {
                if (d.converted_to_id) {
                    h += `<button class="ag-action-btn" style="color:#2563eb" title="مشاهده‌ی فاکتور رسمی" onclick="location.href='/pages/inv-invoice-print.php?id=${d.converted_to_id}'"><i class="bi bi-box-arrow-up-left"></i></button>`;
                } else {
                    h += `<button class="ag-action-btn" style="color:#0f7a57" title="تبدیل به فاکتور رسمی" onclick="convertInv(${d.id})"><i class="bi bi-file-earmark-check"></i></button>`;
                }
            }
            return h;
        }

        const colDefs = [{
                headerName: 'شماره',
                field: 'number',
                width: 130,
                cellRenderer: p => p.value ? faDigits(p.value) : '<span class="st-badge draft">پیش‌نویس</span>'
            },
            {
                headerName: 'نوع',
                field: 'doc_type',
                width: 100,
                cellRenderer: p => TYPE_LABEL[p.value] || p.value
            },
            {
                headerName: 'تاریخ صدور',
                field: 'issue_date',
                width: 120,
                cellRenderer: p => jDate(p.value || (p.data.created_at || '').split(' ')[0])
            },
            {
                headerName: 'مشتری',
                field: 'customer_name',
                flex: 2,
                minWidth: 160
            },
            {
                headerName: 'مبلغ کل (ریال)',
                field: 'total_amount',
                width: 150,
                type: 'rightAligned',
                cellRenderer: p => money(p.value)
            },
            {
                headerName: 'وضعیت',
                field: 'status',
                width: 120,
                cellRenderer: p => p.data.converted_to_id ?
                    '<span class="st-badge" style="background:rgba(37,99,235,.14);color:#2563eb">تبدیل‌شده</span>' :
                    `<span class="st-badge ${p.value}">${ST_LABEL[p.value]||p.value}</span>`
            },
            {
                headerName: 'عملیات',
                width: 244,
                sortable: false,
                filter: false,
                cellRenderer: actionCell
            },
        ];

        const gridOptions = {
            theme: AgGridFa.theme(), // پایهٔ مشترک در assets/js/ag-grid-fa.js
            columnDefs: colDefs,
            rowData: [],
            enableRtl: true,
            animateRows: true,
            pagination: true,
            paginationPageSize: 20,
            paginationPageSizeSelector: [20, 50, 100],
            onPaginationChanged: () => AgGridFa.persianizePaging(),
            onRowClicked: (e) => {
                // کلیک روی دکمه‌های ستونِ «عملیات» نباید صفحه را عوض کند
                if (e.event && e.event.target.closest && e.event.target.closest('.ag-action-btn')) return;
                // اگر کاربر متنی را انتخاب کرده، رهایش کن
                if (window.getSelection && String(window.getSelection()).length > 0) return;
                location.href = '/pages/inv-invoice-print.php?id=' + e.data.id;
            },
            defaultColDef: {
                resizable: true,
                sortable: true
            },
            overlayNoRowsTemplate: '<span class="text-muted">فاکتوری یافت نشد</span>',
        };

        async function loadAll() {
            gridApi.showLoadingOverlay();
            try {
                let page = 1,
                    out = [],
                    total = Infinity;
                while (out.length < total) {
                    const d = await apiGet('/inv/invoices?per=200&page=' + page);
                    out = out.concat(d.items || []);
                    total = d.total || 0;
                    if (!d.items || !d.items.length) break;
                    page++;
                }
                allRows = out;
                applyFilter();
            } catch (e) {
                showToast(e.message || 'خطا در بارگذاری', 'error');
                gridApi.setGridOption('rowData', []);
            }
        }

        function applyFilter() {
            const q = document.getElementById('crmSearch').value.trim().toLowerCase();
            const st = document.getElementById('stFilter').value;
            let rows = allRows;
            if (st) rows = rows.filter(r => r.status === st);
            if (q) rows = rows.filter(r =>
                (r.number || '').toLowerCase().includes(q) ||
                (r.customer_name || '').toLowerCase().includes(q));
            gridApi.setGridOption('rowData', rows);
        }

        function approveInv(id) {
            uiConfirm('این فاکتور تأیید و شماره‌ی رسمی برایش صادر شود؟ (پس از تأیید قابلِ ویرایش نیست)', async () => {
                try {
                    const d = await apiSend('POST', '/inv/invoices/' + id + '/approve');
                    showToast('صادر شد: ' + faDigits(d.number || ''), 'success');
                    loadAll();
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            });
        }

        function cancelInv(id) {
            uiConfirm('این فاکتور باطل شود؟ موجودیِ کالاها به انبار برمی‌گردد.', async () => {
                try {
                    await apiSend('POST', '/inv/invoices/' + id + '/cancel');
                    showToast('باطل شد', 'success');
                    loadAll();
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            });
        }

        function convertInv(id) {
            uiConfirm('از این پیش‌فاکتور یک «فاکتور رسمیِ پیش‌نویس» ساخته شود؟ پیش‌فاکتور بایگانی می‌شود و دیگر موجودی رزرو نمی‌کند.', async () => {
                try {
                    const d = await apiSend('POST', '/inv/invoices/' + id + '/to-official');
                    showToast('فاکتور رسمی ساخته شد', 'success');
                    location.href = '/pages/inv-invoice-edit.php?id=' + d.id;
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            });
        }

        function deleteInv(id) {
            uiConfirm('این پیش‌نویس حذف شود؟', async () => {
                try {
                    await apiSend('DELETE', '/inv/invoices/' + id);
                    showToast('حذف شد', 'success');
                    loadAll();
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (!tok()) {
                location.href = '../index.php';
                return;
            }
            gridApi = agGrid.createGrid(document.getElementById('invGrid'), gridOptions);
            document.getElementById('crmSearch').addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(applyFilter, 200);
            });
            document.getElementById('stFilter').addEventListener('change', applyFilter);
            loadAll();
        });
    </script>
</body>

</html>
