<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once  $_SERVER['DOCUMENT_ROOT'] . '/includes/version.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

if (!isset($db)) {
    $database = new Database();
    $db = $database->getConnection();
}
$auth = new Auth($db);
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    header('Location: ../index.php');
    exit;
}

$__me = loadUserForPermissions($db, (int) $user_id);
if (!$__me) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کارهای من - سیستم مدیریت کار</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>"
        rel="stylesheet">
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
                <h1><i class="bi bi-graph-up"></i> کارهای من</h1>
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

                <div id="dashFilterBanner" style="display:none;align-items:center;justify-content:space-between;gap:10px;background:#f0e9fd;border:1px solid rgba(126,85,179,.25);border-radius:10px;padding:8px 14px;margin-bottom:10px;font-size:13px;color:#7e55b3;">
                    <span id="dashFilterBannerText"></span>
                    <a href="my-tasks.php" style="color:#7e55b3;font-weight:700;text-decoration:underline;">پاک کردن فیلتر</a>
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
                            <option value="delegated">ارجاع شده</option>
                            <option value="pending_approval">منتظر تأیید</option>
                            <option value="termination_requested">در انتظار اتمام</option>
                            <option value="period_done">دوره انجام شد</option>
                            <option value="completed">تکمیل شده</option>
                            <option value="approved">تأیید شده</option>
                            <option value="rejected">متوقف</option>
                            <option value="stopped">متوقف شده</option>
                            <option value="checklist_archive">کارهای تمام‌شده‌ی من (چک‌لیست)</option>
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
                </div>
            </div>
        </div>

        <!-- Table -->
        <div id="myGrid" class="ag-theme-alpine" style="height: 600px; width: 100%; padding-top: 1rem;"></div>

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
        let currentPage = 1,
            totalPages = 1,
            allTasks = [],
            filteredTasks = [],
            searchTimeout;
        let viewingArchive = false; // آیا الان بایگانی نمایش داده می‌شود؟
        let sortColumn = 'created_at',
            sortDirection = 'desc';
        let perPage = 10;
        let statFilter = 'today';
        let currentUser = null;
        let acticity_section = {};
        let filterAssigneeId = '';

        // فیلترِ آمده از لینک داشبورد (مودال هفته/ماه/+N مورد دیگر) — ?filter=week یا ?filter=month&jy=..&jm=.. یا ?filter=day&date=YYYY-MM-DD
        const urlParamsInit = new URLSearchParams(location.search);
        const dashFilter = urlParamsInit.get('filter'); // 'week' | 'month' | 'day' | null
        const dashFilterJY = parseInt(urlParamsInit.get('jy'), 10) || null;
        const dashFilterJM = parseInt(urlParamsInit.get('jm'), 10) || null;
        const dashFilterDate = urlParamsInit.get('date'); // برای filter=day

        /* تبدیل میلادی به شمسی (همان الگوریتم داشبورد مدیریت) */
        function toJalali(gy, gm, gd) {
            const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
            let jy = (gy <= 1600) ? 0 : 979;
            gy -= (gy <= 1600) ? 621 : 1600;
            const gy2 = (gm > 2) ? gy + 1 : gy;
            let days = 365 * gy + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) +
                Math.floor((gy2 + 399) / 400) - 80 + gd + g_d_m[gm - 1];
            jy += 33 * Math.floor(days / 12053);
            days %= 12053;
            jy += 4 * Math.floor(days / 1461);
            days %= 1461;
            jy += Math.floor((days - 1) / 365);
            if (days > 365) days = (days - 1) % 365;
            const jm = (days < 186) ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
            const jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
            return [jy, jm, jd];
        }

        function jalaliOf(d) {
            return toJalali(d.getFullYear(), d.getMonth() + 1, d.getDate());
        }

        /* تاریخ محلی به شکل YYYY-MM-DD (بدون تبدیل UTC، برخلاف toISOString) */
        function localYMD(d) {
            const p = n => String(n).padStart(2, '0');
            return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
        }


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
            localeText: {
                page: 'صفحه',
                more: 'بیشتر',
                to: 'تا',
                of: 'از',
                next: 'بعدی',
                last: 'آخر',
                first: 'اول',
                previous: 'قبلی',
                pageSize: 'تعداد در صفحه',
                rowCount: 'تعداد کل',
                noRowsToShow: 'داده‌ای یافت نشد',
                loadingOoo: 'در حال بارگذاری...',
            },
            overlayLoadingTemplate: '<div style="display:flex;flex-direction:column;align-items:center;gap:10px;color:#744ca4;font-size:.85rem;"><div class="spinner-border" style="width:2.2rem;height:2.2rem;" role="status"></div><span>در حال بارگذاری...</span></div>',
            animateRows: true,
            pagination: true,
            paginationPageSize: 15,
            paginationPageSizeSelector: [15, 30, 50, 100], // ✅ حل خطای #94 و #95

            // ✅ حفظ state بعد از برگشت به صفحه
            onGridReady: params => {
                const saved = localStorage.getItem('allTasksGridState');
                if (saved) params.api.applyColumnState({
                    state: JSON.parse(saved),
                    applyOrder: true
                });
                applyResponsiveColumns(); // 🆕 تنظیم ستون‌ها بر اساس اندازه صفحه
            },
            onSortChanged: params => localStorage.setItem('myTasksGridState', JSON.stringify(params.api.getColumnState())),
            onColumnResized: params => localStorage.setItem('myTasksGridState', JSON.stringify(params.api.getColumnState())),

            // ✅ کلیک روی ردیف
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
            // ✅ hover خودکار (AG Grid این رو built-in داره)
        };

        const gridApi = agGrid.createGrid(document.getElementById('myGrid'), gridOptions);
        gridApi.showLoadingOverlay();
        // 🆕 با تغییر اندازه پنجره، ستون‌ها دوباره تنظیم شوند
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(applyResponsiveColumns, 200);
        });

        function showNewTaskModal() {
            window.location.href = 'create-task.php';
        }

        function showDailyReportModal() {
            window.location.href = 'daily-report.php';
        }
        document.addEventListener('DOMContentLoaded', function() {
            authToken = localStorage.getItem('auth_token');
            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }

            currentUser = JSON.parse(localStorage.getItem('user_info') || '{}');



            loadTasks().then(() => applyDashFilterFromUrl());
            loadSections().then(() => loadUsers());

            ['filterStatus', 'filterPriority', 'filterType'].forEach(id => {
                document.getElementById(id).addEventListener('change', () => {
                    statFilter = '';
                    clearStatActive();
                    currentPage = 1;

                    // اگر «کارهای تمام‌شده‌ی من» انتخاب شد، از API بایگانی بخوان
                    if (id === 'filterStatus' &&
                        document.getElementById('filterStatus').value === 'checklist_archive') {
                        loadChecklistArchive();
                    } else if (viewingArchive) {
                        // از بایگانی به یک وضعیت دیگر سوئیچ شد → اول لیست فعال را از سرور بیاور
                        loadTasks();
                    } else {
                        onFilterChange();
                    }
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
        async function loadTasks() {
            try {
                const res = await fetch('../api/tasks/my-tasks.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();
                if (data.success) {
                    viewingArchive = false;
                    allTasks = data.data?.tasks || [];
                    statFilter = '';
                    document.getElementById('filterStatus').value = 'open';
                    updateStats();
                    applyFilters(); // این خودش renderTable رو صدا می‌زنه که gridApi رو آپدیت می‌کنه
                } else showError(data.message || 'خطا');
            } catch (e) {
                console.error(e);
                showError('خطا در ارتباط');
            }
        }

        /* اعمال فیلتر آمده از لینک داشبورد (مودال هفته/ماه) — بعد از لود اولیهٔ کارها */
        function applyDashFilterFromUrl() {
            if (dashFilter !== 'week' && dashFilter !== 'month' && dashFilter !== 'day') return;
            if (dashFilter === 'day' && !dashFilterDate) return;

            statFilter = dashFilter;
            document.getElementById('filterStatus').value = ''; // همهٔ وضعیت‌ها، نه فقط «باز»

            const banner = document.getElementById('dashFilterBanner');
            const bannerText = document.getElementById('dashFilterBannerText');
            const months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

            if (dashFilter === 'week') {
                bannerText.textContent = 'نمایش فقط کارهای این هفته';
            } else if (dashFilter === 'month') {
                const targetJY = dashFilterJY || jalaliOf(new Date())[0];
                const targetJM = dashFilterJM || jalaliOf(new Date())[1];
                bannerText.textContent = 'نمایش فقط کارهای ' + months[targetJM - 1] + ' ' + toPersian(targetJY);
            } else {
                const [gy, gm, gd] = dashFilterDate.split('-').map(Number);
                const [jy, jm, jd] = toJalali(gy, gm, gd);
                bannerText.textContent = 'نمایش فقط کارهای ' + toPersian(jd) + ' ' + months[jm - 1] + ' ' + toPersian(jy);
            }

            banner.style.display = 'flex';
            applyFilters();
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
        // بارگذاری کارهای تمام‌شده‌ی چک‌لیستی (بایگانی)
        async function loadChecklistArchive() {
            try {
                const res = await fetch('../api/tasks/my-tasks.php?filter=checklist_archive', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();
                if (data.success) {
                    viewingArchive = true;
                    allTasks = data.data.tasks || [];
                    // بایگانی نیازی به فیلترهای آماری ندارد؛ مستقیم نمایش بده
                    filteredTasks = allTasks;
                    renderTable();
                } else {
                    showError(data.message || 'خطا در بارگذاری بایگانی');
                }
            } catch (e) {
                console.error('loadChecklistArchive error:', e);
                showError('خطا در ارتباط');
            }
        }

        function updateStats() {
            const today = todayLocal();
            const todayCount = allTasks.filter(t => TF.isDueToday(t, currentUser, today)).length;
            const overdueCount = allTasks.filter(t => TF.isOverdue(t, currentUser, today)).length;
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

        function applyFilters() {
            const s = normalizeDigits(document.getElementById('searchInput').value).trim().toLowerCase();
            const as = filterAssigneeId;
            const st = document.getElementById('filterStatus').value;
            const pr = document.getElementById('filterPriority').value;
            const ty = document.getElementById('filterType').value;
            const today = todayLocal();

            filteredTasks = allTasks.filter(t => {
                const otherText = (t.title || '') + ' ' + (t.description || '') + ' ' + t.id + ' ' + (t.history_text || '');
                t._checklistOnlyMatch = isChecklistOnlyMatch(otherText, t.checklist_titles || '', s);
                if (s && !matchesAllWords(otherText + ' ' + (t.checklist_titles || ''), s)) return false;
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

                if (statFilter === 'today' && !TF.isDueToday(t, currentUser, today)) return false;
                if (statFilter === 'overdue' && !TF.isOverdue(t, currentUser, today)) return false;
                if (statFilter === 'in_progress' && t.status !== 'in_progress') return false;
                if (statFilter === 'completed' && t.status !== 'completed' && t.status !== 'approved') return false;
                if (statFilter === 'not_started' && t.status !== 'not_started') return false;
                if (statFilter === 'delegated' && !(t.creator_id === currentUser.id && t.assignee_id !== currentUser.id)) return false;

                if (statFilter === 'week') {
                    const due = TF.effectiveDue(t);
                    if (!due) return false;
                    const d = new Date(due);
                    d.setHours(0, 0, 0, 0);
                    if (isNaN(d)) return false;
                    const now = new Date();
                    now.setHours(0, 0, 0, 0);
                    const start = new Date(now);
                    start.setDate(now.getDate() - ((now.getDay() + 1) % 7));
                    const end = new Date(start);
                    end.setDate(start.getDate() + 6);
                    if (!(d >= start && d <= end)) return false;
                }

                if (statFilter === 'month') {
                    const due = TF.effectiveDue(t);
                    if (!due) return false;
                    const d = new Date(due);
                    if (isNaN(d)) return false;
                    const [jy, jm] = jalaliOf(d);
                    const targetJY = dashFilterJY || jalaliOf(new Date())[0];
                    const targetJM = dashFilterJM || jalaliOf(new Date())[1];
                    if (jy !== targetJY || jm !== targetJM) return false;
                }

                if (statFilter === 'day' && dashFilterDate) {
                    const due = TF.effectiveDue(t);
                    if (!due) return false;
                    const d = new Date(due);
                    if (isNaN(d)) return false;
                    if (localYMD(d) !== dashFilterDate) return false;
                }

                return true;
            });
            // ✅ محاسبه next_due_date و days_remaining برای هر تسک
            const todayDate = new Date();
            todayDate.setHours(0, 0, 0, 0);

            filteredTasks.forEach(t => {
                let effectiveDate = null;

                effectiveDate = TF.effectiveDue(t) || null;

                t.effective_due_date = effectiveDate;

                if (effectiveDate && t.status !== 'completed' && t.status !== 'approved') {
                    const dueDate = new Date(effectiveDate);
                    dueDate.setHours(0, 0, 0, 0);
                    t.days_remaining = Math.ceil((dueDate - todayDate) / 86400000);
                } else if (t.status === 'completed' || t.status === 'approved') {
                    t.days_remaining = 99999; // تکمیل شده‌ها آخر
                } else {
                    t.days_remaining = 99998; // بدون تاریخ
                }
            });
            // ✅ سورت اصلاح شده
            filteredTasks.sort((a, b) => {
                let va = a[sortColumn] ?? '',
                    vb = b[sortColumn] ?? '';

                if (sortColumn === 'id' || sortColumn === 'days_remaining') {
                    va = (va === '' || va === null) ? 99999 : +va;
                    vb = (vb === '' || vb === null) ? 99999 : +vb;
                }

                if (va < vb) return sortDirection === 'asc' ? -1 : 1;
                if (va > vb) return sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
            renderTable();
        }

        function renderTable() {
            if (!gridApi) return;
            gridApi.setGridOption('rowData', filteredTasks); // ✅ AG Grid داده‌ها رو می‌گیره
        }

        // Helpers
        const priorityCfg = {
            high: ['بالا', 'arrow-up'],
            medium: ['متوسط', 'dash'],
            low: ['پایین', 'arrow-down']
        };
        const typeCfg = {
            periodic: ['مقطعی', 'calendar-event'],
            continuous: ['دوره‌ای', 'arrow-repeat']
        };

        // برچسب/آیکن/رنگِ وضعیت — از assets/js/task-filters.js (تنها مرجع)
        function statusBadge(s) {
            return `<span class="badge ${TF.statusClass(s)}"><i class="bi bi-${TF.statusIcon(s)}"></i>${TF.statusLabel(s)}</span>`;
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

        // ========================================
        // Column Resize
        // ========================================


        // قبلاً فقط console.error می‌زد و هیچ پیغامی به کاربر نشون داده نمی‌شد
        function showError(msg) {
            console.error('خطا:', msg);
            if (gridApi) gridApi.setGridOption('rowData', []);
            showToast(msg || 'خطا در بارگذاریِ کارها', 'error');
        }
    </script>
</body>

</html>