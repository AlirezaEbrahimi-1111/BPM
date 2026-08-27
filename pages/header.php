<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/version.php';
?>
<!-- 🌗 تم روشن/تاریک — اعمال فوری از localStorage، پیش از رندرِ هدر (جلوگیریِ فلاش) -->
<script>
    function bpmGetTheme() {
        try { return localStorage.getItem('bpm_theme') || 'light'; }
        catch (e) { return 'light'; }
    }
    function bpmApplyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        var icon  = document.getElementById('themeToggleIcon');
        var btn   = document.getElementById('themeToggleBtn');
        var label = document.getElementById('themeToggleLabel');
        if (icon)  icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        if (btn)   btn.title = theme === 'dark' ? 'تغییر به تم روشن' : 'تغییر به تم تاریک';
        if (label) label.textContent = theme === 'dark' ? 'حالت روشن' : 'حالت تاریک';
    }
    function toggleTheme() {
        var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem('bpm_theme', next); } catch (e) {}
        bpmApplyTheme(next);
    }
    bpmApplyTheme(bpmGetTheme());
</script>
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="stylesheet" href="<?= asset('/assets/css/custom.css') ?>">
<link rel="stylesheet" href="<?= asset('/assets/css/responsive/dashboard-responsive.css') ?>">
<!-- وزیرمتن - بهینه برای موبایل -->
<link rel="stylesheet" href="<?= asset('/assets/fonts/Vazirmatn-font-face.css') ?>">

<style>
    /* یکدست‌سازیِ بجِ زنگِ اعلان/مگافونِ اطلاعیه/چت: هر سه از حالا کلاسِ
       .notification-badge رو مشترک استفاده می‌کنن (تعریفِ اصلی در custom.css،
       سمتِ راست، سایزِ ۲۰px) — این بلاک قبلاً یک نسخهٔ دوم و ناقص از همون
       استایل بود که با !important روی بعضی از خاصیت‌ها (نه همه) با نسخهٔ
       custom.css قاطی می‌شد و باعثِ ناهماهنگیِ بجِ اطلاعیه (که کلاسِ جداگانهٔ
       announcement-badge داشت) می‌شد */
    .notification-badge.hidden {
        display: none !important;
    }

    /* آیکنِ پروفایل مثل بقیه‌ی آیکن‌های هدر (زنگ/مگافون) بدون فلشِ dropdown دیده شود */
    #profileDropdown.dropdown-toggle::after {
        display: none;
    }

    /* پروفایل، آخرین آیکنِ سمت چپِ هدر است؛ کلاس‌های start/end بوت‌استرپ باعث می‌شدند
       منو از لبهٔ چپِ صفحه بیرون بزند. اینجا صریحاً لبهٔ چپِ منو را به لبهٔ آیکن می‌چسبانیم
       تا منو فقط به سمت راست (داخلِ صفحه) باز شود، نه به چپ (بیرونِ صفحه) */
    #profileDropdownMenu {
        left: 0 !important;
        right: auto !important;
        margin: 0 !important;
    }

    /* نام و نام‌خانوادگیِ کاربرِ جاری، کنارِ نامِ شرکت — کمی ریزتر.
       عمداً خارجِ تگِ <a> (نه داخلش) قرار گرفته تا هاور/کلیک‌پذیریِ
       navbar-brand را به ارث نبرد — فقط متنِ ساده است. */
    #headerUserFullName {
        font-size: .8em;
        font-weight: 500;
        opacity: .85;
        white-space: nowrap;
        cursor: default;
    }

    .header-name-divider {
        margin: 0 !important;
    }

    /* 🆕 موبایل: نامِ کاربر کنارِ نامِ سازمان+همبرگر+آیکن‌ها تویِ یک ردیفِ
       باریک جا نمی‌شد و هدر رو بهم می‌ریخت. این‌جا کاملاً از نوارِ بالا
       حذف می‌شه؛ به‌جاش داخلِ منویِ پروفایل (پایین‌تر، #profileDropdownName)
       نشون داده می‌شه — دسکتاپ دست‌نخورده می‌مونه (نامِ کاربر همون‌جای همیشگی) */
    @media (max-width: 1399px) {

        #headerUserFullName,
        #headerNameDivider {
            display: none !important;
        }
    }

    /* نامِ کاربر داخلِ منویِ پروفایل — پیش‌فرض مخفی (دسکتاپ نیازی نداره،
       چون بالای هدر خودش نشون داده می‌شه)، فقط موبایل نمایش داده می‌شه */
    .profile-dropdown-name {
        display: none;
    }

    .profile-dropdown-name-divider {
        display: none;
    }

    @media (max-width: 1399px) {
        .profile-dropdown-name {
            display: flex !important;
            align-items: center;
            padding: .5rem 1rem;
            font-weight: 600;
            color: var(--text-strong, #1f2937);
        }

        .profile-dropdown-name-divider {
            display: block !important;
        }
    }

    /* ═══ سرچ سراسری — فلشِ زیرِ هدر + کادرِ کشویی ═══ */
    .gs-toggle {
        position: fixed;
        top: 4.2rem;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1020;
        width: 40px;
        height: 20px;
        background: #8549fd;
        border: 1px solid var(--border-soft, #e9e9e9);
        border-top: none;
        border-radius: 0 0 9px 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 10px rgba(0, 0, 0, .08);
        color: #ffffff;
        transition: background .15s;
    }

    .gs-toggle:hover {
        background: #9560ff;
    }

    .gs-toggle i {
        font-size: .9rem;
        transition: transform .2s ease;
    }

    .gs-toggle.open i {
        transform: rotate(180deg);
    }

    .gs-panel {
        position: fixed;
        top: calc(3.5rem + 14px);
        left: 50%;
        transform: translateX(-50%) translateY(-8px);
        width: 40%;
        z-index: 1010;
        background: rgba(255, 255, 255, .5);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border-radius: 14px;
        border: 1px solid rgba(255, 255, 255, .5);
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        box-shadow: none;
        transition: opacity .2s ease, transform .2s ease, visibility .2s, box-shadow .2s;
    }

    .gs-panel.open {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
        transform: translateX(-50%) translateY(0);
        box-shadow: 0 16px 36px rgba(0, 0, 0, .16);
    }

    .gs-panel-inner {
        padding: 14px 16px 16px;
    }

    .gs-search-box {
        position: relative;
        display: flex;
        align-items: center;
    }

    .gs-search-box i.bi-search {
        position: absolute;
        right: 14px;
        color: #9ca3af;
        pointer-events: none;
    }

    .gs-search-box input {
        width: 100%;
        border: 1.5px solid #e9e9e9;
        border-radius: 9px;
        padding: 10px 42px 10px 14px;
        font-size: .9rem;
        outline: none;
        transition: border-color .15s;
    }

    .gs-search-box input:focus {
        border-color: #8e57fe;
    }

    .gs-type-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }

    .gs-type-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid rgba(142, 87, 254, .3);
        border-radius: 9px;
        padding: 3px 10px;
        font-size: .72rem;
        font-weight: 600;
        color: #6b5a8a;
        background: rgba(255, 255, 255, .5);
        cursor: pointer;
        user-select: none;
        transition: all .15s;
    }

    .gs-type-chip.active {
        background: #8e57fe;
        border-color: #8e57fe;
        color: #fff;
    }

    .gs-results {
        margin-top: 6px;
        max-height: 55vh;
        overflow-y: auto;
    }

    .gs-result-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 8px;
        border-radius: 9px;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
    }

    .gs-result-item:hover {
        background: rgba(197, 168, 255, 0.12);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
    }

    .gs-result-icon {
        width: 32px;
        height: 32px;
        flex-shrink: 0;
        border-radius: 9px;
        background: rgba(142, 87, 254, 0.12);
        color: #8e57fe;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .95rem;
    }

    .gs-result-main {
        min-width: 0;
        flex: 1;
    }

    .gs-result-title {
        font-size: .85rem;
        font-weight: 600;
        color: #1f2937;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .gs-result-meta {
        font-size: .74rem;
        color: #9ca3af;
        margin-top: 2px;
        display: flex;
        gap: 6px;
        align-items: center;
    }

    .gs-result-type {
        color: #8e57fe;
        font-weight: 600;
    }

    .gs-result-snippet {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .gs-empty,
    .gs-hint {
        text-align: center;
        color: #9ca3af;
        font-size: .8rem;
        padding: 10px 0 4px;
    }

    @media (max-width: 768px) {
        .gs-panel {
            width: 92%;
        }

        .gs-panel-inner {
            padding: 12px 12px 16px;
        }
    }

    /* «خواندن همه» به‌شکلِ لینک، نه دکمه */
    .mark-all-link {
        background: none;
        border: none;
        padding: 0;
        font: inherit;
        font-size: 12px;
        font-weight: 600;
        color: var(--primary, #8e57fe);
        cursor: pointer;
        text-decoration: none;
    }

    .mark-all-link:hover {
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .mark-all-link:disabled {
        opacity: .4;
        cursor: default;
        pointer-events: none;
        text-decoration: none;
    }

    /* ورود/خروج — خوانایی روی هدرِ بنفش (override قواعدِ کم‌کنتراستِ custom.css) */
    #attendanceContainer .attendance-complete {
        background: rgba(255, 255, 255, 0.95);
        color: #059669;
    }

    #attendanceContainer .attendance-info-btn {
        background: rgba(255, 255, 255, 0.16);
        color: #fff;
    }

    #attendanceContainer .attendance-info-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        color: #fff;
    }
</style>
<!-- بستن فوری drawer قبل از render — جلوگیری از flash -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var d = document.getElementById('navbarNav');
        if (d) {
            d.classList.remove('drawer-open');
        }
        document.body.classList.remove('drawer-body-open');
        var b = document.getElementById('mobileMenuBtn');
        if (b) b.classList.remove('menu-btn-hidden');
        var o = document.getElementById('drawerOverlay');
        if (o) o.classList.remove('overlay-active');
    });
