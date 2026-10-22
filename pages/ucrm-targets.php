<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// CRM جدید — «تنظیمات هدف‌گذاری و تارگت‌های فروش». API: crm-service/ucrm_targets.go
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
    <title>تارگت‌های فروش - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
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
                <div>
                    <h1>تنظیمات هدف‌گذاری و تارگت‌های فروش</h1>
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
                </div>
            </div>
            <?php if ($__canEdit): ?>
                <div class="uc-tg-actions">
                    <button class="btn btn-outline-secondary" id="btnRules"><i class="bi bi-pencil-square ms-1"></i> تنظیمات هدف‌گذاری</button>
                    <button class="btn btn-primary" id="btnBulk"><i class="bi bi-pencil-square ms-1"></i> ویرایش گروهی</button>
                    <button class="btn btn-primary" id="btnAddT"><i class="bi bi-plus-lg ms-1"></i> افزودن تارگت</button>
                </div>
            <?php endif; ?>
        </div>

        <div id="tgGrid" class="ag-theme-alpine inv-grid" style="height: 620px; width: 100%; padding-top: 1rem;"></div>
    </div>

    <!-- ویرایش تکی / گروهی / افزودن تارگت -->
    <div class="modal fade" id="tgModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header-custom modal-header border-0">
                    <h5 class="modal-title text-white"><i class="bi bi-bullseye ms-2"></i><span id="tgTitle">ویرایش تارگت</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="tgWho" class="small text-muted mb-3"></div>
                    <div id="tgPickWrap" class="mb-3" hidden>
                        <label class="form-label">مشتری <span class="text-danger">*</span></label>
                        <div id="tgPicker"></div>
                        <div class="form-text">فقط مشتری‌هایی که برای این دوره عدد اختصاصی ندارند.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">تارگت هدف (تومان)</label>
                            <input type="text" class="form-control" id="tTarget" autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label class="form-label">کف فروش (تومان)</label>
                            <input type="text" class="form-control" id="tFloor" autocomplete="off">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="tActive" checked>
                                <label class="form-check-label" for="tActive" id="tActiveLbl">فعال</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-text mt-2" id="tgHint"></div>
                    <div id="tgAlert" class="mt-2"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" id="tgSave">ذخیره</button>
                </div>
            </div>
        </div>
    </div>

    <!-- تنظیمات هدف‌گذاری: قواعد پیش‌فرض + رنگ وضعیت تارگت -->
    <div class="modal fade" id="rulesModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header-custom modal-header border-0">
                    <h5 class="modal-title text-white"><i class="bi bi-sliders ms-2"></i>تنظیمات هدف‌گذاری</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="uc-seg mb-3" id="rulesSeg">
                        <button type="button" class="uc-seg-btn active" data-tab="rules">قواعد پیش‌فرض</button>
                        <button type="button" class="uc-seg-btn" data-tab="colors">رنگ وضعیت تارگت</button>
                    </div>

                    <div data-tab-pane="rules">
                        <p class="small text-muted">قاعده برای مشتری‌هایی اعمال می‌شود که برای آن دوره عدد اختصاصی ندارند (و از دورهٔ قبل هم عددی ادامه پیدا نکرده). عدد اختصاصی مشتری همیشه مقدم است؛ قاعدهٔ یک کارشناس بر «همهٔ کارشناسان» مقدم است.</p>
                        <form id="ruleForm" class="uc-rule-form" novalidate>
                            <input type="hidden" id="rId">
                            <div class="uc-rule-row"><label>مبنای هدف‌گذاری</label>
                                <select class="form-select" id="rBasis">
                                    <option value="floor">کف فروش</option>
                                    <option value="target">تارگت</option>
                                </select></div>
                            <div class="uc-rule-row"><label>دورهٔ زمانی</label>
                                <select class="form-select" id="rPeriod">
                                    <option value="monthly">ماهانه</option>
                                    <option value="quarterly">فصلی</option>
                                    <option value="yearly">سالانه</option>
                                </select></div>
                            <div class="uc-rule-row"><label>مقدار هدف</label>
                                <div class="input-group"><input type="text" class="form-control" id="rAmount" autocomplete="off"><span class="input-group-text">تومان</span></div></div>
                            <div class="uc-rule-row"><label>کارشناس مسئول</label>
                                <select class="form-select" id="rRep"><option value="0">همهٔ کارشناسان</option></select></div>
                            <div class="uc-rule-row"><label>وضعیت</label>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" id="rActive" checked>
                                    <label class="form-check-label" for="rActive" id="rActiveLbl">فعال</label>
                                </div></div>
                            <div id="ruleAlert"></div>
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-secondary" id="ruleReset">قاعدهٔ جدید</button>
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
        const BASIS_LABEL = { target: 'تارگت', floor: 'کف فروش' };
        const JM = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        const JQ = ['بهار', 'تابستان', 'پاییز', 'زمستان'];

        let gridApi, rows = [], reps = [], current = null, settings = { target_red_below: 80, target_green_from: 100 };
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
            rule: ['bi-sliders', 'از قاعدهٔ «تنظیمات هدف‌گذاری»'],
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
            { headerName: 'نام مشتری', field: 'name', flex: 2, minWidth: 140, cellRenderer: p => `<span class="uc-name">${esc(p.value)}</span>` },
            { headerName: 'کارشناس', field: 'rep_name', flex: 1, minWidth: 110, hide: !SEES_ALL },
            { headerName: 'تارگت هدف', field: 'target', width: 175, filter: false, cellRenderer: p => amountCell(p.value, p.data.target_src) },
            { headerName: 'کف فروش', field: 'floor', width: 175, filter: false, cellRenderer: p => amountCell(p.value, p.data.floor_src) },
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
                cellRenderer: p => p.value ? '<span class="uc-dot on">فعال</span>' : '<span class="uc-dot off">معلق</span>'
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

        async function load() {
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
                if (d.settings) settings = d.settings;
                gridApi.setGridOption('rowData', rows);
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
            document.getElementById('tgTitle').textContent = mode === 'bulk' ? 'ویرایش گروهی تارگت' : (mode === 'add' ? 'افزودن تارگت' : 'ویرایش تارگت');
            document.getElementById('tgWho').textContent = (mode === 'bulk' ? faDigits(tgIds.length) + ' مشتری انتخاب‌شده' :
                (one ? 'مشتری: ' + one.name : '')) + ' — ' + periodLabel;
            document.getElementById('tgPickWrap').hidden = mode !== 'add';
            ['tTarget', 'tFloor'].forEach(id => document.getElementById(id).value = '');
            document.getElementById('tActive').checked = one ? one.is_active : true;
            document.getElementById('tActive').indeterminate = mode === 'bulk';
            activeLbl('tActive', 'tActiveLbl');
            if (one) {
                if (one.target_src === 'own') UCRM.setMoney(document.getElementById('tTarget'), one.target);
                if (one.floor_src === 'own') UCRM.setMoney(document.getElementById('tFloor'), one.floor);
            }
            document.getElementById('tgHint').textContent = mode === 'bulk' ?
                'هر فیلدی را خالی بگذارید، برای این مشتری‌ها تغییر نمی‌کند. وضعیت فقط اگر کلیدش را بزنید عوض می‌شود.' :
                'خالی = عدد اختصاصی این دوره ندارد (از دورهٔ قبل یا تنظیمات هدف‌گذاری می‌آید).';
            document.getElementById('tgAlert').innerHTML = '';
            if (mode === 'add') {
                const items = rows.filter(r => r.target_src !== 'own' && r.floor_src !== 'own').map(r => ({
                    id: r.customer_id, label: r.name, meta: r.rep_name || '', search: r.name + ' ' + (r.rep_name || ''),
                }));
                if (!picker) picker = EntityPicker.create({ container: '#tgPicker', items, placeholder: 'جست‌وجوی مشتری…' });
                else { picker.updateItems(items); picker.reset(); }
            }
            tgModal.show();
        }

        function openEdit(ids) { openTg('one', ids); }

        function activeLbl(chk, lbl) {
            const c = document.getElementById(chk);
            document.getElementById(lbl).textContent = c.indeterminate ? 'بدون تغییر' : (c.checked ? 'فعال' : 'معلق');
        }

        async function saveTg() {
            const al = document.getElementById('tgAlert');
            let ids = tgIds;
            if (tgMode === 'add') {
                const v = picker && picker.getValue();
                if (!v) { al.innerHTML = '<div class="alert alert-warning py-2 mb-0">مشتری را انتخاب کنید.</div>'; return; }
                ids = [v.id];
            }
            const t = document.getElementById('tTarget'), f = document.getElementById('tFloor'), a = document.getElementById('tActive');
            const body = { customer_ids: ids, period_type: document.getElementById('fType').value, period: document.getElementById('fPeriod').value };
            if (tgMode === 'bulk') {
                if (t.value.trim()) body.target = UCRM.moneyVal(t);
                if (f.value.trim()) body.floor = UCRM.moneyVal(f);
                if (!a.indeterminate) body.is_active = a.checked;
            } else {
                body.target = UCRM.moneyVal(t);
                body.floor = UCRM.moneyVal(f);
                body.is_active = a.checked;
                if (tgMode === 'add' && !body.target && !body.floor) {
                    al.innerHTML = '<div class="alert alert-warning py-2 mb-0">تارگت یا کف فروش را وارد کنید.</div>';
                    return;
                }
            }
            const btn = document.getElementById('tgSave');
            btn.disabled = true;
            try {
                const d = await UCRM.send('PUT', '/targets', body);
                tgModal.hide();
                showToast(d.message || 'ذخیره شد', 'success');
                load();
            } catch (e) {
                al.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + esc(e.message || 'خطا') + '</div>';
            } finally {
                btn.disabled = false;
            }
        }

        // ── تنظیمات هدف‌گذاری ──
        function renderRules() {
            const box = document.getElementById('ruleList');
            if (!rules.length) { box.innerHTML = '<div class="text-muted small text-center py-3">هنوز قاعده‌ای تعریف نشده است.</div>'; return; }
            box.innerHTML = `<table class="table table-sm align-middle mb-0"><thead><tr>
                <th>مبنا</th><th>دوره</th><th>مقدار</th><th>کارشناس</th><th>وضعیت</th><th></th></tr></thead><tbody>` +
                rules.map(r => `<tr>
                    <td>${BASIS_LABEL[r.basis] || ''}</td><td>${PT_LABEL[r.period_type] || ''}</td>
                    <td>${UCRM.money(r.amount)} تومان</td><td>${r.rep_id ? esc(r.rep_name) : 'همهٔ کارشناسان'}</td>
                    <td>${r.is_active ? '<span class="uc-dot on">فعال</span>' : '<span class="uc-dot off">غیرفعال</span>'}</td>
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
            activeLbl('rActive', 'rActiveLbl');
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
            activeLbl('rActive', 'rActiveLbl');
            document.getElementById('ruleAlert').innerHTML = '';
            document.getElementById('rBasis').focus();
        }

        async function saveRule() {
            const id = +document.getElementById('rId').value || 0;
            const body = {
                basis: document.getElementById('rBasis').value,
                period_type: document.getElementById('rPeriod').value,
                amount: UCRM.moneyVal(document.getElementById('rAmount')),
                rep_id: +document.getElementById('rRep').value || 0,
                is_active: document.getElementById('rActive').checked,
            };
            const al = document.getElementById('ruleAlert');
            if (!body.amount) { al.innerHTML = '<div class="alert alert-warning py-2">مقدار هدف را وارد کنید.</div>'; return; }
            try {
                const d = await UCRM.send(id ? 'PUT' : 'POST', '/target-rules' + (id ? '/' + id : ''), body);
                showToast(d.message || 'ذخیره شد', 'success');
                resetRule();
                await loadRules();
                load();
            } catch (e) {
                al.innerHTML = '<div class="alert alert-danger py-2">' + esc(e.message || 'خطا') + '</div>';
            }
        }

        function delRule(id) {
            uiConfirm('این قاعدهٔ هدف‌گذاری حذف شود؟', async () => {
                try {
                    await UCRM.send('DELETE', '/target-rules/' + id, {});
                    showToast('قاعده حذف شد', 'success');
                    await loadRules();
                    load();
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

        function openRules() {
            rulesTab('rules');
            resetRule();
            document.getElementById('s_red').value = settings.target_red_below;
            document.getElementById('s_green').value = settings.target_green_from;
            document.getElementById('setAlert').innerHTML = '';
            bandPreview();
            loadRules();
            rulesModal.show();
        }

        document.addEventListener('DOMContentLoaded', async () => {
            if (!UCRM.tok()) { location.href = '../index.php'; return; }
            gridApi = agGrid.createGrid(document.getElementById('tgGrid'), gridOptions);
            // کلیک راست روی ردیف = منوی همان موارد ستون عملیات (assets/js/ag-grid-fa.js)
            AgGridFa.rowContextMenu(document.getElementById('tgGrid'), gridApi);
            tgModal = new bootstrap.Modal(document.getElementById('tgModal'));
            rulesModal = new bootstrap.Modal(document.getElementById('rulesModal'));
            ['tTarget', 'tFloor', 'rAmount'].forEach(id => UCRM.bindMoney(document.getElementById(id)));
            document.getElementById('tActive').addEventListener('change', e => { e.target.indeterminate = false; activeLbl('tActive', 'tActiveLbl'); });
            document.getElementById('rActive').addEventListener('change', () => activeLbl('rActive', 'rActiveLbl'));
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
            fillPeriods();
            document.getElementById('fType').addEventListener('change', () => { fillPeriods(); load(); });
            document.getElementById('fPeriod').addEventListener('change', load);
            if (document.getElementById('fRep')) document.getElementById('fRep').addEventListener('change', load);

            if (CAN_EDIT) {
                document.getElementById('btnRules').addEventListener('click', openRules);
                document.getElementById('btnAddT').addEventListener('click', () => openTg('add'));
                document.getElementById('btnBulk').addEventListener('click', () => {
                    const ids = gridApi.getSelectedRows().map(r => r.customer_id);
                    if (!ids.length) { showToast('اول مشتری‌ها را از ستون انتخاب تیک بزنید.', 'warning'); return; }
                    openTg('bulk', ids);
                });
                document.querySelectorAll('#rulesSeg .uc-seg-btn').forEach(b => b.addEventListener('click', () => rulesTab(b.dataset.tab)));
                document.getElementById('ruleSave').addEventListener('click', saveRule);
                document.getElementById('ruleReset').addEventListener('click', resetRule);
                document.getElementById('setSave').addEventListener('click', saveColors);
                ['s_red', 's_green'].forEach(id => document.getElementById(id).addEventListener('input', bandPreview));
            }
            load();
        });
    </script>
</body>

</html>
