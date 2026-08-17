<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
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
if (!$__me || !hasPermission($__me, 'view_reports')) {
    header('Location: dashboard-manager.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش‌گیری - سیستم مدیریت کار</title>

    <!-- Bootstrap 5 RTL -->
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">

    <!-- فونت فارسی -->

    <!-- تقویم شمسی -->
    <link rel="stylesheet"
        href="<?= asset('../assets/js/cdn/persian-datepicker.min.css') ?>">

    <script src="<?= asset('../assets/js/cdn/intro.min.js') ?>"></script>

    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/minified/introjs.min.css') ?>">
    <style>
                :root {
            --primary-color1: #6366F1;
            --primary-color2: #8B5CF6;
            --primary: #744ca4;
            --primary-dark: #657ae7;
            --primary-light: #8b6fb9;
            --success: #059669;
            --danger: #dc2626;
            --warning: #d97706;
            --info: #0891b2;
            --light-bg: #f5f7ff;
            --lighter-bg: #fafbff;
            --white: #ffffff;
            --light-hover: #f9fbfd;
            --border-light: #e2e8f0;
            --text-dark: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
        }

        * {
            font-family: 'Vazir', sans-serif !important;
        }

        body {
            background-color: #f8f9fa;
            margin: 0 auto;
            padding-top: 76px;

        }

        .main-content {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
            padding: 15px 20px;
        }

        .search-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            font-weight: 500;
            padding: 10px 20px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .report-card {
            border-radius: 12px;
            margin-bottom: 15px;
            border-right: 4px solid #007bff;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .report-card:hover {
            transform: translateX(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }

        .report-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
        }

        .report-code {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 5px;
            color: #495057;
        }

        .report-date {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .report-content {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            white-space: pre-wrap;
            font-size: 0.9rem;
            line-height: 1.6;
            max-height: 150px;
            overflow-y: auto;
        }

        .report-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-sm {
            padding: 5px 12px;
            font-size: 0.8rem;
            border-radius: 6px;
        }

        .unit-badge {
            font-size: 0.75rem;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .unit-RS {
            background-color: #007bff;
            color: white;
        }

        .unit-ATM {
            background-color: #28a745;
            color: white;
        }

        .unit-AM {
            background-color: #ffc107;
            color: #212529;
        }

        .unit-AC {
            background-color: #dc3545;
            color: white;
        }

        .unit-PR {
            background-color: #6f42c1;
            color: white;
        }

        .unit-HE {
            background-color: #fd7e14;
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        [dir="rtl"] .form-select {
            padding-left: 2.5rem !important;
            padding-right: 0.75rem !important;
            background-position: left 0.75rem center !important;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            border-left: 4px solid;
        }

        .stat-card.total {
            border-left-color: #007bff;
        }

        .stat-card.today {
            border-left-color: #28a745;
        }

        .stat-card.week {
            border-left-color: #ffc107;
        }

        .stat-card.month {
            border-left-color: #dc3545;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin: 5px 0 0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }

        .modal-lg {
            max-width: 900px;
        }

        .report-full-content {
            white-space: pre-wrap;
            font-size: 0.9rem;
            line-height: 1.8;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
            max-height: 400px;
            overflow-y: auto;
        }
        .quick-actions {
            position: fixed;
            bottom: 24px;
            left: 24px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
 .fab {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color1), var(--primary-color2)) !important;
            border: none;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fab:hover {
            background: #9d7bf3ff !important;
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .fab:active {
            transform: scale(0.95);
        }
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            .quick-actions {
                bottom: 16px;
                left: 16px;
            }
        .fab {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .search-section {
                padding: 20px 15px;
            }

            .report-actions {
                flex-wrap: wrap;
                gap: 5px;
            }

            .btn-sm {
                font-size: 0.75rem;
                padding: 4px 8px;
            }
        }

        .search-highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
        }

        .advanced-search {
            border-top: 1px solid #e9ecef;
            margin-top: 20px;
            padding-top: 20px;
        }

        .toggle-advanced {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .toggle-advanced:hover {
            text-decoration: underline;
        }

        /* ─── دارک‌مود ─── */
        :root[data-theme="dark"] body {
            background-color: var(--bg-page);
        }

        :root[data-theme="dark"] .search-section,
        :root[data-theme="dark"] .stat-card {
            background: var(--surface);
        }

        :root[data-theme="dark"] .report-code,
        :root[data-theme="dark"] .report-content,
        :root[data-theme="dark"] .report-full-content {
            background: var(--bg-page);
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .report-date,
        :root[data-theme="dark"] .stat-label,
        :root[data-theme="dark"] .empty-state,
        :root[data-theme="dark"] .loading {
            color: var(--text-muted);
        }

        :root[data-theme="dark"] .empty-state i {
            color: var(--border-soft);
        }

        :root[data-theme="dark"] .stat-number {
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .search-highlight {
            background-color: #7a5f0f;
            color: #fff3cd;
        }

        :root[data-theme="dark"] .advanced-search {
            border-top-color: var(--border-soft);
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container-fluid main-content">
        <!-- آمار گزارش‌ها -->
        <div class="stats-grid">
            <div class="stat-card total">
                <h3 class="stat-number" id="totalReports">0</h3>
                <p class="stat-label">کل گزارش‌ها</p>
            </div>
            <div class="stat-card today">
                <h3 class="stat-number" id="todayReports">0</h3>
                <p class="stat-label">گزارش‌های امروز</p>
            </div>
            <div class="stat-card week">
                <h3 class="stat-number" id="weekReports">0</h3>
                <p class="stat-label">این هفته</p>
            </div>
            <div class="stat-card month">
                <h3 class="stat-number" id="monthReports">0</h3>
                <p class="stat-label">این ماه</p>
            </div>
        </div>

        <!-- بخش جستجو -->
        <div class="search-section">
            <h5 class="mb-3"><i class="bi bi-search ms-2"></i>جستجو و فیلتر گزارش‌ها</h5>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">جستجو در متن</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="جستجو در محتوای گزارش‌ها...">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">واحد فعالیت</label>
                    <select class="form-select" id="unitFilter">
                        <option value="">همه واحدها</option>
                        <option value="RS">RS</option>
                        <option value="ATM">ATM</option>
                        <option value="AM">AM</option>
                        <option value="AC">AC</option>
                        <option value="PR">PR</option>
                        <option value="HE">HE</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">از تاریخ</label>
                    <input type="text" class="form-control persian-date" id="dateFrom" placeholder="از تاریخ">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">تا تاریخ</label>
                    <input type="text" class="form-control persian-date" id="dateTo" placeholder="تا تاریخ">
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100" onclick="searchReports()">
                        <i class="bi bi-search ms-2"></i>جستجو
                    </button>
                </div>
            </div>

            <div class="text-center">
                <a href="#" class="toggle-advanced" onclick="toggleAdvancedSearch()">
                    <i class="bi bi-chevron-down me-1" id="advancedIcon"></i>جستجوی پیشرفته
                </a>
            </div>

            <div id="advancedSearch" class="advanced-search" style="display: none;">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">کد گزارش</label>
                        <input type="text" class="form-control" id="reportCodeSearch" placeholder="RPT240101001">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">مرتب‌سازی</label>
                        <select class="form-select" id="sortBy">
                            <option value="report_date">تاریخ گزارش</option>
                            <option value="created_at">تاریخ ایجاد</option>
                            <option value="unique_code">کد گزارش</option>
                            <option value="activity_unit">واحد فعالیت</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">ترتیب</label>
                        <select class="form-select" id="sortOrder">
                            <option value="desc">جدیدترین</option>
                            <option value="asc">قدیمی‌ترین</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">تعداد نمایش</label>
                        <select class="form-select" id="limitResults">
                            <option value="10">10 گزارش</option>
                            <option value="25">25 گزارش</option>
                            <option value="50">50 گزارش</option>
                            <option value="100">100 گزارش</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- لیست گزارش‌ها -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-file-text ms-2"></i>گزارش‌های ارسال شده</h5>
                <!-- <div>
                    <button class="btn btn-sm btn-light ms-2" onclick="exportReports('excel')">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i>اکسل
                    </button>
                    <button class="btn btn-sm btn-light" onclick="exportReports('pdf')">
                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </button>
                </div> -->
            </div>
            <div class="card-body">
                <div id="reportsContainer">
                    <div class="loading">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">در حال بارگذاری...</span>
                        </div>
                        <p class="mt-3">در حال بارگذاری گزارش‌ها...</p>
                    </div>
                </div>

                <!-- صفحه‌بندی -->
                <div class="pagination-container" id="paginationContainer" style="display: none;">
                    <nav>
                        <ul class="pagination" id="paginationList">
                            <!-- صفحه‌بندی اینجا بارگذاری می‌شود -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- مودال نمایش کامل گزارش -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">جزئیات گزارش</h5>
                    <button type="button" class="btn-close me-2" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>کد گزارش:</strong>
                            <span id="modalReportCode" class="report-code ms-2"></span>
                        </div>
                        <div class="col-md-6 text-end">
                            <strong>تاریخ گزارش:</strong>
                            <span id="modalReportDate" class="ms-2"></span>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>واحد فعالیت:</strong>
                            <span id="modalActivityUnit" class="ms-2"></span>
                        </div>
                        <div class="col-md-6 text-end">
                            <strong>تاریخ ایجاد:</strong>
                            <span id="modalCreatedAt" class="ms-2"></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <strong>محتوای گزارش:</strong>
                        <div id="modalReportContent" class="report-full-content"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
        <?php include 'footer.php'; ?>

        <div class="quick-actions">
        <button class="fab" onclick="showNewTaskModal()" title="کار جدید">
            <i class="bi bi-plus"></i>
        </button>
    </div>
    <!-- Bootstrap JS -->
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>

    <!-- jQuery -->
    <script src="<?= asset('../assets/js/cdn/jquery-3.6.0.min.js') ?>"></script>

    <!-- تقویم شمسی -->
    <script src="<?= asset('../assets/js/cdn/persian-date.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/persian-datepicker.min.js') ?>"></script>

    <script>
        // let authToken = '';
        let currentUser = null;
        let allReports = [];
        let filteredReports = [];
        let currentPage = 1;
        let reportsPerPage = 10;
        let currentReportData = null;

        function toFa(n) {
            return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
        }

        // بارگذاری اولیه
        document.addEventListener('DOMContentLoaded', function () {
            checkAuth();
            console.log('Auth Token:', authToken); // این خط را اضافه کنید برای تست
            if (!authToken) {
                console.error('No token found!'); // این هم
                window.location.href = '../index.php';
                return;
            }
            setupDatePickers();
            setupEventListeners();
            loadReports();
            loadStats();
        });

        // بررسی احراز هویت
        function checkAuth() {
            authToken = localStorage.getItem('auth_token');
            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }

            const userInfo = localStorage.getItem('user_info');
            if (userInfo) {
                currentUser = JSON.parse(userInfo);
                updateUserInfo();
            }
        }

        // بروزرسانی اطلاعات کاربر
        function updateUserInfo() {
            const userName = currentUser.first_name && currentUser.last_name
                ? `${currentUser.first_name} ${currentUser.last_name}`
                : 'کاربر گرامی';

            document.getElementById('userName').textContent = userName;
        }

        // راه‌اندازی تقویم شمسی
        function setupDatePickers() {
            $('.persian-date').pDatepicker({
                format: 'YYYY/MM/DD',
                initialValue: false,
                observer: true,
                calendar: {
                    persian: {
                        locale: 'fa'
                    }
                }
            });
        }

        // راه‌اندازی event listeners
        function setupEventListeners() {
            document.getElementById('searchInput').addEventListener('input', debounce(searchReports, 500));
            document.getElementById('unitFilter').addEventListener('change', searchReports);
            document.getElementById('sortBy').addEventListener('change', searchReports);
            document.getElementById('sortOrder').addEventListener('change', searchReports);
            document.getElementById('limitResults').addEventListener('change', function () {
                reportsPerPage = parseInt(this.value);
                currentPage = 1;
                renderReports();
                renderPagination();
            });
        }

        // بارگذاری آمار
        async function loadStats() {
            try {
                const response = await fetch('../api/reports/stats.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                const data = await response.json();
                if (data.success) {
                    document.getElementById('totalReports').textContent = toFa(data.stats.total || 0);
                    document.getElementById('todayReports').textContent = toFa(data.stats.today || 0);
                    document.getElementById('weekReports').textContent = toFa(data.stats.week || 0);
                    document.getElementById('monthReports').textContent = toFa(data.stats.month || 0);
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // بارگذاری گزارش‌ها
        async function loadReports() {
            try {
                console.log('=== شروع بارگذاری گزارش‌ها ===');
                console.log('Token:', authToken);
                console.log('URL:', window.location.origin + '/../api/reports/list.php');

                showLoading();
                const response = await fetch('../api/reports/list.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                console.log('Status:', response.status);
                console.log('OK?', response.ok);

                const text = await response.text();
                console.log('Raw response:', text);

                const data = JSON.parse(text);
                console.log('Parsed data:', data);

                if (data.success) {
                    allReports = data.reports || [];
                    filteredReports = [...allReports];
                    renderReports();
                    renderPagination();
                } else {
                    console.error('API Error:', data);
                    showError(data.message || 'خطا در بارگذاری گزارش‌ها');
                }
            } catch (error) {
                console.error('Catch Error:', error);
                showError('خطا در ارتباط با سرور: ' + error.message);
            }
        }

        // جستجوی گزارش‌ها
        function searchReports() {
            const searchQuery = document.getElementById('searchInput').value.toLowerCase();
            const unitFilter = document.getElementById('unitFilter').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const reportCodeSearch = document.getElementById('reportCodeSearch').value.toLowerCase();
            const sortBy = document.getElementById('sortBy').value;
            const sortOrder = document.getElementById('sortOrder').value;

            // فیلتر کردن
            filteredReports = allReports.filter(report => {
                // جستجو در متن
                const matchesSearch = !searchQuery ||
                    report.content.toLowerCase().includes(searchQuery);

                // فیلتر واحد
                const matchesUnit = !unitFilter || report.activity_unit === unitFilter;

                // فیلتر کد گزارش
                const matchesCode = !reportCodeSearch ||
                    report.unique_code.toLowerCase().includes(reportCodeSearch);

                // فیلتر تاریخ
                let matchesDate = true;
                if (dateFrom || dateTo) {
                    const reportDate = report.report_date;
                    if (dateFrom) {
                        const fromDate = convertPersianToGregorian(dateFrom);
                        matchesDate = matchesDate && reportDate >= fromDate;
                    }
                    if (dateTo) {
                        const toDate = convertPersianToGregorian(dateTo);
                        matchesDate = matchesDate && reportDate <= toDate;
                    }
                }

                return matchesSearch && matchesUnit && matchesCode && matchesDate;
            });

            // مرتب‌سازی
            filteredReports.sort((a, b) => {
                let aValue = a[sortBy];
                let bValue = b[sortBy];

                if (sortOrder === 'desc') {
                    return aValue > bValue ? -1 : 1;
                } else {
                    return aValue < bValue ? -1 : 1;
                }
            });

            currentPage = 1;
            renderReports();
            renderPagination();
        }

        // نمایش گزارش‌ها
        function renderReports() {
            const container = document.getElementById('reportsContainer');

            if (filteredReports.length === 0) {
                container.innerHTML = `

        <div class="row justify-content-center">
            <div class="col-md-6 text-center">

                    <div class="card-body py-5">
                        <i class="bi bi-file-text display-5 text-muted mb-4"></i>
                        <h5 class="card-title mb-3">گزارشی یافت نشد</h5>
                        <p class="card-text text-muted mb-4">
                            برای این فیلترها گزارشی وجود ندارد یا هنوز گزارشی ارسال نکرده‌اید.
                        </p>
                        <a href="daily-report.php" class="btn btn-primary">
                            <i class="bi bi-plus ms-2"></i>ارسال گزارش جدید
                        </a>
                    </div>

            </div>
        </div>

                `;
                document.getElementById('paginationContainer').style.display = 'none';
                return;
            }

            // محاسبه صفحه‌بندی
            const startIndex = (currentPage - 1) * reportsPerPage;
            const endIndex = startIndex + reportsPerPage;
            const reportsToShow = filteredReports.slice(startIndex, endIndex);

            let html = '';
            reportsToShow.forEach(report => {
                const searchQuery = document.getElementById('searchInput').value.toLowerCase();
                const contentPreview = report.content || report.content_preview || '';
                const highlightedContent = highlightSearchText(
                    contentPreview.substring(0, 4096),
                    searchQuery
                );

                html += `
                    <div class="report-card" onclick="viewReportDetail('${report.unique_code}')">
                        <div class="card-body">
                            <div class="report-header">
                                <div>
                                    <span class="report-code">${report.unique_code}</span>
                                    <span class="unit-badge unit-${esc(report.activity_unit)}">${esc(report.activity_unit)}</span>
                                </div>
                                <div class="report-date">
                                    <i class="bi bi-calendar me-1"></i>
                                    ${formatPersianDate(report.report_date)}
                                </div>
                            </div>
                            
                            <div class="report-content">
${highlightedContent}${contentPreview.length > 4096 ? '...' : ''}
                            </div>
                            
                            <div class="report-actions" onclick="event.stopPropagation()">
                                <button class="btn btn-primary btn-sm" onclick="viewReportDetail('${report.unique_code}')">
                                    <i class="bi bi-eye ms-2"></i>مشاهده کامل
                                </button>
                                <button class="btn btn-secondary btn-sm" onclick="copyReport('${report.unique_code}')">
                                    <i class="bi bi-clipboard ms-2"></i>کپی
                                </button>
                                <button class="btn btn-success btn-sm" onclick="shareReportTelegram('${report.unique_code}')">
                                    <i class="bi bi-chat-left-dots ms-2"></i>گروه گزارشات
                                </button>
                            </div>
                            
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    ایجاد شده: ${formatPersianDateTime(report.created_at)}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            document.getElementById('paginationContainer').style.display = filteredReports.length > reportsPerPage ? 'block' : 'none';
        }
        function showNewTaskModal() {
            window.location.href = 'create-task.php';
        }
        // نمایش صفحه‌بندی
        function renderPagination() {
            const totalPages = Math.ceil(filteredReports.length / reportsPerPage);
            const paginationList = document.getElementById('paginationList');

            if (totalPages <= 1) {
                paginationList.innerHTML = '';
                return;
            }

            let html = '';

            // دکمه قبلی
            html += `
                <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${currentPage - 1})">قبلی</a>
                </li>
            `;

            // صفحات
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    html += `
                        <li class="page-item ${i === currentPage ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="changePage(${i})">${toFa(i)}</a>
                        </li>
                    `;
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }

            // دکمه بعدی
            html += `
                <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${currentPage + 1})">بعدی</a>
                </li>
            `;

            paginationList.innerHTML = html;
        }

        // تغییر صفحه
        function changePage(page) {
            const totalPages = Math.ceil(filteredReports.length / reportsPerPage);
            if (page < 1 || page > totalPages) return;

            currentPage = page;
            renderReports();
            renderPagination();

            // اسکرول به بالا
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // مشاهده جزئیات گزارش
        function viewReportDetail(reportCode) {
            const report = allReports.find(r => r.unique_code === reportCode);
            if (!report) return;

            currentReportData = report;

            document.getElementById('modalReportCode').textContent = report.unique_code;
            document.getElementById('modalReportDate').textContent = formatPersianDate(report.report_date);
            document.getElementById('modalActivityUnit').innerHTML = `<span class="unit-badge unit-${esc(report.activity_unit)}">${esc(report.activity_unit)}</span>`;
            document.getElementById('modalCreatedAt').textContent = formatPersianDateTime(report.created_at);
            document.getElementById('modalReportContent').textContent = report.content_preview;

            const modal = new bootstrap.Modal(document.getElementById('reportModal'));
            modal.show();
        }

        // کپی گزارش مشخص
        function copyReport(reportCode) {
            const report = allReports.find(r => r.unique_code === reportCode);
            if (!report) return;

            const textToCopy = `گزارش ${report.unique_code}\nتاریخ: ${formatPersianDate(report.report_date)}\nواحد: ${report.activity_unit}\n\n${report.content_preview}`;

            // تلاش برای استفاده از Clipboard API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    showAlert('گزارش کپی شد', 'success');
                }).catch(err => {
                    console.error('خطا در کپی با Clipboard API:', err);
                    fallbackCopyText(textToCopy);
                });
            } else {
                // استفاده از روش جایگزین
                fallbackCopyText(textToCopy);
            }
        }
        function fallbackCopyText(text) {
            try {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed'; // جلوگیری از اسکرول به پایین صفحه
                textArea.style.opacity = '0'; // مخفی کردن textarea
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();

                const successful = document.execCommand('copy');
                document.body.removeChild(textArea);

                if (successful) {
                    showAlert('گزارش کپی شد', 'success');
                } else {
                    showAlert('خطا در کپی کردن', 'error');
                }
            } catch (err) {
                console.error('خطا در کپی جایگزین:', err);
                showAlert('خطا در کپی کردن', 'error');
            }
        }
        // ارسال به سروش
        function shareReportSoroush(reportCode) {
            const report = allReports.find(r => r.unique_code === reportCode);
            if (!report) return;

            const text = encodeURIComponent(`گزارش ${report.unique_code}\nتاریخ: ${formatPersianDate(report.report_date)}\nواحد: ${report.activity_unit}\n\n${report.content}`);

            const souroushURL = 'https://web.splus.ir/#-' + currentUser.report_group_unit_code;

            window.open(souroushURL, '_blank');
        }

        // دانلود PDF
        function downloadReportPDF(reportCode) {
            const report = allReports.find(r => r.unique_code === reportCode);
            if (!report) return;

            // ایجاد محتوای PDF (ساده)
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>گزارش ${report.unique_code}</title>
                    <style>
                        body { font-family: Arial, sans-serif; direction: rtl; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .content { line-height: 1.8; white-space: pre-wrap; }
                        .meta { margin: 20px 0; padding: 10px; background: #f5f5f5; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h2>گزارش ${report.unique_code}</h2>
                    </div>
                    <div class="meta">
                        <p><strong>تاریخ گزارش:</strong> ${formatPersianDate(report.report_date)}</p>
                        <p><strong>واحد فعالیت:</strong> ${report.activity_unit}</p>
                        <p><strong>تاریخ ایجاد:</strong> ${formatPersianDateTime(report.created_at)}</p>
                    </div>
                    <div class="content">
                        ${report.content}
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // صادرات گزارش‌ها
        function exportReports(format) {
            if (filteredReports.length === 0) {
                showAlert('گزارشی برای صادرات وجود ندارد', 'warning');
                return;
            }

            if (format === 'excel') {
                exportToExcel();
            } else if (format === 'pdf') {
                exportToPDF();
            }
        }

        // صادرات به اکسل
        function exportToExcel() {
            let csvContent = "کد گزارش,تاریخ گزارش,واحد فعالیت,محتوا,تاریخ ایجاد\n";

            filteredReports.forEach(report => {
                const row = [
                    report.unique_code,
                    formatPersianDate(report.report_date),
                    report.activity_unit,
                    `"${report.content.replace(/"/g, '""')}"`,
                    formatPersianDateTime(report.created_at)
                ].join(',');
                csvContent += row + "\n";
            });

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `گزارش‌ها_${new Date().getTime()}.csv`;
            link.click();
        }

        // صادرات به PDF
        function exportToPDF() {
            const printWindow = window.open('', '_blank');
            let html = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>گزارش‌های ارسال شده</title>
                    <style>
                        body { font-family: Arial, sans-serif; direction: rtl; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .report { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; }
                        .report-header { background: #f5f5f5; padding: 10px; margin: -15px -15px 15px -15px; }
                        .content { line-height: 1.6; white-space: pre-wrap; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>گزارش‌های ارسال شده</h1>
                        <p>تعداد: ${toFa(filteredReports.length)} گزارش</p>
                    </div>
            `;

            filteredReports.forEach(report => {
                html += `
                    <div class="report">
                        <div class="report-header">
                            <strong>کد گزارش:</strong> ${report.unique_code} |
                            <strong>تاریخ:</strong> ${formatPersianDate(report.report_date)} |
                            <strong>واحد:</strong> ${report.activity_unit}
                        </div>
                        <div class="content">${report.content}</div>
                    </div>
                `;
            });

            html += '</body></html>';

            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.print();
        }

        // نمایش/مخفی کردن جستجوی پیشرفته
        function toggleAdvancedSearch() {
            const advancedDiv = document.getElementById('advancedSearch');
            const icon = document.getElementById('advancedIcon');

            if (advancedDiv.style.display === 'none') {
                advancedDiv.style.display = 'block';
                icon.className = 'bi bi-chevron-up me-1';
            } else {
                advancedDiv.style.display = 'none';
                icon.className = 'bi bi-chevron-down me-1';
            }
        }

        // توابع کمکی
        function highlightSearchText(text, searchQuery) {
            const safeText = esc(text);
            if (!searchQuery || searchQuery.length < 2) return safeText;

            const regex = new RegExp(`(${esc(searchQuery)})`, 'gi');
            return safeText.replace(regex, '<span class="search-highlight">$1</span>');
        }

        function formatPersianDate(dateString) {
            if (!dateString) return 'نامشخص';

            try {
                const date = new persianDate(new Date(dateString));
                return date.format('DD MMMM YYYY');
            } catch {
                return dateString;
            }
        }

        function formatPersianDateTime(dateTimeString) {
            if (!dateTimeString) return 'نامشخص';

            try {
                const date = new persianDate(new Date(dateTimeString));
                return date.format('DD MMMM YYYY - HH:mm');
            } catch {
                return dateTimeString;
            }
        }

        function convertPersianToGregorian(persianDate) {
            try {
                const pDate = new persianDate(persianDate);
                return pDate.toDate().toISOString().split('T')[0];
            } catch {
                return persianDate;
            }
        }

        function showLoading() {
            document.getElementById('reportsContainer').innerHTML = `
                <div class="loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">در حال بارگذاری...</span>
                    </div>
                    <p class="mt-3">در حال بارگذاری گزارش‌ها...</p>
                </div>
            `;
        }

        // showError از showInlineError مشترک (assets/js/alert.js) استفاده می‌کنه
        function showError(message) {
            showInlineError('reportsContainer', message, { onRetry: loadReports });
        }

        // showAlert قبلاً یک پیاده‌سازیِ جداگانه (باکسِ alert بوت‌استرپ) داشت؛
        // الان فقط یک نام‌مستعارِ نازک برایِ showToastِ مشترکه (از assets/js/alert.js)
        function showAlert(message, type = 'info') {
            showToast(message, type);
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function logout() {
            uiConfirm('آیا مطمئن هستید که می‌خواهید خارج شوید؟', function () {
                fetch('../api/auth/logout.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                })
                    .finally(() => {
                        localStorage.removeItem('auth_token');
                        localStorage.removeItem('user_info');
                        window.location.href = '../index.php';
                    });
            }, { danger: true, yesText: 'بله، خروج', noText: 'انصراف' });
        }
    </script>
</body>

</html>