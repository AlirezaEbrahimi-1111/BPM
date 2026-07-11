<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../config/config.php';
require_once '../includes/version.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کارهای واگذار شده - سیستم مدیریت کار</title>

    <!-- Bootstrap 5 RTL -->
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>" rel="stylesheet">

    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <!-- Persian Date -->
    <script src="<?= asset('../assets/js/cdn/persian-date.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>

</head>

<body>

    <?php include 'header.php'; ?>

    <div class="overview-container">

        <!-- Filters -->

        <div class="filters-wrapper two-col">

            <!-- عنوان -->
            <div class="filters-title-col">
                <h1>
                    <i class="bi bi-arrow-right-circle"></i> کارهای واگذار شده
                </h1>
                <p>
                    کارهایی که به دیگران واگذار کرده‌اید
                </p>
                <!-- Stats Row -->
                <div class="stats-row" style="display: none;">
                    <div class="stat-card">
                        <div class="stat-icon total"><i class="bi bi-list-task"></i></div>
                        <div>
                            <div class="stat-number" id="totalDelegated">0</div>
                            <div class="stat-label">کل واگذار شده</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon done"><i class="bi bi-check-circle"></i></div>
                        <div>
                            <div class="stat-number" id="completedDelegated">0</div>
                            <div class="stat-label">تکمیل شده</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon pending"><i class="bi bi-play-circle"></i></div>
                        <div>
                            <div class="stat-number" id="pendingDelegated">0</div>
                            <div class="stat-label">در انتظار</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon overdue"><i class="bi bi-exclamation-triangle"></i></div>
                        <div>
                            <div class="stat-number" id="overdueDelegated">0</div>
                            <div class="stat-label">عقب افتاده</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- فیلترها -->
            <div style="flex: 1;">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" placeholder="جستجو در عنوان، توضیحات یا شناسه...">
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
                    <div class="filter-item no-arrow">
                        <div class="checkbox-item">
                            <input type="checkbox" id="includePreviousDelegations">
                            <label for="includePreviousDelegations">ارجاعات سابق</label>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Table -->
        <div id="myGrid" class="ag-theme-alpine" style="height: 620px; width: 100%; padding-top: 1rem;"></div>

    </div>
    <?php include 'footer.php'; ?>

    <div class="quick-actions">
        <button class="fab" onclick="showNewTaskModal()" title="کار جدید">
            <i class="bi bi-plus"></i>
        </button>

    </div>

    <!-- Bootstrap JS -->
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/jquery-3.6.0.min.js') ?>"></script>
    <script src="<?= asset('../../assets/js/table-utils.js') ?>"></script>
    <script src="<?= asset('../assets/js/assignee-picker.js') ?>"></script>
    <script src="<?= asset('../../assets/js/task-filters.js') ?>"></script>

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
                width: 90,
                resizable: true,
                sortable: false,
                cellRenderer: p => {
                    const t = p.data;
                    if (!t.assignee_id || t.status === 'completed' || t.status === 'approved') return '';
                    const title = (t.title || '').replace(/'/g, "\\'");
                    const name = (t.assignee_name || 'نامشخص').replace(/'/g, "\\'");
                    return `<button class="btn-remind-overview" onclick="event.stopPropagation();sendReminder(${t.id},'${title}','${name}')" title="یادآوری"><i class="bi bi-bell"></i></button>`;
                }
            },
        ];

        const gridOptions = {
            columnDefs,
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
            onGridReady: params => {
                const saved = localStorage.getItem('delegatedTasksGridState');
                if (saved) params.api.applyColumnState({ state: JSON.parse(saved), applyOrder: true });
                applyResponsiveColumns();   // 🆕 تنظیم ستون‌ها بر اساس اندازه صفحه

                // 🆕 اگر با ?sort=overdue آمده‌ایم → سورت بر اساس موعد (معوقه‌ها اول)
                const usp = new URLSearchParams(location.search);
                if (usp.get('sort') === 'overdue') {
                    params.api.applyColumnState({
                        state: [{ colId: 'col_moed', sort: 'asc' }],
                        defaultState: { sort: null }
                    });
                }
            },
            onSortChanged: params => localStorage.setItem('delegatedTasksGridState', JSON.stringify(params.api.getColumnState())),
            onColumnResized: params => localStorage.setItem('delegatedTasksGridState', JSON.stringify(params.api.getColumnState())),
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
        // 🆕 با تغییر اندازه پنجره، ستون‌ها دوباره تنظیم شوند
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(applyResponsiveColumns, 200);
        });
        let currentUser = null;

        function showNewTaskModal() {
            window.location.href = 'create-task.php';
        }

        function showDailyReportModal() {
            window.location.href = 'daily-report.php';
        }

        function normalizeDigits(str) {
            return str
                .replace(/[۰-۹]/g, d => d.charCodeAt(0) - 1776)
                .replace(/[٠-٩]/g, d => d.charCodeAt(0) - 1632);
        }
        document.addEventListener('DOMContentLoaded', function() {
            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }

            loadUserInfo();
            loadSections().then(() => loadAssigneeList());
            document.getElementById('filterStatus').value = 'open';
            loadTasks();

            ['filterStatus', 'filterPriority', 'filterType'].forEach(id => {
                document.getElementById(id).addEventListener('change', onFilterChange);
            });


            document.getElementById('includePreviousDelegations').addEventListener('change', loadTasks);

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

        async function loadUserInfo() {
            try {
                const res = await fetch('../api/auth/profile.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();
                if (data.success) currentUser = data.user;
            } catch (e) {
                console.error(e);
            }
        }

        // 🆕 نمایش ستون‌های کمتر در موبایل
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

        async function loadAssigneeList() {
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

        async function loadTasks() {
            try {
                const includeHistory = document.getElementById('includePreviousDelegations').checked;
                const apiUrl = includeHistory ?
                    '../api/tasks/delegated-tasks.php?filter=previous_delegations' :
                    '../api/tasks/delegated-tasks.php';
                const res = await fetch(apiUrl, {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();
                if (data.success) {
                    allTasks = data.tasks || [];
                    updateStats();
                    applyFilters();
                } else showError(data.message || 'خطا');
            } catch (e) {
                console.error(e);
                showError('خطا در ارتباط با سرور');
            }
        }

        function updateStats() {
            const today = todayLocal();
            document.getElementById('totalDelegated').textContent = toPersian(allTasks.length);
            document.getElementById('completedDelegated').textContent = toPersian(allTasks.filter(t => t.status === 'completed' || t.status === 'approved').length);
            document.getElementById('pendingDelegated').textContent = toPersian(allTasks.filter(t => t.status === 'in_progress' || t.status === 'not_started').length);
            document.getElementById('overdueDelegated').textContent = toPersian(allTasks.filter(t => t.due_date && t.due_date < today && t.status !== 'completed' && t.status !== 'approved').length);
        }

        function onFilterChange() {
            applyFilters();
        }


        function applyFilters() {
            const s = normalizeDigits(document.getElementById('searchInput').value).trim().toLowerCase();
            const as = filterAssigneeId;
            const st = document.getElementById('filterStatus').value;
            const pr = document.getElementById('filterPriority').value;
            const ty = document.getElementById('filterType').value;

            filteredTasks = allTasks.filter(t => {
                const otherText = (t.title || '') + ' ' + (t.description || '') + ' ' + (t.assignee_name || '') + ' ' + t.id;
                t._checklistOnlyMatch = isChecklistOnlyMatch(otherText, t.checklist_titles || '', s);
                if (s && !matchesAllWords(otherText + ' ' + (t.checklist_titles || ''), s)) return false;
                if (as && t.assignee_id != as) return false;
                if (pr && t.priority !== pr) return false;
                if (ty && t.task_type !== ty) return false;
                if (st) {
                    if (st === 'open' && (t.status === 'completed' || t.status === 'approved')) return false;
                    else if (st !== 'open' && t.status !== st) return false;
                }
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

        function viewTask(id) {
            window.location.href = `task-detail.php?id=${id}`;
        }

        function sendReminder(taskId, title, assigneeName) {
            uiPrompt(`ارسال یادآوری برای "${title}" به ${assigneeName}:`, async function(message) {
                if (!message) return;
                try {
                    const response = await fetch('../api/tasks/send-reminder.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            message: message
                        })
                    });
                    const data = await response.json();
                    if (data.success) showAlert('یادآوری با موفقیت ارسال شد', 'success');
                    else showAlert(data.message || 'خطا در ارسال یادآوری', 'danger');
                } catch (error) {
                    showAlert('خطا در ارتباط با سرور', 'danger');
                }
            }, {
                placeholder: 'پیام یادآوری...',
                required: true,
                okText: 'ارسال'
            });
        }

        function showError(msg) {
            console.error(msg);
            if (gridApi) gridApi.setGridOption('rowData', []);
        }

        function showAlert(message, type = 'info') {
            const map = {
                danger: 'warning',
                error: 'warning'
            };
            showToast(message, map[type] || type);
        }
    </script>

</body>

</html>