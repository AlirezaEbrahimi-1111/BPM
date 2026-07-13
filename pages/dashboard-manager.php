<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../config/config.php';
require_once '../includes/version.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد مدیریت - سیستم مدیریت کار</title>

    <!-- Bootstrap 5 RTL -->
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

        /* body در custom.css مقدار margin-top: 3.5rem دارد (نوار ثابت) */
        .dash-wrap {
            max-width: 1440px;
            margin: 0 auto;
            padding: 14px 24px 18px;
            height: calc(100vh - 3.5rem);
            display: flex;
            flex-direction: column;
            gap: 14px;
            box-sizing: border-box;
        }

        /* ═══ کارت بخش‌ها ═══ */
        .dash-card {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
        }

        .dash-card-head {
            background: var(--gray-100);
            /* هدر خاکستری */
            border-bottom: 1px solid var(--gray-200);
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
            font-weight: 600;
            font-size: .95rem;
            color: var(--gray-700);
        }

        .dash-card-title i {
            font-size: 1rem;
            color: var(--gray-500);
        }

        .dash-see-all {
            font-size: .8rem;
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
            font-size: .75rem;
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

        /* ═══ برنامه کاری ═══ */
        .plan-row {
            flex-shrink: 0;
        }

        .plan-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            padding: 14px;
        }

        .plan-item {
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            padding: 13px 16px;
        }

        .plan-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .plan-icon.month {
            background: #dcfce7;
            color: #16a34a;
        }

        .plan-icon.week {
            background: #ffedd5;
            color: #ea580c;
        }

        .plan-icon.tomor {
            background: #dbeafe;
            color: #2563eb;
        }

        .plan-label {
            font-size: .82rem;
            font-weight: 600;
            color: var(--gray-500);
            margin-bottom: 2px;
            /* هر سه هم‌رنگ */
        }

        .plan-value {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--gray-900);
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
            border-bottom: 1px solid var(--gray-200);
            padding: 0 12px;
            flex-shrink: 0;
            background: #fff;
        }

        .dash-tab {
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 10px 14px 9px;
            font-size: .87rem;
            color: var(--gray-500);
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


        /* راهنمای خالی، فضای بیهوده می‌گیرد */
        #planModal .ap-hint:empty {
            display: none !important;
        }

        .tab-pin {
            font-size: .78rem;
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
        }

        .filter-chip {
            border: 1px solid var(--gray-200);
            background: #fff;
            border-radius: var(--radius-sm);
            padding: 5px 14px;
            font-size: .8rem;
            color: var(--gray-500);
            cursor: pointer;
            transition: all .15s;
        }

        .filter-chip:hover {
            background: var(--gray-50);
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
            background: #fff;
            font-size: .78rem;
            font-weight: 600;
            color: var(--gray-400);
            text-align: right;
            padding: 8px 14px;
            border-bottom: 1px solid var(--gray-100);
            white-space: nowrap;
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

        .task-table tbody tr:hover {
            background: var(--gray-50);
        }

        .task-table td {
            padding: 10px 14px;
            font-size: .85rem;
            color: var(--gray-700);
            vertical-align: middle;
        }

        .td-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .td-title i.doc {
            color: #93c5fd;
            font-size: .95rem;
            flex-shrink: 0;
        }

        .td-title span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .td-deadline {
            color: var(--gray-500);
            white-space: nowrap;
        }

        .td-status {
            text-align: center;
        }

        .td-ops {
            text-align: center;
        }

        .st-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .st-not_started {
            background: var(--gray-100);
            color: var(--gray-500);
        }

        .st-in_progress {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .st-completed,
        .st-approved {
            background: #dcfce7;
            color: #15803d;
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
            font-size: 1rem;
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

        .routine-row:hover {
            background: var(--gray-50);
        }

        .routine-name {
            flex: 1;
            font-size: .86rem;
            color: var(--gray-700);
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .routine-bar-wrap {
            flex: 1.6;
            height: 9px;
            background: var(--gray-100);
            border-radius: 999px;
            overflow: hidden;
        }

        .routine-bar {
            height: 100%;
            border-radius: 999px;
        }

        .routine-count {
            font-size: .85rem;
            font-weight: 700;
            color: var(--gray-700);
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

        .dlg-row:hover {
            background: var(--gray-50);
        }

        .dlg-main {
            flex: 1;
            min-width: 0;
        }

        .dlg-title {
            font-size: .85rem;
            color: var(--gray-700);
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dlg-sub {
            font-size: .74rem;
            color: var(--gray-400);
            margin-top: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dlg-days {
            background: #fee2e2;
            color: #b91c1c;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: .74rem;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .dash-empty {
            text-align: center;
            color: var(--gray-400);
            font-size: .85rem;
            padding: 30px 14px;
        }

        .dash-empty i {
            display: block;
            font-size: 1.6rem;
            margin-bottom: 6px;
            opacity: .5;
        }

        .inst-row {
            border: 1px solid var(--gray-100);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            margin-bottom: 8px;
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
            font-size: .9rem;
            color: var(--gray-700);
        }

        .inst-meta {
            font-size: .78rem;
            color: var(--gray-500);
            margin-bottom: 6px;
        }

        .inst-prog {
            height: 7px;
            background: var(--gray-100);
            border-radius: 999px;
            overflow: hidden;
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

        /* ═══ کارت‌های آماری کلیک‌پذیر ═══ */
        .plan-item.clickable {
            cursor: pointer;
            transition: border-color .15s, box-shadow .15s, transform .12s;
            position: relative;
        }

        .plan-item.clickable:hover {
            border-color: #c7d2fe;
            box-shadow: 0 2px 10px rgba(37, 99, 235, .08);
            transform: translateY(-1px);
        }

        .plan-arrow {
            margin-right: auto;
            color: #d1d5db;
            font-size: .9rem;
            transition: color .15s, transform .15s;
        }

        .plan-item.clickable:hover .plan-arrow {
            color: #2563eb;
            transform: translateX(-3px);
        }

        /* ═══════════════════════════════════════════
           مودال برنامه کاری — تعریف واحد و ساده
           ═══════════════════════════════════════════ */

        /* هیچ دستکاری در چیدمان Bootstrap نمی‌کنیم.
           فقط ظاهر را تنظیم می‌کنیم. */
        #planModal .modal-content {
            border: none;
            border-radius: 14px;
        }

        #planModal .modal-header {
            background: #f3f4f6;
            border-bottom: 1px solid #e5e7eb;
            border-radius: 14px 14px 0 0;
            padding: 14px 18px;
        }

        #planModal .modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #planModal .modal-body {
            padding: 12px 14px;
            max-height: 60vh;
            overflow-y: auto;
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
            padding: 12px 14px;
            margin-bottom: 8px;
            transition: background .12s, border-color .12s;
        }

        .pm-row:hover {
            background: #f9fafb;
            border-color: #e5e7eb;
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
            font-size: .9rem;
            font-weight: 600;
            color: #374151;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pm-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 5px;
            font-size: .75rem;
            color: #9ca3af;
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
            font-size: 1.1rem;
            padding: 4px 8px;
            border-radius: 6px;
            transition: background .12s, color .12s;
        }

        .pm-kebab:hover {
            background: #f3f4f6;
            color: #374151;
        }

        /* وقتی فرم عملیات باز است، دکمهٔ سه‌نقطه مخفی شود.
           هم ظاهر تمیزتر می‌شود، هم کاربر گیج نمی‌شود. */
        .pm-row.form-open .pm-menu-wrap {
            display: none;
        }

        /* دکمه همیشه بالای ردیف بماند، نه وسط */
        .pm-menu-wrap {
            align-self: flex-start;
            margin-top: 2px;
        }

        .pm-menu {
            display: none;
            position: fixed;
            /* ← مختصات را JS حساب می‌کند */
            z-index: 3000;
            /* ← بالاتر از مودال */
            min-width: 165px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, .1);
            padding: 5px;
            overflow: hidden;
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
            font-size: .85rem;
            color: #374151;
            text-align: right;
            transition: background .1s;
        }

        .pm-menu button:hover {
            background: #f3f4f6;
        }

        .pm-menu button i {
            font-size: .95rem;
            width: 16px;
        }

        .pm-menu button.act-approve i {
            color: #16a34a;
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
            font-size: .82rem;
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
            font-size: .84rem;
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
            font-size: .82rem;
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
            font-size: .86rem;
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
            background: #15803d;
        }

        #pmToast.err {
            background: #b91c1c;
        }

        /* انتخابگر تاریخ داخل مودال باید بالای بقیه باشد */
        #planModal .persian-datepicker {
            z-index: 2100;
        }
    </style>


    <div class="dash-wrap">

        <!-- ═══ برنامه کاری ═══ -->
        <div class="dash-card plan-row">
            <div class="dash-card-head">
                <div class="dash-card-title">
                    <i class="bi bi-calendar3"></i><span>برنامه کاری</span>
                </div>
            </div>
            <div class="plan-grid">
                <div class="plan-item clickable" onclick="openPlanModal('month')">
                    <div class="plan-icon month"><i class="bi bi-calendar-check"></i></div>
                    <div>
                        <div class="plan-label">این ماه</div>
                        <div class="plan-value" id="statMonth">— وظیفه</div>
                    </div>
                    <i class="bi bi-chevron-left plan-arrow"></i>
                </div>
                <div class="plan-item clickable" onclick="openPlanModal('week')">
                    <div class="plan-icon week"><i class="bi bi-calendar-week"></i></div>
                    <div>
                        <div class="plan-label">این هفته</div>
                        <div class="plan-value" id="statWeek">— وظیفه</div>
                    </div>
                    <i class="bi bi-chevron-left plan-arrow"></i>
                </div>
                <div class="plan-item clickable" onclick="openPlanModal('tomorrow')">
                    <div class="plan-icon tomor"><i class="bi bi-calendar-event"></i></div>
                    <div>
                        <div class="plan-label">فردا</div>
                        <div class="plan-value" id="statTomorrow">— وظیفه</div>
                    </div>
                    <i class="bi bi-chevron-left plan-arrow"></i>
                </div>
            </div>
        </div>


        <!-- ═══ کارها ═══ -->
        <div class="tasks-row">
            <div class="dash-card">
                <div class="dash-card-head">
                    <div class="dash-card-title">
                        <i class="bi bi-list-task"></i><span>کارها</span>
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
                </div>

                <div class="dash-filters">
                    <button class="filter-chip active" data-filter="all">همه</button>
                    <button class="filter-chip" data-filter="today">امروز</button>
                    <button class="filter-chip" data-filter="overdue">عقب افتاده</button>
                </div>

                <div class="dash-card-body">
                    <table class="task-table">
                        <thead>
                            <tr>
                                <th>عنوان وظیفه</th>
                                <th>مهلت انجام</th>
                                <th class="col-status">وضعیت</th>
                                <th class="col-ops">عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="taskTbody">
                            <tr>
                                <td colspan="4" class="dash-empty">در حال بارگذاری…</td>
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
                        <i class="bi bi-arrow-repeat"></i><span>فرآیندهای جاری</span>
                    </div>
                    <a href="workflow-monitor.php" class="dash-see-all">
                        مشاهده همه <i class="bi bi-chevron-left"></i>
                    </a>
                </div>
                <div class="dash-card-body">
                    <div id="routineList">
                        <div class="dash-empty">در حال بارگذاری…</div>
                    </div>
                </div>
            </div>

            <div class="dash-card">
                <div class="dash-card-head">
                    <div class="dash-card-title">
                        <i class="bi bi-clock-history"></i>
                        <span>کارهای واگذار شده (تاخیر دار)</span>
                    </div>
                    <a href="#" class="dash-see-all" id="delayedSeeAll">
                        مشاهده همه <i class="bi bi-chevron-left"></i>
                    </a>
                </div>
                <div class="dash-card-body">
                    <div id="delayedList">
                        <div class="dash-empty">در حال بارگذاری…</div>
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
                        <i class="bi bi-calendar3" id="pmIcon"></i>
                        <span id="pmTitle">برنامه کاری</span>
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
            const tbody = document.getElementById('taskTbody');
            const list = applyFilter(getTabList());

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

                return `<tr onclick="location.href='${link}'">
                <td>
                    <div class="td-title">
                        <i class="bi bi-file-earmark-text doc"></i>
                        <span title="${safe}">${t.title || '—'}</span>
                    </div>
                </td>
                <td class="td-deadline">${faDate(TF.effectiveDue(t))}</td>
                <td class="td-status">${TF.statusBadge(t, currentUser)}</td>
                <td class="td-ops">
                    <button class="star-btn${on ? ' on' : ''}"
                            title="${on ? 'حذف از منتخب' : 'افزودن به منتخب'}"
                            onclick="toggleStar('${key}', this, event)">
                        <i class="bi bi-star${on ? '-fill' : ''}"></i>
                    </button>
                </td>
            </tr>`;
            }).join('');
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
            const colors = ['#2563eb', '#0d9488', '#16a34a', '#ea580c', '#7c3aed'];

            box.innerHTML = list.map((r, i) => {
                const pct = Math.round((r.active_count / max) * 100);
                const name = (r.template_name || '').replace(/'/g, "\\'");
                return `<div class="routine-row" onclick="openInstances(${r.template_id}, '${name}')">
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
            document.getElementById('instModalBody').innerHTML = `<div class="dash-empty">در حال بارگذاری…</div>`;
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
                <div style="text-align:left; font-size:.75rem; color:var(--gray-500); margin-top:4px;">
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
                <i class="bi bi-file-earmark-text" style="color:#93c5fd;"></i>
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
                location.href = 'delegated-tasks.php?sort=overdue';
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
            month: {
                title: 'برنامه این ماه',
                icon: 'bi-calendar-check'
            },
            week: {
                title: 'برنامه این هفته',
                icon: 'bi-calendar-week'
            },
            tomorrow: {
                title: 'برنامه فردا',
                icon: 'bi-calendar-event'
            }
        };

        async function openPlanModal(scope) {
            pmScope = scope;

            const meta = PM_META[scope];
            document.getElementById('pmTitle').textContent = meta.title;
            document.getElementById('pmIcon').className = 'bi ' + meta.icon;

            if (!pmModal) {
                pmModal = new bootstrap.Modal(document.getElementById('planModal'));
                // 🆕 موقع بسته شدن مودال، عناصر شناور را پاک کن
                document.getElementById('planModal')
                    .addEventListener('hidden.bs.modal', pmCleanupFloating);
            }
            pmModal.show();

            pmRender();

            // لیست کاربران را یک‌بار (و فقط در صورت نیاز) بگیر
            if (pmUsers.length === 0) {
                pmLoadUsers();
            }
        }


        /* ═══════════════════════════════════════════════════
           ۴) رسم لیست
           ═══════════════════════════════════════════════════ */

        function pmRender() {
            pmCleanupFloating(); // ← جایگزین pmCloseMenus() و حذف تقویم

            document.querySelectorAll('body > .persian-datepicker.pm-floating')
                .forEach(c => c.remove());
            const box = document.getElementById('pmBody');
            const list = store.mine.filter(t => pmInScope(t, pmScope));

            if (!list.length) {
                box.innerHTML = `
            <div class="dash-empty">
                <i class="bi bi-check2-circle"></i>
                کاری در این بازه وجود ندارد
            </div>`;
                return;
            }

            // کارهایی که منتظر اقدام من‌اند، بالاتر بیایند
            list.sort((a, b) => pmActions(b).length - pmActions(a).length);

            box.innerHTML = list.map(t => {
                const acts = pmActions(t);
                const who = [t.assignee_first_name, t.assignee_last_name].filter(Boolean).join(' ');
                const safe = (t.title || '').replace(/"/g, '&quot;');
                const badge = TF.statusBadge(t, currentUser);

                return `
        <div class="pm-row" id="pmRow-${t.id}">
            <div style="flex:1; min-width:0;">
                <div class="pm-main" onclick="location.href='task-detail.php?id=${t.id}'">
                    <div class="pm-title" title="${safe}">${t.title || '—'}</div>
                    <div class="pm-meta">
                        ${badge}
                        ${who ? `<span><i class="bi bi-person"></i> ${who}</span>` : ''}
                        <span><i class="bi bi-calendar3"></i> ${faDate(TF.effectiveDue(t))}</span>
                    </div>
                </div>

                <!-- فرم عملیات — هنگام انتخاب گزینه باز می‌شود -->
                <div class="pm-form" id="pmForm-${t.id}"></div>
            </div>

            ${acts.length ? `
            <div class="pm-menu-wrap">
                <button class="pm-kebab" onclick="pmToggleMenu(${t.id}, event)" title="عملیات">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <div class="pm-menu" id="pmMenu-${t.id}">
                    ${acts.includes('approve') ? `
                        <button class="act-approve" onclick="pmOpenForm(${t.id}, 'approve')">
                            <i class="bi bi-check-lg"></i> تایید
                        </button>` : ''}
                    ${acts.includes('reject') ? `
                        <button class="act-reject" onclick="pmOpenForm(${t.id}, 'reject')">
                            <i class="bi bi-x-lg"></i> رد
                        </button>` : ''}
                    ${acts.includes('delegate') ? `
                        <button class="act-delegate" onclick="pmOpenForm(${t.id}, 'delegate')">
                            <i class="bi bi-arrow-left-right"></i> ارجاع
                        </button>` : ''}
                    ${acts.includes('extend') ? `
                        <button class="act-extend" onclick="pmOpenForm(${t.id}, 'extend')">
                            <i class="bi bi-calendar-plus"></i> تمدید موعد
                        </button>` : ''}
                </div>
            </div>` : ''}
        </div>`;
            }).join('');
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
                <i class="bi bi-check-lg" style="color:#16a34a"></i> تایید کار
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
            <label style="font-size:.8rem; color:#6b7280; display:flex; align-items:center; gap:6px; margin-bottom:8px;">
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
    </script>

</body>

</html>