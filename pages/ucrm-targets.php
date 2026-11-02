<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// CRM جدید — «تنظیمات تارگت‌گذاری و فروش». API: crm-service/ucrm_targets.go
// ویرایش فقط مدیر؛ کارشناس فقط تارگت مشتری‌های خودش را می‌بیند.
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ucrm_access.php';
if (!ucrmAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}
$__ucrmRoles = ucrmRoles($db, (int) $user_id);
$__canEdit = $__ucrmRoles['manager'];
$__seesAll = $__ucrmRoles['manager'] || $__ucrmRoles['accounting'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات تارگت‌گذاری و فروش - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
    <script src="<?= asset('../../assets/js/search-persist.js') ?>"></script>
    <script src="<?= asset('../../assets/js/entity-picker.js') ?>"></script>
    <script src="<?= asset('../../assets/js/money-shortcut.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/ucrm.css') ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <!-- چیدمان و اندازهٔ کادر صفحه هم‌شکل pages/tasks.php (همهٔ صفحه‌های CRM) -->
    <div class="overview-container uc-top-gap">

        <div class="uc-card uc-tg-head">
            <div class="uc-tg-title">
                <span class="uc-tg-ico"><i class="bi bi-bullseye"></i></span>
                <h1>تنظیمات تارگت‌گذاری و فروش</h1>
            </div>
            <div class="uc-tg-bar">
                <div class="uc-tg-filters">
                    <?php if ($__seesAll): ?>
                        <div class="uc-tg-f"><i class="bi bi-person"></i>
                            <select id="fRep" class="form-select form-select-sm" title="کارشناس">
                                <option value="">همهٔ کارشناسان</option>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="uc-tg-f"><i class="bi bi-person"></i> کارشناس: <b id="meName"></b></div>
                    <?php endif; ?>
                    <div class="uc-tg-f"><i class="bi bi-calendar3"></i>
                        <select id="fType" class="form-select form-select-sm" title="دورهٔ زمانی">
                            <option value="monthly">ماهانه</option>
                            <option value="quarterly">فصلی</option>
                            <option value="yearly">سالانه</option>
                        </select>
                        <select id="fPeriod" class="form-select form-select-sm" title="دوره"></select>
                    </div>
                </div>
                <?php if ($__canEdit): ?>
                    <div class="uc-tg-actions">
                        <button class="btn btn-outline-secondary" id="btnRules"><i class="bi bi-pencil-square ms-1"></i> تنظیمات تارگت‌گذاری</button>
                        <button class="btn btn-primary" id="btnBulk"><i class="bi bi-pencil-square ms-1"></i> ویرایش گروهی</button>
                        <button class="btn btn-primary" id="btnAddT"><i class="bi bi-plus-lg ms-1"></i> افزودن تارگت</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="tgGrid" class="ag-theme-alpine inv-grid" style="height: 620px; width: 100%; padding-top: 1rem;"></div>
    </div>

    <!-- ویرایش تکی / گروهی / افزودن تارگت — طرح «ویرایش گروهی تارگت‌ها» (۱۴۰۵/۰۸/۰۲) -->
    <div class="modal fade uc-tg-modal" id="tgModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="tgTitle">ویرایش تارگت</h5>
                        <div class="sub" id="tgSub"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body">
                    <div id="tgWho" class="small text-muted mb-3"></div>
                    <label class="tg-sel" id="tgSelWrap" hidden>
                        <input type="checkbox" id="tgOnlySel" checked> <span>اعمال برای مشتری انتخاب شده</span>
                    </label>
                    <div id="tgPickWrap" class="mb-3" hidden>
                        <label class="form-label">مشتری <span class="text-danger">*</span></label>
                        <div id="tgPicker"></div>
                        <div class="form-text">فقط مشتری‌هایی که برای این دوره عدد اختصاصی ندارند.</div>
                    </div>
                    <div class="tg-row"><label for="tTarget">مبنای تارگت فروش</label>
                        <div class="input-group"><select class="form-select uc-basis-sel" id="tBasis" aria-label="نوع مبنا"><option value="target">سقف فروش</option><option value="floor">کف فروش</option></select><input type="text" class="form-control" id="tTarget" placeholder="۰" autocomplete="off"><span class="input-group-text">تومان</span></div></div>
                    <div class="tg-row"><label for="tRep">کارشناس فروش</label>
                        <select class="form-select" id="tRep"></select></div>
                    <div class="tg-row"><label>تاریخ شروع</label>
                        <div class="tg-date"><input type="text" class="form-control" id="tStart" readonly tabindex="-1"><i class="bi bi-calendar3"></i></div></div>
                    <div class="tg-row"><label>تاریخ پایان</label>
                        <div class="tg-date"><input type="text" class="form-control" id="tEnd" readonly tabindex="-1"><i class="bi bi-calendar3"></i></div></div>
                    <div class="tg-row"><label>وضعیت</label>
                        <div class="form-check form-switch m-0 tg-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="tActive" aria-label="وضعیت تارگت" checked>
                        </div></div>
                    <div class="form-text mt-2" id="tgHint"></div>
                    <div id="tgAlert" class="mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary tg-btn" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary tg-btn" id="tgSave"><i class="bi bi-check2 ms-1"></i><span>اعمال تغییرات</span></button>
                </div>
            </div>
        </div>
    </div>

    <!-- تنظیمات تارگت‌گذاری: قواعد پیش‌فرض + رنگ وضعیت تارگت -->
    <div class="modal fade" id="rulesModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header-custom modal-header border-0">
                    <h5 class="modal-title text-white"><i class="bi bi-sliders ms-2"></i>تنظیمات تارگت‌گذاری</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="uc-seg mb-3" id="rulesSeg">
                        <button type="button" class="uc-seg-btn active" data-tab="rules">قواعد پیش‌فرض</button>
                        <button type="button" class="uc-seg-btn" data-tab="colors">رنگ وضعیت تارگت</button>
                        <button type="button" class="uc-seg-btn" data-tab="followup">هشدار پیگیری</button>
                    </div>

                    <div data-tab-pane="rules">
                        <p class="small text-muted">قاعده برای مشتری‌هایی اعمال می‌شود که برای آن دوره عدد اختصاصی ندارند (و از دورهٔ قبل هم عددی ادامه پیدا نکرده). عدد اختصاصی مشتری همیشه مقدم است؛ قاعدهٔ یک کارشناس بر «همهٔ کارشناسان» مقدم است.</p>
                        <form id="ruleForm" class="uc-rule-form" novalidate>
                            <input type="hidden" id="rId">
                            <div class="uc-rule-row"><label for="rBasis">مبنای تارگت فروش</label>
                                <select class="form-select" id="rBasis">
                                    <option value="floor">کف فروش</option>
                                    <option value="target">سقف فروش</option>
                                </select></div>
                            <div class="uc-rule-row"><label>دورهٔ زمانی</label>
                                <select class="form-select" id="rPeriod">
                                    <option value="monthly">ماهانه</option>
                                    <option value="quarterly">فصلی</option>
                                    <option value="yearly">سالانه</option>
                                </select></div>
                            <div class="uc-rule-row"><label>مقدار هدف</label>
                                <div class="input-group"><input type="text" class="form-control" id="rAmount" placeholder="۰" autocomplete="off"><span class="input-group-text">تومان</span></div></div>
                            <div class="uc-rule-row"><label>کارشناس مسئول</label>
                                <select class="form-select" id="rRep"><option value="0">همهٔ کارشناسان</option></select></div>
                            <div class="uc-rule-row"><label>وضعیت</label>
                                <div class="form-check form-switch m-0 tg-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="rActive" aria-label="وضعیت قاعده" checked>
                                </div></div>
                            <div id="ruleAlert"></div>
                            <div class="uc-rule-btns">
                                <button type="button" class="btn btn-secondary" id="ruleReset">انصراف</button>
                                <button type="button" class="btn btn-primary" id="ruleSave">ذخیره</button>
                            </div>
                        </form>
                        <div class="uc-rule-list mt-3" id="ruleList"></div>
                    </div>

                    <div data-tab-pane="colors" hidden>
                        <p class="small text-muted mb-3">رنگ هر مشتری بر اساس «درصد فروش دوره نسبت به تارگت همان دوره» تعیین می‌شود. تارگت «معلق» حساب نمی‌شود.</p>
                        <div class="row g-3 align-items-end">
                            <div class="col-6">
                                <label class="form-label">قرمز: کمتر از (درصد)</label>
                                <input type="number" class="form-control" id="s_red" min="1" max="999">
                            </div>
                            <div class="col-6">
                                <label class="form-label">سبز: از (درصد) به بالا</label>
                                <input type="number" class="form-control" id="s_green" min="2" max="1000">
                            </div>
                        </div>
                        <div class="uc-bands mt-3" id="bandPreview"></div>
                        <div id="setAlert" class="mt-2"></div>
                        <div class="d-flex justify-content-end mt-3">
                            <button type="button" class="btn btn-primary" id="setSave">ذخیرهٔ رنگ‌ها</button>
                        </div>
                    </div>

                    <!-- داشبورد فروش: «مشتریان نیازمند پیگیری» بعد از چند روز (۱۴۰۵/۰۸/۰۳) -->
                    <div data-tab-pane="followup" hidden>
                        <p class="small text-muted mb-3">مشتری فعالی که این تعداد روز پیگیری «انجام‌شده» نداشته باشد، در بخش «مشتریان نیازمند پیگیری» داشبورد فروش نشان داده می‌شود.</p>
                        <label class="form-label">هشدار عدم پیگیری بعد از (روز)</label>
                        <input type="number" class="form-control" id="s_fudays" min="1" max="365" style="max-width:160px">
                        <div id="fuSetAlert" class="mt-2"></div>
                        <div class="d-flex justify-content-end mt-3">
                            <button type="button" class="btn btn-primary" id="fuSetSave">ذخیرهٔ هشدار پیگیری</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../../assets/js/ucrm-common.js') ?>"></script>
    <script>
        const CAN_EDIT = <?= $__canEdit ? 'true' : 'false' ?>;
        const SEES_ALL = <?= $__seesAll ? 'true' : 'false' ?>;
        const PT_LABEL = { monthly: 'ماهانه', quarterly: 'فصلی', yearly: 'سالانه' };
        const JM = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        const JQ = ['بهار', 'تابستان', 'پاییز', 'زمستان'];

        let gridApi, rows = [], reps = [], current = null, settings = { target_red_below: 80, target_green_from: 100 };
        let periodStart = '', periodEnd = '', tgSelIds = [], tgAllIds = [];
        let periodLabel = '', tgModal, rulesModal, tgMode = 'one', tgIds = [], picker = null, rules = [];

        // ── دوره‌ها ──
        function periodOptions(type) {
            const cy = +String(current[type]).slice(0, 4);
            const out = [];
            for (let y = cy + 1; y >= cy - 2; y--) {
                if (type === 'yearly') out.push([String(y), 'سال ' + faDigits(y)]);
                if (type === 'quarterly') for (let q = 4; q >= 1; q--) out.push([y + '-Q' + q, JQ[q - 1] + ' ' + faDigits(y)]);
                if (type === 'monthly') for (let m = 12; m >= 1; m--) out.push([y + '-' + String(m).padStart(2, '0'), JM[m - 1] + ' ' + faDigits(y)]);
            }
            return out;
        }

        function fillPeriods(keep) {
            const type = document.getElementById('fType').value;
            const sel = document.getElementById('fPeriod');
            sel.innerHTML = periodOptions(type).map(([k, l]) =>
                `<option value="${k}">${l}${k === current[type] ? ' (جاری)' : ''}</option>`).join('');
            sel.value = keep && [...sel.options].some(o => o.value === keep) ? keep : current[type];
        }

        // ── سلول‌ها ──
        // منبع عدد: اختصاصی همین دوره (بی‌علامت) / ادامه از دورهٔ قبل / قاعدهٔ پیش‌فرض — آیکن کوچک با توضیح
        const SRC_TAG = {
            carry: ['bi-arrow-return-left', 'ادامه از دورهٔ قبل (برای این دوره عدد جدا تعیین نشده)'],
            rule: ['bi-sliders', 'از قاعدهٔ «تنظیمات تارگت‌گذاری»'],
        };
        function amountCell(v, src) {
            if (v == null) return '<span class="text-muted small">تعیین نشده</span>';
            const t = SRC_TAG[src];
            const tag = t ? ` <i class="bi ${t[0]} uc-src-ico" title="${t[1]}"></i>` : '';
            return `<span class="uc-amt">${UCRM.money(v)} تومان</span>${tag}`;
        }

        const colDefs = [{
                headerName: '',
                colId: 'sel',
                width: 50,
                checkboxSelection: CAN_EDIT,
                headerCheckboxSelection: CAN_EDIT,
                sortable: false,
                filter: false,
                hide: !CAN_EDIT,
                resizable: false,
            },
            { headerName: 'نام مشتری', field: 'name', flex: 2, minWidth: 140, cellRenderer: p => `<a class="uc-name uc-name-link" href="ucrm-customer.php?id=${p.data.customer_id}">${esc(p.value)}</a>` },
            { headerName: 'کارشناس', field: 'rep_name', flex: 1, minWidth: 110, hide: !SEES_ALL },
            { headerName: 'مبنا', field: 'target_basis', width: 100, filter: false, valueFormatter: p => p.data.target == null ? '' : UCRM.basisLabel(p.value) },
            { headerName: 'تارگت', field: 'target', width: 175, filter: false, cellRenderer: p => amountCell(p.value, p.data.target_src) },
            {
                headerName: 'حجم فروش',
                field: 'sales',
                width: 145,
                filter: false,
                cellRenderer: p => p.value != null ? `<span class="uc-amt">${UCRM.money(p.value)} تومان</span>` :
                    '<span class="text-muted small" title="از فاکتورهای CRM حساب می‌شود">هنوز فاکتوری ثبت نشده</span>'
            },
            {
                headerName: 'وضعیت',
                field: 'is_active',
                width: 105,
                valueFormatter: p => p.value ? 'فعال' : 'معلق',
                cellRenderer: p => p.value ? '<span class="uc-badge st-active">فعال</span>' : '<span class="uc-badge st-suspended">معلق</span>'
            },
            {
                headerName: 'عملیات',
                colId: 'actions',
                width: 75,
                sortable: false,
                filter: false,
                hide: !CAN_EDIT,
                cellRenderer: p => `<button class="ag-action-btn edit" title="ویرایش تارگت" onclick="openEdit([${p.data.customer_id}])"><i class="bi bi-pencil-square"></i></button>`
            },
        ];

        const gridOptions = {
            theme: AgGridFa.theme(),
            columnDefs: colDefs,
            rowData: [],
            enableRtl: true,
            rowSelection: 'multiple',
            suppressRowClickSelection: true,
            pagination: true,
            paginationPageSize: 20,
            paginationPageSizeSelector: [20, 50, 100],
            onPaginationChanged: () => AgGridFa.persianizePaging(),
            defaultColDef: { resizable: true, sortable: true, filter: true },
            getRowId: p => String(p.data.customer_id),
            overlayNoRowsTemplate: '<span class="text-muted">مشتری تأییدشده‌ای برای این کارشناس نیست</span>',
        };

        // keepPage: بارگذاری دوباره بعد از ذخیره/حذف → همان صفحهٔ جدول بماند (AgGridFa.setRowsKeepPage)
        async function load(keepPage) {
            gridApi.showLoadingOverlay();
            const q = new URLSearchParams({
                period_type: document.getElementById('fType').value,
                period: document.getElementById('fPeriod').value,
            });
            const rep = document.getElementById('fRep');
            if (rep && rep.value) q.set('rep_id', rep.value);
            try {
                const d = await UCRM.get('/targets?' + q);
                rows = d.items || [];
                periodLabel = d.period_label;
                periodStart = d.period_start || '';
                periodEnd = d.period_end || '';
                if (d.settings) settings = d.settings;
                if (keepPage) AgGridFa.setRowsKeepPage(gridApi, rows);
                else gridApi.setGridOption('rowData', rows);
            } catch (e) {
                showToast(e.message || 'خطا در بارگذاری', 'error');
                gridApi.setGridOption('rowData', []);
            }
        }

        // ── ویرایش / گروهی / افزودن ──
        function openTg(mode, ids) {
            tgMode = mode;
            tgIds = ids || [];
            const one = mode === 'one' ? rows.find(r => r.customer_id === tgIds[0]) : null;
            document.getElementById('tgTitle').textContent = mode === 'bulk' ? 'ویرایش گروهی تارگت‌ها' : (mode === 'add' ? 'افزودن تارگت' : 'ویرایش تارگت');
            document.getElementById('tgSub').textContent = mode === 'bulk' ? 'مقادیر جدید را برای همه مشتری‌های انتخاب شده اعمال کنید.' : '';
            document.querySelector('#tgSave span').textContent = mode === 'bulk' ? 'اعمال تغییرات' : 'ذخیره';
            // گروهی: تیک = ردیف‌های انتخاب‌شده؛ بدون تیک = همهٔ ردیف‌های دیده‌شده (با فیلترهای صفحه)
            const selWrap = document.getElementById('tgSelWrap'), onlySel = document.getElementById('tgOnlySel');
            selWrap.hidden = mode !== 'bulk';
            if (mode === 'bulk') {
                tgSelIds = tgIds.slice();
                tgAllIds = [];
                gridApi.forEachNodeAfterFilterAndSort(n => n.data && tgAllIds.push(n.data.customer_id));
                onlySel.checked = tgSelIds.length > 0;
                onlySel.disabled = tgSelIds.length === 0;
                bulkWho();
            } else {
                document.getElementById('tgWho').textContent = (one ? 'مشتری: ' + one.name + ' — ' : '') + periodLabel;
            }
            document.getElementById('tgPickWrap').hidden = mode !== 'add';
            document.getElementById('tStart').value = faDigits(periodStart);
            document.getElementById('tEnd').value = faDigits(periodEnd);
            document.getElementById('tTarget').value = '';
            document.getElementById('tBasis').value = one && one.target != null && one.target_basis === 'floor' ? 'floor' : 'target';
            fillTgRep(one);
            document.getElementById('tActive').checked = one ? one.is_active : true;
            document.getElementById('tActive').indeterminate = mode === 'bulk';
            if (one) {
                if (one.target_src === 'own') UCRM.setMoney(document.getElementById('tTarget'), one.target);
            }
            document.getElementById('tgHint').textContent = mode === 'bulk' ?
                'هر فیلدی را خالی بگذارید، برای این مشتری‌ها تغییر نمی‌کند. وضعیت فقط اگر کلیدش را بزنید عوض می‌شود.' :
                'خالی = عدد اختصاصی این دوره ندارد (از دورهٔ قبل یا تنظیمات تارگت‌گذاری می‌آید).';
            document.getElementById('tgAlert').innerHTML = '';
            if (mode === 'add') {
                const items = rows.filter(r => r.target_src !== 'own').map(r => ({
                    id: r.customer_id, rep_id: r.rep_id, label: r.name, meta: r.rep_name || '', search: r.name + ' ' + (r.rep_name || ''),
                }));
                if (!picker) picker = EntityPicker.create({ container: '#tgPicker', items, searchOnly: true, onSelect: it => { document.getElementById('tRep').value = it && it.rep_id ? String(it.rep_id) : ''; }, placeholder: 'جست‌وجوی مشتری…' });
                else { picker.updateItems(items); picker.reset(); }
            }
            tgModal.show();
        }

        // کارشناس فروش مشتری: ویرایش تکی با کارشناس فعلی؛ گروهی و افزودن با «بدون تغییر» (تا مشتری انتخاب شود)
        function fillTgRep(one) {
            const sel = document.getElementById('tRep');
            const opts = reps.map(r => `<option value="${r.id}">${esc(r.name)}</option>`);
            if (one && one.rep_id && !reps.some(r => +r.id === +one.rep_id)) opts.push(`<option value="${one.rep_id}">${esc(one.rep_name || '')}</option>`);
            const first = !one ? '<option value="">بدون تغییر</option>' : (one.rep_id ? '' : '<option value="">تعیین نشده</option>');
            sel.innerHTML = first + opts.join('');
            sel.value = one && one.rep_id ? String(one.rep_id) : '';
        }

        function openEdit(ids) { openTg('one', ids); }

        // متن «N مشتری» پنجرهٔ گروهی، بسته به تیک «اعمال برای مشتری انتخاب شده»
        function bulkWho() {
            const only = document.getElementById('tgOnlySel').checked;
            tgIds = only ? tgSelIds.slice() : tgAllIds.slice();
            document.getElementById('tgWho').textContent = faDigits(tgIds.length) + ' مشتری' +
                (only ? ' انتخاب‌شده' : ' (همهٔ مشتری‌های فهرست با فیلترهای فعلی)') + ' — ' + periodLabel;
        }

        async function saveTg() {
            const al = document.getElementById('tgAlert');
            let ids = tgIds;
            if (tgMode === 'add') {
                const v = picker && picker.getValue();
                if (!v) { al.innerHTML = '<div class="alert alert-warning py-2 mb-0">مشتری را انتخاب کنید.</div>'; return; }
                ids = [v.id];
            }
            const t = document.getElementById('tTarget'), a = document.getElementById('tActive');
            if (tgMode === 'bulk' && !ids.length) { al.innerHTML = '<div class="alert alert-warning py-2 mb-0">مشتری‌ای برای اعمال نیست.</div>'; return; }
            const body = { customer_ids: ids, period_type: document.getElementById('fType').value, period: document.getElementById('fPeriod').value };
            const repSel = document.getElementById('tRep').value;
            if (repSel) body.rep_id = +repSel;
            const basis = document.getElementById('tBasis').value;
            if (tgMode === 'bulk') {
                if (t.value.trim()) { body.target = UCRM.moneyVal(t); body.target_basis = basis; }
                if (!a.indeterminate) body.is_active = a.checked;
            } else {
                body.target = UCRM.moneyVal(t);
                body.target_basis = basis;
                body.is_active = a.checked;
                if (tgMode === 'add' && !body.target) {
                    al.innerHTML = '<div class="alert alert-warning py-2 mb-0">مبلغ مبنای تارگت فروش را وارد کنید.</div>';
                    return;
                }
            }
            const btn = document.getElementById('tgSave');
            btn.disabled = true;
            try {
                const d = await UCRM.send('PUT', '/targets', body);
                tgModal.hide();
                showToast(d.message || 'ذخیره شد', 'success');
                load(true);
            } catch (e) {
                al.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + esc(e.message || 'خطا') + '</div>';
            } finally {
                btn.disabled = false;
            }
        }

        // ── تنظیمات تارگت‌گذاری ──
        function renderRules() {
            const box = document.getElementById('ruleList');
            if (!rules.length) { box.innerHTML = '<div class="text-muted small text-center py-3">هنوز قاعده‌ای تعریف نشده است.</div>'; return; }
            box.innerHTML = `<table class="table table-sm align-middle mb-0"><thead><tr>
                <th>مبنا</th><th>دوره</th><th>مقدار</th><th>کارشناس</th><th>وضعیت</th><th></th></tr></thead><tbody>` +
                rules.map(r => `<tr>
                    <td>${UCRM.basisLabel(r.basis)}</td><td>${PT_LABEL[r.period_type] || ''}</td>
                    <td>${UCRM.money(r.amount)} تومان</td><td>${r.rep_id ? esc(r.rep_name) : 'همهٔ کارشناسان'}</td>
                    <td>${r.is_active ? '<span class="uc-badge st-active">فعال</span>' : '<span class="uc-badge st-suspended">غیرفعال</span>'}</td>
                    <td class="text-nowrap"><button class="ag-action-btn edit" title="ویرایش" onclick="editRule(${r.id})"><i class="bi bi-pencil"></i></button>
                        <button class="ag-action-btn del" title="حذف" onclick="delRule(${r.id})"><i class="bi bi-trash"></i></button></td></tr>`).join('') +
                '</tbody></table>';
        }

        async function loadRules() {
            try {
                rules = (await UCRM.get('/target-rules')).items || [];
            } catch (e) { rules = []; }
            renderRules();
        }

        function resetRule() {
            document.getElementById('rId').value = '';
            document.getElementById('rBasis').value = 'floor';
            document.getElementById('rPeriod').value = document.getElementById('fType').value;
            document.getElementById('rAmount').value = '';
            document.getElementById('rRep').value = '0';
            document.getElementById('rActive').checked = true;
            document.getElementById('ruleAlert').innerHTML = '';
        }

        function editRule(id) {
            const r = rules.find(x => x.id === id);
            if (!r) return;
            document.getElementById('rId').value = r.id;
            document.getElementById('rBasis').value = r.basis;
            document.getElementById('rPeriod').value = r.period_type;
            UCRM.setMoney(document.getElementById('rAmount'), r.amount);
            const sel = document.getElementById('rRep');
            if (r.rep_id && ![...sel.options].some(o => +o.value === r.rep_id)) sel.insertAdjacentHTML('beforeend', `<option value="${r.rep_id}">${esc(r.rep_name)}</option>`);
            sel.value = String(r.rep_id || 0);
            document.getElementById('rActive').checked = r.is_active;
            document.getElementById('ruleAlert').innerHTML = '';
            document.getElementById('rBasis').focus();
        }

        async function saveRule() {
            const id = +document.getElementById('rId').value || 0;
            const body = {
                period_type: document.getElementById('rPeriod').value,
                amount: UCRM.moneyVal(document.getElementById('rAmount')),
                rep_id: +document.getElementById('rRep').value || 0,
                basis: document.getElementById('rBasis').value,
                is_active: document.getElementById('rActive').checked,
            };
            const al = document.getElementById('ruleAlert');
            if (!body.amount) { al.innerHTML = '<div class="alert alert-warning py-2">مقدار هدف را وارد کنید.</div>'; return; }
            try {
                const d = await UCRM.send(id ? 'PUT' : 'POST', '/target-rules' + (id ? '/' + id : ''), body);
                showToast(d.message || 'ذخیره شد', 'success');
                resetRule();
                await loadRules();
                load(true);
            } catch (e) {
                al.innerHTML = '<div class="alert alert-danger py-2">' + esc(e.message || 'خطا') + '</div>';
            }
        }

        function delRule(id) {
            uiConfirm('این قاعدهٔ تارگت‌گذاری حذف شود؟', async () => {
                try {
                    await UCRM.send('DELETE', '/target-rules/' + id, {});
                    showToast('قاعده حذف شد', 'success');
                    await loadRules();
                    load(true);
                } catch (e) { showToast(e.message || 'خطا', 'error'); }
            }, { danger: true, yesText: 'حذف', noText: 'انصراف' });
        }

        function rulesTab(t) {
            document.querySelectorAll('#rulesSeg .uc-seg-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === t));
            document.querySelectorAll('[data-tab-pane]').forEach(p => p.hidden = p.dataset.tabPane !== t);
        }

        function bandPreview() {
            const r = +document.getElementById('s_red').value || 0;
            const g = +document.getElementById('s_green').value || 0;
            document.getElementById('bandPreview').innerHTML =
                `<span class="uc-target red">کمتر از ${faDigits(r)}٪</span>
                 <span class="uc-target orange">${faDigits(r)}٪ تا کمتر از ${faDigits(g)}٪</span>
                 <span class="uc-target green">${faDigits(g)}٪ و بیشتر</span>`;
        }

        async function saveColors() {
            const body = {
                target_red_below: parseInt(UCRM.toEn(document.getElementById('s_red').value), 10) || 0,
                target_green_from: parseInt(UCRM.toEn(document.getElementById('s_green').value), 10) || 0,
            };
            try {
                const d = await UCRM.send('PUT', '/settings', body);
                settings = d.settings;
                showToast('رنگ‌های وضعیت تارگت ذخیره شد', 'success');
            } catch (e) {
                document.getElementById('setAlert').innerHTML = '<div class="alert alert-danger py-2 mb-0">' + esc(e.message || 'خطا') + '</div>';
            }
        }

        async function saveFollowupDays() {
            const n = parseInt(UCRM.toEn(document.getElementById('s_fudays').value), 10) || 0;
            const box = document.getElementById('fuSetAlert');
            box.innerHTML = '';
            if (n < 1 || n > 365) {
                box.innerHTML = '<div class="alert alert-danger py-2 mb-0">عددی بین ۱ تا ۳۶۵ وارد کنید.</div>';
                return;
            }
            try {
                const d = await UCRM.send('PUT', '/settings', { followup_alert_days: n });
                settings = d.settings;
                showToast('هشدار پیگیری ذخیره شد: بعد از ' + faDigits(n) + ' روز', 'success');
            } catch (e) {
                box.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + esc(e.message || 'خطا') + '</div>';
            }
        }

        function openRules() {
            rulesTab('rules');
            resetRule();
            document.getElementById('s_red').value = settings.target_red_below;
            document.getElementById('s_green').value = settings.target_green_from;
            document.getElementById('s_fudays').value = settings.followup_alert_days || 14;
            document.getElementById('setAlert').innerHTML = '';
            document.getElementById('fuSetAlert').innerHTML = '';
            bandPreview();
            loadRules();
            rulesModal.show();
        }

        document.addEventListener('DOMContentLoaded', async () => {
            if (!UCRM.tok()) { location.href = '../index.php'; return; }
            gridApi = agGrid.createGrid(document.getElementById('tgGrid'), gridOptions);
            // کلیک راست روی ردیف = منوی همان موارد ستون عملیات (assets/js/ag-grid-fa.js)
            AgGridFa.rowContextMenu(document.getElementById('tgGrid'), gridApi, null, { insideGrid: true });
            tgModal = new bootstrap.Modal(document.getElementById('tgModal'));
            rulesModal = new bootstrap.Modal(document.getElementById('rulesModal'));
            ['tTarget', 'rAmount'].forEach(id => UCRM.bindMoney(document.getElementById(id)));
            document.getElementById('tActive').addEventListener('change', e => { e.target.indeterminate = false; });
            document.getElementById('tgSave').addEventListener('click', saveTg);
            // فوکوس خودکار روی اولین فیلد هر مودال
            document.getElementById('tgModal').addEventListener('shown.bs.modal', () =>
                (tgMode === 'add' && picker ? picker.focus() : document.getElementById('tTarget').focus()));
            document.getElementById('rulesModal').addEventListener('shown.bs.modal', () => document.getElementById('rBasis').focus());

            try {
                const [me, rp, first] = await Promise.all([UCRM.get('/me'), UCRM.get('/reps'), UCRM.get('/targets')]);
                if (document.getElementById('meName')) document.getElementById('meName').textContent = me.name;
                reps = rp.items || [];
                current = first.current;
                settings = first.settings || settings;
                const opts = reps.map(r => `<option value="${r.id}">${esc(r.name)}</option>`).join('');
                if (document.getElementById('fRep')) document.getElementById('fRep').insertAdjacentHTML('beforeend', opts);
                document.getElementById('rRep').insertAdjacentHTML('beforeend', opts);
            } catch (e) {
                showToast(e.message || 'خطا در بارگذاری', 'error');
                return;
            }
            // فیلترهای کارشناس/نوع دوره/دوره بعد از رفرش/برگشت می‌مانند (search-persist.js)
            SearchPersist.bindSelect(document.getElementById('fType'), 'ucrm-targets');
            fillPeriods(SearchPersist.getValue('ucrm-targets', 'fPeriod'));
            SearchPersist.bindSelect(document.getElementById('fPeriod'), 'ucrm-targets');
            if (document.getElementById('fRep')) SearchPersist.bindSelect(document.getElementById('fRep'), 'ucrm-targets');
            document.getElementById('fType').addEventListener('change', () => {
                fillPeriods();
                // دورهٔ تازه با عوض‌شدن نوع، برنامه‌ای عوض شده (رویداد change ندارد) → دستی ذخیره شود
                SearchPersist.setValue('ucrm-targets', 'fPeriod', document.getElementById('fPeriod').value);
                load();
            });
            document.getElementById('fPeriod').addEventListener('change', () => load());
            if (document.getElementById('fRep')) document.getElementById('fRep').addEventListener('change', () => load());

            if (CAN_EDIT) {
                document.getElementById('btnRules').addEventListener('click', openRules);
                document.getElementById('btnAddT').addEventListener('click', () => openTg('add'));
                document.getElementById('tgOnlySel').addEventListener('change', bulkWho);
                document.getElementById('btnBulk').addEventListener('click', () => {
                    openTg('bulk', gridApi.getSelectedRows().map(r => r.customer_id));
                });
                document.querySelectorAll('#rulesSeg .uc-seg-btn').forEach(b => b.addEventListener('click', () => rulesTab(b.dataset.tab)));
                document.getElementById('ruleSave').addEventListener('click', saveRule);
                document.getElementById('ruleReset').addEventListener('click', resetRule);
                document.getElementById('setSave').addEventListener('click', saveColors);
                document.getElementById('fuSetSave').addEventListener('click', saveFollowupDays);
                ['s_red', 's_green'].forEach(id => document.getElementById(id).addEventListener('input', bandPreview));
            }
            load();
        });
    </script>
</body>

</html>
