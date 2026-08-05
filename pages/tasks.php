<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../config/config.php';
require_once '../includes/version.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کارها - سیستم مدیریت کار</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>" rel="stylesheet">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/persian-date.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>

</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container">

        <div class="filters-wrapper two-col">
            <div class="filters-title-col">
                <h1><i class="bi bi-graph-up"></i> مدیریت کارها</h1>
                <p> مشاهده
                    و مدیریت کارها</p>
            </div>




            <div style="flex: 1;">

                <!-- Stats -->
                <div class="stats-row" style="display:none;">
                    <div class="stat-card active" data-filter="today" onclick="setStatFilter('today')">
                        <div class="stat-icon today"><i class="bi bi-calendar-day"></i></div>
                        <div>
                            <div class="stat-number" id="statToday">0</div>
                            <div class="stat-label">امروز</div>
                        </div>
                    </div>
                    <div class="stat-card" data-filter="overdue" onclick="setStatFilter('overdue')">
                        <div class="stat-icon overdue"><i class="bi bi-exclamation-triangle"></i></div>
                        <div>
                            <div class="stat-number" id="statOverdue">0</div>
                            <div class="stat-label">معوقه</div>
                        </div>
                    </div>
                    <div class="stat-card" data-filter="in_progress" onclick="setStatFilter('in_progress')">
                        <div class="stat-icon progress"><i class="bi bi-play-circle"></i></div>
                        <div>
                            <div class="stat-number" id="statProgress">0</div>
                            <div class="stat-label">در حال انجام</div>
                        </div>
                    </div>
                    <div class="stat-card" data-filter="completed" onclick="setStatFilter('completed')">
                        <div class="stat-icon done"><i class="bi bi-check-circle"></i></div>
                        <div>
                            <div class="stat-number" id="statCompleted">0</div>
                            <div class="stat-label">تکمیل شده</div>
                        </div>
                    </div>
                    <div class="stat-card" data-filter="not_started" onclick="setStatFilter('not_started')">
                        <div class="stat-icon not-started"><i class="bi bi-circle"></i></div>
                        <div>
                            <div class="stat-number" id="statNotStarted">0</div>
                            <div class="stat-label">شروع نشده</div>
                        </div>
                    </div>
                    <div class="stat-card" data-filter="delegated" onclick="setStatFilter('delegated')">
                        <div class="stat-icon delegated"><i class="bi bi-arrow-left-right"></i></div>
                        <div>
                            <div class="stat-number" id="statDelegated">0</div>
                            <div class="stat-label">ارجاع شده</div>
                        </div>
                    </div>
                </div>

                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" placeholder="جستجو در عنوان یا توضیحات...">
                </div>
                <div class="filters-row">
                    <div class="filter-item">
                        <div id="filterAssigneePicker"></div>
                    </div>
                    <div class="filter-item">
                        <select id="filterStatus">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="open">کارهای باز</option>
                            <option value="not_started">شروع نشده</option>
                            <option value="in_progress">در حال انجام</option>
                            <option value="completed">تکمیل شده</option>
                            <option value="pending_approval">منتظر تأیید</option>
                            <option value="approved">تأیید شده</option>
                            <option value="delegated">ارجاع شده</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <select id="filterPriority">
                            <option value="">همه اولویت‌ها</option>
                            <option value="high">بالا</option>
                            <option value="medium">متوسط</option>
                            <option value="low">پایین</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <select id="filterType">
                            <option value="">همه انواع</option>
                            <option value="periodic">مقطعی</option>
                            <option value="continuous">دوره‌ای</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <select id="filterGroup">
                            <option value="">همه گروه‌ها</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div id="myGrid" class="ag-theme-alpine" style="height: 620px; width: 100%; padding-top: 1rem;"></div>

    </div>

    <div class="quick-actions">
        <!-- window.location.href='create-task.php' -->
        <button class="fab" onclick="showNewTaskModal()" title="کار جدید">
            <i class="bi bi-plus"></i>
        </button>

    </div>
    <?php include 'footer.php'; ?>

    <script src="<?= asset('/assets/js/task-filters.js') ?>"></script>
    <script src="<?= asset('/assets/js/table-utils.js') ?>"></script>
    <script src="<?= asset('/assets/js/assignee-picker.js') ?>"></script>
    <script src="<?= asset('/assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script>
        let allTasks = [],
            filteredTasks = [],
            searchTimeout;
        let sortColumn = 'created_at',
            sortDirection = 'desc';
        let perPage = 15;
        let gridApi = null;
        let acticity_section = {};
        let filterAssigneeId = '';

        const columnDefs = [{
                field: 'id',
                headerName: 'شناسه',
                width: 75,
                sortable: true,
                resizable: true,
                cellRenderer: p => p.value ? String(p.value).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹' [d]) : '-'
            },
            {
                field: 'title',
                headerName: 'عنوان',
                flex: 2,
                sortable: true,
                resizable: true,
                cellRenderer: p => {
                    const desc = p.data.description ? `<div style="font-size:0.7rem;color:#94a3b8;line-height:1.4;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px;">${p.data.description}</div>` : '';
                    return `<div>${p.value || '-'}${checklistMatchBadge(p.data)}${desc}</div>`;
                }
            },
            {
                field: 'group_name',
                colId: 'col_group',
                headerName: 'گروه',
                width: 120,
                resizable: true,
                cellRenderer: p => p.value ?
                    `<span class="badge" style="background:${p.data.group_color || '#6366f1'}20;color:${p.data.group_color || '#6366f1'};border:1px solid ${p.data.group_color || '#6366f1'}40;"><i class="${p.data.group_icon || 'bi-tag'} me-1"></i>${p.value}</span>` : '<span class="text-muted">—</span>'
            },
            {
                field: 'creator_name',
                headerName: 'تعریف‌کننده',
                flex: 1,
                sortable: true,
                resizable: true
            },
            {
                field: 'assignee_name',
                headerName: 'مسئول انجام',
                flex: 1,
                sortable: true,
                resizable: true
            },
            {
                field: 'status',
                headerName: 'وضعیت',
                width: 130,
                resizable: true,
                cellRenderer: p => statusBadge(p.value)
            },
            {
                field: 'task_type',
                headerName: 'نوع',
                width: 95,
                resizable: true,
                cellRenderer: p => typeBadge(p.value)
            },
            {
                field: 'deadline',
                colId: 'col_moed',
                headerName: 'موعد',
                width: 105,
                resizable: true,
                comparator: (a, b, nodeA, nodeB) => {
                    const da = TF.effectiveDue(nodeA.data) || '9999';
                    const db = TF.effectiveDue(nodeB.data) || '9999';
                    return da < db ? -1 : da > db ? 1 : 0;
                },
                cellRenderer: p => {
                    const d = TF.effectiveDue(p.data);
                    return `<span class="date-display">${fmtDate(d)}</span>`;
                }
            },
            {
                headerName: 'مهلت',
                colId: 'col_mohlat',
                width: 120,
                resizable: true,
                field: 'deadline',
                sortable: false,
                comparator: (a, b, nodeA, nodeB) => {
                    const da = TF.effectiveDue(nodeA.data) || '9999';
                    const db = TF.effectiveDue(nodeB.data) || '9999';
                    return da < db ? -1 : da > db ? 1 : 0;
                },
                cellRenderer: p => {
                    const d = TF.effectiveDue(p.data);
                    return daysLeft(d, p.data.status);
                }
            },
            {
                field: 'created_at',
                headerName: 'ایجاد',
                width: 120,
                resizable: true,
                cellRenderer: p => `<span class="date-display">${fmtDate(p.value)}</span><br><span class="date-relative">${relTime(p.value)}</span>`
            },
            {
                headerName: 'عملیات',
                colId: 'col_actions',
                width: 90,
                sortable: false,
                resizable: false,
                cellRenderer: p => buildActionButtons(p.data)
            },
        ];

        const gridOptions = {
            theme: agGrid.themeQuartz.withParams({
                fontFamily: 'Tahoma, Vazirmatn, sans-serif',
                fontSize: 13,
                rowHoverColor: '#f0f7ff',
                headerBackgroundColor: '#f8f9fa',
            }),
            columnDefs: columnDefs,
            rowData: [],
            enableRtl: true,
            animateRows: true,
            pagination: true,
            paginationPageSize: 15,
            paginationPageSizeSelector: [15, 30, 50, 100],
            defaultColDef: {
                sortable: true,
                resizable: true
            },
            overlayLoadingTemplate: '<div style="display:flex;flex-direction:column;align-items:center;gap:10px;color:#744ca4;font-size:.85rem;"><div class="spinner-border" style="width:2.2rem;height:2.2rem;" role="status"></div><span>در حال بارگذاری...</span></div>',
            onGridReady: params => {
                const saved = localStorage.getItem('allTasksGridState');
                if (saved) params.api.applyColumnState({
                    state: JSON.parse(saved),
                    applyOrder: true
                });
                applyResponsiveColumns(); // 🆕 تنظیم ستون‌ها بر اساس اندازه صفحه
            },
            onSortChanged: params => localStorage.setItem('allTasksGridState', JSON.stringify(params.api.getColumnState())),
            onColumnResized: params => localStorage.setItem('allTasksGridState', JSON.stringify(params.api.getColumnState())),
            onRowClicked: params => viewTask(params.data.id),
            onPaginationChanged: () => {
                setTimeout(() => {
                    // فارسی کردن اعداد و متن‌ها
                    document.querySelectorAll('.ag-paging-panel span, .ag-paging-panel button').forEach(el => {
                        if (el.childElementCount === 0 && !el.classList.contains('injected-az')) {
                            el.textContent = el.textContent
                                .replace(/Page/g, 'صفحه')
                                .replace(/\bof\b/g, 'از')
                                .replace(/\bto\b/g, 'تا')
                                .replace(/\d+/g, n => n.replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹' [d]));
                        }
                    });

                    // حذف span های تنها «از» که بیرون از summary پنل هستند
                    document.querySelectorAll('.ag-paging-panel > span, .ag-paging-panel > div:not(.ag-paging-row-summary-panel):not(.ag-paging-page-size):not(.ag-paging-button-wrapper):not(.ag-paging-page-summary-panel)').forEach(el => {
                        if (el.textContent.trim() === 'از') el.remove();
                    });

                    // اضافه کردن «از» به ابتدای summary
                    const summary = document.querySelector('.ag-paging-row-summary-panel');
                    if (summary) {
                        summary.querySelectorAll('.injected-az').forEach(el => el.remove());
                        const azSpan = document.createElement('span');
                        azSpan.textContent = 'از ';
                        azSpan.className = 'injected-az';
                        summary.insertBefore(azSpan, summary.firstChild);
                    }
                }, 100); // ← از 0 به 100 تغییر کرد تا AG Grid اول رندر کنه
            },
        };
        gridApi = agGrid.createGrid(document.getElementById('myGrid'), gridOptions);
        gridApi.showLoadingOverlay();
        // 🆕 با تغییر اندازه پنجره، ستون‌ها دوباره تنظیم شوند
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(applyResponsiveColumns, 200);
        });
        let statFilter = '';
        let currentUser = null;

        function showNewTaskModal() {
            window.location.href = 'create-task.php';
        }

        function showDailyReportModal() {
            window.location.href = 'daily-report.php';
        }

        function matchesAllWords(text, query) {
            if (!query) return true;
            const words = normalizeDigits(query).trim().toLowerCase().split(/\s+/);
            const haystack = normalizeDigits(text).toLowerCase();
            return words.every(w => haystack.includes(w));
        }

        function isChecklistOnlyMatch(otherText, checklistText, searchTerm) {
            if (!searchTerm) return false;
            if (matchesAllWords(otherText, searchTerm)) return false; // خودش مچ شده، نیازی به چک‌لیست نبوده
            return matchesAllWords(checklistText, searchTerm);
        }

        function checklistMatchBadge(task) {
            if (!task._checklistOnlyMatch) return '';
            return '<span style="display:inline-flex;align-items:center;gap:3px;background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;border-radius:8px;padding:1px 6px;font-size:0.65rem;margin-inline-start:6px;vertical-align:middle;" title="این کار به‌خاطر چک‌لیستش پیدا شد"><i class="bi bi-check2-square"></i> چک‌لیست</span>';
        }

        function normalizeDigits(str) {
            return str
                .replace(/[۰-۹]/g, d => d.charCodeAt(0) - 1776)
                .replace(/[٠-٩]/g, d => d.charCodeAt(0) - 1632);
        }
        document.addEventListener('DOMContentLoaded', function() {
            authToken = localStorage.getItem('auth_token');
            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }

            currentUser = JSON.parse(localStorage.getItem('user_info') || '{}');

            document.getElementById('filterStatus').value = 'open';

            loadTasks();
            loadGroupOptions();
            loadSections().then(() => loadUsers());

            ['filterStatus', 'filterPriority', 'filterType', 'filterGroup'].forEach(id => {
                document.getElementById(id).addEventListener('change', () => {
                    statFilter = '';
                    clearStatActive();
                    currentPage = 1;
                    onFilterChange();
                });
            });

            document.querySelectorAll('.per-page-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.per-page-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    perPage = parseInt(this.dataset.value);
                    currentPage = 1;
                    onFilterChange();
                });
            });

            document.getElementById('searchInput').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    onFilterChange();
                }, 300);
            });

        });

        async function loadTasks() {
            try {
                const res = await fetch('../api/tasks/all-tasks.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();
                if (data.success) {
                    allTasks = data.tasks || [];
                    populateGroupFilter();
                    updateStats();
                    applyFilters();
                } else showError(data.message || 'خطا');
            } catch (e) {
                console.error(e);
                showError('خطا در ارتباط');
            }
        }
        // گروه‌های کاربر (حتی بدون تسک) از API
        let allGroups = [];
        async function loadGroupOptions() {
            try {
                const res = await fetch('../api/task-groups/list.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();
                if (data.success) allGroups = data.groups || [];
            } catch (e) {
                console.error('loadGroupOptions:', e);
            }
            populateGroupFilter();
        }

        // 🆕 پر کردن فیلتر گروه: همهٔ گروه‌های کاربر + گروه‌های موجود در تسک‌ها
        function populateGroupFilter() {
            const sel = document.getElementById('filterGroup');
            if (!sel) return;
            const current = sel.value;
            const seen = new Map();
            // ۱) همهٔ گروه‌های کاربر (حتی بدون تسک)
            allGroups.forEach(g => {
                if (g.id && g.name) seen.set(String(g.id), g.name);
            });
            // ۲) گروه‌هایی که فقط در تسک‌ها هستند (محکم‌کاری)
            allTasks.forEach(t => {
                if (t.group_id && t.group_name && !seen.has(String(t.group_id))) {
                    seen.set(String(t.group_id), t.group_name);
                }
            });
            let html = '<option value="">همه گروه‌ها</option>';
            html += '<option value="__none__">بدون گروه</option>';
            seen.forEach((name, id) => {
                html += `<option value="${id}">${name}</option>`;
            });
            sel.innerHTML = html;
            sel.value = current; // حفظ انتخاب قبلی
        }
        async function loadSections() {
            try {
                const res = await fetch('../api/organization/activity-sections.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();
                if (data.success) {
                    data.sections.forEach(s => {
                        acticity_section[s.section_key] = s.section_label;
                    });
                }
            } catch {}
        }

        async function loadUsers() {
            try {
                const response = await fetch('../api/users/list.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await response.json();
                if (data.success) {
                    users = data.users;
                    AssigneePicker.create({
                        container: '#filterAssigneePicker',
                        users,
                        sectionMap: acticity_section,
                        showSections: false,
                        placeholder: 'همه پرسنل',
                        onSelect: (_, v) => {
                            filterAssigneeId = v || '';
                            applyFilters();
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading users:', error);
            }
        }

        function updateStats() {
            const today = todayLocal();
            const todayCount = allTasks.filter(t => {
                if (t.task_type === 'periodic') return t.due_date === today && t.status !== 'completed' && t.status !== 'approved';
                if (t.task_type === 'continuous') return (t.overdue_periods || 0) > 0;
                return false;
            }).length;
            const overdueCount = allTasks.filter(t => {
                if (t.task_type === 'periodic') return t.due_date && t.due_date < today && (t.status === 'not_started' || t.status === 'in_progress');
                if (t.task_type === 'continuous') return (t.overdue_periods || 0) > 0;
                return false;
            }).length;
            const progressCount = allTasks.filter(t => t.status === 'in_progress').length;
            const completedCount = allTasks.filter(t => t.status === 'completed' || t.status === 'approved').length;
            const notStartedCount = allTasks.filter(t => t.status === 'not_started').length;
            const delegatedCount = allTasks.filter(t => t.creator_id === currentUser.id && t.assignee_id !== currentUser.id).length;

            document.getElementById('statToday').textContent = toPersian(todayCount);
            document.getElementById('statOverdue').textContent = toPersian(overdueCount);
            document.getElementById('statProgress').textContent = toPersian(progressCount);
            document.getElementById('statCompleted').textContent = toPersian(completedCount);
            document.getElementById('statNotStarted').textContent = toPersian(notStartedCount);
            document.getElementById('statDelegated').textContent = toPersian(delegatedCount);
        }

        function setStatFilter(filter) {
            statFilter = statFilter === filter ? '' : filter;
            clearStatActive();
            if (statFilter) document.querySelector(`[data-filter="${filter}"]`)?.classList.add('active');
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterPriority').value = '';
            document.getElementById('filterType').value = '';
            AssigneePicker.reset();
            filterAssigneeId = '';
            currentPage = 1;
            onFilterChange();
        }

        function clearStatActive() {
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
        }

        function onFilterChange() {
            applyFilters();
        }

        function applyResponsiveColumns() {
            if (!gridApi) return;
            const isMobile = window.innerWidth <= 576;

            // در موبایل پنهان شوند (فقط عنوان، وضعیت، موعد بماند)
            // 🆕 ستون «مهلت» با colId یکتا، تا با «موعد» قاطی نشود
            const hideOnMobile = [
                'id', 'creator_name', 'assignee_name',
                'task_type', 'created_at', 'col_mohlat'
            ];

            hideOnMobile.forEach(col => {
                gridApi.setColumnsVisible([col], !isMobile);
            });
        }

        function applyFilters() {
            const s = normalizeDigits(document.getElementById('searchInput').value).trim().toLowerCase();
            const as = filterAssigneeId;
            const st = document.getElementById('filterStatus').value;
            const pr = document.getElementById('filterPriority').value;
            const ty = document.getElementById('filterType').value;
            const gr = document.getElementById('filterGroup')?.value || ''; // 🆕
            const today = new Date().toISOString().split('T')[0];

            filteredTasks = allTasks.filter(t => {
                const otherText = (t.title || '') + ' ' + (t.description || '') + ' ' + t.id + ' ' + (t.history_text || '');
                t._checklistOnlyMatch = isChecklistOnlyMatch(otherText, t.checklist_titles || '', s);
                if (s && !matchesAllWords(otherText + ' ' + (t.checklist_titles || ''), s)) return false;
                if (as && t.assignee_id != as) return false;
                if (pr && t.priority !== pr) return false;
                if (ty && t.task_type !== ty) return false;
                // 🆕 فیلتر گروه
                if (gr === '__none__' && t.group_id) return false;
                else if (gr && gr !== '__none__' && t.group_id != gr) return false;
                if (st) {
                    // فیلتر پیش‌فرضِ «باز» فقط وقتی جستجویی در جریان نیست اعمال می‌شود؛
                    // با تایپ‌کردن در کادر جستجو، کارهای تکمیل‌شده/تأییدشده هم پیدا می‌شوند
                    if (st === 'open') {
                        if (!s && (t.status === 'completed' || t.status === 'approved')) return false;
                    } else if (t.status !== st) return false;
                }

                if (statFilter === 'today') {
                    if (t.task_type === 'periodic') {
                        if (!(t.due_date === today && t.status !== 'completed' && t.status !== 'approved')) return false;
                    } else if (t.task_type === 'continuous') {
                        if (!((t.overdue_periods || 0) > 0)) return false;
                    } else return false;
                }
                if (statFilter === 'overdue') {
                    if (t.task_type === 'periodic') {
                        if (!(t.due_date && t.due_date < today && (t.status === 'not_started' || t.status === 'in_progress'))) return false;
                    } else if (t.task_type === 'continuous') {
                        if (!((t.overdue_periods || 0) > 0)) return false;
                    } else return false;
                }
                if (statFilter === 'in_progress' && t.status !== 'in_progress') return false;
                if (statFilter === 'completed' && t.status !== 'completed' && t.status !== 'approved') return false;
                if (statFilter === 'not_started' && t.status !== 'not_started') return false;
                if (statFilter === 'delegated' && !(t.creator_id === currentUser.id && t.assignee_id !== currentUser.id)) return false;
                return true;
            });

            filteredTasks.sort((a, b) => {
                let va = a[sortColumn] || '',
                    vb = b[sortColumn] || '';
                if (sortColumn === 'id') {
                    va = +va;
                    vb = +vb;
                }
                if (va < vb) return sortDirection === 'asc' ? -1 : 1;
                if (va > vb) return sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
            renderTable();
        }

        function renderTable() {
            if (gridApi) gridApi.setGridOption('rowData', filteredTasks);
        }

        // Helpers
        const statusCfg = {
            not_started: ['شروع نشده', 'circle'],
            in_progress: ['در حال انجام', 'play-circle'],
            completed: ['تکمیل شده', 'check-circle'],
            pending_approval: ['منتظر تأیید', 'hourglass-split'],
            approved: ['تأیید شده', 'check-circle-fill'],
            delegated: ['ارجاع شده', 'arrow-left-right'],
            rejected: ['متوقف', 'pause-circle'],
            termination_requested: ['در انتظار اتمام', 'hourglass-split'],
            period_done: ['دوره انجام شد', 'calendar-check']
        };
        const priorityCfg = {
            high: ['بالا', 'arrow-up'],
            medium: ['متوسط', 'dash'],
            low: ['پایین', 'arrow-down']
        };
        const typeCfg = {
            periodic: ['مقطعی', 'calendar-event'],
            continuous: ['دوره‌ای', 'arrow-repeat']
        };

        function statusBadge(s) {
            const [l, i] = statusCfg[s] || [s, 'circle'];
            return `<span class="badge status-${s}"><i class="bi bi-${i}"></i>${l}</span>`;
        }

        function priorityBadge(p) {
            const [l, i] = priorityCfg[p] || [p, 'dash'];
            return `<span class="badge priority-${p}"><i class="bi bi-${i}"></i>${l}</span>`;
        }

        function typeBadge(t) {
            const [l, i] = typeCfg[t] || [t, 'tag'];
            return `<span class="badge type-${t}"><i class="bi bi-${i}"></i>${l}</span>`;
        }

        function daysLeft(d, status) {
            if (!d) return '<span class="days-badge">-</span>';
            if (status === 'completed' || status === 'approved') return '<span class="days-badge days-normal">تکمیل</span>';
            const diff = Math.ceil((new Date(d).setHours(0, 0, 0, 0) - new Date().setHours(0, 0, 0, 0)) / 864e5);
            if (diff < 0) return `<span class="days-badge days-overdue">${toPersian(-diff)} روز تاخیر</span>`;
            if (diff === 0) return `<span class="days-badge days-today">امروز</span>`;
            if (diff <= 3) return `<span class="days-badge days-soon">${toPersian(diff)} روز دیگر</span>`;
            return `<span class="days-badge days-normal">${toPersian(diff)} روز</span>`;
        }

        function fmtDate(d) {
            return d ? new Date(d).toLocaleDateString('fa-IR') : '-';
        }

        function relTime(d) {
            if (!d) return '';
            const ms = Date.now() - new Date(d),
                m = Math.floor(ms / 6e4),
                h = Math.floor(ms / 36e5),
                dy = Math.floor(ms / 864e5);
            if (m < 60) return `${toPersian(m)} دقیقه پیش`;
            if (h < 24) return `${toPersian(h)} ساعت پیش`;
            if (dy < 7) return `${toPersian(dy)} روز پیش`;
            if (dy < 30) return `${toPersian(Math.floor(dy / 7))} هفته پیش`;
            return `${toPersian(Math.floor(dy / 30))} ماه پیش`;
        }

        function toPersian(n) {
            return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹' [d]);
        }
        // ستون عملیات — حذف فقط برای کارهای غیرروتین و تکمیل/تأییدنشده
        function buildActionButtons(t) {
            if (!t) return '';
            if (t.is_workflow_task == 1) return ''; // کارهای روتین دکمهٔ حذف ندارند
            if (t.status === 'completed' || t.status === 'approved') return '';
            return `<button class="btn btn-sm btn-outline-danger" style="padding:.15rem .4rem;"
                onclick="event.stopPropagation();deleteTask(${t.id})" title="حذف کار">
                <i class="bi bi-trash"></i>
            </button>`;
        }

        function deleteTask(taskId) {
            uiConfirm('آیا از حذف این کار اطمینان دارید؟', async function() {
                try {
                    const res = await fetch('../api/tasks/delete.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        loadTasks();
                    } else {
                        alert('❌ خطا: ' + (data.message || 'عملیات ناموفق بود'));
                    }
                } catch (e) {
                    console.error(e);
                    alert('❌ خطا در ارتباط با سرور');
                }
            }, {
                danger: true,
                yesText: 'بله، حذف',
                noText: 'انصراف'
            });
        }

        function viewTask(id) {
            window.location.href = `task-detail.php?id=${id}`;
        }

        function showError(msg) {
            console.error(msg);
            if (gridApi) gridApi.setGridOption('rowData', []);
        }
    </script>
</body>

</html>