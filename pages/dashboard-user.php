<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../config/config.php';
require_once '../includes/version.php';

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
    <title>داشبورد مدیریت - سیستم مدیریت کار</title>


    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>" rel="stylesheet">

    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/persian-date.min.js') ?>"></script>

    <!-- Bootstrap JS — لازم برای مودال و dropdownهای هدر -->
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>

    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <script src="<?= asset('../assets/js/assignee-picker.js') ?>"></script>
    <script src="<?= asset('../../assets/js/sections-helper.js') ?>"></script>
    <script src="<?= asset('../assets/js/persian-datepicker.js') ?>"></script>

</head>

<body>

    <?php include 'header.php'; ?>

    <style>
        /* ═══ چیدمان کلی — بدون اسکرول صفحه‌ای ═══ */
        html,
        body {
            overflow: hidden;
        }

        /* ═══ توکن‌های محلی تم (روشن/تاریک) — این صفحه شامل مودال‌هایی است که
           بیرون از .dash-wrap رندر می‌شوند، پس این متغیرها روی :root تعریف
           می‌شوند نه روی .dash-wrap؛ مقدار پیش‌فرض دقیقاً همان رنگ‌های
           هاردکدشدهٔ قبلی است تا ظاهر حالت روشن هیچ تغییری نکند */
        :root {
            --du-surface: #fff;
            /* پس‌زمینهٔ کارت/پنل/مودال (قبلاً #fff) */
            --du-ink: #000;
            /* متن پررنگ (قبلاً #000) */
            --du-head-bg: #e9e9e9;
            /* پس‌زمینهٔ خاکستریِ هدر کارت */
        }

        :root[data-theme="dark"] {
            --du-surface: var(--surface);
            --du-ink: var(--text-strong);
            --du-head-bg: var(--info-box-bg);
        }

        /* body در custom.css مقدار margin-top: 3.5rem دارد (نوار ثابت) */
        .dash-wrap {
            max-width: 1440px;
            margin: 0 auto;
            padding: 14px 150px 14px 150px;
            height: calc(100vh - 3.5rem);
            display: flex;
            flex-direction: column;
            gap: 14px;
            box-sizing: border-box;
        }

        /* ═══ کارت بخش‌ها ═══ */
        .dash-card {
            background: var(--du-surface);
            border: 1px solid var(--border-soft);
            border-radius: var(--radius);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
        }

        .dash-card-head {
            background: var(--du-head-bg);
            /* هدر خاکستری — کمی پررنگ‌تر */
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .dash-card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: .855rem;
            color: var(--du-ink);
        }

        .dash-card-title i {
            font-size: .9rem;
            color: var(--pm-purple, #7e55b3);
            background: none;
        }

        .dash-see-all {
            font-size: .72rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        /* AssigneePicker یک راهنمای خالی تولید می‌کند که فضا می‌گیرد */
        #planModal .ap-hint:empty {
            display: none;
        }

        /* فشرده‌تر کردن انتخابگر کاربر داخل مودال */
        #planModal .ap-hint {
            margin-bottom: 0;
            font-size: .675rem;
        }



        .dash-see-all:hover {
            text-decoration: underline;
        }

        /* بدنه‌ی اسکرول‌دار — اسکرول‌بار سمت راست */
        .dash-card-body {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            direction: ltr;
        }

        .dash-card-body>* {
            direction: rtl;
        }

        .dash-card-body::-webkit-scrollbar {
            width: 7px;
        }

        .dash-card-body::-webkit-scrollbar-track {
            background: var(--gray-50);
        }

        .dash-card-body::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 99px;
        }

        .dash-card-body::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }

        :root[data-theme="dark"] .dash-card-body::-webkit-scrollbar-track {
            background: var(--info-box-bg);
        }

        :root[data-theme="dark"] .dash-card-body::-webkit-scrollbar-thumb {
            background: var(--border-soft);
        }

        :root[data-theme="dark"] .dash-card-body::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }

        /* ═══ برنامه کاری ═══ */
        .plan-row {
            flex-shrink: 0;
        }

        .plan-row .dash-card-head {
            background: var(--du-surface);
        }

        .plan-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 100px;
            padding: 0 14px 14px 14px;

            margin: auto !important;
        }

        /* ═══ کارت‌های آماری (بازطراحی) ═══ */
        .plan-item {
            display: flex;
            align-items: center;
            gap: 0px;
            background: var(--du-surface);
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            padding: 11px 13px;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .07);
            transition: border-color .15s, box-shadow .15s, transform .12s;
            width: 250px;
        }

        .plan-item:hover {
            border-color: #7e55b3;
            box-shadow: 0 3px 12px rgba(126, 85, 179, .1);
            transform: translateY(-1px);
        }

        /* آیکون گرد (دایره) سمت چپ */
        .plan-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .99rem;
            flex-shrink: 0;
            order: 2;
        }

        .plan-icon.month {
            background: #f0e9fd;
            color: #7e55b3;
        }

        .plan-icon.week {
            background: #ffedd5;
            color: #ea580c;
        }

        .plan-icon.tomor {
            background: #dbeafe;
            color: #2563eb;
        }

        :root[data-theme="dark"] .plan-icon.month {
            background: color-mix(in srgb, #7e55b3 28%, var(--du-surface));
            color: #c9a9f7;
        }

        :root[data-theme="dark"] .plan-icon.week {
            background: color-mix(in srgb, #ea580c 28%, var(--du-surface));
            color: #ffb677;
        }

        :root[data-theme="dark"] .plan-icon.tomor {
            background: color-mix(in srgb, #2563eb 28%, var(--du-surface));
            color: #8fb4fb;
        }

        /* متن سمت راست */
        .plan-text {
            order: 1;
            flex: 1;
            text-align: center;
        }

        .plan-label {
            font-size: .702rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 2px;
        }

        .plan-label.tomor {
            color: #dc2626;
        }

        .plan-label.week {
            color: #2563eb;
        }

        .plan-label.month {
            color: #7e55b3;
        }

        .plan-value {
            font-size: .9rem;
            font-weight: 700;
            color: var(--text-strong);
        }

        /* ═══ بخش کارها ═══ */
        .tasks-row {
            flex: 1.15;
            min-height: 0;
            display: flex;
        }

        .tasks-row .dash-card {
            flex: 1;
        }

        .dash-tabs {
            display: flex;
            gap: 2px;
            border-bottom: 1px solid var(--border-soft);
            padding: 0 12px;
            flex-shrink: 0;
            background: var(--du-surface);
            align-items: center;
        }

        /* گروه فیلترها سمت چپ همین ردیف */
        .dash-tabs .dash-filters {
            margin-right: auto;
            /* هل به چپ (RTL) */
            padding: 6px 0;
        }

        .dash-tab {
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 10px 14px 9px;
            font-size: .783rem;
            color: var(--text-strong);
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .dash-tab:hover {
            color: var(--gray-700);
        }

        .dash-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            font-weight: 600;
        }

        .status-badge {
            border-radius: 5px;
            padding: 4px 9px 4px 9px;
            font-size: 11px;
            width: 130px;
            text-align: center;
        }

        .td-status .status-badge {
            font-weight: bold;
        }

        .td-status .status-completed {
            color: #1b7b39;
            background: #40b86c1a;
        }

        /* راهنمای خالی، فضای بیهوده می‌گیرد */
        #planModal .ap-hint:empty {
            display: none !important;
        }

        .tab-pin {
            font-size: .702rem;
            color: var(--gray-300);
            opacity: 0;
            transition: opacity .15s, color .15s;
            padding: 2px;
            border-radius: 4px;
        }

        .dash-tab:hover .tab-pin {
            opacity: 1;
        }

        .tab-pin:hover {
            color: var(--gray-600);
            background: var(--gray-100);
        }

        .tab-pin.pinned {
            opacity: 1;
            color: var(--warning);
        }

        .dash-filters {
            display: flex;
            gap: 6px;
            padding: 10px 14px;
            flex-shrink: 0;
            align-self: self-end;
        }

        .filter-chip {
            border: 1px solid var(--border-soft);
            background: var(--du-head-bg);
            border-radius: var(--radius-sm);
            padding: 5px 14px;
            font-size: .72rem;
            color: var(--text-strong);
            font-weight: 700;
            cursor: pointer;
            transition: all .15s;
        }

        .filter-chip:hover {
            background: #d8d8d8;
        }

        :root[data-theme="dark"] .filter-chip:hover {
            background: var(--border-soft);
        }

        .filter-chip.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            font-weight: 600;
        }

        .task-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            /* عرض ستون‌ها را ثابت می‌کند */
        }

        .task-table th:nth-child(1),
        .task-table td:nth-child(1) {
            width: 42%;
        }

        /* عنوان */
        .task-table th:nth-child(2),
        .task-table td:nth-child(2) {
            width: 26%;
        }

        /* مهلت  */
        .task-table th:nth-child(3),
        .task-table td:nth-child(3) {
            width: 20%;
        }

        /* وضعیت */
        .task-table th:nth-child(4),
        .task-table td:nth-child(4) {
            width: 12%;
        }

        /* عملیات */
        .task-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--du-surface);
            font-size: .702rem;
            font-weight: 700;
            color: var(--text-strong);
            text-align: right;
            padding: 8px 14px;
            border-bottom: 1px solid var(--gray-100);
            white-space: nowrap;
        }

        :root[data-theme="dark"] .task-table thead th {
            border-bottom-color: var(--border-soft);
        }

        .task-table thead th.col-status {
            text-align: center;
            width: 130px;
        }

        .task-table thead th.col-ops {
            text-align: center;
            width: 90px;
        }

        .task-table tbody tr {
            border-bottom: 1px solid var(--gray-50);
            cursor: pointer;
            transition: background .12s;
        }

        :root[data-theme="dark"] .task-table tbody tr {
            border-bottom-color: var(--border-soft);
        }

        .task-table tbody tr:hover {
            background: var(--gray-50);
        }

        :root[data-theme="dark"] .task-table tbody tr:hover {
            background: var(--info-box-bg);
        }

        .task-table td {
            padding: 0 8px 0 8px;
            font-size: .765rem;
            color: var(--du-ink);
            font-weight: 500;
            vertical-align: middle;
        }

        .td-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .td-title i.doc {
            color: #93c5fd;
            font-size: .855rem;
            flex-shrink: 0;
        }

        .td-title span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ستارهٔ منتخب کنار عنوان — فقط در هاور ردیف */
        .td-star {
            background: none;
            border: none;
            cursor: pointer;
            font-size: .855rem;
            color: var(--gray-300);
            padding: 2px;
            flex-shrink: 0;
            opacity: 0;
            /* پیش‌فرض پنهان */
            transition: opacity .15s, color .15s, transform .12s;
        }

        .task-table tr:hover .td-star {
            opacity: 1;
        }

        /* در هاور ردیف ظاهر شود */
        .td-star:hover {
            color: #fbbf24;
            transform: scale(1.15);
        }

        .td-star.on {
            opacity: 1;
            color: var(--warning);
        }

        /* اگر منتخب است، همیشه دیده شود */
        .td-deadline {
            color: var(--text-muted);
            white-space: nowrap;
        }

        .td-status {
            text-align: center;
        }

        .td-ops {
            text-align: center;
        }

        /* منوی سه‌نقطهٔ جدول اصلی */
        .row-menu-wrap {
            position: relative;
            display: inline-block;
        }

        .row-kebab {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--du-ink);
            font-size: .99rem;
            padding: 4px 8px;
            border-radius: 7px;
            transition: background .12s, color .12s;
        }

        .row-kebab:hover {
            background: #7e55b3;
            color: #fff;
        }

        .row-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 30;
            min-width: 150px;
            margin-top: 4px;
            background: var(--du-surface);
            border: 1px solid #eee;
            border-radius: 11px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
            padding: 5px;
            text-align: right;
        }

        :root[data-theme="dark"] .row-menu {
            border-color: var(--border-soft);
        }

        .row-menu.open {
            display: block;
        }

        .row-menu button {
            width: 100%;
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 9px;
            border-radius: 7px;
            font-size: .765rem;
            color: var(--du-ink);
        }

        .row-menu button:hover {
            background: #7e55b3;
        }

        .row-menu button i {
            font-size: .855rem;
            width: 17px;
        }

        .row-menu .act-approve i {
            color: #00BF8F;
            padding-top: 5px;
            font-size: 180%;
        }

        .row-menu .act-reject i {
            color: #c61717;
            padding-top: 5px;
            font-size: 180%;
        }

        .row-menu .act-delegate i {
            color: #2479b9;
            padding-top: 5px;
            font-size: 180%;
        }

        .row-menu .act-extend i {
            color: #818181;
            padding-top: 5px;
            font-size: 180%;
        }

        .row-menu .empty-hint {
            color: #9ca3af;
            font-size: .702rem;
            padding: 8px 11px;
        }

        :root[data-theme="dark"] .row-menu .empty-hint {
            color: var(--text-muted);
        }

        .st-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: .666rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .st-not_started {
            background: var(--gray-100);
            color: var(--gray-500);
        }

        :root[data-theme="dark"] .st-not_started {
            background: var(--border-soft);
            color: var(--text-muted);
        }

        .st-in_progress {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .st-completed,
        .st-approved {
            background: #40b86c1a;
            color: #1b7b39;
        }

        .st-pending_approval,
        .st-termination_requested {
            background: #fef3c7;
            color: #b45309;
        }

        .st-delegated {
            background: #e0e7ff;
            color: #4338ca;
        }

        .st-rejected {
            background: #fee2e2;
            color: #b91c1c;
        }

        .st-period_done {
            background: #cffafe;
            color: #0e7490;
        }

        .st-overdue {
            background: #fee2e2;
            color: #b91c1c;
        }

        .star-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: .9rem;
            color: var(--gray-300);
            padding: 3px;
            transition: color .15s, transform .12s;
        }

        .star-btn:hover {
            color: #fbbf24;
            transform: scale(1.15);
        }

        .star-btn.on {
            color: var(--warning);
        }

        /* ═══ ردیف پایین ═══ */
        .bottom-row {
            flex: 1;
            min-height: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .routine-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            cursor: pointer;
            border-bottom: 1px solid var(--gray-50);
            transition: background .12s;
        }

        :root[data-theme="dark"] .routine-row {
            border-bottom-color: var(--border-soft);
        }

        .routine-row:hover {
            background: var(--gray-50);
        }

        :root[data-theme="dark"] .routine-row:hover {
            background: var(--info-box-bg);
        }

        .routine-name {
            flex: 1;
            font-size: .774rem;
            color: var(--du-ink);
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .routine-bar-wrap {
            flex: 1.6;
            height: 13px;
            background: var(--gray-100);
            border-radius: 5px;
            overflow: hidden;
        }

        :root[data-theme="dark"] .routine-bar-wrap {
            background: var(--border-soft);
        }

        .routine-bar {
            height: 100%;
            /*border-radius: 999px;*/
        }

        .routine-count {
            font-size: .765rem;
            font-weight: 700;
            color: var(--du-ink);
            min-width: 26px;
            text-align: center;
        }

        .dlg-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            cursor: pointer;
            border-bottom: 1px solid var(--gray-50);
            transition: background .12s;
        }

        :root[data-theme="dark"] .dlg-row {
            border-bottom-color: var(--border-soft);
        }

        .dlg-row:hover {
            background: var(--gray-50);
        }

        :root[data-theme="dark"] .dlg-row:hover {
            background: var(--info-box-bg);
        }

        .dlg-main {
            flex: 1;
            min-width: 0;
        }

        .dlg-title {
            font-size: .765rem;
            color: var(--du-ink);
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dlg-sub {
            font-size: .666rem;
            color: var(--gray-400);
            margin-top: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dlg-days {
            background: #fee2e2;
            color: #b91c1c;
            border-radius: 8px;
            padding: 3px 10px 0px 10px;
            font-size: .666rem;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
            height: 28px;
        }

        .dash-empty {
            text-align: center;
            color: var(--gray-400);
            font-size: .765rem;
            padding: 30px 14px;
        }

        .dash-empty i {
            display: block;
            font-size: 1.44rem;
            margin-bottom: 6px;
            opacity: .5;
        }

        /* حالت یکسانِ «در حال بارگذاری» برای همهٔ کارت‌های داشبورد */
        .dash-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center;
            color: var(--gray-400);
            font-size: .765rem;
            padding: 30px 14px;
        }

        .dash-loading .spinner-border {
            width: 1rem;
            height: 1rem;
            border-width: .15em;
            color: var(--pm-purple);
        }

        /* .task-table td{color:#000} اسپسیفیسیتیِ بالاتری از .dash-loading دارد،
           برای همین رنگ را اینجا دوباره خاکستری می‌کنیم. همچنین display:flex
           روی <td colspan> باعث می‌شود مرورگر colspan را نادیده بگیرد و سلول
           فقط به‌اندازهٔ ستون اول عرض بگیرد؛ پس مستقیماً table-cell + text-align
           نگه داشته می‌شود تا واقعاً وسط کل جدول بیفتد */
        .task-table td.dash-loading {
            display: table-cell;
            text-align: center;
            color: var(--gray-400);
        }

        /* چون display اینجا table-cell است نه flex، gap کار نمی‌کند؛ فاصله با margin */
        .task-table td.dash-loading .spinner-border {
            margin-left: 6px;
        }

        .inst-row {
            border: 1px solid var(--gray-100);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            margin-bottom: 8px;
        }

        :root[data-theme="dark"] .inst-row {
            border-color: var(--border-soft);
        }

        .inst-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }

        .inst-title {
            font-weight: 600;
            font-size: .81rem;
            color: var(--du-ink);
        }

        .inst-meta {
            font-size: .702rem;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .inst-prog {
            height: 7px;
            background: var(--gray-100);
            border-radius: 999px;
            overflow: hidden;
        }

        :root[data-theme="dark"] .inst-prog {
            background: var(--border-soft);
        }

        .inst-prog>div {
            height: 100%;
            border-radius: 999px;
            background: var(--primary);
        }

        @media (max-width: 992px) {

            html,
            body {
                overflow: auto;
            }

            .dash-wrap {
                height: auto;
            }

            .plan-grid {
                grid-template-columns: 1fr;
            }

            .bottom-row {
                grid-template-columns: 1fr;
            }

            .tasks-row,
            .bottom-row {
                min-height: 420px;
            }
        }

        /* ═══════════ مودال برنامه کاری (بنفش) ═══════════ */
        :root {
            --pm-purple: #7e55b3;
            --pm-purple-soft: #7e55b3;
            --pm-purple-dark: #7e55b3;
            --pm-green: #00BF8F;
            --pm-green-soft: #00BF8F;
            --pm-green-dark: #00BF8F;
            --primary: #7e55b3;
            --primary-dark: #7e55b3;
            --primary-light: #7e55b3;
            --primary-gradient: linear-gradient(135deg, #7e55b3 0%, #7e55b3 100%);
            --success: #00BF8F;
            --success-dark: #00BF8F;
        }

        #planModal .modal-content,
        #weekModal .modal-content {
            border: none;
            border-radius: 18px;
            overflow: hidden;
        }

        #planModal .modal-header,
        #weekModal .modal-header {
            background: var(--du-surface);
            border-bottom: 1px solid #f1f1f4;
            padding: 18px 22px;
            align-items: center;
        }

        :root[data-theme="dark"] #planModal .modal-header,
        :root[data-theme="dark"] #weekModal .modal-header {
            border-bottom-color: var(--border-soft);
        }

        #planModal .modal-title,
        #weekModal .modal-title,
        #monthModal .modal-title {
            font-size: 1.035rem;
            font-weight: 700;
            color: #2d2d3a;
            display: flex;
            align-items: center;
            gap: 11px;
            flex-direction: row-reverse;
        }

        :root[data-theme="dark"] #planModal .modal-title,
        :root[data-theme="dark"] #weekModal .modal-title,
        :root[data-theme="dark"] #monthModal .modal-title {
            color: var(--text-strong);
        }

        .pm-head-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #f0e9fd;
            color: var(--pm-purple);
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            font-size: 1.08rem;
        }

        :root[data-theme="dark"] .pm-head-icon {
            background: color-mix(in srgb, var(--pm-purple) 28%, var(--du-surface));
            color: #c9a9f7;
        }

        .pm-head-icon i {
            line-height: 1;
        }

        /* ریشه‌یِ اصلیِ اسکرولِ همیشگی: یک ruleِ عمومیِ ".modal-content" توی
           custom.css (متعلق به یک سیستمِ modal قدیمی‌تر/دیگه) با
           position:fixed + max-height:90vh + overflow-y:auto روی *همه*ی
           modal-content ها از جمله همین مودالِ بوت‌استرپی اعمال می‌شه — یعنی
           صرف‌نظر از هر محاسبه‌ای که تویِ JS برایِ modal-body انجام بدیم،
           خودِ modal-content مستقل بهش سقف/اسکرول تحمیل می‌کرد. اینجا فقط
           برایِ همین مودال خنثی‌ش می‌کنیم تا رفتارِ نرمالِ بوت‌استرپ برگرده */
        #monthModal .modal-content {
            position: relative;
            top: auto;
            left: auto;
            transform: none;
            max-height: none;
            overflow: visible;
        }

        /* قبلاً چون modal-content با position:fixed از جریانِ عادی خارج بود،
           محدودیتِ عرضِ پیش‌فرضِ بوت‌استرپ روی modal-dialog (بدونِ کلاسِ
           modal-lg/modal-xl، فقط ۵۰۰px) اصلاً اثر نداشت — با رفعِ position:fixed
           بالا، این محدودیت آشکار شد و باید صریحاً بازش کنیم */
        #monthModal .modal-dialog {
            max-width: 1300px;
            margin: 25px auto !important;
        }

        /* طوری‌که همه‌ی روزهایِ ماه بدونِ اسکرول در یک نگاه دیده بشن — فضایِ
           عمودیِ هدر/فوتر رو تا حدِ ممکن پس می‌گیریم (ارتفاعِ واقعیِ ردیف‌ها
           هم به‌صورتِ پویا در JS، بر اساسِ همین فضایِ آزادشده، محاسبه می‌شه) */
        #monthModal .modal-header {
            padding-top: 0;
        }

        #monthModal .modal-footer {
            padding-bottom: 0;
        }

        #planModal .btn-close,
        #weekModal .btn-close,
        #monthModal .btn-close {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background-color: var(--du-surface);
            border: 1px solid var(--gray-300);
            background-size: 10px;
            opacity: 1;
            margin: 0;
            transition: background-color .15s, border-color .15s;
        }

        #planModal .btn-close:hover,
        #weekModal .btn-close:hover,
        #monthModal .btn-close:hover {
            background-color: var(--gray-100);
            border-color: var(--gray-400);
        }

        :root[data-theme="dark"] #planModal .btn-close:hover,
        :root[data-theme="dark"] #weekModal .btn-close:hover,
        :root[data-theme="dark"] #monthModal .btn-close:hover {
            background-color: var(--border-soft);
            border-color: var(--text-muted);
        }

        #planModal .modal-body {
            padding: 14px 18px;
            max-height: 60vh;
            overflow-y: auto;
        }

        /* ── ردیف کار (ساده: عنوان + سه‌نقطه) ── */
        .pm-row {
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #f1f1f4;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 9px;
            transition: background .12s, border-color .12s;
        }

        /* هر دو تعریف .pm-row در فایل (این کارت ساده و ردیف مودال هفتگی/اکشن)
           را با یک override پوشش می‌دهد؛ چون این قاعده کلاس-محور است، اسپسیفیسیتیِ
           بالاترش (root+attr+class) روی هر دو مقدار پایه غلبه می‌کند */
        :root[data-theme="dark"] .pm-row {
            border-color: var(--border-soft);
        }

        .pm-row:hover {
            background: #7e55b3;
            border-color: #7e55b3;
        }

        /* اسپسیفیسیتیِ override بالای تیره (root+attr+class) از .pm-row:hover
           (class+pseudo) بیشتر است؛ برای اینکه رنگ بنفشِ هاور در تم تاریک هم
           باقی بماند، اینجا دوباره تصریح می‌شود */
        :root[data-theme="dark"] .pm-row:hover {
            border-color: #7e55b3;
        }

        .pm-row.removing {
            opacity: 0;
            transform: translateX(-20px);
            transition: .3s;
        }

        .pm-title {
            flex: 1;
            min-width: 0;
            cursor: pointer;
            font-size: .828rem;
            font-weight: 600;
            color: #374151;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        :root[data-theme="dark"] .pm-title {
            color: var(--text-strong);
        }

        /* ── منوی سه‌نقطه ── */
        .pm-menu-wrap {
            position: relative;
            flex-shrink: 0;
        }

        .pm-kebab {
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            font-size: 1.035rem;
            padding: 5px 9px;
            border-radius: 8px;
            transition: background .12s, color .12s;
        }

        :root[data-theme="dark"] .pm-kebab {
            color: var(--text-muted);
        }

        .pm-kebab:hover {
            background: var(--pm-purple-soft);
            color: var(--pm-purple);
        }

        /*.pm-menu {*/
        /*    display: none;*/
        /*    position: absolute;*/
        /*    top: 100%;*/
        /*    left: 0;*/
        /*    z-index: 20;*/
        /*    min-width: 155px;*/
        /*    margin-top: 5px;*/
        /*    background: #fff;*/
        /*    border: 1px solid #eee;*/
        /*    border-radius: 12px;*/
        /*    box-shadow: 0 8px 26px rgba(0, 0, 0, .12);*/
        /*    padding: 6px;*/
        /*    overflow: hidden;*/
        /*    margin-right:120px;*/
        /*}*/

        .pm-menu.open {
            display: block;
        }

        .pm-menu button {
            width: 100%;
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: .792rem;
            color: var(--du-ink);
            text-align: right;
            transition: background .1s;
        }

        .pm-menu button:hover {
            background: #7e55b3;
        }

        .pm-menu button i {
            font-size: .9rem;
            width: 18px;
            text-align: center;
        }

        .pm-menu button.act-approve i {
            color: #00BF8F;
        }

        .pm-menu button.act-reject i {
            color: #dc2626;
        }

        .pm-menu button.act-delegate i {
            color: var(--pm-purple);
        }

        .pm-menu button.act-extend i {
            color: var(--pm-purple);
        }

        /* ── فرم عملیات ── */
        .pm-form {
            display: none;
            margin-top: 11px;
            padding-top: 11px;
            border-top: 1px dashed #e5e7eb;
        }

        .pm-form.open {
            display: block;
        }

        .pm-form-title {
            font-size: .756rem;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 9px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        :root[data-theme="dark"] .pm-form-title {
            color: var(--text-muted);
        }

        .pm-form textarea,
        .pm-form select,
        .pm-form input[type="text"] {
            width: 100%;
            font-size: .765rem;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            padding: 9px 11px;
            margin-bottom: 9px;
        }

        :root[data-theme="dark"] .pm-form textarea,
        :root[data-theme="dark"] .pm-form select,
        :root[data-theme="dark"] .pm-form input[type="text"] {
            background: var(--du-surface);
            color: var(--du-ink);
            border-color: var(--border-soft);
        }

        .pm-form textarea {
            resize: vertical;
            min-height: 60px;
        }

        .pm-form-actions {
            display: flex;
            gap: 8px;
        }

        .pm-btn {
            border: none;
            border-radius: 9px;
            cursor: pointer;
            padding: 8px 18px;
            font-size: .756rem;
            font-weight: 600;
            transition: opacity .12s;
        }

        .pm-btn:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .pm-btn-primary {
            background: var(--pm-purple);
            color: #fff;
        }

        .pm-btn-danger {
            background: #dc2626;
            color: #fff;
        }

        .pm-btn-ghost {
            background: #f3f4f6;
            color: #6b7280;
        }

        :root[data-theme="dark"] .pm-btn-ghost {
            background: var(--info-box-bg);
            color: var(--text-muted);
        }

        .pm-empty {
            text-align: center;
            padding: 40px 20px;
            color: #9ca3af;
            font-size: .81rem;
        }

        .pm-empty i {
            font-size: 1.98rem;
            display: block;
            margin-bottom: 10px;
            color: #d1d5db;
        }

        :root[data-theme="dark"] .pm-empty,
        :root[data-theme="dark"] .pm-empty i {
            color: var(--text-muted);
        }

        /* مودال عملیات: اجازه بده تقویم بیرون بزند */
        #rowActModal .modal-body {
            overflow: visible;
        }

        #rowActModal .modal-content {
            overflow: visible;
        }

        #rowActModal .persian-datepicker-wrapper {
            position: relative;
        }

        #rowActModal .persian-datepicker {
            position: absolute;
            z-index: 2200;
            /* اگر پایین ج
            ا نبود، بالای اینپوت باز شود */
        }

        #rowActModal.modal {
            overflow: visible;
        }

        #rowActModal .modal-dialog {
            overflow: visible;
        }

        /* رنگ‌های اینلاینِ هدر/دکمهٔ بستنِ این مودال (در HTML، پایین صفحه) در تم تاریک
           باید override شوند؛ چون style اینلاین اولویت دارد، از !important استفاده می‌شود */
        :root[data-theme="dark"] #rowActModal .modal-header {
            border-bottom-color: var(--border-soft) !important;
        }

        :root[data-theme="dark"] #rowActModal .btn-close {
            background-color: var(--info-box-bg) !important;
        }

        /* ═══════════ مودال هفتگی ═══════════ */
        #weekModal .modal-dialog {
            max-width: 96vw;
        }

        #weekModal .modal-body {
            padding: 12px 16px;
            max-height: 68vh;
            overflow: auto;
        }

        .wk-nav {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-right: auto;
            /* هل به سمت چپ (RTL) */
            margin-left: 12px;
            background: var(--pm-purple);
            border-radius: 10px;
            padding: 4px 6px;
            gap: 0px;
        }

        .wk-nav button {
            border-radius: 8px;
            border: none;
            cursor: pointer;
            color: white;
            font-size: .855rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .12s;
            background-color: transparent;
        }

        .wk-nav button:hover {
            background: rgba(255, 255, 255, .22);
        }

        .wk-nav .wk-label {
            font-size: .792rem;
            font-weight: 700;
            color: white;
            min-width: 78px;
            text-align: center;
        }

        .wk-grid {
            display: flex;
            gap: 8px;
            min-width: 0;
        }

        .wk-col {
            background: #fafafa;
            border: 1px solid #f0f0f3;
            border-radius: 12px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            min-width: 0;
            flex: 1 1 0;
            transition: flex-grow .25s ease;
        }

        :root[data-theme="dark"] .wk-col {
            background: var(--info-box-bg);
            border-color: var(--border-soft);
        }

        .wk-grid:hover .wk-col {
            opacity: .6;
            transition: opacity .25s, flex-grow .25s;
        }

        .wk-grid:hover .wk-col:hover {
            flex-grow: 1.5;
            opacity: 1;
        }

        .wk-col-head {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }

        :root[data-theme="dark"] .wk-col-head {
            border-bottom-color: var(--border-soft);
        }

        .wk-day {
            font-size: .738rem;
            font-weight: 700;
            color: var(--du-ink);
        }

        .wk-date {
            font-size: .612rem;
            color: #9ca3af;
            margin-top: 2px;
        }

        :root[data-theme="dark"] .wk-date {
            color: var(--text-muted);
        }

        .wk-cards {
            display: flex;
            flex-direction: column;
            gap: 6px;
            overflow-y: auto;
            max-height: 46vh;
            direction: ltr;
            text-align: right;
        }

        .wk-card {
            background: var(--du-surface);
            border: 1px solid #f0f0f3;
            border-radius: 9px;
            padding: 8px 9px;
            position: relative;
        }

        :root[data-theme="dark"] .wk-card {
            border-color: var(--border-soft);
        }

        .wk-card-title {
            font-size: .666rem;
            color: var(--du-ink);
            font-weight: 600;
            line-height: 1.4;
            cursor: pointer;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            padding-left: 30px;
        }

        .wk-card-time {
            font-size: .594rem;
            color: #9ca3af;
            margin-top: 3px;
        }

        :root[data-theme="dark"] .wk-card-time {
            color: var(--text-muted);
        }

        .wk-card .pm-kebab {
            position: absolute;
            left: 3px;
            font-size: .81rem;
            padding: 0px 5px;
        }

        .wk-col-empty {
            text-align: center;
            color: #d1d5db;
            font-size: .63rem;
            padding: 14px 0;
        }

        :root[data-theme="dark"] .wk-col-empty {
            color: var(--text-muted);
        }

        /* فرم عملیات داخل کارت هفتگی */
        .wk-card .pm-form {
            margin-top: 8px;
            padding-top: 8px;
        }

        .wk-card .pm-form textarea,
        .wk-card .pm-form select,
        .wk-card .pm-form input[type="text"] {
            font-size: .648rem;
            padding: 6px 8px;
        }

        .wk-card .pm-btn {
            padding: 5px 10px;
            font-size: .648rem;
        }

        .wk-card .pm-form-title {
            font-size: .648rem;
        }

        /* ═══ مودال ماهانه ═══ */
        .mo-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
            transition: grid-template-columns .6s cubic-bezier(.4, 0, .2, 1), grid-template-rows .6s cubic-bezier(.4, 0, .2, 1);
            will-change: grid-template-columns, grid-template-rows;
        }

        .mo-weekday {
            text-align: center;
            font-size: .702rem;
            font-weight: 700;
            color: var(--text-muted);
            padding-bottom: 6px;
            border-bottom: 1px solid #eee;
        }

        :root[data-theme="dark"] .mo-weekday {
            border-bottom-color: var(--border-soft);
        }

        /* اندازهٔ همهٔ سلول‌ها یکسان است (ارتفاع از grid-template-rows می‌آید، نه از محتوا) */
        .mo-cell {
            background: #f2f2f6;
            border: 1px solid #e2e2ea;
            border-radius: 10px;
            padding: 6px;
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
            min-height: 0;
            overflow: hidden;
        }

        :root[data-theme="dark"] .mo-cell {
            background: var(--info-box-bg);
            border-color: var(--border-soft);
        }

        .mo-cell.mo-hover {
            background: #fff;
            box-shadow: 0 10px 28px rgba(0, 0, 0, .18);
            z-index: 5;
        }

        :root[data-theme="dark"] .mo-cell.mo-hover {
            background: var(--du-surface);
            box-shadow: 0 10px 28px rgba(0, 0, 0, .45);
        }

        .mo-cell-empty {
            background: transparent;
            border: none;
        }

        .mo-cell-date {
            font-size: .666rem;
            font-weight: 700;
            color: var(--du-ink);
            text-align: center;
            flex-shrink: 0;
        }

        .mo-today .mo-cell-date {
            color: var(--pm-purple);
        }

        .mo-cell-tasks {
            display: flex;
            flex-direction: column;
            gap: 2px;
            overflow-y: auto;
            min-height: 0;
        }

        .mo-task-title {
            font-size: .576rem;
            color: var(--du-ink);
            background: var(--du-surface);
            border: 1px solid #f0f0f3;
            border-radius: 4px;
            padding: 1px 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            flex-shrink: 0;
        }

        :root[data-theme="dark"] .mo-task-title {
            border-color: var(--border-soft);
        }

        .mo-cell.mo-hover .mo-task-title {
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
        }

        .mo-more {
            font-size: .54rem;
            color: var(--pm-purple);
            font-weight: 700;
            text-align: center;
            flex-shrink: 0;
            cursor: pointer;
        }

        .mo-more:hover {
            text-decoration: underline;
        }

        /* پیام کوتاه */
        #pmToast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(80px);
            background: #111827;
            color: #fff;
            padding: 11px 22px;
            border-radius: 10px;
            font-size: .774rem;
            z-index: 3000;
            opacity: 0;
            transition: .25s;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .2);
        }

        #pmToast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        #pmToast.ok {
            background: #00BF8F;
        }

        #pmToast.err {
            background: #b91c1c;
        }

        /* تقویمی که به body منتقل شده */
        body>.persian-datepicker.pm-floating {
            position: fixed !important;
            z-index: 3100 !important;
        }

        /* ردیف کار در مودال */
        .pm-row {
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #f3f4f6;
            border-radius: 10px;
            padding: 8px 8px;
            margin-bottom: 8px;
            transition: background .12s, border-color .12s;

        }

        .pm-row:hover {
            background: #f9fafb;
            border-color: #e5e7eb;
            cursor: pointer;
        }

        .pm-row.removing {
            opacity: 0;
            transform: translateX(-20px);
            transition: opacity .3s, transform .3s;
        }

        .pm-main {
            flex: 1;
            min-width: 0;
            cursor: pointer;
        }

        .pm-title {
            font-size: .81rem;
            font-weight: 600;
            color: #374151;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* منوی سه‌نقطه */
        .pm-menu-wrap {
            position: relative;
            flex-shrink: 0;
        }

        .pm-kebab {
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            font-size: .99rem;
            padding: 4px 8px;
            border-radius: 6px;
            transition: background .12s, color .12s;
        }

        .pm-kebab:hover {
            background: #f3f4f6;
            color: #374151;
        }

        :root[data-theme="dark"] .pm-kebab:hover {
            background: var(--info-box-bg);
            color: var(--text-strong);
        }

        /* وقتی فرم عملیات باز است، دکمهٔ سه‌نقطه مخفی شود.
           هم ظاهر تمیزتر می‌شود، هم کاربر گیج نمی‌شود. */
        .pm-row.form-open .pm-menu-wrap {
            display: none;
        }

        /* دکمه همیشه بالای ردیف بماند، نه وسط */
        .pm-menu-wrap {
            align-self: flex-start;
        }

        .pm-menu {
            display: none;
            position: fixed;
            /* ← مختصات را JS حساب می‌کند */
            z-index: 3000;
            /* ← بالاتر از مودال */
            min-width: 165px;
            background: var(--du-surface);
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, .1);
            padding: 5px;
            overflow: hidden;
            margin: 5px 120px;
        }

        .pm-menu.open {
            display: block;
        }

        .pm-menu button {
            width: 100%;
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 11px;
            border-radius: 7px;
            font-size: .765rem;
            color: var(--du-ink);
            text-align: right;
            transition: background .1s;
        }

        .pm-menu button:hover {
            background: #f3f4f6;
        }

        :root[data-theme="dark"] .pm-menu button:hover {
            background: var(--info-box-bg);
        }

        .pm-menu button i {
            font-size: .855rem;
            width: 16px;
        }

        .pm-menu button.act-approve i {
            color: #00BF8F;
        }

        .pm-menu button.act-reject i {
            color: #dc2626;
        }

        .pm-menu button.act-delegate i {
            color: #2563eb;
        }

        .pm-menu button.act-extend i {
            color: #ea580c;
        }

        /* فرم عملیات (داخل ردیف باز می‌شود) */
        .pm-form {
            display: none;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #e5e7eb;
        }

        .pm-form.open {
            display: block;
        }

        .pm-form-title {
            font-size: .738rem;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .pm-form textarea,
        .pm-form select,
        .pm-form input[type="text"] {
            width: 100%;
            font-size: .756rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 10px;
            margin-bottom: 8px;
        }

        .pm-form textarea {
            resize: vertical;
            min-height: 62px;
        }

        .pm-form-actions {
            display: flex;
            gap: 7px;
        }

        .pm-btn {
            border: none;
            border-radius: 8px;
            cursor: pointer;
            padding: 7px 16px;
            font-size: .738rem;
            font-weight: 600;
            transition: opacity .12s;
        }

        .pm-btn:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .pm-btn-primary {
            background: #2563eb;
            color: #fff;
        }

        .pm-btn-danger {
            background: #dc2626;
            color: #fff;
        }

        .pm-btn-ghost {
            background: #f3f4f6;
            color: #6b7280;
        }

        /* پیام کوتاه (toast) */
        #pmToast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(80px);
            background: #111827;
            color: #fff;
            padding: 11px 22px;
            border-radius: 10px;
            font-size: .774rem;
            z-index: 3000;
            opacity: 0;
            transition: opacity .25s, transform .25s;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .2);
        }

        body>.ap-dropdown.pm-floating-drop {
            position: fixed !important;
            z-index: 3200 !important;
            max-width: none !important;
        }

        /* راهنمای خالیِ AssigneePicker، فضای بیهوده می‌گیرد */
        #planModal .ap-hint:empty {
            display: none !important;
        }

        #pmToast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        #pmToast.ok {
            background: #00BF8F;
        }

        #pmToast.err {
            background: #b91c1c;
        }

        /* انتخابگر تاریخ داخل مودال باید بالای بقیه باشد */
        #planModal .persian-datepicker {
            z-index: 2100;
        }

        .ra-input {
            width: 100%;
            font-size: .774rem;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            padding: 9px 11px;
            margin-bottom: 10px;
        }

        :root[data-theme="dark"] .ra-input {
            background: var(--du-surface);
            color: var(--du-ink);
            border-color: var(--border-soft);
        }

        .ra-input:focus {
            outline: none;
            border-color: #7e55b3;
        }

        :root[data-theme="dark"] .ra-input:focus {
            border-color: #7e55b3;
        }

        textarea.ra-input {
            resize: vertical;
        }

        .ra-actions {
            display: flex;
            gap: 8px;
        }

        /* لیست کاربران قابل جستجو */
        .ra-userlist {
            max-height: 180px;
            overflow-y: auto;
            border: 1px solid #f1f1f4;
            border-radius: 9px;
            margin-bottom: 10px;
        }

        :root[data-theme="dark"] .ra-userlist {
            border-color: var(--border-soft);
        }

        .ra-user {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 11px;
            cursor: pointer;
            font-size: .765rem;
            border-bottom: 1px solid #f7f7f9;
            transition: background .1s;
        }

        :root[data-theme="dark"] .ra-user {
            border-bottom-color: var(--border-soft);
        }

        .ra-user:last-child {
            border-bottom: none;
        }

        .ra-user:hover {
            background: #7e55b3;
        }

        .ra-user.sel {
            background: #7e55b3;
            color: #fff;
            font-weight: 600;
        }

        .ra-user i {
            color: #9ca3af;
            font-size: .9rem;
        }

        .ra-user.sel i {
            color: #7e55b3;
        }

        .ra-user-empty {
            text-align: center;
            color: #9ca3af;
            font-size: .738rem;
            padding: 14px;
        }

        :root[data-theme="dark"] .ra-user i,
        :root[data-theme="dark"] .ra-user-empty {
            color: var(--text-muted);
        }

        /* اسپسیفیسیتیِ override بالا (root+attr+class+type) از .ra-user.sel i
           (class+class+type) بیشتر است؛ رنگ بنفشِ آیتمِ انتخاب‌شده را برمی‌گردانیم */
        :root[data-theme="dark"] .ra-user.sel i {
            color: #7e55b3;
        }

        /* انتخابگر تاریخ داخل این مودال بالای بقیه */
        #rowActModal .persian-datepicker {
            z-index: 2200;
        }

        /* ═══ استثنا: فقط در این صفحه، هدر بالا بنفش با متن/آیکون سفید ═══ */
        body .navbar {
            background: var(--pm-purple) !important;
        }

        body .navbar-brand {
            background: none !important;
            -webkit-text-fill-color: #fff !important;
            color: #fff !important;
        }

        body .navbar #userName {
            color: #fff !important;
        }

        body .navbar .nav-link {
            color: #fff !important;
        }

        body .navbar .nav-link:hover {
            color: #F4EFFF !important;
        }

        body .navbar .settings-btn i,
        body .navbar .settings-btn svg,
        body .navbar .notification-bell,
        body .navbar .announcement-bell {
            color: #fff !important;
            stroke: #fff !important;
        }

        body .navbar .navbar-divider {
            background: rgba(255, 255, 255, .35);
        }

        body .navbar .navbar-toggler-icon {
            filter: brightness(0) invert(1);
        }

        body .mobile-menu-btn .hamburger-line {
            background: #fff !important;
        }
    </style>


    <div class="dash-wrap">

        <!-- ═══ برنامه کاری ═══ -->
        <div class="dash-card plan-row">
            <div class="dash-card-head">
                <div class="dash-card-title">
                    <i class="bi bi-calendar3" style="color:#2563eb;width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size: .9rem;flex-shrink:0;"></i><span>برنامه کاری</span>
                </div>
            </div>
            <div class="plan-grid">
                <div class="plan-item" onclick="openPlanModal('tomorrow')">
                    <div class="plan-text">
                        <div class="plan-label tomor">فردا</div>
                        <div class="plan-value" id="statTomorrow">— وظیفه</div>
                    </div>
                    <div class="plan-icon tomor"><i class="bi bi-calendar-event"></i></div>
                </div>
                <div class="plan-item" onclick="openPlanModal('week')">
                    <div class="plan-text">
                        <div class="plan-label week">این هفته</div>
                        <div class="plan-value" id="statWeek">— وظیفه</div>
                    </div>
                    <div class="plan-icon week"><i class="bi bi-calendar-week"></i></div>
                </div>
                <div class="plan-item" onclick="openPlanModal('month')">
                    <div class="plan-text">
                        <div class="plan-label month">این ماه</div>
                        <div class="plan-value" id="statMonth">— وظیفه</div>
                    </div>
                    <div class="plan-icon month"><i class="bi bi-calendar-check"></i></div>
                </div>
            </div>
        </div>


        <!-- ═══ کارها ═══ -->
        <div class="tasks-row">
            <div class="dash-card">
                <div class="dash-card-head">
                    <div class="dash-card-title">
                        <i class="bi bi-calendar3" style="color:#2563eb;width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size: .9rem;flex-shrink:0;"></i><span>کارها</span>
                    </div>
                    <a href="#" class="dash-see-all" id="tasksSeeAll">
                        مشاهده همه <i class="bi bi-chevron-left"></i>
                    </a>
                </div>

                <div class="dash-tabs">
                    <button class="dash-tab" data-tab="mine">
                        <span>کارهای من</span>
                        <i class="bi bi-pin-angle tab-pin" data-pin="mine"></i>
                    </button>
                    <button class="dash-tab" data-tab="delegated">
                        <span>کارهای واگذار شده</span>
                        <i class="bi bi-pin-angle tab-pin" data-pin="delegated"></i>
                    </button>
                    <button class="dash-tab" data-tab="recent">
                        <span>فعالیت های اخیر</span>
                        <i class="bi bi-pin-angle tab-pin" data-pin="recent"></i>
                    </button>
                    <button class="dash-tab" data-tab="starred">
                        <span>منتخب</span>
                        <i class="bi bi-pin-angle tab-pin" data-pin="starred"></i>
                    </button>

                    <div class="dash-filters">
                        <button class="filter-chip active" data-filter="all">همه</button>
                        <button class="filter-chip" data-filter="today">امروز</button>
                        <button class="filter-chip" data-filter="overdue">عقب افتاده</button>
                    </div>
                </div>

                <div class="dash-card-body">
                    <table class="task-table">
                        <thead>
                            <tr>
                                <th>عنوان وظیفه</th>
                                <th id="thDeadline">مهلت انجام</th>
                                <th class="col-status" id="thStatus">وضعیت</th>
                                <th class="col-ops" id="thOps">عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="taskTbody">
                            <tr>
                                <td colspan="4" class="dash-loading"><span class="spinner-border spinner-border-sm" role="status"></span>در حال بارگذاری…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


        <!-- ═══ ردیف پایین ═══ -->
        <div class="bottom-row">

            <div class="dash-card">
                <div class="dash-card-head">
                    <div class="dash-card-title">
                        <i class="bi bi-arrow-repeat" style="color:#7e55b3;width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size: .9rem;flex-shrink:0;"></i><span>فرآیندهای جاری</span>
                    </div>
                    <a href="workflow-monitor.php" class="dash-see-all">
                        مشاهده همه <i class="bi bi-chevron-left"></i>
                    </a>
                </div>
                <div class="dash-card-body">
                    <div id="routineList">
                        <div class="dash-loading"><span class="spinner-border spinner-border-sm" role="status"></span>در حال بارگذاری…</div>
                    </div>
                </div>
            </div>

            <div class="dash-card">
                <div class="dash-card-head">
                    <div class="dash-card-title">
                        <i class="bi bi-clock" style="color:#dc2626;width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size: .9rem;flex-shrink:0;"></i>
                        <span>کارهای واگذار شده (تاخیر دار)</span>
                    </div>
                    <a href="#" class="dash-see-all" id="delayedSeeAll">
                        مشاهده همه <i class="bi bi-chevron-left"></i>
                    </a>
                </div>
                <div class="dash-card-body">
                    <div id="delayedList">
                        <div class="dash-loading"><span class="spinner-border spinner-border-sm" role="status"></span>در حال بارگذاری…</div>
                    </div>
                </div>
            </div>

        </div>
    </div>


    <!-- مودال: نمونه‌های فعال یک روتین -->
    <div class="modal fade" id="instancesModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="instModalTitle">نمونه‌های فعال</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="instModalBody"></div>
            </div>
        </div>
    </div>
    <!-- ═══════════ مودال برنامه کاری ═══════════ -->
    <div class="modal fade" id="planModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span id="pmTitle">برنامه کاری</span>
                        <span class="pm-head-icon"><i class="bi bi-calendar3" id="pmIcon"></i></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="pmBody"></div>
                <div class="modal-footer" style="padding:10px 18px;">
                    <a href="my-tasks.php" class="btn btn-sm btn-primary">مشاهده همه کارها</a>
                </div>
            </div>
        </div>
    </div>
    <!-- ═══════════ مودال هفتگی ═══════════ -->
    <div class="modal fade" id="weekModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="    max-width: 1300px;">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span>برنامه هفتگی</span>
                        <span class="pm-head-icon"><i class="bi bi-calendar-week"></i></span>
                    </h5>

                    <!-- ناوبری هفته — کنار دکمهٔ بستن -->
                    <div class="wk-nav">
                        <button onclick="wkShift(-1)" title="هفتهٔ قبل"><i class="bi bi-chevron-right"></i></button>
                        <span class="wk-label" id="wkLabel">این هفته</span>
                        <button onclick="wkShift(1)" title="هفتهٔ بعد"><i class="bi bi-chevron-left"></i></button>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="wk-grid" id="wkGrid"></div>
                </div>
                <div class="modal-footer" style="padding:12px 12px 0 12px;">
                    <a href="my-tasks.php?filter=week" class="btn btn-sm" style="background:var(--pm-purple);color:#fff;">مشاهده همه کارها</a>
                </div>
            </div>
        </div>
    </div>
    <!-- ═══════════ مودال ماهانه ═══════════ -->
    <div class="modal fade" id="monthModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="    max-width: 1300px;">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span>برنامه این ماه</span>
                        <span class="pm-head-icon"><i class="bi bi-calendar-check"></i></span>
                    </h5>

                    <!-- ناوبری ماه — کنار دکمهٔ بستن -->
                    <div class="wk-nav">
                        <button onclick="moShift(-1)" title="ماه قبل"><i class="bi bi-chevron-right"></i></button>
                        <span class="wk-label" id="moLabel">این ماه</span>
                        <button onclick="moShift(1)" title="ماه بعد"><i class="bi bi-chevron-left"></i></button>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:12px 16px;max-height:76vh;overflow:auto;">
                    <div class="mo-grid" id="moGrid" onmouseover="moGridOver(event)" onmouseleave="moGridLeave()"></div>
                </div>
                <div class="modal-footer" style="padding:12px 12px 0 12px;">
                    <a href="my-tasks.php?filter=month" id="moSeeAllBtn" class="btn btn-sm" style="background:var(--pm-purple);color:#fff;">مشاهده همه کارها</a>
                </div>
            </div>
        </div>
    </div>
    <!-- مودال کوچک عملیات تک‌کار -->
    <div class="modal fade" id="rowActModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border:none;border-radius:16px;">
                <div class="modal-header" style="padding:16px 18px;border-bottom:1px solid #f1f1f4;">
                    <h6 class="modal-title" style="display:flex;align-items:center;gap:9px;font-weight:700;">
                        <span id="rowActTitle">عملیات</span>
                        <i id="rowActIcon" class="bi"></i>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        style="width:32px;height:32px;border-radius:50%;background-color:#f3f4f6;opacity:1;"></button>
                </div>
                <div class="modal-body" id="rowActBody" style="padding:16px 18px;"></div>
            </div>
        </div>
    </div>
    <!-- پیام کوتاه -->
    <div id="pmToast"></div>
    <script src="<?= asset('/assets/js/task-filters.js') ?>"></script>
    <script>
        /* متغیر authToken از header.php می‌آید */

        const LS_STARRED = 'mgrDash.starred';
        const LS_DEFTAB = 'mgrDash.defaultTab';
        const currentUser = JSON.parse(localStorage.getItem('user_info') || '{}');

        let currentTab = 'mine';
        let currentFilter = 'all';
        let tasksDataReady = false; // تا وقتی داده‌های واقعی نیامده، renderTasks نباید حالت خالی نشان بدهد

        const store = {
            mine: [],
            delegated: [],
            recent: []
        };

        /* ───────── کمکی‌ها ───────── */
        function toFa(n) {
            if (n === null || n === undefined || n === '') return '—';
            return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹' [d]);
        }

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

        function faDate(str) {
            if (!str) return '—';
            const d = new Date(str);
            if (isNaN(d)) return '—';
            const [jy, jm, jd] = jalaliOf(d);
            const p = n => String(n).padStart(2, '0');
            return toFa(`${jy}/${p(jm)}/${p(jd)}`);
        }

        function dateOnly(v) {
            if (!v) return null;
            const d = new Date(v);
            if (isNaN(d)) return null;
            d.setHours(0, 0, 0, 0);
            return d;
        }

        /* ───────── منتخب ───────── */
        function getStarred() {
            try {
                return JSON.parse(localStorage.getItem(LS_STARRED) || '[]');
            } catch {
                return [];
            }
        }

        function isStarred(k) {
            return getStarred().includes(k);
        }

        function toggleStar(key, btn, ev) {
            ev.stopPropagation();
            let list = getStarred();
            if (list.includes(key)) {
                list = list.filter(k => k !== key);
                btn.classList.remove('on');
                btn.innerHTML = '<i class="bi bi-star"></i>';
            } else {
                list.push(key);
                btn.classList.add('on');
                btn.innerHTML = '<i class="bi bi-star-fill"></i>';
            }
            localStorage.setItem(LS_STARRED, JSON.stringify(list));
            if (currentTab === 'starred') renderTasks();
        }

        /* ───────── تب پیش‌فرض ───────── */
        function getDefaultTab() {
            return localStorage.getItem(LS_DEFTAB) || 'mine';
        }

        function togglePin(tabKey, ev) {
            ev.stopPropagation();
            if (localStorage.getItem(LS_DEFTAB) === tabKey) {
                localStorage.removeItem(LS_DEFTAB);
            } else {
                localStorage.setItem(LS_DEFTAB, tabKey);
            }
            refreshPins();
        }

        function refreshPins() {
            const def = localStorage.getItem(LS_DEFTAB);
            document.querySelectorAll('.tab-pin').forEach(p => {
                const key = p.dataset.pin;
                const on = (def === key);
                p.className = `bi ${on ? 'bi-pin-angle-fill' : 'bi-pin-angle'} tab-pin${on ? ' pinned' : ''}`;
                p.dataset.pin = key;
                p.title = on ? 'تب پیش‌فرض (برای لغو کلیک کنید)' : 'تعیین به‌عنوان تب پیش‌فرض';
            });
        }

        /* ───────── دریافت داده ───────── */
        async function apiGet(url) {
            try {
                const res = await fetch(url, {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                return await res.json();
            } catch (e) {
                console.error('API error:', url, e);
                return {
                    success: false
                };
            }
        }

        function pickList(d) {
            if (!d) return [];
            if (Array.isArray(d)) return d;

            const keys = ['tasks', 'data', 'workflows', 'instances', 'items', 'result', 'rows'];
            for (const k of keys)
                if (Array.isArray(d[k])) return d[k];

            if (d.data && typeof d.data === 'object') {
                for (const k of keys)
                    if (Array.isArray(d.data[k])) return d.data[k];
                for (const k of Object.keys(d.data))
                    if (Array.isArray(d.data[k])) return d.data[k];
            }
            console.warn('pickList: ساختار ناشناخته →', d);
            return [];
        }

        async function loadAll() {
            const [mine, delegated, recent, routines] = await Promise.all([
                apiGet('../api/tasks/my-tasks.php'),
                apiGet('../api/tasks/delegated-tasks.php'),
                apiGet('../api/workflows/list.php'),
                apiGet('../api/workflows/active-summary.php')
            ]);

            store.mine = pickList(mine);
            store.delegated = pickList(delegated);
            store.recent = pickList(recent);
            tasksDataReady = true;

            renderStats();
            renderTasks();
            renderRoutines(routines);
            renderDelayed();
        }

        /* ───────── کارت‌های آماری (بر پایه‌ی تقویم) ───────── */
        function renderStats() {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const [tjy, tjm] = jalaliOf(today);

            const tomorrow = new Date(today);
            tomorrow.setDate(today.getDate() + 1);

            // هفته‌ی جاری: شنبه تا جمعه
            // getDay(): ۰=یکشنبه … ۶=شنبه
            const daysSinceSat = (today.getDay() + 1) % 7;
            const weekStart = new Date(today);
            weekStart.setDate(today.getDate() - daysSinceSat);
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 6);

            let cMonth = 0,
                cWeek = 0,
                cTomorrow = 0;

            store.mine.forEach(t => {
                if (t.status === 'completed' || t.status === 'approved') return;
                const d = dateOnly(TF.effectiveDue(t));
                if (!d) return;

                // این ماه = همان ماهِ شمسیِ جاری (شامل روزهای گذشته‌ی همین ماه)
                const [jy, jm] = jalaliOf(d);
                if (jy === tjy && jm === tjm) cMonth++;

                if (d >= weekStart && d <= weekEnd) cWeek++;
                if (d.getTime() === tomorrow.getTime()) cTomorrow++;
            });

            document.getElementById('statMonth').textContent = `${toFa(cMonth)} وظیفه`;
            document.getElementById('statWeek').textContent = `${toFa(cWeek)} وظیفه`;
            document.getElementById('statTomorrow').textContent = `${toFa(cTomorrow)} وظیفه`;
        }

        /* ───────── جدول کارها ───────── */
        function starKey(t) {
            const src = t._src || currentTab;
            return `${src === 'recent' ? 'wf' : 'task'}:${t.id}`;
        }

        function getTabList() {
            if (currentTab === 'starred') {
                const starred = getStarred();
                const all = [
                    ...store.mine.map(t => ({
                        ...t,
                        _src: 'mine'
                    })),
                    ...store.delegated.map(t => ({
                        ...t,
                        _src: 'delegated'
                    })),
                    ...store.recent.map(t => ({
                        ...t,
                        _src: 'recent'
                    }))
                ];
                const seen = new Set();
                return all.filter(t => {
                    const k = starKey(t);
                    if (!starred.includes(k) || seen.has(k)) return false;
                    seen.add(k);
                    return true;
                });
            }
            return (store[currentTab] || []).map(t => ({
                ...t,
                _src: currentTab
            }));
        }

        function applyFilter(list) {
            if (currentFilter === 'today') return list.filter(t => TF.isDueToday(t, currentUser));
            if (currentFilter === 'overdue') return list.filter(t => TF.isOverdue(t, currentUser));
            return list;
        }

        function renderTasks() {
            if (!tasksDataReady) return; // هنوز داده‌ای نیامده — پیام «در حال بارگذاری» دست‌نخورده بماند

            const tbody = document.getElementById('taskTbody');
            const list = applyFilter(getTabList());
            // ── هدر پویا: تب فعالیت‌های اخیر، ستون «تاریخ/ساعت» دارد ──
            const isRecentTab = (currentTab === 'recent');
            const thDeadline = document.getElementById('thDeadline');
            const thStatus = document.getElementById('thStatus');
            const thOps = document.getElementById('thOps');

            if (isRecentTab) {
                thDeadline.textContent = 'تاریخ / ساعت';
                thStatus.style.display = 'none';
                thOps.style.display = 'none';
            } else {
                thDeadline.textContent = 'مهلت انجام';
                thStatus.style.display = '';
                thOps.style.display = '';
            }
            if (!list.length) {
                tbody.innerHTML = `<tr><td colspan="4" class="dash-empty">
                <i class="bi bi-inbox"></i>کاری برای نمایش وجود ندارد</td></tr>`;
                return;
            }

            tbody.innerHTML = list.map(t => {
                const key = starKey(t);
                const on = isStarred(key);
                const isWf = (t._src === 'recent');
                const link = isWf ? `workflow-monitor.php?id=${t.id}` : `task-detail.php?id=${t.id}`;
                const safe = (t.title || '').replace(/"/g, '&quot;');
                const acts = isWf ? [] : pmActions(t);

                // ── ردیف فعالیت اخیر: عنوان + تاریخ/ساعت ──
                if (isWf) {
                    return `<tr onclick="location.href='${link}'">
                    <td>
                        <div class="td-title">
                            <button class="td-star${on ? ' on' : ''}"
                                    title="${on ? 'حذف از منتخب' : 'افزودن به منتخب'}"
                                    onclick="toggleStar('${key}', this, event)">
                                <i class="bi bi-star${on ? '-fill' : ''}"></i>
                            </button>
                            <span title="${safe}">${t.title || '—'}</span>
                        </div>
                    </td>
                    <td class="td-deadline">${faDateTime(t.started_at || t.updated_at)}</td>
                </tr>`;
                }

                // ── ردیف کار عادی ──
                return `<tr onclick="location.href='${link}'">
                <td>
                    <div class="td-title">
                        <button class="td-star${on ? ' on' : ''}"
                                title="${on ? 'حذف از منتخب' : 'افزودن به منتخب'}"
                                onclick="toggleStar('${key}', this, event)">
                            <i class="bi bi-star${on ? '-fill' : ''}"></i>
                        </button>
                        <span title="${safe}">${t.title || '—'}</span>
                    </div>
                </td>
                <td class="td-deadline">${faDate(TF.effectiveDue(t))}</td>
                <td class="td-status">${TF.statusBadge(t, currentUser)}</td>
                <td class="td-ops">${rowMenuHtml(t.id, acts)}</td>
            </tr>`;
            }).join('');
        }

        /* منوی سه‌نقطهٔ جدول اصلی */
        function rowMenuHtml(taskId, acts) {
            const items = [];
            if (acts.includes('approve')) items.push(`<button class="act-approve" onclick="rowAction(${taskId},'approve',event)"><i class="bi bi-check-lg"></i> تایید</button>`);
            if (acts.includes('reject')) items.push(`<button class="act-reject" onclick="rowAction(${taskId},'reject',event)"><i class="bi bi-x"></i> رد</button>`);
            if (acts.includes('delegate')) items.push(`<button class="act-delegate" onclick="rowAction(${taskId},'delegate',event)"><i class="bi bi-arrow-right-short"></i> ارجاع</button>`);
            if (acts.includes('extend')) items.push(`<button class="act-extend" onclick="rowAction(${taskId},'extend',event)"><i class="bi bi-check-lg"></i> تمدید موعد</button>`);

            const body = items.length ? items.join('') : `<div class="empty-hint">عملیاتی موجود نیست</div>`;

            return ` 
            <div class="row-menu-wrap">
                <button class="row-kebab" onclick="rowToggleMenu(${taskId}, event)"><i class="bi bi-three-dots-vertical"></i></button>
                <div class="row-menu" id="rowMenu-${taskId}">${body}</div>
            </div>`;
        }

        /* باز/بسته کردن منوی جدول */
        function rowToggleMenu(taskId, ev) {
            ev.stopPropagation();
            const menu = document.getElementById('rowMenu-' + taskId);
            const wasOpen = menu.classList.contains('open');
            document.querySelectorAll('.row-menu.open').forEach(m => m.classList.remove('open'));
            if (!wasOpen) menu.classList.add('open');
        }
        document.addEventListener('click', () => {
            document.querySelectorAll('.row-menu.open').forEach(m => m.classList.remove('open'));
        });

        /* اجرای عملیات از جدول — از مودال استفاده می‌کند */
        function rowAction(taskId, action, ev) {
            ev.stopPropagation();
            document.querySelectorAll('.row-menu.open').forEach(m => m.classList.remove('open'));

            // کار موردنظر را پیدا کن و در مودال باز کن
            const t = store.mine.find(x => Number(x.id) === Number(taskId)) ||
                store.delegated.find(x => Number(x.id) === Number(taskId));
            if (!t) {
                pmToast('کار یافت نشد', 'err');
                return;
            }

            // از همان مودال «برنامه کاری» به‌عنوان بستر فرم استفاده می‌کنیم
            rowActionInModal(t, action);
        }
        /* ═══════════════════════════════════════════════
                   عملیات تک‌کار از جدول (مودال کوچک مینیمال)
                   ═══════════════════════════════════════════════ */

        let rowModal = null;

        function rowActionInModal(task, action) {

            // ── تأیید: بدون فرم، مستقیم ──────────────────
            if (action === 'approve') {
                rowSubmit(task.id, 'approve', null);
                return;
            }

            // ── بقیه: مودال کوچک ─────────────────────────
            const titles = {
                reject: ['رد کار', 'bi-x-lg', '#dc2626'],
                delegate: ['ارجاع کار', 'bi-arrow-left-right', '#7e55b3'],
                extend: ['تمدید موعد', 'bi-calendar-plus', '#7e55b3'],
            };
            const [title, icon, color] = titles[action];

            document.getElementById('rowActTitle').textContent = title;
            document.getElementById('rowActIcon').className = 'bi ' + icon;
            document.getElementById('rowActIcon').style.color = color;

            document.getElementById('rowActBody').innerHTML = rowFormHtml(task, action);

            if (!rowModal) {
                rowModal = new bootstrap.Modal(document.getElementById('rowActModal'));
            }
            rowModal.show();
            if (action === 'delegate') {
                const setup = () => {
                    AssigneePicker.init({
                        container: '#rowAssigneePicker',
                        users: pmUsers,
                        sections: (typeof sectionList !== 'undefined') ? sectionList : [],
                        sectionMap: (typeof sectionMap !== 'undefined') ? sectionMap : {},
                        showSections: false, // فقط کاربر (نه واحد) برای ارجاع
                        allowAll: false
                    });
                };
                if (pmUsers.length === 0) {
                    pmLoadUsers().then(setup);
                } else {
                    setTimeout(setup, 120);
                }
            }
            // انتخابگر تاریخ برای تمدید
            if (action === 'extend' && typeof window.reinitPersianDatepickers === 'function') {
                setTimeout(() => window.reinitPersianDatepickers(), 120);
            }

            if (action === 'delegate') {
                if (pmUsers.length === 0) {
                    pmLoadUsers().then(() => rowFillUsers());
                } else {
                    setTimeout(rowFillUsers, 100);
                }
            }
        }

        /* قالب فرم هر عملیات — مینیمال */
        function rowFormHtml(task, action) {

            if (action === 'reject') {
                return `
                    <textarea id="rowNote" class="ra-input"
                              placeholder="دلیل رد (الزامی)" rows="3"></textarea>
                    <div class="ra-actions">
                        <button class="pm-btn pm-btn-danger" onclick="rowSubmit(${task.id},'reject',this)">رد کردن</button>
                        <button class="pm-btn pm-btn-ghost" onclick="rowModal.hide()">انصراف</button>
                    </div>`;
            }

            if (action === 'delegate') {
                return `
                    <div id="rowAssigneePicker"></div>
                    <div class="ra-actions" style="margin-top:10px;">
                        <button class="pm-btn pm-btn-primary" onclick="rowSubmit(${task.id},'delegate',this)">ارجاع</button>
                        <button class="pm-btn pm-btn-ghost" onclick="rowModal.hide()">انصراف</button>
                    </div>`;
            }

            if (action === 'extend') {
                return `
                    <div class="persian-datepicker-wrapper">
                        <input type="text" class="persian-datepicker-input ra-input"
                               id="rowDate" placeholder="انتخاب موعد جدید..." readonly>
                        <div class="persian-datepicker">
                            <div class="datepicker-header">
                                <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                <span class="datepicker-current"></span>
                                <button type="button" class="datepicker-nav" data-action="next">◄</button>
                            </div>
                            <div class="datepicker-weekdays">
                                <div class="datepicker-weekday">ش</div><div class="datepicker-weekday">ی</div>
                                <div class="datepicker-weekday">د</div><div class="datepicker-weekday">س</div>
                                <div class="datepicker-weekday">چ</div><div class="datepicker-weekday">پ</div>
                                <div class="datepicker-weekday">ج</div>
                            </div>
                            <div class="datepicker-days"></div>
                            <button type="button" class="datepicker-today-btn">امروز</button>
                        </div>
                    </div>
                    <div class="ra-actions">
                        <button class="pm-btn pm-btn-primary" onclick="rowSubmit(${task.id},'extend',this)">ثبت درخواست</button>
                        <button class="pm-btn pm-btn-ghost" onclick="rowModal.hide()">انصراف</button>
                    </div>`;
            }
            return '';
        }

        /* ─── لیست کاربران قابل جستجو (ارجاع) ─── */
        function rowFillUsers() {
            const box = document.getElementById('rowUserList');
            if (!box) return;
            rowRenderUsers(pmUsers);
        }

        function rowFilterUsers() {
            const q = document.getElementById('rowUserSearch').value.trim().toLowerCase();
            const filtered = pmUsers.filter(u =>
                Number(u.id) !== Number(currentUser.id) &&
                `${u.first_name} ${u.last_name}`.toLowerCase().includes(q)
            );
            rowRenderUsers(filtered);
        }

        function rowRenderUsers(users) {
            const box = document.getElementById('rowUserList');
            if (!box) return;
            const list = users.filter(u => Number(u.id) !== Number(currentUser.id));
            if (!list.length) {
                box.innerHTML = `<div class="ra-user-empty">کاربری یافت نشد</div>`;
                return;
            }
            box.innerHTML = list.slice(0, 50).map(u => `
                <div class="ra-user" onclick="rowPickUser(${u.id}, this, '${(u.first_name + ' ' + u.last_name).replace(/'/g, '')}')">
                    <i class="bi bi-person-circle"></i>
                    <span>${u.first_name} ${u.last_name}</span>
                </div>`).join('');
        }

        function rowPickUser(id, el, name) {
            document.getElementById('rowUserId').value = id;
            document.querySelectorAll('.ra-user.sel').forEach(x => x.classList.remove('sel'));
            el.classList.add('sel');
            document.getElementById('rowUserSearch').value = name;
        }

        /* ─── ارسال به سرور ─── */
        async function rowSubmit(taskId, action, btn) {
            let url, payload;

            if (action === 'approve') {
                url = '../api/tasks/approve.php';
                payload = {
                    task_id: taskId,
                    approve: true,
                    notes: ''
                };
            } else if (action === 'reject') {
                const note = document.getElementById('rowNote').value.trim();
                if (!note) {
                    pmToast('لطفاً دلیل رد را بنویسید', 'err');
                    return;
                }
                url = '../api/tasks/approve.php';
                payload = {
                    task_id: taskId,
                    approve: false,
                    notes: note
                };
            } else if (action === 'delegate') {
                const sel = AssigneePicker.getValue();
                if (!sel || sel.type !== 'user' || !sel.value || sel.value === '__all_users__') {
                    pmToast('لطفاً کاربر مقصد را انتخاب کنید', 'err');
                    return;
                }
                url = '../api/tasks/delegate.php';
                payload = {
                    task_id: taskId,
                    to_user_id: Number(sel.value),
                    notes: '',
                    share_history: true
                };
            } else if (action === 'extend') {
                const d = document.getElementById('rowDate').getAttribute('data-date');
                if (!d) {
                    pmToast('لطفاً موعد جدید را انتخاب کنید', 'err');
                    return;
                }
                url = '../api/tasks/request-deadline.php';
                payload = {
                    task_id: taskId,
                    new_deadline: d,
                    reason: 'تمدید از داشبورد'
                };
            }

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            }

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'عملیات ناموفق بود');

                const msgs = {
                    approve: 'کار تایید شد',
                    reject: 'کار رد شد',
                    delegate: 'کار ارجاع شد',
                    extend: 'درخواست تمدید ثبت شد'
                };
                pmToast(msgs[action] || 'انجام شد', 'ok');

                if (rowModal) rowModal.hide();
                pmRefresh();

            } catch (err) {
                pmToast(err.message || 'خطا', 'err');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'تلاش دوباره';
                }
            }
        }
        /* ───────── فرآیندهای جاری ───────── */
        function renderRoutines(data) {
            const box = document.getElementById('routineList');
            const list = (data && data.success) ? (data.routines || []) : [];

            if (!list.length) {
                box.innerHTML = `<div class="dash-empty">
                <i class="bi bi-diagram-3"></i>فرآیند فعالی وجود ندارد</div>`;
                return;
            }

            const max = Math.max(...list.map(r => r.active_count), 1);
            const colors = ['#2563eb', '#0d9488', '#00BF8F', '#ea580c', '#7e55b3'];

            box.innerHTML = list.map((r, i) => {
                const pct = Math.round((r.active_count / max) * 100);
                const name = (r.template_name || '').replace(/'/g, "\\'");
                return `<div class="routine-row" onclick="location.href='workflow-monitor.php?template=${r.template_id}'">
                <div class="routine-name" title="${r.template_name}">${r.template_name}</div>
                <div class="routine-bar-wrap">
                    <div class="routine-bar" style="width:${pct}%; background:${colors[i % colors.length]};"></div>
                </div>
                <div class="routine-count">${toFa(r.active_count)}</div>
            </div>`;
            }).join('');
        }

        async function openInstances(templateId, templateName) {
            const modal = new bootstrap.Modal(document.getElementById('instancesModal'));
            document.getElementById('instModalTitle').textContent = `نمونه‌های فعال — ${templateName}`;
            document.getElementById('instModalBody').innerHTML = `<div class="dash-loading"><span class="spinner-border spinner-border-sm" role="status"></span>در حال بارگذاری…</div>`;
            modal.show();

            const all = pickList(await apiGet('../api/workflows/list.php'));

            const items = all.filter(w =>
                (w.template_id == templateId || w.workflow_id == templateId) &&
                (w.status === 'in_progress' || w.status === 'delayed')
            );

            if (!items.length) {
                document.getElementById('instModalBody').innerHTML =
                    `<div class="dash-empty"><i class="bi bi-inbox"></i>نمونه‌ی فعالی یافت نشد</div>`;
                return;
            }

            document.getElementById('instModalBody').innerHTML = items.map(w => {
                const prog = parseInt(w.progress) || 0;
                return `<div class="inst-row">
                <div class="inst-head">
                    <div class="inst-title">${w.title || '—'}</div>
                    ${statusBadge(w)}
                </div>
                <div class="inst-meta">مرحله فعلی: ${w.current_stage_name || 'نامشخص'}</div>
                <div class="inst-prog"><div style="width:${prog}%"></div></div>
                <div style="text-align:left; font-size: .675rem; color:var(--gray-500); margin-top:4px;">
                    ${toFa(prog)}٪
                </div>
            </div>`;
            }).join('');
        }

        /* ───────── کارهای واگذار تأخیردار ───────── */
        function daysLate(t) {
            const d = dateOnly(TF.effectiveDue(t));
            if (!d) return 0;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const diff = Math.floor((today - d) / 86400000);
            return diff > 0 ? diff : 0;
        }

        function renderDelayed() {
            const box = document.getElementById('delayedList');
            const list = store.delegated.filter(t => TF.isOverdue(t, currentUser));

            if (!list.length) {
                box.innerHTML = `<div class="dash-empty">
                <i class="bi bi-check2-circle"></i>کار واگذارشده‌ی تأخیرداری وجود ندارد</div>`;
                return;
            }

            box.innerHTML = list.map(t => {
                const who = [t.assignee_first_name, t.assignee_last_name].filter(Boolean).join(' ');
                const safe = (t.title || '').replace(/"/g, '&quot;');
                return `<div class="dlg-row" onclick="location.href='task-detail.php?id=${t.id}'">
                <div class="dlg-main">
                    <div class="dlg-title" title="${safe}">${t.title || '—'}</div>
                    ${who ? `<div class="dlg-sub">مسئول: ${who}</div>` : ''}
                </div>
                <div class="dlg-days">${toFa(daysLate(t))} روز</div>
            </div>`;
            }).join('');
        }

        /* ───────── رویدادها ───────── */
        function updateTasksSeeAll() {
            const a = document.getElementById('tasksSeeAll');
            const map = {
                mine: 'my-tasks.php',
                delegated: 'delegated-tasks.php',
                recent: 'workflow-monitor.php',
                starred: '#'
            };
            a.href = map[currentTab] || '#';
            a.style.visibility = (currentTab === 'starred') ? 'hidden' : 'visible';
        }

        function switchTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.dash-tab').forEach(b =>
                b.classList.toggle('active', b.dataset.tab === tab));

            // فیلترها در تب فعالیت‌های اخیر معنا ندارند → مخفی
            const filters = document.querySelector('.dash-filters');
            if (filters) {
                filters.style.display = (tab === 'recent') ? 'none' : '';
            }

            updateTasksSeeAll();
            renderTasks();
        }

        document.addEventListener('DOMContentLoaded', () => {

            document.querySelectorAll('.dash-tab').forEach(btn =>
                btn.addEventListener('click', () => switchTab(btn.dataset.tab)));

            document.querySelectorAll('.tab-pin').forEach(pin =>
                pin.addEventListener('click', ev => togglePin(pin.dataset.pin, ev)));

            document.querySelectorAll('.filter-chip').forEach(chip => {
                chip.addEventListener('click', () => {
                    currentFilter = chip.dataset.filter;
                    document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
                    chip.classList.add('active');
                    renderTasks();
                });
            });

            // «مشاهده همه»ی کارهای واگذار تأخیردار
            document.getElementById('delayedSeeAll').addEventListener('click', ev => {
                ev.preventDefault();
                // «مشاهده همه»ی کارهای واگذار تأخیردار
                location.href = 'delegated-tasks.php?filter=overdue';
            });

            refreshPins();
            switchTab(getDefaultTab());
            loadAll();
        });

        /* ═══════════════════════════════════════════════════════════════════
        مودال برنامه کاری — داشبورد مدیریت
        ───────────────────────────────────────────────────────────────────
        این بلوک را در انتهای <script> اصلی dashboard-manager.php اضافه کن
        (قبل از بسته‌شدن script).

        پیش‌نیازها که از قبل در صفحه هستند:
            • authToken           (از header.php)
            • currentUser         (کاربر جاری)
            • store.mine          (کارهای کاربر جاری)
            • TF.*                (task-filters.js)
            • toFa, faDate        (توابع صفحه)
            • bootstrap.Modal
        ═══════════════════════════════════════════════════════════════════ */


        /* ───────────── وضعیت مودال ───────────── */
        let pmScope = 'tomorrow'; // month | week | tomorrow
        let pmUsers = []; // لیست کاربران (برای ارجاع)
        let pmModal = null; // نمونهٔ Bootstrap Modal
        let pmOpenMenu = null; // منوی سه‌نقطهٔ باز (فقط یکی هم‌زمان)

        /* تاریخ شمسی + ساعت */
        function faDateTime(str) {
            if (!str) return '—';
            const d = new Date(str);
            if (isNaN(d)) return '—';
            const [jy, jm, jd] = jalaliOf(d);
            const p = n => String(n).padStart(2, '0');
            const date = `${jy}/${p(jm)}/${p(jd)}`;
            const time = `${p(d.getHours())}:${p(d.getMinutes())}`;
            return toFa(`${date} - ${time}`);
        }
        /* ═══════════════════════════════════════════════════
           ۱) فیلتر بازه
           ═══════════════════════════════════════════════════ */

        /**
         * آیا موعد این کار در بازهٔ انتخابی است؟
         * منطق دقیقاً همان کارت‌های آماری است تا عدد و لیست همخوان باشند.
         */
        function pmInScope(t, scope) {
            if (TF.isDone(t)) return false;

            const due = TF.effectiveDue(t);
            if (!due) return false;

            const d = new Date(due);
            d.setHours(0, 0, 0, 0);
            if (isNaN(d)) return false;

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (scope === 'tomorrow') {
                const tom = new Date(today);
                tom.setDate(today.getDate() + 1);
                return d.getTime() === tom.getTime();
            }

            if (scope === 'week') {
                // هفتهٔ شمسی: شنبه تا جمعه
                const start = new Date(today);
                start.setDate(today.getDate() - ((today.getDay() + 1) % 7));
                const end = new Date(start);
                end.setDate(start.getDate() + 6);
                return d >= start && d <= end;
            }

            if (scope === 'month') {
                // ماه شمسی جاری
                const jd = jOf(d);
                const jt = jOf(today);
                return jd && jt && jd[0] === jt[0] && jd[1] === jt[1];
            }

            return false;
        }


        /* ═══════════════════════════════════════════════════
           ۲) تشخیص عملیات مجاز
           ═══════════════════════════════════════════════════ */

        /**
         * چه گزینه‌هایی برای این کار به کاربر جاری نشان داده شود؟
         *
         *   • منتظر تأیید من  →  تأیید / رد
         *   • مسئولش من      →  ارجاع / تمدید موعد
         */
        function pmActions(t) {
            const myId = Number(currentUser.id);

            // ⚠️ نکتهٔ مهم: هنگام pending_approval، سامانه assignee_id را روی
            //    تأییدکننده می‌گذارد. پس اگر assignee من باشم و وضعیت
            //    pending_approval، یعنی منتظر تأیید من است.
            if (t.status === 'pending_approval' && Number(t.assignee_id) === myId) {
                return ['approve', 'reject'];
            }

            if (Number(t.assignee_id) === myId) {
                return ['delegate', 'extend'];
            }

            return []; // فقط مشاهده
        }


        /* ═══════════════════════════════════════════════════
           ۳) باز کردن مودال
           ═══════════════════════════════════════════════════ */

        const PM_META = {
            tomorrow: {
                title: 'برنامه فردا',
                icon: 'bi-calendar-event'
            }
        };

        async function openPlanModal(scope) {
            // «این هفته» → مودال هفتگی جداگانه
            if (scope === 'week') {
                openWeekModal();
                return;
            }
            // «این ماه» → مودال ماهانه جداگانه
            if (scope === 'month') {
                openMonthModal();
                return;
            }

            pmScope = scope;

            const meta = PM_META[scope];
            document.getElementById('pmTitle').textContent = meta.title;
            document.getElementById('pmIcon').className = 'bi ' + meta.icon;

            if (!pmModal) {
                pmModal = new bootstrap.Modal(document.getElementById('planModal'));
            }
            pmModal.show();

            pmRender();

            if (pmUsers.length === 0) {
                pmLoadUsers();
            }
        }

        /* ═══════════════════════════════════════════════════
           ۴) رسم لیست
           ═══════════════════════════════════════════════════ */

        function pmRender() {
            const box = document.getElementById('pmBody');
            const list = store.mine.filter(t => pmInScope(t, pmScope));

            if (!list.length) {
                box.innerHTML = `
                    <div class="pm-empty">
                        <i class="bi bi-check2-circle"></i>
                        کاری در این بازه وجود ندارد
                    </div>`;
                return;
            }

            // کارهای منتظر اقدام، بالاتر
            list.sort((a, b) => pmActions(b).length - pmActions(a).length);

            box.innerHTML = list.map(t => {
                const acts = pmActions(t);
                const safe = (t.title || '').replace(/"/g, '&quot;');

                return `
                <div class="pm-row" id="pmRow-${t.id}">
                    <div style="flex:1; min-width:0;">
                        <div class="pm-title" onclick="location.href='task-detail.php?id=${t.id}'"
                             title="${safe}">${t.title || '—'}</div>
                        <div class="pm-form" id="pmForm-${t.id}"></div>
                    </div>
                    ${acts.length ? pmKebabHtml(t.id, acts) : ''}
                </div>`;
            }).join('');
        }

        /* منوی سه‌نقطه — مشترک بین مودال روزانه و هفتگی */
        function pmKebabHtml(taskId, acts) {
            return `
            <div class="pm-menu-wrap">
                <button class="pm-kebab" onclick="pmToggleMenu(${taskId}, event)" title="عملیات">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <div class="pm-menu" id="pmMenu-${taskId}">
                    ${acts.includes('approve') ? `
                        <button class="act-approve" onclick="pmOpenForm(${taskId}, 'approve')">
                            <i class="bi bi-check-lg"></i> تایید
                        </button>` : ''}
                    ${acts.includes('reject') ? `
                        <button class="act-reject" onclick="pmOpenForm(${taskId}, 'reject')">
                            <i class="bi bi-x-lg"></i> رد
                        </button>` : ''}
                    ${acts.includes('delegate') ? `
                        <button class="act-delegate" onclick="pmOpenForm(${taskId}, 'delegate')">
                            <i class="bi bi-arrow-left-right"></i> ارجاع
                        </button>` : ''}
                    ${acts.includes('extend') ? `
                        <button class="act-extend" onclick="pmOpenForm(${taskId}, 'extend')">
                            <i class="bi bi-calendar-plus"></i> تمدید موعد
                        </button>` : ''}
                </div>
            </div>`;
        }


        /* ═══════════════════════════════════════════════════
           ۵) منوی سه‌نقطه
           ═══════════════════════════════════════════════════ */

        function pmToggleMenu(taskId, ev) {
            ev.stopPropagation();

            const btn = ev.target.closest('.pm-kebab');
            if (!btn) return;

            const menu = document.getElementById('pmMenu-' + taskId);
            const wasOpen = menu.classList.contains('open');

            pmCloseMenus();
            if (wasOpen) return;

            // 🔑 کلید حل مشکل:
            // منو را به <body> منتقل می‌کنیم تا از «مبدأ مختصاتِ» مودال آزاد شود.
            // (مودال Bootstrap برای انیمیشن از transform استفاده می‌کند و این
            //  باعث می‌شود position:fixed نسبت به مودال حساب شود، نه پنجره)
            document.body.appendChild(menu);
            menu.classList.add('open');

            // حالا مختصات را می‌گیریم
            const r = btn.getBoundingClientRect();
            const mh = menu.offsetHeight;
            const mw = menu.offsetWidth;

            // ── عمودی: زیر دکمه، مگر جا نباشد ──
            let top = (window.innerHeight - r.bottom < mh + 12) ?
                r.top - mh - 4 // بالای دکمه
                :
                r.bottom + 4; // زیر دکمه
            if (top < 8) top = 8;

            // ── افقی (RTL): لبهٔ راست منو با لبهٔ راست دکمه هم‌تراز ──
            let left = r.right - mw;
            if (left < 8) left = 8;
            if (left + mw > window.innerWidth - 8) left = window.innerWidth - mw - 8;

            menu.style.top = top + 'px';
            menu.style.left = left + 'px';

            pmOpenMenu = menu;
        }

        function pmCloseMenus() {
            document.querySelectorAll('.pm-menu').forEach(m => {
                m.classList.remove('open');

                // منو را به ردیف خودش برگردان (اگر در body مانده)
                const taskId = m.id.replace('pmMenu-', '');
                const wrap = document.querySelector('#pmRow-' + taskId + ' .pm-menu-wrap');

                if (wrap && m.parentElement !== wrap) {
                    wrap.appendChild(m);
                }
            });
            pmOpenMenu = null;
        }

        // کلیک بیرون → بستن منو
        document.addEventListener('click', () => pmCloseMenus());
        document.getElementById('planModal')?.addEventListener('scroll', () => pmCloseMenus(), true);


        // کلید Esc → بستن منو
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') pmCloseMenus();
        });


        /* ═══════════════════════════════════════════════════
           ۶) لیست کاربران (برای ارجاع)
           ═══════════════════════════════════════════════════ */

        async function pmLoadUsers() {
            try {
                // نام فارسی واحدها را بارگذاری کن (sections-helper.js)
                // این تابع، متغیر سراسری sectionMap را می‌سازد: { sales: 'فروش', ... }
                if (typeof loadSectionMap === 'function') {
                    await loadSectionMap();
                }

                const res = await fetch('../api/users/list.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();

                if (data.success) {
                    pmUsers = data.users || [];
                }
            } catch (e) {
                console.error('خطا در دریافت کاربران:', e);
            }
        }


        /* ═══════════════════════════════════════════════════
           ۷) باز کردن فرم عملیات
           ═══════════════════════════════════════════════════ */

        function pmOpenForm(taskId, action) {
            pmCloseMenus();

            const box = document.getElementById('pmForm-' + taskId);

            // اگر همین فرم باز است، ببند
            if (box.classList.contains('open') && box.dataset.action === action) {
                pmCloseForm(taskId);
                return;
            }

            box.dataset.action = action;
            box.innerHTML = pmFormHtml(taskId, action);
            box.classList.add('open');

            // دکمهٔ سه‌نقطه را مخفی کن (تا فرم بسته شود)
            document.getElementById('pmRow-' + taskId).classList.add('form-open');

            // انتخابگر تاریخ شمسی را برای المان تازه‌ساخته‌شده فعال کن
            // انتخابگر تاریخ شمسی
            if (action === 'extend' && typeof window.reinitPersianDatepickers === 'function') {
                setTimeout(() => {
                    window.reinitPersianDatepickers();
                    pmFloatDatepicker(taskId);
                }, 60);
            }
            // انتخابگر کاربر (با جستجو)
            if (action === 'delegate') {
                setTimeout(() => {
                    AssigneePicker.init({
                        container: '#pmPicker-' + taskId,
                        users: pmUsers.filter(u => Number(u.id) !== Number(currentUser.id)),
                        sections: (typeof sectionList !== 'undefined') ? sectionList : [],
                        sectionMap: (typeof sectionMap !== 'undefined') ? sectionMap : {},
                        showSections: false, // ارجاع فقط به شخص
                        allowAll: false,
                        onSelect: () => {}
                    });

                    // فهرست را با مختصات درست جای‌گذاری کن
                    pmAnchorDropdown(taskId);
                }, 60);
            }
            // فوکوس روی اولین ورودی
            setTimeout(() => {
                const f = box.querySelector('textarea, select');
                if (f) f.focus();
            }, 80);
        }


        function pmAnchorDropdown(taskId) {
            const box = document.getElementById('pmPicker-' + taskId);
            if (!box) return;

            const input = box.querySelector('.ap-input');
            const drop = box.querySelector('.ap-dropdown');

            if (!input || !drop) {
                console.warn('AssigneePicker: ap-input یا ap-dropdown پیدا نشد');
                return;
            }

            drop.classList.add('pm-floating-drop');

            /** فهرست را به body ببر و مختصاتش را تنظیم کن */
            const place = () => {
                if (!drop.classList.contains('open')) return;

                // 🔑 انتقال به body — از مبدأ مختصاتِ مودال آزاد شو
                if (drop.parentElement !== document.body) {
                    document.body.appendChild(drop);
                }

                const r = input.getBoundingClientRect();
                const dw = Math.max(drop.offsetWidth || 0, r.width, 300);
                const dh = drop.offsetHeight || 280;

                // عمودی: زیر کادر؛ اگر جا نبود، بالای آن
                let top = (window.innerHeight - r.bottom < dh + 12) ?
                    r.top - dh - 4 :
                    r.bottom + 4;
                if (top < 8) top = 8;

                // افقی (RTL): لبهٔ راستِ فهرست با لبهٔ راستِ کادر هم‌تراز
                let left = r.right - dw;
                if (left < 8) left = 8;
                if (left + dw > window.innerWidth - 8) left = window.innerWidth - dw - 8;

                // اولویت inline + important — تا استایلِ inject‌شدهٔ کتابخانه
                // (که right:0 دارد) نتواند با ما بجنگد
                drop.style.setProperty('right', 'auto', 'important');
                drop.style.setProperty('bottom', 'auto', 'important');
                drop.style.setProperty('top', top + 'px', 'important');
                drop.style.setProperty('left', left + 'px', 'important');
                drop.style.setProperty('width', dw + 'px', 'important');
            };

            // کتابخانه با کلاس .open باز و بسته می‌کند
            const observer = new MutationObserver(() => {
                if (drop.classList.contains('open')) {
                    requestAnimationFrame(place);
                }
            });
            observer.observe(drop, {
                attributes: true,
                attributeFilter: ['class']
            });

            // موقع تایپ، ارتفاع فهرست عوض می‌شود → جای‌گذاری مجدد
            input.addEventListener('input', () => requestAnimationFrame(place));

            // اسکرول مودال / تغییر اندازهٔ پنجره
            document.querySelector('#planModal .modal-body')
                ?.addEventListener('scroll', place);
            window.addEventListener('resize', place);
        }

        function pmCleanupFloating() {
            // تقویم و فهرست را حذف کن (با هر بار باز شدن فرم، دوباره ساخته می‌شوند)
            document.querySelectorAll(
                'body > .persian-datepicker.pm-floating, body > .ap-dropdown.pm-floating-drop'
            ).forEach(el => el.remove());

            // منوها را به ردیف خودشان برگردان
            pmCloseMenus();
        }

        function pmFloatDatepicker(taskId) {
            const input = document.getElementById('pmDate-' + taskId);
            if (!input) return;

            const wrapper = input.closest('.persian-datepicker-wrapper');
            const cal = wrapper?.querySelector('.persian-datepicker');
            if (!cal) return;

            // نشانه‌گذاری تا CSS بشناسدش
            cal.classList.add('pm-floating');

            const place = () => {
                const r = input.getBoundingClientRect();

                // اول به body منتقل کن تا ابعادش را بگیریم
                if (cal.parentElement !== document.body) {
                    document.body.appendChild(cal);
                }

                const ch = cal.offsetHeight || 320;
                const cw = cal.offsetWidth || 300;

                // عمودی: زیر کادر، مگر جا نباشد
                let top = (window.innerHeight - r.bottom < ch + 12) ?
                    r.top - ch - 4 :
                    r.bottom + 4;
                if (top < 8) top = 8;

                // افقی (RTL): لبهٔ راست تقویم با لبهٔ راست کادر
                let left = r.right - cw;
                if (left < 8) left = 8;
                if (left + cw > window.innerWidth - 8) left = window.innerWidth - cw - 8;

                cal.style.top = top + 'px';
                cal.style.left = left + 'px';
            };

            // کتابخانه با کلاس .show باز و بسته می‌کند.
            // ما فقط وقتی باز شد، جای درست می‌گذاریمش.
            const observer = new MutationObserver(() => {
                if (cal.classList.contains('show')) {
                    requestAnimationFrame(place);
                }
            });
            observer.observe(cal, {
                attributes: true,
                attributeFilter: ['class']
            });

            // موقع اسکرول مودال، تقویم همراه کادر حرکت کند
            document.querySelector('#planModal .modal-body')
                ?.addEventListener('scroll', () => {
                    if (cal.classList.contains('show')) place();
                });
        }

        function pmCloseForm(taskId) {
            pmCleanupFloating(); // ← جایگزین خط حذف تقویم

            document.querySelectorAll('body > .persian-datepicker.pm-floating')
                .forEach(c => c.remove());

            const box = document.getElementById('pmForm-' + taskId);
            box.classList.remove('open');
            box.innerHTML = '';
            delete box.dataset.action;

            document.getElementById('pmRow-' + taskId).classList.remove('form-open');
        }


        /* ═══════════════════════════════════════════════════
           ۸) قالب فرم هر عملیات
           ═══════════════════════════════════════════════════ */

        function pmFormHtml(taskId, action) {

            // ── تایید ──────────────────────────────────────
            if (action === 'approve') {
                return `
            <div class="pm-form-title">
                <i class="bi bi-check-lg" style="color:#00BF8F"></i> تایید کار
            </div>
            <textarea id="pmNote-${taskId}" placeholder="یادداشت (اختیاری)"></textarea>
            <div class="pm-form-actions">
                <button class="pm-btn pm-btn-primary" onclick="pmSubmit(${taskId}, 'approve', this)">
                    تایید
                </button>
                <button class="pm-btn pm-btn-ghost" onclick="pmCloseForm(${taskId})">انصراف</button>
            </div>`;
            }

            // ── رد ─────────────────────────────────────────
            if (action === 'reject') {
                return `
            <div class="pm-form-title">
                <i class="bi bi-x-lg" style="color:#dc2626"></i> رد کار
            </div>
            <textarea id="pmNote-${taskId}" placeholder="دلیل رد (الزامی)"></textarea>
            <div class="pm-form-actions">
                <button class="pm-btn pm-btn-danger" onclick="pmSubmit(${taskId}, 'reject', this)">
                    رد کردن
                </button>
                <button class="pm-btn pm-btn-ghost" onclick="pmCloseForm(${taskId})">انصراف</button>
            </div>`;
            }

            // ── ارجاع ──────────────────────────────────────
            if (action === 'delegate') {
                return `
            <div class="pm-form-title">
                <i class="bi bi-arrow-left-right" style="color:#2563eb"></i> ارجاع کار
            </div>

            <!-- انتخابگر کاربر با جستجو — مانند create-task.php -->
            <div id="pmPicker-${taskId}" style="margin-bottom:8px;"></div>

            <textarea id="pmNote-${taskId}" placeholder="یادداشت (اختیاری)"></textarea>
            <label style="font-size: .72rem; color:#6b7280; display:flex; align-items:center; gap:6px; margin-bottom:8px;">
                <input type="checkbox" id="pmShare-${taskId}" checked>
                اشتراک‌گذاری تاریخچهٔ کار
            </label>
            <div class="pm-form-actions">
                <button class="pm-btn pm-btn-primary" onclick="pmSubmit(${taskId}, 'delegate', this)">
                    ارجاع
                </button>
                <button class="pm-btn pm-btn-ghost" onclick="pmCloseForm(${taskId})">انصراف</button>
            </div>`;
            }

            // ── تمدید موعد ─────────────────────────────────
            if (action === 'extend') {
                return `
            <div class="pm-form-title">
                <i class="bi bi-calendar-plus" style="color:#ea580c"></i> تمدید موعد
            </div>

            <!-- ساختار انتخابگر تاریخ، دقیقاً مانند create-task.php -->
            <div class="persian-datepicker-wrapper" style="margin-bottom:8px;">
                <input type="text" class="persian-datepicker-input form-control"
                       id="pmDate-${taskId}" placeholder="انتخاب موعد جدید..." readonly>
                <div class="persian-datepicker">
                    <div class="datepicker-header">
                        <button type="button" class="datepicker-nav" data-action="prev">►</button>
                        <span class="datepicker-current"></span>
                        <button type="button" class="datepicker-nav" data-action="next">◄</button>
                    </div>
                    <div class="datepicker-weekdays">
                        <div class="datepicker-weekday">ش</div>
                        <div class="datepicker-weekday">ی</div>
                        <div class="datepicker-weekday">د</div>
                        <div class="datepicker-weekday">س</div>
                        <div class="datepicker-weekday">چ</div>
                        <div class="datepicker-weekday">پ</div>
                        <div class="datepicker-weekday">ج</div>
                    </div>
                    <div class="datepicker-days"></div>
                    <button type="button" class="datepicker-today-btn">امروز</button>
                </div>
            </div>

            <textarea id="pmNote-${taskId}" placeholder="دلیل تمدید (الزامی)"></textarea>
            <div class="pm-form-actions">
                <button class="pm-btn pm-btn-primary" onclick="pmSubmit(${taskId}, 'extend', this)">
                    ثبت درخواست
                </button>
                <button class="pm-btn pm-btn-ghost" onclick="pmCloseForm(${taskId})">انصراف</button>
            </div>`;
            }

            return '';
        }


        /* ═══════════════════════════════════════════════════
           ۹) ارسال عملیات به سرور
           ═══════════════════════════════════════════════════ */

        async function pmSubmit(taskId, action, btn) {

            const noteEl = document.getElementById('pmNote-' + taskId);
            const note = noteEl ? noteEl.value.trim() : '';

            let url, payload;

            // ── اعتبارسنجی و ساخت درخواست ─────────────────
            switch (action) {

                case 'approve':
                    url = '../api/tasks/approve.php';
                    payload = {
                        task_id: taskId,
                        approve: true,
                        notes: note
                    };
                    break;

                case 'reject':
                    if (!note) {
                        pmToast('لطفاً دلیل رد را بنویسید', 'err');
                        return;
                    }
                    url = '../api/tasks/approve.php';
                    payload = {
                        task_id: taskId,
                        approve: false,
                        notes: note
                    };
                    break;

                case 'delegate': {
                    const sel = AssigneePicker.getValue();

                    if (!sel || !sel.value) {
                        pmToast('لطفاً کاربر مقصد را انتخاب کنید', 'err');
                        return;
                    }

                    url = '../api/tasks/delegate.php';
                    payload = {
                        task_id: taskId,
                        to_user_id: Number(sel.value),
                        notes: note,
                        share_history: document.getElementById('pmShare-' + taskId).checked
                    };
                    break;
                }

                case 'extend': {
                    const dateEl = document.getElementById('pmDate-' + taskId);
                    // انتخابگر تاریخ، مقدار میلادی را در data-date می‌گذارد
                    const newDate = dateEl.getAttribute('data-date');

                    if (!newDate) {
                        pmToast('لطفاً موعد جدید را انتخاب کنید', 'err');
                        return;
                    }
                    if (!note) {
                        pmToast('لطفاً دلیل تمدید را بنویسید', 'err');
                        return;
                    }

                    url = '../api/tasks/request-deadline.php';
                    payload = {
                        task_id: taskId,
                        new_deadline: newDate,
                        reason: note
                    };
                    break;
                }

                default:
                    return;
            }

            // ── ارسال ──────────────────────────────────────
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (!data.success) {
                    throw new Error(data.message || 'عملیات ناموفق بود');
                }

                // پیام موفقیت
                const msgs = {
                    approve: 'کار تایید شد',
                    reject: 'کار رد شد',
                    delegate: 'کار ارجاع شد',
                    extend: (data.data && data.data.auto_approved) ?
                        'موعد تغییر یافت' : 'درخواست تمدید ثبت شد'
                };
                pmToast(msgs[action] || 'انجام شد', 'ok');

                // ردیف را با انیمیشن حذف کن
                pmRemoveRow(taskId);

                // داده‌های صفحه را تازه کن (بی‌صدا)
                pmRefresh();

            } catch (err) {
                pmToast(err.message || 'خطا در انجام عملیات', 'err');
                btn.disabled = false;
                btn.innerHTML = original;
            }
        }


        /* ═══════════════════════════════════════════════════
           ۱۰) حذف ردیف + به‌روزرسانی عدد کارت
           ═══════════════════════════════════════════════════ */

        function pmRemoveRow(taskId) {
            const row = document.getElementById('pmRow-' + taskId);
            if (!row) return;

            row.classList.add('removing');

            setTimeout(() => {
                row.remove();

                // اگر لیست خالی شد، پیام بگذار
                const body = document.getElementById('pmBody');
                if (!body.querySelector('.pm-row')) {
                    body.innerHTML = `
                <div class="dash-empty">
                    <i class="bi bi-check2-circle"></i>
                    کاری در این بازه باقی نمانده
                </div>`;
                }
            }, 300);
        }

        /**
         * داده‌ها را از سرور تازه می‌گیرد و عدد کارت‌ها را به‌روز می‌کند.
         * مودال باز می‌ماند.
         */
        async function pmRefresh() {
            try {
                const res = await fetch('../api/tasks/my-tasks.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();

                store.mine = pickList(data);

                renderStats(); // عدد کارت‌ها
                renderTasks(); // جدول پشت مودال
                // اگر مودال هفتگی باز است، آن را هم تازه کن
                const wkEl = document.getElementById('weekModal');
                if (wkEl && wkEl.classList.contains('show')) {
                    wkRender();
                }
            } catch (e) {
                console.error('خطا در به‌روزرسانی:', e);
            }
        }


        /* ═══════════════════════════════════════════════════
           ۱۱) پیام کوتاه (toast)
           ═══════════════════════════════════════════════════ */

        let pmToastTimer = null;

        function pmToast(message, type = 'ok') {
            const el = document.getElementById('pmToast');
            el.textContent = message;
            el.className = type + ' show';

            clearTimeout(pmToastTimer);
            pmToastTimer = setTimeout(() => {
                el.className = type;
            }, 2600);
        }
        /* ═══════════════════════════════════════════════
           مودال هفتگی
           ═══════════════════════════════════════════════ */

        let wkOffset = 0; // 0 = این هفته، -1 = قبل، +1 = بعد
        let wkModal = null;

        function openWeekModal() {
            wkOffset = 0;
            if (!wkModal) {
                wkModal = new bootstrap.Modal(document.getElementById('weekModal'));
            }
            wkModal.show();
            wkRender();

            if (pmUsers.length === 0) pmLoadUsers();
        }

        function wkShift(dir) {
            wkOffset += dir;
            wkRender();
        }

        /* شنبهٔ هفتهٔ هدف را برمی‌گرداند */
        function wkSaturdayOf(offset) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            // شنبه = اولین روز هفتهٔ شمسی. getDay(): شنبه=6
            const back = (today.getDay() + 1) % 7; // فاصله تا شنبهٔ همین هفته
            const sat = new Date(today);
            sat.setDate(today.getDate() - back + offset * 7);
            return sat;
        }

        function wkRender() {
            const sat = wkSaturdayOf(wkOffset);

            // برچسب ناوبری
            const label = wkOffset === 0 ? 'این هفته' :
                wkOffset === -1 ? 'هفتهٔ قبل' :
                wkOffset === 1 ? 'هفتهٔ بعد' :
                `${toFa(Math.abs(wkOffset))} هفته ${wkOffset < 0 ? 'قبل' : 'بعد'}`;
            document.getElementById('wkLabel').textContent = label;

            const dayNames = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
            const months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

            let html = '';

            for (let i = 0; i < 6; i++) { // شنبه تا پنج‌شنبه (جمعه حذف)
                const day = new Date(sat);
                day.setDate(sat.getDate() + i);
                day.setHours(0, 0, 0, 0);

                const [jy, jm, jd] = jalaliOf(day);
                const dateStr = day.toISOString().slice(0, 10);

                // کارهای این روز (فقط کارهای کاربر جاری)
                const dayTasks = store.mine.filter(t => {
                    const due = TF.effectiveDue(t);
                    if (!due) return false;
                    if (TF.isDone(t)) return false;
                    const d = new Date(due);
                    d.setHours(0, 0, 0, 0);
                    return d.getTime() === day.getTime();
                });

                let cards = '';
                if (dayTasks.length) {
                    cards = dayTasks.map(t => {
                        const acts = pmActions(t);
                        const safe = (t.title || '').replace(/"/g, '&quot;');
                        const time = wkTimeOf(t);
                        return `
                        <div class="wk-card" id="pmRow-${t.id}">
                            ${acts.length ? pmKebabHtml(t.id, acts) : ''}
                            <div class="wk-card-title" onclick="location.href='task-detail.php?id=${t.id}'"
                                 title="${safe}">${t.title || '—'}</div>
                            ${time ? `<div class="wk-card-time">${time}</div>` : ''}
                            <div class="pm-form" id="pmForm-${t.id}"></div>
                        </div>`;
                    }).join('');
                } else {
                    cards = `<div class="wk-col-empty">—</div>`;
                }

                html += `
                <div class="wk-col">
                    <div class="wk-col-head">
                        <div class="wk-day">${dayNames[i]}</div>
                        <div class="wk-date">${toFa(jd)} ${months[jm - 1]}</div>
                    </div>
                    <div class="wk-cards">${cards}</div>
                </div>`;
            }

            document.getElementById('wkGrid').innerHTML = html;
        }

        /* ساعت کار، فقط اگر واقعاً وجود داشت (نه 00:00) */
        function wkTimeOf(t) {
            const raw = t.deadline || '';
            if (!raw || raw.indexOf(' ') === -1) return '';
            const hm = raw.split(' ')[1] || '';
            if (!hm || hm.startsWith('00:00')) return '';
            return toFa(hm.slice(0, 5));
        }

        /* ═══════════════════════════════════════════════
           مودال ماهانه
           ═══════════════════════════════════════════════ */

        let moOffset = 0; // 0 = این ماه، -1 = قبل، +1 = بعد
        let moModal = null;

        function openMonthModal() {
            moOffset = 0;
            if (!moModal) {
                moModal = new bootstrap.Modal(document.getElementById('monthModal'));
                // موقعِ show()، مودال هنوز کاملاً چیده نشده و clientHeight درست نیست؛
                // بعدِ اتمامِ ترنزیشنِ نمایش، یک‌بار دیگه با ارتفاعِ واقعی بازمحاسبه می‌کنیم
                document.getElementById('monthModal').addEventListener('shown.bs.modal', moReapplyRowPx);
            }
            moModal.show();
            moRender();

            if (pmUsers.length === 0) pmLoadUsers();
        }

        function moShift(dir) {
            moOffset += dir;
            moRender();
        }

        /* اولِ ماهِ شمسیِ جاری، به گرگوری */
        function moTodayMonthStart() {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const jd = jalaliOf(today)[2];
            const d = new Date(today);
            d.setDate(d.getDate() - (jd - 1));
            return d;
        }

        /* تعداد روزهای ماهی که این تاریخ در آن است */
        function moDaysInMonth(monthStart) {
            const jm = jalaliOf(monthStart)[1];
            let count = 0;
            const d = new Date(monthStart);
            while (jalaliOf(d)[1] === jm) {
                count++;
                d.setDate(d.getDate() + 1);
            }
            return count;
        }

        /* اولِ ماهِ هدف (بر اساس افست از ماه جاری)، به گرگوری */
        function moMonthStartOf(offset) {
            let d = moTodayMonthStart();
            if (offset > 0) {
                for (let i = 0; i < offset; i++) {
                    d.setDate(d.getDate() + moDaysInMonth(d));
                }
            } else if (offset < 0) {
                for (let i = 0; i < -offset; i++) {
                    const prevDay = new Date(d);
                    prevDay.setDate(prevDay.getDate() - 1); // آخرین روز ماه قبل
                    const pjd = jalaliOf(prevDay)[2];
                    prevDay.setDate(prevDay.getDate() - (pjd - 1)); // اول همان ماه قبل
                    d = prevDay;
                }
            }
            return d;
        }

        function moTruncate(title) {
            const t = title || '—';
            return t.length > 15 ? t.slice(0, 15) + '…' : t;
        }

        /* تاریخ محلی به شکل YYYY-MM-DD (بدون تبدیل UTC، برخلاف toISOString) */
        function moYMD(d) {
            const p = n => String(n).padStart(2, '0');
            return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
        }

        /* ─── هاور: بزرگ‌شدنِ کلِ ردیف + کلِ ستونِ سلولِ هاورشده (به‌جای زوم خودِ سلول) ─── */
        let MO_ROW_PX = 100; // ارتفاعِ پایهٔ هر ردیف (px) — قبلِ هر render، پویا بازمحاسبه می‌شود
        const MO_ROW_PX_MIN = 56; // زیرِ این مقدار سلول‌ها غیرِقابلِ‌استفاده می‌شوند؛ در این حالتِ نادر، اسکرولِ modal-body به‌عنوانِ راهِ‌فرار باقی می‌ماند
        const MO_ROW_PX_MAX = 100; // سقفِ ایمنی — حتی اگر تخمینِ فضایِ آزاد کمی خوش‌بینانه باشه، ردیف‌ها هیچ‌وقت بزرگ‌تر از این نمی‌شن
        const MO_HEADER_ROW_PX = 26; // ارتفاعِ تقریبیِ ردیفِ نام‌روزها (auto) — از رویِ CSSِ ثابتِ .mo-weekday
        const MO_GRID_GAP_PX = 6; // باید با gap در CSSِ .mo-grid یکی باشد
        const MO_COL_GROW = 1.4; // ضریب بزرگ‌شدنِ عرض سلولِ هاورشده (۴۰٪ بیشتر)
        const MO_ROW_GROW = 2.0; // ضریب بزرگ‌شدنِ ارتفاع سلولِ هاورشده (۱۰۰٪ بیشتر)
        let moTotalRows = 0;

        /* گامِ اول: تخمینِ تحلیلی، تا modal-body به‌اندازه‌ی فضایِ واقعاً موجود رشد
           کنه (بدونِ این گام، اگر رندرِ اول به‌خاطرِ کلمپِ حداقلی کوچیک شروع بشه،
           دیگه هیچ‌وقت به فضایِ واقعی رشد نمی‌کنه چون overflowِ گامِ دوم صفر
           می‌مونه و دلیلی برایِ بزرگ‌شدن پیدا نمی‌شه).
           گامِ دوم: اگر بازم (به‌خاطرِ خطایِ تخمینِ هدر/فوتر/حاشیه‌ها) چیزی از
           پایینِ viewport بیرون زده، دقیقاً به همون‌اندازه که واقعاً اندازه‌گیری
           شده کم می‌کنیم — این گام بر اساسِ رندرِ واقعیِ مرورگره، نه حدس */
        function moFitModalBody() {
            const dialog = document.querySelector('#monthModal .modal-dialog');
            const header = document.querySelector('#monthModal .modal-header');
            const footer = document.querySelector('#monthModal .modal-footer');
            const body = document.getElementById('moGrid')?.parentElement;
            if (!dialog || !header || !footer || !body) return;

            const dm = getComputedStyle(dialog);
            const margins = parseFloat(dm.marginTop) + parseFloat(dm.marginBottom);
            const estimated = window.innerHeight - margins - header.offsetHeight - footer.offsetHeight - 8;
            body.style.height = Math.max(200, estimated) + 'px';

            const overflow = dialog.getBoundingClientRect().bottom - window.innerHeight;
            if (overflow > 0) {
                body.style.height = Math.max(200, body.clientHeight - overflow - 8) + 'px';
            }
        }

        function moComputeRowPx(totalRows) {
            const body = document.getElementById('moGrid')?.parentElement;
            if (!body || totalRows <= 0) return 100;
            const available = body.clientHeight - MO_HEADER_ROW_PX - MO_GRID_GAP_PX * totalRows;
            const rowPx = Math.floor(available / totalRows);
            return Math.min(MO_ROW_PX_MAX, Math.max(MO_ROW_PX_MIN, rowPx));
        }

        /* بازمحاسبه‌ی ارتفاعِ ردیف‌ها بعدِ اتمامِ ترنزیشنِ نمایشِ مودال (وقتی
           اندازه‌گیری‌هایِ واقعی در دسترسه) — بدونِ رندرِ دوباره‌ی HTML، فقط
           ارتفاعِ modal-body و grid-template-rows به‌روزرسانی می‌شه */
        function moReapplyRowPx() {
            if (moTotalRows <= 0) return;
            moFitModalBody();
            MO_ROW_PX = moComputeRowPx(moTotalRows);
            const grid = document.getElementById('moGrid');
            if (!grid) return;
            grid.style.gridTemplateRows = moBaseGridTemplate(moTotalRows).rows;
        }

        function moBaseGridTemplate(totalRows) {
            return {
                cols: 'repeat(7, 1fr)',
                rows: 'auto repeat(' + totalRows + ', ' + MO_ROW_PX + 'px)'
            };
        }

        function moHoverGridTemplate(hoverRow, hoverCol, totalRows) {
            const colShrink = (7 - MO_COL_GROW) / 6;
            const cols = [];
            for (let c = 0; c < 7; c++) {
                cols.push(((c === hoverCol) ? MO_COL_GROW : colShrink).toFixed(4) + 'fr');
            }

            let rowsStr;
            if (totalRows <= 1) {
                rowsStr = (MO_ROW_GROW * MO_ROW_PX) + 'px';
            } else {
                const rowShrinkPx = MO_ROW_PX * (totalRows - MO_ROW_GROW) / (totalRows - 1);
                const rows = [];
                for (let r = 0; r < totalRows; r++) {
                    rows.push(((r === hoverRow ? MO_ROW_GROW * MO_ROW_PX : rowShrinkPx)).toFixed(2) + 'px');
                }
                rowsStr = rows.join(' ');
            }

            return {
                cols: cols.join(' '),
                rows: 'auto ' + rowsStr
            };
        }

        /* ─── هاورِ گروهی روی کل گرید (نه تک‌تک سلول‌ها) ───
           چون موس معمولاً از یک سلول مستقیم به سلول مجاور می‌رود، اگر enter/leave
           جدا روی هر سلول باشد، بین دو رویداد لحظه‌ای به حالت پایه برمی‌گردد و
           چشمک/تیک ایجاد می‌کند. اینجا فقط وقتی سلولِ هاورشده واقعاً عوض شود
           (یا موس کلاً از گرید خارج شود) قالب گرید را تغییر می‌دهیم. */
        let moHoveredCell = null;

        function moApplyHover(cell) {
            if (moHoveredCell === cell) return;

            if (moHoveredCell) {
                moHoveredCell.classList.remove('mo-hover');
                moHoveredCell.querySelectorAll('.mo-task-title[data-full]').forEach(el => {
                    el.textContent = moTruncate(el.dataset.full);
                });
            }

            moHoveredCell = cell;
            cell.classList.add('mo-hover');
            cell.querySelectorAll('.mo-task-title[data-full]').forEach(el => {
                el.textContent = el.dataset.full;
            });

            const row = parseInt(cell.dataset.row, 10);
            const col = parseInt(cell.dataset.col, 10);
            const grid = document.getElementById('moGrid');
            const t = moHoverGridTemplate(row, col, moTotalRows);
            grid.style.gridTemplateColumns = t.cols;
            grid.style.gridTemplateRows = t.rows;
        }

        function moGridOver(ev) {
            const cell = ev.target.closest('.mo-cell:not(.mo-cell-empty)');
            if (!cell) return;
            moApplyHover(cell);
        }

        function moGridLeave() {
            if (moHoveredCell) {
                moHoveredCell.classList.remove('mo-hover');
                moHoveredCell.querySelectorAll('.mo-task-title[data-full]').forEach(el => {
                    el.textContent = moTruncate(el.dataset.full);
                });
                moHoveredCell = null;
            }
            const grid = document.getElementById('moGrid');
            const b = moBaseGridTemplate(moTotalRows);
            grid.style.gridTemplateColumns = b.cols;
            grid.style.gridTemplateRows = b.rows;
        }

        function moRender() {
            moHoveredCell = null; // چون grid دوباره ساخته می‌شود، رفرنس قبلی معتبر نمی‌ماند
            moFitModalBody();

            const monthStart = moMonthStartOf(moOffset);
            const [jy, jm] = jalaliOf(monthStart);
            const daysCount = moDaysInMonth(monthStart);
            const months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
            document.getElementById('moLabel').textContent = moOffset === 0 ? 'این ماه' : (months[jm - 1] + ' ' + toFa(jy));
            document.getElementById('moSeeAllBtn').href = 'my-tasks.php?filter=month&jy=' + jy + '&jm=' + jm;

            const dayNames = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
            let html = dayNames.map(n => `<div class="mo-weekday">${n}</div>`).join('');

            // چیدمانِ تقویمی: خانه‌های خالیِ ابتدای ماه تا روز هفتهٔ درست
            const firstWeekday = (monthStart.getDay() + 1) % 7; // شنبه=۰
            let cellIdx = 0;
            for (let i = 0; i < firstWeekday; i++) {
                const r = Math.floor(cellIdx / 7),
                    c = cellIdx % 7;
                html += `<div class="mo-cell mo-cell-empty" data-row="${r}" data-col="${c}"></div>`;
                cellIdx++;
            }

            const todayRef = new Date();
            todayRef.setHours(0, 0, 0, 0);
            const todayTime = todayRef.getTime();

            for (let dayNum = 1; dayNum <= daysCount; dayNum++) {
                const d = new Date(monthStart);
                d.setDate(d.getDate() + (dayNum - 1));
                d.setHours(0, 0, 0, 0);

                // کارهای این روز (فقط کارهای کاربر جاری)
                const dayTasks = store.mine.filter(t => {
                    const due = TF.effectiveDue(t);
                    if (!due) return false;
                    if (TF.isDone(t)) return false;
                    const dd = new Date(due);
                    dd.setHours(0, 0, 0, 0);
                    return dd.getTime() === d.getTime();
                });

                let tasksHtml = '';
                if (dayTasks.length) {
                    const shown = dayTasks.slice(0, 10);
                    tasksHtml = shown.map(t => {
                        const full = (t.title || '—').replace(/"/g, '&quot;');
                        return `<div class="mo-task-title" data-full="${full}"
                             onclick="location.href='task-detail.php?id=${t.id}'">${moTruncate(t.title || '—')}</div>`;
                    }).join('');
                    if (dayTasks.length > 10) {
                        tasksHtml += `<div class="mo-more" onclick="location.href='my-tasks.php?filter=day&amp;date=${moYMD(d)}'">+${toFa(dayTasks.length - 10)} مورد دیگر</div>`;
                    }
                }

                const isToday = d.getTime() === todayTime;
                const r = Math.floor(cellIdx / 7),
                    c = cellIdx % 7;
                html += `
                <div class="mo-cell ${isToday ? 'mo-today' : ''}" data-row="${r}" data-col="${c}">
                    <div class="mo-cell-date">${toFa(dayNum)}</div>
                    <div class="mo-cell-tasks">${tasksHtml}</div>
                </div>`;
                cellIdx++;
            }

            moTotalRows = Math.ceil(cellIdx / 7);
            MO_ROW_PX = moComputeRowPx(moTotalRows);

            const grid = document.getElementById('moGrid');
            grid.innerHTML = html;
            const base = moBaseGridTemplate(moTotalRows);
            grid.style.gridTemplateColumns = base.cols;
            grid.style.gridTemplateRows = base.rows;
        }
    </script>
    <?php include 'footer.php'; ?>

</body>

</html>