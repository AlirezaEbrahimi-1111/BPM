<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// 🔒 همون دو مجوزی که api/reports/top-delayed-users.php هم می‌پذیره —
// این صفحه فقط دادهٔ همون API رو نشون می‌ده، پس گیت دسترسیش هم باید یکی باشه
if (!hasPermission($__me, 'view_all_org_tasks') && !hasPermission($__me, 'view_org_dashboard_reports')) {
    header('Location: dashboard-manager.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کاربران و واحدهای دارای تأخیر - سیستم مدیریت کار</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>

    <style>
        /* 🔒 مطابقِ استانداردِ «هاوِر» سراسریِ پروژه (rgba(142,87,254,.12/.18)) */
        .du-row-name {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .du-row-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: .9rem;
        }

        .du-row-icon.is-user {
            background: rgba(27, 123, 57, .12);
            color: #1b7b39;
        }

        .du-row-icon.is-section {
            background: rgba(142, 87, 254, .12);
            color: #8e57fe;
        }

        /* 🔒 پدینگ/فونت‌سایز/رادیوس از کلاسِ مشترکِ .badge (custom.css) میاد —
           اون کلاس با !important و «استاندارد سراسری، بدون راه گریز» تعریف
           شده، پس اینجا فقط رنگِ مخصوصِ هر حالت رو اضافه می‌کنیم، نه
           پدینگ/سایزِ جدا */
        .du-kind-badge.is-user {
            background: rgba(27, 123, 57, .12);
            color: #1b7b39;
        }

        .du-kind-badge.is-section {
            background: rgba(142, 87, 254, .12);
            color: #8e57fe;
        }

        .du-delay-badge {
            background: rgba(220, 38, 38, .1);
            color: #dc2626;
            font-weight: 700;
        }

        :root[data-theme="dark"] .du-delay-badge {
            background: rgba(220, 38, 38, .18);
        }

        /* 🔒 سایزِ متنِ فرعی — عینِ همون ۰.۷rem ای که tasks.php برایِ متنِ
           فرعیِ زیرِ عنوان (توضیحاتِ کار) استفاده می‌کنه */
        .du-task-count {
            font-size: .7rem;
            color: var(--text-muted, #6b7280);
        }

        .du-breakdown {
            font-size: .7rem;
            color: var(--text-muted, #6b7280);
        }

        /* ─── مودالِ کارهایِ تأخیردار یک کاربر/واحد ─── */
        #duTasksModal .modal-dialog {
            max-width: 720px;
        }

        .du-task-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            cursor: pointer;
            transition: background .12s ease;
        }

        .du-task-row:hover {
            background: rgba(142, 87, 254, .12);
        }

        :root[data-theme="dark"] .du-task-row:hover {
            background: rgba(142, 87, 254, .18);
        }

        .du-task-row+.du-task-row {
            margin-top: 2px;
        }

        .du-task-title {
            font-size: .87rem;
            font-weight: 600;
            color: var(--ink-900, #1f2937);
        }

        .du-task-meta {
            font-size: .75rem;
            color: var(--text-muted, #6b7280);
            margin-top: 2px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .du-task-delay {
            font-size: .78rem;
            font-weight: 700;
            color: #dc2626;
            white-space: nowrap;
        }

        /* 🔒 عینِ .dash-loading/.dash-empty در dashboard-manager.php — همون
           کلاس‌ها، چون این صفحه هم از همون قالبِ «کارتِ داشبورد» استفاده
           می‌کنه، ولی چون اون دو کلاس محلیِ همون صفحه بودن (نه custom.css)،
           اینجا دوباره تعریف شدن با رنگِ ثابتِ پروژه به‌جایِ متغیرِ محلیِ
           --pm-purple */
        .dash-empty {
            text-align: center;
            color: var(--text-muted, #6b7280);
            font-size: .8rem;
            padding: 40px 14px;
        }

        .dash-empty i {
            display: block;
            font-size: 1.6rem;
            margin-bottom: 8px;
            opacity: .5;
        }

        .dash-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center;
            color: var(--text-muted, #6b7280);
            font-size: .8rem;
            padding: 40px 14px;
        }

        .dash-loading .spinner-border {
            width: 1rem;
            height: 1rem;
            border-width: .15em;
            color: #8e57fe;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container">

        <div class="filters-wrapper two-col">
            <div class="filters-title-col">
                <h1><i class="bi bi-people"></i> کاربران و واحدهای دارای تأخیر</h1>
                <p>مشاهده‌ی همه‌ی کاربران و واحدهای سازمانی که کار تأخیردار دارند</p>
            </div>
            <div style="flex: 1;">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" placeholder="جستجو در نام کاربر یا واحد...">
                </div>
            </div>
        </div>

        <div id="duGrid" class="ag-theme-alpine" style="height: 620px; width: 100%; padding-top: 1rem;"></div>

    </div>

    <!-- مودالِ کارهایِ تأخیردار یک کاربر/واحدِ خاص -->
    <div class="modal fade" id="duTasksModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">کارهای تأخیردار <span id="duModalName"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="duModalBody">
                    <div class="dash-loading"><span class="spinner-border spinner-border-sm" role="status"></span>در حال بارگذاری…</div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('/assets/js/sections-helper.js') ?>"></script>
    <script src="<?= asset('/assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script>
        'use strict';

        let duList = [];
        let duGridApi = null;
        let duModalInstance = null;

        // 🔒 عینِ همون فرمولِ combinedHours در pages/dashboard-manager.php
        // (renderTopDelayed) — روزِ کاری (مقطعی/دوره‌ای) به ساعت تبدیل و با
        // ساعتِ روتین جمع می‌شه، تا یک عددِ واحدِ قابل‌مقایسه داشته باشیم.
        // 🔒 چون همون فرمول دو جا نوشته می‌شه، دقیقا طبقِ اصلِ «یک مفهوم، یک
        // تعریف» عمدا اینجا هم کامنت‌گذاری شده تا اگه یکی عوض شد، اون یکی
        // یادش نره.
        function duCombinedHours(u) {
            return (u.delay_days || 0) * 24 + (u.delay_hours || 0);
        }

        function duSectionLabel(key) {
            return (typeof getSectionLabel === 'function') ? getSectionLabel(key) : key;
        }

        const duColumnDefs = [
            {
                headerName: 'نوع',
                field: 'kind',
                width: 100,
                sortable: true,
                cellRenderer: p => p.value === 'section'
                    ? '<span class="badge du-kind-badge is-section">واحد</span>'
                    : '<span class="badge du-kind-badge is-user">کاربر</span>'
            },
            {
                headerName: 'نام',
                field: 'name',
                flex: 2,
                sortable: true,
                cellRenderer: p => {
                    const isSection = p.data.kind === 'section';
                    const displayName = isSection ? duSectionLabel(p.data.name) : p.data.name;
                    const icon = isSection ? 'bi-building' : 'bi-person-circle';
                    const iconCls = isSection ? 'is-section' : 'is-user';
                    return `<div class="du-row-name">
                        <div class="du-row-icon ${iconCls}"><i class="bi ${icon}"></i></div>
                        <span>${esc(displayName)}</span>
                    </div>`;
                }
            },
            {
                headerName: 'تفکیک',
                field: 'breakdown',
                flex: 2,
                sortable: false,
                cellRenderer: p => {
                    const u = p.data;
                    const parts = [];
                    if (u.periodic > 0) parts.push(`${toFa(u.periodic)} مقطعی`);
                    if (u.continuous > 0) parts.push(`${toFa(u.continuous)} دوره‌ای`);
                    if (u.workflow > 0) parts.push(`${toFa(u.workflow)} روتین`);
                    return `<span class="du-breakdown">${parts.join(' • ') || '—'}</span>`;
                }
            },
            {
                headerName: 'تعداد کل کار',
                field: 'total_tasks',
                width: 130,
                sortable: true,
                comparator: (a, b, nodeA, nodeB) => {
                    const ta = (nodeA.data.periodic || 0) + (nodeA.data.continuous || 0) + (nodeA.data.workflow || 0);
                    const tb = (nodeB.data.periodic || 0) + (nodeB.data.continuous || 0) + (nodeB.data.workflow || 0);
                    return ta - tb;
                },
                cellRenderer: p => {
                    const total = (p.data.periodic || 0) + (p.data.continuous || 0) + (p.data.workflow || 0);
                    return `<span class="du-task-count">${toFa(total)} کار</span>`;
                }
            },
            {
                headerName: 'مجموع تأخیر',
                field: 'delay',
                width: 190,
                minWidth: 170,
                sortable: true,
                sort: 'desc',
                comparator: (a, b, nodeA, nodeB) => duCombinedHours(nodeA.data) - duCombinedHours(nodeB.data),
                cellRenderer: p => `<span class="badge du-delay-badge">${esc(formatHourDelay(duCombinedHours(p.data)))}</span>`
            }
        ];

        const duGridOptions = {
            columnDefs: duColumnDefs,
            rowData: [],
            defaultColDef: { resizable: true },
            enableRtl: true, // 🔒 همون تنظیمی که tasks.php/users.php هم دارن — بدونش هم ترتیبِ
            // پرشدنِ ستون‌ها از چپ می‌شه (باید از راست باشه)، هم ترتیبِ عدد/کلمه‌ی
            // فارسیِ ترکیبی (مثلا «روز ۱۸۵» به‌جایِ «۱۸۵ روز») به‌هم می‌ریزه
            domLayout: 'normal',
            rowHeight: 54,
            onRowClicked: e => duOpenModal(e.data),
            overlayNoRowsTemplate: '<div class="dash-empty"><i class="bi bi-check2-circle"></i>کاربر یا واحدی با تأخیر یافت نشد</div>',
            overlayLoadingTemplate: '<div style="display:flex;flex-direction:column;align-items:center;gap:10px;color:#8e57fe;font-size:.85rem;"><div class="spinner-border" style="width:2.2rem;height:2.2rem;" role="status"></div><span>در حال بارگذاری...</span></div>'
        };

        async function duLoad() {
            duGridApi.showLoadingOverlay();
            try {
                if (typeof loadSectionMap === 'function') await loadSectionMap();

                const res = await fetch('/api/reports/top-delayed-users.php', {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });
                const data = await res.json();
                if (!data.success) {
                    showToast(data.message || 'خطا در دریافت اطلاعات', 'error');
                    duGridApi.showNoRowsOverlay();
                    return;
                }
                duList = data.users || [];
                duGridApi.setGridOption('rowData', duList);
                if (!duList.length) duGridApi.showNoRowsOverlay();
                else duGridApi.hideOverlay();
            } catch (e) {
                console.error('duLoad:', e);
                showToast('خطا در ارتباط با سرور', 'error');
                duGridApi.showNoRowsOverlay();
            }
        }

        function duApplySearch() {
            const q = (document.getElementById('searchInput').value || '').trim();
            if (!q) {
                duGridApi.setGridOption('rowData', duList);
                return;
            }
            const qLower = q.toLowerCase();
            const filtered = duList.filter(u => {
                const displayName = u.kind === 'section' ? duSectionLabel(u.name) : u.name;
                return String(displayName).toLowerCase().includes(qLower);
            });
            duGridApi.setGridOption('rowData', filtered);
        }

        /* ─── مودالِ کارهایِ یک کاربر/واحدِ خاص ─── */
        function duTaskTypeLabel(t) {
            if (t === 'periodic') return 'مقطعی';
            if (t === 'continuous') return 'دوره‌ای';
            return 'روتین';
        }

        function duFormatDelay(t) {
            const hours = t.task_type === 'workflow' ? (t.delay_hours || 0) : (t.delay_days || 0) * 24;
            return formatHourDelay(hours);
        }

        async function duOpenModal(row) {
            const isSection = row.kind === 'section';
            const displayName = isSection ? duSectionLabel(row.name) : row.name;
            document.getElementById('duModalName').textContent = displayName;

            const body = document.getElementById('duModalBody');
            body.innerHTML = '<div class="dash-loading"><span class="spinner-border spinner-border-sm" role="status"></span>در حال بارگذاری…</div>';

            if (!duModalInstance) duModalInstance = new bootstrap.Modal(document.getElementById('duTasksModal'));
            duModalInstance.show();

            try {
                const res = await fetch(`/api/reports/delayed-tasks-for.php?kind=${encodeURIComponent(row.kind)}&ref_id=${encodeURIComponent(row.ref_id)}`, {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });
                const data = await res.json();
                if (!data.success) {
                    body.innerHTML = `<div class="text-danger text-center py-3">${esc(data.message || 'خطا در دریافت کارها')}</div>`;
                    return;
                }
                const tasks = data.tasks || [];
                if (!tasks.length) {
                    body.innerHTML = '<div class="dash-empty"><i class="bi bi-check2-circle"></i>کار تأخیرداری یافت نشد</div>';
                    return;
                }
                body.innerHTML = tasks.map(t => `
                    <div class="du-task-row" onclick="window.location.href='task-detail.php?id=${t.id}'">
                        <div>
                            <div class="du-task-title">${esc(t.title || '—')}</div>
                            <div class="du-task-meta">
                                <span>${duTaskTypeLabel(t.task_type)}</span>
                                <span>#${toFa(t.id)}</span>
                            </div>
                        </div>
                        <div class="du-task-delay">${esc(duFormatDelay(t))}</div>
                    </div>
                `).join('');
            } catch (e) {
                console.error('duOpenModal:', e);
                body.innerHTML = '<div class="text-danger text-center py-3">خطا در ارتباط با سرور</div>';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            duGridApi = agGrid.createGrid(document.getElementById('duGrid'), duGridOptions);
            duLoad();

            let searchTimeout;
            document.getElementById('searchInput').addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(duApplySearch, 200);
            });
        });
    </script>
</body>

</html>
