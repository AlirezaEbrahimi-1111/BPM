<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
// چک سمت سرور — قبل از هر چیز
if (empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/version.php';

// اگر organization_name در session نیست، از دیتابیس بخوان
if (!isset($_SESSION['organization_name'])) {
    try {
        $stmt = $db->query("SELECT value FROM settings WHERE `key` = 'organization_name' LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $_SESSION['organization_name'] = $row['value'];
        }
    } catch (Exception $e) {
        // اگر خطا داشت، مقدار پیش‌فرض
        $_SESSION['organization_name'] = 'سیستم مدیریت';
    }
}
// تعیین مسیر base به صورت خودکار
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . "://" . $host . dirname($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - سیستم مدیریت کار</title>

    <!-- Bootstrap 5 RTL -->
    <link href="<?= asset('../assets/js/cdn/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">

    <!-- فونت فارسی -->
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>" rel="stylesheet">
    <!-- تقویم شمسی -->
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/persian-datepicker.min.css') ?>">
    <script src="<?= asset('../assets/js/jalali.js') ?>"></script>
    <!-- Moment.js -->
    <script src="<?= asset('../assets/js/cdn/moment.min.js') ?>"></script>
    <!-- Moment Jalaali -->
    <script src="<?= asset('../assets/js/cdn/moment-jalaali.js') ?>"></script>
    <script src="<?= asset('../assets/js/config.js') ?>"></script>

    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <!-- Bootstrap JS -->
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/intro.min.js') ?>"></script>

    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/introjs.min.css') ?>">

    <!-- jQuery -->
    <script src="<?= asset('../assets/js/cdn/jquery-3.6.0.min.js') ?>"></script>

    <!-- تقویم شمسی -->
    <script src="<?= asset('../assets/js/cdn/persian-date.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/persian-datepicker.min.js') ?>"></script>

    <!-- Chart.js -->
    <script src="<?= asset('../assets/js/cdn/chart.js') ?>"></script>
    <script src="<?= asset('../assets/js/dashboard-improvements.js') ?>"></script>
    <script src="<?= asset('../assets/js/delegated-tasks-improvements.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <!-- <link rel="stylesheet" href="<?= asset('../../assets/css/responsive/dashboard-responsive.css') ?>"> -->
    <link rel="stylesheet" href="<?= asset('../assets/css/deadline-toast.css') ?>">


</head>

<body>

    <?php include 'header.php'; ?>

    <!-- ============ استایل بهینه‌سازی داشبورد (scoped: #dashboardModern) ============ -->
    <style>
        @import url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css');

        #dashboardModern {
            --dm-bg: #f3f4f8;
            --dm-surface: #ffffff;
            --dm-ink: #1e2233;
            --dm-muted: #7a8194;
            --dm-line: #e9ebf2;
            --dm-brand: #6c4ac9;
            --dm-brand-2: #8a63e6;
            --dm-shadow: 0 1px 2px rgba(24, 28, 46, .04), 0 8px 24px rgba(24, 28, 46, .06);
            --dm-shadow-hover: 0 6px 16px rgba(24, 28, 46, .10), 0 16px 40px rgba(24, 28, 46, .10);
            --dm-radius: 18px;
            font-family: 'Vazirmatn', 'IRANSans', 'Vazir', system-ui, -apple-system, sans-serif;
            color: var(--dm-ink);
            padding-top: 1.25rem;
            padding-bottom: 2.5rem;
        }

        /* ---------- هیرو: خوش‌آمد + آمار ---------- */
        #dashboardModern .dash-hero {
            display: grid;
            grid-template-columns: 1.4fr 2.6fr;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        #dashboardModern .dash-welcome {
            position: relative;
            overflow: hidden;
            border-radius: var(--dm-radius);
            padding: 1.6rem 1.5rem;
            color: #fff;
            background: linear-gradient(135deg, #d5c2ff, #7228ff 100%);
            box-shadow: var(--dm-shadow);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        #dashboardModern .dash-welcome__glow {
            position: absolute;
            inset: auto -40px -60px auto;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(255, 255, 255, .25), transparent 70%);
            pointer-events: none;
        }

        #dashboardModern .dash-welcome__title {
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0 0 .35rem;
            line-height: 1.5;
            color: white;
        }

        #dashboardModern .dash-welcome__sub {
            margin: 0;
            opacity: .92;
            font-size: .95rem;
        }

        #dashboardModern .dash-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        #dashboardModern .stat-card {
            background: var(--dm-surface);
            border: 1px solid var(--dm-line);
            border-radius: var(--dm-radius);
            padding: 1.1rem 1rem;
            box-shadow: var(--dm-shadow);
            display: flex;
            align-items: center;
            gap: .85rem;
            transition: transform .18s ease, box-shadow .18s ease;
            position: relative;
            overflow: hidden;
        }

        #dashboardModern .stat-card::before {
            content: "";
            position: absolute;
            inset-inline-start: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--c, var(--dm-brand));
        }

        #dashboardModern .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--dm-shadow-hover);
        }

        #dashboardModern .stat-card__icon {
            flex: 0 0 auto;
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 1.4rem;
            background: color-mix(in srgb, var(--c, var(--dm-brand)) 30%, #fff);
            color: var(--c, var(--dm-brand));
        }

        #dashboardModern .stat-card__body {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        #dashboardModern .stat-number {
            font-size: 1.7rem;
            font-weight: 800;
            margin: 0;
            color: var(--dm-ink);
        }

        #dashboardModern .stat-label {
            margin: .15rem 0 0;
            color: var(--dm-muted);
            font-size: .85rem;
        }

        #dashboardModern .stat-card--total {
            --c: #6c4ac9;
        }

        #dashboardModern .stat-card--done {
            --c: #14a06b;
        }

        #dashboardModern .stat-card--over {
            --c: #e0683a;
        }

        #dashboardModern .stat-card--soon {
            --c: #2f7be0;
        }

        /* ---------- پنل‌ها ---------- */
        #dashboardModern .dash-card {
            background: var(--dm-surface);
            border: 1px solid var(--dm-line);
            border-radius: var(--dm-radius);
            box-shadow: var(--dm-shadow);
            overflow: hidden;
            height: 100%;
        }

        #dashboardModern .dash-card .card-header {
            background: transparent;
            border-bottom: 1px solid var(--dm-line);
            padding: 1rem 1.1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        #dashboardModern .dash-card .card-header h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: var(--dm-ink);
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        #dashboardModern .dash-card .card-header h5 i {
            color: var(--dm-brand);
            font-size: 1.15rem;
        }

        #dashboardModern .dash-link {
            font-size: .82rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--dm-brand);
            background: color-mix(in srgb, var(--dm-brand) 10%, #fff);
            padding: .35rem .7rem;
            border-radius: 999px;
            transition: background .15s ease;
        }

        #dashboardModern .dash-link:hover {
            background: color-mix(in srgb, var(--dm-brand) 20%, #fff);
        }

        #dashboardModern .dash-card .card-body {
            padding: 1rem 1.1rem;
        }

        /* ---------- دکمه‌های فیلتر ---------- */
        #dashboardModern .filter-buttons {
            display: flex;
            gap: .4rem;
            margin-bottom: .9rem;
            flex-wrap: wrap;
        }

        #dashboardModern .filter-btn {
            border: 1px solid var(--dm-line);
            background: #fff;
            color: var(--dm-muted);
            padding: .35rem .85rem;
            border-radius: 999px;
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s ease;
        }

        #dashboardModern .filter-btn:hover {
            border-color: var(--dm-brand);
            color: var(--dm-brand);
        }

        #dashboardModern .filter-btn.active {
            background: var(--dm-brand);
            border-color: var(--dm-brand);
            color: #fff;
            box-shadow: 0 4px 12px color-mix(in srgb, var(--dm-brand) 35%, transparent);
        }

        /* ---------- ناحیهٔ اسکرول ---------- */
        #dashboardModern .recent-activities-scroll {
            max-height: 420px;
            overflow-y: auto;
            padding-inline-end: .25rem;
        }

        #dashboardModern .recent-activities-scroll::-webkit-scrollbar {
            width: 7px;
        }

        #dashboardModern .recent-activities-scroll::-webkit-scrollbar-thumb {
            background: #d6dae6;
            border-radius: 999px;
        }

        #dashboardModern .recent-activities-scroll::-webkit-scrollbar-thumb:hover {
            background: #bcc2d4;
        }

        #dashboardModern .loading {
            display: grid;
            place-items: center;
            padding: 2.5rem 0;
        }

        #dashboardModern .loading .spinner-border {
            color: var(--dm-brand);
        }

        /* ---------- ریسپانسیو ---------- */
        @media (max-width: 992px) {
            #dashboardModern .dash-hero {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            #dashboardModern .dash-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: .7rem;
            }

            #dashboardModern .stat-card {
                padding: .9rem .8rem;
                gap: .6rem;
            }

            #dashboardModern .stat-card__icon {
                width: 42px;
                height: 42px;
                font-size: 1.2rem;
            }

            #dashboardModern .stat-number {
                font-size: 1.4rem;
            }

            #dashboardModern .dash-welcome {
                padding: 1.3rem 1.2rem;
            }

            #dashboardModern .dash-welcome__title {
                font-size: 1.25rem;
            }
        }
    </style>
    <!-- ناوبری بالا -->
    <div class="container-fluid">
        <div class="row">
            <!-- سایدبار -->

            <!-- محتوای اصلی -->
            <main class="col-lg-9 ms-sm-auto main-content" id="dashboardModern">

                <!-- هیرو: خوش‌آمد + کارت‌های آماری -->
                <div class="dash-hero">
                    <div class="dash-welcome">
                        <div class="dash-welcome__glow"></div>
                        <h2 class="dash-welcome__title">خوش آمدید، <span id="welcomeName">کاربر گرامی</span></h2>
                        <p class="dash-welcome__sub" id="welcomeMessage">امروز <span id="currentDate"></span></p>
                    </div>

                    <div class="dash-stats">
                        <div class="stat-card stat-card--total">
                            <div class="stat-card__icon"><i class="bi bi-list-task"></i></div>
                            <div class="stat-card__body">
                                <h3 class="stat-number" id="totalTasks">0</h3>
                                <p class="stat-label">کل کارها</p>
                            </div>
                        </div>
                        <div class="stat-card stat-card--done">
                            <div class="stat-card__icon"><i class="bi bi-check-circle"></i></div>
                            <div class="stat-card__body">
                                <h3 class="stat-number" id="completedTasks">0</h3>
                                <p class="stat-label">انجام شده</p>
                            </div>
                        </div>
                        <div class="stat-card stat-card--over">
                            <div class="stat-card__icon"><i class="bi bi-exclamation-triangle"></i></div>
                            <div class="stat-card__body">
                                <h3 class="stat-number" id="overdueTasks">0</h3>
                                <p class="stat-label">عقب افتاده</p>
                            </div>
                        </div>
                        <div class="stat-card stat-card--soon">
                            <div class="stat-card__icon"><i class="bi bi-calendar-day"></i></div>
                            <div class="stat-card__body">
                                <h3 class="stat-number" id="todayTasks">0</h3>
                                <p class="stat-label">به زودی</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- پنل‌ها -->
                <div class="row g-3 dash-panels">
                    <!-- کارهای من -->
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card dash-card">
                            <div class="card-header">
                                <h5><i class="bi bi-person-check"></i><span>کارهای من</span></h5>
                                <a href="my-tasks.php" class="dash-link">مشاهده همه</a>
                            </div>
                            <div class="card-body">
                                <div class="filter-buttons">
                                    <button class="filter-btn active" onclick="filterMyTasks('all',event)">همه</button>
                                    <button class="filter-btn" onclick="filterMyTasks('today',event)">امروز</button>
                                    <button class="filter-btn" onclick="filterMyTasks('overdue',event)">عقب افتاده</button>
                                </div>
                                <div id="myTasksList" class="recent-activities-scroll">
                                    <div class="loading">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">در حال بارگذاری...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- کارهای واگذار شده -->
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card dash-card">
                            <div class="card-header">
                                <h5><i class="bi bi-arrow-right-circle"></i><span>کارهای واگذار شده</span></h5>
                                <a href="delegated-tasks.php" class="dash-link">مشاهده همه</a>
                            </div>
                            <div class="card-body">
                                <div id="delegatedTasksList" class="recent-activities-scroll">
                                    <div class="loading">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">در حال بارگذاری...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- فعالیت‌های اخیر -->
                    <div class="col-12 col-lg-4">
                        <div class="card dash-card">
                            <div class="card-header">
                                <h5><i class="bi bi-clock-history"></i><span>فعالیت‌های اخیر</span></h5>
                            </div>
                            <div class="card-body">
                                <div id="recentActivities" class="recent-activities-scroll">
                                    <div class="loading">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">در حال بارگذاری...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <!-- بروزرسانی دکمه‌های سریع -->
    <div class="quick-actions">
        <!-- window.location.href='create-task.php' -->
        <button class="fab" onclick="showNewTaskModal()" title="کار جدید">
            <i class="bi bi-plus"></i>
        </button>
    </div>
    <script src="../../assets/js/table-utils.js"></script>
    <script src="<?= asset('/assets/js/undo-toast.js') ?>"></script>
    <script>
        let currentUser = null;
        // let authToken = null;
        let myTasksData = [];
        let delegatedTasksData = [];
        let allTasks = [];

        // ============================================
        // 🎨 CSS برای بهبودهای جدید
        // ============================================
        const improvementStyles = `
/* Search Box */
.input-group {
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.input-group .form-control {
    border: 1px solid #e8eaed;
    padding: 10px 14px;
    font-size: 14px;
}

.input-group .form-control:focus {
    border-color: #667eea;
    box-shadow: none;
}

/* Task Item بهبود شده */
.task-item {
    position: relative;
    transition: all 0.3s ease;
        width: 100% !important;
    overflow: visible !important;
    box-sizing: border-box !important;
}

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.task-title-section {
    flex: 1;
    overflow: visible !important;
    width: 100% !important;
}
.task-title {
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow: visible !important;
    text-overflow: clip !important;
    line-height: 1.5 !important;
    max-width: 100% !important;
    width: 100% !important;
    display: block !important;
}

/* غیرفعال کردن ellipsis در کلاس‌های موجود */
.task-title,
[class*="task-title"],
.recent-activities-scroll .task-title {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
}
.task-type-icon {
    font-size: 1.2em;
    margin-left: 6px;
}

.task-assignee {
    font-size: 13px;
    color: #5f6368;
    margin-top: 6px;
}

.task-assignee strong {
    color: #1a1a1a;
    font-weight: 600;
}

.task-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}

.task-badges .badge {
    font-size: 12px;
}

.task-actions {
    display: flex;
    gap: 6px;
}

/* Empty State بهبود شده */
.empty-state-improved {
    text-align: center;
    padding: 60px 20px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(102,126,234,0.05), rgba(147,52,230,0.05));
    border: 2px dashed #e8eaed;
}

.empty-state-improved .empty-icon {
    font-size: 4rem;
    color: #667eea;
    opacity: 0.8;
}

.empty-state-improved h5 {
    color: #1a1a1a;
    font-weight: 600;
    margin-top: 16px;
}

.empty-state-improved p {
    color: #5f6368;
    margin-top: 8px;
}

/* Progress Bar */
.progress {
    background: rgba(255,255,255,0.3);
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    background: rgba(255,255,255,0.8);
    transition: width 0.3s ease;
}

/* Countdown Badge Colors */
.badge-danger {
    background: linear-gradient(135deg, #ea4335, #c5221f) !important;
}

.badge-warning {
    background: linear-gradient(135deg, #fbbc04, #ea8600) !important;
}

.badge-success {
    background: linear-gradient(135deg, #34a853, #137333) !important;
}

.badge-info {
    background: linear-gradient(135deg, #4285f4, #1967d2) !important;
}
`;
        // بارگذاری اولیه
        document.addEventListener('DOMContentLoaded', function() {
            checkAuth();
            // authToken = localStorage.getItem('auth_token');

            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }


            initializePage();
        });


        // بررسی احراز هویت
        function checkAuth() {
            // دریافت اطلاعات کاربر
            fetch('../api/auth/profile.php', {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Unauthorized');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        sessionStorage.removeItem('auth_bounce'); // 🆕 این یک خط را اضافه کن
                        currentUser = data.user;
                        updateUserInfo();
                    } else {
                        throw new Error('Invalid response');
                    }
                })
                .catch(error => {
                    console.error('Auth error:', error);
                    localStorage.removeItem('auth_token');
                    localStorage.removeItem('user_info');
                    window.location.href = '../index.php';
                });
        }
        // ارسال یادآوری
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

                    if (data.success) {
                        showAlert('یادآوری با موفقیت ارسال شد', 'success');
                    } else {
                        showAlert(data.message || 'خطا در ارسال یادآوری', 'danger');
                    }
                } catch (error) {
                    console.error('Error sending reminder:', error);
                    showAlert('خطا در ارتباط با سرور', 'danger');
                }
            }, {
                placeholder: 'پیام یادآوری...',
                required: true,
                okText: 'ارسال'
            });
        }
        // بروزرسانی اطلاعات کاربر
        function updateUserInfo() {
            const userName = currentUser.first_name && currentUser.last_name ?
                `${currentUser.first_name} ${currentUser.last_name}` :
                'کاربر گرامی';

            document.getElementById('welcomeName').textContent = userName;
            // تاریخ امروز
            const persianDatevar = new persianDate();

            // گرفتن دقیق سال/ماه/روز
            const year = persianDatevar.year();
            const month = persianDatevar.month();
            const day = persianDatevar.date() - 1;

            // ساخت دوباره تاریخ با همین مقادیر
            const syncedDate = new persianDate([year, month, day]);

            let my_day = enTofaNumber(String(day + 1));

            // نمایش در صفحه
            document.getElementById("currentDate").textContent = ": " + persianDatevar.format("dddd") + " " + my_day + " " + persianDatevar.format("MMMM") + " " + persianDatevar.format("YYYY");
            // checkRoutineAccess();


        }

        function enTofaNumber(numb) {
            const persianNumbers = "۰۱۲۳۴۵۶۷۸۹";
            const englishNumbers = "0123456789";
            return String(numb).replace(/[0-9]/g, d => persianNumbers[englishNumbers.indexOf(d)]);
        }
        // راه‌اندازی اولیه صفحه
        function initializePage() {
            setupPersianDatePicker();
            loadDashboardData();

        }
        async function loadDashboardData() {
            try {
                await Promise.allSettled([
                    loadStats(),
                    loadMyTasks(),
                    loadDelegatedTasks(),
                    loadRecentActivities(),
                    loadAnnouncements()
                ]);
                initializeImprovements();
            } catch (error) {
                console.error('Error:', error);
            }
        }
        // ============================================
        // 🚀 راه‌اندازی بهبودهای جدید
        // ============================================
        function initializeImprovements() {
            // اضافه کردن Styles
            const styleTag = document.createElement('style');
            styleTag.innerHTML = improvementStyles;
            document.head.appendChild(styleTag);

            // اضافه کردن UI Elements
            addSearchBox();

            // به‌روزرسانی Stats
            updateProgressStats();
        }

        function searchMyTasks(query) {
            const searchTerm = normalizeDigits(query).toLowerCase().trim();

            let filtered = myTasksData.filter(task =>
                !['completed', 'approved'].includes(task.status)
            );

            if (searchTerm) {
                filtered = filtered.filter(task => {
                    const otherText = (task.title || '') + ' ' + (task.description || '') + ' ' + task.id;
                    task._checklistOnlyMatch = isChecklistOnlyMatch(otherText, task.checklist_titles || '', searchTerm);
                    return matchesAllWords(otherText + ' ' + (task.checklist_titles || ''), searchTerm);
                });
            } else {
                filtered.forEach(task => {
                    task._checklistOnlyMatch = false;
                });
            }

            renderMyTasks(filtered);
        }
        // تابع addSearchBox فعلی را با این جایگزین کنید:
        function addSearchBox() {
            const myTasksCard = document.querySelector('[id="myTasksList"]').closest('.card');
            if (!myTasksCard) return;

            if (!document.getElementById('myTaskSearchBox')) {
                const searchHTML = `
            <div id="myTaskSearchBox" style="margin-bottom:1rem;">
                <div class="dtask-search-row">
                    <i class="bi bi-search"></i>
                    <input type="text" placeholder="جستجو در عنوان، توضیحات یا شناسه..."
                        oninput="searchMyTasks(this.value)">
                </div>
            </div>
        `;
                const cardBody = myTasksCard.querySelector('.card-body');
                const filterButtons = cardBody.querySelector('.filter-buttons');
                if (filterButtons) {
                    filterButtons.insertAdjacentHTML('afterend', searchHTML);
                } else {
                    cardBody.insertAdjacentHTML('afterbegin', searchHTML);
                }
            }
        }
        // بارگذاری آمار
        async function loadStats() {

            try {
                const response = await fetch('../api/tasks/stats.php', {
                    method: 'POST',

                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                const data = await response.json();

                if (data.success) {

                    document.getElementById('totalTasks').textContent = String(enTofaNumber(data.stats.total)) || 0;
                    document.getElementById('completedTasks').textContent = String(enTofaNumber(data.stats.completed)) || 0;
                    document.getElementById('overdueTasks').textContent = String(enTofaNumber(data.stats.overdue)) || 0;
                    document.getElementById('todayTasks').textContent = String(enTofaNumber(0)); // data.stats.today || 0;
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }
        // بارگذاری کارهای من
        // ============================================
        // 🔧 تابع loadMyTasks اصلاح شده
        // محل قرارگیری: dashboard.php - خط 1215
        // ============================================

        async function loadMyTasks() {
            try {
                // چک کردن توکن
                if (!authToken) {
                    window.location.href = '../index.php';
                    return;
                }

                const response = await fetch('../api/tasks/my-tasks.php', {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (response.status === 401) {
                    localStorage.removeItem('auth_token');
                    window.location.href = '../index.php';
                    return;
                }

                const text = await response.text();
                const data = JSON.parse(text);
                const today = todayLocal();
                if (data.success) {
                    myTasksData = data.data?.tasks || data.tasks || [];
                    filteredTasks = myTasksData.filter(task => {
                        // ✅ حذف کارهای تکمیل شده و تأیید شده
                        if (task.status === 'completed' || task.status === 'approved') {
                            return false;
                        }
                        return true;
                    });
                    renderMyTasks(filteredTasks);
                } else {
                    document.getElementById('myTasksList').innerHTML =
                        '<div class="empty-state"><i class="bi bi-exclamation-circle ms-2"></i><p>' + data.message + '</p></div>';
                }
            } catch (error) {
                document.getElementById('myTasksList').innerHTML =
                    '<div class="empty-state"><i class="bi bi-exclamation-circle ms-2"></i><p>خطا در بارگذاری کارها</p></div>';
            }
        }
        // نمایش کارهای من

        // ============================================
        // 🎛️ UI: Sort Buttons
        // ============================================
        function updateProgressStats() {
            const progressPercent = calculateProgressPercentage();

            // اگر کارت Progress موجود است، به‌روزرسانی کنید
            const progressCard = document.getElementById('progressCard');
            if (progressCard) {
                progressCard.innerHTML = `
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea, #5568d3);">
                <h3 class="stat-number">${progressPercent}%</h3>
                <p class="stat-label">تکمیل شده</p>
                <div class="progress mt-3" style="height: 8px;">
                    <div class="progress-bar" style="width: ${progressPercent}%;"></div>
                </div>
                <i class="bi bi-percent position-absolute"
                    style="font-size: 3rem; opacity: 0.2; top: 10px; left: 15px;"></i>
            </div>
        `;
            }
        }


        // بارگذاری کارهای واگذار شده
        async function loadDelegatedTasks() {
            try {
                const response = await fetch('../api/tasks/delegated-tasks.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                const data = await response.json();
                if (data.success) {
                    delegatedTasksData = data.data?.tasks || data.tasks || [];

                    // ✅ حذف کارهای تکمیل‌شده، تأییدشده و درخواست اتمام
                    const visibleTasks = delegatedTasksData.filter(task =>
                        !['completed', 'approved', 'termination_requested'].includes(task.status)
                    );

                    renderDelegatedTasks(visibleTasks); // ← visibleTasks نه delegatedTasksData

                    // 🆕 اضافه کنید:
                    initializeDelegatedImprovements();
                }
            } catch (error) {
                console.error('Error loading delegated tasks:', error);
                document.getElementById('delegatedTasksList').innerHTML =
                    '<div class="empty-state"><i class="bi bi-exclamation-circle"></i><p>خطا در بارگذاری کارها</p></div>';
            }
        }
        // نمایش کارهای واگذار شده
        // کپی کامل تابع جدید از delegated-tasks-improvements-FIXED.js
        function renderDelegatedTasks(tasks) {
            const container = document.getElementById('delegatedTasksList');

            if (tasks.length === 0) {
                container.innerHTML = `
            <div class="empty-state-improved">
                <div class="empty-icon">
                    <i class="bi bi-arrow-right-circle"></i>
                </div>
                <h5 class="mt-3">هیچ کاری واگذار نکرده‌اید! 🎯</h5>
                <p class="text-muted">کارهای جدید ایجاد کنید و به تیمتان واگذار کنید</p>
                <button class="btn btn-primary mt-3" onclick="showNewTaskModal()">
                    <i class="bi bi-plus-circle me-2"></i>ایجاد کار جدید
                </button>
            </div>
        `;
                return;
            }

            let html = '';
            tasks.slice(0, 50).forEach(task => {
                const taskClass = getTaskClass(task);
                const priorityBadge = getPriorityBadge(task.priority);
                // ✅ چک: آیا درخواست pending دارد و user creator است؟
                let statusBadge = '';
                if (task.has_pending_deadline_request == 1 && currentUser && (currentUser.id == task.creator_id || currentUser.id == task.current_approver_id)) {
                    statusBadge = `
                <span class="badge" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white;">
                    <i class="bi bi-hourglass-split me-1"></i>
                    در انتظار تأیید مهلت
                </span>
    `;
                } else {
                    statusBadge = getStatusBadge(task);
                }
                // نوع کار
                const taskTypeIcon = task.task_type === 'continuous' ?
                    '🔄' :
                    task.task_type === 'periodic' ?
                    '📅' :
                    '⚙️';

                // Countdown
                const countdown = calculateDelegatedCountdown(task.original_deadline || task.due_date);
                const countdownBadge = countdown ?
                    `<span class="badge ${countdown.class}">${countdown.text}</span>` :
                    '';

                const continuousBadge = task.task_type === 'continuous' && task.overdue_periods > 0 ?
                    `<span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"> </i>${task.overdue_periods} دوره معوقه</span>` :
                    '';

                // نام مسئول انجام
                const assigneeName = task.assignee_name ||
                    task.department_name ||
                    task.assignee_department ||
                    task.section_name ||
                    (task.assignee_type === 'department' ? 'بخش نامشخص' : 'نامشخص');

                html += `
            <div class="task-item ${taskClass}" onclick="viewTask(${task.id})">
                <div class="task-header">
                    <div class="task-title-section">
                        <div class="task-title">
                            <span class="task-type-icon" title="${task.task_type}">${taskTypeIcon}</span>
                                ${task.title}${checklistMatchBadge(task)}
                        </div>
                        <div class="task-assignee">
                            <i class="bi bi-person-circle me-1"></i>
                            واگذار به: <strong>${assigneeName}</strong>
                        </div>
                    </div>
                    <div class="task-actions">
                        <button class="btn btn-sm btn-remind-overview" 
                            onclick="event.stopPropagation(); sendReminder(${task.id}, '${task.title}', '${assigneeName}')" 
                            title="ارسال یادآوری">
                            <i class="bi bi-bell"></i>
                        </button>
                    </div>
                </div>
                
                <div class="task-badges">
                    ${priorityBadge}
                    ${statusBadge}
                    ${continuousBadge}
                    ${countdownBadge}
                </div>
            </div>
        `;
            });
            container.innerHTML = html;
        }
        // ============================================
        // 🔍 جستجو در کارهای واگذار شده
        // ============================================
        function searchDelegatedTasks(query) {
            const searchTerm = query.toLowerCase().trim();

            let filtered = delegatedTasksData.filter(task =>
                !['completed', 'approved', 'termination_requested'].includes(task.status)
            );

            if (searchTerm) {
                filtered = filtered.filter(task => {
                    const otherText = (task.title || '') + ' ' + (task.description || '') + ' ' + (task.assignee_name || '') + ' ' + task.id;
                    task._checklistOnlyMatch = isChecklistOnlyMatch(otherText, task.checklist_titles || '', searchTerm);
                    return matchesAllWords(otherText + ' ' + (task.checklist_titles || ''), searchTerm);
                });
            } else {
                filtered.forEach(task => {
                    task._checklistOnlyMatch = false;
                });
            }

            renderDelegatedTasks(filtered);
        }
        // ⏳ بج زمان باقی‌مانده برای کارهای روتین (با دقت ساعت/دقیقه)
        function workflowTimeBadge(task) {
            // مبنا: موعدِ مرحلهٔ فعال (deadline با ساعت دقیق)
            const raw = task.deadline || task.due_date || task.original_deadline;
            if (!raw) return '';

            // پشتیبانی از "YYYY-MM-DD HH:MM:SS" و "YYYY-MM-DD"
            const due = new Date(raw.replace(' ', 'T'));
            if (isNaN(due.getTime())) return '';

            const diffMin = Math.round((due - new Date()) / 60000); // دقیقه (مثبت=مانده، منفی=گذشته)
            const hourglass = '<i class="bi bi-hourglass-split me-1"></i>';

            // مهلت گذشته
            if (diffMin < 0) {
                const m = Math.abs(diffMin);
                const txt = m >= 1440 ? `${Math.floor(m / 1440)} روز تأخیر` :
                    m >= 60 ? `${Math.floor(m / 60)} ساعت تأخیر` :
                    `${m} دقیقه تأخیر`;
                return `<span class="badge bg-danger">${hourglass}${enTofaNumber(txt)}</span>`;
            }

            // بیشتر از ۲۴ ساعت → روز
            if (diffMin >= 1440) {
                const d = Math.floor(diffMin / 1440);
                return `<span class="badge badge-secondary">${hourglass}${enTofaNumber(d + ' روز مانده')}</span>`;
            }
            // بین ۱ تا ۲۴ ساعت → ساعت (سبز/زرد)
            if (diffMin >= 60) {
                const h = Math.floor(diffMin / 60);
                const cls = diffMin <= 180 ? 'badge-warning' : 'badge-success'; // ≤۳ساعت زرد
                return `<span class="badge ${cls}">${hourglass}${enTofaNumber(h + ' ساعت مانده')}</span>`;
            }
            // کمتر از ۱ ساعت → دقیقه (قرمز، فوری)
            return `<span class="badge bg-danger">${hourglass}${enTofaNumber(diffMin + ' دقیقه مانده')}</span>`;
        }
        // ============================================
        // ⏱️ Countdown برای کارهای واگذار شده
        // ============================================
        function calculateDelegatedCountdown(dueDate) {
            if (!dueDate) return null;

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const due = new Date(dueDate);
            due.setHours(0, 0, 0, 0);

            const diffTime = due - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays === 0) return {
                text: 'امروز',
                class: 'badge-info'
            };
            if (diffDays === 1) return {
                text: '۱ روز مانده',
                class: 'badge-warning'
            };
            if (diffDays > 1 && diffDays <= 3) return {
                text: `${diffDays} روز مانده`,
                class: 'badge-warning'
            };
            if (diffDays > 3 && diffDays <= 7) return {
                text: `${diffDays} روز مانده`,
                class: 'badge-success'
            };
            if (diffDays < 0) return {
                text: `${Math.abs(diffDays)} روز تاخیر`,
                class: 'badge-danger'
            };
            return {
                text: `${diffDays} روز مانده`,
                class: 'badge-secondary'
            };
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
        // ============================================
        // 🎛️ اضافه کردن جستجو برای کارهای واگذار
        // ============================================
        function addSearchBoxForDelegated() {
            const delegatedCard = document.querySelector('[id="delegatedTasksList"]').closest('.card');

            if (!delegatedCard) {
                console.warn('Delegated card not found');
                return;
            }

            if (!document.getElementById('delegatedTaskSearchBox')) {
                const searchHTML = `
    <div id="delegatedTaskSearchBox" style="margin-bottom:1rem;">
        <div class="dtask-search-row">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="جستجو در عنوان، توضیحات یا شناسه..."
                oninput="searchDelegatedTasks(this.value)">
        </div>
    </div>
`;

                const delegatedBody = delegatedCard.querySelector('.card-body');
                delegatedBody.insertAdjacentHTML('afterbegin', searchHTML);
            }
        }

        // ============================================
        // 🚀 راه‌اندازی بهبودهای کارهای واگذار
        // ============================================
        function initializeDelegatedImprovements() {
            const delegatedStyles = `
    .dtask-search-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 7px;
        border-bottom: 1px solid #ddd;
        transition: border-color .15s;
    }
    .dtask-search-row:focus-within { border-bottom-color: #555; }
    .dtask-search-row i {
        font-size: 15px;
        color: #aaa;
        flex-shrink: 0;
        transition: color .15s;
    }
    .dtask-search-row:focus-within i { color: #666; }
    .dtask-search-row input {
        border: none;
        outline: none;
        background: transparent;
        font-size: 14px;
        width: 100%;
        direction: rtl;
    }
    .dtask-search-hint {
        margin-top: 5px;
        font-size: 11px;
        color: #aaa;
        direction: rtl;
    }
    .btn-remind-overview {
        opacity: 0;
        transition: opacity 0.2s ease, transform 0.2s ease;
        position: absolute !important;
        right: auto !important;
        left: 88% !important;
    }
    .task-item:hover .btn-remind-overview { opacity: 1; }
    .btn-remind-overview:hover { transform: scale(1.1); }
`;

            // اضافه کردن Styles (اگر قبلا اضافه نشده باشد)
            if (!document.getElementById('delegatedStyles')) {
                const styleTag = document.createElement('style');
                styleTag.id = 'delegatedStyles';
                styleTag.innerHTML = delegatedStyles;
                document.head.appendChild(styleTag);
            }

            // اضافه کردن جستجو
            addSearchBoxForDelegated();
        }
        // بارگذاری فعالیت‌های اخیر
        async function loadRecentActivities() {
            try {
                const response = await fetch('../api/tasks/recent-activities.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                const data = await response.json();

                if (data.success) {
                    renderRecentActivities(data.activities || []);
                }
            } catch (error) {
                console.error('Error:', error);
                // نمایش پیام خالی
                const container = document.getElementById('recentActivities');
                if (container) {
                    container.innerHTML = '<p class="text-muted">فعالیتی ثبت نشده است</p>';
                }
            }
        }
        // نمایش فعالیت‌های اخیر
        // ============================================
        // 🕐 فعالیت‌های اخیر — نسخه بهبود یافته
        // ============================================

        let allActivities = [];
        let activitiesDisplayed = 10;
        let currentActivityFilter = 'all';
        window.addEventListener("pageshow", function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        // رندر اصلی
        function renderRecentActivities(activities) {
            const container = document.getElementById('recentActivities');
            allActivities = activities;
            activitiesDisplayed = 10;
            currentActivityFilter = 'all';

            if (!activities || activities.length === 0) {
                container.innerHTML = `
                    <div class="act-empty">
                        <i class="bi bi-clock-history"></i>
                        <p>هیچ فعالیتی ثبت نشده است</p>
                    </div>`;
                return;
            }

            let html = '';

            // فیلترها
            html += `
                <div class="act-filters">
                    <button class="act-filter-btn active" onclick="filterActivities('all', this)">همه</button>
                    <button class="act-filter-btn" onclick="filterActivities('created', this)">ایجاد</button>
                    <button class="act-filter-btn" onclick="filterActivities('completed', this)">تکمیل</button>
                    <button class="act-filter-btn" onclick="filterActivities('assigned', this)">واگذاری</button>
                    <button class="act-filter-btn" onclick="filterActivities('approved', this)">تأیید</button>
                </div>`;

            // لیست گروه‌بندی شده
            html += `<div id="activitiesList">${buildGroupedActivities(activities, activitiesDisplayed)}</div>`;

            // بارگذاری بیشتر
            if (activities.length > activitiesDisplayed) {
                const remaining = activities.length - activitiesDisplayed;
                html += `
                    <div class="act-load-more" id="actLoadMore">
                        <button onclick="loadMoreActivities()">
                            <i class="bi bi-chevron-down"></i>
                            ${enTofaNumber(remaining)} مورد دیگر
                        </button>
                    </div>`;
            }

            container.innerHTML = html;
        }

        function normalizeDigits(str) {
            return str
                .replace(/[۰-۹]/g, d => d.charCodeAt(0) - 1776)
                .replace(/[٠-٩]/g, d => d.charCodeAt(0) - 1632);
        }

        function buildActivityItems(activities, limit) {
            let html = '';
            const items = activities.slice(0, limit);

            items.forEach((activity, index) => {
                const actionInfo = getActivityInfo(activity.action);
                const timeAgo = getSmartTimeAgo(activity.created_at);
                const userName = activity.user_name || activity.performed_by_name || '';
                const taskTitle = activity.task_title || '';
                const taskId = activity.task_id || null;
                const priority = activity.priority || '';
                const notes = activity.notes || '';

                // ساخت لینک کار
                const taskLink = taskId ?
                    `<a class="task-link" href="task-detail.php?id=${taskId}" onclick="event.stopPropagation();">${taskTitle}</a>` :
                    taskTitle;

                // ساخت متن توضیحات
                let notesHtml = '';
                if (notes) {
                    notesHtml = `<div class="activity-task-title" style="font-style:italic; color:#94a3b8; margin-top:2px;">«${truncateText(notes, 60)}»</div>`;
                }

                html += `
            <div class="activity-item" ${taskId ? `onclick="viewTask(${taskId})"` : ''} data-action="${activity.action}">
                <div class="activity-icon-wrapper ${activity.action}">
                    <i class="bi bi-${actionInfo.icon}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-action-text">${actionInfo.text}</div>
                    <div class="activity-task-title">${taskLink}</div>
                    ${notesHtml}
                    <div class="activity-meta">
                        ${userName ? `
                            <span class="activity-user">
                                <i class="bi bi-person-fill"></i>
                                ${userName}
                            </span>
                            <span class="activity-dot"></span>
                        ` : ''}
                        <span class="activity-time">${timeAgo}</span>
                    </div>
                </div>
            </div>
        `;
            });

            return html;
        }

        // فیلتر
        function filterActivities(action, btnEl) {
            document.querySelectorAll('.act-filter-btn').forEach(b => b.classList.remove('active'));
            if (btnEl) btnEl.classList.add('active');

            currentActivityFilter = action;
            activitiesDisplayed = 10;

            const filtered = action === 'all' ?
                allActivities :
                allActivities.filter(a => a.action === action);

            const listEl = document.getElementById('activitiesList');
            if (filtered.length === 0) {
                listEl.innerHTML = `
                    <div class="act-empty" style="padding: 1.5rem;">
                        <i class="bi bi-funnel"></i>
                        <p>فعالیتی با این فیلتر یافت نشد</p>
                    </div>`;
            } else {
                listEl.innerHTML = buildGroupedActivities(filtered, activitiesDisplayed);
            }

            // بروزرسانی دکمه بیشتر
            const loadMoreEl = document.getElementById('actLoadMore');
            if (loadMoreEl) {
                if (filtered.length > activitiesDisplayed) {
                    const r = filtered.length - activitiesDisplayed;
                    loadMoreEl.style.display = 'block';
                    loadMoreEl.innerHTML = `<button onclick="loadMoreActivities()"><i class="bi bi-chevron-down"></i> ${enTofaNumber(r)} مورد دیگر</button>`;
                } else {
                    loadMoreEl.style.display = 'none';
                }
            }
        }

        // بارگذاری بیشتر
        function loadMoreActivities() {
            activitiesDisplayed += 10;

            const filtered = currentActivityFilter === 'all' ?
                allActivities :
                allActivities.filter(a => a.action === currentActivityFilter);

            document.getElementById('activitiesList').innerHTML =
                buildGroupedActivities(filtered, activitiesDisplayed);

            const loadMoreEl = document.getElementById('actLoadMore');
            if (loadMoreEl) {
                if (filtered.length > activitiesDisplayed) {
                    const r = filtered.length - activitiesDisplayed;
                    loadMoreEl.innerHTML = `<button onclick="loadMoreActivities()"><i class="bi bi-chevron-down"></i> ${enTofaNumber(r)} مورد دیگر</button>`;
                } else {
                    loadMoreEl.style.display = 'none';
                }
            }
        }

        // اطلاعات هر نوع عملیات
        function getActivityInfo(action) {
            const info = {
                'created': {
                    icon: 'plus-circle-fill',
                    text: 'کار جدید ایجاد شد'
                },
                'assigned': {
                    icon: 'arrow-right-circle-fill',
                    text: 'کار واگذار شد'
                },
                'completed': {
                    icon: 'check-circle-fill',
                    text: 'کار تکمیل شد'
                },
                'approved': {
                    icon: 'patch-check-fill',
                    text: 'کار تأیید شد'
                },
                'rejected': {
                    icon: 'x-circle-fill',
                    text: 'کار رد شد'
                },
                'stopped': {
                    icon: 'pause-circle-fill',
                    text: 'کار متوقف شد'
                },
                'delegated': {
                    icon: 'share-fill',
                    text: 'کار ارجاع داده شد'
                },
                'commented': {
                    icon: 'chat-dots-fill',
                    text: 'نظر جدید ثبت شد'
                },
                'reminder': {
                    icon: 'bell-fill',
                    text: 'یادآوری ارسال شد'
                },
                'status_changed': {
                    icon: 'arrow-repeat',
                    text: 'وضعیت تغییر کرد'
                },
                'deadline_extended': {
                    icon: 'calendar-plus',
                    text: 'مهلت تمدید شد'
                },
                'pending_approval': {
                    icon: 'hourglass-split',
                    text: 'در انتظار تأیید'
                },
                'termination_requested': {
                    icon: 'exclamation-diamond-fill',
                    text: 'درخواست اتمام'
                },
            };

            return info[action] || {
                icon: 'circle-fill',
                text: action
            };
        }
        // اطلاعات هر نوع عملیات
        function getActInfo(action) {
            const map = {
                'created': {
                    template: 'کار {task} <v>ایجاد شد</v>'
                },
                'assigned': {
                    template: 'کار {task} به {user} <v>واگذار شد</v>'
                },
                'completed': {
                    template: 'کار {task} <v>تکمیل شد</v>'
                },
                'approved': {
                    template: 'کار {task} <v>تأیید شد</v>'
                },
                'rejected': {
                    template: 'کار {task} <v>رد شد</v>'
                },
                'stopped': {
                    template: 'کار {task} توسط {user} <v>متوقف شد</v>'
                },
                'delegated': {
                    template: 'کار {task} <v>ارجاع شد</v>'
                },
                'commented': {
                    template: 'روی {task} <v>نظر داد</v>'
                },
                'reminder': {
                    template: '<v>یادآوری</v> برای {task} به {user} ارسال شد'
                },
                'status_changed': {
                    template: 'وضعیت {task} توسط {user} <v>تغییر کرد</v>'
                },
                'deadline_extended': {
                    template: 'مهلت {task} توسط {user} <v>تمدید شد</v>'
                },
                'pending_approval': {
                    template: 'کار {task} <v>در انتظار تأیید</v> {user}'
                },
                'termination_requested': {
                    template: '<v>درخواست اتمام</v> {task} ثبت شد'
                },
            };
            return map[action] || {
                template: 'عملیاتی روی {task} انجام شد'
            };
        }
        // زمان هوشمند (دقیق‌تر)
        function getSmartTimeAgo(dateString) {
            if (!dateString) return '';

            const now = new Date();
            const date = new Date(dateString);
            const diffMs = now - date;
            const diffSec = Math.floor(diffMs / 1000);
            const diffMin = Math.floor(diffSec / 60);
            const diffHour = Math.floor(diffMin / 60);
            const diffDay = Math.floor(diffHour / 24);

            if (diffSec < 60) return 'همین الان';
            if (diffMin < 60) return enTofaNumber(diffMin) + ' دقیقه پیش';
            if (diffHour < 24) return enTofaNumber(diffHour) + ' ساعت پیش';
            if (diffDay === 1) return 'دیروز';
            if (diffDay === 2) return 'پریروز';
            if (diffDay < 7) return enTofaNumber(diffDay) + ' روز پیش';
            if (diffDay < 30) return enTofaNumber(Math.floor(diffDay / 7)) + ' هفته پیش';
            if (diffDay < 365) return enTofaNumber(Math.floor(diffDay / 30)) + ' ماه پیش';

            return formatPersianDate(dateString.split('T')[0] || dateString.split(' ')[0]);
        }
        // تعیین گروه زمانی
        function getTimeGroup(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const yesterdayStart = new Date(todayStart - 86400000);
            const weekStart = new Date(todayStart - 6 * 86400000);

            if (date >= todayStart) return 'امروز';
            if (date >= yesterdayStart) return 'دیروز';
            if (date >= weekStart) return 'این هفته';
            return 'قبل‌تر';
        }

        // ساخت HTML یک آیتم
        function buildActItem(activity) {
            const info = getActInfo(activity.action);
            const user = activity.user_name || activity.performed_by_name || 'کاربر';
            const task = activity.task_title || 'بدون عنوان';
            const taskId = activity.task_id || null;
            const time = getSmartTimeAgo(activity.created_at);

            const clickAttr = taskId ? `onclick="viewTask(${taskId})"` : '';

            // ساخت جمله از template
            const sentence = info.template
                .replace('{user}', `<span class="act-user">${user}</span>`)
                .replace('{task}', `<span class="act-task">«${task}»</span>`)
                .replace(/<v>/g, '<span >')
                .replace(/<\/v>/g, '</span>');

            return `
                <div class="act-item" ${clickAttr} data-action="${activity.action}">
                    <span class="act-dot ${activity.action}"></span>
                    <div class="act-body">
                        <div class="act-text">${sentence}</div>
                        <div class="act-time">${time}</div>
                    </div>
                </div>`;
        }

        // ساخت لیست گروه‌بندی شده
        function buildGroupedActivities(activities, limit) {
            const items = activities.slice(0, limit);
            const groups = {};
            const groupOrder = ['امروز', 'دیروز', 'این هفته', 'قبل‌تر'];

            items.forEach(act => {
                const group = getTimeGroup(act.created_at);
                if (!groups[group]) groups[group] = [];
                groups[group].push(act);
            });

            let html = '';
            groupOrder.forEach(groupName => {
                if (!groups[groupName] || groups[groupName].length === 0) return;

                html += `
                    <div class="act-group">
                        <div class="act-group-header">
                            <span class="act-group-label">${groupName}</span>
                            <span class="act-group-line"></span>
                        </div>
                        ${groups[groupName].map(buildActItem).join('')}
                    </div>`;
            });

            return html;
        }
        // بریدن متن طولانی
        function truncateText(text, maxLen) {
            if (!text) return '';
            if (text.length <= maxLen) return text;
            return text.substring(0, maxLen) + '...';
        }
        // رسم نمودار عملکرد
        function drawPerformanceChart() {
            const ctx = document.getElementById('performanceChart').getContext('2d');

            // داده‌های نمونه - در حالت واقعی از API دریافت می‌شود
            const data = {
                labels: ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'],
                datasets: [{
                    label: 'کارهای انجام شده',
                    data: [12, 19, 3, 5, 2, 3, 7],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }, {
                    label: 'کارهای ایجاد شده',
                    data: [2, 3, 20, 5, 1, 4, 8],
                    borderColor: '#764ba2',
                    backgroundColor: 'rgba(118, 75, 162, 0.1)',
                    tension: 0.4
                }]
            };

            new Chart(ctx, {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function getEffectiveDate(task) {
            const dates = [task.due_date, task.deadline, task.original_deadline]
                .filter(d => d)
                .map(d => d.split(' ')[0]);
            return dates.length > 0 ? dates.sort().pop() : null;
        }

        function filterMyTasks(filter, e) {
            // بروزرسانی دکمه‌های فیلتر
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            if (e) e.target.classList.add('active');

            const today = todayLocal();
            let filteredTasks = myTasksData;

            switch (filter) {
                case 'all':
                    filteredTasks = myTasksData.filter(task => {
                        // ✅ حذف کارهای تکمیل شده و تأیید شده
                        if (task.status === 'completed' || task.status === 'approved') {
                            return false;
                        }
                        return true;
                    });
                    break;

                case 'today':
                    filteredTasks = myTasksData.filter(task => {

                        if (task.status === 'completed' || task.status === 'approved') {
                            return false;
                        }
                        // 🆕 منتظرِ تأییدِ انجام توسط کاربر — بدونِ توجه به موعد/تاریخِ pending
                        if (task.is_pending_approval == 1 &&
                            currentUser &&
                            (currentUser.id == task.creator_id || currentUser.id == task.current_approver_id)) return true;
                        // 🆕 منتظرِ تأییدِ تمدیدِ موعد توسط کاربر — بدونِ توجه به موعد
                        if (task.has_pending_deadline_request == 1 &&
                            currentUser &&
                            currentUser.id == task.current_approver_id) return true;
                        if (task.is_workflow_task == 1 &&
                            (task.status === 'in_progress' || task.status === 'not_started')) {
                            return true;
                        }



                        if (task.task_type === 'periodic') {
                            const originalDate = task.original_deadline ? task.original_deadline.split(' ')[0] : '';
                            const dueDate = task.due_date || '';
                            const deadlineDate = task.deadline ? task.deadline.split(' ')[0] : ''; // ← اضافه شد

                            // پیدا کردن بزرگترین تاریخ
                            const dates = [originalDate, dueDate, deadlineDate].filter(d => d);
                            const maxDate = dates.length > 0 ? dates.sort().reverse()[0] : '';

                            return maxDate && maxDate <= today;
                        }

                        // کارهای دوره‌ای امروز
                        if (task.task_type === 'continuous' && task.overdue_periods > 0 && (task.last_approved_date === null || task.last_approved_date !== today)) return true;

                        return false;
                    });
                    break;

                case 'overdue':
                    filteredTasks = myTasksData.filter(task => {
                        if (task.status === 'completed' || task.status === 'approved') {
                            return false;
                        }

                        // ✅ pending approval (تأییدِ انجام) فقط اگر قبل از امروز منتظر شده
                        const isPendingMyApproval = task.is_pending_approval == 1 &&
                            currentUser &&
                            (currentUser.id == task.creator_id || currentUser.id == task.current_approver_id) &&
                            task.last_pending_date &&
                            task.last_pending_date.split(' ')[0] < today;

                        // 🆕 تمدیدِ موعد: اگر درخواست قبل از امروز ثبت شده و هنوز منتظرِ تأییدِ کاربر است
                        const isPendingDeadlineOverdue = task.has_pending_deadline_request == 1 &&
                            currentUser &&
                            currentUser.id == task.current_approver_id &&
                            task.deadline_request_date &&
                            task.deadline_request_date.split(' ')[0] < today;

                        // ✅ اصلاح: workflow tasks — مقایسه رشته‌ای درست
                        if (task.is_workflow_task == 1) {
                            const d1 = task.original_deadline ? task.original_deadline.split(' ')[0] : '';
                            const d2 = task.due_date || '';
                            const maxDate = [d1, d2].filter(d => d).sort().reverse()[0] || '';
                            if (maxDate && maxDate < today) return true;
                        }

                        // ✅ اصلاح: periodic tasks — مقایسه رشته‌ای تمیزتر
                        const isRegularOverdue = task.task_type === 'periodic' && (() => {
                            const d1 = task.deadline ? task.deadline.split(' ')[0] : '';
                            const d2 = task.due_date || '';
                            const maxDate = [d1, d2].filter(d => d).sort().reverse()[0] || '';
                            return maxDate && maxDate < today;
                        })();

                        // ✅ اصلاح: continuous — فقط اگه بیشتر از ۱ دوره عقب باشه
                        const isContinuousOverdue = task.task_type === 'continuous' &&
                            task.overdue_periods > 1;

                        return isRegularOverdue || isContinuousOverdue || isPendingMyApproval || isPendingDeadlineOverdue;
                    });
                    break;

                default:
                    filteredTasks = myTasksData.filter(task => task.status === filter);
            }

            renderMyTasks(filteredTasks);
        }

        function renderMyTasks(tasks) {
            const container = document.getElementById('myTasksList');

            if (tasks.length === 0) {
                container.innerHTML = `
            <div class="empty-state-improved">
                <div class="empty-icon">
                    <i class="bi bi-inbox"></i>
                </div>
                <h5 class="mt-3">هیچ کاری وجود ندارد! 🎉</h5>
                <p class="text-muted">کارهای جدید ایجاد کنید یا منتظر واگذاری باشید</p>
            </div>
        `;
                return;
            }

            let html = '';
            tasks.slice(0, 50).forEach(task => {
                const taskClass = getTaskClass(task);
                const priorityBadge = getPriorityBadge(task.priority);

                // ✅ چک: آیا درخواست pending دارد و user creator است؟
                let statusBadge = '';
                if (task.has_pending_deadline_request == 1 && currentUser && (currentUser.id == task.creator_id || currentUser.id == task.current_approver_id)) {
                    statusBadge = `
                <span class="badge" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white;">
                    <i class="bi bi-hourglass-split me-1"></i>
                    در انتظار تأیید مهلت
                </span>
            `;
                } else {
                    statusBadge = getStatusBadge(task);
                }

                const taskTypeIcon = task.task_type === 'continuous' ?
                    '🔄' :
                    task.task_type === 'periodic' ?
                    '📅' :
                    '⚙️';

                const continuousBadge = task.task_type === 'continuous' && task.overdue_periods > 0 ?
                    `<span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"> </i>${task.overdue_periods} دوره معوقه</span>` :
                    '';

                // 🆕 محاسبه تاخیر برای کارهای مقطعی (periodic)
                // محاسبه تاخیر برای کارهای مقطعی (periodic)
                let periodicDelayBadge = '';
                if (task.task_type === 'periodic' &&
                    task.is_workflow_task != 1 && // 🆕 روتین‌ها بج زمانِ مخصوص خودشان را دارند
                    task.working_days_delayed > 0 &&
                    task.status !== 'completed' &&
                    task.status !== 'approved') {

                    periodicDelayBadge = enTofaNumber(
                        `<span class="badge bg-danger">
            <i class="bi bi-clock-history me-2"> </i>
            ${task.working_days_delayed} روز کاری تاخیر
         </span>`
                    );
                }

                // 🆕 محاسبه چند روز مانده (مثل کارهای واگذار شده)
                // محاسبه بزرگترین تاریخ از بین سه فیلد
                const allDates = [task.original_deadline, task.due_date, task.deadline]
                    .filter(d => d)
                    .map(d => d.split(' ')[0]);
                const effectiveDate = allDates.length > 0 ? allDates.sort().reverse()[0] : null;

                // 🆕 کارهای روتین: بج زمان باقی‌مانده با دقت ساعت/دقیقه (نه فقط روز)
                let myCountdownBadge = '';
                if (task.is_workflow_task == 1) {
                    myCountdownBadge = workflowTimeBadge(task);
                } else {
                    // فقط اگر تاخیر نداره، badge مانده نشون بده
                    const myCountdown = (periodicDelayBadge === '') ?
                        calculateDelegatedCountdown(effectiveDate) :
                        null;
                    myCountdownBadge = myCountdown ?
                        `<span class="badge ${myCountdown.class}">${enTofaNumber(myCountdown.text)}</span>` :
                        '';
                }
                html += `
    <div class="task-item ${taskClass}" onclick="viewTask(${task.id})">
        <div class="task-header">
            <div class="task-title-section">
                <div class="task-title">
                    <span class="task-type-icon">${taskTypeIcon}</span>
                    ${task.title}
                </div>
            </div>
        </div>
        
        <div class="task-badges">
            ${priorityBadge}
            ${statusBadge}
            ${continuousBadge}
            ${periodicDelayBadge}
            ${myCountdownBadge}
        </div>
    </div>
`;
            });

            container.innerHTML = html;
        }

        // ذخیره کار جدید
        async function saveTask() {
            const isWorkflow = document.getElementById('isWorkflowTask').checked;
            console.log(isWorkflow);
            if (isWorkflow) {
                await saveWorkflowTask();
            } else {
                await saveNewTask();
            }
        }
        // راه‌اندازی تقویم شمسی
        function setupPersianDatePicker() {
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

        // توابع کمکی
        function getTaskClass(task) {
            const today = todayLocal();

            if (task.status === 'completed') return 'task-completed';
            if (task.status === 'delegated') return 'task-delegated';
            if (task.due_date === today) return 'task-today';
            if (task.due_date && task.due_date < today) return 'task-overdue';

            return '';
        }

        function getPriorityBadge(priority) {
            const labels = {
                'high': 'بالا',
                'medium': 'متوسط',
                'low': 'پایین'
            };

            return `<span class="badge priority-${priority}">${labels[priority] || priority}</span>`;
        }

        function getStatusBadge(task) {
            if (task.is_Pending_Approval) {
                return '<span class="badge bg-warning">در انتظار تأیید</span>';
            }
            if (task.task_type === 'continuous' && task.status === 'completed') {
                return `<span class="badge status-${task.status}">${task.overdue_periods > 0 ? 'معوقه دارد' : 'تکمیل دوره‌ای'}</span>`;
            }
            const labels = {
                'not_started': 'شروع نشده',
                'in_progress': 'در حال انجام',
                'completed': 'انجام شد',
                'rejected': 'متوقف شد',
                'delegated': 'ارجاع شد',
                'approved': 'تأیید شده',
                'pending_approval': 'در انتظار تأیید',
                'termination_requested': 'در انتظار اتمام',
            };
            return `<span class="badge status-${task.status}">${labels[task.status] || task.status}</span>`;
        }

        function formatPersianDate(dateString) {
            if (!dateString) return 'دوره‌ای';

            try {
                // تجزیه رشته تاریخ به اجزای جداگانه
                const [year, month, day] = dateString.split('-').map(Number);

                // ساخت تاریخ با منطقه زمانی محلی
                const date = new persianDate(new Date(year, month - 1, day));
                return date.format(' D MMMM YYYY');
            } catch {
                return dateString;
            }
        }

        function getTimeAgo(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const diffInMinutes = Math.floor((now - date) / (1000 * 60));

            if (diffInMinutes < 60) return `${diffInMinutes} دقیقه پیش`;
            if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)} ساعت پیش`;
            temp_math = enTofaNumber(Math.floor(diffInMinutes / 1440));
            return `${temp_math} روز پیش`;
        }



        function viewTask(taskId) {
            window.location.href = `../pages/task-detail.php?id=${taskId}`;
        }

        function showAlert(message, type = 'info') {
            // ایجاد و نمایش alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.top = '90px';
            alertDiv.style.left = '20px';
            alertDiv.style.zIndex = '9999';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(alertDiv);

            // حذف خودکار بعد از 5 ثانیه
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 5000);
        }

        function showDailyReportModal() {
            window.location.href = 'daily-report.php';
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        function logout() {
            uiConfirm('آیا مطمئن هستید که می‌خواهید خارج شوید؟', function() {
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
            }, {
                danger: true,
                yesText: 'بله، خروج',
                noText: 'انصراف'
            });
        }

        // بستن سایدبار موبایل با کلیک خارج از آن
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleButton = document.querySelector('.navbar-toggler');

            if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
                if (!sidebar.contains(event.target) && !toggleButton.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        // بروزرسانی خودکار هر 5 دقیقه
        setInterval(() => {
            loadStats();
        }, 5 * 60 * 1000);

        // ذخیره کار (تشخیص نوع)
        async function saveTask() {
            const taskType = document.getElementById('taskTypeSelect').value;

            console.log('💾 ذخیره کار - نوع:', taskType);

            if (taskType === 'routine') {
                await saveRoutineTask();
            } else {
                await saveManualTask();
            }
        }
        async function saveWorkflowTask() {
            const templateId = document.getElementById('workflowTemplate').value;
            const title = document.getElementById('workflowTitle').value.trim();

            if (!templateId) {
                showAlert('لطفاً نوع کار روتین را انتخاب کنید', 'warning');
                return;
            }

            if (!title) {
                showAlert('عنوان کار الزامی است', 'warning');
                return;
            }

            try {
                const response = await fetch('../api/workflows/start-workflow.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({
                        template_id: parseInt(templateId),
                        title: title
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('کار روتین با موفقیت ایجاد شد', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('newTaskModal')).hide();

                    // پاک کردن فرم
                    document.getElementById('isWorkflowTask').checked = false;
                    document.getElementById('workflowTemplate').value = '';
                    document.getElementById('workflowTitle').value = '';
                    document.getElementById('workflowSection').style.display = 'none';
                    document.getElementById('normalTaskSection').style.display = 'block';
                    document.getElementById('workflowPreview').style.display = 'none';

                    // بروزرسانی داده‌ها
                    loadDashboardData();
                } else {
                    showAlert(data.message || 'خطا در ایجاد کار روتین', 'danger');
                }
            } catch (error) {
                console.error('Error creating workflow task:', error);
                showAlert('خطا در ارتباط با سرور', 'danger');
            }
        }
        // تابع کمکی برای تغییر نوع کار دستی
        function toggleManualTaskType() {
            const taskType = document.getElementById('taskType').value;
            const dueDateContainer = document.getElementById('dueDateContainer');
            const continuousContainer = document.getElementById('continuousContainer');

            if (taskType === 'continuous') {
                dueDateContainer.style.display = 'none';
                continuousContainer.style.display = 'block';
            } else {
                dueDateContainer.style.display = 'block';
                continuousContainer.style.display = 'none';
            }
        }
        // ذخیره کار دستی (کد قبلی شما)
        async function saveManualTask() {
            const title = document.getElementById('manualTaskTitle').value.trim();
            const description = document.getElementById('taskDescription').value.trim();
            const taskType = document.getElementById('taskType').value;
            const priority = document.getElementById('taskPriority').value;
            const dueDate = document.getElementById('taskDueDate').value || null;
            const startDate = document.getElementById('taskStartDate').value || null;
            const periodType = document.getElementById('taskPeriod').value || null;

            if (!title) {
                showAlert('عنوان کار الزامی است', 'danger');
                return;
            }

            const saveBtn = document.querySelector('#newTaskModal .btn-primary');
            const btnText = saveBtn.querySelector('.btn-text');
            const originalText = btnText.textContent;

            btnText.textContent = 'در حال ذخیره...';
            saveBtn.disabled = true;

            try {
                const apiUrl = getApiUrl('/tasks/create.php');
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({
                        title,
                        description,
                        task_type: taskType,
                        priority,
                        due_date: dueDate,
                        start_date: startDate,
                        period_type: periodType
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('کار جدید با موفقیت ایجاد شد', 'success');

                    const modal = bootstrap.Modal.getInstance(document.getElementById('newTaskModal'));
                    modal.hide();

                    document.getElementById('newTaskForm').reset();
                    loadDashboardData();
                } else {
                    showAlert(data.message || 'خطا در ایجاد کار', 'danger');
                }
            } catch (error) {
                console.error('Error saving task:', error);
                showAlert('خطا در ارتباط با سرور', 'danger');
            } finally {
                btnText.textContent = originalText;
                saveBtn.disabled = false;
            }
        }

        // نمایش مودال کار جدید
        // در <script> dashboard.php، بعد از تعریف توابع دیگر
        function showNewTaskModal() {
            window.location.href = 'create-task.php';
        }
    </script>

</body>

</html>