<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// CRM جدید (حسابداری غیررسمی) — جدا از «مشتریان» فاکتور رسمی (crm-customers.php).
// فقط سازمان ۱: سرپرست‌ها، تیم فروش، تیم حسابداری — includes/ucrm_access.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ucrm_access.php';
if (!ucrmAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}
$__ucrmRoles = ucrmRoles($db, (int) $user_id);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مشتریان CRM - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
    <script src="<?= asset('../../assets/js/search-persist.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/xlsx.full.min.js') ?>"></script>
    <script src="<?= asset('../../assets/js/iran-id-validate.js') ?>"></script>
    <script src="<?= asset('../../assets/js/iran-provinces.js') ?>"></script>
    <script src="<?= asset('../../assets/js/money-shortcut.js') ?>"></script>
    <script src="<?= asset('../../assets/js/edit-guard.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/ucrm.css') ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <!-- چیدمان و اندازهٔ کادر صفحه هم‌شکل pages/tasks.php -->
    <div class="overview-container">

        <div class="filters-wrapper two-col">
            <div class="filters-title-col">
                <h1><i class="bi bi-person-lines-fill"></i> مشتریان CRM</h1>
                <p>بانک مشتریان، کارشناس فروش مسئول و وضعیت تارگت ماه جاری</p>
            </div>

            <div style="flex: 1;">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="crmSearch" placeholder="جستجو در نام، شماره تماس، کد ملی، کارشناس یا آدرس" data-placeholder-mobile="جستجو...">
                </div>
                <div class="filters-row">
                    <div class="filter-item uc-st">
                        <select id="stFilter" title="وضعیت">
                            <option value="">همهٔ وضعیت‌ها</option>
                            <option value="mine">در انتظار تأیید من</option>
                            <option value="pending_manager">در انتظار مدیر</option>
                            <option value="pending_accounting">در انتظار حسابداری</option>
                            <option value="rejected">رد شده</option>
                            <option value="active">فعال</option>
                        </select>
                    </div>
                    <div class="uc-actions">
                        <?php if ($__ucrmRoles['manager'] || $__ucrmRoles['sales']): /* حسابداری فقط تأیید می‌کند، ثبت نه */ ?>
                            <button class="btn btn-link btn-sm" id="btnTemplate" type="button">دریافت الگوی اکسل</button>
                            <button class="btn btn-outline-secondary" id="btnImport"><i class="bi bi-file-earmark-spreadsheet ms-1"></i> بارگذاری از اکسل</button>
                            <button class="btn btn-primary" id="btnAdd"><i class="bi bi-plus-lg ms-1"></i> مشتری جدید</button>
                        <?php endif; ?>
                        <input type="file" id="excelFile" accept=".xlsx,.xls" hidden>
                    </div>
                </div>
                <div id="importResult"></div>
            </div>
        </div>

        <!-- Table -->
        <div id="ucrmGrid" class="ag-theme-alpine inv-grid" style="height: 620px; width: 100%; padding-top: 1rem;"></div>

    </div>

    <!-- افزودن / ویرایش مشتری — دو حالت: تعریف دستی، انتخاب از حسابداری -->
    <div class="modal fade" id="custModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header-custom modal-header border-0">
                    <h5 class="modal-title text-white"><i class="bi bi-person-plus ms-2"></i><span id="custModalTitle">افزودن و انتساب مشتری جدید</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="uc-seg mb-3" id="modeSeg">
                        <button type="button" class="uc-seg-btn active" data-mode="manual">تعریف مشتری جدید</button>
                        <button type="button" class="uc-seg-btn" data-mode="accounting">انتخاب از حسابداری</button>
                    </div>

                    <div id="custModalAlert"></div>

                    <!-- حالت ۱: تعریف دستی -->
                    <form id="custForm" novalidate data-mode-pane="manual">
                        <input type="hidden" id="f_id">
                        <input type="hidden" id="f_type" value="legal">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">نوع مشتری <span class="text-danger">*</span></label>
                                <div class="uc-seg uc-seg-sm" id="typeSeg">
                                    <button type="button" class="uc-seg-btn active" data-type="legal"><i class="bi bi-building ms-1"></i>حقوقی</button>
                                    <button type="button" class="uc-seg-btn" data-type="individual"><i class="bi bi-person ms-1"></i>حقیقی</button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" id="lblName">نام شرکت / مشتری <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="f_name" placeholder="نام شرکت یا مشتری را وارد کنید">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">شماره تماس <span class="text-danger">*</span></label>
                                <div class="uc-phone-row">
                                    <input type="text" class="form-control" id="f_phone_in" placeholder="شماره را بنویسید و Enter بزنید" autocomplete="off" inputmode="tel">
                                    <button class="btn btn-primary" type="button" id="btnAddPhone" title="افزودن شماره"><i class="bi bi-plus-lg"></i></button>
                                </div>
                                <div class="uc-chips" id="phoneChips"></div>
                                <div class="uc-warn" id="phoneWarn"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" id="lblNid">شناسهٔ ملی</label>
                                <input type="text" class="form-control" id="f_national_id" autocomplete="off" inputmode="numeric" placeholder="اختیاری">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">شماره اقتصادی</label>
                                <input type="text" class="form-control" id="f_economic_code" autocomplete="off" inputmode="numeric" placeholder="اختیاری — ۱۰ تا ۱۲ رقم">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">استان</label>
                                <select class="form-select" id="f_province">
                                    <option value="">انتخاب استان</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">شهر</label>
                                <input type="text" class="form-control" id="f_city" placeholder="شهر را انتخاب یا تایپ کنید" autocomplete="off">
                            </div>
                            <div class="col-12">
                                <label class="form-label">آدرس</label>
                                <input type="text" class="form-control" id="f_address" placeholder="آدرس کامل مشتری را وارد کنید">
                            </div>
                            <div class="col-12"><hr class="my-1"></div>
                            <div class="col-md-6">
                                <label class="form-label" id="lblRep">کارشناس فروش <span class="text-danger">*</span></label>
                                <select class="form-select" id="f_rep"></select>
                                <div class="form-text" id="repHint"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" id="lblTarget">تارگت ماهانهٔ پیشنهادی (تومان)</label>
                                <input type="text" class="form-control" id="f_target" placeholder="مثلا ۵۰,۰۰۰,۰۰۰ — با * سه صفر اضافه می‌شود" autocomplete="off">
                                <div class="form-text" id="targetHint">مدیر هنگام تأیید، تارگت نهایی را تعیین می‌کند.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">توضیحات / یادداشت اولیه</label>
                                <textarea class="form-control" id="f_note" rows="2" placeholder="توضیحات اولیه را وارد کنید"></textarea>
                            </div>
                        </div>
                        <div class="uc-info mt-3" id="flowHint"><i class="bi bi-info-circle-fill ms-1"></i><span></span></div>
                    </form>

                    <!-- حالت ۲: از حسابداری (اتصال به وب‌سرویس شایگان — مرحلهٔ بعد) -->
                    <div data-mode-pane="accounting" hidden>
                        <div class="uc-empty">
                            <i class="bi bi-plug"></i>
                            <div class="fw-bold mb-1">اتصال به حسابداری هنوز برقرار نشده است.</div>
                            <div class="small">وقتی وب‌سرویس نرم‌افزار حسابداری وصل شود، مشتریان حسابداری این‌جا فهرست می‌شوند و با یک کلیک اضافه می‌شوند. فعلا از «تعریف مشتری جدید» استفاده کنید.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0" id="custFooter">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" id="btnSave"><i class="bi bi-send ms-1"></i><span>ثبت و ارسال درخواست</span></button>
                </div>
            </div>
        </div>
    </div>

    <!-- تأیید مدیر (با امکان تغییر کارشناس) -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header-custom modal-header border-0">
                    <h5 class="modal-title text-white"><i class="bi bi-check2-circle ms-2"></i><span id="apTitle">تأیید مشتری</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="apText" class="mb-3"></p>
                    <label class="form-label">کارشناس فروش <span class="text-danger">*</span></label>
                    <select class="form-select" id="apRep"></select>
                    <div class="form-text">کارشناس پیشنهادی انتخاب شده؛ در صورت نیاز عوضش کنید.</div>
                    <div id="apAlert" class="mt-2"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-success" id="apDo"><i class="bi bi-check-lg ms-1"></i> تأیید و ارسال برای حسابداری</button>
                </div>
            </div>
        </div>
    </div>

    <!-- رد با دلیل -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header-custom modal-header border-0">
                    <h5 class="modal-title text-white"><i class="bi bi-x-circle ms-2"></i><span id="rjTitle">رد مشتری</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">دلیل رد <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rjReason" rows="4" placeholder="دلیل را بنویسید تا کارشناس فروش اصلاح کند"></textarea>
                    <div id="rjAlert" class="mt-2"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-danger" id="rjDo">رد و برگشت به کارشناس</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../../assets/js/ucrm-common.js') ?>"></script>
    <script>
        const ROLES = <?= json_encode($__ucrmRoles) ?>;
        const ME = <?= (int) $user_id ?>;

        let gridApi = null;
        let allRows = [];
        let reps = [];
        let settings = { target_red_below: 80, target_green_from: 100 };
        let custModal, approveModal, rejectModal;
        let editRow = null; // null = مشتری جدید
        let phones = [];
        let searchTimer, phoneTimer;
        let actId = null;

        // ── ستون‌ها ──
        function actionCell(p) {
            const d = p.data;
            let h = '';
            if (d.can_approve) {
                h += `<button class="ag-action-btn ok" title="تأیید" onclick="openApprove(${d.id})"><i class="bi bi-check-lg"></i></button>`;
                h += `<button class="ag-action-btn del" title="رد با دلیل" onclick="openReject(${d.id})"><i class="bi bi-x-lg"></i></button>`;
            }
            if (d.can_resubmit) h += `<button class="ag-action-btn edit" title="اصلاح و ارسال دوباره" onclick="openEdit(${d.id}, true)"><i class="bi bi-arrow-repeat"></i></button>`;
            else if (d.can_edit) h += `<button class="ag-action-btn edit" title="ویرایش" onclick="openEdit(${d.id})"><i class="bi bi-pencil"></i></button>`;
            if (d.can_delete) h += `<button class="ag-action-btn del" title="${d.status === 'active' || ROLES.manager ? 'حذف' : 'انصراف از درخواست (حذف)'}" onclick="removeRow(${d.id})"><i class="bi bi-trash"></i></button>`;
            return h;
        }

        const colDefs = [{
                headerName: 'نوع',
                field: 'type',
                width: 95,
                valueFormatter: p => UCRM.TYPE_LABEL[p.value] || '',
                cellRenderer: p => UCRM.typeBadge(p.value)
            },
            {
                headerName: 'نام مشتری',
                field: 'name',
                flex: 2,
                minWidth: 130,
                // نام = لینک صفحهٔ جزئیات مشتری (کلیک ردیف هم همان‌جا می‌رود)
                cellRenderer: p => `<a class="uc-name uc-name-link" href="ucrm-customer.php?id=${p.data.id}">${esc(p.value)}</a>`
            },
            {
                headerName: 'کارشناس فروش',
                field: 'rep_name',
                flex: 1,
                minWidth: 125,
                cellRenderer: p => p.value ? esc(p.value) : '<span class="text-muted">تعیین نشده</span>'
            },
            {
                headerName: 'وضعیت',
                field: 'status',
                width: 140,
                valueFormatter: p => UCRM.STATUS_LABEL[p.value] || '',
                cellRenderer: p => UCRM.statusBadge(p.data)
            },
            {
                headerName: 'وضعیت تارگت',
                colId: 'target',
                width: 115,
                sortable: true,
                filter: false,
                valueGetter: p => UCRM.targetPct(p.data),
                cellRenderer: p => UCRM.targetBadge(p.data, settings)
            },
            {
                headerName: 'آخرین پیگیری',
                field: 'last_followup_at',
                width: 105,
                filter: false,
                cellRenderer: p => UCRM.lastFollowup(p.value)
            },
            {
                headerName: 'شماره تماس',
                colId: 'phones',
                width: 130,
                valueGetter: p => (p.data.phones || []).join(' '),
                cellRenderer: p => UCRM.phonesCell(p.data.phones)
            },
            {
                headerName: 'آدرس',
                colId: 'address',
                flex: 1,
                minWidth: 100,
                valueGetter: p => UCRM.fullAddress(p.data),
                cellRenderer: p => p.value ? `<span title="${esc(p.value)}">${esc(p.value)}</span>` : '<span class="text-muted">ثبت نشده</span>'
            },
            {
                headerName: 'عملیات',
                colId: 'actions',
                width: 80,
                sortable: false,
                filter: false,
                // منوی «⋮» مشترک پروژه (assets/js/ag-grid-fa.js) — مثل فهرست فاکتورها
                cellRenderer: AgGridFa.actionMenuCell(actionCell)
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
            defaultColDef: { resizable: true, sortable: true, filter: true },
            overlayNoRowsTemplate: '<span class="text-muted">مشتری‌ای یافت نشد</span>',
            rowClass: 'uc-row-link',
            // کلیک روی ردیف → صفحهٔ جزئیات (نه وقتی روی دکمه‌های عملیات کلیک شده)
            onCellClicked: e => {
                if (e.colDef.colId === 'actions') return;
                if (e.event && e.event.target.closest('button, a')) return;
                location.href = 'ucrm-customer.php?id=' + e.data.id;
            },
        };

        async function loadAll() {
            gridApi.showLoadingOverlay();
            try {
                const d = await UCRM.get('/customers');
                allRows = d.items || [];
                if (d.settings) settings = d.settings;
                applyFilter(true);
            } catch (e) {
                showToast(e.message || 'خطا در بارگذاری', 'error');
                gridApi.setGridOption('rowData', []);
            }
        }

            // keepPage: بارگذاری دوباره بعد از یک عملیات → همان صفحهٔ جدول بماند (AgGridFa.setRowsKeepPage)
        function applyFilter(keepPage) {
            const q = UCRM.toEn(document.getElementById('crmSearch').value.trim()).toLowerCase();
            const st = document.getElementById('stFilter').value;
            const qd = q.replace(/\D/g, '');
            const rows = allRows.filter(r => {
                if (st === 'mine' && !r.can_approve) return false;
                if (st && st !== 'mine' && r.status !== st) return false;
                if (!q) return true;
                return (r.name || '').toLowerCase().includes(q) ||
                    (r.rep_name || '').toLowerCase().includes(q) ||
                    (qd && ((r.national_id || '').includes(qd) || (r.phones || []).some(p => p.includes(qd)))) ||
                    UCRM.fullAddress(r).toLowerCase().includes(q);
            });
            if (keepPage) AgGridFa.setRowsKeepPage(gridApi, rows);
            else gridApi.setGridOption('rowData', rows);
        }

        // ── فرم ──
        function setType(t) {
            document.getElementById('f_type').value = t;
            document.querySelectorAll('#typeSeg .uc-seg-btn').forEach(b => b.classList.toggle('active', b.dataset.type === t));
            document.getElementById('lblNid').textContent = t === 'individual' ? 'کد ملی' : 'شناسهٔ ملی';
            document.getElementById('f_type').dispatchEvent(new Event('change'));
        }

        function setMode(m) {
            document.querySelectorAll('#modeSeg .uc-seg-btn').forEach(b => b.classList.toggle('active', b.dataset.mode === m));
            document.querySelectorAll('[data-mode-pane]').forEach(el => el.hidden = el.dataset.modePane !== m);
            document.getElementById('btnSave').hidden = m !== 'manual';
            if (m === 'manual') setTimeout(() => document.getElementById('f_name').focus(), 30);
        }

        function renderPhones() {
            document.getElementById('phoneChips').innerHTML = phones.map((p, i) =>
                `<span class="uc-chip"><span class="num-ltr">${faDigits(p)}</span><button type="button" title="حذف" onclick="removePhone(${i})">×</button></span>`).join('');
            clearTimeout(phoneTimer);
            phoneTimer = setTimeout(checkPhones, 300);
        }

        function addPhone() {
            const el = document.getElementById('f_phone_in');
            const v = UCRM.toEn(el.value).replace(/\D/g, '');
            if (!v) return false;
            if (v.length < 4 || v.length > 15) {
                showToast('شماره تماس معتبر نیست.', 'warning');
                return false;
            }
            if (!phones.includes(v)) phones.push(v);
            el.value = '';
            renderPhones();
            el.focus();
            return true;
        }

        function removePhone(i) {
            phones.splice(i, 1);
            renderPhones();
        }

        // شمارهٔ تکراری = فقط هشدار
        async function checkPhones() {
            const box = document.getElementById('phoneWarn');
            if (!phones.length) { box.innerHTML = ''; return; }
            try {
                const d = await UCRM.get('/customers/phone-check?phones=' + phones.join(',') + '&exclude=' + (editRow ? editRow.id : 0));
                box.innerHTML = (d.items || []).map(x =>
                    `<div><i class="bi bi-exclamation-triangle ms-1"></i>شمارهٔ <span class="num-ltr">${faDigits(x.phone)}</span> قبلا برای مشتری «${esc(x.customer)}»${x.rep ? ' (کارشناس: ' + esc(x.rep) + ')' : ''} ثبت شده است.</div>`).join('');
            } catch (e) {
                box.innerHTML = '';
            }
        }

        function fillRepSelect(sel, current) {
            const list = reps.slice();
            if (current && !list.some(r => r.id === current.id)) list.unshift(current);
            sel.innerHTML = '<option value="">انتخاب کارشناس فروش</option>' +
                list.map(r => `<option value="${r.id}">${esc(r.name)}</option>`).join('');
        }

        function flowHint(resubmit) {
            const el = document.querySelector('#flowHint span');
            if (editRow && !resubmit) {
                el.textContent = 'تغییرات مستقیم ذخیره می‌شود.';
                return;
            }
            el.textContent = ROLES.manager ?
                'توجه: پس از ثبت، مشتری برای تأیید به کارتابل حسابداری ارسال می‌شود.' :
                'توجه: پس از ثبت، درخواست تخصیص مشتری به کارتابل مدیر ارسال می‌شود.';
        }

        // مدیر روی مشتری‌ای که از مرحلهٔ مدیر گذشته → «تارگت ماهانه» (همان که تأیید شده)؛ بقیه → «پیشنهادی»
        function targetMode(r) {
            const monthly = ROLES.manager && r && r.status !== 'pending_manager' &&
                !(r.status === 'rejected' && r.rejected_stage === 'manager');
            document.getElementById('lblTarget').textContent = monthly ? 'تارگت ماهانه (تومان)' : 'تارگت ماهانهٔ پیشنهادی (تومان)';
            document.getElementById('targetHint').textContent = monthly ? 'تارگت ماهانهٔ تأییدشدهٔ این مشتری.' :
                (ROLES.manager && !r ? 'چون خودتان ثبت می‌کنید، همین عدد تارگت ماهانه می‌شود.' : 'مدیر هنگام تأیید، تارگت نهایی را تعیین می‌کند.');
            UCRM.setMoney(document.getElementById('f_target'), monthly ? (r.target_amount || 0) : ((r && r.proposed_target) || 0));
        }

        function resetForm() {
            document.getElementById('custForm').reset();
            document.getElementById('custModalAlert').innerHTML = '';
            document.getElementById('phoneWarn').innerHTML = '';
            phones = [];
            renderPhones();
            if (window.IranId) IranId.resetLive(document.getElementById('custForm'));
        }

        function openAdd() {
            editRow = null;
            resetForm();
            setType('legal');
            IranProvinces.setValue(document.getElementById('f_province'), document.getElementById('f_city'), '', '');
            const sel = document.getElementById('f_rep');
            if (ROLES.manager) {
                fillRepSelect(sel, null);
                sel.disabled = false;
                document.getElementById('repHint').textContent = '';
            } else {
                fillRepSelect(sel, { id: ME, name: UCRM.myName || 'خودم' });
                sel.value = ME;
                sel.disabled = true;
                document.getElementById('repHint').textContent = 'نام شما به‌عنوان کارشناس ثبت می‌شود؛ مدیر هنگام تأیید می‌تواند عوضش کند.';
            }
            targetMode(null);
            document.getElementById('custModalTitle').textContent = 'افزودن و انتساب مشتری جدید';
            document.querySelector('#btnSave span').textContent = 'ثبت و ارسال درخواست';
            document.getElementById('btnSave').dataset.resubmit = '';
            document.getElementById('modeSeg').hidden = false;
            flowHint(false);
            setMode('manual');
            custModal.show();
        }

        function openEdit(id, resubmit) {
            const r = allRows.find(x => x.id === id);
            if (!r) return;
            editRow = r;
            resetForm();
            document.getElementById('f_id').value = r.id;
            setType(r.type || 'legal');
            document.getElementById('f_name').value = r.name || '';
            document.getElementById('f_national_id').value = faDigits(r.national_id || '');
            document.getElementById('f_economic_code').value = faDigits(r.economic_code || '');
            IranProvinces.setValue(document.getElementById('f_province'), document.getElementById('f_city'), r.province, r.city);
            document.getElementById('f_address').value = r.address || '';
            document.getElementById('f_note').value = r.note || '';
            targetMode(r);
            phones = (r.phones || []).slice();
            renderPhones();
            const sel = document.getElementById('f_rep');
            fillRepSelect(sel, r.rep_id ? { id: r.rep_id, name: r.rep_name } : null);
            sel.value = r.rep_id || '';
            sel.disabled = !ROLES.manager;
            document.getElementById('repHint').textContent = ROLES.manager ? '' : 'کارشناس را فقط مدیر می‌تواند عوض کند.';
            document.getElementById('custModalTitle').textContent = resubmit ? 'اصلاح و ارسال دوباره' : 'ویرایش مشتری';
            document.querySelector('#btnSave span').textContent = resubmit ? 'ذخیره و ارسال دوباره' : 'ذخیره';
            document.getElementById('btnSave').dataset.resubmit = resubmit ? '1' : '';
            document.getElementById('modeSeg').hidden = true;
            if (resubmit && r.reject_reason) {
                document.getElementById('custModalAlert').innerHTML =
                    `<div class="alert alert-warning py-2">دلیل رد ${r.rejected_stage === 'accounting' ? 'حسابداری' : 'مدیر'}: «${esc(r.reject_reason)}»</div>`;
            }
            flowHint(!!resubmit);
            setMode('manual');
            custModal.show();
        }

        function formAlert(msg) {
            document.getElementById('custModalAlert').innerHTML = '<div class="alert alert-danger py-2">' + msg + '</div>';
            document.querySelector('#custModal .modal-body').scrollTop = 0;
        }

        // خروجی: true اگر ذخیره شد (برای «ذخیره و خروج» پنجرهٔ ترک صفحه — EditGuard)
        async function saveCust() {
            // شماره‌ای که تایپ شده ولی هنوز + نخورده هم حساب شود
            if (document.getElementById('f_phone_in').value.trim()) addPhone();
            const body = {
                type: document.getElementById('f_type').value,
                name: document.getElementById('f_name').value.trim(),
                national_id: UCRM.toEn(document.getElementById('f_national_id').value.trim()),
                economic_code: UCRM.toEn(document.getElementById('f_economic_code').value.trim()),
                province: document.getElementById('f_province').value,
                city: document.getElementById('f_city').value.trim(),
                address: document.getElementById('f_address').value.trim(),
                note: document.getElementById('f_note').value.trim(),
                rep_id: +document.getElementById('f_rep').value || 0,
                target: UCRM.moneyVal(document.getElementById('f_target')),
                phones: phones.slice(),
            };
            const missing = [];
            if (!body.name) missing.push('نام');
            if (!body.phones.length) missing.push('شماره تماس');
            if (!body.rep_id) missing.push('کارشناس فروش');
            if (missing.length) { formAlert('این فیلدها الزامی‌اند: ' + missing.join('، ')); return false; }
            if (window.IranId && body.national_id &&
                !(editRow && body.national_id === editRow.national_id && body.type === editRow.type)) {
                const errs = IranId.checkCustomer({ type: body.type, national_id: body.national_id });
                if (errs.length) { formAlert(errs.join('<br>')); return false; }
            }
            // شماره اقتصادی: فقط وقتی عوض شده بررسی شود (دادهٔ قدیمی ذخیرهٔ بقیه را قفل نکند)
            if (window.IranId && body.economic_code && !(editRow && body.economic_code === (editRow.economic_code || ''))) {
                const errs = IranId.checkCustomer({ type: body.type, economic_code: body.economic_code });
                if (errs.length) { formAlert(errs.join('<br>')); return false; }
            }
            const btn = document.getElementById('btnSave');
            btn.disabled = true;
            try {
                let d;
                if (editRow) {
                    body.resubmit = btn.dataset.resubmit === '1';
                    d = await UCRM.send('PUT', '/customers/' + editRow.id, body);
                } else {
                    d = await UCRM.send('POST', '/customers', body);
                }
                EditGuard.saved();
                custModal.hide();
                showToast(d.message || 'ذخیره شد', 'success');
                loadAll();
                return true;
            } catch (e) {
                formAlert(esc(e.message || 'خطا'));
                return false;
            } finally {
                btn.disabled = false;
            }
        }

        // ── محافظ ترک صفحه + پیش‌نویس خودکار فرم مشتری (assets/js/edit-guard.js) ──
        // فقط وقتی پنجرهٔ «مشتری جدید / ویرایش» باز است تغییر حساب می‌شود. مبنا هر بار بعد
        // از پر شدن فرم (باز شدن پنجره) و بعد از بستن/ذخیره تازه می‌شود.
        let guardRestoring = false;

        function guardSnapshot() {
            if (!document.getElementById('custModal').classList.contains('show')) return null;
            return {
                m: editRow ? editRow.id : 0,
                rs: document.getElementById('btnSave').dataset.resubmit === '1',
                ty: document.getElementById('f_type').value,
                na: document.getElementById('f_name').value,
                ni: document.getElementById('f_national_id').value,
                ec: document.getElementById('f_economic_code').value,
                pr: document.getElementById('f_province').value,
                ci: document.getElementById('f_city').value,
                ad: document.getElementById('f_address').value,
                no: document.getElementById('f_note').value,
                re: document.getElementById('f_rep').value,
                ta: document.getElementById('f_target').value,
                ph: phones.slice(),
                pi: document.getElementById('f_phone_in').value,
            };
        }

        function guardRestore(st) {
            if (!st) return;
            if (st.m && !allRows.some(r => r.id === st.m)) return; // مشتری دیگر نیست/دیده نمی‌شود
            guardRestoring = true;
            if (st.m) openEdit(st.m, st.rs); else openAdd();
            setType(st.ty || 'legal');
            document.getElementById('f_name').value = st.na || '';
            document.getElementById('f_national_id').value = st.ni || '';
            document.getElementById('f_economic_code').value = st.ec || '';
            IranProvinces.setValue(document.getElementById('f_province'), document.getElementById('f_city'), st.pr || '', st.ci || '');
            document.getElementById('f_address').value = st.ad || '';
            document.getElementById('f_note').value = st.no || '';
            if (st.re && !document.getElementById('f_rep').disabled) document.getElementById('f_rep').value = st.re;
            document.getElementById('f_target').value = st.ta || '';
            phones = (st.ph || []).slice();
            renderPhones();
            document.getElementById('f_phone_in').value = st.pi || '';
        }

        function guardLabel() {
            const n = document.getElementById('f_name').value.trim();
            return editRow ? 'مشتری «' + (n || editRow.name) + '»' : (n ? 'مشتری جدید «' + n + '»' : 'مشتری جدید');
        }

        // ── تأیید / رد ──
        function openApprove(id) {
            const r = allRows.find(x => x.id === id);
            if (!r) return;
            actId = id;
            if (r.status === 'pending_accounting') {
                uiConfirm('مشتری «' + esc(r.name) + '» (کارشناس: ' + esc(r.rep_name || 'تعیین نشده') + ') تأیید و فعال شود؟', async () => {
                    try {
                        const d = await UCRM.send('POST', '/customers/' + id + '/approve', {});
                        showToast(d.message || 'تأیید شد', 'success');
                        loadAll();
                    } catch (e) {
                        showToast(e.message || 'خطا', 'error');
                    }
                }, { yesText: 'تأیید و فعال‌سازی', noText: 'انصراف' });
                return;
            }
            document.getElementById('apTitle').textContent = 'تأیید مشتری «' + r.name + '»';
            document.getElementById('apText').textContent = 'پس از تأیید، مشتری برای تأیید نهایی به حسابداری می‌رود.';
            const sel = document.getElementById('apRep');
            fillRepSelect(sel, r.rep_id ? { id: r.rep_id, name: r.rep_name } : null);
            sel.value = r.rep_id || '';
            document.getElementById('apAlert').innerHTML = '';
            approveModal.show();
        }

        async function doApprove() {
            const rep = +document.getElementById('apRep').value || 0;
            if (!rep) {
                document.getElementById('apAlert').innerHTML = '<div class="alert alert-warning py-2 mb-0">کارشناس فروش را انتخاب کنید.</div>';
                return;
            }
            const btn = document.getElementById('apDo');
            btn.disabled = true;
            try {
                const d = await UCRM.send('POST', '/customers/' + actId + '/approve', { rep_id: rep });
                approveModal.hide();
                showToast(d.message || 'تأیید شد', 'success');
                loadAll();
            } catch (e) {
                document.getElementById('apAlert').innerHTML = '<div class="alert alert-danger py-2 mb-0">' + esc(e.message || 'خطا') + '</div>';
            } finally {
                btn.disabled = false;
            }
        }

        function openReject(id) {
            const r = allRows.find(x => x.id === id);
            if (!r) return;
            actId = id;
            document.getElementById('rjTitle').textContent = 'رد مشتری «' + r.name + '»';
            document.getElementById('rjReason').value = '';
            document.getElementById('rjAlert').innerHTML = '';
            rejectModal.show();
        }

        async function doReject() {
            const reason = document.getElementById('rjReason').value.trim();
            if (reason.length < 3) {
                document.getElementById('rjAlert').innerHTML = '<div class="alert alert-warning py-2 mb-0">دلیل رد را بنویسید.</div>';
                document.getElementById('rjReason').focus();
                return;
            }
            const btn = document.getElementById('rjDo');
            btn.disabled = true;
            try {
                const d = await UCRM.send('POST', '/customers/' + actId + '/reject', { reason });
                rejectModal.hide();
                showToast(d.message || 'رد شد', 'success');
                loadAll();
            } catch (e) {
                document.getElementById('rjAlert').innerHTML = '<div class="alert alert-danger py-2 mb-0">' + esc(e.message || 'خطا') + '</div>';
            } finally {
                btn.disabled = false;
            }
        }

        function removeRow(id) {
            const r = allRows.find(x => x.id === id);
            if (!r) return;
            const txt = r.status === 'active' || ROLES.manager ?
                'مشتری «' + esc(r.name) + '» حذف شود؟' :
                'درخواست ثبت مشتری «' + esc(r.name) + '» پس گرفته و حذف شود؟';
            uiConfirm(txt, async () => {
                try {
                    const d = await UCRM.send('DELETE', '/customers/' + id, {});
                    showToast(d.message || 'حذف شد', 'success');
                    loadAll();
                } catch (e) {
                    showToast(e.message || 'خطا در حذف', 'error');
                }
            }, { danger: true, yesText: 'حذف', noText: 'انصراف' });
        }

        // ── اکسل ──
        const XL_COLS = ['نوع', 'نام', 'شماره تماس', 'کد / شناسه ملی', 'شماره اقتصادی', 'استان', 'شهر', 'آدرس', 'کارشناس فروش', 'تارگت ماهانه پیشنهادی (تومان)', 'توضیحات'];
        const XL_MAP = {
            'نوع': 'type', 'نام': 'name', 'نام مشتری': 'name', 'شماره تماس': 'phones', 'تلفن': 'phones', 'موبایل': 'phones',
            'کد / شناسه ملی': 'national_id', 'کد/شناسه ملی': 'national_id', 'کد ملی': 'national_id', 'شناسه ملی': 'national_id',
            'شماره اقتصادی': 'economic_code', 'کد اقتصادی': 'economic_code',
            'استان': 'province', 'شهر': 'city', 'آدرس': 'address', 'کارشناس فروش': 'rep_name', 'کارشناس': 'rep_name', 'توضیحات': 'note',
            'تارگت ماهانه پیشنهادی (تومان)': 'target', 'تارگت ماهانه پیشنهادی': 'target', 'تارگت': 'target',
        };

        function downloadTemplate() {
            const ws = XLSX.utils.aoa_to_sheet([
                XL_COLS,
                ['حقوقی', 'شرکت نمونه', '02122223333، 09121234567', '', '', 'تهران', 'تهران', 'خیابان نمونه، پلاک ۱', ROLES.manager ? 'نام و نام خانوادگی کارشناس' : '', 50000000, ''],
            ]);
            ws['!cols'] = XL_COLS.map((c, i) => ({ wch: [8, 28, 28, 16, 14, 14, 36, 24, 26, 24][i] }));
            const wb = XLSX.utils.book_new();
            wb.Workbook = { Views: [{ RTL: true }] };
            XLSX.utils.book_append_sheet(wb, ws, 'مشتریان');
            XLSX.writeFile(wb, 'ucrm-customers-template.xlsx');
        }

        // صفرِ اولِ شماره/کد ملی که اکسل (سلول عددی) حذف کرده برگردد
        function fixLeadingZero(v, len) {
            v = UCRM.toEn(String(v == null ? '' : v)).replace(/\D/g, '');
            if (v && len && v.length < len) v = v.padStart(len, '0');
            return v;
        }

        function handleExcel(file) {
            const box = document.getElementById('importResult');
            box.innerHTML = 'در حال خواندن فایل…';
            const reader = new FileReader();
            reader.onload = async e => {
                try {
                    const wb = XLSX.read(e.target.result, { type: 'array' });
                    const raw = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { defval: '' });
                    const rows = raw.map(obj => {
                        const o = { phones: [] };
                        for (const k in obj) {
                            const key = XL_MAP[String(k).trim()];
                            if (!key) continue;
                            const v = String(obj[k]).trim();
                            if (key === 'phones') {
                                v.split(/[،,;\/\n]+/).forEach(p => {
                                    p = UCRM.toEn(p).replace(/\D/g, '');
                                    if (p.length === 10 && p[0] !== '0') p = '0' + p; // 9121234567 → 09121234567
                                    if (p) o.phones.push(p);
                                });
                            } else if (key === 'target') {
                                o.target = parseInt(UCRM.toEn(v).replace(/\D/g, ''), 10) || 0;
                            } else o[key] = v;
                        }
                        if (o.national_id) {
                            const t = /حقیقی|individual/i.test(o.type || '') ? 'individual' : 'legal';
                            o.national_id = fixLeadingZero(o.national_id, t === 'individual' ? 10 : 11);
                        }
                        return o;
                    }).filter(o => o.name || o.phones.length);
                    if (!rows.length) {
                        box.innerHTML = '<span class="text-danger">هیچ ردیفی پیدا نشد. از «دریافت الگوی اکسل» استفاده کنید.</span>';
                        return;
                    }
                    box.innerHTML = 'در حال ارسال ' + faDigits(rows.length) + ' ردیف…';
                    const d = await UCRM.send('POST', '/customers/import', { rows });
                    let msg = '<span class="text-success">' + faDigits(d.inserted) + ' مشتری ثبت شد و برای تأیید مدیر ارسال شد.</span>';
                    if (d.errors && d.errors.length) {
                        msg += '<br><span class="text-danger">' + faDigits(d.errors.length) + ' ردیف ثبت نشد: ' +
                            d.errors.slice(0, 8).map(x => 'ردیف ' + faDigits(x.row) + ' (' + esc(x.message) + ')').join('، ') +
                            (d.errors.length > 8 ? ' …' : '') + '</span>';
                    }
                    box.innerHTML = msg;
                    loadAll();
                } catch (err) {
                    box.innerHTML = '<span class="text-danger">' + esc(err.message || 'خطا در پردازش فایل') + '</span>';
                }
            };
            reader.readAsArrayBuffer(file);
        }

        document.addEventListener('DOMContentLoaded', async () => {
            if (!UCRM.tok()) {
                location.href = '../index.php';
                return;
            }
            gridApi = agGrid.createGrid(document.getElementById('ucrmGrid'), gridOptions);
            // کلیک راست روی ردیف = منوی همان موارد ستون عملیات (assets/js/ag-grid-fa.js)
            AgGridFa.rowContextMenu(document.getElementById('ucrmGrid'), gridApi);
            custModal = new bootstrap.Modal(document.getElementById('custModal'));
            approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
            rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));

            IranProvinces.bind(document.getElementById('f_province'), document.getElementById('f_city'));
            UCRM.bindMoney(document.getElementById('f_target'));
            if (window.IranId) IranId.attachLive({ type: document.getElementById('f_type'), national: document.getElementById('f_national_id'),
                economic: document.getElementById('f_economic_code') });

            document.querySelectorAll('#typeSeg .uc-seg-btn').forEach(b => b.addEventListener('click', () => setType(b.dataset.type)));
            document.querySelectorAll('#modeSeg .uc-seg-btn').forEach(b => b.addEventListener('click', () => setMode(b.dataset.mode)));
            document.getElementById('btnAddPhone').addEventListener('click', addPhone);
            document.getElementById('f_phone_in').addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); addPhone(); }
            });
            // فوکوس خودکار روی اولین فیلد هر مودال
            document.getElementById('custModal').addEventListener('shown.bs.modal', () => document.getElementById('f_name').focus());
            document.getElementById('approveModal').addEventListener('shown.bs.modal', () => document.getElementById('apRep').focus());
            document.getElementById('rejectModal').addEventListener('shown.bs.modal', () => document.getElementById('rjReason').focus());

            document.getElementById('btnSave').addEventListener('click', saveCust);
            document.getElementById('apDo').addEventListener('click', doApprove);
            document.getElementById('rjDo').addEventListener('click', doReject);
            if (document.getElementById('btnAdd')) {
                document.getElementById('btnAdd').addEventListener('click', openAdd);
                document.getElementById('btnTemplate').addEventListener('click', downloadTemplate);
                document.getElementById('btnImport').addEventListener('click', () => document.getElementById('excelFile').click());
            }
            document.getElementById('excelFile').addEventListener('change', e => {
                if (e.target.files[0]) handleExcel(e.target.files[0]);
                e.target.value = '';
            });
            // جستجو و فیلتر وضعیت بعد از رفرش/برگشت می‌مانند (search-persist.js)
            SearchPersist.bind(document.getElementById('crmSearch'), 'ucrm-customers');
            SearchPersist.bindSelect(document.getElementById('stFilter'), 'ucrm-customers');
            document.getElementById('crmSearch').addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => applyFilter(), 200);
            });
            document.getElementById('stFilter').addEventListener('change', () => applyFilter());
            // تأییدکننده‌ها پیش‌فرض «در انتظار تأیید من» نمی‌بینند؛ همه نشان داده می‌شود (طبق توافق)

            try {
                const [me, rp] = await Promise.all([UCRM.get('/me'), UCRM.get('/reps')]);
                UCRM.myName = me.name;
                reps = rp.items || [];
            } catch (e) {
                showToast(e.message || 'خطا در بارگذاری کارشناسان', 'error');
            }
            await loadAll();
            // از تسک «اصلاح مشتری» (ucrm-approval.php): ?edit=ID&resubmit=1 → فرم اصلاح خودکار باز شود
            const qp = new URLSearchParams(location.search);
            const eid = +qp.get('edit');
            if (eid) {
                const r = allRows.find(x => x.id === eid);
                if (r && (r.can_resubmit || r.can_edit)) openEdit(eid, qp.get('resubmit') === '1' && r.can_resubmit);
                // از مداد «اطلاعات پایه» صفحهٔ جزئیات مشتری (?back=detail): بعد از بستن فرم برگردد همان‌جا
                if (qp.get('back') === 'detail') {
                    document.getElementById('custModal').addEventListener('hidden.bs.modal', () => {
                        location.href = 'ucrm-customer.php?id=' + eid;
                    }, { once: true });
                }
                history.replaceState(null, '', location.pathname);
            }

            EditGuard.init({
                key: 'ucrm-customer-form',
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
            EditGuard.ready();
        });
    </script>
</body>

</html>