</script>
<!-- هدر ثابت -->
<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <div class="container-fluid">
        <!-- نام سایت در سمت راست -->
        <a class="navbar-brand ms-auto" href="../../pages/dashboard-manager.php">
            <span id="userName" class="me-2"><?php echo isset($_SESSION['organization_name']) ? htmlspecialchars($_SESSION['organization_name']) : 'کاربر جاری'; ?></span>
        </a>
        <span class="navbar-divider header-name-divider" id="headerNameDivider" style="display:none;"></span>
        <span id="headerUserFullName" style="display:none;"></span>
        <script>
            /* نام‌ونام‌خانوادگیِ کاربر از localStorage.user_info (نه سشنِ سرور) —
               چون این مقدار همون لحظه‌یِ لاگین پر می‌شه و نیازی به لاگینِ
               مجدد نداره (بر خلافِ $_SESSION که فقط موقعِ لاگین ست می‌شه) */
            (function () {
                try {
                    var u = JSON.parse(localStorage.getItem('user_info') || '{}');

                    // 🔒 نامِ سازمان: اگه localStorage.user_info مقدارش رو داشته
                    // باشه (لاگین‌هایِ جدید، بعدِ این اصلاح)، جایگزینِ همونی می‌شه
                    // که PHP از رویِ $_SESSION رندر کرده — چون $_SESSION زودتر از
                    // JWT منقضی می‌شه و رویِ سشن‌هایِ قدیمی، هدر «کاربر جاری»
                    // نشون می‌داد با اینکه کاربر هنوز (طبقِ JWT) لاگین بود
                    if (u.organization_name) {
                        var orgEl = document.getElementById('userName');
                        if (orgEl) orgEl.textContent = u.organization_name;
                    }

                    var full = ((u.first_name || '') + ' ' + (u.last_name || '')).trim();
                    if (!full) return;
                    var el = document.getElementById('headerUserFullName');
                    var div = document.getElementById('headerNameDivider');
                    if (el) {
                        el.textContent = full;
                        el.style.display = '';
                    }
                    if (div) div.style.display = '';

                    // 🆕 همون نام، داخلِ منویِ پروفایل هم — نمایشِ نهایی‌ش رو
                    // CSS (@media) تصمیم می‌گیره (فقط موبایل)
                    var ddText = document.getElementById('profileDropdownNameText');
                    if (ddText) ddText.textContent = full;
                } catch (e) {}
            })();
        </script>

        <!-- دکمه همبرگر سفارشی موبایل -->
        <button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="باز کردن منو">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>

        <!-- منوی ناوبری موبایل -->
        <div class="mobile-drawer" id="navbarNav">
            <div class="drawer-header">
                <span class="drawer-title">منو</span>
                <button class="drawer-close-btn" id="drawerCloseBtn" aria-label="بستن منو">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <ul class="navbar-nav" id="mainNav">
                <li class="nav-item">
                    <a class="nav-link" href="../../pages/dashboard-manager.php">
                        <i class="bi bi-house-door me-2"></i>داشبورد
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../pages/create-task.php">
                        <i class="bi bi-plus-circle me-2"></i>کار جدید
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../pages/tasks.php">
                        <i class="bi bi-list-task me-2"></i>مدیریت کارها
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../pages/my-tasks.php">
                        <i class="bi bi-person-check me-2"></i>کارهای من
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../pages/delegated-tasks.php">
                        <i class="bi bi-person-check me-2"></i>کارهای واگذارشده
                    </a>
                </li>
                <?php if ((int)($_SESSION['organization_id'] ?? 0) === 1): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../../attendance_system/pages/requests.php">
                            <i class="bi bi-file-text me-2"></i>ورود و خروج
                        </a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="../../pages/announcements.php">
                        <i class="bi bi-megaphone me-2"></i>اطلاعیه‌ها
                    </a>
                </li>
                <li class="nav-item" style="display:none;">
                    <a class="nav-link" href="../../pages/reports.php">
                        <i class="bi bi-file-text me-2"></i>گزارشات
                    </a>
                </li>
                <!-- منوی نظارت (فقط برای مدیران) -->
                <li class="nav-item dropdown" id="navOverview" style="display: none;">
                    <a class="nav-link dropdown-toggle" href="#" id="overviewDropdown" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-view-list me-2"></i>نظارت
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end admin-submenu" aria-labelledby="overviewDropdown">
                        <li id="overviewTasksItem">
                            <a class="dropdown-item" href="/pages/tasks-overview.php">
                                <i class="bi bi-list-check ms-2"></i>نظارت بر کارها
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="/pages/workflow-monitor.php">
                                <i class="bi-diagram-3 ms-2"></i>نظارت بر روتین‌های فعال
                            </a>
                        </li>
                        <li><a class="dropdown-item" href="../../attendance_system/pages/payroll-report.php"><i class="bi bi-cash-stack ms-2"></i>گزارش حقوق پرسنل</a></li>
                    </ul>
                </li>
                <!-- منوی مدیریت با زیرمنو (فقط برای مدیران) -->
                <li class="nav-item dropdown" id="fulladmintag" style="display: none;">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-shield-lock me-2"></i>مدیریت
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end admin-submenu" aria-labelledby="adminDropdown">
                        <!--<li>-->
                        <!--    <a class="dropdown-item" href="../../pages/sms-templates.php">-->
                        <!--        <i class="bi bi-chat-square-text ms-2"></i>الگوهای پیامک-->
                        <!--    </a>-->
                        <!--</li>-->
                        <!--<li>-->
                        <!--    <a class="dropdown-item" href="../../pages/sms-logs.php">-->
                        <!--        <i class="bi bi-graph-up ms-2"></i>گزارش پیامک‌ها-->
                        <!--    </a>-->
                        <!--</li>-->
                        <!--<li>-->
                        <!--    <hr class="dropdown-divider">-->
                        <!--</li>-->
                        <li>
                            <a class="dropdown-item" href="../../pages/users.php">
                                <i class="bi bi-people ms-2"></i>مدیریت کاربران
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="../../pages/workflow-templates.php">
                                <i class="bi bi-diagram-3 ms-2"></i>مدیریت روتین‌ها
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="../../pages/activity_section_managment.php">
                                <i class="bi bi-diagram-3 ms-2"></i>مدیریت واحدهای فعالیت
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="../../pages/group-management.php">
                                <i class="bi bi-diagram-3 ms-2"></i>مدیریت گروه‌ها
                            </a>
                        </li>
                        <li id="securityLogMenuItem" style="display:none;">
                            <a class="dropdown-item" href="../../pages/security-log.php">
                                <i class="bi bi-shield-lock ms-2"></i>رصدِ امنیتی
                            </a>
                        </li>
                        <li id="holidaysMenuItem" style="display:none;">
                            <a class="dropdown-item" href="../../pages/holidays.php">
                                <i class="bi bi-calendar-x ms-2"></i>روزهای تعطیل
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="../../pages/attendance-devices.php">
                                <i class="bi bi-shield-lock ms-2"></i>دستگاه‌های حضور و غیاب
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item" href="../../attendance_system/pages/settings.php">
                                <i class="bi bi-gear-fill ms-2"></i>تنظیمات سیستم
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item" id="drawerLogoutDivider">
                    <hr class="dropdown-divider" style="margin: 8px 14px; opacity: .15;">
                </li>
                <li class="nav-item" id="drawerLogoutItem">
                    <a class="nav-link" href="#" onclick="logoutConfirm()">
                        <i class="bi bi-box-arrow-right me-2"></i>خروج
                    </a>
                </li>
            </ul>
        </div>
        <!-- Overlay تاریک پشت منو -->
        <div class="drawer-overlay" id="drawerOverlay"></div>
            <div class="attendance-container" id="attendanceContainer">
                <div class="attendance-loading">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                </div>
            </div>
        <!-- آیکون‌های تنظیمات و خروج -->
        <div class="navbar-nav me-0" style="flex-direction: row;">
            <!-- حضور و غیاب - Minimal -->

            <div class="navbar-divider"></div>
            <div class="nav-item">
                <a class="nav-link settings-btn position-relative" href="../../pages/chat.php" title="گفتگوها">
                    <i class="bi bi-chat-dots" style="font-size:1.2rem;color:var(--icon-accent);"></i>
                    <span class="notification-badge hidden" id="chatUnreadBadge">0</span>
                </a>
            </div>

            <div class="nav-item">
                <a class="nav-link settings-btn" href="../../pages/tickets.php" title="تیکت‌ها">
                    <i class="bi bi-headset" style="font-size:1.2rem;color:var(--icon-accent);"></i>
                </a>
            </div>

            <!-- آیکنِ دستیارِ هوش‌مصنوعی موقتاً مخفی — صفحه هنوز در حالِ توسعه/تسته -->
            <div class="nav-item" style="display:none;">
                <a class="nav-link settings-btn" href="../../pages/ai-assistant-test.php" title="دستیارِ هوش‌مصنوعی">
                    <i class="bi bi-stars" style="font-size:1.2rem;color:var(--icon-accent);"></i>
                </a>
            </div>

            <div class="dropdown" style="position: relative;">
                <!-- آیکون مگافون با بج -->
                <a href="#" class="nav-link position-relative settings-btn" id="announcementDropdown"
                    aria-expanded="false" style="display: inline-flex; align-items: center;">
                    <i class="bi bi-megaphone announcement-bell"></i>
                    <span class="notification-badge hidden" id="announcementBadge">0</span>
                </a>
            </div>

            <!-- Dropdown اطلاعیه‌ها — دقیقاً مثل notificationDropdownMenu -->
            <div class="dropdown-menu notification-dropdown p-0" id="announcementDropdownMenu"
                aria-labelledby="announcementDropdown" style="min-width: 360px;">
                <div class="notification-header">
                    <span>اطلاعیه‌های سازمانی</span>
                    <button type="button" class="mark-all-link" id="annMarkAllBtn" onclick="annMarkAllRead()" disabled>
                        خواندن همه
                    </button>
                </div>
                <div class="notif-toolbar">
                    <div class="notif-search">
                        <i class="bi bi-search"></i>
                        <input type="text" placeholder="جستجو در اطلاعیه‌ها..." id="annSearchInput"
                               oninput="annSearchQuery = this.value.trim(); renderAnnouncementList();">
                    </div>
                    <div class="notif-chips">
                        <button type="button" class="notif-chip active" data-mode="all" onclick="annSetFilterMode('all')">همه</button>
                        <button type="button" class="notif-chip" data-mode="unread" onclick="annSetFilterMode('unread')">خوانده‌نشده</button>
                    </div>
                </div>
                <!-- لیست اطلاعیه‌ها -->
                <div class="notification-list-container" id="announcementList">
                    <div class="notification-loading">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">در حال بارگذاری...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dropdown" style="position: relative;">
                <a href="#" class="nav-link position-relative settings-btn" id="notificationDropdown"
                    data-bs-toggle="dropdown" aria-expanded="false" style="display: inline-flex; align-items: center;">
                    <i class="bi bi-bell notification-bell"></i>
                    <span class="notification-badge hidden" id="notificationBadge">0</span>
                </a>
            </div>

            <div class="dropdown-menu notification-dropdown p-0" id="notificationDropdownMenu"
                aria-labelledby="notificationDropdown">
                <div class="notification-header">
                    <span>اعلان‌ها</span>
                    <button type="button" class="mark-all-link" id="notifMarkAllBtn" onclick="notifMarkAllRead()" disabled>
                        خواندن همه
                    </button>
                </div>
                <div class="notif-toolbar">
                    <div class="notif-search">
                        <i class="bi bi-search"></i>
                        <input type="text" placeholder="جستجو در اعلان‌ها..." id="notifSearchInput"
                               oninput="notifSearchQuery = this.value.trim(); renderNotificationList();">
                    </div>
                    <div class="notif-chips">
                    <button type="button" class="notif-chip active" data-mode="all" onclick="notifSetFilterMode('all')">همه</button>    
                    <button type="button" class="notif-chip" data-mode="unread" onclick="notifSetFilterMode('unread')">خوانده‌نشده</button>
                    </div>
                </div>
                <!-- لیست اعلان‌ها -->
                <div class="notification-list-container" id="notificationList">
                    <div class="notification-loading">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">در حال بارگذاری...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="navbar-divider"></div>
            <div class="dropdown" style="position: relative;">
                <a href="#" class="nav-link settings-btn dropdown-toggle" id="profileDropdown"
                    data-bs-toggle="dropdown" aria-expanded="false" title="پروفایل"
                    style="display: inline-flex; align-items: center;">
                    <i class="bi bi-person" style="font-size:1.25rem;color:var(--icon-accent);"></i>
                </a>
                <ul class="dropdown-menu" id="profileDropdownMenu" aria-labelledby="profileDropdown">
                    <li class="profile-dropdown-name" id="profileDropdownName">
                        <i class="bi bi-person-circle ms-2"></i><span id="profileDropdownNameText"></span>
                    </li>
                    <li class="profile-dropdown-name-divider" style="display:none;"><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="../../pages/settings.php">
                            <i class="bi bi-gear ms-2"></i>تنظیمات
                        </a>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()">
                            <i class="bi bi-moon-stars ms-2" id="themeToggleIcon" ></i><span style="padding-right: 5px;" id="themeToggleLabel">حالت تاریک</span>
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="#" onclick="logoutConfirm()">
                            <i class="bi bi-box-arrow-right ms-2"></i>خروج
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <script>bpmApplyTheme(bpmGetTheme());</script>
</nav>
<div class="gs-toggle" id="gsToggle" title="جستجوی سراسری" onclick="gsTogglePanel()">
    <i class="bi bi-chevron-down"></i>
