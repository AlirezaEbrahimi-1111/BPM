<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../config/config.php';
require_once '../includes/version.php';
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
if (!$__me || (!hasPermission($__me, 'view_all_org_tasks') && !hasPermission($__me, 'view_section_tasks'))) {
    header('Location: dashboard-manager.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظارت بر کارها - سیستم مدیریت کار</title>

    <!-- Bootstrap 5 RTL -->
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>" rel="stylesheet">

    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <!-- بعد از custom.css اضافه شود -->
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
</head>

<body>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/pages/header.php'; ?>


    <div class="overview-container">
        <!-- Filters -->

        <div class="filters-wrapper two-col">
            <div class="filters-title-col">
                <h1><i class="bi bi-graph-up"></i> نظارت بر کارها</h1>
                <p> مشاهده
                    و مدیریت کارهای زیردستان</p>
            </div>
            <!-- بخش چپ: فیلترها و جستجو (80%) -->
            <div style="flex: 1;">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" placeholder="جستجو در عنوان یا توضیحات...">
                </div>

                <div class="filters-row">
                    <div class="filter-item">
                        <div id="filterCreatorPicker"></div>
                    </div>
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
                        </select>
                    </div>
                    <div class="filter-item" style="display: none;">
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
                    <div class="filter-item no-arrow" id="managerFilters" style="display: none;">
                        <div class="checkbox-item">
                            <input type="checkbox" id="includeMyTasks">
                            <label for="includeMyTasks">نمایش کارهای من</label>
                        </div>
                    </div>


                </div>
                <!-- 🆕 نوار فیلتر فعال (وقتی از داشبورد می‌آییم) -->
                <div id="activeFilterBar" style="display:none; margin-top:10px; padding:10px 14px; background:#fff8f0; border:1px solid #fed7aa; border-radius:10px; align-items:center; gap:10px;">
                    <i class="bi bi-funnel-fill" style="color:#ea580c;"></i>
                    <span id="activeFilterText" style="font-size:.86rem; color:#9a3412; flex:1;"></span>
                    <button onclick="clearDashboardFilters()" style="background:#fff; border:1px solid #fed7aa; color:#ea580c; border-radius:8px; padding:5px 14px; font-size:.8rem; cursor:pointer;">
                        پاک کردن فیلتر
                    </button>
                </div>
            </div>
        </div>

        <div id="myGrid" class="ag-theme-alpine" style="height: 620px; width: 100%; padding-top: 1rem;"></div>

    </div>
    <?php include 'footer.php'; ?>

    <script src="<?= asset('../../assets/js/table-utils.js') ?>"></script>
    <script src="<?= asset('../assets/js/assignee-picker.js') ?>"></script>
    <script>
        let currentPage = 1,
            totalPages = 1,
            allTasks = [],
            filteredTasks = [],
            searchTimeout;
        let sortColumn = 'created_at',
            sortDirection = 'desc';
        let perPage = 15;
        let gridApi = null; // ← اضافه می‌شود
        let userRole = null;
        let currentUserId = null;
        let acticity_section = {};
        let filterCreatorId = '';
        let filterAssigneeId = '';
        const columnDefs = [{
                field: 'id',
                headerName: 'شناسه',
                width: 75,
                cellRenderer: p => p.value ? String(p.value).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹' [d]) : '-'
            },
            {
                field: 'title',
                headerName: 'عنوان',
                width: 90,
                flex: 2,
                cellRenderer: p => {
                    const desc = p.data.description ? `<div style="font-size:0.7rem;color:#94a3b8;line-height:1.4;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px;">${esc(p.data.description)}</div>` : '';
                    return `<div>${esc(p.value) || '-'}${checklistMatchBadge(p.data)}${desc}</div>`;
                }
            },
            {
                field: 'creator_name',
                headerName: 'تعریف‌کننده',
                flex: 1
            },
            {
                field: 'assignee_name',
                headerName: 'مسئول انجام',
                flex: 1,
                cellRenderer: p => {
                    const t = p.data;
                    const personName = (p.value && p.value.trim()) ? esc(p.value.trim()) : '';
                    const isWf = (t.is_workflow_task == 1);
                    const unitLabel = t.activity_section ? esc(acticity_section[t.activity_section] || t.activity_section) : '';

                    let main;
                    if (isWf) {
                        if (t.assignee_id && personName) {
                            // شروع‌شده توسط فردی از واحد → «واحد (نام فرد)»
                            main = unitLabel ? `${unitLabel} (${personName})` : personName;
                        } else {
                            // هنوز شروع‌نشده → فقط نام واحد
                            main = unitLabel || 'نامشخص';
                        }
                    } else {
                        // کار عادی
                        main = personName || 'نامشخص';
                    }

                    let html = `<div>${main}</div>`;

                    const extra = Array.isArray(t.checklist_assignees) ? t.checklist_assignees : [];
                    if (extra.length) {
                        const badges = extra.map(name =>
                            `<span style="display:inline-block;background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe;border-radius:10px;padding:1px 7px;font-size:0.68rem;margin:1px 2px;">${esc(name)}</span>`
                        ).join('');
                        html += `<div style="margin-top:2px;line-height:1.6;">
                                    <span style="font-size:0.65rem;color:#94a3b8;">چک‌لیست:</span> ${badges}
                                 </div>`;
                    }
                    return html;
                }
            },
            {
                field: 'status',
                headerName: 'وضعیت',
                width: 130,
                cellRenderer: p => statusBadge(p.value)
            },
            {
                field: 'task_type',
                headerName: 'نوع',
                width: 95,
                cellRenderer: p => typeBadge(p.value)
            },
            {
                field: 'deadline',
                colId: 'col_moed',
                headerName: 'موعد',
                width: 105,
                resizable: true,
                comparator: (a, b, nodeA, nodeB) => {
                    const da = [nodeA.data.due_date, nodeA.data.deadline, nodeA.data.original_deadline].filter(d => d).sort().pop() || '9999';
                    const db = [nodeB.data.due_date, nodeB.data.deadline, nodeB.data.original_deadline].filter(d => d).sort().pop() || '9999';
                    return da < db ? -1 : da > db ? 1 : 0;
                },
                cellRenderer: p => {
                    const d = [p.data.due_date, p.data.deadline, p.data.original_deadline].filter(d => d).sort().pop();
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
                    const da = [nodeA.data.due_date, nodeA.data.deadline, nodeA.data.original_deadline].filter(d => d).sort().pop() || '9999';
                    const db = [nodeB.data.due_date, nodeB.data.deadline, nodeB.data.original_deadline].filter(d => d).sort().pop() || '9999';
                    return da < db ? -1 : da > db ? 1 : 0;
                },
                cellRenderer: p => {
                    const d = [p.data.due_date, p.data.deadline, p.data.original_deadline].filter(d => d).sort().pop();
                    return daysLeft(d, p.data.status);
                }
            },
            {
                field: 'created_at',
                headerName: 'ایجاد',
                width: 120,
                cellRenderer: p => `<span class="date-display">${fmtDate(p.value)}</span><br><span class="date-relative">${relTime(p.value)}</span>`
            },
            {
                headerName: 'عملیات',
                width: 110,
                sortable: false,
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
            overlayLoadingTemplate: '<div style="display:flex;flex-direction:column;align-items:center;gap:10px;color:#744ca4;font-size:.85rem;"><div class="spinner-border" style="width:2.2rem;height:2.2rem;" role="status"></div><span>در حال بارگذاری...</span></div>',
            onRowClicked: params => viewTask(params.data.id),
            onGridReady: params => {
                const saved = localStorage.getItem('allTasksGridState');
                if (saved) params.api.applyColumnState({
                    state: JSON.parse(saved),
                    applyOrder: true
                });
                applyResponsiveColumns(); // 🆕 تنظیم ستون‌ها بر اساس اندازه صفحه
            },
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
        document.addEventListener('DOMContentLoaded', function() {
            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }
            loadFiltersFromURL();
            checkManagerRole();
            document.getElementById('filterStatus').value = 'open';

            loadSections().then(() => loadUsers());
            loadTasks();
            initColumnResize('.table', [55, 220, 110, 110, 115, 80, 85, 95, 110, 120, 65]);
            ['filterPriority', 'filterStatus', 'filterType'].forEach(id => {
                document.getElementById(id).addEventListener('change', onFilterChange);
            });

            // دکمه‌های تعداد نمایش
            document.querySelectorAll('.per-page-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.per-page-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    perPage = parseInt(this.dataset.value);
                    currentPage = 1;
                    onFilterChange();
                });
            });

            document.getElementById('includeMyTasks').addEventListener('change', loadTasks);
            document.getElementById('searchInput').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    onFilterChange();
                }, 300);
            });
            document.querySelectorAll('th[data-sort]').forEach(th => {
                th.addEventListener('click', () => handleSort(th.dataset.sort));
            });
        });

        function loadFiltersFromURL() {
            const p = new URLSearchParams(window.location.search);
            if (p.get('search')) document.getElementById('searchInput').value = p.get('search');
            // مقادیر URL بعد از loadUsers اعمال می‌شوند — در callback onSelect ذخیره شده‌اند
            // اگر نیاز به pre-select دارید، پس از AssigneePicker.create() فراخوانی کنید:
            //   pickerCreator.setValue(p.get('creator'));
            if (p.get('status')) document.getElementById('filterStatus').value = p.get('status');
            if (p.get('priority')) document.getElementById('filterPriority').value = p.get('priority');
            if (p.get('type')) document.getElementById('filterType').value = p.get('type');
            if (p.get('perPage')) {
                perPage = parseInt(p.get('perPage'));
                document.querySelectorAll('.per-page-btn').forEach(btn => {
                    btn.classList.toggle('active', parseInt(btn.dataset.value) === perPage);
                });
            }
            if (p.get('page')) currentPage = parseInt(p.get('page')) || 1;
            if (p.get('sort')) sortColumn = p.get('sort');
            if (p.get('dir')) sortDirection = p.get('dir');

            // 🆕 فیلترهای ورودی از داشبورد
            if (p.get('assignee')) filterAssigneeId = p.get('assignee');
            if (p.get('section')) window._filterSection = p.get('section');
            if (p.get('filter') === 'overdue') window._filterOverdue = true;

            // نمایش نوار فیلتر فعال
            showActiveFilterBar(p);
        }

        /* 🆕 نمایش نوار فیلتر فعال بر اساس پارامترهای داشبورد */
        function showActiveFilterBar(p) {
            const parts = [];

            if (window._filterOverdue) parts.push('فقط کارهای تأخیردار');

            if (p.get('section')) {
                const secFa = (typeof acticity_section !== 'undefined' && acticity_section[p.get('section')]) ?
                    acticity_section[p.get('section')] : p.get('section');
                parts.push(`واحد: ${secFa}`);
            }

            // نام کاربر بعد از لود کاربران اضافه می‌شود (در loadUsers)
            if (p.get('assignee')) {
                window._pendingAssigneeName = true;
            }

            if (parts.length || p.get('assignee')) {
                const bar = document.getElementById('activeFilterBar');
                bar.style.display = 'flex';
                document.getElementById('activeFilterText').textContent =
                    'فیلتر فعال: ' + (parts.join(' • ') || '...');
                window._activeFilterParts = parts;
            }
        }

        /* 🆕 پاک کردن فیلترهای داشبورد */
        function clearDashboardFilters() {
            window._filterOverdue = false;
            window._filterSection = null;
            filterAssigneeId = '';

            document.getElementById('activeFilterBar').style.display = 'none';

            // ریست کردن picker مسئول (اگر ممکن)
            if (window._assigneePickerRef && typeof window._assigneePickerRef.setValue === 'function') {
                window._assigneePickerRef.setValue('');
            }

            // پاک کردن URL
            window.history.replaceState({}, '', window.location.pathname);

            applyFilters();
        }


        function saveFiltersToURL() {
            const p = new URLSearchParams();
            const vals = {
                search: document.getElementById('searchInput').value,
                creator: filterCreatorId,
                assignee: filterAssigneeId,
                status: document.getElementById('filterStatus').value,
                priority: document.getElementById('filterPriority').value,
                type: document.getElementById('filterType').value,
                perPage: perPage
            };
            Object.entries(vals).forEach(([k, v]) => {
                if (v && v !== 15) p.set(k, v);
            });
            if (currentPage > 1) p.set('page', currentPage);
            if (sortColumn !== 'created_at') p.set('sort', sortColumn);
            if (sortDirection !== 'desc') p.set('dir', sortDirection);
            window.history.replaceState({}, '', p.toString() ? `?${p.toString()}` : window.location.pathname);
        }

        function onFilterChange() {
            applyFilters();
            saveFiltersToURL();
        }

        function handleSort(col) {
            if (isCurrentlyResizing()) return;
            sortDirection = sortColumn === col ? (sortDirection === 'asc' ? 'desc' : 'asc') : 'asc';
            sortColumn = col;
            document.querySelectorAll('th[data-sort]').forEach(th => {
                th.classList.remove('sorted');
                th.querySelector('.sort-icon').className = 'bi bi-arrow-down sort-icon';
            });
            const h = document.querySelector(`th[data-sort="${col}"]`);
            h.classList.add('sorted');
            h.querySelector('.sort-icon').className = `bi bi-arrow-${sortDirection === 'asc' ? 'up' : 'down'} sort-icon`;
            onFilterChange();
        }

        async function checkManagerRole() {
            try {
                const res = await fetch('../api/auth/profile.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();
                if (!data.success || !['manager', 'supervisor'].includes(data.user.role)) {
                    showToast('⛔ دسترسی ندارید', 'error');
                    setTimeout(() => {
                        window.location.href = 'dashboard-user.php';
                    }, 1200);
                    return;
                }
                userRole = data.user.role;
                currentUserId = data.user.id;
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

        function normalizeDigits(str) {
            return str
                .replace(/[۰-۹]/g, d => d.charCodeAt(0) - 1776)
                .replace(/[٠-٩]/g, d => d.charCodeAt(0) - 1632);
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
                    const pickerCfg = {
                        users,
                        sectionMap: acticity_section,
                        showSections: false
                    };
                    AssigneePicker.create({
                        ...pickerCfg,
                        container: '#filterCreatorPicker',
                        placeholder: 'همه تعریف‌کنندگان',
                        onSelect: (_, v) => {
                            filterCreatorId = v || '';
                            applyFilters();
                        }
                    });
                    const assigneePicker = AssigneePicker.create({
                        ...pickerCfg,
                        container: '#filterAssigneePicker',
                        placeholder: 'همه مسئولان',
                        onSelect: (_, v) => {
                            filterAssigneeId = v || '';
                            applyFilters();
                        }
                    });

                    // 🆕 اگر از داشبورد با ?assignee آمده‌ایم، pre-select کن
                    window._assigneePickerRef = assigneePicker;
                    if (filterAssigneeId && assigneePicker && typeof assigneePicker.setValue === 'function') {
                        assigneePicker.setValue(filterAssigneeId);
                    }

                    // نام کاربر را به نوار فیلتر فعال اضافه کن
                    if (window._pendingAssigneeName && filterAssigneeId) {
                        const u = users.find(x => String(x.id) === String(filterAssigneeId));
                        if (u) {
                            const parts = window._activeFilterParts || [];
                            parts.unshift(`مسئول: ${u.first_name} ${u.last_name}`);
                            document.getElementById('activeFilterText').textContent = 'فیلتر فعال: ' + parts.join(' • ');
                        }
                    }
                }
            } catch (error) {
                console.error('Error loading users:', error);
            }
        }

        async function loadTasks() {
            try {
                const inc = document.getElementById('includeMyTasks').checked ? '1' : '0';
                const res = await fetch(`../api/tasks/overview.php?include_my_tasks=${inc}`, {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();
                if (data.success) {
                    allTasks = data.tasks || [];
                    applyFilters();
                    console.log('📋 اولین رکورد:', data.tasks[0]);
                } else showError(data.message || 'خطا');
            } catch (e) {
                console.error(e);
                showError('خطا در ارتباط');
            }
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

        function applyFilters() {
            const s = normalizeDigits(document.getElementById('searchInput').value).trim().toLowerCase();
            const cr = filterCreatorId;
            const as = filterAssigneeId;
            const st = document.getElementById('filterStatus').value;
            const pr = document.getElementById('filterPriority').value;
            const ty = document.getElementById('filterType').value;

            const today = new Date().toISOString().slice(0, 10);

            filteredTasks = allTasks.filter(t => {
                const otherText = (t.title || '') + ' ' + (t.description || '') + ' ' + t.id + ' ' + (t.history_text || '');
                t._checklistOnlyMatch = isChecklistOnlyMatch(otherText, t.checklist_titles || '', s);
                if (s && !matchesAllWords(otherText + ' ' + (t.checklist_titles || ''), s)) return false;
                if (cr && t.creator_id != cr) return false;
                if (as && t.assignee_id != as) return false;
                if (pr && t.priority !== pr) return false;
                if (ty && t.task_type !== ty) return false;
                if (st) {
                    // فیلترِ «باز» همیشه اعمال می‌شه، چه جستجویی در جریان باشه چه نه —
                    // قبلاً با تایپ‌کردن در کادر جستجو، کارهای تکمیل‌شده/تأییدشده/متوقف‌شده
                    // هم توی نتیجه‌ی «کارهای باز» ظاهر می‌شدن
                    if (st === 'open') {
                        if (['completed', 'approved', 'rejected'].includes(t.status)) return false;
                    } else if (t.status !== st) return false;
                }

                // 🆕 فیلتر واحد (از داشبورد)
                if (window._filterSection && t.activity_section !== window._filterSection) return false;

                // 🆕 فیلتر تأخیردار (از داشبورد) — با جستجو نادیده گرفته می‌شود
                if (window._filterOverdue && !s) {
                    // کارهای بسته‌شده تأخیردار محسوب نمی‌شوند
                    const closed = ['completed', 'approved', 'rejected', 'stopped'].includes(t.status);
                    if (closed) return false;

                    if (t.task_type === 'continuous') {
                        if (!((t.overdue_periods || 0) > 0)) return false;
                    } else {
                        const due = [t.due_date, t.deadline, t.original_deadline].filter(Boolean).sort().pop();
                        if (!due || due >= today) return false;
                    }
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
            if (status === 'completed' || status === 'approved')
                return '<span class="days-badge days-normal">تکمیل</span>';
            if (!d) return '<span class="days-badge">-</span>';
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

        function updatePaginationInfo(f, t, total) {
            document.getElementById('showingFrom').textContent = toPersian(f);
            document.getElementById('showingTo').textContent = toPersian(t);
            document.getElementById('totalTasks').textContent = toPersian(total);
        }

        function renderPagination(pages) {
            const nav = document.getElementById('paginationNav');
            if (pages <= 1) {
                nav.innerHTML = '';
                return;
            }
            let h = `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="goPage(${currentPage - 1});return false">قبلی</a></li>`;
            for (let i = 1; i <= pages; i++) {
                if (i === 1 || i === pages || (i >= currentPage - 2 && i <= currentPage + 2))
                    h += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" onclick="goPage(${i});return false">${toPersian(i)}</a></li>`;
                else if (i === currentPage - 3 || i === currentPage + 3)
                    h += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            h += `<li class="page-item ${currentPage === pages ? 'disabled' : ''}"><a class="page-link" href="#" onclick="goPage(${currentPage + 1});return false">بعدی</a></li>`;
            nav.innerHTML = h;
        }

        function goPage(p) {
            if (p >= 1 && p <= totalPages) {
                currentPage = p;
                onFilterChange();
                document.querySelector('.table-scroll').scrollTop = 0;
            }
        }

        function viewTask(id) {
            window.location.href = `task-detail.php?id=${id}`;
        }

        // showError از showInlineError مشترک (assets/js/alert.js) استفاده می‌کنه
        function showError(msg) {
            showInlineError('tasksTableBody', msg, { asTableRow: true, colspan: 11 });
        }

        function exportToExcel() {
            showToast('این قابلیت به زودی اضافه می‌شود', 'info');
        }

        // ========================================
        // ارسال یادآوری (نوتیفیکیشن و SMS)
        // ========================================
        function sendReminder(taskId, title, assigneeName) {
            uiPrompt(`ارسال یادآوری برای "${esc(title)}" به ${esc(assigneeName)}:`, async function(message) {
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

                    if (data.success) {
                        showAlert('یادآوری با موفقیت ارسال شد', 'success');
                    } else {
                        showAlert(data.message || 'خطا در ارسال یادآوری', 'error');
                    }
                } catch (error) {
                    console.error('Error sending reminder:', error);
                    showAlert('خطا در ارتباط با سرور', 'error');
                }
            }, {
                placeholder: 'پیام یادآوری...',
                required: true,
                okText: 'ارسال'
            });
        }

        // نمایش پیام (alert)
        // showAlert قبلاً یک پیاده‌سازیِ جداگانه (باکسِ alert بوت‌استرپ) داشت؛
        // الان فقط یک نام‌مستعارِ نازک برایِ showToastِ مشترکه (از assets/js/alert.js)
        function showAlert(message, type = 'info') {
            showToast(message, type);
        }

        function buildActionButtons(t) {
            if (!t) return '';
            const title = escJsAttr(t.title || '');
            const name = escJsAttr(t.assignee_name || 'نامشخص');
            let html = '';

            // دکمه یادآوری — فقط اگر مسئول دارد و تکمیل نشده
            if (t.assignee_id && t.status !== 'completed' && t.status !== 'approved') {
                html += `<button class="btn-remind-overview" 
            onclick="event.stopPropagation();sendReminder(${t.id},'${title}','${name}')" 
            title="یادآوری">
            <i class="bi bi-bell"></i>
        </button>`;
            }

            // دکمه حذف — فقط برای کارهای غیرروتین و تکمیل/تأییدنشده
            if (t.is_workflow_task != 1 && t.status !== 'completed' && t.status !== 'approved') {
                html += `<button class="btn-remind-overview" style="color:#dc3545;"
        onclick="event.stopPropagation();deleteTask(${t.id})" 
        title="حذف">
        <i class="bi bi-trash"></i>
    </button>`;
            }

            return html;
        }

        function deleteTask(taskId) {
            uiConfirm('آیا از حذف این کار اطمینان دارید؟', async function() {
                try {
                    const res = await fetch(`../api/tasks/delete.php`, {
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
                        loadTasks(); // بارگذاری مجدد لیست
                    } else {
                        showToast('❌ خطا: ' + (data.message || 'عملیات ناموفق بود'), 'error');
                    }
                } catch (e) {
                    console.error(e);
                    showToast('❌ خطا در ارتباط با سرور', 'error');
                }
            }, {
                danger: true,
                yesText: 'بله، حذف',
                noText: 'انصراف'
            });
        }
    </script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
</body>

</html>