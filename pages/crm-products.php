<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';


// فعلاً فقط کاربر id=1 — ماژولِ CRM/فاکتور در حالِ ساخت است و برای بقیه دیده نمی‌شود.
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/crm_access.php';
if (!crmModuleAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}

$canWrite = false;
try {
    $st = $db->prepare("SELECT (COALESCE(is_create_official_invoice,0) = 1 OR COALESCE(is_sales_manager,0) = 1) AS w FROM users WHERE id = ?");
    $st->execute([(int) $user_id]);
    $canWrite = (bool) $st->fetchColumn();
} catch (Throwable $e) {
    $canWrite = false;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کالاها - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/xlsx.full.min.js') ?>"></script>

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
            min-width: 220px;
            max-width: 360px;
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
            transition: background .12s, border-color .12s;
            margin: 0 2px;
        }

        .ag-action-btn:hover {
            background: rgba(142, 87, 254, .12);
            border-color: rgba(142, 87, 254, .12);
        }

        .ag-action-btn.edit {
            color: #8e57fe;
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

        .tag-pill {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 500;
            background: rgba(142, 87, 254, .12);
            color: #6d3ed6;
        }

        :root[data-theme="dark"] .tag-pill {
            color: #b79bff;
        }

        .num-ltr {
            direction: ltr;
            display: inline-block;
            letter-spacing: 0;
        }

        #prodModal .form-check.form-switch.switch-inline {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0;
            margin: 0;
        }

        #prodModal .switch-inline .form-check-input {
            margin: 0;
            float: none;
        }

        #prodModal .switch-inline .form-check-label {
            order: -1;
        }

        .stock-low {
            color: #dc2626;
            font-weight: 700;
        }

        #importResult {
            font-size: .8rem;
            margin-top: .5rem;
        }

        .modal .modal-header.modal-header-custom {
            background: #8e57fe !important;
            color: #fff !important;
            border-radius: .4rem .4rem 0 0;
            padding: .9rem 1.1rem;
            border-bottom: 0;
        }

        .modal .modal-header-custom .modal-title,
        .modal .modal-header-custom .modal-title * {
            color: #fff !important;
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
                <h1><i class="bi bi-box-seam"></i> کالاها</h1>
                <p>کالاها و خدماتِ قابل درج در فاکتور — به‌همراه موجودیِ انبارِ فاکتور رسمی</p>
            </div>
        </div>

        <div class="crm-toolbar">
            <input type="text" id="crmSearch" class="form-control crm-search" placeholder="جست‌وجو در نام یا کد کالا…">
            <div class="spacer"></div>
            <?php if ($canWrite): ?>
                <button class="btn btn-primary" id="btnAdd"><i class="bi bi-plus-lg ms-1"></i> کالای جدید</button>
                <button class="btn btn-outline-secondary" id="btnImport"><i class="bi bi-file-earmark-spreadsheet ms-1"></i> بارگذاری از اکسل</button>
                <a class="btn btn-link btn-sm" href="<?= asset('/assets/templates/products-template.xlsx') ?>" download>دریافت الگوی اکسل</a>
                <input type="file" id="excelFile" accept=".xlsx,.xls" hidden>
            <?php endif; ?>
        </div>
        <div id="importResult"></div>

        <div id="crmGrid" class="ag-theme-alpine grid-fill" style="width:100%;"></div>
    </div>

    <div class="modal fade" id="prodModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header-custom modal-header border-0">
                    <h5 class="modal-title text-white"><i class="bi bi-box-seam ms-2"></i><span id="prodModalTitle">کالای جدید</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="prodModalAlert"></div>
                    <form id="prodForm" novalidate>
                        <input type="hidden" id="f_id">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">کد کالا <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="f_code" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">نام کالا <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="f_name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">واحد</label>
                                <input type="text" class="form-control" id="f_unit" placeholder="عدد">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">قیمت واحد (ریال) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="f_unit_price" inputmode="numeric">
                            </div>
                            <div class="col-md-4" id="openingWrap">
                                <label class="form-label">موجودی اولیه</label>
                                <input type="text" class="form-control" id="f_opening" inputmode="decimal">
                                <div class="form-text">فقط هنگام ساخت. انبارِ «فاکتور رسمی».</div>
                            </div>
                            <div class="col-12 d-flex gap-4 pt-1">
                                <div class="form-check form-switch switch-inline">
                                    <input class="form-check-input" type="checkbox" id="f_is_service">
                                    <label class="form-check-label" for="f_is_service">خدمت است (نه کالای فیزیکی)</label>
                                </div>
                                <div class="form-check form-switch switch-inline">
                                    <input class="form-check-input" type="checkbox" id="f_is_tax_exempt">
                                    <label class="form-check-label" for="f_is_tax_exempt">معاف از مالیات</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary btn-lg px-5" id="btnSave">ذخیره</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        const CAN_WRITE = <?= $canWrite ? 'true' : 'false' ?>;
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

        function toEnDigits(s) {
            return String(s == null ? '' : s)
                .replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
                .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
        }

        function parseIntFa(v) {
            const s = toEnDigits(v).replace(/[,٬\s]/g, '').replace(/[^\d-]/g, '');
            const n = parseInt(s, 10);
            return isNaN(n) ? 0 : n;
        }

        function parseFloatFa(v) {
            const s = toEnDigits(v).replace(/[,٬\s]/g, '').replace(/[^\d.-]/g, '');
            const n = parseFloat(s);
            return isNaN(n) ? 0 : n;
        }

        function truthyFa(v) {
            return ['1', 'بله', 'بلی', 'معاف', 'true', 'yes', 'y', 'on'].includes(toEnDigits(v).trim().toLowerCase());
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
                body: JSON.stringify(body)
            });
            const d = await r.json().catch(() => ({}));
            if (!r.ok || d.success === false) throw new Error(d.message || ('خطای ' + r.status));
            return d;
        }

        let gridApi = null;
        let allRows = [];
        let prodModal;
        let searchTimer;

        function actionCell(p) {
            if (!CAN_WRITE) return '';
            return `
                <button class="ag-action-btn edit" title="ویرایش" onclick="openEdit(${p.data.id})"><i class="bi bi-pencil"></i></button>
                <button class="ag-action-btn del" title="حذف" onclick="removeRow(${p.data.id})"><i class="bi bi-trash"></i></button>`;
        }

        const colDefs = [{
                headerName: 'کد',
                field: 'code',
                width: 110,
                cellRenderer: p => faDigits(p.value) || '—'
            },
            {
                headerName: 'نام کالا',
                field: 'name',
                flex: 2,
                minWidth: 180
            },
            {
                headerName: 'واحد',
                field: 'unit',
                width: 90
            },
            {
                headerName: 'قیمت واحد (ریال)',
                field: 'unit_price',
                width: 160,
                type: 'rightAligned',
                cellRenderer: p => money(p.value)
            },
            {
                headerName: 'موجودی انبار',
                field: 'stock',
                width: 110,
                type: 'rightAligned',
                cellRenderer: p => faDigits(Number(p.value || 0))
            },
            {
                headerName: 'رزرو',
                field: 'reserved',
                width: 90,
                type: 'rightAligned',
                cellRenderer: p => {
                    const v = Number(p.value || 0);
                    return v > 0 ? `<span style="color:#d97706;font-weight:600">${faDigits(v)}</span>` : '<span class="text-muted">۰</span>';
                }
            },
            {
                headerName: 'قابل‌فروش',
                field: 'available',
                width: 110,
                type: 'rightAligned',
                cellRenderer: p => {
                    const v = Number(p.value || 0);
                    const t = faDigits(v);
                    return v <= 0 ? `<span class="stock-low">${t}</span>` : t;
                }
            },
            {
                headerName: 'نوع',
                field: 'is_service',
                width: 100,
                cellRenderer: p => p.value ? '<span class="tag-pill">خدمت</span>' : 'کالا'
            },
            {
                headerName: 'مالیات',
                field: 'is_tax_exempt',
                width: 100,
                cellRenderer: p => p.value ? '<span class="tag-pill">معاف</span>' : 'مشمول'
            },
            {
                headerName: 'عملیات',
                width: 120,
                sortable: false,
                filter: false,
                hide: !CAN_WRITE,
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
            defaultColDef: {
                resizable: true,
                sortable: true
            },
            overlayNoRowsTemplate: '<span class="text-muted">کالایی یافت نشد</span>',
        };

        async function loadAll() {
            gridApi.showLoadingOverlay();
            try {
                let page = 1,
                    out = [],
                    total = Infinity;
                while (out.length < total) {
                    const d = await apiGet('/inv/products?per=200&page=' + page);
                    out = out.concat(d.items || []);
                    total = d.total || 0;
                    if (!d.items || d.items.length === 0) break;
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
            const rows = !q ? allRows : allRows.filter(r =>
                (r.name || '').toLowerCase().includes(q) ||
                (r.code || '').toLowerCase().includes(q));
            gridApi.setGridOption('rowData', rows);
        }

        function openAdd() {
            document.getElementById('prodForm').reset();
            document.getElementById('f_id').value = '';
            document.getElementById('openingWrap').style.display = '';
            document.getElementById('prodModalTitle').textContent = 'کالای جدید';
            document.getElementById('prodModalAlert').innerHTML = '';
            prodModal.show();
        }

        function openEdit(id) {
            const r = allRows.find(x => x.id === id);
            if (!r) return;
            document.getElementById('f_id').value = r.id;
            document.getElementById('f_code').value = faDigits(r.code || '');
            document.getElementById('f_name').value = r.name || '';
            document.getElementById('f_unit').value = r.unit || '';
            document.getElementById('f_unit_price').value = money(r.unit_price || 0);
            document.getElementById('f_opening').value = '';
            document.getElementById('f_is_service').checked = !!r.is_service;
            document.getElementById('f_is_tax_exempt').checked = !!r.is_tax_exempt;
            document.getElementById('openingWrap').style.display = 'none';
            document.getElementById('prodModalTitle').textContent = 'ویرایش کالا';
            document.getElementById('prodModalAlert').innerHTML = '';
            prodModal.show();
        }

        async function saveProd() {
            const id = document.getElementById('f_id').value;
            const body = {
                code: document.getElementById('f_code').value.trim(),
                name: document.getElementById('f_name').value.trim(),
                unit: document.getElementById('f_unit').value.trim(),
                unit_price: parseIntFa(document.getElementById('f_unit_price').value),
                is_service: document.getElementById('f_is_service').checked,
                is_tax_exempt: document.getElementById('f_is_tax_exempt').checked,
            };
            const errs = [];
            if (!body.name) errs.push('نام کالا');
            if (!body.code) errs.push('کد کالا');
            if (!(body.unit_price > 0)) errs.push('قیمت واحد');
            if (errs.length) {
                document.getElementById('prodModalAlert').innerHTML =
                    '<div class="alert alert-danger py-2">این فیلدها الزامی‌اند: ' + errs.join('، ') + '</div>';
                return;
            }
            try {
                if (id) {
                    await apiSend('PUT', '/inv/products/' + id, body);
                } else {
                    const op = parseFloatFa(document.getElementById('f_opening').value);
                    if (op) body.opening_stock = op;
                    await apiSend('POST', '/inv/products', body);
                }
                prodModal.hide();
                showToast('ذخیره شد', 'success');
                loadAll();
            } catch (e) {
                document.getElementById('prodModalAlert').innerHTML =
                    '<div class="alert alert-danger py-2">' + (e.message || 'خطا') + '</div>';
            }
        }

        function removeRow(id) {
            uiConfirm('این کالا حذف شود؟', async () => {
                try {
                    await apiSend('DELETE', '/inv/products/' + id, {});
                    showToast('حذف شد', 'success');
                    loadAll();
                } catch (e) {
                    showToast(e.message || 'خطا در حذف', 'error');
                }
            });
        }

        const COLMAP = {
            'کد': 'code',
            'نام کالا': 'name',
            'نام': 'name',
            'واحد': 'unit',
            'قیمت واحد (ریال)': 'unit_price',
            'قیمت واحد': 'unit_price',
            'معاف از مالیات': 'is_tax_exempt',
            'موجودی اولیه': 'opening_stock',
        };

        function handleExcel(file) {
            const box = document.getElementById('importResult');
            box.innerHTML = 'در حال خواندن فایل…';
            const reader = new FileReader();
            reader.onload = async e => {
                try {
                    const wb = XLSX.read(e.target.result, {
                        type: 'array'
                    });
                    const ws = wb.Sheets[wb.SheetNames[0]];
                    const raw = XLSX.utils.sheet_to_json(ws, {
                        defval: ''
                    });
                    const rows = raw.map(obj => {
                        const o = {};
                        for (const k in obj) {
                            const key = COLMAP[String(k).trim()];
                            if (!key) continue;
                            const val = obj[k];
                            if (key === 'unit_price') o.unit_price = parseIntFa(val);
                            else if (key === 'opening_stock') {
                                const n = parseFloatFa(val);
                                if (n) o.opening_stock = n;
                            } else if (key === 'is_tax_exempt') o.is_tax_exempt = truthyFa(val);
                            else o[key] = String(val).trim();
                        }
                        return o;
                    }).filter(o => o.name);
                    if (!rows.length) {
                        box.innerHTML = '<span class="text-danger">هیچ ردیفِ معتبری پیدا نشد (ستونِ «نام کالا» لازم است).</span>';
                        return;
                    }
                    box.innerHTML = 'در حال ارسال ' + faDigits(rows.length) + ' ردیف…';
                    const d = await apiSend('POST', '/inv/products/import', {
                        rows
                    });
                    let msg = '<span class="text-success">' + faDigits(d.inserted) + ' کالا افزوده شد.</span>';
                    if (d.errors && d.errors.length) {
                        msg += '<br><span class="text-danger">' + faDigits(d.errors.length) + ' ردیف رد شد: ' +
                            d.errors.slice(0, 5).map(x => 'ردیف ' + faDigits(x.row) + ' (' + x.message + ')').join('، ') +
                            (d.errors.length > 5 ? ' …' : '') + '</span>';
                    }
                    box.innerHTML = msg;
                    loadAll();
                } catch (err) {
                    box.innerHTML = '<span class="text-danger">' + (err.message || 'خطا در پردازشِ فایل') + '</span>';
                }
            };
            reader.readAsArrayBuffer(file);
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (!tok()) {
                location.href = '../index.php';
                return;
            }
            gridApi = agGrid.createGrid(document.getElementById('crmGrid'), gridOptions);
            prodModal = new bootstrap.Modal(document.getElementById('prodModal'));
            document.getElementById('prodModal').addEventListener('shown.bs.modal', () => {
                const el = document.querySelector('#prodModal .modal-body input:not([type=hidden]), #prodModal .modal-body select, #prodModal .modal-body textarea');
                if (el) el.focus();
            });

            // اعدادِ فیلدها هنگام خروج از فوکوس فارسی شوند
            ['f_code', 'f_opening'].forEach(id => {
                document.getElementById(id).addEventListener('blur', e => {
                    e.target.value = faDigits(toEnDigits(e.target.value));
                });
            });
            // قیمت واحد: فارسی + جداکننده‌ی هزارگان
            document.getElementById('f_unit_price').addEventListener('blur', e => {
                const n = parseIntFa(e.target.value);
                e.target.value = n ? money(n) : '';
            });

            document.getElementById('crmSearch').addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(applyFilter, 200);
            });

            if (CAN_WRITE) {
                document.getElementById('btnAdd').addEventListener('click', openAdd);
                document.getElementById('btnSave').addEventListener('click', saveProd);
                document.getElementById('btnImport').addEventListener('click', () => document.getElementById('excelFile').click());
                document.getElementById('excelFile').addEventListener('change', e => {
                    if (e.target.files[0]) handleExcel(e.target.files[0]);
                    e.target.value = '';
                });
            }

            loadAll();
        });
    </script>
</body>

</html>
