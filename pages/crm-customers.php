<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';


// فعلا فقط کاربر id=1 — ماژول CRM/فاکتور در حال ساخت است و برای بقیه دیده نمی‌شود.
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/crm_access.php';
if (!crmModuleAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}

// دسترسی نوشتن = دسترسی دیدن: هرکسی که این منو را می‌بیند، CRUD کامل دارد.
$canWrite = true;
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
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
    <script src="<?= asset('../../assets/js/search-persist.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/xlsx.full.min.js') ?>"></script>
    <script src="<?= asset('../../assets/js/iran-id-validate.js') ?>"></script>
    <script src="<?= asset('../../assets/js/entity-picker.js') ?>"></script>
    <script src="<?= asset('../../assets/js/edit-guard.js') ?>"></script>

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
            color: #6d3ed6;
        }

        .ctype-badge.individual {
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
        /* فوتر ثابت ته پنجره (نه sticky) تا همیشه دیده شود و صفحه اسکرول نخورد.
           جدول دقیقا فضای بین تولبار و فوتر را می‌گیرد؛ ردیف‌های زیاد
           داخل خود جدول اسکرول می‌شوند، نه کل صفحه. */
        .site-footer { position: fixed !important; left: 0; right: 0; bottom: 0; top: auto !important; margin-top: 0 !important; z-index: 80; }
        body { padding-bottom: 46px; }
        .grid-fill { height: calc(100vh - 340px); min-height: 260px; padding-top: 1rem; }
        /* مودال حذف/ادغام مشتری */
        .del-inv-wrap { max-height: 300px; overflow-y: auto; border: 1px solid var(--border-soft, #e9e9e9); border-radius: 10px; }
        .del-inv-table { font-size: 12.5px; }
        .del-inv-table thead th { position: sticky; top: 0; background: #f8f9fa; color: #374151 !important; font-weight: 600; z-index: 1; }
        .del-inv-table td, .del-inv-table th { padding: 6px 10px; }
        .dup-banner { margin: .75rem 0 0; border: 1px solid rgba(245, 158, 11, .35); background: rgba(245, 158, 11, .08); border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #92400e; }
        .dup-banner summary { cursor: pointer; font-weight: 600; }
        .dup-banner .dup-group { margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(245, 158, 11, .35); }
        .dup-banner .dup-item { display: flex; align-items: center; gap: 8px; padding: 3px 0; color: var(--text-strong, #2d3748); }
        .dup-banner .dup-item .meta { color: var(--text-muted, #6b7490); font-size: 12px; }
        :root[data-theme="dark"] .dup-banner { color: #fbbf24; background: rgba(251, 191, 36, .08); border-color: rgba(251, 191, 36, .3); }
        .del-summary { background: rgba(220, 38, 38, .08); border: 1px solid rgba(220, 38, 38, .25); border-radius: 10px; padding: 10px 12px; font-size: 13px; line-height: 1.9; }
        :root[data-theme="dark"] .del-inv-table thead th { background: var(--surface); color: var(--text-strong) !important; }
        :root[data-theme="dark"] .del-inv-table td { color: var(--text-strong); background: transparent; }
        :root[data-theme="dark"] .del-summary { background: rgba(248, 113, 113, .10); border-color: rgba(248, 113, 113, .3); color: var(--text-strong); }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container" style="margin-top:70px">

        <div class="filters-wrapper two-col">
            <div class="filters-title-col">
                <h1><i class="bi bi-people"></i> مشتریان</h1>
                <p>فهرست مشتریان حقیقی و حقوقی — مشترک بین CRM و فاکتور رسمی</p>
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
        <!-- مشتری‌های تکراری موجود (نام یکسان‌سازی‌شده یا شناسهٔ یکسان) — برای یکی کردن -->
        <div id="dupBanner" class="dup-banner" hidden></div>

        <div id="crmGrid" class="ag-theme-alpine grid-fill inv-grid" style="width:100%;"></div>
    </div>

    <!-- مودال افزودن/ویرایش -->
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
                                <input type="text" class="form-control" id="f_phone" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">موبایل</label>
                                <input type="text" class="form-control" id="f_mobile" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">کد / شناسه ملی <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="f_national_id" autocomplete="off" inputmode="numeric">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">شماره اقتصادی</label>
                                <input type="text" class="form-control" id="f_economic_code" autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">استان</label>
                                <input type="text" class="form-control" id="f_province">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">شهر</label>
                                <input type="text" class="form-control" id="f_city">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">کد پستی <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="f_postal_code" autocomplete="off" inputmode="numeric">
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

    <!-- حذف مشتریِ دارای فاکتور: ۱) فهرست فاکتورها + تأیید ۲) انتخاب مشتری جایگزین → انتقال و حذف -->
    <div class="modal fade" id="delModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header-custom modal-header border-0">
                    <h5 class="modal-title text-white"><i class="bi bi-person-x ms-2"></i><span id="delTitle">حذف مشتری</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="delIntro" class="mb-3"></div>
                    <div id="delPickWrap" class="mb-3" hidden>
                        <label class="form-label fw-bold">به کدام مشتری منتقل شوند؟ <span class="text-danger">*</span></label>
                        <div id="delTargetPicker"></div>
                        <div class="form-text">جست‌وجو با نام، شناسه/کد ملی یا موبایل.</div>
                    </div>
                    <div class="del-inv-wrap">
                        <table class="table table-sm align-middle mb-0 del-inv-table">
                            <thead><tr><th>شماره</th><th>نوع</th><th>تاریخ</th><th>وضعیت</th><th class="text-start">مبلغ (ریال)</th></tr></thead>
                            <tbody id="delInvBody"></tbody>
                        </table>
                    </div>
                    <div id="delOthers" class="small text-muted mt-2"></div>
                    <div id="delAlert" class="mt-2"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-danger" id="delNext">تأیید حذف و انتخاب مشتری جایگزین</button>
                    <button type="button" class="btn btn-danger" id="delDo" hidden>انتقال فاکتورها و حذف مشتری</button>
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

        function toEn(s) {
            return String(s == null ? '' : s)
                .replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
                .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
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
        // مقادیر اولیهٔ مشتری در حال ویرایش — اعتبارسنجی فقط برای فیلدهای تغییرکرده (داده‌های قدیمی
        // نامعتبر، ویرایش بقیهٔ فیلدها را قفل نکند). null = افزودن مشتری جدید → همه بررسی می‌شوند.
        let editOrig = null;

        function actionCell(p) {
            if (!CAN_WRITE) return '';
            return `
                <button class="ag-action-btn edit" title="ویرایش" onclick="openEdit(${p.data.id})"><i class="bi bi-pencil"></i></button>
                <button class="ag-action-btn del" title="حذف" onclick="removeRow(${p.data.id})"><i class="bi bi-trash"></i></button>`;
        }

        // کلیک روی نام / شناسه ملی / شماره اقتصادی / کد پستی = کپی در کلیپ‌بورد + پیام
        // (AgGridFa.copyCell مشترک). مقدار خام کپی می‌شود (رقم لاتین) تا در شایگان/مؤدیان
        // درست جای‌گذاری شود؛ نمایش با رقم فارسی می‌ماند.
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
                minWidth: 180,
                cellRenderer: p => AgGridFa.copyCell(p.value, esc(p.value), 'نام')
            },
            {
                headerName: 'کد / شناسه ملی',
                field: 'national_id',
                width: 150,
                cellRenderer: p => AgGridFa.copyCell(p.value, esc(faDigits(p.value)), 'شناسه / کد ملی')
            },
            {
                headerName: 'شماره اقتصادی',
                field: 'economic_code',
                width: 140,
                cellRenderer: p => AgGridFa.copyCell(p.value, esc(faDigits(p.value)), 'شماره اقتصادی')
            },
            {
                // به‌جای تلفن و موبایل (درخواست ۱۴۰۵/۰۷/۲۹)
                headerName: 'کد پستی',
                field: 'postal_code',
                width: 130,
                cellRenderer: p => AgGridFa.copyCell(p.value, esc(faDigits(p.value)), 'کد پستی')
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
                loadDups();
            } catch (e) {
                showToast(e.message || 'خطا در بارگذاری', 'error');
                gridApi.setGridOption('rowData', []);
            }
        }

        // ── مشتری‌های تکراری موجود (جلوی تکراری جدید را خود سرور می‌گیرد؛ این‌ها قدیمی‌اند) ──
        async function loadDups() {
            const box = document.getElementById('dupBanner');
            if (!CAN_WRITE) return;
            let d;
            try { d = await apiGet('/customers/duplicates'); } catch (e) { box.hidden = true; return; }
            const groups = d.groups || [];
            if (!groups.length) { box.hidden = true; box.innerHTML = ''; return; }
            box.innerHTML = `<details><summary><i class="bi bi-exclamation-triangle ms-1"></i>${faDigits(groups.length)} گروه مشتری تکراری پیدا شد — برای یکی کردن، روی «حذف و انتقال» مشتری اضافه بزنید تا فاکتورهایش به مشتری دیگر منتقل شود.</summary>` +
                groups.map(g => `<div class="dup-group"><div class="small mb-1">${esc(g.reason)}:</div>` +
                    g.items.map(it => `<div class="dup-item">
                        <span>${esc(it.name)}</span>
                        <span class="meta">${it.national_id ? 'شناسه ملی: ' + faDigits(it.national_id) : 'بدون شناسه ملی'}</span>
                        <button type="button" class="ag-action-btn del" title="حذف و انتقال فاکتورها به مشتری دیگر" onclick="removeRow(${it.id})"><i class="bi bi-trash"></i></button>
                    </div>`).join('') + '</div>').join('') + '</details>';
            box.hidden = false;
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
            editOrig = null;
            if (window.IranId) IranId.resetLive(document.getElementById('custForm'));
            document.getElementById('custModalTitle').textContent = 'مشتری جدید';
            document.getElementById('custModalAlert').innerHTML = '';
            custModal.show();
        }

        function openEdit(id) {
            const r = allRows.find(x => x.id === id);
            if (!r) return;
            document.getElementById('f_id').value = r.id;
            document.getElementById('f_type').value = r.type || 'legal';
            editOrig = { type: r.type || 'legal', national_id: toEn(r.national_id || ''), postal_code: toEn(r.postal_code || ''), economic_code: toEn(r.economic_code || '') };
            document.getElementById('f_name').value = r.name || '';
            document.getElementById('f_phone').value = faDigits(r.phone || '');
            document.getElementById('f_mobile').value = faDigits(r.mobile || '');
            document.getElementById('f_national_id').value = faDigits(r.national_id || '');
            document.getElementById('f_economic_code').value = faDigits(r.economic_code || '');
            document.getElementById('f_province').value = r.province || '';
            document.getElementById('f_city').value = r.city || '';
            document.getElementById('f_postal_code').value = faDigits(r.postal_code || '');
            document.getElementById('f_address').value = r.address || '';
            document.getElementById('custModalTitle').textContent = 'ویرایش مشتری';
            if (window.IranId) IranId.resetLive(document.getElementById('custForm'));
            document.getElementById('custModalAlert').innerHTML = '';
            custModal.show();
        }

        // خروجی: true اگر ذخیره شد (برای «ذخیره و خروج» پنجرهٔ ترک صفحه — EditGuard)
        // ── محافظ ترک صفحه + پیش‌نویس خودکار فرم مشتری (assets/js/edit-guard.js) ──
        // فقط وقتی پنجرهٔ «مشتری جدید / ویرایش مشتری» باز است تغییر حساب می‌شود.
        const GUARD_FIELDS = ['f_type', 'f_name', 'f_phone', 'f_mobile', 'f_national_id', 'f_economic_code',
            'f_province', 'f_city', 'f_postal_code', 'f_address'];
        let guardRestoring = false;

        function guardSnapshot() {
            if (!document.getElementById('custModal').classList.contains('show')) return null;
            const st = { id: +document.getElementById('f_id').value || 0 };
            GUARD_FIELDS.forEach(f => st[f] = document.getElementById(f).value);
            return st;
        }

        function guardRestore(st) {
            if (!st) return;
            if (st.id && !allRows.some(r => r.id === st.id)) return; // مشتری دیگر نیست
            guardRestoring = true;
            if (st.id) openEdit(st.id); else openAdd();
            GUARD_FIELDS.forEach(f => { if (st[f] != null) document.getElementById(f).value = st[f]; });
        }

        function guardLabel() {
            const n = document.getElementById('f_name').value.trim();
            return document.getElementById('f_id').value ? 'مشتری «' + n + '»' : (n ? 'مشتری جدید «' + n + '»' : 'مشتری جدید');
        }

        async function saveCust() {
            const id = document.getElementById('f_id').value;
            const body = {
                type: document.getElementById('f_type').value,
                name: document.getElementById('f_name').value.trim(),
                phone: toEn(document.getElementById('f_phone').value.trim()),
                mobile: toEn(document.getElementById('f_mobile').value.trim()),
                national_id: toEn(document.getElementById('f_national_id').value.trim()),
                economic_code: toEn(document.getElementById('f_economic_code').value.trim()),
                province: document.getElementById('f_province').value.trim(),
                city: document.getElementById('f_city').value.trim(),
                postal_code: toEn(document.getElementById('f_postal_code').value.trim()),
                address: document.getElementById('f_address').value.trim(),
            };
            const missing = [];
            if (!body.name) missing.push('نام');
            if (!body.national_id) missing.push('شناسه/کد ملی');
            if (!body.postal_code) missing.push('کد پستی');
            if (missing.length) {
                document.getElementById('custModalAlert').innerHTML =
                    '<div class="alert alert-danger py-2">این فیلدها الزامی‌اند: ' + missing.join('، ') + '</div>';
                return false;
            }
            // اعتبارسنجی شناسهٔ ملی شرکت (حقوقی) / کد ملی (حقیقی) و کد پستی — assets/js/iran-id-validate.js
            if (window.IranId) {
                const chk = { type: body.type };
                if (!editOrig || body.national_id !== editOrig.national_id || body.type !== editOrig.type) chk.national_id = body.national_id;
                if (!editOrig || body.postal_code !== editOrig.postal_code) chk.postal_code = body.postal_code;
                if (!editOrig || body.economic_code !== editOrig.economic_code) chk.economic_code = body.economic_code;
                const errs = IranId.checkCustomer(chk);
                if (errs.length) {
                    document.getElementById('custModalAlert').innerHTML =
                        '<div class="alert alert-danger py-2">' + errs.join('<br>') + '</div>';
                    return false;
                }
            }
            try {
                if (id) await apiSend('PUT', '/customers/' + id, body);
                else await apiSend('POST', '/customers', body);
                EditGuard.saved();
                custModal.hide();
                showToast('ذخیره شد', 'success');
                loadAll();
                return true;
            } catch (e) {
                document.getElementById('custModalAlert').innerHTML =
                    '<div class="alert alert-danger py-2">' + (e.message || 'خطا') + '</div>';
                return false;
            }
        }

        // ── حذف مشتری ──
        // بدون فاکتور/سابقه → حذف ساده مثل قبل. با فاکتور → ۱) فهرست فاکتورها و تأیید حذف
        // ۲) فهرست دوباره + انتخاب مشتری جایگزین (جست‌وجوپذیر، EntityPicker) → انتقال همه و حذف
        // (crm-service/customer_merge.go: GET /customers/{id}/usage و POST /customers/{id}/merge)
        const DOC_FA = { official: 'رسمی', proforma: 'پیش‌فاکتور' };
        const ST_FA = { draft: 'پیش‌نویس', approved: 'تأییدشده', cancelled: 'باطل' };
        let delId = null, delName = '', delUsage = null, delPicker = null, delModal = null;

        function delInvRows(list) {
            return list.map(i => `<tr>
                <td>${i.number ? faDigits(i.number) : '<span class="text-muted">پیش‌نویس</span>'}</td>
                <td>${DOC_FA[i.doc_type] || esc(i.doc_type)}</td>
                <td>${i.issue_date ? (window.TimeSync ? TimeSync.formatJalali(i.issue_date) : faDigits(i.issue_date)) : '—'}</td>
                <td>${ST_FA[i.status] || esc(i.status)}</td>
                <td class="text-start">${faDigits(Math.round(i.total_amount || 0).toLocaleString('en-US'))}</td>
            </tr>`).join('');
        }

        function delOthersText(others) {
            if (!others || !others.length) return '';
            return 'سوابق دیگرِ این مشتری که همراه فاکتورها منتقل می‌شوند: ' +
                others.map(o => faDigits(o.count) + ' ' + o.label).join('، ') + '.';
        }

        async function removeRow(id) {
            const r = allRows.find(x => x.id === id);
            let u;
            try {
                u = await apiGet('/customers/' + id + '/usage');
            } catch (e) {
                showToast(e.message || 'خطا در بررسی فاکتورهای مشتری', 'error');
                return;
            }
            if (!u.total_refs) {
                uiConfirm('مشتری «' + esc(u.name || (r && r.name) || '') + '» فاکتوری ندارد. حذف شود؟', async () => {
                    try {
                        await apiSend('DELETE', '/customers/' + id, {});
                        showToast('حذف شد', 'success');
                        loadAll();
                    } catch (e) {
                        showToast(e.message || 'خطا در حذف', 'error');
                    }
                }, { danger: true, yesText: 'حذف', noText: 'انصراف' });
                return;
            }
            delId = id;
            delName = u.name;
            delUsage = u;
            delStep1();
            delModal = delModal || new bootstrap.Modal(document.getElementById('delModal'));
            delModal.show();
        }

        // مرحلهٔ ۱: فهرست فاکتورها + تأیید حذف
        function delStep1() {
            const n = delUsage.invoices.length;
            document.getElementById('delTitle').textContent = 'حذف مشتری «' + delName + '»';
            document.getElementById('delIntro').innerHTML = `<div class="del-summary">
                مشتری <b>«${esc(delName)}»</b> ${n ? faDigits(n) + ' فاکتور دارد' : 'فاکتوری ندارد ولی سوابق دیگری دارد'}.
                با حذف، ${n ? 'این فاکتورها' : 'این سوابق'} باید به یک مشتری دیگر منتقل شوند تا بی‌صاحب نمانند.
                اگر مطمئنی این مشتری تکراری است و باید حذف شود، تأیید کن تا مشتری جایگزین را انتخاب کنی.</div>`;
            document.getElementById('delInvBody').innerHTML = delInvRows(delUsage.invoices);
            document.querySelector('.del-inv-wrap').hidden = !n;
            document.getElementById('delOthers').textContent = delOthersText(delUsage.others);
            document.getElementById('delPickWrap').hidden = true;
            document.getElementById('delAlert').innerHTML = '';
            document.getElementById('delNext').hidden = false;
            document.getElementById('delDo').hidden = true;
        }

        // مرحلهٔ ۲: فهرست دوباره + انتخاب مشتری جایگزین
        function delStep2() {
            const n = delUsage.invoices.length;
            document.getElementById('delIntro').innerHTML = `<div class="del-summary">
                ${n ? faDigits(n) + ' فاکتور زیر' : 'سوابق این مشتری'} به مشتریِ انتخابی منتقل
                و سپس <b>«${esc(delName)}»</b> حذف می‌شود.</div>`;
            document.getElementById('delPickWrap').hidden = false;
            document.getElementById('delNext').hidden = true;
            document.getElementById('delDo').hidden = false;
            const items = allRows.filter(c => c.id !== delId).map(c => ({
                id: c.id,
                label: c.name,
                meta: c.national_id ? ('شناسه ملی: ' + faDigits(c.national_id)) : (c.mobile ? faDigits(c.mobile) : ''),
                search: c.name + ' ' + (c.national_id || '') + ' ' + (c.mobile || '') + ' ' + (c.phone || ''),
            }));
            if (!delPicker) {
                delPicker = EntityPicker.create({ container: '#delTargetPicker', items, placeholder: 'جست‌وجوی مشتری جایگزین…' });
            } else {
                delPicker.updateItems(items);
                delPicker.reset();
            }
            setTimeout(() => delPicker.focus(), 50);
        }

        async function delDoMerge() {
            const t = delPicker && delPicker.getValue();
            const alertEl = document.getElementById('delAlert');
            if (!t) {
                alertEl.innerHTML = '<div class="alert alert-warning py-2 mb-0">مشتری جایگزین را انتخاب کن.</div>';
                return;
            }
            alertEl.innerHTML = '';
            uiConfirm('همهٔ فاکتورها و سوابق «' + esc(delName) + '» به «' + esc(t.label) + '» منتقل و «' + esc(delName) + '» حذف شود؟', async () => {
                const btn = document.getElementById('delDo');
                btn.disabled = true;
                try {
                    const d = await apiSend('POST', '/customers/' + delId + '/merge', { target_id: t.id });
                    delModal.hide();
                    const moved = (d.moved || []).map(m => faDigits(m.count) + ' ' + m.label).join('، ');
                    showToast('«' + esc(delName) + '» حذف شد' + (moved ? ' و ' + moved + ' به «' + esc(t.label) + '» منتقل شد' : ''), 'success');
                    if (d.left_on_deleted && d.left_on_deleted.length) {
                        showToast('چون «' + esc(t.label) + '» از قبل داشت، این موارد منتقل نشد و روی مشتری حذف‌شده ماند: ' +
                            d.left_on_deleted.map(m => faDigits(m.count) + ' ' + m.label).join('، '), 'warning');
                    }
                    loadAll();
                } catch (e) {
                    alertEl.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + esc(e.message || 'خطا') + '</div>';
                } finally {
                    btn.disabled = false;
                }
            }, { danger: true, yesText: 'بله، منتقل و حذف شود', noText: 'انصراف' });
        }

        // ── بارگذاری از اکسل ──
        const COLMAP = {
            'نوع': 'type',
            'نام': 'name',
            'تلفن': 'phone',
            'موبایل': 'mobile',
            'کد/شناسه ملی': 'national_id',
            'کد / شناسه ملی': 'national_id',
            'شماره اقتصادی': 'economic_code',
            'کد اقتصادی': 'economic_code', // عنوان قدیمی — فایل‌های اکسل قبلی هنوز خوانده شوند
            'استان': 'province',
            'شهر': 'city',
            'کد پستی': 'postal_code',
            'کدپستی': 'postal_code',
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
                        box.innerHTML = '<span class="text-danger">هیچ ردیف معتبری پیدا نشد (ستون «نام» لازم است).</span>';
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
                    box.innerHTML = '<span class="text-danger">' + (err.message || 'خطا در پردازش فایل') + '</span>';
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
            // کلیک راست روی ردیف = منوی همان موارد ستون عملیات (assets/js/ag-grid-fa.js)
            AgGridFa.rowContextMenu(document.getElementById('crmGrid'), gridApi);
            custModal = new bootstrap.Modal(document.getElementById('custModal'));
            document.getElementById('delNext').addEventListener('click', delStep2);
            document.getElementById('delDo').addEventListener('click', delDoMerge);
            // اعتبارسنجی زندهٔ کد/شناسه ملی و کد پستی هنگام تایپ — assets/js/iran-id-validate.js
            if (window.IranId) IranId.attachLive({
                type: document.getElementById('f_type'),
                national: document.getElementById('f_national_id'),
                postal: document.getElementById('f_postal_code'),
                economic: document.getElementById('f_economic_code')
            });
            document.getElementById('custModal').addEventListener('shown.bs.modal', () => {
                const el = document.querySelector('#custModal .modal-body input:not([type=hidden]), #custModal .modal-body select, #custModal .modal-body textarea');
                if (el) el.focus();
            });

            // متن جستجو بعد از رفرش/برگشت می‌ماند تا × زده شود (search-persist.js)
            SearchPersist.bind(document.getElementById('crmSearch'), 'crm-customers');
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

            EditGuard.init({
                key: 'inv-customer-form',
                label: guardLabel,
                snapshot: guardSnapshot,
                restore: guardRestore,
                save: () => saveCust(),
            });
            // پر شدن فرم با باز شدن پنجره = «بدون تغییر»؛ بستن پنجره (انصراف) = کنار گذاشتن تغییرات
            document.getElementById('custModal').addEventListener('shown.bs.modal', () => {
                if (guardRestoring) { guardRestoring = false; return; } // نسخهٔ بازیابی‌شده «تغییر ذخیره‌نشده» بماند
                EditGuard.saved();
            });
            document.getElementById('custModal').addEventListener('hidden.bs.modal', () => EditGuard.saved());

            // بعد از بارگذاری فهرست (برای بازیابی فرم «ویرایش» باید مشتری‌ها آمده باشند)
            loadAll().then(() => EditGuard.ready());
        });
    </script>
</body>

</html>
