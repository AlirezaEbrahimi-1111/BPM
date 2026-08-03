<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../includes/version.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مانیتورینگ پیشرفت کارهای روتین</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>" rel="stylesheet">
    <script src="<?= asset('../assets/js/cdn/intro.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/introjs.min.css') ?>">
    <script src="<?= asset('../../assets/js/sections-helper.js') ?>"></script>

    <style>
        /* ── Layout ── */
        .overview-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* ── Page Header ── */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .page-header-left {
            display: flex;
            align-items: center;
            gap: 0.875rem;
        }

        .page-title {
            font-size: 1.375rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
            letter-spacing: -0.02em;
        }

        .page-title i {
            color: var(--primary);
            margin-left: 0.5rem;
        }

        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #dcfce7;
            color: #166534;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .live-dot {
            width: 6px;
            height: 6px;
            background: #16a34a;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.4;
            }
        }

        #lastUpdate {
            font-size: 0.8rem;
            color: var(--gray-400);
        }

        /* ── Stats Grid ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .stat-tile {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1.25rem 1.375rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: box-shadow 0.2s ease;
        }

        .stat-tile:hover {
            box-shadow: var(--shadow-md);
        }

        .stat-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .stat-icon-wrap.blue {
            background: #eff6ff;
            color: #2563eb;
        }

        .stat-icon-wrap.red {
            background: #fef2f2;
            color: var(--danger);
        }

        .stat-icon-wrap.green {
            background: #f0fdf4;
            color: var(--success);
        }

        .stat-icon-wrap.amber {
            background: #fffbeb;
            color: var(--warning);
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1;
            color: var(--gray-900);
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--gray-500) !important;
            margin-top: 0.25rem;
        }

        /* ── Filter Pills ── */
        .filter-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: 1px solid var(--gray-200);
            border-radius: 999px;
            padding: 0.45rem 1rem;
            background: white;
            color: var(--gray-600);
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .filter-pill:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .filter-pill.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* ── Workflow Cards ── */
        .workflows-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        @media (max-width: 900px) {
            .workflows-grid {
                grid-template-columns: 1fr;
            }
        }

        .wf-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1.25rem 1.375rem;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .wf-card::before {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            border-radius: 0 var(--radius) var(--radius) 0;
        }

        .wf-card.in_progress::before {
            background: var(--primary);
        }

        .wf-card.completed::before {
            background: var(--success);
        }

        .wf-card.delayed::before {
            background: var(--danger);
        }

        .wf-card.cancelled::before {
            background: var(--gray-300);
        }

        .wf-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .wf-card.completed {
            opacity: 0.85;
        }

        .wf-card.cancelled {
            opacity: 0.65;
        }

        /* دکمه حذف روی کارت (گوشه بالا-چپ) */
        .wf-delete-btn {
            position: absolute;
            top: 0.6rem;
            left: 0.6rem;
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 8px;
            background: #fef2f2;
            color: var(--danger);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            cursor: pointer;
            opacity: 0.55;
            transition: all 0.15s ease;
            z-index: 3;
        }

        .wf-card:hover .wf-delete-btn {
            opacity: 1;
        }

        .wf-delete-btn:hover {
            background: var(--danger);
            color: white;
        }

        .wf-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-left: 2.2rem;
            /* جا برای دکمه حذف */
        }

        .wf-title {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
            line-height: 1.4;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .wf-id-badge {
            font-family: 'Courier New', Courier, monospace;
            font-size: .8rem;
            font-weight: 700;
            color: var(--gray-700);
            background: var(--gray-100);
            padding: 0.15rem 0.45rem;
            border-radius: 4px;
            flex-shrink: 0;
            letter-spacing: 0.02em;
        }

        .wf-badges {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            flex-shrink: 0;
        }

        /* Progress */
        .progress-wrap {
            position: relative;
            margin-bottom: 0.875rem;
        }

        .progress {
            height: 20px;
            border-radius: 999px;
            background: var(--gray-100);
            overflow: hidden;
        }

        .progress-bar {
            border-radius: 999px;
            transition: width 0.6s ease;
            background: var(--primary);
        }

        .progress-bar.bar-success {
            background: var(--success);
        }

        .progress-bar.bar-warning {
            background: var(--warning);
        }

        .progress-bar.bar-danger {
            background: var(--danger);
        }

        .progress-pct {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: white;
            mix-blend-mode: luminosity;
        }

        /* Meta row */
        .wf-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.775rem;
            color: var(--gray-500);
            margin-bottom: 0.875rem;
        }

        .wf-meta span {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Mini timeline */
        .mini-timeline {
            display: flex;
            align-items: center;
            gap: 0;
            margin-bottom: 0;
        }

        .mini-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }

        .mini-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 14px;
            left: 0;
            width: 100%;
            height: 1px;
            background: var(--gray-200);
            z-index: 0;
        }

        .mini-dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
            border: 2px solid white;
            box-shadow: 0 0 0 1px var(--gray-200);
            background: var(--gray-100);
            color: var(--gray-500);
            z-index: 1;
            position: relative;
        }

        .mini-dot.completed {
            background: var(--success);
            color: white;
            box-shadow: 0 0 0 1px var(--success);
        }

        .mini-dot.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 0 0 1px var(--primary);
        }

        .mini-dot.delayed {
            background: var(--danger);
            color: white;
            box-shadow: 0 0 0 1px var(--danger);
        }

        /* Delay alert strip */
        .delay-strip {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: var(--radius-sm);
            padding: 0.5rem 0.875rem;
            margin-top: 0.875rem;
            font-size: 0.8rem;
            color: #b91c1c;
            font-weight: 500;
        }

        /* Bottleneck badge */
        .bottleneck-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: var(--danger);
            color: white;
            padding: 0.25rem 0.6rem;
            border-radius: var(--radius-xs);
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* ── Empty / Error / Loading ── */
        .state-box {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-400);
            grid-column: 1 / -1;
        }

        .state-box i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 0.75rem;
        }

        /* ── Refresh FAB ── */
        .fab-refresh {
            position: fixed;
            bottom: 1.5rem;
            left: 1.5rem;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            border: none;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
            cursor: pointer;
            transition: all 0.2s ease;
            z-index: 999;
        }

        .fab-refresh:hover {
            background: var(--primary-dark);
            transform: scale(1.08);
        }

        .fab-refresh.spinning i {
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ── Dropdown Filters ── */
        .filter-dropdown-wrap {
            position: relative;
            display: inline-flex;
        }

        .filter-dropdown-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: 1px solid var(--gray-200);
            border-radius: 999px;
            padding: 0.45rem 1rem;
            background: white;
            color: var(--gray-600);
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .filter-dropdown-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .filter-dropdown-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .filter-dropdown-btn .caret {
            font-size: 0.65rem;
            transition: transform 0.2s;
        }

        .filter-dropdown-btn.open .caret {
            transform: rotate(180deg);
        }

        .filter-dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            min-width: 220px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            z-index: 999;
            overflow: hidden;
        }

        .filter-dropdown-menu.show {
            display: block;
        }

        .filter-dropdown-search {
            padding: 8px 10px;
            border-bottom: 1px solid var(--gray-100);
        }

        .filter-dropdown-search input {
            width: 100%;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 0.8rem;
            outline: none;
            direction: rtl;
        }

        .filter-dropdown-list {
            max-height: 220px;
            overflow-y: auto;
        }

        .filter-dropdown-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            font-size: 0.82rem;
            color: var(--gray-700);
            cursor: pointer;
            transition: background 0.1s;
        }

        .filter-dropdown-item:hover {
            background: var(--gray-50);
        }

        .filter-dropdown-item.selected {
            background: #eff6ff;
            color: var(--primary);
            font-weight: 600;
        }

        .filter-dropdown-item .check-icon {
            margin-right: auto;
            color: var(--primary);
            display: none;
        }

        .filter-dropdown-item.selected .check-icon {
            display: inline;
        }

        .filter-dropdown-empty {
            padding: 16px;
            text-align: center;
            color: var(--gray-400);
            font-size: 0.8rem;
        }

        /* ── Modal ── */
        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .modal-body {
            padding: 1.5rem;
        }

        /* ── مودال تأیید حذف: قفل عرض (ریشه‌ای) ── */
        #confirmDeleteModal .modal-dialog {
            max-width: 400px !important;
            width: calc(100% - 2rem) !important;
            margin: 1.75rem auto !important;
            flex-shrink: 0;
        }

        #confirmDeleteModal .modal-content {
            width: 100% !important;
        }

        #confirmDeleteModal .modal-body p {
            word-break: normal;
        }

        /* وسط‌چین متن دکمه‌های مودال */
        #confirmDeleteModal .modal-body .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .detail-progress-wrap {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .detail-step {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
            position: relative;
            border-right: 3px solid var(--gray-300);
            transition: box-shadow 0.2s;
        }

        .detail-step:hover {
            box-shadow: var(--shadow-sm);
        }

        .detail-step.completed {
            border-right-color: var(--success);
            background: #f0fdf4;
        }

        .detail-step.active {
            border-right-color: var(--primary);
            background: #eff6ff;
        }

        .detail-step.delayed {
            border-right-color: var(--danger);
            background: #fef2f2;
        }

        .detail-step-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .meta-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem 1.5rem;
            font-size: 0.8rem;
            color: var(--gray-600);
        }

        .meta-row .lbl {
            color: var(--gray-400);
        }

        @media (max-width: 576px) {
            .meta-row {
                grid-template-columns: 1fr;
            }
        }

        /* ── Search Box ── */
        .search-box-wrap {
            position: relative;
            margin-left: auto;
            margin-left: 0;
            flex-shrink: 0;
        }

        .search-box-wrap .search-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 0.85rem;
            pointer-events: none;
            transition: color 0.15s;
        }

        .search-box-wrap input {
            width: 380px;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            padding: 0.45rem 2.4rem 0.45rem 1rem;
            font-size: 0.8rem;
            font-family: inherit;
            direction: rtl;
            outline: none;
            background: white;
            color: var(--gray-800);
            transition: all 0.15s ease;
        }

        .search-box-wrap input::placeholder {
            color: var(--gray-400);
        }

        .search-box-wrap input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .search-box-wrap input:focus~.search-icon {
            color: var(--primary);
        }

        .search-box-wrap .search-clear {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 22px;
            height: 22px;
            border: none;
            border-radius: 50%;
            background: var(--gray-200);
            color: var(--gray-500);
            font-size: 0.7rem;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
            padding: 0;
            line-height: 1;
        }

        .search-box-wrap .search-clear:hover {
            background: var(--gray-300);
            color: var(--gray-700);
        }

        .search-box-wrap .search-clear.visible {
            display: flex;
        }

        @media (max-width: 640px) {
            .search-box-wrap input {
                width: 100%;
            }

            .search-box-wrap {
                width: 100%;
                margin-right: 0;
                margin-bottom: 0.5rem;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-bar>*:not(.search-box-wrap) {
                flex: 1;
                justify-content: center;
            }
        }

        .role-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-inline-start: 4px;
            cursor: help;
        }

        .role-creator {
            background: #ede9fe;
            color: #6d28d9;
        }

        .role-step {
            background: #dbeafe;
            color: #1d4ed8;
        }

        /* ─── تم تاریک ─── */
        :root[data-theme="dark"] .page-title {
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .stat-tile {
            background: var(--surface);
            border-color: var(--border-soft);
        }

        :root[data-theme="dark"] .stat-value {
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .stat-label {
            color: var(--text-muted) !important;
        }

        :root[data-theme="dark"] .filter-pill,
        :root[data-theme="dark"] .filter-dropdown-btn {
            background: var(--surface);
            border-color: var(--border-soft);
            color: var(--text-muted);
        }

        :root[data-theme="dark"] .wf-card {
            background: var(--surface);
            border-color: var(--border-soft);
        }

        :root[data-theme="dark"] .wf-title {
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .wf-id-badge {
            color: var(--text-strong);
            background: var(--border-soft);
        }

        :root[data-theme="dark"] .progress {
            background: var(--border-soft);
        }

        :root[data-theme="dark"] .wf-meta {
            color: var(--text-muted);
        }

        :root[data-theme="dark"] .mini-step:not(:last-child)::after {
            background: var(--border-soft);
        }

        :root[data-theme="dark"] .mini-dot {
            border-color: var(--surface);
            box-shadow: 0 0 0 1px var(--border-soft);
            background: var(--border-soft);
            color: var(--text-muted);
        }

        :root[data-theme="dark"] .filter-dropdown-menu {
            background: var(--surface);
            border-color: var(--border-soft);
        }

        :root[data-theme="dark"] .filter-dropdown-search {
            border-bottom-color: var(--border-soft);
        }

        :root[data-theme="dark"] .filter-dropdown-search input {
            background: var(--bg-page);
            border-color: var(--border-soft);
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .filter-dropdown-item {
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .filter-dropdown-item:hover {
            background: var(--border-soft);
        }

        :root[data-theme="dark"] .filter-dropdown-item.selected {
            background: #232a3a;
        }

        :root[data-theme="dark"] .detail-progress-wrap {
            background: var(--info-box-bg);
        }

        :root[data-theme="dark"] .detail-step {
            border-color: var(--border-soft);
            border-right-color: var(--border-soft);
        }

        :root[data-theme="dark"] .detail-step.completed {
            background: rgba(16, 185, 129, 0.1);
        }

        :root[data-theme="dark"] .detail-step.active {
            background: rgba(99, 102, 241, 0.12);
        }

        :root[data-theme="dark"] .detail-step.delayed {
            background: rgba(239, 68, 68, 0.12);
        }

        :root[data-theme="dark"] .detail-step-title {
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .meta-row {
            color: var(--text-muted);
        }

        :root[data-theme="dark"] .search-box-wrap input {
            background: var(--surface);
            border-color: var(--border-soft);
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .search-box-wrap .search-clear {
            background: var(--border-soft);
            color: var(--text-muted);
        }

        :root[data-theme="dark"] .search-box-wrap .search-clear:hover {
            background: #3a4256;
            color: var(--text-strong);
        }

    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <h2 class="page-title">
                    <i class="bi bi-diagram-3"></i>مانیتورینگ کارهای روتین
                </h2>
                <span class="live-badge">
                    <span class="live-dot"></span>
                    زنده
                </span>
            </div>
            <span id="lastUpdate">بروزرسانی: الان</span>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-tile">
                <div class="stat-icon-wrap blue"><i class="bi bi-play-circle"></i></div>
                <div>
                    <div class="stat-value" id="activeWorkflows">—</div>
                    <div class="stat-label">در حال اجرا</div>
                </div>
            </div>
            <div class="stat-tile">
                <div class="stat-icon-wrap red"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <div class="stat-value" id="delayedWorkflows">—</div>
                    <div class="stat-label">دارای تأخیر</div>
                </div>
            </div>
            <div class="stat-tile">
                <div class="stat-icon-wrap green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-value" id="completedWorkflows">—</div>
                    <div class="stat-label">تکمیل شده</div>
                </div>
            </div>
            <div class="stat-tile">
                <div class="stat-icon-wrap amber"><i class="bi bi-graph-up"></i></div>
                <div>
                    <div class="stat-value" id="avgProgress">—</div>
                    <div class="stat-label">میانگین پیشرفت</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar" id="filterBar">

            <!-- جستجو بر اساس عنوان یا شناسه -->
            <div class="search-box-wrap">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="searchInput" placeholder="جستجو بر اساس عنوان یا شناسه..."
                    oninput="handleSearch(this.value)" autocomplete="off">
                <button class="search-clear" id="searchClearBtn" onclick="clearSearch()" title="پاک کردن">
                    <i class="bi bi-x"></i>
                </button>
            </div>

            <button class="filter-pill active" data-filter="all" onclick="setStatusFilter('all', this)">
                <i class="bi bi-list-ul"></i>همه
            </button>
            <button class="filter-pill" data-filter="in_progress" onclick="setStatusFilter('in_progress', this)">
                <i class="bi bi-play"></i>در حال اجرا
            </button>
            <button class="filter-pill" data-filter="delayed" onclick="setStatusFilter('delayed', this)">
                <i class="bi bi-exclamation-triangle"></i>دارای تأخیر
            </button>
            <button class="filter-pill" data-filter="completed" onclick="setStatusFilter('completed', this)">
                <i class="bi bi-check-lg"></i>تکمیل شده
            </button>

            <!-- فیلتر روتین -->
            <div class="filter-dropdown-wrap" id="routineDropdownWrap">
                <button class="filter-dropdown-btn" id="routineDropdownBtn" onclick="toggleDropdown('routine')">
                    <i class="bi bi-diagram-3"></i>
                    <span id="routineDropdownLabel">روتین</span>
                    <span class="caret">▾</span>
                </button>
                <div class="filter-dropdown-menu" id="routineDropdownMenu">
                    <div class="filter-dropdown-search">
                        <input type="text" placeholder="جستجوی روتین..." id="routineSearchInput"
                            oninput="filterDropdownList('routine', this.value)">
                    </div>
                    <div class="filter-dropdown-list" id="routineDropdownList">
                        <div class="filter-dropdown-empty">در حال بارگذاری...</div>
                    </div>
                </div>
            </div>

            <!-- فیلتر واحد/بخش -->
            <div class="filter-dropdown-wrap" id="sectionDropdownWrap">
                <button class="filter-dropdown-btn" id="sectionDropdownBtn" onclick="toggleDropdown('section')">
                    <i class="bi bi-building"></i>
                    <span id="sectionDropdownLabel">واحد</span>
                    <span class="caret">▾</span>
                </button>
                <div class="filter-dropdown-menu" id="sectionDropdownMenu">
                    <div class="filter-dropdown-search">
                        <input type="text" placeholder="جستجوی واحد..." id="sectionSearchInput"
                            oninput="filterDropdownList('section', this.value)">
                    </div>
                    <div class="filter-dropdown-list" id="sectionDropdownList">
                        <div class="filter-dropdown-empty">در حال بارگذاری...</div>
                    </div>
                </div>
            </div>
            <!-- فیلتر: فقط روتین‌های ساخته‌ی من -->
            <button class="filter-pill" id="myRoutinesBtn" onclick="toggleMyRoutines(this)">
                <i class="bi bi-person-check"></i>روتین‌های من
            </button>
            <!-- دکمه ریست فیلترها (فقط وقتی فیلتر فعال داریم) -->
            <button class="filter-pill" id="resetFiltersBtn" onclick="resetAllFilters()"
                style="display:none; background:#fef2f2; border-color:#fecaca; color:#b91c1c;">
                <i class="bi bi-x-circle"></i>پاک کردن فیلترها
            </button>

        </div>

        <!-- Workflows Grid -->
        <div id="workflowsList" class="workflows-grid">
            <div class="state-box">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-3 mb-0">در حال بارگذاری...</p>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">جزئیات کار روتین</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody"></div>
            </div>
        </div>
    </div>
    <!-- Confirm Delete Modal -->
    <div class="modal" id="confirmDeleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="width: 20% !important;">
                <div class="modal-body text-center p-4">
                    <div style="width:56px;height:56px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                        <i class="bi bi-trash" style="font-size:1.5rem;color:var(--danger);"></i>
                    </div>
                    <h6 class="mb-2" style="font-weight:700;">حذف روتین</h6>
                    <p class="mb-1" style="font-size:0.9rem;color:var(--text-muted);">
                        روتین «<span id="confirmDeleteTitle"></span>» حذف شود؟
                    </p>
                    <p class="mb-4" style="font-size:0.8rem;color:var(--text-muted);">
                        تسک‌های در حال انجام آن متوقف و پنهان می‌شوند.
                    </p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">انصراف</button>
                        <button type="button" class="btn btn-danger flex-fill" id="confirmDeleteBtn">حذف</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Undo Toast (هماهنگ با استایل toast پروژه) -->
    <div id="undoToast" class="toast-notification">
        <div class="toast-header">
            <i class="bi bi-trash" style="color:#744ca4;"></i>
            <span>حذف روتین</span>
            <button type="button" onclick="hideUndoToast()"
                style="margin-right:auto;background:none;border:none;color:#94a3b8;cursor:pointer;font-size:1rem;line-height:1;">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="toast-body">
            <span id="undoToastBody">روتین حذف شد</span>
            <div style="margin-top:10px;">
                <button type="button" id="undoToastBtn"
                    style="display:inline-flex;align-items:center;gap:6px;background:none;border:none;color:#744ca4;font-weight:600;font-size:12.5px;cursor:pointer;padding:0;">
                    <i class="bi bi-arrow-counterclockwise"></i> بازگرداندن
                </button>
            </div>
        </div>
    </div>
    <!-- Refresh FAB -->
    <button class="fab-refresh" id="fabRefresh" onclick="loadAllData()" title="بروزرسانی">
        <i class="bi bi-arrow-clockwise"></i>
    </button>

    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script>
        let allWorkflows = [];
        let currentFilter = 'all'; // فیلتر وضعیت
        // نقش کاربر — فقط مدیریت/سرپرست دکمهٔ حذف ببینند
        let isManagerUser = false;
        try {
            const _u = JSON.parse(localStorage.getItem('user_info') || '{}');
            isManagerUser = (parseInt(_u.id) === 1) || ['management', 'supervisor'].includes(_u.role);
        } catch (e) {
            isManagerUser = false;
        }
        let searchQuery = ''; // عبارت جستجوی کاربر
        let currentRoutine = null; // id روتین انتخاب‌شده
        let _hideCompletedFromUrl = false; // وقتی از داشبورد با template آمده‌ایم
        let currentSection = null; // key واحد انتخاب‌شده
        let onlyMyRoutines = false; // فیلتر: فقط روتین‌های ساخته‌ی من
        // شناسه و واحدِ کاربر جاری (برای تشخیص نقش‌ها)
        let currentUserId = null;
        let currentUserSection = null;
        try {
            const _uu = JSON.parse(localStorage.getItem('user_info') || '{}');
            currentUserId = parseInt(_uu.id) || null;
            currentUserSection = _uu.activity_section || null;
        } catch (e) {
            currentUserId = null;
            currentUserSection = null;
        }
        let allRoutines = []; // لیست روتین‌های تعریف‌شده
        let allSections = []; // لیست واحدها

        /* ─── Data Loading ─── */
        async function loadAllData() {
            const fab = document.getElementById('fabRefresh');
            fab.classList.add('spinning');
            try {
                await loadWorkflows();
                updateLastUpdateTime();
            } catch (e) {
                console.error('خطا در بارگذاری:', e);
            } finally {
                fab.classList.remove('spinning');
            }
        }

        // آمار از روی همان دادهٔ لیست (allWorkflows) ساخته می‌شود تا دقیقاً با لیست یکی باشد
        function loadStats() {
            const list = allWorkflows || [];
            const count = s => list.filter(w => w.status === s).length;
            document.getElementById('activeWorkflows').textContent = toFa(count('in_progress'));
            document.getElementById('delayedWorkflows').textContent = toFa(count('delayed'));
            document.getElementById('completedWorkflows').textContent = toFa(count('completed'));
        }
        /* ─── Search ─── */
        function handleSearch(value) {
            searchQuery = (value || '').trim();
            const clearBtn = document.getElementById('searchClearBtn');
            clearBtn.classList.toggle('visible', searchQuery.length > 0);
            updateResetBtn();
            applyFilter();
        }

        function clearSearch() {
            const input = document.getElementById('searchInput');
            input.value = '';
            searchQuery = '';
            document.getElementById('searchClearBtn').classList.remove('visible');
            input.focus();
            updateResetBtn();
            applyFilter();
        }
        async function loadWorkflows() {
            try {
                const res = await fetch('../api/workflows/list.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();
                if (data.success) {
                    allWorkflows = data.workflows || [];
                    loadStats();
                    updateAvgProgress();

                    // 🆕 اگر با ?template=ID آمده‌ایم، همان روتین را فیلتر کن (یک‌بار)
                    applyTemplateFromUrl();

                    applyFilter(); // ← render با فیلتر فعلی، نه reset

                    // 🆕 اگر با ?instance=ID آمده‌ایم، مستقیم جزئیات همان نمونه را باز کن
                    const _inst = new URLSearchParams(location.search).get('instance');
                    if (_inst) {
                        showDetails(_inst);
                    }
                } else {
                    showError(data.message);
                }
            } catch (e) {
                console.error('loadWorkflows:', e);
                showError('خطا در بارگذاری داده‌ها');
            }
        }

        function updateAvgProgress() {
            if (!allWorkflows.length) {
                document.getElementById('avgProgress').textContent = '۰٪';
                return;
            }
            const avg = Math.round(allWorkflows.reduce((s, w) => s + (parseInt(w.progress) || 0), 0) / allWorkflows.length);
            document.getElementById('avgProgress').textContent = toFa(avg) + '٪';
        }

        /* ─── Filter ─── */
        function setStatusFilter(filter, btn) {
            currentFilter = filter;
            _hideCompletedFromUrl = false; // 🆕 کاربر دستی وضعیت انتخاب کرد، محدودیت برداشته شود
            document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            updateResetBtn();
            applyFilter();
        }
        // فعال/غیرفعال کردن فیلتر «روتین‌های من»
        function toggleMyRoutines(btn) {
            onlyMyRoutines = !onlyMyRoutines; // برعکس کردن وضعیت
            if (btn) btn.classList.toggle('active', onlyMyRoutines);
            updateResetBtn();
            applyFilter();
        }
        /* 🆕 خواندن ?template=ID از URL و اعمال فیلتر روتین */
        let _templateUrlApplied = false;

        function applyTemplateFromUrl() {
            if (_templateUrlApplied) return;

            const tpl = new URLSearchParams(location.search).get('template');
            if (!tpl) return;

            // نام قالب را از لیست روتین‌ها بگیر (r.name = نام قالب، نه عنوان نمونه)
            const routine = (typeof allRoutines !== 'undefined') ?
                allRoutines.find(r => String(r.id) === String(tpl)) :
                null;

            // اگر لیست روتین‌ها هنوز نیامده، بعداً دوباره تلاش کن
            if (!routine) return;

            _templateUrlApplied = true;
            _hideCompletedFromUrl = true; // فقط جاری‌ها (فعال + تأخیردار + در حال انجام)
            selectRoutine(tpl, routine.name);
        }

        function applyFilter() {
            let list = allWorkflows;

            // فیلتر وضعیت
            if (currentFilter !== 'all') {
                list = list.filter(w => w.status === currentFilter);
            }

            // فیلتر روتین (AND)
            if (currentRoutine !== null) {
                list = list.filter(w => String(w.workflow_id) === String(currentRoutine));
            }

            // 🆕 حالت ورود از داشبورد: تکمیل‌شده‌ها را نشان نده
            if (_hideCompletedFromUrl) {
                list = list.filter(w => w.status !== 'completed');
            }

            // فیلتر واحد — فقط روتین‌هایی که مرحله فعلی‌شان از این بخش است
            if (currentSection !== null) {
                list = list.filter(w => w.current_section === currentSection);
            }

            // فیلتر «روتین‌های من» — فقط روتین‌هایی که خودم ساخته‌ام
            if (onlyMyRoutines && currentUserId !== null) {
                list = list.filter(w => parseInt(w.created_by) === currentUserId);
            }
            // فیلتر جستجو — بر اساس عنوان یا شناسه
            if (searchQuery.length > 0) {
                const q = searchQuery.toLowerCase();
                list = list.filter(w => {
                    const titleMatch = (w.title || '').toLowerCase().includes(q);
                    const idMatch = String(w.id || '').includes(q);
                    return titleMatch || idMatch;
                });
            }
            renderWorkflows(list);
        }

        /* ─── Dropdown Filters ─── */
        function toggleDropdown(type) {
            const menu = document.getElementById(type + 'DropdownMenu');
            const btn = document.getElementById(type + 'DropdownBtn');
            const isOpen = menu.classList.contains('show');

            // بستن همه dropdown ها
            document.querySelectorAll('.filter-dropdown-menu').forEach(m => m.classList.remove('show'));
            document.querySelectorAll('.filter-dropdown-btn').forEach(b => b.classList.remove('open'));

            if (!isOpen) {
                menu.classList.add('show');
                btn.classList.add('open');
            }
        }

        // بستن dropdown با کلیک خارج
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.filter-dropdown-wrap')) {
                document.querySelectorAll('.filter-dropdown-menu').forEach(m => m.classList.remove('show'));
                document.querySelectorAll('.filter-dropdown-btn').forEach(b => b.classList.remove('open'));
            }
        });

        function selectRoutine(id, name) {
            currentRoutine = id;
            document.getElementById('routineDropdownLabel').textContent = name;
            document.getElementById('routineDropdownBtn').classList.add('active');

            // آپدیت UI آیتم‌های لیست
            document.querySelectorAll('#routineDropdownList .filter-dropdown-item').forEach(item => {
                item.classList.toggle('selected', item.dataset.id === String(id));
            });

            document.getElementById('routineDropdownMenu').classList.remove('show');
            document.getElementById('routineDropdownBtn').classList.remove('open');
            updateResetBtn();
            applyFilter();
        }

        function selectSection(key, label) {
            currentSection = key;
            document.getElementById('sectionDropdownLabel').textContent = label;
            document.getElementById('sectionDropdownBtn').classList.add('active');

            document.querySelectorAll('#sectionDropdownList .filter-dropdown-item').forEach(item => {
                item.classList.toggle('selected', item.dataset.key === key);
            });

            document.getElementById('sectionDropdownMenu').classList.remove('show');
            document.getElementById('sectionDropdownBtn').classList.remove('open');
            updateResetBtn();
            applyFilter();
        }

        function resetAllFilters() {
            currentFilter = 'all';
            _hideCompletedFromUrl = false; // 🆕 محدودیت داشبورد هم برداشته شود
            currentRoutine = null;
            currentSection = null;
            onlyMyRoutines = false; // 🆕 پاک‌کردن فیلتر «روتین‌های من»
            searchQuery = ''; // 🆕 پاک کردن سرچ

            document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
            document.querySelector('.filter-pill[data-filter="all"]').classList.add('active');

            document.getElementById('routineDropdownLabel').textContent = 'روتین';
            document.getElementById('routineDropdownBtn').classList.remove('active');
            document.getElementById('sectionDropdownLabel').textContent = 'واحد';
            document.getElementById('sectionDropdownBtn').classList.remove('active');
            document.querySelectorAll('.filter-dropdown-item').forEach(i => i.classList.remove('selected'));

            // 🆕 پاک کردن فیلد سرچ
            document.getElementById('searchInput').value = '';
            document.getElementById('searchClearBtn').classList.remove('visible');

            updateResetBtn();
            applyFilter();
        }

        function updateResetBtn() {
            const hasExtra = currentRoutine !== null || currentSection !== null || currentFilter !== 'all' || onlyMyRoutines || searchQuery.length > 0; // 🆕
            document.getElementById('resetFiltersBtn').style.display = hasExtra ? 'inline-flex' : 'none';
        }

        function filterDropdownList(type, query) {
            const listId = type + 'DropdownList';
            const items = document.querySelectorAll('#' + listId + ' .filter-dropdown-item');
            const q = query.trim().toLowerCase();
            let visible = 0;
            items.forEach(item => {
                const match = item.textContent.toLowerCase().includes(q);
                item.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            // نمایش "نتیجه‌ای نیست" اگر هیچ آیتمی نمونده
            let empty = document.querySelector('#' + listId + ' .filter-dropdown-empty');
            if (!empty) {
                empty = document.createElement('div');
                empty.className = 'filter-dropdown-empty';
                document.getElementById(listId).appendChild(empty);
            }
            empty.style.display = visible === 0 ? 'block' : 'none';
            empty.textContent = 'نتیجه‌ای یافت نشد';
        }

        /* ─── Load Routines & Sections for dropdowns ─── */
        async function loadRoutinesList() {
            try {
                const res = await fetch('../api/workflows/routines-list.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();
                if (!data.success) return;
                allRoutines = data.routines || [];
                const list = document.getElementById('routineDropdownList');
                if (!allRoutines.length) {
                    list.innerHTML = '<div class="filter-dropdown-empty">روتینی تعریف نشده</div>';
                    return;
                }
                list.innerHTML = allRoutines.map(r => `
                    <div class="filter-dropdown-item" data-id="${r.id}"
                         onclick="selectRoutine(${r.id}, '${r.name.replace(/'/g, "\'")}')">
                        <i class="bi bi-diagram-3" style="font-size:0.85rem;color:var(--primary);"></i>
                        ${r.name}
                        <i class="bi bi-check check-icon"></i>
                    </div>
                `).join('');
                // اگر با ?template آمده‌ایم و منتظر لیست روتین‌ها بودیم
                applyTemplateFromUrl();
            } catch (e) {
                console.error('loadRoutinesList:', e);
            }
        }

        async function loadSectionsList() {
            try {
                const res = await fetch('/api/organization/activity-sections.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();
                if (!data.success) return;
                allSections = data.sections || [];

                // آپدیت sectionLabels (موجود)
                allSections.forEach(sec => {
                    sectionLabels[sec.section_key] = sec.section_label;
                });

                const list = document.getElementById('sectionDropdownList');
                if (!allSections.length) {
                    list.innerHTML = '<div class="filter-dropdown-empty">واحدی تعریف نشده</div>';
                    return;
                }
                list.innerHTML = allSections.map(s => `
                    <div class="filter-dropdown-item" data-key="${s.section_key}"
                         onclick="selectSection('${s.section_key}', '${s.section_label.replace(/'/g, "\'")}')">
                        <i class="bi bi-building" style="font-size:0.85rem;color:var(--gray-500);"></i>
                        ${s.section_label}
                        <i class="bi bi-check check-icon"></i>
                    </div>
                `).join('');
            } catch (e) {
                console.error('loadSectionsList:', e);
            }
        }

        /* ─── Render ─── */
        function renderWorkflows(workflows) {
            const container = document.getElementById('workflowsList');
            if (!workflows?.length) {
                // اگر اصلاً روتینی برای کاربر نیست → پیامِ اختصاصی؛ اگر فقط فیلتر خالی است → پیامِ فیلتر
                const noneAtAll = !(allWorkflows && allWorkflows.length);
                const msg = noneAtAll ?
                    'هیچ کار روتینی مربوط به واحد یا شخص شما نیست' :
                    'هیچ کاری با این فیلتر یافت نشد';
                container.innerHTML = `<div class="state-box"><i class="bi bi-inbox"></i><p>${msg}</p></div>`;
                return;
            }
            container.innerHTML = workflows.map(renderCard).join('');
        }
        // ساخت آیکون‌های نقش کاربر برای هر روتین (سازنده / مسئول مرحله)
        function renderRoleIcons(wf) {
            let icons = '';

            // نقش ۱: سازنده‌ی روتین
            const isCreator = currentUserId !== null && parseInt(wf.created_by) === currentUserId;
            if (isCreator) {
                icons += `<span class="role-icon role-creator" title="شما سازندهٔ این روتین هستید">
                            <i class="bi bi-person-badge"></i>
                          </span>`;
            }

            // نقش ۲: مسئول مرحله (مرحله‌ی فعال مالِ واحد کاربر است)
            const isStepOwner = currentUserSection !== null &&
                wf.current_section &&
                wf.current_section === currentUserSection;
            if (isStepOwner) {
                icons += `<span class="role-icon role-step" title="مرحلهٔ فعال این روتین به واحد شما مربوط است">
                            <i class="bi bi-pin-angle-fill"></i>
                          </span>`;
            }

            return icons;
        }

        function renderCard(wf) {
            const progress = parseInt(wf.progress) || 0;
            const statusCls = {
                in_progress: 'in_progress',
                delayed: 'delayed',
                completed: 'completed',
                cancelled: 'cancelled'
            } [wf.status] || 'in_progress';
            const barCls = wf.status === 'delayed' ? 'bar-danger' :
                wf.status === 'completed' ? 'bar-success' :
                progress > 70 ? 'bar-success' :
                progress > 30 ? 'bar-warning' : '';

            const statusBadge = getStatusBadge(wf.status);
            const modeBadge = wf.execution_mode === 'parallel' ?
                `<span class="badge bg-info">موازی</span>` :
                `<span class="badge bg-secondary">آبشاری</span>`;
            const bottleneck = wf.status === 'delayed' ?
                `<span class="bottleneck-badge"><i class="bi bi-exclamation-circle"></i>گلوگاه</span>` : '';

            const deleteBtn = isManagerUser ? `
                <button class="wf-delete-btn" title="حذف روتین"
                        onclick="deleteWorkflow(${wf.id}, '${(wf.title || '').replace(/'/g, "\\'")}', event)">
                    <i class="bi bi-trash"></i>
                </button>` : '';

            return `
            <div class="wf-card ${statusCls}" onclick="showDetails(${wf.id})">
                ${deleteBtn}
                <div class="wf-header">
                    <h6 class="wf-title"><span class="wf-id-badge">${toFa(wf.id)}</span>${wf.title}</h6>
                    <div class="wf-badges">${statusBadge}${modeBadge}${bottleneck}</div>
                </div>

                <div class="progress-wrap">
                    <div class="progress">
                        <div class="progress-bar ${barCls}" style="width:${progress}%"></div>
                    </div>
                    <span class="progress-pct">${toFa(progress)}٪</span>
                </div>

                <div class="wf-meta">
                    <span><i class="bi bi-list-ol"></i>مرحله ${toFa(wf.current_stage || 1)} از ${toFa(wf.total_stages || 1)}</span>
                    <span><i class="bi bi-clock"></i>${formatDate(wf.updated_at || wf.started_at)}</span>
                </div>

                ${renderMiniTimeline(wf)}

                ${wf.status === 'delayed' ? `
                <div class="delay-strip">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    این کار دارای تأخیر است و نیاز به پیگیری دارد
                </div>` : ''}
            </div>`;
        }

        function renderMiniTimeline(wf) {
            const total = parseInt(wf.total_stages) || 1;
            const current = parseInt(wf.current_stage) || 1;
            const show = Math.min(total, 6);
            let dots = '';
            for (let i = 1; i <= show; i++) {
                const cls = i < current ? 'completed' :
                    i === current ? (wf.status === 'delayed' ? 'delayed' : 'active') :
                    '';
                const icon = i < current ? '<i class="bi bi-check"></i>' :
                    i === current ? '<i class="bi bi-arrow-left"></i>' :
                    (total > 6 && i === show ? '…' : toFa(i));
                dots += `<div class="mini-step"><div class="mini-dot ${cls}">${icon}</div></div>`;
            }
            return `<div class="mini-timeline">${dots}</div>`;
        }

        /* ─── Detail Modal ─── */
        async function showDetails(id) {
            try {
                const res = await fetch(`../api/workflows/detail.php?id=${id}`, {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();
                if (data.success) renderDetailModal(data.workflow, data.steps);
            } catch (e) {
                console.error('showDetails:', e);
            }
        }
        /* ─── حذف نرم با مودال تأیید + بازگردانی (Undo) ─── */
        let pendingDelete = {
            id: null,
            title: ''
        };
        let confirmModalInstance = null;

        function getConfirmModal() {
            if (!confirmModalInstance) {
                confirmModalInstance = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            }
            return confirmModalInstance;
        }

        function deleteWorkflow(id, title, event) {
            if (event) event.stopPropagation(); // جلوگیری از باز شدن مودال جزئیات
            pendingDelete = {
                id,
                title
            };
            document.getElementById('confirmDeleteTitle').textContent = title;
            getConfirmModal().show();
        }

        async function performDelete() {
            const {
                id,
                title
            } = pendingDelete;
            if (!id) return;

            // بستن مودال تأیید
            getConfirmModal().hide();

            try {
                const res = await fetch('../api/workflows/delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({
                        instance_id: id
                    })
                });
                const data = await res.json();
                if (data.success) {
                    allWorkflows = allWorkflows.filter(w => String(w.id) !== String(id));
                    loadAllData();
                    showUndoToast(id, title);
                } else {
                    alert(data.message || 'حذف انجام نشد');
                }
            } catch (e) {
                console.error('performDelete:', e);
                alert('خطا در ارتباط با سرور');
            }
        }

        let undoToastTimer = null;

        function showUndoToast(id, title) {
            document.getElementById('undoToastBody').textContent = `روتین «${title}» حذف شد`;
            const toastEl = document.getElementById('undoToast');

            // اتصال دکمه «بازگرداندن» به این روتین
            document.getElementById('undoToastBtn').onclick = async () => {
                hideUndoToast();
                await restoreWorkflow(id);
            };

            toastEl.classList.add('show');
            clearTimeout(undoToastTimer);
            undoToastTimer = setTimeout(hideUndoToast, 6000);
        }

        function hideUndoToast() {
            document.getElementById('undoToast').classList.remove('show');
            clearTimeout(undoToastTimer);
        }

        async function restoreWorkflow(id) {
            try {
                const res = await fetch('../api/workflows/restore.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({
                        instance_id: id
                    })
                });
                const data = await res.json();
                if (data.success) {
                    loadAllData();
                } else {
                    alert(data.message || 'بازگرداندن انجام نشد');
                }
            } catch (e) {
                console.error('restoreWorkflow:', e);
                alert('خطا در ارتباط با سرور');
            }
        }

        function renderDetailModal(wf, steps) {
            const modeLabel = wf.execution_mode === 'parallel' ? 'موازی' : 'آبشاری';
            document.getElementById('modalTitle').textContent = wf.title + ' — ' + modeLabel;
            const progress = parseInt(wf.progress) || 0;
            const barCls = wf.status === 'delayed' ? 'bar-danger' : wf.status === 'completed' ? 'bar-success' : '';

            let stepsHtml = '';
            steps.forEach((step, idx) => {
                const cls = step.status === 'completed' ? 'completed' :
                    step.status === 'active' ? 'active' :
                    step.status === 'delayed' ? 'delayed' : '';

                // گلوگاه فقط برای مرحلهٔ «فعالِ» از موعد گذشته — مرحلهٔ تکمیل‌شده گلوگاه نیست
                const activeOverdue = (step.status === 'active') && (step.is_delayed == 1);
                const completedLate = (step.status === 'completed') && (step.is_delayed == 1);

                // وضعیت نمایشی: مرحلهٔ فعال ولی هنوز شروع‌نشده → «شروع‌نشده»
                const stepBadge = (step.status === 'active' && step.task_status === 'not_started') ?
                    '<span class="badge bg-secondary">شروع‌نشده</span>' :
                    getStepStatusBadge(step.status);

                stepsHtml += `
                <div class="detail-step ${cls}" style="cursor:pointer;" onclick="window.location.href='task-detail.php?id=${step.task_id}'">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="detail-step-title">
                            <span class="badge bg-secondary ms-2">${toFa(step.stage_sequence || idx + 1)}</span>
                            ${step.step_name || 'مرحله ' + toFa(idx + 1)}
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            ${stepBadge}
                            ${activeOverdue ? '<span class="badge bg-danger">تأخیر</span>' : ''}
                            ${completedLate ? '<span class="badge bg-warning text-dark">با تأخیر تکمیل شد</span>' : ''}
                        </div>
                    </div>
                    <div class="meta-row">
                        <div>${
                            step.assignee_type === 'user'
                            ? '<span class="lbl">مسئول: </span>' + ((step.assignee_first_name || step.assignee_last_name)
                                ? `${step.assignee_first_name || ''} ${step.assignee_last_name || ''}`.trim()
                                : 'نامشخص')
                            : step.assignee_type === 'creator'
                            ? '<span class="lbl">مسئول: </span>↩ ایجادکنندهٔ روتین'
                            : '<span class="lbl">بخش: </span>' + (step.activity_section ? getSectionLabel(step.activity_section) : '<em class="text-muted">مرحله حذف‌شده</em>')
                        }</div>
                        ${step.started_at   ? `<div><span class="lbl">شروع: </span>${formatDateTime(step.started_at)}</div>` : ''}
                        ${step.completed_at ? `<div><span class="lbl">اتمام: </span>${formatDateTime(step.completed_at)}</div>` : ''}
                        ${step.completed_by_first_name ? `<div><span class="lbl">انجام‌دهنده: </span>${step.completed_by_first_name} ${step.completed_by_last_name}</div>` : ''}
                        ${step.time_limit_hours ? `<div><span class="lbl">زمان مجاز: </span>${formatDuration(step.time_limit_hours * 60)}${step.extended_minutes > 0 ? ' (+' + formatDuration(step.extended_minutes) + ')' : ''}</div>` : ''}
                        ${step.duration_minutes ? `<div><span class="lbl">زمان واقعی: </span><strong class="${activeOverdue ? 'text-danger' : (completedLate ? 'text-warning' : 'text-success')}">${formatDuration(step.duration_minutes)}</strong></div>` : ''}
                    </div>
                    ${step.completion_notes ? `<div class="mt-2" style="font-size:.85rem;background:var(--info-box-bg);border-radius:8px;padding:8px 10px;color:var(--text-strong);"><span class="lbl">توضیحات انجام‌دهنده: </span>${escHtml(step.completion_notes).replace(/\n/g, '<br>')}</div>` : ''}
                    ${activeOverdue ? `<div class="delay-strip mt-2"><i class="bi bi-exclamation-triangle-fill"></i>گلوگاه شناسایی شده! این مرحله بیش از زمان مجاز طول کشیده است.</div>` : ''}
                </div>`;
            });

            document.getElementById('modalBody').innerHTML = `
                <div class="detail-progress-wrap">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div class="d-flex gap-3 flex-wrap" style="font-size:.85rem;color:var(--text-muted)">
                            <span><strong>وضعیت:</strong> ${getStatusLabel(wf.status)}</span>
                            <span><strong>مرحله فعلی:</strong> ${wf.current_stage_name || 'نامشخص'}</span>
                            <span><strong>شروع:</strong> ${formatDate(wf.started_at)}</span>
                            ${wf.completed_at ? `<span><strong>اتمام:</strong> ${formatDate(wf.completed_at)}</span>` : ''}
                        </div>
                        <strong style="font-size:1.1rem;">${toFa(progress)}٪</strong>
                    </div>
                    <div class="progress" style="height:10px;border-radius:999px;">
                        <div class="progress-bar ${barCls}" style="width:${progress}%;border-radius:999px;"></div>
                    </div>
                </div>
                <p class="fw-600 mb-3" style="font-weight:600;">مراحل (${toFa(steps.length)})</p>
                ${stepsHtml}
            `;

            new bootstrap.Modal(document.getElementById('detailModal')).show();
        }
        /* تبدیل اعداد لاتین به فارسی */
        function toFa(n) {
            if (n === null || n === undefined) return n;
            return String(n).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹' [d]);
        }

        /* برای متنِ آزادِ کاربر (مثل توضیحاتِ تکمیلِ مرحله) قبل از innerHTML */
        function escHtml(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }
        /* ─── Helpers ─── */
        function formatDate(d) {
            if (!d) return '—';
            return new Date(d).toLocaleDateString('fa-IR', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function formatDateTime(d) {
            if (!d) return '—';
            return new Date(d).toLocaleDateString('fa-IR', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function formatDuration(mins) {
            if (!mins) return '';
            const h = Math.floor(mins / 60),
                m = mins % 60;
            if (h >= 24) {
                const d = Math.floor(h / 24),
                    rh = h % 24;
                return `${toFa(d)} روز${rh ? ' ' + toFa(rh) + ' ساعت' : ''}`;
            }
            if (h > 0) return `${toFa(h)} ساعت${m ? ' ' + toFa(m) + ' دقیقه' : ''}`;
            return `${toFa(m)} دقیقه`;
        }

        function getStatusBadge(s) {
            return {
                in_progress: '<span class="badge bg-primary">در حال اجرا</span>',
                delayed: '<span class="badge bg-danger">تأخیر دارد</span>',
                completed: '<span class="badge bg-success">تکمیل شده</span>',
                cancelled: '<span class="badge bg-secondary">لغو شده</span>'
            } [s] || `<span class="badge bg-secondary">${s}</span>`;
        }

        function getStatusLabel(s) {
            return {
                in_progress: 'در حال اجرا',
                delayed: 'دارای تأخیر',
                completed: 'تکمیل شده',
                cancelled: 'لغو شده'
            } [s] || s;
        }

        function getStepStatusBadge(s) {
            return {
                completed: '<span class="badge bg-success">تکمیل شده</span>',
                active: '<span class="badge bg-primary">در حال انجام</span>',
                delayed: '<span class="badge bg-danger">تأخیر</span>',
                pending: '<span class="badge bg-secondary">در انتظار</span>'
            } [s] || `<span class="badge bg-secondary">${s}</span>`;
        }

        let sectionLabels = {
            management: 'مدیریت',
            supervisor: 'سرپرست'
        };

        function getSectionLabel(key) {
            return sectionLabels[key] || key || '—';
        }

        async function loadSections() {
            // این تابع حالا از loadSectionsList فراخوانی می‌شود که هم label و هم dropdown را پر می‌کند
            await loadSectionsList();
        }

        function updateLastUpdateTime() {
            document.getElementById('lastUpdate').textContent = 'بروزرسانی: ' + new Date().toLocaleTimeString('fa-IR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function showError(msg) {
            document.getElementById('workflowsList').innerHTML = `<div class="state-box" style="color:var(--danger)"><i class="bi bi-exclamation-circle"></i><p>${msg}</p></div>`;
        }

        /* ─── Init ─── */
        document.addEventListener('DOMContentLoaded', async () => {
            document.getElementById('confirmDeleteBtn').addEventListener('click', performDelete);
            await Promise.all([loadSections(), loadRoutinesList()]);
            loadAllData();
        });

        setInterval(loadAllData, 60000); // هر ۶۰ ثانیه — فیلترها حفظ می‌شوند
    </script>
</body>

</html>