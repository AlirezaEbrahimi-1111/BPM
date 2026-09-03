<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../config/config.php';
require_once '../includes/version.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

if (!isset($db)) {
    $database = new Database();
    $db = $database->getConnection();
}
$auth = new Auth($db);
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    header('Location: ../index.php');
    exit;
}
$__me = loadUserForPermissions($db, (int) $user_id);
if (!$__me) {
    header('Location: ../index.php');
    exit;
}
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
    <title>فاکتورهای خرید - سامانه مدیریت فرآیندها</title>

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

        .ag-action-btn.confirm {
            color: #0f7a57;
        }

        .ag-action-btn.cancel {
            color: #d97706;
        }

        .ag-action-btn.del {
            color: #dc2626;
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

        .st-badge.confirmed {
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
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container" style="margin-top:70px">

        <div class="filters-wrapper two-col">
            <div class="filters-title-col">
                <h1><i class="bi bi-cart-plus"></i> فاکتورهای خرید</h1>
                <p>ثبتِ خریدِ کالا از تأمین‌کننده — «تأیید» موجودیِ انبار را زیاد می‌کند</p>
            </div>
        </div>

        <div class="crm-toolbar">
            <input type="text" id="crmSearch" class="form-control crm-search" placeholder="جست‌وجو در شماره‌ی فروشنده یا نام…">
            <select id="stFilter" class="form-select" style="max-width:170px">
                <option value="">همه‌ی وضعیت‌ها</option>
                <option value="draft">پیش‌نویس</option>
                <option value="confirmed">تأییدشده</option>
                <option value="cancelled">باطل‌شده</option>
            </select>
            <div class="spacer"></div>
            <a class="btn btn-primary" href="/pages/inv-purchase-edit.php"><i class="bi bi-plus-lg ms-1"></i> فاکتور خرید جدید</a>
        </div>

        <div id="invGrid" class="ag-theme-alpine" style="width:100%; padding-top:1rem;"></div>
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

        function faDigits(s) {
            return String(s == null ? '' : s).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[+d]);
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

        async function apiSend(method, path) {
            const r = await fetch(API + path, {
                method,
                headers: ahj()
            });
            const d = await r.json().catch(() => ({}));
            if (!r.ok || d.success === false) throw new Error(d.message || ('خطای ' + r.status));
            return d;
        }

        const ST_LABEL = {
            draft: 'پیش‌نویس',
            confirmed: 'تأییدشده',
            cancelled: 'باطل'
        };
        let gridApi = null,
            allRows = [],
            searchTimer;

        function actionCell(p) {
            const d = p.data;
            let h = '';
            if (d.status === 'draft') {
                h += `<button class="ag-action-btn edit" title="ویرایش" onclick="location.href='/pages/inv-purchase-edit.php?id=${d.id}'"><i class="bi bi-pencil"></i></button>`;
                h += `<button class="ag-action-btn confirm" title="تأیید و افزودن به انبار" onclick="confirmP(${d.id})"><i class="bi bi-check2-circle"></i></button>`;
                h += `<button class="ag-action-btn del" title="حذف" onclick="deleteP(${d.id})"><i class="bi bi-trash"></i></button>`;
            } else if (d.status === 'confirmed') {
                h += `<button class="ag-action-btn cancel" title="ابطال (کسر از انبار)" onclick="cancelP(${d.id})"><i class="bi bi-x-octagon"></i></button>`;
            }
            return h || '—';
        }

        const colDefs = [{
                headerName: 'شماره‌ی فروشنده',
                field: 'supplier_ref_number',
                width: 150,
                cellRenderer: p => p.value ? faDigits(p.value) : '—'
            },
            {
                headerName: 'تاریخ',
                field: 'issue_date',
                width: 120,
                cellRenderer: p => jDate(p.value)
            },
            {
                headerName: 'تأمین‌کننده',
                field: 'supplier_name',
                flex: 2,
                minWidth: 160
            },
            {
                headerName: 'مبلغ کل (ریال)',
                field: 'total_amount',
                width: 150,
                type: 'rightAligned',
                cellRenderer: p => `<span style="direction:ltr;display:inline-block;letter-spacing:0;unicode-bidi:isolate">${money(p.value)}</span>`
            },
            {
                headerName: 'وضعیت',
                field: 'status',
                width: 110,
                cellRenderer: p => `<span class="st-badge ${p.value}">${ST_LABEL[p.value]||p.value}</span>`
            },
            {
                headerName: 'عملیات',
                width: 150,
                sortable: false,
                filter: false,
                cellRenderer: actionCell
            },
        ];

        const gridOptions = {
            theme: agGrid.themeQuartz.withParams({
                fontFamily: "'Vazirmatn', sans-serif",
                fontSize: 13,
                headerBackgroundColor: '#f8f9fa',
                rowHoverColor: 'rgba(142, 87, 254, 0.12)',
            }),
            columnDefs: colDefs,
            rowData: [],
            enableRtl: true,
            animateRows: true,
            domLayout: 'autoHeight',
            pagination: true,
            paginationPageSize: 20,
            paginationPageSizeSelector: [20, 50, 100],
            onPaginationChanged: () => AgGridFa.persianizePaging(),
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
                    const d = await apiGet('/inv/purchases?per=200&page=' + page);
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
                (r.supplier_ref_number || '').toLowerCase().includes(q) ||
                (r.supplier_name || '').toLowerCase().includes(q));
            gridApi.setGridOption('rowData', rows);
        }

        function confirmP(id) {
            uiConfirm('این فاکتورِ خرید تأیید شود؟ موجودیِ کالاها به انبارِ رسمی اضافه می‌شود (پس از تأیید قابلِ ویرایش نیست).', async () => {
                try {
                    await apiSend('POST', '/inv/purchases/' + id + '/confirm');
                    showToast('تأیید شد؛ موجودی به‌روزرسانی شد', 'success');
                    loadAll();
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            });
        }

        function cancelP(id) {
            uiConfirm('این فاکتورِ خرید باطل شود؟ موجودیِ اضافه‌شده از انبار کسر می‌شود.', async () => {
                try {
                    await apiSend('POST', '/inv/purchases/' + id + '/cancel');
                    showToast('باطل شد', 'success');
                    loadAll();
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            });
        }

        function deleteP(id) {
            uiConfirm('این پیش‌نویس حذف شود؟', async () => {
                try {
                    await apiSend('DELETE', '/inv/purchases/' + id);
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