</div>
<div class="gs-panel" id="gsPanel">
    <div class="gs-panel-inner">
        <div class="gs-search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="gsInput" placeholder="جستجو در تسک‌ها، تیکت‌ها، اطلاعیه‌ها، نوتیفیکیشن‌ها و..." oninput="gsOnInput()">
        </div>
        <div class="gs-type-filters" id="gsTypeFilters">
            <span class="gs-type-chip active" data-type="task" onclick="gsToggleType(this)">کارها</span>
            <span class="gs-type-chip active" data-type="ticket" onclick="gsToggleType(this)">تیکت‌ها</span>
            <span class="gs-type-chip active" data-type="announcement" onclick="gsToggleType(this)">اطلاعیه‌ها</span>
            <span class="gs-type-chip active" data-type="notification" onclick="gsToggleType(this)">نوتیفیکیشن‌ها</span>
            <span class="gs-type-chip active" data-type="task_history" onclick="gsToggleType(this)">تاریخچه کار</span>
            <span class="gs-type-chip active" data-type="workflow" onclick="gsToggleType(this)">فرآیندهای جاری</span>
        </div>
        <div class="gs-results" id="gsResults">
            <div class="gs-hint">برای جستجو تایپ کنید</div>
        </div>
    </div>
</div>
<?php $__st = new DateTime('now', new DateTimeZone('Asia/Tehran')); ?>
<script>
    /* زمانِ سرور — درون‌خطی تا کلاینت بدونِ round-trip با ساعتِ سرور هم‌کوک شود */
    window.__SERVER_TIME__ = {
        epoch_ms: <?= (int) round(microtime(true) * 1000) ?>,
        offset_minutes: <?= (int) ($__st->getOffset() / 60) ?>,
        mysql: "<?= $__st->format('Y-m-d H:i:s') ?>"
    };
</script>
<script src="<?= asset('/assets/js/time-sync.js') ?>"></script>
<script src="<?= asset('/assets/js/common-bundle.js') ?>"></script>


