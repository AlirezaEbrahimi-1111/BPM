<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../config/config.php';
require_once '../includes/version.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اطلاعیه‌های سازمانی</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>" rel="stylesheet">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
        <script src="<?= asset('../assets/js/assignee-picker.js') ?>"></script>

</head>

<body>
    <?php include 'header.php'; ?>

    <style>
    .overview-container { max-width: 1100px !important; }

    .ann-head-card {
        background: var(--primary-gradient, linear-gradient(135deg,#6366F1,#8B5CF6));
        border-radius: var(--radius-lg, 16px); padding: 1.2rem 1.4rem; margin-bottom: 1.25rem;
        box-shadow: var(--shadow-md); color: #fff;
        display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap;
    }
    .ann-head-card h1 { font-size: 1.35rem; font-weight: 800; margin: 0; color: #fff; display: flex; align-items: center; gap: .5rem; }
    .ann-head-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
    .ann-head-actions .btn-ghost { background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.35); color: #fff; border-radius: 10px; padding: .45rem .9rem; font-size: .85rem; font-weight: 600; cursor: pointer; }
    .ann-head-actions .btn-ghost:hover { background: rgba(255,255,255,.28); }
    .ann-head-actions .btn-white { background: #fff; color: #4f46e5; border: none; border-radius: 10px; padding: .45rem 1rem; font-size: .9rem; font-weight: 700; cursor: pointer; box-shadow: var(--shadow-sm); }

    .ann-grid-card { background: #fff; border: 1px solid #eceef3; border-radius: var(--radius-lg, 16px); padding: 1rem; box-shadow: var(--shadow-sm); }

    /* AG Grid */
    #annGrid { width: 100%; height: 600px; }
    .ann-grid-title { font-weight: 700; color: #1e2233; }
    .ann-chev { color: #9097a6; transition: transform .15s; }
    .ann-tag { font-size: .72rem; font-weight: 700; padding: .12rem .55rem; border-radius: 999px; }
    .ann-scope-badge { font-size: .72rem; font-weight: 600; padding: .12rem .55rem; border-radius: 999px; background: #eef2ff; color: #4f46e5; }
    .ann-readstat { font-size: .82rem; color: #6b7280; }
    .ann-icon-btn { border: 1px solid #e6e8ef; background: #fff; color: #6b7280; width: 30px; height: 30px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all .15s ease; }
    .ann-icon-btn:hover { background: #f5f3ff; color: var(--primary, #6366F1); border-color: var(--primary, #6366F1); }
    .ann-icon-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

    /* ردیف جزئیات (تمام‌عرض) */
    .ann-detail { background: #fbfaff; border-right: 4px solid var(--primary, #6366F1); padding: .9rem 1.2rem; height: 100%; overflow: auto; }
    .ann-detail-text { color: #4a5160; line-height: 2; white-space: pre-wrap; font-size: .92rem; }
    .ann-detail-meta { margin-top: .6rem; color: #9097a6; font-size: .8rem; display: flex; gap: 1rem; flex-wrap: wrap; }

    /* مودال */
    #annModal .modal-content { border: none; border-radius: 18px; overflow: visible; box-shadow: 0 20px 60px rgba(24,28,46,.18); }
    #annModal .modal-header { border-bottom: 1px solid #eef0f5; padding: 1.1rem 1.4rem; }
    #annModal .modal-title { font-weight: 800; color: #1e2233; }
    #annModal .modal-body { padding: 1.3rem 1.4rem; overflow: visible; }
    #annModal .modal-footer { border-top: 1px solid #eef0f5; padding: 1rem 1.4rem; }
    #annModal .form-label { font-weight: 600; color: #1e2233; margin-bottom: .4rem; font-size: .9rem; }
    #annModal .form-control, #annModal .form-select, #annModal .persian-datepicker-input { border: 1px solid #e3e6ef; border-radius: 10px; padding: .6rem .85rem; font-size: .92rem; min-height: 44px; }
    #annModal .form-control:focus, #annModal .form-select:focus { border-color: var(--primary, #6366F1); box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
    .ann-switch { display: flex; align-items: center; gap: .6rem; padding: .55rem .2rem; }
    .ann-switch .form-check-input { width: 2.6em; height: 1.4em; margin: 0; cursor: pointer; float: none; }
    .ann-switch .form-check-input:checked { background-color: var(--primary, #6366F1); border-color: var(--primary, #6366F1); }
    .ann-switch .form-check-label { font-weight: 600; color: #1e2233; cursor: pointer; }
    .ann-scope-options { display: flex; gap: .55rem; flex-wrap: wrap; }
    .ann-scope-chip { flex: 0 0 auto; border: 1.5px solid #e6e8ef; border-radius: 12px; padding: .5rem .85rem; display: flex; align-items: center; gap: .45rem; cursor: pointer; transition: all .15s ease; font-weight: 600; color: #4a5160; user-select: none; text-align: start; font-size: .85rem; }
    .ann-scope-chip .chip-check { color: #cfd4e4; font-size: 1.15rem; flex-shrink: 0; }
    .ann-scope-chip.active { border-color: var(--primary, #6366F1); background: #f5f3ff; color: #4f46e5; }
    .ann-scope-chip.active .chip-check { color: var(--primary, #6366F1); }
    .ann-btn-primary { background: var(--primary-gradient, linear-gradient(135deg,#6366F1,#8B5CF6)) !important; border: none !important; color: #fff !important; border-radius: 10px !important; padding: .6rem 1.4rem !important; font-weight: 700 !important; box-shadow: var(--shadow-md); }
    .ann-btn-primary:hover { filter: brightness(1.06); }

    @media (max-width: 768px) { .overview-container { max-width: 100% !important; } .ann-head-card h1 { font-size: 1.15rem; } }
    </style>

    <div class="overview-container">
        <div class="ann-head-card">
            <h1><i class="bi bi-megaphone"></i> اطلاعیه‌های سازمانی</h1>
            <div class="ann-head-actions">
                <button class="btn-ghost" id="markAllReadBtn" onclick="markAllRead()"><i class="bi bi-check2-all ms-1"></i> همه را خوانده‌شده کن</button>
                <button class="btn-white" id="newAnnBtn" style="display:none;" onclick="openCreate()"><i class="bi bi-plus-circle ms-1"></i> اطلاعیهٔ جدید</button>
            </div>
        </div>

        <div class="ann-grid-card">
            <div id="annGrid" class="ag-theme-alpine"></div>
        </div>
    </div>

    <!-- مودال ساخت/ویرایش -->
    <div class="modal fade" id="annModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="annModalTitle">اطلاعیهٔ جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="annId" value="">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">عنوان *</label>
                            <input type="text" class="form-control" id="annTitle" placeholder="عنوان اطلاعیه">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">اولویت</label>
                            <select class="form-select" id="annPriority">
                                <option value="normal">عادی</option>
                                <option value="low">کم</option>
                                <option value="high">مهم</option>
                                <option value="urgent">فوری</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">متن *</label>
                        <textarea class="form-control" id="annContent" rows="4" placeholder="متن اطلاعیه..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">زمان انتشار <small class="text-muted">(اختیاری)</small></label>
                            <div class="persian-datepicker-wrapper">
                                <input type="text" class="persian-datepicker-input form-control" id="annPublishAt" placeholder="انتخاب تاریخ..." readonly>
                                <div class="persian-datepicker">
                                    <div class="datepicker-header"><button type="button" class="datepicker-nav" data-action="prev">►</button><span class="datepicker-current"></span><button type="button" class="datepicker-nav" data-action="next">◄</button></div>
                                    <div class="datepicker-weekdays"><div class="datepicker-weekday">ش</div><div class="datepicker-weekday">ی</div><div class="datepicker-weekday">د</div><div class="datepicker-weekday">س</div><div class="datepicker-weekday">چ</div><div class="datepicker-weekday">پ</div><div class="datepicker-weekday">ج</div></div>
                                    <div class="datepicker-days"></div>
                                    <button type="button" class="datepicker-today-btn">امروز</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">زمان انقضا <small class="text-muted">(اختیاری)</small></label>
                            <div class="persian-datepicker-wrapper">
                                <input type="text" class="persian-datepicker-input form-control" id="annExpireAt" placeholder="انتخاب تاریخ..." readonly>
                                <div class="persian-datepicker">
                                    <div class="datepicker-header"><button type="button" class="datepicker-nav" data-action="prev">►</button><span class="datepicker-current"></span><button type="button" class="datepicker-nav" data-action="next">◄</button></div>
                                    <div class="datepicker-weekdays"><div class="datepicker-weekday">ش</div><div class="datepicker-weekday">ی</div><div class="datepicker-weekday">د</div><div class="datepicker-weekday">س</div><div class="datepicker-weekday">چ</div><div class="datepicker-weekday">پ</div><div class="datepicker-weekday">ج</div></div>
                                    <div class="datepicker-days"></div>
                                    <button type="button" class="datepicker-today-btn">امروز</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">دامنهٔ ارسال *</label>
                        <div class="ann-scope-options" id="annScopeOptions"></div>
                    </div>
                    <div class="mb-2 mt-3" id="annPickerWrap" style="display:none;">
                        <label class="form-label">انتخاب کاربر یا واحد *</label>
                        <div id="annAssigneePicker"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="ann-btn-primary" id="annSaveBtn" onclick="saveAnnouncement()"><i class="bi bi-check-circle ms-1"></i> ذخیره</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/persian-datepicker.js') ?>"></script>
    <script src="<?= asset('../assets/js/alert.js') ?>"></script>

    <script>
        if (!authToken) { window.location.href = '../index.php'; }

        let ME = null, SECTIONS = [], USERS = [], SECTION_MAP = {}, CAN_MANAGE = false, IS_SUPER = false, CAN_EDIT = false, SCOPE = 'organization';
        let annPicker = null;   // instance کامپوننت انتخاب کاربر/واحد
        let EDIT_ORIG = {};     // گیرندهٔ اصلی هنگام ویرایش (برای حفظ در صورت عدم تغییر)
        let gridApi = null;
        let BASE_ROWS = [];                 // ردیف‌های اصلی
        const ROW_MAP = {};                 // id → آبجکت اطلاعیه (برای ویرایش)
        const EXPANDED = new Set();         // idهای بازشده

        function faNum(x) { const fa = '۰۱۲۳۴۵۶۷۸۹'; return String(x ?? '').replace(/[0-9]/g, d => fa[d]); }
        function toast(msg, type) { if (typeof showToast === 'function') { const t = showToast(msg, type || 'info'); if (t && t.close) setTimeout(() => t.close(), 2500); } else { alert(msg); } }
        function esc(s) { return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
        function pad(n) { return n < 10 ? '0' + n : '' + n; }
        function faTime(iso) {
            if (!iso) return '';
            try { const d = new Date(iso.replace(' ', 'T')); if (isNaN(d)) return faNum(iso); return faNum(d.toLocaleDateString('fa-IR') + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes())); }
            catch { return faNum(iso); }
        }
        async function api(url, opts) {
            opts = opts || {};
            opts.headers = Object.assign({ 'Authorization': 'Bearer ' + authToken }, opts.headers || {});
            const res = await fetch(url, opts);
            const txt = await res.text();
            let data; try { data = JSON.parse(txt); } catch { throw new Error('پاسخ نامعتبر سرور'); }
            return data;
        }

        // ───── رندرها ─────
        function priorityCell(p) {
            if (p.data._fullWidth) return '';
            const map = { urgent: ['فوری', '#fee2e2', '#b91c1c'], high: ['مهم', '#fef3c7', '#b45309'], normal: ['عادی', '#eef2ff', '#4f46e5'], low: ['کم', '#f1f5f9', '#64748b'] };
            const m = map[p.value] || map.normal;
            return `<span class="ann-tag" style="background:${m[1]};color:${m[2]};">${m[0]}</span>`;
        }
        function titleCell(p) {
            if (p.data._fullWidth) return '';
            const pin = p.data.is_pinned ? '<i class="bi bi-pin-angle-fill" style="color:#6d28d9;margin-left:5px;"></i>' : '';
            return `<span class="ann-grid-title">${pin}${esc(p.data.title)}</span>`;
        }
        function scopeCell(p) { if (p.data._fullWidth) return ''; return p.value ? `<span class="ann-scope-badge">${esc(p.value)}</span>` : ''; }
        function readCell(p) {
            if (p.data._fullWidth) return '';
            if (typeof p.data.read_count === 'undefined') return '—';
            return `<span class="ann-readstat">${faNum(p.data.read_count || 0)} از ${faNum(p.data.total_users || 0)}</span>`;
        }
        function actionsCell(p) {
            if (p.data._fullWidth) return '';
            return `<span class="ann-icon-btn" title="ویرایش" onclick="annEditById(${p.data.id});event.stopPropagation();"><i class="bi bi-pencil"></i></span>
                    <span class="ann-icon-btn danger" title="حذف" onclick="deleteAnnouncement(${p.data.id});event.stopPropagation();"><i class="bi bi-trash"></i></span>`;
        }
        function expanderCell(p) {
            if (p.data._fullWidth) return '';
            const open = EXPANDED.has(p.data.id);
            return `<i class="bi bi-chevron-${open ? 'up' : 'down'} ann-chev"></i>`;
        }
        // ردیف جزئیات تمام‌عرض
        function FullDetail(params) {
            const a = params.data;
            const div = document.createElement('div');
            div.className = 'ann-detail';
            div.innerHTML = `
                <div class="ann-detail-text">${esc(a.content)}</div>
                <div class="ann-detail-meta">
                    <span><i class="bi bi-person"></i> ${esc(a.author_name || '—')}</span>
                    <span><i class="bi bi-clock"></i> ${faTime(a.created_at)}</span>
                    ${a.expire_at ? '<span><i class="bi bi-hourglass-split"></i> انقضا: ' + faTime(a.expire_at) + '</span>' : ''}
                </div>`;
            return div;
        }

        function buildColumns() {
            const cols = [
                { headerName: '', field: '_exp', width: 46, sortable: false, filter: false, cellRenderer: expanderCell, cellStyle: { textAlign: 'center', cursor: 'pointer' } },
                { headerName: 'عنوان', field: 'title', flex: 2, minWidth: 200, sortable: true, cellRenderer: titleCell, cellStyle: { cursor: 'pointer' } },
                { headerName: 'اولویت', field: 'priority', width: 100, sortable: true, cellRenderer: priorityCell },
                { headerName: 'دامنه', field: 'scope_label', width: 130, sortable: true, cellRenderer: scopeCell },
                { headerName: 'نویسنده', field: 'author_name', flex: 1, minWidth: 120, sortable: true, cellRenderer: p => p.data._fullWidth ? '' : esc(p.value || '—') },
                { headerName: 'تاریخ', field: 'created_at', width: 150, sortable: true, cellRenderer: p => p.data._fullWidth ? '' : faTime(p.value) }
            ];
            if (CAN_EDIT) {
                cols.push({ headerName: 'خوانده', field: 'read_count', width: 120, sortable: true, cellRenderer: readCell });
                cols.push({ headerName: '', field: '_act', width: 96, sortable: false, filter: false, cellRenderer: actionsCell });
            }
            return cols;
        }

        function buildRowData() {
            const rows = [];
            BASE_ROWS.forEach(a => {
                rows.push(a);
                if (EXPANDED.has(a.id)) rows.push({ _fullWidth: true, _parent: a.id, content: a.content, author_name: a.author_name, created_at: a.created_at, expire_at: a.expire_at });
            });
            return rows;
        }
        function refreshGrid() { if (gridApi) gridApi.setGridOption('rowData', buildRowData()); }

        function toggleExpand(id) {
            if (EXPANDED.has(id)) EXPANDED.delete(id);
            else { EXPANDED.add(id); markRead(id); }
            refreshGrid();
        }

        function initGrid() {
            const gridOptions = {
                columnDefs: buildColumns(),
                rowData: [],
                enableRtl: true,
                headerHeight: 44,
                rowHeight: 52,
                animateRows: true,
                isFullWidthRow: p => !!(p.rowNode.data && p.rowNode.data._fullWidth),
                fullWidthCellRenderer: FullDetail,
                getRowHeight: p => {
                    if (p.data && p.data._fullWidth) {
                        const txt = p.data.content || '';
                        const lines = txt.split('\n').length + Math.ceil(txt.length / 70);
                        return Math.min(360, Math.max(96, 56 + lines * 22));
                    }
                    return 52;
                },
                onCellClicked: p => {
                    if (!p.data || p.data._fullWidth) return;
                    if (p.column && (p.column.getColId() === '_act')) return; // دکمه‌های عملیات
                    toggleExpand(p.data.id);
                },
                overlayNoRowsTemplate: '<div style="padding:2rem;color:#9097a6;"><i class="bi bi-megaphone" style="font-size:2rem;display:block;margin-bottom:.5rem;color:#cfd4e4;"></i>اطلاعیه‌ای وجود ندارد</div>'
            };
            gridApi = agGrid.createGrid(document.getElementById('annGrid'), gridOptions);
        }

        // ───── بارگذاری ─────
        async function init() {
            try {
                const p = await api('../api/auth/profile.php');
                if (p.success) { ME = p.user; IS_SUPER = (parseInt(ME.id) === 1); CAN_MANAGE = ['management', 'supervisor'].includes(ME.role); }
            } catch (e) { console.error(e); }
            CAN_EDIT = CAN_MANAGE || IS_SUPER;

            if (CAN_EDIT) {
                document.getElementById('newAnnBtn').style.display = 'inline-flex';
                try { const s = await api('../api/organization/activity-sections.php'); if (s.success) SECTIONS = s.sections || []; } catch (e) { console.error(e); }
                try { const us = await api('../api/users/list.php'); if (us.success) USERS = us.users || []; } catch (e) { console.error(e); }
                SECTION_MAP = {};
                SECTIONS.forEach(s => { SECTION_MAP[s.section_key] = s.section_label; });
                if (typeof AssigneePicker !== 'undefined') {
                    annPicker = AssigneePicker.create({
                        container: '#annAssigneePicker',
                        users: USERS,
                        sections: SECTIONS,
                        sectionMap: SECTION_MAP,
                        showSections: true,
                        placeholder: 'انتخاب کاربر یا واحد...',   // حالت فیلتر ⟵ «خودم» و «همه» مخفی
                        onSelect: () => {}
                    });
                }
            }
            initGrid();
            loadList();
        }

        async function loadList() {
            try {
                const allParam = CAN_EDIT ? '&all=1' : '';
                const data = await api('../api/announcements/list.php?limit=100&offset=0' + allParam);
                if (!data.success) { toast('خطا در بارگذاری', 'danger'); return; }
                BASE_ROWS = data.announcements || [];
                Object.keys(ROW_MAP).forEach(k => delete ROW_MAP[k]);
                BASE_ROWS.forEach(a => { ROW_MAP[a.id] = a; });
                refreshGrid();
            } catch (e) { toast('خطا: ' + e.message, 'danger'); }
        }

        // ───── مودال (ساخت/ویرایش) ─────
        function buildScopeOptions() {
            const box = document.getElementById('annScopeOptions');
            const opts = [];
            if (IS_SUPER) opts.push({ v: 'all_orgs', l: 'همهٔ سازمان‌ها (سراسری)' });
            opts.push({ v: 'organization', l: 'کل سازمان' });
            opts.push({ v: 'specific', l: 'کاربر یا واحدِ خاص' });
            box.innerHTML = opts.map((o, i) => `
                <div class="ann-scope-chip ${i === 0 ? 'active' : ''}" data-val="${o.v}" onclick="onScopeChange('${o.v}')">
                    <i class="bi ${i === 0 ? 'bi-check-circle-fill' : 'bi-circle'} chip-check"></i><span>${o.l}</span>
                </div>`).join('');
            SCOPE = opts[0].v; onScopeChange(SCOPE);
        }
        function onScopeChange(val) {
            SCOPE = val;
            document.querySelectorAll('#annScopeOptions .ann-scope-chip').forEach(el => {
                const active = el.getAttribute('data-val') === val;
                el.classList.toggle('active', active);
                const icon = el.querySelector('.chip-check');
                if (icon) icon.className = 'bi ' + (active ? 'bi-check-circle-fill' : 'bi-circle') + ' chip-check';
            });
            document.getElementById('annPickerWrap').style.display = (val === 'specific') ? 'block' : 'none';
        }
        function resetDatepicker(id) { const el = document.getElementById(id); if (el) { el.value = ''; el.removeAttribute('data-date'); } }

        function openCreate() {
            document.getElementById('annModalTitle').textContent = 'اطلاعیهٔ جدید';
            document.getElementById('annId').value = '';
            document.getElementById('annTitle').value = '';
            document.getElementById('annContent').value = '';
            document.getElementById('annPriority').value = 'normal';
            resetDatepicker('annPublishAt'); resetDatepicker('annExpireAt');
            buildScopeOptions();
            EDIT_ORIG = {};
            if (annPicker) annPicker.reset();
            new bootstrap.Modal(document.getElementById('annModal')).show();
            if (typeof window.reinitPersianDatepickers === 'function') setTimeout(window.reinitPersianDatepickers, 100);
        }
        function annEditById(id) { const a = ROW_MAP[id]; if (a) openEdit(a); }
        function openEdit(a) {
            document.getElementById('annModalTitle').textContent = 'ویرایش اطلاعیه';
            document.getElementById('annId').value = a.id;
            document.getElementById('annTitle').value = a.title || '';
            document.getElementById('annContent').value = a.content || '';
            document.getElementById('annPriority').value = a.priority || 'normal';
            resetDatepicker('annPublishAt'); resetDatepicker('annExpireAt');
            if (a.publish_at) document.getElementById('annPublishAt').setAttribute('data-date', a.publish_at.slice(0, 10));
            if (a.expire_at) document.getElementById('annExpireAt').setAttribute('data-date', a.expire_at.slice(0, 10));
            buildScopeOptions();
            EDIT_ORIG = { target_user_id: a.target_user_id || null, target_section: a.target_section || null };
            let cur = 'organization';
            if (a.organization_id === null || typeof a.organization_id === 'undefined') cur = 'all_orgs';
            else if (a.target_user_id || a.target_section) cur = 'specific';
            onScopeChange(cur);
            if (annPicker) annPicker.reset();
            new bootstrap.Modal(document.getElementById('annModal')).show();
            if (typeof window.reinitPersianDatepickers === 'function') setTimeout(window.reinitPersianDatepickers, 100);
        }

        async function saveAnnouncement() {
            const id = document.getElementById('annId').value;
            const title = document.getElementById('annTitle').value.trim();
            const content = document.getElementById('annContent').value.trim();
            if (!title || !content) { toast('عنوان و متن الزامی است', 'warning'); return; }
            const pubDate = document.getElementById('annPublishAt').getAttribute('data-date');
            const expDate = document.getElementById('annExpireAt').getAttribute('data-date');
            const body = {
                title, content,
                priority: document.getElementById('annPriority').value,
                publish_at: pubDate ? (pubDate + ' 00:00:00') : null,
                expire_at: expDate ? (expDate + ' 23:59:59') : null,
                scope: SCOPE
            };
            if (SCOPE === 'specific') {
                const sel = annPicker ? annPicker.getValue() : null;
                if (sel && sel.type === 'section' && sel.value && sel.value !== '__all__') {
                    body.scope = 'section'; body.target_section = sel.value;
                } else if (sel && sel.type === 'user' && sel.value && sel.value !== '__all_users__') {
                    body.scope = 'user'; body.target_user_id = parseInt(sel.value);
                } else if (id && (EDIT_ORIG.target_user_id || EDIT_ORIG.target_section)) {
                    // ویرایش بدون تغییر گیرنده ⟵ همان گیرندهٔ قبلی حفظ شود
                    if (EDIT_ORIG.target_user_id) { body.scope = 'user'; body.target_user_id = EDIT_ORIG.target_user_id; }
                    else { body.scope = 'section'; body.target_section = EDIT_ORIG.target_section; }
                } else {
                    toast('یک کاربر یا واحد انتخاب کنید', 'warning'); return;
                }
            }
            const btn = document.getElementById('annSaveBtn'); btn.disabled = true;
            try {
                const opt = { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '' };
                let data;
                if (id) { body.id = parseInt(id); opt.body = JSON.stringify(body); data = await api('../api/announcements/update.php', opt); }
                else { opt.body = JSON.stringify(body); data = await api('../api/announcements/create.php', opt); }
                if (data.success) { toast(id ? 'اطلاعیه ویرایش شد' : 'اطلاعیه ایجاد شد', 'success'); bootstrap.Modal.getInstance(document.getElementById('annModal')).hide(); loadList(); }
                else { toast(data.message || 'خطا در ذخیره', 'danger'); }
            } catch (e) { toast('خطا: ' + e.message, 'danger'); }
            finally { btn.disabled = false; }
        }

        async function deleteAnnouncement(id) {
            if (!confirm('این اطلاعیه حذف شود؟')) return;
            try {
                const data = await api('../api/announcements/update.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete', id }) });
                if (data.success) { EXPANDED.delete(id); toast('حذف شد', 'success'); loadList(); }
                else { toast(data.message || 'خطا در حذف', 'danger'); }
            } catch (e) { toast('خطا: ' + e.message, 'danger'); }
        }
        async function markRead(id) {
            try { await api('../api/announcements/update.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'mark_read', id }) }); } catch (e) { }
        }
        async function markAllRead() {
            try {
                const data = await api('../api/announcements/update.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'mark_all_read' }) });
                if (data.success) { toast('همه خوانده‌شده شد', 'success'); loadList(); }
            } catch (e) { toast('خطا: ' + e.message, 'danger'); }
        }

        document.addEventListener('DOMContentLoaded', init);
    </script>

</body>

</html>