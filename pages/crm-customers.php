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

// دسترسیِ نوشتن: همان قاعده‌ی سرویسِ Go — یکی از این دو مجوز کافی است.
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
    <title>مشتریان - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
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

        .ctype-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 600;
        }

        .ctype-badge.legal {
            background: rgba(142, 87, 254, .12);
            color: #6d3ed6;
        }

        .ctype-badge.individual {
            background: rgba(16, 122, 87, .12);
            color: #0f7a57;
        }

        :root[data-theme="dark"] .ctype-badge.legal {
            color: #b79bff;
        }

        :root[data-theme="dark"] .ctype-badge.individual {
            color: #5fd0a8;
        }

        #importResult {
            font-size: .8rem;
            margin-top: .5rem;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container" style="margin-top:70px">

        <div class="filters-wrapper two-col">
            <div class="filters-title-col">
                <h1><i class="bi bi-people"></i> مشتریان</h1>
                <p>فهرست مشتریانِ حقیقی و حقوقی — مشترک بین CRM و فاکتور رسمی</p>
            </div>
        </div>

        <div class="crm-toolbar">
            <input type="text" id="crmSearch" class="form-control crm-search" placeholder="جست‌وجو در نام، تلفن، موبایل، کد ملی…">
            <div class="spacer"></div>
            <?php if ($canWrite): ?>
                <button class="btn btn-primary" id="btnAdd"><i class="bi bi-plus-lg ms-1"></i> مشتری جدید</button>
                <button class="btn btn-outline-secondary" id="btnImport"><i class="bi bi-file-earmark-spreadsheet ms-1"></i> بارگذاری از اکسل</button>
                <a class="btn btn-link btn-sm" href="<?= asset('/assets/templates/customers-template.xlsx') ?>" download>دریافت الگوی اکسل</a>
                <input type="file" id="excelFile" accept=".xlsx,.xls" hidden>
            <?php endif; ?>
        </div>
        <div id="importResult"></div>

        <div id="crmGrid" class="ag-theme-alpine" style="height:600px; width:100%;"></div>
    </div>

    <!-- مودالِ افزودن/ویرایش -->
    <div class="modal fade" id="custModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header-custom modal-header border-0">
                    <h5 class="modal-title text-white"><i class="bi bi-person-vcard ms-2"></i><span id="custModalTitle">مشتری جدید</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="custModalAlert"></div>
                    <form id="custForm" novalidate>
                        <input type="hidden" id="f_id">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">نوع <span class="text-danger">*</span></label>
                                <select class="form-select" id="f_type">
                                    <option value="legal">حقوقی (شرکت)</option>
                                    <option value="individual">حقیقی (شخص)</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">نام <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="f_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تلفن</label>
                                <input type="text" class="form-control" id="f_phone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">موبایل</label>
                                <input type="text" class="form-control" id="f_mobile">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">کد / شناسه ملی</label>
                                <input type="text" class="form-control" id="f_national_id">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">کد اقتصادی</label>
                                <input type="text" class="form-control" id="f_economic_code">
                            </div>
                            <div class="col-12">
                                <label class="form-label">آدرس</label>
                                <textarea class="form-control" id="f_address" rows="2"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" id="btnSave">ذخیره</button>
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

        function faDigits(s) {
            return String(s == null ? '' : s).replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
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

        const TYPE_LABEL = {
            legal: 'حقوقی',
            individual: 'حقیقی'
        };
        let gridApi = null;
        let allRows = [];
        let custModal;
        let searchTimer;

        function actionCell(p) {
            if (!CAN_WRITE) return '';
            return `
                <button class="ag-action-btn edit" title="ویرایش" onclick="openEdit(${p.data.id})"><i class="bi bi-pencil"></i></button>
                <button class="ag-action-btn del" title="حذف" onclick="removeRow(${p.data.id})"><i class="bi bi-trash"></i></button>`;
        }

        const colDefs = [{
                headerName: 'نوع',
                field: 'type',
                width: 110,
                cellRenderer: p => `<span class="ctype-badge ${p.value}">${TYPE_LABEL[p.value]||p.value}</span>`
            },
            {
                headerName: 'نام',
                field: 'name',
                flex: 2,
                minWidth: 180
            },
            {
                headerName: 'تلفن',
                field: 'phone',
                width: 140,
                cellRenderer: p => faDigits(p.value) || '—'
            },
            {
                headerName: 'موبایل',
                field: 'mobile',
                width: 140,
                cellRenderer: p => faDigits(p.value) || '—'
            },
            {
                headerName: 'کد / شناسه ملی',
                field: 'national_id',
                width: 150,
                cellRenderer: p => faDigits(p.value) || '—'
            },
            {
                headerName: 'کد اقتصادی',
                field: 'economic_code',
                width: 140,
                cellRenderer: p => faDigits(p.value) || '—'
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
            theme: agGrid.themeQuartz.withParams({
                fontFamily: "'Vazirmatn', Tahoma, sans-serif",
                fontSize: 13,
                headerBackgroundColor: '#e9e9e9',
            }),
            columnDefs: colDefs,
            rowData: [],
            enableRtl: true,
            animateRows: true,
            pagination: true,
            paginationPageSize: 20,
            paginationPageSizeSelector: [20, 50, 100],
            defaultColDef: {
                resizable: true,
                sortable: true,
                filter: true
            },
            overlayNoRowsTemplate: '<span class="text-muted">مشتری‌ای یافت نشد</span>',
        };

        async function loadAll() {
            gridApi.showLoadingOverlay();
            try {
                let page = 1,
                    out = [],
                    total = Infinity;
                while (out.length < total) {
                    const d = await apiGet('/customers?per=200&page=' + page);
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
                (r.phone || '').includes(q) ||
                (r.mobile || '').includes(q) ||
                (r.national_id || '').includes(q));
            gridApi.setGridOption('rowData', rows);
        }

        function openAdd() {
            document.getElementById('custForm').reset();
            document.getElementById('f_id').value = '';
            document.getElementById('custModalTitle').textContent = 'مشتری جدید';
            document.getElementById('custModalAlert').innerHTML = '';
            custModal.show();
        }

        function openEdit(id) {
            const r = allRows.find(x => x.id === id);
            if (!r) return;
            document.getElementById('f_id').value = r.id;
            document.getElementById('f_type').value = r.type || 'legal';
            document.getElementById('f_name').value = r.name || '';
            document.getElementById('f_phone').value = r.phone || '';
            document.getElementById('f_mobile').value = r.mobile || '';
            document.getElementById('f_national_id').value = r.national_id || '';
            document.getElementById('f_economic_code').value = r.economic_code || '';
            document.getElementById('f_address').value = r.address || '';
            document.getElementById('custModalTitle').textContent = 'ویرایش مشتری';
            document.getElementById('custModalAlert').innerHTML = '';
            custModal.show();
        }

        async function saveCust() {
            const id = document.getElementById('f_id').value;
            const body = {
                type: document.getElementById('f_type').value,
                name: document.getElementById('f_name').value.trim(),
                phone: document.getElementById('f_phone').value.trim(),
                mobile: document.getElementById('f_mobile').value.trim(),
                national_id: document.getElementById('f_national_id').value.trim(),
                economic_code: document.getElementById('f_economic_code').value.trim(),
                address: document.getElementById('f_address').value.trim(),
            };
            if (!body.name) {
                document.getElementById('custModalAlert').innerHTML =
                    '<div class="alert alert-danger py-2">نام مشتری الزامی است</div>';
                return;
            }
            try {
                if (id) await apiSend('PUT', '/customers/' + id, body);
                else await apiSend('POST', '/customers', body);
                custModal.hide();
                showToast('ذخیره شد', 'success');
                loadAll();
            } catch (e) {
                document.getElementById('custModalAlert').innerHTML =
                    '<div class="alert alert-danger py-2">' + (e.message || 'خطا') + '</div>';
            }
        }

        function removeRow(id) {
            uiConfirm('این مشتری حذف شود؟', async () => {
                try {
                    await apiSend('DELETE', '/customers/' + id, {});
                    showToast('حذف شد', 'success');
                    loadAll();
                } catch (e) {
                    showToast(e.message || 'خطا در حذف', 'error');
                }
            });
        }

        // ── بارگذاری از اکسل ──
        const COLMAP = {
            'نوع': 'type',
            'نام': 'name',
            'تلفن': 'phone',
            'موبایل': 'mobile',
            'کد/شناسه ملی': 'national_id',
            'کد / شناسه ملی': 'national_id',
            'کد اقتصادی': 'economic_code',
            'آدرس': 'address',
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
                            if (key) o[key] = String(obj[k]).trim();
                        }
                        return o;
                    }).filter(o => o.name);
                    if (!rows.length) {
                        box.innerHTML = '<span class="text-danger">هیچ ردیفِ معتبری پیدا نشد (ستونِ «نام» لازم است).</span>';
                        return;
                    }
                    box.innerHTML = 'در حال ارسال ' + faDigits(rows.length) + ' ردیف…';
                    const d = await apiSend('POST', '/customers/import', {
                        rows
                    });
                    let msg = '<span class="text-success">' + faDigits(d.inserted) + ' مشتری افزوده شد.</span>';
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
            custModal = new bootstrap.Modal(document.getElementById('custModal'));

            document.getElementById('crmSearch').addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(applyFilter, 200);
            });

            if (CAN_WRITE) {
                document.getElementById('btnAdd').addEventListener('click', openAdd);
                document.getElementById('btnSave').addEventListener('click', saveCust);
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