<script>
    // ============================================
    // متغیرهای سراسری
    // ============================================
    var authToken;
    var unreadCount = 0;
    var lastNotificationId = 0;
    let annUnreadCount = 0;
    let annCache = {}; // ذخیرهٔ کاملِ اطلاعیه‌ها برای نمایش در مودال

    // ─── وضعیتِ فیلتر/جستجویِ لیستِ اعلان‌ها و اطلاعیه‌ها (هردو یکسان) ───
    let annAllItems = [];
    let annFilterMode = 'all'; // 'all' | 'unread'
    let annSearchQuery = '';
    let notifAllItems = [];
    let notifFilterMode = 'all';
    let notifSearchQuery = '';

    /** برچسبِ گروهِ روز — «امروز»/«دیروز»/«قدیمی‌تر»، مبنا: امروزِ سرور (تهران) */
    function bpmDayGroupLabel(dateStr) {
        if (window.TimeSync) {
            const df = TimeSync.daysFromToday(dateStr); // ۰ = امروز، ‑۱ = دیروز
            if (isNaN(df)) return 'قدیمی‌تر';
            if (df >= 0) return 'امروز';
            if (df === -1) return 'دیروز';
            return 'قدیمی‌تر';
        }
        const d = new Date(dateStr);
        const startOfDay = dt => new Date(dt.getFullYear(), dt.getMonth(), dt.getDate()).getTime();
        const diffDays = Math.round((startOfDay(new Date()) - startOfDay(d)) / 86400000);
        if (diffDays <= 0) return 'امروز';
        if (diffDays === 1) return 'دیروز';
        return 'قدیمی‌تر';
    }

    /** items رو بر اساسِ روز (امروز/دیروز/قدیمی‌تر) گروه‌بندی می‌کنه، با همون ترتیبِ ورودی (که از قبل created_at DESC هست) */
    function bpmGroupByDay(items, dateField) {
        const order = ['امروز', 'دیروز', 'قدیمی‌تر'];
        const groups = {};
        items.forEach(it => {
            const label = bpmDayGroupLabel(it[dateField]);
            (groups[label] = groups[label] || []).push(it);
        });
        return order.filter(l => groups[l]).map(l => ({ label: l, items: groups[l] }));
    }

    // toFa/enTofaNumber/faNum از assets/js/common.js میاد (لود شده بالاتر)

    // ============================================
    // تابع کمکی URL
    // ============================================
    function getApiUrl(endpoint) {
        endpoint = endpoint.replace(/^\/+/, '').replace(/^api\/+/, '');
        return '/api/' + endpoint;
    }

    // ============================================
    // آیکون بر اساس نوع اعلان
    // ============================================
    function getNotificationIcon(type) {
        const icons = {
            info: 'info-circle',
            success: 'check-circle',
            warning: 'exclamation-triangle',
            danger: 'x-circle'
        };
        return icons[type] || 'bell';
    }

    // ============================================
    // بروزرسانی badge
    // ============================================
    function updateBadge(count) {
        // ⚠️ عمداً با شناسه (نه با کلاسِ عمومیِ notification-badge): چون بجِ چت هم
        // همین کلاس رو داره و querySelector فقط اولین match رو برمی‌گردونه، قبلاً
        // این تابع به‌جای زنگوله، گاهی بجِ چت رو (که زودتر توی DOM میاد) آپدیت می‌کرد
        const badge = document.getElementById('notificationBadge');
        if (!badge) return;

        unreadCount = count;

        // دکمهٔ «خواندن همه» همیشه دیده می‌شود؛ وقتی چیزی خوانده‌نشده نیست، غیرفعال
        const markAllBtn = document.getElementById('notifMarkAllBtn');
        if (markAllBtn) markAllBtn.disabled = count <= 0;

        if (count > 0) {
            badge.textContent = count > 99 ? '۹۹+' : toFa(count);
            badge.classList.remove('hidden');

            const bell = document.querySelector('.notification-bell');
            if (bell) bell.classList.add('has-notification');
        } else {
            badge.classList.add('hidden');

            const bell = document.querySelector('.notification-bell');
            if (bell) bell.classList.remove('has-notification');
        }
    }

    // ============================================
    // بارگذاری اعلان‌ها
    // ============================================
    // preData: اگه از قبل fetch شده باشه (مثلاً از باندلِ header/bootstrap.php
    // در بارگذاریِ اولیه‌ی صفحه)، همون استفاده می‌شه؛ وگرنه (رفرش‌هایِ دوره‌ای
    // با setInterval) مثلِ قبل مستقیم fetch می‌کنه.
    async function loadNotifications(preData) {
        const listContainer = document.getElementById('notificationList');

        if (!authToken) {
            console.warn('⚠️ authToken هنوز set نشده');
            return;
        }

        if (listContainer && !preData) {
            listContainer.innerHTML = '<div class="notification-loading"><div class="spinner-border" role="status"></div></div>';
        }

        try {
            let data;
            if (preData) {
                data = preData;
            } else {
                const apiUrl = '/api/notifications/list.php?limit=50';

                const response = await fetch(apiUrl, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    }
                });

                const contentType = response.headers.get('content-type');

                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('❌ HTML returned:', text.substring(0, 200));
                    throw new Error('پاسخ JSON نیست');
                }

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'خطای سرور');
                }

                data = await response.json();
            }

            if (data.success && listContainer) {
                notifAllItems = data.notifications || [];
                if (notifAllItems.length > 0) {
                    lastNotificationId = Math.max(...notifAllItems.map(n => parseInt(n.id)));
                }
                renderNotificationList();
                updateBadge(data.unread_count || 0);
            }
        } catch (error) {
            console.error('❌ خطا در بارگذاری اعلان‌ها:', error);
            if (listContainer) {
                listContainer.innerHTML = '<div class="notification-empty"><i class="bi bi-wifi-off"></i><div>خطا: ' + esc(error.message) + '</div></div>';
            }
            updateBadge(0);
        }
    }

    function notifSetFilterMode(mode) {
        notifFilterMode = mode;
        document.querySelectorAll('#notificationDropdownMenu .notif-chip').forEach(c =>
            c.classList.toggle('active', c.dataset.mode === mode));
        renderNotificationList();
    }

    /** خواندنِ همه‌ی اعلان‌های خوانده‌نشده — سراسری (نه فقط موارد بارگذاری‌شده/یک روز) */
    async function notifMarkAllRead() {
        const btn = document.getElementById('notifMarkAllBtn');
        if (btn && btn.disabled) return;
        if (btn) btn.disabled = true;
        try {
            const response = await fetch('/api/notifications/mark-all-read.php', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + authToken }
            });
            const data = await response.json();
            if (data.success) {
                await loadNotifications(); // لیست و شمارنده از سرور تازه می‌شوند
            } else if (btn) {
                btn.disabled = false;
            }
        } catch (error) {
            console.error('❌ خطا در خواندن همه اعلان‌ها:', error);
            if (btn) btn.disabled = false;
        }
    }

    // ─── رندر لیست (از notifAllItems، با فیلتر/جستجویِ فعلی) ───
    function renderNotificationList() {
        const listContainer = document.getElementById('notificationList');
        if (!listContainer) return;

        const q = (notifSearchQuery || '').toLowerCase();
        const filtered = notifAllItems.filter(notif => {
            if (notifFilterMode === 'unread' && notif.is_read) return false;
            if (q && !((notif.title || '') + (notif.message || '')).toLowerCase().includes(q)) return false;
            return true;
        });

        if (filtered.length === 0) {
            listContainer.innerHTML = '<div class="notification-empty"><i class="bi bi-bell-slash"></i><div>اعلانی وجود ندارد</div></div>';
            return;
        }

        let html = '';
        bpmGroupByDay(filtered, 'created_at').forEach(group => {
            html += `
            <div class="notif-day-header">
                <span>${group.label}</span>
            </div>`;

            group.items.forEach(notif => {
                const isUnread = !notif.is_read;
                html += `
                <a class="notification-item ${isUnread ? 'unread' : ''}" href="${notif.link || '#'}" data-notif-id="${notif.id}">
                    <div class="d-flex align-items-start">
                        <div class="notification-icon ${notif.type}">
                            <i class="bi bi-${getNotificationIcon(notif.type)}"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">${esc(notif.title)}</div>
                            <div class="notification-message">${esc(notif.message)}</div>
                            <div class="notification-time">${getSmartAnnTime(notif.created_at)}</div>
                        </div>
                    </div>
                </a>`;
            });
        });

        listContainer.innerHTML = html;

        listContainer.querySelectorAll('.notification-item').forEach(item => {
            const notifId = item.getAttribute('data-notif-id');
            const notif = notifAllItems.find(n => String(n.id) === notifId);
            item.addEventListener('click', (e) => {
                e.preventDefault();
                if (notif && !notif.is_read) markAsRead(notif.id);
                if (notif && notif.link && notif.link !== '#') window.location.href = notif.link;
            });
        });
    }

    function updateAnnouncementBadge(count) {
        const badge = document.getElementById('announcementBadge');
        if (!badge) return;

        annUnreadCount = count;

        // دکمهٔ «خواندن همه» همیشه دیده می‌شود؛ وقتی چیزی خوانده‌نشده نیست، غیرفعال
        const annMarkAllBtn = document.getElementById('annMarkAllBtn');
        if (annMarkAllBtn) annMarkAllBtn.disabled = count <= 0;

        if (count > 0) {
            badge.textContent = count > 99 ? '۹۹+' : toFa(count);
            badge.classList.remove('hidden');
            const icon = document.querySelector('.announcement-bell');
            if (icon) icon.classList.add('has-announcement');
        } else {
            badge.classList.add('hidden');
            const icon = document.querySelector('.announcement-bell');
            if (icon) icon.classList.remove('has-announcement');
        }
    }

    // ─── بارگذاری اطلاعیه‌ها ───
    async function loadAnnouncements(preData) {
        const listContainer = document.getElementById('announcementList');
        if (!authToken || !listContainer) return;

        if (!preData) {
            listContainer.innerHTML = `
        <div class="notification-loading">
            <div class="spinner-border" role="status"></div>
        </div>`;
        }

        try {
            let data;
            if (preData) {
                data = preData;
            } else {
                const response = await fetch('/api/announcements/list.php?limit=50&offset=0', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                if (!response.ok) throw new Error('server error');
                data = await response.json();
            }

            if (data.success) {
                annAllItems = data.announcements || [];
                annCache = {};
                annAllItems.forEach(ann => annCache[ann.id] = ann);
                renderAnnouncementList();
                updateAnnouncementBadge(data.unread_count || 0);
            }
        } catch (err) {
            console.error('❌ خطا در بارگذاری اطلاعیه‌ها:', err);
            listContainer.innerHTML = `
            <div class="ann-empty">
                <i class="bi bi-wifi-off"></i>
                <p>خطا در بارگذاری</p>
            </div>`;
        }
    }

    function annSetFilterMode(mode) {
        annFilterMode = mode;
        document.querySelectorAll('#announcementDropdownMenu .notif-chip').forEach(c =>
            c.classList.toggle('active', c.dataset.mode === mode));
        renderAnnouncementList();
    }

    /** خواندنِ همه‌ی اطلاعیه‌های خوانده‌نشده — سراسری (نه فقط موارد بارگذاری‌شده/یک روز) */
    async function annMarkAllRead() {
        const btn = document.getElementById('annMarkAllBtn');
        if (btn && btn.disabled) return;
        if (btn) btn.disabled = true;
        try {
            const response = await fetch('/api/announcements/update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + authToken
                },
                body: JSON.stringify({ action: 'mark_all_read' })
            });
            const data = await response.json();
            if (data.success) {
                await loadAnnouncements(); // لیست و شمارنده از سرور تازه می‌شوند
            } else if (btn) {
                btn.disabled = false;
            }
        } catch (error) {
            console.error('❌ خطا در خواندن همه اطلاعیه‌ها:', error);
            if (btn) btn.disabled = false;
        }
    }

    // ─── رندر لیست (از annAllItems، با فیلتر/جستجویِ فعلی) ───
    function renderAnnouncementList() {
        const listContainer = document.getElementById('announcementList');
        if (!listContainer) return;

        const q = (annSearchQuery || '').toLowerCase();
        const filtered = annAllItems.filter(ann => {
            const isUnread = ann.is_read === false || ann.is_read === 0;
            if (annFilterMode === 'unread' && !isUnread) return false;
            if (q && !(ann.title || '').toLowerCase().includes(q)) return false;
            return true;
        });

        if (filtered.length === 0) {
            listContainer.innerHTML = `
            <div class="ann-empty">
                <i class="bi bi-megaphone"></i>
                <p>اطلاعیه‌ای وجود ندارد</p>
            </div>`;
            return;
        }

        const priorityIcons = {
            urgent: '<i class="bi bi-exclamation-triangle-fill"></i>',
            high: '<i class="bi bi-exclamation-circle-fill"></i>',
            normal: '<i class="bi bi-megaphone-fill"></i>',
            low: '<i class="bi bi-info-circle"></i>'
        };

        let html = '';
        bpmGroupByDay(filtered, 'created_at').forEach(group => {
            html += `
            <div class="notif-day-header">
                <span>${group.label}</span>
            </div>`;

            group.items.forEach(ann => {
                const isUnread = ann.is_read === false || ann.is_read === 0;
                const priority = ann.priority || 'normal';
                const icon = priorityIcons[priority] || priorityIcons.normal;
                const timeText = getSmartAnnTime(ann.created_at);

                let priorityTag = '';
                if (priority === 'urgent') priorityTag = '<span class="ann-urgent-tag">فوری</span>';
                else if (priority === 'high') priorityTag = '<span class="ann-high-tag">مهم</span>';

                html += `
                <div class="announcement-item ${isUnread ? 'unread' : ''}"
                     data-ann-id="${ann.id}"
                     onclick="handleAnnouncementClick(event, ${ann.id})">
                    <div class="ann-priority-icon ${priority}">${icon}</div>
                    <div class="ann-item-body">
                        <div class="ann-item-title">
                            ${priorityTag}
                            ${escapeHtml(ann.title)}
                        </div>
                        <div class="ann-item-time">
                            <i class="bi bi-clock" style="font-size:10px;"></i>
                            ${timeText}
                        </div>
                    </div>
                </div>`;
            });
        });

        // footer — لینک مشاهده همه (اگه صفحه جداگانه داری)
        html += `
        <div class="ann-dropdown-footer">
            <a href="/pages/announcements.php">مشاهده همه اطلاعیه‌ها ←</a>
        </div>`;

        listContainer.innerHTML = html;
    }

    // ─── کلیک روی اطلاعیه ⟵ باز کردنِ مودالِ جزئیات ───
    function handleAnnouncementClick(ev, annId) {
        if (ev) {
            ev.preventDefault();
            ev.stopPropagation();
        }

        // علامت خوانده‌شده
        markAnnouncementRead(annId);

        // بستن dropdown
        const menu = document.getElementById('announcementDropdownMenu');
        if (menu) menu.classList.remove('show');

        // باز کردنِ مودالِ جزئیات
        const ann = annCache[annId];
        if (ann) openAnnouncementModal(ann);
    }

    // ─── مودالِ جزئیاتِ اطلاعیه ───
    function openAnnouncementModal(ann) {
        closeAnnouncementModal(); // اگر مودالِ قبلی باز بود

        const priority = ann.priority || 'normal';
        const prMap = {
            urgent: {
                label: 'فوری',
                color: '#ef4444'
            },
            high: {
                label: 'مهم',
                color: '#f59e0b'
            },
            normal: {
                label: 'عادی',
                color: '#8e57fe'
            },
            low: {
                label: 'اطلاع‌رسانی',
                color: '#64748b'
            }
        };
        const pr = prMap[priority] || prMap.normal;
        const timeText = (typeof getSmartAnnTime === 'function') ? getSmartAnnTime(ann.created_at) : '';
        const contentHtml = escapeHtml(ann.content || '').replace(/\n/g, '<br>');

        const overlay = document.createElement('div');
        overlay.id = 'annModalOverlay';
        overlay.setAttribute('dir', 'rtl');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;';
        overlay.innerHTML =
            '<div style="background:var(--surface);border-radius:16px;max-width:520px;width:100%;max-height:82vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3);font-family:inherit;">' +
            '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:16px 18px;border-bottom:1px solid var(--border-soft);">' +
            '<div style="font-weight:800;color:var(--text-strong);font-size:16px;">' + escapeHtml(ann.title || 'اطلاعیه') + '</div>' +
            '<button type="button" id="annModalClose" style="border:none;background:var(--border-soft);width:32px;height:32px;border-radius:9px;cursor:pointer;font-size:18px;line-height:1;color:var(--text-strong);">&times;</button>' +
            '</div>' +
            '<div style="display:flex;align-items:center;gap:10px;padding:10px 18px;border-bottom:1px solid var(--border-soft);">' +
            '<span style="background:' + pr.color + ';color:#fff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;">' + pr.label + '</span>' +
            '<span style="color:#9CA3AF;font-size:12px;">' + timeText + '</span>' +
            '</div>' +
            '<div style="padding:16px 18px;overflow:auto;line-height:2;color:var(--text-strong);font-size:14px;">' + (contentHtml || '<span style="color:#9CA3AF;">متنی برای این اطلاعیه ثبت نشده است.</span>') + '</div>' +
            '<div style="padding:12px 18px;border-top:1px solid var(--border-soft);text-align:center;">' +
            '<a href="/pages/announcements.php" style="color:#8e57fe;font-weight:700;text-decoration:none;font-size:13px;">مشاهده همه اطلاعیه‌ها ←</a>' +
            '</div>' +
            '</div>';

        document.body.appendChild(overlay);

        document.getElementById('annModalClose').addEventListener('click', closeAnnouncementModal);
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeAnnouncementModal();
        });
        document.addEventListener('keydown', annModalEsc);
    }

    function annModalEsc(e) {
        if (e.key === 'Escape') closeAnnouncementModal();
    }

    function closeAnnouncementModal() {
        const o = document.getElementById('annModalOverlay');
        if (o) o.remove();
        document.removeEventListener('keydown', annModalEsc);
    }

    // ─── علامت خوانده شده (تک) ───
    async function markAnnouncementRead(annId) {
        // annAllItems رو هم به‌روز کن — وگرنه رندرِ بعدی (جستجو/فیلتر) دوباره «نخوانده» نشونش می‌ده
        const cached = annAllItems.find(a => Number(a.id) === Number(annId));
        if (cached && (cached.is_read === false || cached.is_read === 0)) {
            cached.is_read = true;
            const newCount = Math.max(0, annUnreadCount - 1);
            updateAnnouncementBadge(newCount);
        }

        // بروزرسانی فوری UI
        const item = document.querySelector(`.announcement-item[data-ann-id="${annId}"]`);
        if (item) item.classList.remove('unread');

        try {
            await fetch('/api/announcements/update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + authToken
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    id: annId
                })
            });
        } catch (err) {
            console.error('❌ خطا در mark_read:', err);
        }
    }

    // ─── راه‌اندازی dropdown ───
    function setupAnnouncementDropdown() {
        const toggle = document.getElementById('announcementDropdown');
        const menu = document.getElementById('announcementDropdownMenu');
        if (!toggle || !menu) return;

        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const isShown = menu.classList.contains('show');

            // بستن dropdown اعلان و کادر سرچ سراسری اگه باز بودن
            const notifMenu = document.getElementById('notificationDropdownMenu');
            if (notifMenu) notifMenu.classList.remove('show');
            gsClosePanel();

            if (isShown) {
                menu.classList.remove('show');
            } else {
                menu.classList.add('show');
                loadAnnouncements(); // هر بار که باز میشه refresh کن
            }
        });

        // بستن با کلیک خارج
        document.addEventListener('click', function(e) {
            if (!toggle.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('show');
            }
        });
    }

    // ─── بررسی اطلاعیه‌های جدید (polling) ───
    async function checkNewAnnouncements() {
        if (!authToken) return;
        try {
            const response = await fetch('/api/announcements/list.php?limit=1&offset=0', {
                headers: {
                    'Authorization': 'Bearer ' + authToken
                }
            });
            const data = await response.json();
            if (data.success && data.unread_count !== annUnreadCount) {
                updateAnnouncementBadge(data.unread_count || 0);
            }
        } catch (err) {
            /* silent */
        }
    }

    // ─── توابع کمکی ───
    function getSmartAnnTime(dateString) {
        // زمانِ نسبی از منبعِ یگانه (ساعتِ سرور، نه دستگاه) — time-sync.js
        return window.TimeSync ? TimeSync.timeAgo(dateString) : '';
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    // ============================================
    // علامت‌گذاری خوانده شده
    // ============================================
    async function markAsRead(notifId) {
        const cached = notifAllItems.find(n => Number(n.id) === Number(notifId));
        const wasUnread = cached && !cached.is_read;
        if (!wasUnread) return; // از قبل خوانده بود — دوباره به سرور/شمارنده دست نزن

        try {
            const response = await fetch('/api/notifications/mark-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + authToken
                },
                body: JSON.stringify({
                    id: notifId
                })
            });
            const data = await response.json();
            if (data.success) {
                cached.is_read = 1;
                unreadCount = Math.max(0, unreadCount - 1);
                updateBadge(unreadCount);
                const notifItem = document.querySelector(`[data-notif-id="${notifId}"]`);
                if (notifItem) notifItem.classList.remove('unread');
            }
        } catch (error) {
            console.error('❌ خطا در علامت‌گذاری:', error);
        }
    }

    // ============================================
    // علامت‌گذاری همه
    // ============================================
    // async function markAllAsRead() {
    //     const btn = document.getElementById('markAllBtn');
    //     if (!btn || unreadCount === 0) return;

    //     const originalText = btn.textContent;
    //     btn.disabled = true;
    //     btn.textContent = 'در حال پردازش...';

    //     try {
    //         const response = await fetch('/api/notifications/mark-all-read.php', {
    //             method: 'POST',
    //             headers: {
    //                 'Authorization': 'Bearer ' + authToken
    //             }
    //         });
    //         const data = await response.json();
    //         if (data.success) {
    //             unreadCount = 0;
    //             notifAllItems.forEach(n => n.is_read = 1);
    //             updateBadge(0);
    //             document.querySelectorAll('.notification-item.unread').forEach(item => item.classList.remove('unread'));
    //             setTimeout(() => {
    //                 const dropdownMenu = document.getElementById('notificationDropdownMenu');
    //                 if (dropdownMenu) {
    //                     dropdownMenu.classList.remove('show');
    //                 }
    //             }, 100);
    //         }
    //     } catch (error) {
    //         console.error('❌ خطا:', error);
    //     } finally {
    //         btn.disabled = false;
    //         btn.textContent = originalText;
    //     }
    // }

    // ============================================
    // بررسی اعلان‌های جدید
    // ============================================
    async function checkNewNotifications() {
        if (!authToken) return;
        try {
            const apiUrl = '/api/notifications/new.php' + '?since=' + lastNotificationId;
            const response = await fetch(apiUrl, {
                headers: {
                    'Authorization': 'Bearer ' + authToken
                }
            });
            if (!response.ok) return;
            const data = await response.json();
            if (data.success && data.new_count > 0) {
                loadNotifications();
                lastNotificationId = data.latest_id;
            }
        } catch (error) {
            console.error('❌ خطا در بررسی اعلان‌های جدید:', error);
        }
    }
    // ============================================
    // ✅ نشانگرِ پیام‌های خوانده‌نشدهٔ چت (هدر)
    // ============================================
    async function updateChatUnreadBadge(preData) {
        if (!authToken) return;
        const badge = document.getElementById('chatUnreadBadge');
        if (!badge) return;
        try {
            let data;
            if (preData) {
                data = preData;
            } else {
                const response = await fetch('/api/chat/conversations.php', {
                    headers: { 'Authorization': 'Bearer ' + authToken },
                    cache: 'no-store'
                });
                if (!response.ok) return;
                data = await response.json();
            }
            if (!data.success) return;
            // گفتگوهای بی‌صداشده در شمارشِ زنگوله‌ی کلیِ هدر حساب نمی‌شوند
            const total = (data.conversations || []).reduce((sum, c) => sum + (c.is_muted ? 0 : (c.unread_count || 0)), 0);
            if (total > 0) {
                badge.textContent = total > 99 ? '۹۹+' : toFa(total);
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        } catch (error) {
            console.error('❌ خطا در بررسی پیام‌های خوانده‌نشده:', error);
        }
    }

    // ============================================
    // ✅ بارگذاری وضعیت حضور و غیاب - نسخه مینیمال
    // ============================================
    async function loadAttendanceStatus(preData) {
        const container = document.getElementById('attendanceContainer');

        if (!authToken || !container) {
            if (container) container.style.display = 'none';
            return;
        }

        try {
            let data;
            if (preData) {
                data = preData;
            } else {
                const apiUrl = getApiUrl('attendance/today-status.php');

                const response = await fetch(apiUrl, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    cache: 'no-store',
                    // 🔒 اگه درخواست به هر دلیلی (تداخل با درخواست‌های دیگه، شبکه، ...) خیلی طول
                    // بکشه، به‌جای گیرکردنِ ابدیِ اسپینر، بعد از ۸ ثانیه لغو و مخفی می‌شه
                    signal: AbortSignal.timeout(8000)
                });

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    container.style.display = 'none';
                    return;
                }

                data = await response.json();
            }

            if (!data.success) {
                throw new Error(data.message || 'API Error');
            }

            const tooltipContent = buildTooltipContent(data);

            container.innerHTML = '';
            container.style.display = 'flex';

            if (data.buttons && data.buttons.length > 0) {
                data.buttons.forEach(btn => {
                    const button = document.createElement('button');
                    if (btn.type === 'check_in') {
                        button.className = 'attendance-icon-btn btn-in';
                        button.innerHTML = '<i class="bi bi-box-arrow-in-left"></i>';
                        button.title = data.shift_count === 2 ? btn.label : 'ثبت ورود';
                    } else {
                        button.className = 'attendance-icon-btn btn-out';
                        button.innerHTML = '<i class="bi bi-box-arrow-right"></i>';
                        button.title = data.shift_count === 2 ? btn.label : 'ثبت خروج';
                    }
                    button.onclick = () => registerAttendance(btn.type, btn.shift);
                    container.appendChild(button);
                });
            } else if (data.window_message) {
                // بازه‌ی بینِ دو شیفت ⟵ پیامِ «الان زمانِ ثبت ورود نیست»
                const waiting = document.createElement('span');
                waiting.className = 'attendance-complete';
                waiting.innerHTML = '<i class="bi bi-clock-history"></i>';
                waiting.title = data.window_message;
                container.appendChild(waiting);
            } else {
                const complete = document.createElement('span');
                complete.className = 'attendance-complete';
                complete.innerHTML = '<i class="bi bi-check-lg"></i>';
                complete.title = 'حضور امروز تکمیل شد';
                container.appendChild(complete);
            }

            if (tooltipContent) {
                const infoBtn = document.createElement('button');
                infoBtn.className = 'attendance-info-btn';
                infoBtn.type = 'button';
                infoBtn.title = 'ساعت ورود و خروج امروز';
                infoBtn.innerHTML = `
                <i class="bi bi-clock-history"></i>
                <div class="attendance-tooltip">${tooltipContent}</div>
            `;
                container.appendChild(infoBtn);
            }

        } catch (error) {
            console.error('❌ Error loading attendance:', error);
            if (container) container.style.display = 'none';
        }
    }
    // ============================================
    // ✅ ساخت محتوای Tooltip
    // ============================================
    function buildTooltipContent(data) {
        let lines = [];

        // اعداد ساعت‌ها فارسی شوند
        const fa = v => (typeof toFa === 'function' ? toFa(v) : String(v ?? ''));

        // شیفت 1
        if (data.shift1) {
            if (data.shift1.check_in) {
                lines.push(`ورود${data.shift_count === 2 ? ' ۱' : ''}: ${fa(data.shift1.check_in)}`);
            }
            if (data.shift1.check_out) {
                lines.push(`خروج${data.shift_count === 2 ? ' ۱' : ''}: ${fa(data.shift1.check_out)}`);
            }
        }

        // شیفت 2
        if (data.shift_count === 2 && data.shift2) {
            if (data.shift2.check_in) {
                lines.push(`ورود ۲: ${fa(data.shift2.check_in)}`);
            }
            if (data.shift2.check_out) {
                lines.push(`خروج ۲: ${fa(data.shift2.check_out)}`);
            }
        }

        return lines.length > 0 ? lines.join('<br>') : null;
    }
    // ============================================
    // شناسهٔ پایدارِ دستگاه — یک کدِ تصادفیِ یک‌بارساخته (نه محاسبه‌شده از
    // مشخصاتِ مرورگر). نسخهٔ قبلی از User-Agent/canvas/اندازهٔ صفحه هش
    // می‌ساخت که با هر آپدیتِ مرورگر یا رندرِ متفاوتِ فونت/GPU عوض می‌شد و
    // کاربر را هر چند روز یک‌بار دوباره «در انتظارِ تأیید» می‌کرد. این کد
    // فقط یک‌بار (اولین بازدید) تصادفی ساخته و برایِ همیشه همان می‌ماند —
    // هم در localStorage هم در یک کوکیِ بلندمدت، تا از دستِ‌رفتنِ یکی از
    // این دو (مثلاً پاک‌شدنِ localStorage توسطِ ITPِ سافاری) مشکلی پیش نیاد
    // ============================================
    function getDeviceFingerprint() {
        try {
            let token = getDeviceCookie('yekta_device_token');
            if (!token) {
                try { token = localStorage.getItem('yekta_device_token') || ''; } catch (e) {}
            }
            if (token && token.length >= 16) {
                try { localStorage.setItem('yekta_device_token', token); } catch (e) {}
                setDeviceCookie('yekta_device_token', token, 730);
                return token;
            }

            token = (window.crypto && crypto.randomUUID)
                ? crypto.randomUUID().replace(/-/g, '')
                : simpleHash(String(Math.random()) + Date.now() + navigator.userAgent);

            try { localStorage.setItem('yekta_device_token', token); } catch (e) {}
            setDeviceCookie('yekta_device_token', token, 730);
            return token;
        } catch (e) {
            return '';
        }
    }

    function getDeviceCookie(name) {
        const m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return m ? decodeURIComponent(m[1]) : '';
    }

    function setDeviceCookie(name, value, days) {
        try {
            const d = new Date();
            d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
            document.cookie = name + '=' + encodeURIComponent(value) +
                '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax; Secure';
        } catch (e) {}
    }

    // هش ساده (FNV-1a 32بیت → رشتهٔ هگز ۱۶ کاراکتری) — فقط به‌عنوانِ راهِ
    // پشتیبان اگه crypto.randomUUID در دسترس نبود (مرورگرهایِ خیلی قدیمی)
    function simpleHash(str) {
        let h1 = 0x811c9dc5,
            h2 = 0x1000193;
        for (let i = 0; i < str.length; i++) {
            const ch = str.charCodeAt(i);
            h1 ^= ch;
            h1 = Math.imul(h1, 0x01000193) >>> 0;
            h2 = (Math.imul(h2 ^ ch, 0x85ebca6b)) >>> 0;
        }
        const hex = (n) => ('00000000' + (n >>> 0).toString(16)).slice(-8);
        return hex(h1) + hex(h2); // ۱۶ کاراکتر
    }
    // ============================================
    // ✅ ثبت حضور
    // ============================================
    async function registerAttendance(action, shift = 1) {
        const container = document.getElementById('attendanceContainer');

        if (container) {
            container.innerHTML = '<div class="attendance-loading"><div class="spinner-border spinner-border-sm"></div></div>';
        }

        try {
            const apiUrl = getApiUrl('attendance/register.php');

            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + authToken,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: action,
                    shift: shift,
                    fingerprint: getDeviceFingerprint()
                })
            });

            const data = await response.json();

            if (data.success) {
                loadAttendanceStatus();

                // Event
                window.dispatchEvent(new CustomEvent('attendanceUpdated', {
                    detail: {
                        action,
                        shift,
                        timestamp: new Date().toISOString()
                    }
                }));

                // Toast
                const shiftText = shift === 2 ? ' شیفت ۲' : '';
                const msg = action === 'check_in' ?
                    `✅ ورود${shiftText} ثبت شد` :
                    `✅ خروج${shiftText} ثبت شد`;
                showToast(msg, 'success');

            } else {
                showToast(data.message || 'خطا در ثبت', 'error');
                loadAttendanceStatus();
            }
        } catch (error) {
            console.error('❌ Error:', error);
            showToast('خطا در ارتباط با سرور', 'error');
            loadAttendanceStatus();
        }
    }
    // ============================================
    // تابع Toast
    // ============================================
    // function showToast(message, type = 'success', options = {}) {
    //     const existingToast = document.querySelector('.custom-toast');
    //     if (existingToast) existingToast.remove();

    //     const toast = document.createElement('div');
    //     toast.className = `custom-toast toast-${type}`;
    //     toast.innerHTML = `
    //         <div class="toast-content">
    //             <i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'x-circle-fill'}"></i>
    //             <span>${message}</span>
    //         </div>
    //     `;
    //     document.body.appendChild(toast);

    //     setTimeout(() => toast.classList.add('show'), 100);
    //     setTimeout(() => {
    //         toast.classList.remove('show');
    //         setTimeout(() => toast.remove(), 300);
    //     }, 2500);
    // }

    // بازنویسیِ سراسریِ alert→toast قبلاً اینجا بود (به‌عنوانِ یک لایهٔ ایمنیِ
    // پنهان با یک قاعدهٔ حدسی برایِ تشخیصِ موفق/خطا که پیغام‌هایِ خطا رو هم
    // با رنگِ warning نشون می‌داد، نه error). حالا که همهٔ فراخوانی‌هایِ
    // alert()/confirm() توی کدِ پروژه مستقیماً به showToast()/uiConfirm()
    // تبدیل شدن، این بازنویسیِ سراسری دیگه لازم نیست

    // ── مودال تأیید (جایگزین confirm) ──
    function uiConfirm(message, onYes, opts = {}) {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;display:flex;align-items:center;justify-content:center;';
        overlay.innerHTML = `
            <div style="background:var(--surface);color:var(--text-strong);border-radius:12px;padding:20px;width:90%;max-width:400px;box-shadow:0 10px 40px rgba(0,0,0,.2);direction:rtl;text-align:right;">
                <p style="margin:0 0 16px;font-size:15px;line-height:1.8;">${message}</p>
                <div style="display:flex;gap:8px;justify-content:flex-end;">
                    <button id="uiConfirmNo" class="btn btn-secondary">${opts.noText || 'خیر'}</button>
                    <button id="uiConfirmYes" class="btn ${opts.danger ? 'btn-danger' : 'btn-primary'}">${opts.yesText || 'بله'}</button>
                </div>
            </div>`;
        document.body.appendChild(overlay);
        const close = () => overlay.remove();
        overlay.querySelector('#uiConfirmNo').onclick = close;
        overlay.onclick = (e) => {
            if (e.target === overlay) close();
        };
        overlay.querySelector('#uiConfirmYes').onclick = () => {
            close();
            onYes && onYes();
        };
    }

    // ── مودال ورودی (جایگزین prompt) ──
    function uiPrompt(message, onSubmit, opts = {}) {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;display:flex;align-items:center;justify-content:center;';
        overlay.innerHTML = `
            <div style="background:var(--surface);color:var(--text-strong);border-radius:12px;padding:20px;width:90%;max-width:420px;box-shadow:0 10px 40px rgba(0,0,0,.2);direction:rtl;text-align:right;">
                <p style="margin:0 0 12px;font-size:15px;line-height:1.8;">${message}</p>
                <textarea id="uiPromptInput" rows="3" style="width:100%;border:1px solid var(--border-soft);border-radius:8px;padding:10px;resize:vertical;font-family:inherit;background:var(--surface);color:var(--text-strong);" placeholder="${opts.placeholder || ''}">${opts.value || ''}</textarea>
                <div style="display:flex;gap:8px;margin-top:14px;justify-content:flex-end;">
                    <button id="uiPromptCancel" class="btn btn-secondary">انصراف</button>
                    <button id="uiPromptOk" class="btn btn-primary">${opts.okText || 'تأیید'}</button>
                </div>
            </div>`;
        document.body.appendChild(overlay);
        const close = () => overlay.remove();
        const input = overlay.querySelector('#uiPromptInput');
        input.focus();
        overlay.querySelector('#uiPromptCancel').onclick = close;
        overlay.onclick = (e) => {
            if (e.target === overlay) close();
        };
        overlay.querySelector('#uiPromptOk').onclick = () => {
            const val = input.value.trim();
            if (opts.required && !val) {
                showToast('لطفاً مقدار را وارد کنید', 'warning');
                return;
            }
            close();
            onSubmit && onSubmit(val);
        };
    }

    // ============================================
    // 📱 Mobile Drawer Control - ULTIMATE FIX
    (function() {
        // اجرای مستقیم و بی‌درنگ
        function forceCloseDrawer() {
            var drawer = document.getElementById('navbarNav');
            var overlay = document.getElementById('drawerOverlay');
            var menuBtn = document.getElementById('mobileMenuBtn');

            if (drawer) {
                drawer.classList.remove('drawer-open');
                // حذف استایل مستقیم اگر وجود داره
                drawer.style.transform = '';
                drawer.style.visibility = '';
            }

            if (overlay) {
                overlay.classList.remove('overlay-active');
            }

            document.body.classList.remove('drawer-body-open');

            if (menuBtn) {
                menuBtn.classList.remove('menu-btn-hidden');
            }

            console.log('✅ Drawer forcefully closed');
        }

        // اجرا بلافاصله
        forceCloseDrawer();

        // اجرا بعد از DOMContentLoaded
        document.addEventListener('DOMContentLoaded', forceCloseDrawer);

        // اجرا بعد از load کامل
        window.addEventListener('load', forceCloseDrawer);

        // اجرا با هر بار تغییر مسیر (برای SPA-like behavior)
        window.addEventListener('popstate', forceCloseDrawer);

        // اجرا با کلیک روی هر لینک
        document.addEventListener('click', function(e) {
            var link = e.target.closest('a');
            if (link && link.getAttribute('href') &&
                link.getAttribute('href') !== '#' &&
                !link.getAttribute('href').startsWith('#')) {

                // اگه در موبایل/لپ‌تاپ کوچک هستیم و drawer بازه
                if (window.innerWidth < 1400) {
                    setTimeout(forceCloseDrawer, 10);
                }
            }
        });

        // راه‌اندازی event listeners برای باز و بسته کردن
        function setupDrawer() {
            var menuBtn = document.getElementById('mobileMenuBtn');
            var drawer = document.getElementById('navbarNav');
            var overlay = document.getElementById('drawerOverlay');
            var closeBtn = document.getElementById('drawerCloseBtn');

            if (!menuBtn || !drawer) return;

            // باز کردن
            menuBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();

                if (window.innerWidth >= 1400) return;

                drawer.classList.add('drawer-open');
                if (overlay) overlay.classList.add('overlay-active');
                document.body.classList.add('drawer-body-open');
                menuBtn.classList.add('menu-btn-hidden');
            });

            // بستن با دکمه close
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    forceCloseDrawer();
                });
            }

            // بستن با کلیک روی overlay
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    forceCloseDrawer();
                });
            }

            // بستن با Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    forceCloseDrawer();
                }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupDrawer);
        } else {
            setupDrawer();
        }
    })();

    // ============================================
    // تابع خروج
    // ============================================
    function logoutConfirm() {
        uiConfirm('آیا مطمئن هستید که می‌خواهید از سیستم خارج شوید؟', function() {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user_info');

            const loginUrl = '../../index.php';
            window.location.href = loginUrl;
        }, {
            danger: true,
            yesText: 'بله، خروج',
            noText: 'انصراف'
        });
    }

    // ============================================
    // نمایش/مخفی منوی مدیریت
    // ============================================
    function toggleManagerMenu() {
        const userInfo = localStorage.getItem('user_info');
        if (!userInfo) return;

        const user = JSON.parse(userInfo);
        const isManager = (user.role === 'management' || user.role === 'supervisor');
        const isFullAdmin = (user.role === 'supervisor');
        // روزهای تعطیل: هر سوپروایزر می‌تونه تعطیلیِ سازمانِ خودش رو مدیریت کنه
        // (تعطیلیِ سراسری همچنان فقط با id=1 قابلِ‌ساختنه، ولی خودِ صفحه باید
        // برایِ همهٔ سوپروایزرها باز بشه)
        const holidaysItem = document.getElementById('holidaysMenuItem');
        if (holidaysItem) {
            holidaysItem.style.display = isFullAdmin ? 'block' : 'none';
        }
        // 🔒 فقط سوپرادمین (هم‌راستا با getSuperAdminIds سمتِ سرور: ۱ و ۱۹) —
        // این فقط نمایش/مخفی‌بودنِ لینکه، مرزِ امنیتیِ واقعی سمتِ سرورِ
        // security-log.php خودش با isSuperAdmin چک می‌شه
        const securityLogItem = document.getElementById('securityLogMenuItem');
        if (securityLogItem) {
            const isSuperAdminUser = [1, 19].includes(Number(user.id));
            securityLogItem.style.display = isSuperAdminUser ? 'block' : 'none';
        }
        const overviewMenu = document.getElementById('navOverview');
        if (overviewMenu) {
            overviewMenu.style.display = isManager ? 'block' : 'none';
        }

        const adminMenus = document.querySelectorAll('#fulladmintag');
        adminMenus.forEach(menu => {
            if (menu.id === 'fulladmintag' && menu.classList.contains('dropdown')) {
                // نمایش منوی مدیریت
                menu.style.display = isFullAdmin ? 'block' : 'none';
            } else {
                //نمایش ورود و خروج
                menu.style.display = 'block';
            }
        });
    }
    // نمایش «نظارت» برای واحدی که مرحلهٔ فعال دارد (فقط زیرمنوی روتین‌ها)
    function setupOverviewForUnit() {
        const userInfo = localStorage.getItem('user_info');
        if (!userInfo) return;
        const user = JSON.parse(userInfo);
        const isManager = (parseInt(user.id) === 1) || ['management', 'supervisor'].includes(user.role);
        if (isManager) return; // مدیران کل منوی نظارت را از قبل می‌بینند
        // کاربرِ واحد: همیشه «نظارت بر روتین‌های فعال» نمایش داده شود (بدون شرطِ داشتن روتین)
        const overviewMenu = document.getElementById('navOverview');
        if (overviewMenu) overviewMenu.style.display = 'block';
        const tasksItem = document.getElementById('overviewTasksItem');
        if (tasksItem) tasksItem.style.display = 'none'; // فقط زیرمنوی روتین‌ها برای کاربر واحد
    }
    // ============================================
    // بستن dropdown با کلیک خارج
    // ============================================
    function setupDropdownBehavior() {
        const dropdownToggle = document.getElementById('notificationDropdown');
        const dropdownMenu = document.getElementById('notificationDropdownMenu');

        if (!dropdownToggle || !dropdownMenu) return;

        dropdownToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const isShown = dropdownMenu.classList.contains('show');

            // بستن dropdown اطلاعیه و کادر سرچ سراسری اگه باز بودن
            const annMenu = document.getElementById('announcementDropdownMenu');
            if (annMenu) annMenu.classList.remove('show');
            gsClosePanel();

            if (isShown) {
                dropdownMenu.classList.remove('show');
            } else {
                dropdownMenu.classList.add('show');
                loadNotifications();
            }
        });

        document.addEventListener('click', function(e) {
            if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.classList.remove('show');
            }
        });
    }

    // ============================================
    // سرچ سراسری (فلشِ زیرِ هدر)
    // ============================================
    var gsSearchTimer = null;
    var gsLastQuery = '';
    var gsResultsCache = [];

    function gsGetActiveTypes() {
        var chips = document.querySelectorAll('.gs-type-chip.active');
        return Array.prototype.map.call(chips, function(c) { return c.dataset.type; });
    }

    function gsToggleType(el) {
        el.classList.toggle('active');
        var input = document.getElementById('gsInput');
        var q = (input.value || '').trim();
        if (q) gsRunSearch(q);
    }

    function gsClosePanel() {
        var toggle = document.getElementById('gsToggle');
        var panel = document.getElementById('gsPanel');
        if (toggle) toggle.classList.remove('open');
        if (panel) panel.classList.remove('open');
    }

    function gsTogglePanel() {
        var toggle = document.getElementById('gsToggle');
        var panel = document.getElementById('gsPanel');
        if (!toggle || !panel) return;

        var isOpen = panel.classList.contains('open');
        if (isOpen) {
            gsClosePanel();
            return;
        }

        // بستن dropdown اعلان/اطلاعیه اگه باز بودن
        var notifMenu = document.getElementById('notificationDropdownMenu');
        var annMenu = document.getElementById('announcementDropdownMenu');
        if (notifMenu) notifMenu.classList.remove('show');
        if (annMenu) annMenu.classList.remove('show');

        toggle.classList.add('open');
        panel.classList.add('open');
        var input = document.getElementById('gsInput');
        if (input) {
            setTimeout(function() { input.focus(); }, 50);
        }
    }

    function gsOnInput() {
        clearTimeout(gsSearchTimer);
        var input = document.getElementById('gsInput');
        var q = (input.value || '').trim();
        gsSearchTimer = setTimeout(function() { gsRunSearch(q); }, 300);
    }

    function gsRunSearch(q) {
        var box = document.getElementById('gsResults');
        if (!box) return;

        if (q === '') {
            box.innerHTML = '<div class="gs-hint">برای جستجو تایپ کنید</div>';
            gsLastQuery = q;
            return;
        }
        if (q.length < 2) {
            box.innerHTML = '<div class="gs-hint">حداقل ۲ حرف وارد کنید</div>';
            gsLastQuery = q;
            return;
        }

        gsLastQuery = q;
        var typesParam = gsGetActiveTypes().join(',');
        fetch('../api/search/global.php?q=' + encodeURIComponent(q) + '&types=' + encodeURIComponent(typesParam), {
                headers: { 'Authorization': 'Bearer ' + authToken }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                // اگه کاربر تا وقتِ برگشتِ پاسخ چیزِ دیگه‌ای تایپ کرده، این پاسخِ قدیمی رو نادیده بگیر
                if (q !== gsLastQuery) return;
                if (!data.success) {
                    box.innerHTML = '<div class="gs-hint">خطا در جستجو</div>';
                    return;
                }
                gsRenderResults(data.results || []);
            })
            .catch(function() {
                if (q !== gsLastQuery) return;
                box.innerHTML = '<div class="gs-hint">خطا در ارتباط با سرور</div>';
            });
    }

    function gsRenderResults(results) {
        var box = document.getElementById('gsResults');
        if (!box) return;

        gsResultsCache = results;

        if (!results.length) {
            box.innerHTML = '<div class="gs-empty">نتیجه‌ای یافت نشد</div>';
            return;
        }

        box.innerHTML = results.map(function(r, idx) {
            var snippet = r.snippet ? '<span class="gs-result-snippet">' + esc(r.snippet) + '</span>' : '';
            var isNav = (r.type !== 'announcement' && r.type !== 'notification');
            var tag = isNav ? 'a' : 'div';
            var hrefAttr = isNav ? (' href="' + esc(r.link || '#') + '"') : '';
            return '<' + tag + ' class="gs-result-item"' + hrefAttr + ' onclick="gsResultClick(' + idx + ', event)">' +
                '<div class="gs-result-icon"><i class="bi ' + esc(r.icon) + '"></i></div>' +
                '<div class="gs-result-main">' +
                '<div class="gs-result-title">' + esc(r.title || '—') + '</div>' +
                '<div class="gs-result-meta"><span class="gs-result-type">' + esc(r.type_label) + '</span>' + snippet + '</div>' +
                '</div>' +
                '</' + tag + '>';
        }).join('');
    }

    /** کلیک روی نتیجه — اطلاعیه: بازکردنِ همون مودالِ آشنا (بدونِ رفتن به صفحه‌ی جدید)؛
        نوتیفیکیشن: علامتِ خوانده‌شده + رفتن به آیتمِ مرتبط (اگه لینکی داشت) */
    function gsResultClick(idx, ev) {
        var r = gsResultsCache[idx];
        if (!r) return;

        if (r.type === 'announcement') {
            ev.preventDefault();
            gsClosePanel();
            openAnnouncementModal({
                id: r.id,
                title: r.title,
                content: r.content,
                priority: r.priority,
                created_at: r.created_at
            });
            return;
        }

        if (r.type === 'notification') {
            ev.preventDefault();
            fetch('/api/notifications/mark-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + authToken
                },
                body: JSON.stringify({ id: r.id })
            }).catch(function() {});
            gsClosePanel();
            if (r.link) {
                location.href = r.link;
            } else if (typeof showToast === 'function') {
                showToast(r.message || r.title, 'info');
            }
            return;
        }
        // بقیه‌ی انواع: لینکِ ساده (رفتارِ پیش‌فرضِ <a> کافیه)
    }

    document.addEventListener('click', function(e) {
        var toggle = document.getElementById('gsToggle');
        var panel = document.getElementById('gsPanel');
        if (!toggle || !panel) return;
        if (!toggle.contains(e.target) && !panel.contains(e.target)) {
            gsClosePanel();
        }
    });

    // ============================================
    // بارگذاری اولیه
    // ============================================
    (function() {
        authToken = localStorage.getItem('auth_token');

        console.log('🔧 Header loaded - authToken:', authToken ? 'SET ✅' : 'NOT SET ❌');

        if (!authToken) {
            const currentPath = window.location.pathname;
            if (currentPath.includes('/pages/') && !currentPath.includes('index.php')) {
                console.warn('⚠️ No token - redirecting');
                window.location.href = '../index.php';
                return;
            }
        }
        // تابع جدید برای تنظیم dropdownهای ناوبری
        // تابع جدید برای تنظیم dropdownهای ناوبری
        function setupNavDropdowns() {
            // صبر کن تا Bootstrap کامل لود شود، بعد dropdown ها را فعال کن
            function activateDropdowns() {
                if (typeof bootstrap === 'undefined' || !bootstrap.Dropdown) {
                    // Bootstrap هنوز لود نشده، 200ms بعد دوباره تلاش کن
                    setTimeout(activateDropdowns, 200);
                    return;
                }

                // فعال‌سازی دستی همه dropdown های ناوبری
                var dropdownToggles = document.querySelectorAll('#mainNav .dropdown-toggle');
                dropdownToggles.forEach(function(toggle) {
                    new bootstrap.Dropdown(toggle);
                });

                console.log('✅ Nav dropdowns activated');
            }

            activateDropdowns();
        }

        function highlightActiveMenu() {
            var currentPath = window.location.pathname;

            // همه لینک‌های منو
            var navLinks = document.querySelectorAll('#mainNav .nav-link, #mainNav .dropdown-item');

            navLinks.forEach(function(link) {
                var href = link.getAttribute('href');
                if (!href || href === '#') return;

                // استخراج نام فایل از href
                var linkFile = href.split('/').pop().split('?')[0];
                var currentFile = currentPath.split('/').pop().split('?')[0];

                if (linkFile && currentFile && linkFile === currentFile) {
                    // اگر لینک مستقیم در nav است
                    if (link.classList.contains('nav-link')) {
                        link.classList.add('active-nav-item');
                    }
                    // اگر لینک داخل dropdown است
                    if (link.classList.contains('dropdown-item')) {
                        link.classList.add('active-nav-item');
                        // خود dropdown toggle هم متمایز شود
                        var parentDropdown = link.closest('.nav-item.dropdown');
                        if (parentDropdown) {
                            var toggle = parentDropdown.querySelector('.nav-link');
                            if (toggle) toggle.classList.add('active-nav-item');
                        }
                    }
                }
            });
        }

        // بارگذاریِ اولیه‌یِ ۴ فراخوانیِ سطحِ هدر (اعلان‌ها، پیام‌ها، وضعیتِ
        // حضور، چت) با یک درخواستِ باندل‌شده به‌جایِ ۴ فراخوانیِ هم‌زمانِ جدا —
        // رفرش‌هایِ دوره‌ای (setInterval پایین) همچنان جدا fetch می‌کنن چون
        // فاصله‌ی زمانیِ متفاوتی دارن و هم‌زمان نیستن.
        async function loadHeaderBundle() {
            try {
                const response = await fetch('/api/header/bootstrap.php', {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });
                if (!response.ok) throw new Error('bundle fetch failed');
                const bundle = await response.json();
                if (!bundle.success) throw new Error('bundle response not successful');
                loadAnnouncements(bundle.announcements);
                loadNotifications(bundle.notifications);
                loadAttendanceStatus(bundle.attendance);
                updateChatUnreadBadge(bundle.conversations);
            } catch (error) {
                console.error('❌ خطا در بارگذاریِ باندلِ هدر، fallback به فراخوانیِ جداگانه:', error);
                loadAnnouncements();
                loadNotifications();
                loadAttendanceStatus();
                updateChatUnreadBadge();
            }
        }

        function initializeHeader() {
            console.log('✅ Initializing header...');
            setupAnnouncementDropdown();
            toggleManagerMenu();
            setupOverviewForUnit();
            setupDropdownBehavior();
            setupNavDropdowns();
            if (authToken) {
                loadHeaderBundle();

                // بررسی هر 30 ثانیه
                setInterval(checkNewNotifications, 30000);
                setInterval(loadAttendanceStatus, 60000);
                setInterval(updateChatUnreadBadge, 15000);
            }
            highlightActiveMenu();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeHeader);
        } else {
            initializeHeader();
        }

        // 🔒 وقتی صفحه از bfcache مرورگر (دکمه‌ی back/forward) برمی‌گرده، اسکریپت از
        // نو اجرا نمی‌شه — DOM دقیقاً با همون وضعیتِ قبلی (مثلاً بجِ خوانده‌نشده‌ی
        // قدیمی) فریز می‌مونه تا تایمرهای قبلی به‌طور طبیعی برسن و اصلاحش کنن. برای
        // اینکه بجِ چت/اعلان‌ها و آیکنِ حضور بلافاصله به‌روز باشن، همین‌جا دوباره صدا زده می‌شن.
        window.addEventListener('pageshow', function (event) {
            if (event.persisted && authToken) {
                updateChatUnreadBadge();
                loadAttendanceStatus();
                loadNotifications();
            }
        });
    })();
</script>
<!-- تعریف مسیر صحیح check-subscription -->
<script>
    window.SUBSCRIPTION_CHECK_URL = '/api/organization/check-subscription.php';
</script>
<script>
    window.NAJVA = {};
    var s = document.createElement("script");
    s.src = "https://van.najva.com/static/js/main-script.js";
    s.defer = !0;
    s.id = "najva-mini-script";
    s.setAttribute("data-najva-id", "5dea1c13-3439-4848-8ba3-581729b1a361");
    document.head.appendChild(s);
</script>