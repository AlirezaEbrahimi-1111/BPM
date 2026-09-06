<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    header('Location: ../index.php');
    exit;
}

$__me = loadUserForPermissions($db, (int) $user_id);
if (!$__me || !hasPermission($__me, 'manage_task_groups')) {
    header('Location: dashboard-manager.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>مدیریت گروه‌ها - سیستم مدیریت کار</title>

    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/js/cdn/bootstrap-icons.css">
    <script src="../assets/js/config.js"></script>
    <link rel="stylesheet" href="../assets/css/custom.css">
    <link rel="stylesheet" href="../assets/css/responsive/dashboard-responsive.css">
    <!-- AG Grid (همان مسیر tasks.php) -->
    <script src="../../assets/js/ag-grid-community.min.js"></script>

    <style>
        .gm-badge {
            display: inline-grid-lanes;padding: 4px; align-items: center; gap: 4px;
             border-radius: 14px; font-size: .85rem; padding-inline: 10px;
        }
        .gm-colors, .gm-icons { display: flex; flex-wrap: wrap; gap: 6px; }
        .gm-color { width: 26px; height: 26px; border-radius: 50%; cursor: pointer; border: 2px solid transparent; }
        .gm-color.active { border-color: #000; }
        .gm-icon { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border: 1px solid #ddd; border-radius: 9px; cursor: pointer; }
        .gm-icon.active { background: rgba(142, 87, 254, 0.12); border-color: #8e57fe; }
        :root[data-theme="dark"] .gm-icon { border-color: var(--border-soft); }
        :root[data-theme="dark"] .gm-icon.active { background: rgba(99, 102, 241, .2); border-color: #6366f1; }
        .gm-act-btn { background: none; border: none; cursor: pointer; padding: 2px 6px; font-size: 1rem; }
        .gm-act-edit { color: #2563eb; }
        .gm-act-del { color: #dc2626; }
        .gm-act-view { color: #9ca3af; }
        /* چیدمان افقی فیلدهای مودال: لیبل سمت راست، کنترل جلوش */
        .gm-field-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }
        .gm-field-label {
            flex: 0 0 90px;        /* عرض ثابت لیبل، تا همه فیلدها تراز شوند */
            text-align: right;
            font-weight: 500;
            margin: 0;
        }
        .gm-field-control {
            flex: 1;               /* بقیه‌ی فضا برای فیلد */
        }
        /* در موبایل، دوباره عمودی شود تا فشرده نشود */
        @media (max-width: 480px) {
            .gm-field-row { flex-direction: column; align-items: stretch; gap: 6px; }
            .gm-field-label { flex: none; }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container">
        <div class="filters-wrapper">
            <h1><i class="bi bi-tags ms-2"></i>مدیریت گروه‌ها</h1>
            <p class="mb-0">مدیریت گروه‌های کار در سازمان شما</p>
        </div>

        <div class="filters-wrapper">
            <div id="accessDenied" class="alert alert-danger" style="display:none;">
                <i class="bi bi-shield-lock me-2"></i>این صفحه فقط برای مدیر سازمان در دسترس است.
            </div>

            <div id="gmContent" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">گروه‌های سازمان</h5>
                    <button class="btn btn-primary btn-sm" onclick="openCreate()">
                        <i class="bi bi-plus-circle me-1"></i>گروه جدید
                    </button>
                </div>

                <!-- جدول ag-grid -->
                <div id="groupsGrid" class="ag-theme-alpine" style="height: 560px; width: 100%;"></div>
            </div>
        </div>
    </div>

    <!-- Modal ساخت/ویرایش -->
    <div class="modal fade" id="gmEditModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="gmModalTitle">گروه جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="gmEditId">

                    <!-- نام گروه -->
                    <div class="row align-items-center mb-3">
                        <label class="col-4 col-form-label">نام گروه</label>
                        <div class="col-8">
                            <input type="text" class="form-control" id="gmName" placeholder="مثلاً: کاری">
                        </div>
                    </div>

                    <!-- رنگ -->
                    <div class="row align-items-center mb-3">
                        <label class="col-4 col-form-label">رنگ</label>
                        <div class="col-8">
                            <div id="gmColors" class="gm-colors"></div>
                        </div>
                    </div>

                    <!-- آیکون -->
                    <div class="row align-items-center mb-3">
                        <label class="col-4 col-form-label">آیکون</label>
                        <div class="col-8">
                            <div id="gmIcons" class="gm-icons"></div>
                        </div>
                    </div>

                    <!-- گروه سازمانی -->
                    <div class="row align-items-center mb-3">
                        <label class="col-4 col-form-label">نوع گروه</label>
                        <div class="col-8">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="gmScopeOrg">
                                <label class="form-check-label" for="gmScopeOrg">گروه سازمانی (برای همه اعضا)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" id="gmSaveBtn" onclick="saveGroup()">ذخیره</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    <script src="../assets/js/cdn/bootstrap.bundle.min.js"></script>
    <script>
        const GROUP_COLORS = ['#6366f1','#ef4444','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#64748b','#0ea5e9'];
        const GROUP_ICONS  = ['bi-tag','bi-briefcase','bi-house','bi-heart','bi-star','bi-flag','bi-bullseye','bi-people','bi-cart','bi-tools','bi-book','bi-lightning'];

        let currentUserId = null;
        let gridApi = null;
        let _selColor = GROUP_COLORS[0];
        let _selIcon = GROUP_ICONS[0];
        let _editId = null;

        function toFa(n){ return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }
        function scopeLabel(s){ return s === 'org' ? 'سازمانی' : 'شخصی'; }

        // ── تعریف ستون‌های ag-grid ──
        const columnDefs = [
            {
                headerName: 'گروه', field: 'name', flex: 1, minWidth: 140, sortable: true, resizable: true,
                cellRenderer: p => `<span class="gm-badge" style="background:${esc(p.data.color)}20;color:${esc(p.data.color)}"><i class="${esc(p.data.icon)}"></i>${esc(p.value)}</span>`
            },
            {
                headerName: 'نوع', field: 'scope', width: 100, sortable: true,
                cellRenderer: p => p.value === 'org'
                    ? '<span style="color:#1b7b39">سازمانی</span>'
                    : '<span style="color:#6b7280">شخصی</span>'
            },
            { headerName: 'سازنده', field: 'creator_name', flex: 1, minWidth: 120, sortable: true },
            {
                headerName: 'تعداد کار', field: 'tasks_count', width: 110, sortable: true,
                valueFormatter: p => toFa(p.value || 0)
            },
            {
                headerName: 'عملیات', width: 110, sortable: false, resizable: false,
                cellRenderer: p => {
                    if (p.data.can_edit) {
                        return `<button class="gm-act-btn gm-act-edit" data-action="edit" data-id="${p.data.id}" title="ویرایش"><i class="bi bi-pencil"></i></button>
                                <button class="gm-act-btn gm-act-del" data-action="del" data-id="${p.data.id}" title="حذف"><i class="bi bi-trash"></i></button>`;
                    }
                    return '<span class="gm-act-view" title="فقط مشاهده"><i class="bi bi-eye"></i></span>';
                }
            }
        ];

        const gridOptions = {
            theme: AgGridFa.theme({ rowHoverColor: '#f0f7ff' }), // پایهٔ مشترک؛ هاورِ فعلیِ همین صفحه حفظ شد
            columnDefs: columnDefs,
            rowData: [],
            enableRtl: true,
            animateRows: true,
            pagination: true,
            paginationPageSize: 15,
            paginationPageSizeSelector: [15, 30, 50],
            defaultColDef: { sortable: true, resizable: true },
            overlayLoadingTemplate: '<div style="display:flex;flex-direction:column;align-items:center;gap:10px;color:#8e57fe;font-size:.85rem;"><div class="spinner-border" style="width:2.2rem;height:2.2rem;" role="status"></div><span>در حال بارگذاری...</span></div>',
            // کلیک روی دکمه‌های عملیات
            onCellClicked: params => {
                const btn = params.event.target.closest('[data-action]');
                if (!btn) return;
                const id = parseInt(btn.dataset.id);
                if (btn.dataset.action === 'edit') {
                    const row = params.api.getRowNode(String(params.node.rowIndex));
                    startEdit(params.data);
                } else if (btn.dataset.action === 'del') {
                    deleteGroup(id);
                }
            },
            onPaginationChanged: () => {
                setTimeout(() => {
                    document.querySelectorAll('.ag-paging-panel span, .ag-paging-panel button').forEach(el => {
                        if (el.childElementCount === 0 && !el.classList.contains('injected-az')) {
                            el.textContent = el.textContent
                                .replace(/Page/g, 'صفحه').replace(/\bof\b/g, 'از')
                                .replace(/\bto\b/g, 'تا')
                                .replace(/\d+/g, n => n.replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]));
                        }
                    });
                }, 100);
            },
        };

        document.addEventListener('DOMContentLoaded', () => {
            if (!authToken) { window.location.href = '../index.php'; return; }
            const userInfo = JSON.parse(localStorage.getItem('user_info') || '{}');
            currentUserId = userInfo.id;

            const isManager = (userInfo.activity_section === 'management' && userInfo.role === 'supervisor');
            if (!isManager) {
                document.getElementById('accessDenied').style.display = 'block';
                return;
            }

            document.getElementById('gmContent').style.display = 'block';
            buildColorIconPickers();
            gridApi = agGrid.createGrid(document.getElementById('groupsGrid'), gridOptions);
            gridApi.showLoadingOverlay();
            loadAll();
        });

        function buildColorIconPickers() {
            document.getElementById('gmColors').innerHTML = GROUP_COLORS.map(c =>
                `<span class="gm-color" data-color="${c}" style="background:${c}" onclick="pickColor('${c}', this)"></span>`).join('');
            document.getElementById('gmIcons').innerHTML = GROUP_ICONS.map(ic =>
                `<span class="gm-icon" data-icon="${ic}" onclick="pickIcon('${ic}', this)"><i class="${ic}"></i></span>`).join('');
        }
        function pickColor(c, el){ _selColor=c; document.querySelectorAll('#gmColors .gm-color').forEach(x=>x.classList.remove('active')); el.classList.add('active'); }
        function pickIcon(ic, el){ _selIcon=ic; document.querySelectorAll('#gmIcons .gm-icon').forEach(x=>x.classList.remove('active')); el.classList.add('active'); }

        async function loadAll() {
            try {
                const res = await fetch('../api/task-groups/list-all.php', {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });
                const data = await res.json();
                if (!data.success) {
                    showToast(data.message || 'خطا در بارگذاری', 'warning');
                    gridApi.setGridOption('rowData', []);
                    return;
                }
                gridApi.setGridOption('rowData', data.groups || []);
            } catch (e) {
                showToast('خطا در ارتباط با سرور', 'warning');
            }
        }

        function openCreate() {
            _editId = null;
            document.getElementById('gmModalTitle').textContent = 'گروه جدید';
            document.getElementById('gmName').value = '';
            document.getElementById('gmScopeOrg').checked = false;
            document.getElementById('gmScopeOrg').disabled = false;
            _selColor = GROUP_COLORS[0]; _selIcon = GROUP_ICONS[0];
            pickColor(_selColor, document.querySelector(`#gmColors [data-color="${_selColor}"]`));
            pickIcon(_selIcon, document.querySelector(`#gmIcons [data-icon="${_selIcon}"]`));
            new bootstrap.Modal(document.getElementById('gmEditModal')).show();
        }

        function startEdit(g) {
            _editId = g.id;
            document.getElementById('gmModalTitle').textContent = 'ویرایش گروه';
            document.getElementById('gmName').value = g.name;
            document.getElementById('gmScopeOrg').checked = (g.scope === 'org');
            document.getElementById('gmScopeOrg').disabled = true; // scope هنگام ویرایش تغییر نمی‌کند
            _selColor = g.color; _selIcon = g.icon;
            pickColor(_selColor, document.querySelector(`#gmColors [data-color="${_selColor}"]`));
            pickIcon(_selIcon, document.querySelector(`#gmIcons [data-icon="${_selIcon}"]`));
            new bootstrap.Modal(document.getElementById('gmEditModal')).show();
        }

        async function saveGroup() {
            const name = document.getElementById('gmName').value.trim();
            if (!name) { showToast('نام گروه را وارد کنید', 'warning'); return; }

            const isEdit = !!_editId;
            const url = isEdit ? '../api/task-groups/update.php' : '../api/task-groups/create.php';
            const scope = document.getElementById('gmScopeOrg').checked ? 'org' : 'personal';
            const body = isEdit
                ? { id: _editId, name, color: _selColor, icon: _selIcon }
                : { name, color: _selColor, icon: _selIcon, scope };

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                    body: JSON.stringify(body)
                });
                const data = await res.json();
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('gmEditModal')).hide();
                    showToast(isEdit ? 'گروه به‌روزرسانی شد' : 'گروه ساخته شد', 'success');
                    loadAll();
                } else {
                    showToast(data.message || 'خطا در ذخیره', 'warning');
                }
            } catch (e) { showToast('خطا در ارتباط با سرور', 'warning'); }
        }

        async function deleteGroup(id) {
            showToast('این گروه حذف شود؟ کارهای داخل آن بدون گروه می‌شوند.', 'warning', {
                duration: 150000,
                buttons: [
                    { label: 'بله، حذف شود', style: 'primary', onClick: async () => {
                        try {
                            const res = await fetch('../api/task-groups/delete.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                                body: JSON.stringify({ id })
                            });
                            const data = await res.json();
                            if (data.success) { showToast('گروه حذف شد', 'success'); loadAll(); }
                            else { showToast(data.message || 'خطا در حذف', 'warning'); }
                        } catch (e) { showToast('خطا در ارتباط با سرور', 'warning'); }
                    }},
                    { label: 'انصراف', style: 'ghost' }
                ]
            });
        }
    </script>
</body>
</html>