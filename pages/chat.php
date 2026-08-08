<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/version.php';

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
    <title>گفتگوها - سیستم مدیریت کار</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>" rel="stylesheet">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/jquery.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../assets/css/custom.css') ?>">

    <style>
        /* ═══════════════════════════════════════════════════════════
           پالتِ صفحه‌ی گفتگوها — عامدانه محدود، بدونِ گرادیان
           ───────────────────────────────────────────────────────────
           جوهری: بنفشِ تیره و خنثی، برایِ پیام‌های خودم و نشانه‌های تعاملی
           مرکب: نزدیک به مشکی، برایِ متنِ اصلی
           کهربا: تنها رنگِ دومِ صفحه، فقط برایِ نشانگرِ آنلاین/تایپ
           هر رنگِ دیگر (خاکستری/سطح) از متغیرهایِ تمِ خودِ سایت می‌آید
           ═══════════════════════════════════════════════════════════ */
        :root {
            --ink-900: #6e52a7;
            --ink-700: #46375c;
            --ink-050: #f4f1f8;
            --amber: #b8860b;
            --bubble-own-bg: #6e52a7;
        }

        :root[data-theme="dark"] {
            --ink-900: #d8cdf0;
            --ink-700: #b6a8d6;
            --ink-050: #2a2338;
            --amber: #d9a441;
            --bubble-own-bg: #4a3d68;
        }

        html,
        body {
            overflow: hidden;
            height: 100%;
        }

        body {
            padding-top: 70px;
            margin-top: 0 !important;
        }

        .chat-wrap {
            max-width: 1200px;
            height: calc(100vh - 70px - 32px);
            margin: 16px auto;
            padding: 0 16px;
            display: flex;
        }

        .chat-shell {
            display: flex;
            width: 100%;
            background: var(--surface);
            border-radius: 8px;
            border: 1px solid var(--border-soft, #e5e0ee);
            box-shadow: 0 1px 3px rgba(36, 27, 51, .06), 0 8px 24px rgba(36, 27, 51, .05);
            overflow: hidden;
        }

        .modal-content {
            max-width: 400px !important;
        }

        /* ── سایدبار — سطحِ ساده و روشن، مثلِ لیستِ گفتگویِ تلگرام ── */
        .chat-sidebar {
            width: 320px;
            flex-shrink: 0;
            background: var(--surface);
            border-left: 1px solid var(--border-soft, #eee);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .chat-sidebar-head {
            padding: 20px 20px 12px;
            display: flex;
            align-items: baseline;
            justify-content: space-between;
        }

        .chat-sidebar-head h5 {
            margin: 0;
            font-weight: 800;
            font-size: 1.2rem;
            letter-spacing: -.01em;
            color: var(--ink-900);
        }

        .chat-sidebar-head .chat-sidebar-count {
            font-size: .74rem;
            font-weight: 600;
            color: var(--ink-900);
            background: var(--surface);
            border: 1px solid var(--border-soft, #e5e0ee);
            border-radius: 20px;
            padding: 1px 9px;
            font-variant-numeric: tabular-nums;
        }

        /* دکمه‌ی شناور «گفتگوی جدید» — مثلِ FAB تلگرام، گوشه‌ی سایدبار */
        .chat-new-btn {
            position: absolute;
            inset-inline-start: 18px;
            bottom: 18px;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: none;
            background: var(--ink-900);
            color: #fff;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(36, 27, 51, .35);
            transition: transform .15s, box-shadow .15s;
            z-index: 5;
        }

        .chat-new-btn:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 18px rgba(36, 27, 51, .4);
        }

        .chat-search-box {
            padding: 0 20px 14px;
            position: relative;
        }

        .chat-search-box i.bi-search {
            position: absolute;
            top: 40%;
            inset-inline-start: 32px;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: .82rem;
            pointer-events: none;
        }

        .chat-search-box input {
            width: 100%;
            border: 1px solid transparent;
            border-radius: 999px;
            padding-block: 9px;
            padding-inline-start: 38px;
            padding-inline-end: 14px;
            font-size: .83rem;
            outline: none;
            background: var(--ink-050);
            color: var(--text-strong);
        }

        .chat-search-box input:focus {
            background: var(--surface);
            border-color: var(--ink-900);
        }

        .chat-conv-list {
            flex: 1;
            overflow-y: auto;
            padding-bottom: 76px;
        }

        /* ── نتایجِ جستجوی سراسری ── */
        .chat-search-results-heading {
            padding: 12px 18px 4px;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .chat-search-result-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding: 8px 18px;
            cursor: pointer;
            border-bottom: 1px solid var(--border-soft, #f5f5f5);
        }

        .chat-search-result-item:hover {
            background: var(--ink-050);
        }

        .chat-search-result-top {
            display: flex;
            justify-content: space-between;
            gap: 6px;
            font-size: .78rem;
        }

        .chat-search-result-conv {
            font-weight: 700;
            color: var(--ink-900);
        }

        .chat-search-result-time {
            color: var(--text-muted);
            font-size: .68rem;
            flex-shrink: 0;
        }

        .chat-search-result-snippet {
            font-size: .8rem;
            color: var(--text-strong);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-search-result-snippet mark {
            background: #ffe58a;
            border-radius: 2px;
            padding: 0 1px;
        }

        :root[data-theme="dark"] .chat-search-result-snippet mark {
            background: #8a6d00;
            color: #fff;
        }

        /* ردیفِ تختِ ساده، بدونِ کارت/حاشیه — جداسازی فقط با فاصله‌گذاری و
           هاورِ ظریف، مثلِ لیستِ گفتگویِ تلگرام */
        .chat-conv-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            cursor: pointer;
            position: relative;
        }

        .chat-conv-item:hover {
            background: var(--ink-050);
        }

        .chat-conv-item.active {
            background: var(--ink-050);
        }

        .chat-conv-item.active::before {
            content: '';
            position: absolute;
            inset-inline-start: 0;
            top: 9px;
            bottom: 9px;
            width: 3px;
            border-radius: 3px;
            background: var(--ink-900);
        }

        .chat-conv-item.active .chat-conv-name {
            color: var(--ink-900);
        }

        .chat-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--ink-900);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: .82rem;
            letter-spacing: .02em;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }

        .chat-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .chat-avatar-sm {
            width: 32px;
            height: 32px;
            font-size: .68rem;
        }

        .chat-avatar-online-dot {
            position: absolute;
            bottom: -1px;
            inset-inline-start: -1px;
            width: 11px;
            height: 11px;
            border-radius: 50%;
            background: #2f9e5b;
            border: 2px solid var(--surface);
            display: none;
        }

        .chat-avatar.online .chat-avatar-online-dot {
            display: block;
        }

        .chat-conv-info {
            flex: 1;
            min-width: 0;
        }

        .chat-conv-name-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 6px;
        }

        .chat-conv-name {
            font-weight: 700;
            font-size: .87rem;
            color: var(--text-strong);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-conv-time {
            font-size: .66rem;
            color: var(--text-muted);
            flex-shrink: 0;
            font-variant-numeric: tabular-nums;
        }

        .chat-conv-preview {
            font-size: .78rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 2px;
        }

        .chat-conv-preview.typing {
            color: var(--amber);
            font-weight: 600;
        }

        .chat-unread-badge {
            background: var(--ink-900);
            color: #fff;
            font-size: .66rem;
            font-weight: 700;
            border-radius: 999px;
            min-width: 19px;
            height: 19px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
            font-variant-numeric: tabular-nums;
        }

        .chat-unread-badge.muted {
            background: var(--text-muted);
        }

        .chat-conv-mute-icon {
            font-size: .72rem;
            color: var(--text-muted);
            margin-inline-start: 5px;
        }

        .chat-empty-list {
            padding: 40px 20px;
            text-align: center;
            color: var(--text-muted);
            font-size: .85rem;
        }

        /* ── پنجره‌ی چت ── */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .chat-main-head {
            padding: 14px 22px;
            border-bottom: 1px solid var(--border-soft, #eee);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-main-head .chat-avatar {
            width: 36px;
            height: 36px;
            font-size: .74rem;
        }

        .chat-main-head-info {
            flex: 1;
            min-width: 0;
        }

        .chat-main-head-info.clickable {
            cursor: pointer;
        }

        .chat-main-head-name {
            font-weight: 800;
            font-size: 1rem;
            letter-spacing: -.005em;
            color: var(--text-strong);
        }

        .chat-main-head-lastseen {
            font-size: .73rem;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .chat-main-head-lastseen.typing {
            color: var(--amber);
            font-weight: 600;
        }

        .chat-search-toggle-btn {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .chat-search-toggle-btn:hover {
            background: var(--ink-050);
            color: var(--ink-900);
        }

        .chat-msg-search-bar {
            display: none;
            align-items: center;
            gap: 8px;
            flex: 1;
        }

        .chat-msg-search-bar.show {
            display: flex;
        }

        .chat-msg-search-bar input {
            flex: 1;
            border: none;
            border-bottom: 1.5px solid var(--border-soft, #e5e7eb);
            padding: 6px 2px;
            font-size: .83rem;
            outline: none;
            background: transparent;
            color: var(--text-strong);
        }

        .chat-msg-search-bar input:focus {
            border-bottom-color: var(--ink-900);
        }

        .chat-msg-search-count {
            font-size: .72rem;
            color: var(--text-muted);
            white-space: nowrap;
            flex-shrink: 0;
            font-variant-numeric: tabular-nums;
        }

        .chat-msg-search-nav-btn {
            width: 26px;
            height: 26px;
            border-radius: 4px;
            border: none;
            background: var(--ink-050);
            color: var(--ink-900);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: .82rem;
            flex-shrink: 0;
        }

        .chat-msg-search-nav-btn:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .chat-bubble-highlight {
            background: #ffe58a;
            border-radius: 2px;
            padding: 0 1px;
        }

        :root[data-theme="dark"] .chat-bubble-highlight {
            background: #8a6d00;
            color: #fff;
        }

        /* ── نوارِ پیامِ سنجاق‌شده ── */
        .chat-pinned-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 18px;
            background: var(--ink-050);
            border-bottom: 1px solid var(--border-soft, #eee);
            cursor: pointer;
        }

        .chat-pinned-banner > i.bi-pin-angle-fill {
            color: var(--ink-900);
            font-size: .95rem;
            flex-shrink: 0;
        }

        .chat-pinned-banner-body {
            flex: 1;
            min-width: 0;
        }

        .chat-pinned-banner-label {
            font-size: .68rem;
            font-weight: 700;
            color: var(--ink-900);
        }

        .chat-pinned-banner-text {
            font-size: .8rem;
            color: var(--text-strong);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-pinned-banner-close {
            cursor: pointer;
            color: var(--text-muted);
            font-size: 1rem;
            flex-shrink: 0;
        }

        .chat-placeholder {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            gap: 10px;
        }

        .chat-placeholder i {
            font-size: 2.2rem;
            opacity: .25;
        }

        .chat-messages-wrap {
            flex: 1;
            position: relative;
            min-height: 0;
        }

        .chat-messages {
            height: 100%;
            overflow-y: auto;
            padding: 20px 22px;
            display: flex;
            flex-direction: column;
            gap: 3px;
            background: var(--chat-bg, #eeecf4);
        }

        :root[data-theme="dark"] .chat-messages {
            --chat-bg: #1a1622;
        }

        .chat-scroll-bottom-btn {
            position: absolute;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%) translateY(12px);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid var(--border-soft, #e5e0ee);
            background: var(--surface);
            color: var(--ink-900);
            box-shadow: 0 2px 10px rgba(0, 0, 0, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            cursor: pointer;
            opacity: 0;
            pointer-events: none;
            transition: opacity .15s, transform .15s;
            z-index: 5;
        }

        .chat-scroll-bottom-btn.show {
            opacity: 1;
            pointer-events: auto;
            transform: translateX(-50%) translateY(0);
        }

        .chat-day-sep {
            text-align: center;
            font-size: .7rem;
            color: var(--text-muted);
            margin: 12px 0;
            position: relative;
        }

        .chat-bubble-row {
            display: flex;
            position: relative;
            align-items: flex-end;
            gap: 4px;
            margin-bottom: 3px;
        }

        /* در RTL، flex-start معادلِ سمتِ راست است — پیام‌های خودم راست، طرفِ مقابل چپ */
        .chat-bubble-row.own {
            justify-content: flex-start;
        }

        .chat-bubble-row.other {
            justify-content: flex-end;
        }

        .chat-bubble {
            max-width: 66%;
            padding: 8px 12px;
            border-radius: 14px;
            font-size: .87rem;
            line-height: 1.65;
            position: relative;
        }

        .chat-bubble-sender-name {
            font-weight: 700;
            font-size: .78rem;
            margin-bottom: 2px;
        }

        .chat-bubble-forward-label {
            font-size: .74rem;
            font-style: italic;
            opacity: .75;
            margin-bottom: 3px;
        }

        .chat-bubble-edited-tag {
            font-size: .63rem;
            opacity: .65;
            margin-inline-start: 4px;
        }

        /* ── منویِ راست‌کلیک ── */
        .chat-ctx-menu {
            position: fixed;
            z-index: 2000;
            background: var(--surface);
            border-radius: 4px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, .18);
            border: 1px solid var(--border-soft, #eee);
            padding: 4px;
            min-width: 148px;
            display: none;
        }

        .chat-ctx-menu.show {
            display: block;
        }

        .chat-ctx-emoji-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 6px 8px;
            border-bottom: 1px solid var(--border-soft, #eee);
            margin-bottom: 4px;
        }

        .chat-ctx-emoji-row span {
            cursor: pointer;
            font-size: 1.15rem;
            padding: 2px 4px;
            border-radius: 4px;
            transition: transform .1s, background .1s;
        }

        .chat-ctx-emoji-row span:hover {
            background: var(--ink-050);
            transform: scale(1.15);
        }

        .chat-bubble-reactions {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 5px;
        }

        .chat-reaction-pill {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: rgba(255, 255, 255, .16);
            border-radius: 12px;
            padding: 1px 7px;
            font-size: .78rem;
            cursor: pointer;
            line-height: 1.6;
        }

        .chat-bubble-row.other .chat-reaction-pill {
            background: var(--ink-050);
        }

        .chat-reaction-pill.mine {
            box-shadow: 0 0 0 1.5px var(--amber) inset;
        }

        .chat-ctx-menu-item {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: .82rem;
            color: var(--text-strong);
        }

        .chat-ctx-menu-item i {
            font-size: .92rem;
            width: 16px;
            text-align: center;
            color: var(--ink-900);
        }

        .chat-ctx-menu-item:hover {
            background: var(--ink-050);
        }

        .chat-ctx-menu-item.danger i,
        .chat-ctx-menu-item.danger {
            color: #b3382c;
        }

        .chat-ctx-menu-item.danger:hover {
            background: #fbeae7;
        }

        /* ── نوارِ ویرایش ── */
        .chat-edit-banner {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            background: var(--ink-050);
            border-top: 1px solid var(--border-soft, #eee);
            font-size: .8rem;
            color: var(--ink-900);
        }

        .chat-edit-banner.show {
            display: flex;
        }

        .chat-edit-banner-text {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-edit-banner-cancel {
            cursor: pointer;
            color: var(--text-muted);
            font-size: 1.05rem;
        }

        /* ── نوارِ پاسخ ── */
        .chat-reply-banner {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            background: var(--ink-050);
            border-top: 1px solid var(--border-soft, #eee);
            border-right: 3px solid var(--ink-900);
            font-size: .8rem;
        }

        .chat-reply-banner.show {
            display: flex;
        }

        .chat-reply-banner i.bi-reply-fill {
            font-size: .95rem;
            color: var(--ink-900);
        }

        .chat-reply-banner-body {
            flex: 1;
            min-width: 0;
        }

        .chat-reply-banner-name {
            font-weight: 700;
            color: var(--ink-900);
            font-size: .76rem;
        }

        .chat-reply-banner-text {
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-reply-banner-cancel {
            cursor: pointer;
            color: var(--text-muted);
            font-size: 1.05rem;
        }

        /* ── نقل‌قول در حبابِ پاسخ ── */
        .chat-bubble-quote {
            border-right: 3px solid rgba(255, 255, 255, .5);
            padding: 4px 9px;
            margin-bottom: 6px;
            border-radius: 6px;
            background: rgba(255, 255, 255, .14);
            cursor: pointer;
            font-size: .77rem;
        }

        .chat-bubble-row.other .chat-bubble-quote {
            border-right-color: var(--ink-900);
            background: var(--ink-050);
        }

        .chat-bubble-quote-name {
            font-weight: 700;
            opacity: .9;
            display: block;
        }

        .chat-bubble-quote-text {
            opacity: .8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }

        .chat-bubble-quote-deleted {
            opacity: .7;
            font-style: italic;
        }

        @keyframes chat-highlight-flash {
            0%, 100% { background: transparent; }
            30% { background: var(--ink-050); }
        }

        .chat-bubble-row.flash .chat-bubble {
            animation: chat-highlight-flash 1.2s ease;
        }

        /* حباب‌ها: رنگِ تخت، بدونِ گرادیان؛ گوشه‌ی نزدیک به فرستنده (دُم) تیزتر */
        .chat-bubble-row.own .chat-bubble {
            background: var(--bubble-own-bg);
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .chat-bubble-row.other .chat-bubble {
            background: var(--surface);
            color: var(--text-strong);
            box-shadow: 0 1px 2px rgba(36, 27, 51, .09);
            border-bottom-left-radius: 4px;
        }

        .chat-bubble-time {
            font-size: .65rem;
            opacity: .7;
            margin-top: 3px;
            text-align: left;
            font-variant-numeric: tabular-nums;
        }

        .chat-bubble-ticks {
            margin-inline-start: 3px;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }

        .chat-bubble-ticks i {
            font-size: .82rem;
            vertical-align: -1px;
        }

        .chat-bubble-ticks i.seen {
            color: #6dc4ff;
            opacity: 1;
        }

        .chat-bubble-seen-count {
            font-size: .64rem;
            opacity: 1;
            color: #6dc4ff;
        }

        .chat-bubble-images {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }

        .chat-bubble-images img {
            width: 140px;
            height: 140px;
            object-fit: cover;
            border-radius: 3px;
            cursor: pointer;
        }

        .chat-bubble-file {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, .15);
            border-radius: 3px;
            padding: 7px 10px;
            margin-top: 6px;
            cursor: pointer;
            font-size: .78rem;
        }

        .chat-bubble-row.other .chat-bubble-file {
            background: rgba(0, 0, 0, .05);
        }

        .chat-composer {
            position: relative;
            border-top: 1px solid var(--border-soft, #eee);
            padding: 12px 18px;
            display: flex;
            align-items: flex-end;
            gap: 10px;
        }

        .chat-mention-autocomplete {
            position: absolute;
            bottom: 100%;
            inset-inline-start: 18px;
            inset-inline-end: 18px;
            margin-bottom: 6px;
            background: var(--surface);
            border: 1px solid var(--border-soft, #e5e0ee);
            border-radius: 8px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, .14);
            max-height: 220px;
            overflow-y: auto;
            z-index: 20;
        }

        .chat-mention-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: .84rem;
        }

        .chat-mention-item:hover,
        .chat-mention-item.active {
            background: var(--ink-050);
        }

        .chat-mention {
            font-weight: 700;
            color: var(--ink-900);
        }

        .chat-bubble-row.own .chat-mention {
            color: #fff;
            text-decoration: underline;
        }

        .chat-mention.me {
            color: var(--amber);
        }

        .chat-bubble-row.own .chat-mention.me {
            color: #ffd685;
        }

        .chat-mention-autocomplete {
            position: absolute;
            bottom: 100%;
            right: 18px;
            left: 18px;
            margin-bottom: 6px;
            background: var(--surface);
            border: 1px solid var(--border-soft, #e5e0ee);
            border-radius: 8px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .14);
            max-height: 180px;
            overflow-y: auto;
            z-index: 20;
        }

        .chat-mention-item {
            padding: 8px 12px;
            font-size: .85rem;
            cursor: pointer;
            color: var(--text-strong);
        }

        .chat-mention-item.active,
        .chat-mention-item:hover {
            background: var(--ink-050);
        }

        .chat-mention {
            font-weight: 700;
            color: var(--ink-900);
        }

        .chat-bubble-row.own .chat-mention {
            color: #fff;
            text-decoration: underline;
        }

        .chat-mention.me {
            color: var(--amber);
        }

        .chat-bubble-row.own .chat-mention.me {
            color: #ffd685;
        }

        .chat-attach-btn,
        .chat-send-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            font-size: 1.05rem;
        }

        #chatAttachBtn {
            background: transparent;
            color: var(--text-muted);
        }

        #chatAttachBtn:hover {
            background: var(--ink-050);
            color: var(--ink-900);
        }

        .chat-send-btn {
            background: var(--ink-900);
            color: #fff;
        }

        .chat-send-btn:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .chat-composer-input {
            flex: 1;
            border: 1.5px solid var(--border-soft, #e5e7eb);
            border-radius: 20px;
            padding: 9px 16px;
            font-size: .87rem;
            line-height: 1.5;
            resize: none;
            overflow-y: hidden;
            outline: none;
            font-family: inherit;
            background: var(--ink-050);
            color: var(--text-strong);
        }

        .chat-composer-input:focus {
            border-color: var(--ink-900);
            background: var(--surface);
        }

        .chat-pending-files {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 0 18px 8px;
        }

        .chat-pending-chip {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--ink-050);
            border-radius: 3px;
            padding: 4px 8px;
            font-size: .74rem;
        }

        .chat-pending-chip i.remove {
            cursor: pointer;
            color: #b3382c;
        }

        /* ── مودالِ گفتگوی جدید ── */
        #newChatModal .modal-dialog {
            max-width: 350px;
        }

        #newChatUserResults {
            max-height: 448px;
            overflow-y: auto;
        }

        .chat-user-result {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        .chat-user-result:hover,
        .chat-user-result.kb-active {
            background: var(--ink-050);
        }

        .chat-user-result-info {
            flex: 1;
            min-width: 0;
        }

        .chat-user-result-name {
            font-size: .87rem;
            font-weight: 600;
            color: var(--text-strong);
        }

        .chat-user-result-section {
            font-size: .72rem;
            color: var(--ink-900);
            background: var(--ink-050);
            display: inline-block;
            padding: 1px 7px;
            border-radius: 3px;
            margin-right: 6px;
        }

        .chat-user-result-lastseen {
            font-size: .72rem;
            color: var(--text-muted);
            flex-shrink: 0;
            white-space: nowrap;
        }

        .chat-user-result-lastseen.online {
            color: #2f9e5b;
            font-weight: 600;
        }

        /* ── تبِ گفتگوی مستقیم / گروهِ جدید ── */
        .chat-modal-tabs {
            display: flex;
            gap: 4px;
            background: var(--ink-050);
            border-radius: 8px;
            padding: 3px;
            margin-bottom: 12px;
        }

        .chat-modal-tab {
            flex: 1;
            border: none;
            background: transparent;
            padding: 7px;
            border-radius: 6px;
            font-size: .8rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
        }

        .chat-modal-tab.active {
            background: var(--surface);
            color: var(--ink-900);
            box-shadow: 0 1px 3px rgba(36, 27, 51, .12);
        }

        .chat-user-result.selected {
            background: var(--ink-050);
        }

        .chat-user-result-check {
            flex-shrink: 0;
            width: 16px;
            height: 16px;
            accent-color: var(--ink-900);
            cursor: pointer;
        }

        .chat-group-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
        }

        .chat-group-chip {
            display: flex;
            align-items: center;
            gap: 5px;
            background: var(--ink-050);
            color: var(--ink-900);
            border-radius: 20px;
            padding: 3px 6px 3px 10px;
            font-size: .75rem;
            font-weight: 600;
        }

        .chat-group-chip i {
            cursor: pointer;
            font-size: .85rem;
        }

        /* ── مودالِ اطلاعاتِ گروه ── */
        .chat-group-avatar-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
            position: relative;
            width: fit-content;
            margin-inline: auto;
        }

        .chat-group-avatar-big {
            width: 84px;
            height: 84px;
            font-size: 1.6rem;
        }

        .chat-group-avatar-edit-badge {
            position: absolute;
            bottom: 0;
            inset-inline-end: 0;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--ink-900);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .78rem;
            border: 2px solid var(--surface);
            cursor: pointer;
        }

        .chat-group-member-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 4px;
        }

        .chat-group-member-name {
            flex: 1;
            font-size: .86rem;
            color: var(--text-strong);
        }

        .chat-group-owner-tag {
            font-size: .68rem;
            color: var(--ink-900);
            background: var(--ink-050);
            border-radius: 3px;
            padding: 1px 6px;
            margin-inline-start: 6px;
        }

        .chat-group-member-remove {
            border: none;
            background: transparent;
            color: #b3382c;
            cursor: pointer;
            font-size: .95rem;
            padding: 4px;
        }

        @media (max-width: 768px) {
            .chat-wrap {
                padding: 0;
                height: calc(100vh - 70px);
                margin: 0;
            }

            .chat-shell {
                border-radius: 0;
            }

            .chat-sidebar {
                width: 100%;
                position: absolute;
                inset: 0;
                z-index: 5;
                background: var(--surface);
            }

            .chat-sidebar.hide-mobile {
                display: none;
            }

            .chat-main.hide-mobile {
                display: none;
            }

            .chat-back-btn {
                display: inline-flex !important;
            }
        }

        .chat-back-btn {
            display: none;
            border: none;
            background: transparent;
            font-size: 1.2rem;
            color: var(--ink-900);
            cursor: pointer;
        }
    </style>
</head>

<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/pages/header.php'; ?>

    <div class="chat-wrap">
        <div class="chat-shell">

            <!-- سایدبار -->
            <div class="chat-sidebar" id="chatSidebar">
                <div class="chat-sidebar-head">
                    <h5>گفتگوها</h5>
                    <span class="chat-sidebar-count" id="chatSidebarCount"></span>
                </div>
                <div class="chat-search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="convSearchInput" placeholder="جستجو در گفتگوها و پیام‌ها..." oninput="onConvSearchInput()">
                </div>
                <div class="chat-conv-list" id="convListWrap">
                    <div id="convList">
                        <div class="chat-empty-list">در حال بارگذاری...</div>
                    </div>
                    <div id="msgSearchResultsSection" style="display:none;">
                        <div class="chat-search-results-heading">پیام‌های یافت‌شده</div>
                        <div id="msgSearchResultsList"></div>
                    </div>
                </div>
                <button class="chat-new-btn" onclick="openNewChatModal()" title="گفتگوی جدید">
                    <i class="bi bi-pencil-fill"></i>
                </button>
            </div>

            <!-- پنجرهٔ چت -->
            <div class="chat-main" id="chatMain">
                <div class="chat-placeholder" id="chatPlaceholder">
                    <i class="bi bi-chat-square-text"></i>
                    <div>یک گفتگو را انتخاب کنید یا گفتگوی جدیدی شروع کنید</div>
                </div>

                <div id="chatActiveView" style="display:none; flex:1; flex-direction:column; min-height:0;">
                    <div class="chat-main-head">
                        <button class="chat-back-btn" onclick="closeConversation()"><i class="bi bi-arrow-right"></i></button>
                        <div class="chat-avatar" id="chatHeadAvatar">?<span class="chat-avatar-online-dot"></span></div>
                        <div class="chat-main-head-info" id="chatHeadInfoWrap" onclick="openGroupInfoIfApplicable()">
                            <div class="chat-main-head-name" id="chatHeadName">—</div>
                            <div class="chat-main-head-lastseen" id="chatHeadLastSeen"></div>
                        </div>

                        <div class="chat-msg-search-bar" id="chatMsgSearchBar">
                            <span class="chat-msg-search-count" id="chatMsgSearchCount"></span>
                            <button class="chat-msg-search-nav-btn" id="chatMsgSearchPrevBtn" onclick="navMsgSearch(-1)" title="نتیجهٔ قبلی"><i class="bi bi-chevron-up"></i></button>
                            <button class="chat-msg-search-nav-btn" id="chatMsgSearchNextBtn" onclick="navMsgSearch(1)" title="نتیجهٔ بعدی"><i class="bi bi-chevron-down"></i></button>
                            <input type="text" id="chatMsgSearchInput" placeholder="جستجو در این گفتگو..." oninput="runMsgSearch()">
                        </div>

                        <button class="chat-search-toggle-btn" id="chatMuteToggleBtn" onclick="toggleMuteActiveConversation()" title="بی‌صداکردنِ این گفتگو">
                            <i class="bi bi-bell"></i>
                        </button>

                        <button class="chat-search-toggle-btn" id="chatSearchToggleBtn" onclick="toggleMsgSearch()" title="جستجو در گفتگو">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>

                    <div class="chat-pinned-banner" id="chatPinnedBanner" style="display:none;">
                        <i class="bi bi-pin-angle-fill"></i>
                        <div class="chat-pinned-banner-body" onclick="scrollToOriginalMessage(pinnedMessage && pinnedMessage.id)">
                            <div class="chat-pinned-banner-label">پیامِ سنجاق‌شده</div>
                            <div class="chat-pinned-banner-text" id="chatPinnedBannerText"></div>
                        </div>
                        <i class="bi bi-x-lg chat-pinned-banner-close" id="chatPinnedBannerClose" onclick="unpinCurrentMessage()" title="برداشتنِ سنجاق"></i>
                    </div>

                    <div class="chat-messages-wrap">
                        <div class="chat-messages" id="chatMessages"></div>
                        <button class="chat-scroll-bottom-btn" id="chatScrollBottomBtn" onclick="scrollChatToBottom()" title="برو به آخرین پیام">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>

                    <div class="chat-pending-files" id="pendingFiles" style="display:none;"></div>

                    <div class="chat-edit-banner" id="chatEditBanner">
                        <i class="bi bi-pencil-square"></i>
                        <span class="chat-edit-banner-text">در حالِ ویرایشِ پیام</span>
                        <i class="bi bi-x-lg chat-edit-banner-cancel" onclick="cancelEditMessage()" title="انصراف از ویرایش"></i>
                    </div>

                    <div class="chat-reply-banner" id="chatReplyBanner">
                        <i class="bi bi-reply-fill"></i>
                        <div class="chat-reply-banner-body">
                            <span class="chat-reply-banner-name" id="chatReplyBannerName"></span>
                            <span class="chat-reply-banner-text" id="chatReplyBannerText"></span>
                        </div>
                        <i class="bi bi-x-lg chat-reply-banner-cancel" onclick="cancelReplyMessage()" title="انصراف از پاسخ"></i>
                    </div>

                    <div class="chat-composer">
                        <div class="chat-mention-autocomplete" id="mentionAutocomplete" style="display:none;"></div>
                        <button class="chat-attach-btn" id="chatAttachBtn" onclick="document.getElementById('chatFileInput').click()" title="پیوست فایل">
                            <i class="bi bi-paperclip"></i>
                        </button>
                        <input type="file" id="chatFileInput" multiple style="display:none;">
                        <textarea class="chat-composer-input" id="chatComposerInput" rows="1" placeholder="پیامی بنویسید..."></textarea>
                        <button class="chat-send-btn" id="chatSendBtn" onclick="sendChatMessage()">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- مودالِ گفتگوی جدید -->
    <div class="modal fade" id="newChatModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="newChatModalTitle">شروعِ گفتگوی جدید</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="chat-modal-tabs" id="newChatModeTabs">
                        <button type="button" class="chat-modal-tab active" id="newChatTabDirect" onclick="switchNewChatMode('direct')">گفتگوی مستقیم</button>
                        <button type="button" class="chat-modal-tab" id="newChatTabGroup" onclick="switchNewChatMode('group')">گروهِ جدید</button>
                    </div>
                    <input type="text" class="form-control mb-2" id="newGroupTitleInput" placeholder="نامِ گروه..." autocomplete="off" style="display:none;" oninput="updateCreateGroupBtnState()">
                    <div class="chat-group-chips" id="groupSelectedChips" style="display:none;"></div>
                    <input type="text" class="form-control mb-3" id="newChatSearchInput" placeholder="جستجوی نام همکار..." autocomplete="off">
                    <div id="newChatUserResults"></div>
                </div>
                <div class="modal-footer" id="newGroupFooter" style="display:none;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary btn-sm" id="createGroupBtn" onclick="submitNewChatModalAction()" disabled>ایجادِ گروه</button>
                </div>
            </div>
        </div>
    </div>

    <!-- مودالِ اطلاعاتِ گروه -->
    <div class="modal fade" id="groupInfoModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="groupInfoTitle">اطلاعاتِ گروه</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="chat-group-avatar-wrap" id="groupInfoAvatarWrap" onclick="triggerGroupAvatarUpload()"></div>
                    <input type="file" id="groupAvatarFileInput" accept="image/*" style="display:none;" onchange="uploadGroupAvatar(this.files[0])">
                    <div id="groupInfoMemberList"></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2" id="groupInfoAddBtn" style="display:none;" onclick="openAddMembersMode()">
                        <i class="bi bi-person-plus"></i> افزودنِ عضو
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmLeaveGroup()">
                        <i class="bi bi-box-arrow-right"></i> خروج از گروه
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- مودالِ فورواردِ پیام -->
    <div class="modal fade" id="forwardModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">فوروارد به...</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control mb-3" id="forwardSearchInput" placeholder="جستجو در گفتگوها..." autocomplete="off" oninput="renderForwardTargetList()">
                    <div id="forwardTargetList" style="max-height:340px; overflow-y:auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- مودالِ تأییدِ حذفِ پیام -->
    <div class="modal fade" id="deleteMessageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">حذفِ پیام</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="deleteMsgForEveryoneCheck" checked>
                        <label class="form-check-label" for="deleteMsgForEveryoneCheck" id="deleteMsgForEveryoneLabel"></label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteMessage()">حذف پیام</button>
                </div>
            </div>
        </div>
    </div>

    <!-- منویِ راست‌کلیکِ پیام -->
    <div class="chat-ctx-menu" id="chatCtxMenu">
        <div class="chat-ctx-emoji-row">
            <span onclick="reactFromCtxMenu('👍')">👍</span>
            <span onclick="reactFromCtxMenu('❤️')">❤️</span>
            <span onclick="reactFromCtxMenu('😂')">😂</span>
            <span onclick="reactFromCtxMenu('😮')">😮</span>
            <span onclick="reactFromCtxMenu('😢')">😢</span>
            <span onclick="reactFromCtxMenu('🙏')">🙏</span>
        </div>
        <div class="chat-ctx-menu-item" onclick="replyFromCtxMenu()">
            <i class="bi bi-reply-fill"></i>
            <span>پاسخ</span>
        </div>
        <div class="chat-ctx-menu-item" onclick="forwardFromCtxMenu()">
            <i class="bi bi-arrow-return-right"></i>
            <span>فوروارد</span>
        </div>
        <div class="chat-ctx-menu-item" id="chatCtxPinItem" onclick="pinFromCtxMenu()">
            <i class="bi bi-pin-angle-fill"></i>
            <span id="chatCtxPinLabel">سنجاق‌کردن</span>
        </div>
        <div class="chat-ctx-menu-item" id="chatCtxEditItem" onclick="startEditFromCtxMenu()">
            <i class="bi bi-pencil"></i>
            <span>ویرایش پیام</span>
        </div>
        <div class="chat-ctx-menu-item danger" id="chatCtxDeleteItem" onclick="deleteFromCtxMenu()">
            <i class="bi bi-trash3"></i>
            <span>حذف پیام</span>
        </div>
    </div>

    <script src="<?= asset('../assets/js/alert.js') ?>"></script>
    <script>
        // ⚠️ عمداً بدونِ «= null»: header.php از قبل، توی یک IIFE سینکرون (که زودتر از این
        // اسکریپت اجرا می‌شه)، authToken رو درست از localStorage خونده. اگه اینجا با
        // «= null» دوباره تعریفش کنیم، همون مقدارِ درستِ header.php رو پاک می‌کنه — و چون
        // initializeHeader() توی header.php هم زودتر (روی DOMContentLoaded) اجرا می‌شه و
        // authToken رو در اون لحظه null می‌بینه، loadAttendanceStatus/loadNotifications و
        // بقیه‌ی کارهای هدر (که پشتِ if(authToken) قفلن) رو کلاً رد می‌کنه — دقیقاً همون
        // چیزی که باعث می‌شد آیکنِ ورود/خروج توی صفحه‌ی چت برای همیشه لودینگ بمونه.
        var authToken;
        var myUserId = null;
        var conversations = [];
        var activeConversationId = null;
        var activeConversationTitle = '';
        var activeConversationType = 'direct';
        var lastMessageId = 0;
        var pendingFiles = [];
        var pollTimer = null;
        var readReceipts = {}; // user_id -> آخرین پیامِ‌خوانده‌شده‌یِ او، فقط برایِ گفتگویِ فعال
        var pinnedMessage = null; // { id, snippet, user_name, is_own } یا null
        var pinnedCanManage = false;
        var activeGroupMembers = []; // [{id, full_name}] — فقط برایِ گفتگویِ گروهیِ فعال، برایِ منشن

        document.addEventListener('DOMContentLoaded', function() {
            authToken = localStorage.getItem('auth_token');
            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }

            var info = JSON.parse(localStorage.getItem('user_info') || '{}');
            myUserId = info.id || null;

            var params = new URLSearchParams(window.location.search);
            var preOpenId = params.get('conversation_id');

            loadConversations(function() {
                if (preOpenId) openConversation(parseInt(preOpenId, 10));
            });

            document.getElementById('chatFileInput').addEventListener('change', function() {
                addPendingFiles(this.files);
                this.value = '';
            });

            var composer = document.getElementById('chatComposerInput');
            composer.addEventListener('input', function() {
                autoGrowComposer(this);
                notifyTyping();
                checkMentionTrigger();
            });
            composer.addEventListener('blur', function() {
                // تأخیرِ کوتاه تا رویدادِ کلیک روی گزینه‌یِ اتوکامپلیت زودتر ثبت شود
                setTimeout(closeMentionAutocomplete, 150);
            });
            composer.addEventListener('paste', function(e) {
                var items = (e.clipboardData || window.clipboardData).items;
                if (!items) return;
                var pasted = [];
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image/') === 0) {
                        var f = items[i].getAsFile();
                        if (f) {
                            var ext = (f.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
                            pasted.push(new File([f], 'clipboard-' + Date.now() + '.' + ext, {
                                type: f.type
                            }));
                        }
                    }
                }
                if (pasted.length) {
                    e.preventDefault();
                    addPendingFiles(pasted);
                }
            });
            composer.addEventListener('keydown', function(e) {
                var autocompleteOpen = document.getElementById('mentionAutocomplete').style.display === 'block';
                if (autocompleteOpen && mentionCandidates.length) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        mentionActiveIndex = Math.min(mentionActiveIndex + 1, mentionCandidates.length - 1);
                        renderMentionAutocomplete();
                        return;
                    }
                    if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        mentionActiveIndex = Math.max(mentionActiveIndex - 1, 0);
                        renderMentionAutocomplete();
                        return;
                    }
                    if (e.key === 'Enter' || e.key === 'Tab') {
                        e.preventDefault();
                        applyMention(mentionActiveIndex >= 0 ? mentionActiveIndex : 0);
                        return;
                    }
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        closeMentionAutocomplete();
                        return;
                    }
                }
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendChatMessage();
                }
            });

            pollTimer = setInterval(pollForUpdates, 4000);

            // نشانگرِ «در حالِ تایپ» — بازه‌ی کوتاه‌تر تا واکنشِ سریع‌تری حس بشه
            setInterval(pollTypingStatus, 2000);

            // ثبتِ حضور در صفحه‌ی چت — برای «آخرین بازدید» در مودالِ گفتگوی جدید
            sendChatPing();
            setInterval(sendChatPing, 60000);

            // نمایشِ دکمهٔ «برو به آخرین پیام» وقتی کاربر از پایینِ لیست فاصله می‌گیرد
            document.getElementById('chatMessages').addEventListener('scroll', function () {
                var el = this;
                var distanceFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
                document.getElementById('chatScrollBottomBtn').classList.toggle('show', distanceFromBottom > 200);
            });

            document.getElementById('chatMsgSearchInput').addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    navMsgSearch(e.shiftKey ? -1 : 1);
                } else if (e.key === 'Escape') {
                    closeMsgSearch();
                }
            });
        });

        function scrollChatToBottom() {
            var el = document.getElementById('chatMessages');
            el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
        }

        function sendChatPing() {
            fetch('../api/chat/ping.php', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + authToken
                }
            }).catch(function() {});
        }

        function esc(s) {
            return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // رشدِ خودکارِ کادرِ نوشتن تا سقفِ ۱۰ خط؛ بعد از آن اسکرولِ داخلیِ خودِ کادر فعال می‌شود
        function autoGrowComposer(el) {
            var lineHeight = parseFloat(getComputedStyle(el).lineHeight);
            var verticalPadding = parseFloat(getComputedStyle(el).paddingTop) + parseFloat(getComputedStyle(el).paddingBottom);
            var maxHeight = Math.round(lineHeight * 10 + verticalPadding);

            el.style.height = 'auto';
            var neededHeight = el.scrollHeight;

            if (neededHeight > maxHeight) {
                el.style.height = maxHeight + 'px';
                el.style.overflowY = 'auto';
            } else {
                el.style.height = neededHeight + 'px';
                el.style.overflowY = 'hidden';
            }
        }

        // ─────────────── جستجو در پیام‌های گفتگویِ جاری ───────────────
        // ⚠️ فقط روی پیام‌هایی که همین الان در DOM لود شده‌اند (لیستِ نمایشی)
        // جستجو می‌کند، نه کلِ تاریخچه — چون هنوز endpoint جستجوی سمتِ سرور نداریم
        var msgSearchMatches = [];
        var msgSearchActiveIdx = -1;

        function toggleMsgSearch() {
            var bar = document.getElementById('chatMsgSearchBar');
            if (bar.classList.contains('show')) {
                closeMsgSearch();
            } else {
                bar.classList.add('show');
                document.getElementById('chatMsgSearchInput').focus();
            }
        }

        function closeMsgSearch() {
            document.getElementById('chatMsgSearchBar').classList.remove('show');
            document.getElementById('chatMsgSearchInput').value = '';
            clearMsgSearchHighlights();
            msgSearchMatches = [];
            msgSearchActiveIdx = -1;
            updateMsgSearchCount();
        }

        function clearMsgSearchHighlights() {
            document.querySelectorAll('#chatMessages .chat-bubble-highlight').forEach(function (mark) {
                var parent = mark.parentNode;
                parent.replaceChild(document.createTextNode(mark.textContent), mark);
                parent.normalize();
            });
        }

        function updateMsgSearchCount() {
            var el = document.getElementById('chatMsgSearchCount');
            el.textContent = msgSearchMatches.length
                ? (msgSearchActiveIdx + 1) + ' از ' + msgSearchMatches.length
                : (document.getElementById('chatMsgSearchInput').value ? 'موردی نیست' : '');
            document.getElementById('chatMsgSearchPrevBtn').disabled = msgSearchMatches.length === 0;
            document.getElementById('chatMsgSearchNextBtn').disabled = msgSearchMatches.length === 0;
        }

        function runMsgSearch() {
            clearMsgSearchHighlights();
            msgSearchMatches = [];
            msgSearchActiveIdx = -1;

            var term = document.getElementById('chatMsgSearchInput').value.trim();
            if (!term) {
                updateMsgSearchCount();
                return;
            }
            var termLower = term.toLowerCase();

            document.querySelectorAll('#chatMessages .chat-bubble-row').forEach(function (row) {
                var textDiv = row.querySelector('.chat-bubble > div:not(.chat-bubble-quote)');
                if (!textDiv) return;
                var walker = document.createTreeWalker(textDiv, NodeFilter.SHOW_TEXT);
                var node;
                while ((node = walker.nextNode())) {
                    var idx = node.nodeValue.toLowerCase().indexOf(termLower);
                    if (idx === -1) continue;
                    var range = document.createRange();
                    range.setStart(node, idx);
                    range.setEnd(node, idx + term.length);
                    var mark = document.createElement('mark');
                    mark.className = 'chat-bubble-highlight';
                    range.surroundContents(mark);
                    msgSearchMatches.push({ row: row, mark: mark });
                    break; // یک هایلایتِ کافی به‌ازای هر پیام؛ برای سادگی و پرهیز از تداخلِ Range
                }
            });

            if (msgSearchMatches.length) {
                msgSearchActiveIdx = 0;
                focusMsgSearchMatch();
            }
            updateMsgSearchCount();
        }

        function navMsgSearch(direction) {
            if (!msgSearchMatches.length) return;
            msgSearchActiveIdx = (msgSearchActiveIdx + direction + msgSearchMatches.length) % msgSearchMatches.length;
            focusMsgSearchMatch();
            updateMsgSearchCount();
        }

        function focusMsgSearchMatch() {
            var match = msgSearchMatches[msgSearchActiveIdx];
            if (!match) return;
            match.row.scrollIntoView({ block: 'center', behavior: 'smooth' });
            match.row.classList.remove('flash');
            void match.row.offsetWidth;
            match.row.classList.add('flash');
        }

        function initials(name) {
            var parts = (name || '').trim().split(/\s+/);
            return ((parts[0] || '')[0] || '') + ((parts[1] || '')[0] || '');
        }

        // پالتِ رنگِ آواتار — هر مخاطب بر اساسِ نامش رنگِ ثابتِ خودش را می‌گیرد (مثلِ تلگرام)
        var AVATAR_COLORS = ['#e0574a', '#e0972e', '#3f9e4d', '#3a9fc9', '#7c66d9', '#d4569a', '#2e9e93', '#c07b30'];

        function avatarColor(name) {
            var s = name || '';
            var hash = 0;
            for (var i = 0; i < s.length; i++) hash = (hash * 31 + s.charCodeAt(i)) >>> 0;
            return AVATAR_COLORS[hash % AVATAR_COLORS.length];
        }

        // برایِ آواتارِ هدرِ گفتگو که به‌جایِ ساختِ کاملِ عنصر، فقط محتوایِ داخلی‌اش تغییر می‌کند
        function setAvatarContent(el, name, avatarUrl) {
            if (avatarUrl) {
                el.style.background = '';
                el.innerHTML = '<img class="chat-avatar-img" src="../' + avatarUrl + '" alt=""><span class="chat-avatar-online-dot"></span>';
            } else {
                el.style.background = avatarColor(name);
                el.innerHTML = esc(initials(name)) + '<span class="chat-avatar-online-dot"></span>';
            }
        }

        function avatarHtml(name, isOnline, extraClass, avatarUrl) {
            var inner = avatarUrl
                ? '<img class="chat-avatar-img" src="../' + avatarUrl + '" alt="">'
                : esc(initials(name));
            return '<div class="chat-avatar' + (isOnline ? ' online' : '') + (extraClass ? ' ' + extraClass : '') + '"' +
                (avatarUrl ? '' : ' style="background:' + avatarColor(name) + '"') + '>' +
                inner +
                '<span class="chat-avatar-online-dot"></span>' +
                '</div>';
        }

        // ─────────────── لیستِ گفتگوها ───────────────
        function loadConversations(cb) {
            fetch('../api/chat/conversations.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    },
                    cache: 'no-store'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        conversations = data.conversations;
                        renderConversationList();
                        var countEl = document.getElementById('chatSidebarCount');
                        if (countEl) countEl.textContent = conversations.length ? conversations.length : '';
                        if (activeConversationId) {
                            var activeConv = conversations.find(c => c.conversation_id === activeConversationId);
                            document.getElementById('chatHeadAvatar').classList.toggle('online', !!(activeConv && activeConv.other_user_is_online));
                            updateChatHeadLastSeen(activeConv);
                            updateMuteButton(activeConv);
                        }
                        if (typeof cb === 'function') cb();
                    } else {
                        document.getElementById('convList').innerHTML =
                            '<div class="chat-empty-list">' + esc(data.message || 'خطا در بارگذاری گفتگوها') + '</div>';
                    }
                })
                .catch(function() {
                    document.getElementById('convList').innerHTML =
                        '<div class="chat-empty-list">خطا در ارتباط با سرور — لطفاً صفحه را رفرش کنید</div>';
                });
        }

        function renderConversationList() {
            var term = (document.getElementById('convSearchInput').value || '').trim().toLowerCase();
            var el = document.getElementById('convList');
            var list = conversations.filter(c => !term || c.title.toLowerCase().includes(term));

            if (!list.length) {
                // ⚠️ اگر کاربر در حالِ جستجوست، این پیام گمراه‌کننده است — چون این بخش
                // فقط روی «عنوانِ گفتگو» فیلتر می‌کند، نه متنِ پیام‌ها؛ نتیجهٔ واقعیِ
                // جستجوی پیام (اگر باشد) پایین‌تر، در بخشِ «پیام‌های یافت‌شده» نمایش داده می‌شود
                el.innerHTML = term
                    ? ''
                    : '<div class="chat-empty-list">هنوز گفتگویی نیست</div>';
                return;
            }

            el.innerHTML = list.map(c => {
                // در گروه، چون چند فرستنده وجود دارد، پیش‌نمایشِ آخرین پیام با نامِ فرستنده مشخص می‌شود
                var sender = c.is_own_last
                    ? 'شما: '
                    : (c.type !== 'direct' && c.last_message_sender_name ? c.last_message_sender_name.split(' ')[0] + ': ' : '');
                var preview = c.last_message ? sender + esc(c.last_message) : (c.last_message === '' ? sender + '📎 پیوست' : 'هنوز پیامی نیست');
                var active = c.conversation_id === activeConversationId ? ' active' : '';
                var badge = c.unread_count > 0
                    ? '<span class="chat-unread-badge' + (c.is_muted ? ' muted' : '') + '">' + (c.unread_count > 99 ? '99+' : c.unread_count) + '</span>'
                    : '';
                var muteIcon = c.is_muted ? '<i class="bi bi-bell-slash-fill chat-conv-mute-icon"></i>' : '';
                var time = c.last_message_at ? new Date(c.last_message_at.replace(' ', 'T')).toLocaleTimeString('fa-IR', {
                    hour: '2-digit',
                    minute: '2-digit'
                }) : '';
                var previewHtml = c.other_user_is_typing
                    ? '<div class="chat-conv-preview typing">در حال نوشتن...</div>'
                    : '<div class="chat-conv-preview">' + preview + '</div>';
                return '<div class="chat-conv-item' + active + '" onclick="openConversation(' + c.conversation_id + ')">' +
                    avatarHtml(c.title, c.other_user_is_online, null, c.avatar_url) +
                    '<div class="chat-conv-info">' +
                    '<div class="chat-conv-name-row"><span class="chat-conv-name">' + esc(c.title) + muteIcon + '</span><span class="chat-conv-time">' + time + '</span></div>' +
                    previewHtml +
                    '</div>' + badge +
                    '</div>';
            }).join('');
        }

        // ─────────────── جستجوی سراسری در متنِ پیام‌ها ───────────────
        var globalSearchDebounce = null;

        function onConvSearchInput() {
            renderConversationList();

            var term = document.getElementById('convSearchInput').value.trim();
            clearTimeout(globalSearchDebounce);
            if (term.length < 2) {
                document.getElementById('msgSearchResultsSection').style.display = 'none';
                return;
            }
            globalSearchDebounce = setTimeout(function () {
                runGlobalMessageSearch(term);
            }, 300);
        }

        function runGlobalMessageSearch(term) {
            fetch('../api/chat/search-messages.php?q=' + encodeURIComponent(term), {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                })
                .then(r => r.json())
                .then(data => {
                    var section = document.getElementById('msgSearchResultsSection');
                    var list = document.getElementById('msgSearchResultsList');
                    if (!data.success || !data.results.length) {
                        section.style.display = 'block';
                        list.innerHTML = '<div class="chat-empty-list" style="padding:16px;">پیامی یافت نشد</div>';
                        return;
                    }
                    section.style.display = 'block';
                    var termEsc = esc(term);
                    var termRe = new RegExp('(' + termEsc.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                    list.innerHTML = data.results.map(r => {
                        var snippet = esc(r.snippet).replace(termRe, '<mark>$1</mark>');
                        var who = r.is_own ? 'شما' : r.sender_name;
                        return '<div class="chat-search-result-item" onclick="jumpToSearchResult(' + r.conversation_id + ',' + r.message_id + ')">' +
                            '<div class="chat-search-result-top">' +
                            '<span class="chat-search-result-conv">' + esc(r.conversation_title) + '</span>' +
                            '<span class="chat-search-result-time">' + esc(r.date_jalali) + '</span>' +
                            '</div>' +
                            '<div class="chat-search-result-snippet">' + esc(who) + ': ' + snippet + '</div>' +
                            '</div>';
                    }).join('');
                })
                .catch(function () {});
        }

        function jumpToSearchResult(conversationId, messageId) {
            document.getElementById('convSearchInput').value = '';
            document.getElementById('msgSearchResultsSection').style.display = 'none';
            renderConversationList();
            openConversation(conversationId, messageId);
        }

        // ─────────────── بازکردنِ یک گفتگو ───────────────
        function openConversation(id, jumpToMessageId) {
            activeConversationId = id;
            var conv = conversations.find(c => c.conversation_id === id);
            activeConversationTitle = conv ? conv.title : '—';
            activeConversationType = conv ? conv.type : 'direct';
            cancelEditMessage();
            cancelReplyMessage();
            closeMsgSearch();
            isOtherPartyTyping = false;

            document.getElementById('chatPlaceholder').style.display = 'none';
            document.getElementById('chatActiveView').style.display = 'flex';
            document.getElementById('chatHeadName').textContent = activeConversationTitle;
            document.getElementById('chatHeadInfoWrap').classList.toggle('clickable', !!(conv && conv.type !== 'direct'));
            var headAvatar = document.getElementById('chatHeadAvatar');
            headAvatar.classList.toggle('online', !!(conv && conv.other_user_is_online));
            setAvatarContent(headAvatar, activeConversationTitle, conv && conv.avatar_url);
            updateMuteButton(conv);
            updateChatHeadLastSeen(conv);
            document.getElementById('chatMessages').innerHTML = '';
            lastMessageId = 0;
            readReceipts = {};
            pinnedMessage = null;
            pinnedCanManage = false;
            renderPinnedBanner();
            loadActiveGroupMembers();

            document.getElementById('chatSidebar').classList.add('hide-mobile');
            document.getElementById('chatMain').classList.remove('hide-mobile');

            var url = '../api/chat/messages.php?conversation_id=' + id + '&limit=40';
            if (jumpToMessageId) url += '&around_id=' + jumpToMessageId;

            fetch(url, {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // ✅ در حالتِ پرش، اسکرولِ خودکار به پایین نمی‌خواهیم — به‌جایش
                        // بعد از رندر، دقیقاً به همون پیامِ موردنظر اسکرول و هایلایت می‌شود
                        appendMessages(data.messages, !jumpToMessageId);
                        loadConversations();
                        pollReadReceipts();
                        loadPinnedMessage();
                        if (jumpToMessageId) scrollToOriginalMessage(jumpToMessageId);
                    } else {
                        document.getElementById('chatMessages').innerHTML =
                            '<div class="chat-empty-list">' + esc(data.message || 'خطا در بارگذاری پیام‌ها') + '</div>';
                    }
                })
                .catch(function() {
                    document.getElementById('chatMessages').innerHTML =
                        '<div class="chat-empty-list">خطا در ارتباط با سرور — لطفاً صفحه را رفرش کنید</div>';
                });
        }

        function closeConversation() {
            document.getElementById('chatSidebar').classList.remove('hide-mobile');
            document.getElementById('chatMain').classList.add('hide-mobile');
        }

        // ─────────────── منشن (@نام) در گروه ───────────────
        function loadActiveGroupMembers() {
            activeGroupMembers = [];
            if (!activeConversationId || activeConversationType === 'direct') return;
            fetch('../api/chat/group-members.php?conversation_id=' + activeConversationId, {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) activeGroupMembers = data.members;
                })
                .catch(function() {});
        }

        function escapeRegex(s) {
            return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        // متنِ ورودی از قبل با esc() امن شده — الگو هم روی همان نسخه‌ی امن‌شده‌یِ
        // نام‌ها ساخته می‌شود تا HTMLِ تزریق‌شده مطابقتِ درستی داشته باشد
        function highlightMentions(escapedText, members) {
            if (!members || !members.length) return escapedText;
            var sorted = members.slice().sort((a, b) => b.full_name.length - a.full_name.length);
            var pattern = sorted.map(m => escapeRegex(esc(m.full_name))).join('|');
            if (!pattern) return escapedText;
            var re = new RegExp('@(' + pattern + ')', 'g');
            return escapedText.replace(re, function(match, name) {
                var member = sorted.find(m => esc(m.full_name) === name);
                var cls = member && member.id === myUserId ? 'chat-mention me' : 'chat-mention';
                return '<span class="' + cls + '">@' + name + '</span>';
            });
        }

        // ─────────────── اتوکامپلیتِ منشن حینِ تایپ ───────────────
        var mentionCandidates = [];
        var mentionActiveIndex = -1;
        var mentionRangeStart = -1; // موقعیتِ کاراکترِ «@» در متنِ کادر، برایِ جایگزینی

        function checkMentionTrigger() {
            var box = document.getElementById('mentionAutocomplete');
            if (activeConversationType === 'direct' || !activeGroupMembers.length) {
                box.style.display = 'none';
                return;
            }
            var input = document.getElementById('chatComposerInput');
            var pos = input.selectionStart;
            var textBefore = input.value.slice(0, pos);
            var atIndex = textBefore.lastIndexOf('@');
            if (atIndex === -1 || /\s/.test(textBefore.slice(atIndex + 1))) {
                box.style.display = 'none';
                return;
            }
            // «@» باید ابتدایِ متن باشد یا بعد از فاصله/خطِ‌جدید — تا داخلِ ایمیل و... مچ نشود
            if (atIndex > 0 && !/\s/.test(textBefore[atIndex - 1])) {
                box.style.display = 'none';
                return;
            }
            var partial = textBefore.slice(atIndex + 1);
            mentionCandidates = activeGroupMembers.filter(m => m.full_name.indexOf(partial) !== -1);
            if (!mentionCandidates.length) {
                box.style.display = 'none';
                return;
            }
            mentionRangeStart = atIndex;
            mentionActiveIndex = 0;
            renderMentionAutocomplete();
        }

        function renderMentionAutocomplete() {
            var box = document.getElementById('mentionAutocomplete');
            box.innerHTML = mentionCandidates.map((m, i) =>
                '<div class="chat-mention-item' + (i === mentionActiveIndex ? ' active' : '') + '" onclick="applyMention(' + i + ')">' + esc(m.full_name) + '</div>'
            ).join('');
            box.style.display = 'block';
        }

        function closeMentionAutocomplete() {
            document.getElementById('mentionAutocomplete').style.display = 'none';
            mentionCandidates = [];
            mentionRangeStart = -1;
        }

        function applyMention(index) {
            var member = mentionCandidates[index];
            if (!member || mentionRangeStart === -1) return;
            var input = document.getElementById('chatComposerInput');
            var pos = input.selectionStart;
            var before = input.value.slice(0, mentionRangeStart);
            var after = input.value.slice(pos);
            var insertText = '@' + member.full_name + ' ';
            input.value = before + insertText + after;
            var newPos = before.length + insertText.length;
            input.setSelectionRange(newPos, newPos);
            input.focus();
            closeMentionAutocomplete();
            autoGrowComposer(input);
        }

        // ─────────────── ری‌اکشنِ ایموجی ───────────────
        function reactionsHtml(messageId, reactions) {
            if (!reactions || !reactions.length) return '';
            return '<div class="chat-bubble-reactions" data-mid="' + messageId + '">' +
                reactions.map(r =>
                    '<span class="chat-reaction-pill' + (r.reacted_by_me ? ' mine' : '') + '" onclick="toggleReaction(' + messageId + ',\'' + r.emoji + '\')">' +
                    r.emoji + (r.count > 1 ? ' ' + r.count : '') +
                    '</span>'
                ).join('') +
                '</div>';
        }

        function reactFromCtxMenu(emoji) {
            if (!ctxMenuTargetRow) return;
            var messageId = ctxMenuTargetRow.getAttribute('data-message-id');
            closeChatCtxMenu();
            sendReaction(messageId, emoji);
        }

        function toggleReaction(messageId, emoji) {
            sendReaction(messageId, emoji);
        }

        function sendReaction(messageId, emoji) {
            fetch('../api/chat/react-message.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ message_id: messageId, emoji: emoji })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        updateReactionsForMessage(messageId, data.reactions);
                    } else {
                        showToast(data.message || 'خطا در ثبتِ ری‌اکشن', 'error');
                    }
                });
        }

        function updateReactionsForMessage(messageId, reactions) {
            var row = document.querySelector('.chat-bubble-row[data-message-id="' + messageId + '"]');
            if (!row) return;
            var bubble = row.querySelector('.chat-bubble');
            var existing = bubble.querySelector('.chat-bubble-reactions');
            if (existing) existing.remove();
            var html = reactionsHtml(messageId, reactions);
            if (html) bubble.insertAdjacentHTML('beforeend', html);
        }

        function pollReactions() {
            if (!activeConversationId) return;
            var convId = activeConversationId;
            fetch('../api/chat/reactions.php?conversation_id=' + convId, {
                    headers: { 'Authorization': 'Bearer ' + authToken },
                    cache: 'no-store'
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success || convId !== activeConversationId) return;
                    document.querySelectorAll('#chatMessages .chat-bubble-reactions').forEach(function(el) {
                        var mid = el.getAttribute('data-mid');
                        if (!data.reactions[mid]) el.remove();
                    });
                    Object.keys(data.reactions).forEach(function(mid) {
                        updateReactionsForMessage(mid, data.reactions[mid]);
                    });
                })
                .catch(function() {});
        }

        function appendMessages(msgs, scrollBottom) {
            var el = document.getElementById('chatMessages');
            msgs.forEach(m => {
                lastMessageId = Math.max(lastMessageId, m.id);
                var row = document.createElement('div');
                row.className = 'chat-bubble-row ' + (m.is_own ? 'own' : 'other');
                row.setAttribute('data-message-id', m.id);

                var imagesHtml = '',
                    filesHtml = '';
                (m.attachments || []).forEach(a => {
                    var url = '../api/chat/download.php?id=' + a.id + '&token=' + authToken + '&view=1';
                    if (a.is_image) {
                        imagesHtml += '<img src="' + url + '" alt="' + esc(a.original_name) + '" onclick="window.open(\'' + url + '\',\'_blank\')">';
                    } else {
                        var dlUrl = '../api/chat/download.php?id=' + a.id + '&token=' + authToken;
                        filesHtml += '<div class="chat-bubble-file" onclick="window.open(\'' + dlUrl + '\',\'_blank\')">' +
                            '<i class="bi bi-file-earmark"></i><span>' + esc(a.original_name) + '</span></div>';
                    }
                });

                var editedTag = m.is_edited ? '<span class="chat-bubble-edited-tag">(ویرایش‌شده)</span>' : '';

                var quoteHtml = '';
                if (m.reply_to) {
                    var quoteBody = m.reply_to.is_deleted
                        ? '<span class="chat-bubble-quote-deleted">پیام حذف شده</span>'
                        : esc(m.reply_to.snippet || '');
                    quoteHtml = '<div class="chat-bubble-quote" onclick="scrollToOriginalMessage(' + m.reply_to.id + ')">' +
                        '<span class="chat-bubble-quote-name">' + esc(m.reply_to.user_name) + '</span>' +
                        '<span class="chat-bubble-quote-text">' + quoteBody + '</span>' +
                        '</div>';
                }

                // در گروه، چون چند فرستنده وجود دارد، بالایِ پیامِ دیگران نامشان مشخص می‌شود
                var senderLabel = (!m.is_own && activeConversationType !== 'direct')
                    ? '<div class="chat-bubble-sender-name" style="color:' + avatarColor(m.user_name) + '">' + esc(m.user_name) + '</div>'
                    : '';

                var forwardLabel = m.forwarded_from
                    ? '<div class="chat-bubble-forward-label"><i class="bi bi-arrow-return-right"></i> فوروارد شده از ' + esc(m.forwarded_from) + '</div>'
                    : '';

                // رسیدِ خوانده‌شدن: فقط برایِ پیام‌هایِ خودم — با تیکِ ✓ (ارسال‌شده) شروع می‌شود
                // و با هر بار poll (پایینِ فایل، renderReadReceipts) به‌روز می‌شود
                var ticksHtml = m.is_own
                    ? '<span class="chat-bubble-ticks" data-mid="' + m.id + '"><i class="bi bi-check"></i></span>'
                    : '';

                row.innerHTML =
                    '<div class="chat-bubble">' +
                    senderLabel +
                    forwardLabel +
                    quoteHtml +
                    (m.message ? '<div>' + highlightMentions(esc(m.message), activeGroupMembers).replace(/\n/g, '<br>') + '</div>' : '') +
                    (imagesHtml ? '<div class="chat-bubble-images">' + imagesHtml + '</div>' : '') +
                    filesHtml +
                    '<div class="chat-bubble-time">' + esc(m.time_jalali) + editedTag + ticksHtml + '</div>' +
                    reactionsHtml(m.id, m.reactions) +
                    '</div>';

                // راست‌کلیک برای همه‌ی پیام‌ها فعال است (پاسخ برای هر پیامی ممکن است)؛
                // ویرایش/حذف فقط داخلِ منو برای پیام‌های خودم نمایش داده می‌شود
                row.setAttribute('data-message-text', m.message || '');
                row.setAttribute('data-can-edit', (m.is_own && m.message) ? '1' : '0');
                row.setAttribute('data-can-delete', m.is_own ? '1' : '0');
                row.setAttribute('data-sender-name', m.user_name);
                row.addEventListener('contextmenu', function (e) {
                    e.preventDefault();
                    openChatCtxMenu(e.clientX, e.clientY, row);
                });

                el.appendChild(row);
            });
            if (scrollBottom) el.scrollTop = el.scrollHeight;
        }

        function scrollToOriginalMessage(messageId) {
            var row = document.querySelector('.chat-bubble-row[data-message-id="' + messageId + '"]');
            if (!row) return;
            row.scrollIntoView({ block: 'center', behavior: 'smooth' });
            row.classList.remove('flash');
            void row.offsetWidth; // ری‌استارت انیمیشن اگه قبلاً هم فلش شده بود
            row.classList.add('flash');
        }

        // ─────────────── منویِ راست‌کلیک (ویرایش/حذف) ───────────────
        var ctxMenuTargetRow = null;

        function openChatCtxMenu(x, y, row) {
            ctxMenuTargetRow = row;
            var menu = document.getElementById('chatCtxMenu');
            var canEdit = row.getAttribute('data-can-edit') === '1';
            var canDelete = row.getAttribute('data-can-delete') === '1';
            document.getElementById('chatCtxEditItem').style.display = canEdit ? 'flex' : 'none';
            document.getElementById('chatCtxDeleteItem').style.display = canDelete ? 'flex' : 'none';

            var messageId = parseInt(row.getAttribute('data-message-id'), 10);
            var isPinned = pinnedMessage && pinnedMessage.id === messageId;
            document.getElementById('chatCtxPinItem').style.display = pinnedCanManage ? 'flex' : 'none';
            document.getElementById('chatCtxPinLabel').textContent = isPinned ? 'برداشتنِ سنجاق' : 'سنجاق‌کردن';

            menu.classList.add('show');
            // ابتدا نمایش داده می‌شود تا offsetWidth/Height درست خوانده شود، سپس موقعیتِ
            // نهایی طوری تنظیم می‌شود که از لبه‌ی صفحه بیرون نزند
            var menuW = menu.offsetWidth,
                menuH = menu.offsetHeight;
            var left = Math.min(x, window.innerWidth - menuW - 8);
            var top = Math.min(y, window.innerHeight - menuH - 8);
            menu.style.left = left + 'px';
            menu.style.top = top + 'px';
        }

        function closeChatCtxMenu() {
            document.getElementById('chatCtxMenu').classList.remove('show');
            ctxMenuTargetRow = null;
        }

        document.addEventListener('click', closeChatCtxMenu);
        document.addEventListener('scroll', closeChatCtxMenu, true);

        function startEditFromCtxMenu() {
            if (!ctxMenuTargetRow) return;
            var messageId = ctxMenuTargetRow.getAttribute('data-message-id');
            var text = ctxMenuTargetRow.getAttribute('data-message-text');
            closeChatCtxMenu();
            beginEditMessage(messageId, text);
        }

        function deleteFromCtxMenu() {
            if (!ctxMenuTargetRow) return;
            var messageId = ctxMenuTargetRow.getAttribute('data-message-id');
            closeChatCtxMenu();
            openDeleteMessageModal(messageId);
        }

        function replyFromCtxMenu() {
            if (!ctxMenuTargetRow) return;
            var messageId = ctxMenuTargetRow.getAttribute('data-message-id');
            var senderName = ctxMenuTargetRow.getAttribute('data-sender-name');
            var text = ctxMenuTargetRow.getAttribute('data-message-text');
            closeChatCtxMenu();
            beginReplyMessage(messageId, senderName, text);
        }

        // ─────────────── فورواردِ پیام ───────────────
        var forwardModalInstance = null;
        var forwardTargetMessageId = null;

        function forwardFromCtxMenu() {
            if (!ctxMenuTargetRow) return;
            forwardTargetMessageId = ctxMenuTargetRow.getAttribute('data-message-id');
            closeChatCtxMenu();

            document.getElementById('forwardSearchInput').value = '';
            renderForwardTargetList();

            if (!forwardModalInstance) {
                forwardModalInstance = new bootstrap.Modal(document.getElementById('forwardModal'));
            }
            forwardModalInstance.show();
        }

        function renderForwardTargetList() {
            var term = (document.getElementById('forwardSearchInput').value || '').trim().toLowerCase();
            var list = conversations.filter(c => !term || c.title.toLowerCase().includes(term));
            var el = document.getElementById('forwardTargetList');

            if (!list.length) {
                el.innerHTML = '<div class="text-muted text-center py-3" style="font-size:.85rem;">گفتگویی یافت نشد</div>';
                return;
            }

            el.innerHTML = list.map(c =>
                '<div class="chat-user-result" onclick="submitForwardTo(' + c.conversation_id + ')">' +
                avatarHtml(c.title, false, null, c.avatar_url) +
                '<div class="chat-user-result-info"><span class="chat-user-result-name">' + esc(c.title) + '</span></div>' +
                '</div>'
            ).join('');
        }

        function submitForwardTo(conversationId) {
            fetch('../api/chat/forward-message.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ message_id: forwardTargetMessageId, conversation_id: conversationId })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('forwardModal')).hide();
                        showToast('پیام فوروارد شد', 'success');
                        if (conversationId === activeConversationId) {
                            pollForUpdates();
                        }
                    } else {
                        showToast(data.message || 'خطا در فوروارد پیام', 'error');
                    }
                });
        }

        // ─────────────── پاسخ به پیام ───────────────
        var replyingToMessageId = null;

        function beginReplyMessage(messageId, senderName, text) {
            cancelEditMessage(); // پاسخ و ویرایش هم‌زمان معنا ندارند
            replyingToMessageId = messageId;
            document.getElementById('chatReplyBannerName').textContent = senderName;
            document.getElementById('chatReplyBannerText').textContent = text || '📎 پیوست';
            document.getElementById('chatReplyBanner').classList.add('show');
            document.getElementById('chatComposerInput').focus();
        }

        function cancelReplyMessage() {
            replyingToMessageId = null;
            document.getElementById('chatReplyBanner').classList.remove('show');
        }

        // ─────────────── ویرایشِ پیام ───────────────
        var editingMessageId = null;

        function beginEditMessage(messageId, currentText) {
            cancelReplyMessage(); // پاسخ و ویرایش هم‌زمان معنا ندارند
            editingMessageId = messageId;
            var input = document.getElementById('chatComposerInput');
            input.value = currentText;
            input.focus();
            input.dispatchEvent(new Event('input'));
            document.getElementById('chatEditBanner').classList.add('show');
            document.getElementById('chatAttachBtn').style.display = 'none';
        }

        function cancelEditMessage() {
            editingMessageId = null;
            var input = document.getElementById('chatComposerInput');
            input.value = '';
            input.style.height = 'auto';
            document.getElementById('chatEditBanner').classList.remove('show');
            document.getElementById('chatAttachBtn').style.display = '';
        }

        // ─────────────── حذفِ پیام ───────────────
        var pendingDeleteMessageId = null;
        var deleteMessageModalInstance = null;

        function openDeleteMessageModal(messageId) {
            pendingDeleteMessageId = messageId;
            document.getElementById('deleteMsgForEveryoneLabel').textContent =
                'آیا این پیام برای ' + esc(activeConversationTitle) + ' هم حذف شود؟';
            document.getElementById('deleteMsgForEveryoneCheck').checked = true;
            if (!deleteMessageModalInstance) {
                deleteMessageModalInstance = new bootstrap.Modal(document.getElementById('deleteMessageModal'));
            }
            deleteMessageModalInstance.show();
        }

        function confirmDeleteMessage() {
            if (!pendingDeleteMessageId) return;
            var forEveryone = document.getElementById('deleteMsgForEveryoneCheck').checked;
            fetch('../api/chat/delete-message.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ message_id: pendingDeleteMessageId, for_everyone: forEveryone })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        var row = document.querySelector('.chat-bubble-row[data-message-id="' + pendingDeleteMessageId + '"]');
                        if (row) row.remove();
                        deleteMessageModalInstance.hide();
                        loadConversations();
                    } else {
                        showToast(data.message || 'خطا در حذف پیام', 'error');
                    }
                    pendingDeleteMessageId = null;
                })
                .catch(function () {
                    showToast('خطا در ارتباط با سرور', 'error');
                    pendingDeleteMessageId = null;
                });
        }

        // ─────────────── ارسالِ پیام ───────────────
        function addPendingFiles(files) {
            for (var i = 0; i < files.length; i++) pendingFiles.push(files[i]);
            renderPendingFiles();
        }

        function removePendingFile(idx) {
            pendingFiles.splice(idx, 1);
            renderPendingFiles();
        }

        function renderPendingFiles() {
            var el = document.getElementById('pendingFiles');
            if (!pendingFiles.length) {
                el.style.display = 'none';
                el.innerHTML = '';
                return;
            }
            el.style.display = 'flex';
            el.innerHTML = pendingFiles.map((f, i) =>
                '<div class="chat-pending-chip"><i class="bi bi-paperclip"></i><span>' + esc(f.name) + '</span><i class="bi bi-x-circle remove" onclick="removePendingFile(' + i + ')"></i></div>'
            ).join('');
        }

        function sendChatMessage() {
            if (!activeConversationId) return;
            var input = document.getElementById('chatComposerInput');
            var text = input.value.trim();

            if (editingMessageId) {
                submitEditMessage(text);
                return;
            }

            if (!text && !pendingFiles.length) return;

            var btn = document.getElementById('chatSendBtn');
            btn.disabled = true;

            var fd = new FormData();
            fd.append('conversation_id', activeConversationId);
            fd.append('message', text);
            if (replyingToMessageId) fd.append('reply_to_message_id', replyingToMessageId);
            pendingFiles.forEach(f => fd.append('attachments[]', f));

            fetch('../api/chat/send.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    if (data.success) {
                        input.value = '';
                        input.style.height = 'auto';
                        pendingFiles = [];
                        renderPendingFiles();
                        cancelReplyMessage();
                        pollForUpdates();
                    } else {
                        showToast(data.message || 'خطا در ارسال پیام', 'error');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    showToast('خطا در ارتباط با سرور', 'error');
                });
        }

        function submitEditMessage(text) {
            if (!text) {
                showToast('متن پیام نمی‌تواند خالی باشد', 'error');
                return;
            }
            var messageId = editingMessageId;
            var btn = document.getElementById('chatSendBtn');
            btn.disabled = true;

            fetch('../api/chat/edit-message.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ message_id: messageId, message: text })
                })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    if (data.success) {
                        var row = document.querySelector('.chat-bubble-row[data-message-id="' + messageId + '"]');
                        if (row) {
                            row.setAttribute('data-message-text', data.message);
                            var textEl = row.querySelector('.chat-bubble > div:first-child');
                            if (textEl) textEl.innerHTML = esc(data.message).replace(/\n/g, '<br>');
                            var timeEl = row.querySelector('.chat-bubble-time');
                            if (timeEl && !timeEl.querySelector('.chat-bubble-edited-tag')) {
                                timeEl.insertAdjacentHTML('beforeend', '<span class="chat-bubble-edited-tag">(ویرایش‌شده)</span>');
                            }
                        }
                        cancelEditMessage();
                        loadConversations();
                    } else {
                        showToast(data.message || 'خطا در ویرایش پیام', 'error');
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    showToast('خطا در ارتباط با سرور', 'error');
                });
        }

        // ─────────────── Polling ───────────────
        function pollForUpdates() {
            loadConversations();
            if (activeConversationId) {
                fetch('../api/chat/messages.php?conversation_id=' + activeConversationId + '&after_id=' + lastMessageId, {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.messages.length) {
                            appendMessages(data.messages, true);
                            renderReadReceipts();
                        }
                    })
                    .catch(() => {});
                pollReadReceipts();
                pollReactions();
            }
        }

        // ─────────────── رسیدِ خوانده‌شدن (تیکِ ✓ / ✓✓) ───────────────
        function pollReadReceipts() {
            if (!activeConversationId) return;
            var convId = activeConversationId;
            fetch('../api/chat/read-receipts.php?conversation_id=' + convId, {
                    headers: { 'Authorization': 'Bearer ' + authToken },
                    cache: 'no-store'
                })
                .then(r => r.json())
                .then(data => {
                    // ⚠️ اگر تا زمانِ برگشتِ پاسخ، کاربر گفتگویِ دیگری باز کرده، این نتیجه را نادیده می‌گیریم
                    if (!data.success || convId !== activeConversationId) return;
                    readReceipts = {};
                    data.participants.forEach(function(p) {
                        readReceipts[p.user_id] = p.last_read_message_id || 0;
                    });
                    renderReadReceipts();
                })
                .catch(function() {});
        }

        // ─────────────── سنجاق‌کردنِ پیام ───────────────
        function loadPinnedMessage() {
            if (!activeConversationId) return;
            var convId = activeConversationId;
            fetch('../api/chat/pinned-message.php?conversation_id=' + convId, {
                    headers: { 'Authorization': 'Bearer ' + authToken },
                    cache: 'no-store'
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success || convId !== activeConversationId) return;
                    pinnedMessage = data.pinned;
                    pinnedCanManage = !!data.can_manage;
                    renderPinnedBanner();
                })
                .catch(function() {});
        }

        function renderPinnedBanner() {
            var banner = document.getElementById('chatPinnedBanner');
            if (!pinnedMessage) {
                banner.style.display = 'none';
                return;
            }
            banner.style.display = 'flex';
            var who = pinnedMessage.is_own ? 'شما' : pinnedMessage.user_name;
            document.getElementById('chatPinnedBannerText').textContent = who + ': ' + (pinnedMessage.snippet || '📎 پیوست');
            document.getElementById('chatPinnedBannerClose').style.display = pinnedCanManage ? 'block' : 'none';
        }

        function pinFromCtxMenu() {
            if (!ctxMenuTargetRow || !activeConversationId) return;
            var messageId = parseInt(ctxMenuTargetRow.getAttribute('data-message-id'), 10);
            closeChatCtxMenu();

            var isCurrentlyPinned = pinnedMessage && pinnedMessage.id === messageId;
            var url = isCurrentlyPinned ? '../api/chat/unpin-message.php' : '../api/chat/pin-message.php';
            var body = isCurrentlyPinned
                ? { conversation_id: activeConversationId }
                : { conversation_id: activeConversationId, message_id: messageId };

            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(body)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        loadPinnedMessage();
                    } else {
                        showToast(data.message || 'خطا در سنجاق‌کردنِ پیام', 'error');
                    }
                });
        }

        function unpinCurrentMessage() {
            if (!activeConversationId) return;
            fetch('../api/chat/unpin-message.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ conversation_id: activeConversationId })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        pinnedMessage = null;
                        renderPinnedBanner();
                    } else {
                        showToast(data.message || 'خطا در برداشتنِ سنجاق', 'error');
                    }
                });
        }

        function renderReadReceipts() {
            var otherIds = Object.keys(readReceipts);
            if (!otherIds.length) return;
            document.querySelectorAll('#chatMessages .chat-bubble-row.own .chat-bubble-ticks').forEach(function(el) {
                var mid = parseInt(el.getAttribute('data-mid'), 10);
                var seenByCount = otherIds.filter(function(uid) {
                    return readReceipts[uid] >= mid;
                }).length;
                if (activeConversationType === 'direct') {
                    el.innerHTML = seenByCount > 0
                        ? '<i class="bi bi-check-all seen"></i>'
                        : '<i class="bi bi-check"></i>';
                } else {
                    el.innerHTML = seenByCount > 0
                        ? '<i class="bi bi-check-all seen"></i><span class="chat-bubble-seen-count">' + seenByCount + '</span>'
                        : '<i class="bi bi-check"></i>';
                }
            });
        }

        // ─────────────── گفتگوی جدید ───────────────
        var newChatModalInstance = null;
        var kbActiveIndex = -1;
        var currentUserResults = [];

        // حالتِ مودالِ «گفتگوی جدید»: مستقیم / ساختِ گروه / افزودنِ عضو به گروهِ موجود
        var newChatMode = 'direct';
        var selectedGroupMembers = {}; // id -> full_name — هم برایِ ساختِ گروه، هم افزودنِ عضو
        var addMembersTargetConvId = null;
        var addMembersExistingIds = [];

        function openNewChatModal() {
            newChatMode = 'direct';
            selectedGroupMembers = {};
            addMembersTargetConvId = null;
            addMembersExistingIds = [];

            document.getElementById('newChatModeTabs').style.display = 'flex';
            document.getElementById('newChatTabDirect').classList.add('active');
            document.getElementById('newChatTabGroup').classList.remove('active');
            document.getElementById('newChatModalTitle').textContent = 'شروعِ گفتگوی جدید';
            document.getElementById('newGroupTitleInput').style.display = 'none';
            document.getElementById('newGroupTitleInput').value = '';
            document.getElementById('newGroupFooter').style.display = 'none';
            document.getElementById('createGroupBtn').textContent = 'ایجادِ گروه';
            document.getElementById('newChatSearchInput').value = '';
            document.getElementById('newChatUserResults').innerHTML = '';
            renderGroupChips();
            kbActiveIndex = -1;
            if (!newChatModalInstance) {
                newChatModalInstance = new bootstrap.Modal(document.getElementById('newChatModal'));
                document.getElementById('newChatModal').addEventListener('shown.bs.modal', function() {
                    document.getElementById('newChatSearchInput').focus();
                });
            }
            newChatModalInstance.show();
            searchChatUsers();
        }

        function switchNewChatMode(mode) {
            newChatMode = mode;
            selectedGroupMembers = {};
            document.getElementById('newChatTabDirect').classList.toggle('active', mode === 'direct');
            document.getElementById('newChatTabGroup').classList.toggle('active', mode === 'group');
            document.getElementById('newChatModalTitle').textContent = mode === 'group' ? 'ساختِ گروهِ جدید' : 'شروعِ گفتگوی جدید';
            document.getElementById('newGroupTitleInput').style.display = mode === 'group' ? 'block' : 'none';
            document.getElementById('newGroupFooter').style.display = mode === 'group' ? 'flex' : 'none';
            renderGroupChips();
            updateCreateGroupBtnState();
            searchChatUsers();
        }

        // از مودالِ «اطلاعاتِ گروه» صدا زده می‌شود — همان مودالِ گفتگویِ جدید را
        // در حالتِ «افزودنِ عضو به گروهِ موجود» دوباره‌استفاده می‌کند
        function openAddMembersMode() {
            var groupModalInst = bootstrap.Modal.getInstance(document.getElementById('groupInfoModal'));
            if (groupModalInst) groupModalInst.hide();

            addMembersTargetConvId = activeConversationId;
            newChatMode = 'add-members';
            selectedGroupMembers = {};

            document.getElementById('newChatModeTabs').style.display = 'none';
            document.getElementById('newChatModalTitle').textContent = 'افزودنِ عضو به گروه';
            document.getElementById('newGroupTitleInput').style.display = 'none';
            document.getElementById('newGroupFooter').style.display = 'flex';
            document.getElementById('createGroupBtn').textContent = 'افزودنِ اعضا';
            document.getElementById('newChatSearchInput').value = '';
            document.getElementById('newChatUserResults').innerHTML = '';
            renderGroupChips();
            updateCreateGroupBtnState();
            kbActiveIndex = -1;

            if (!newChatModalInstance) {
                newChatModalInstance = new bootstrap.Modal(document.getElementById('newChatModal'));
                document.getElementById('newChatModal').addEventListener('shown.bs.modal', function() {
                    document.getElementById('newChatSearchInput').focus();
                });
            }
            newChatModalInstance.show();

            fetch('../api/chat/group-members.php?conversation_id=' + addMembersTargetConvId, {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                })
                .then(r => r.json())
                .then(data => {
                    addMembersExistingIds = data.success ? data.members.map(m => m.id) : [];
                    searchChatUsers();
                });
        }

        function toggleGroupMember(id) {
            var user = currentUserResults.find(u => u.id === id);
            if (!user) return;
            if (selectedGroupMembers[id]) {
                delete selectedGroupMembers[id];
            } else {
                selectedGroupMembers[id] = user.full_name;
            }
            var row = document.querySelector('#newChatUserResults .chat-user-result[data-uid="' + id + '"]');
            if (row) {
                row.classList.toggle('selected', !!selectedGroupMembers[id]);
                var cb = row.querySelector('.chat-user-result-check');
                if (cb) cb.checked = !!selectedGroupMembers[id];
            }
            renderGroupChips();
            updateCreateGroupBtnState();
        }

        function renderGroupChips() {
            var wrap = document.getElementById('groupSelectedChips');
            var ids = Object.keys(selectedGroupMembers);
            if (newChatMode === 'direct' || !ids.length) {
                wrap.style.display = 'none';
                wrap.innerHTML = '';
                return;
            }
            wrap.style.display = 'flex';
            wrap.innerHTML = ids.map(id =>
                '<span class="chat-group-chip">' + esc(selectedGroupMembers[id]) +
                '<i class="bi bi-x" onclick="toggleGroupMember(' + id + ')"></i></span>'
            ).join('');
        }

        function updateCreateGroupBtnState() {
            var btn = document.getElementById('createGroupBtn');
            var hasMembers = Object.keys(selectedGroupMembers).length > 0;
            if (newChatMode === 'group') {
                var titleOk = document.getElementById('newGroupTitleInput').value.trim() !== '';
                btn.disabled = !(titleOk && hasMembers);
            } else if (newChatMode === 'add-members') {
                btn.disabled = !hasMembers;
            }
        }

        function submitNewChatModalAction() {
            if (newChatMode === 'group') {
                submitCreateGroup();
            } else if (newChatMode === 'add-members') {
                submitAddMembers();
            }
        }

        function submitCreateGroup() {
            var title = document.getElementById('newGroupTitleInput').value.trim();
            var memberIds = Object.keys(selectedGroupMembers).map(Number);
            if (!title || !memberIds.length) return;
            fetch('../api/chat/create-group.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ title: title, member_ids: memberIds })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('newChatModal')).hide();
                        loadConversations(function() {
                            openConversation(data.conversation_id);
                        });
                    } else {
                        showToast(data.message || 'خطا در ساختِ گروه', 'error');
                    }
                });
        }

        function submitAddMembers() {
            var memberIds = Object.keys(selectedGroupMembers).map(Number);
            if (!memberIds.length || !addMembersTargetConvId) return;
            fetch('../api/chat/group-add-members.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ conversation_id: addMembersTargetConvId, member_ids: memberIds })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('newChatModal')).hide();
                        loadConversations();
                        showToast('اعضایِ جدید اضافه شدند', 'success');
                    } else {
                        showToast(data.message || 'خطا در افزودنِ عضو', 'error');
                    }
                });
        }

        // ─────────────── مودالِ اطلاعاتِ گروه ───────────────
        var groupInfoModalInstance = null;
        var groupInfoIsOwner = false;

        function openGroupInfoModal() {
            if (!activeConversationId) return;
            document.getElementById('groupInfoMemberList').innerHTML = '<div class="chat-empty-list">در حال بارگذاری...</div>';
            document.getElementById('groupInfoAddBtn').style.display = 'none';

            if (!groupInfoModalInstance) {
                groupInfoModalInstance = new bootstrap.Modal(document.getElementById('groupInfoModal'));
            }
            groupInfoModalInstance.show();

            fetch('../api/chat/group-members.php?conversation_id=' + activeConversationId, {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        document.getElementById('groupInfoMemberList').innerHTML =
                            '<div class="chat-empty-list">' + esc(data.message || 'خطا در بارگذاریِ اعضا') + '</div>';
                        return;
                    }
                    document.getElementById('groupInfoTitle').textContent = activeConversationTitle + ' — ' + data.members.length + ' عضو';
                    document.getElementById('groupInfoAddBtn').style.display = data.is_owner ? 'block' : 'none';
                    groupInfoIsOwner = !!data.is_owner;
                    document.getElementById('groupInfoAvatarWrap').innerHTML =
                        avatarHtml(data.group_title || activeConversationTitle, false, 'chat-group-avatar-big', data.group_avatar_url) +
                        (data.is_owner ? '<div class="chat-group-avatar-edit-badge"><i class="bi bi-camera-fill"></i></div>' : '');
                    document.getElementById('groupInfoMemberList').innerHTML = data.members.map(m =>
                        '<div class="chat-group-member-row">' +
                        avatarHtml(m.full_name, false, null, m.avatar_url) +
                        '<span class="chat-group-member-name">' + esc(m.full_name) +
                        (m.is_owner ? '<span class="chat-group-owner-tag">مدیرِ گروه</span>' : '') +
                        '</span>' +
                        (data.is_owner && !m.is_owner
                            ? '<button class="chat-group-member-remove" title="حذفِ عضو" onclick="removeGroupMember(' + m.id + ')"><i class="bi bi-x-lg"></i></button>'
                            : '') +
                        '</div>'
                    ).join('');
                })
                .catch(function() {
                    document.getElementById('groupInfoMemberList').innerHTML =
                        '<div class="chat-empty-list">خطا در ارتباط با سرور</div>';
                });
        }

        function triggerGroupAvatarUpload() {
            if (!groupInfoIsOwner) return;
            document.getElementById('groupAvatarFileInput').click();
        }

        function uploadGroupAvatar(file) {
            if (!file || !activeConversationId) return;
            var fd = new FormData();
            fd.append('avatar', file);
            fd.append('conversation_id', activeConversationId);
            fetch('../api/chat/upload-group-avatar.php', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + authToken },
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    document.getElementById('groupAvatarFileInput').value = '';
                    if (data.success) {
                        showToast('عکسِ گروه بروزرسانی شد', 'success');
                        openGroupInfoModal();
                        loadConversations();
                    } else {
                        showToast(data.message || 'خطا در آپلودِ عکس', 'error');
                    }
                });
        }

        function removeGroupMember(userId) {
            if (!activeConversationId) return;
            fetch('../api/chat/group-remove-member.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ conversation_id: activeConversationId, user_id: userId })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        openGroupInfoModal();
                        loadConversations();
                    } else {
                        showToast(data.message || 'خطا در حذفِ عضو', 'error');
                    }
                });
        }

        function confirmLeaveGroup() {
            if (!activeConversationId) return;
            if (!confirm('آیا مطمئنید می‌خواهید از این گروه خارج شوید؟')) return;
            fetch('../api/chat/leave-group.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ conversation_id: activeConversationId })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        var modalInst = bootstrap.Modal.getInstance(document.getElementById('groupInfoModal'));
                        if (modalInst) modalInst.hide();
                        closeConversation();
                        document.getElementById('chatPlaceholder').style.display = 'flex';
                        document.getElementById('chatActiveView').style.display = 'none';
                        activeConversationId = null;
                        loadConversations();
                        showToast('از گروه خارج شدید', 'success');
                    } else {
                        showToast(data.message || 'خطا در خروج از گروه', 'error');
                    }
                });
        }

        // «آخرین بازدید از صفحه‌ی چت» به‌صورتِ نسبی (مثلِ تلگرام)
        function formatLastSeen(dateStr) {
            if (!dateStr) return 'هیچ‌وقت';
            var then = new Date(dateStr.replace(' ', 'T'));
            var diffMin = Math.round((Date.now() - then.getTime()) / 60000);
            if (diffMin < 1) return 'همین الان';
            if (diffMin < 60) return diffMin + ' دقیقه پیش';
            var diffHour = Math.round(diffMin / 60);
            if (diffHour < 24) return diffHour + ' ساعت پیش';
            var diffDay = Math.round(diffHour / 24);
            if (diffDay === 1) return 'دیروز';
            if (diffDay < 30) return diffDay + ' روز پیش';
            return then.toLocaleDateString('fa-IR');
        }

        function updateChatHeadLastSeen(conv) {
            var el = document.getElementById('chatHeadLastSeen');
            if (isOtherPartyTyping) {
                el.textContent = 'در حال نوشتن...';
                el.classList.add('typing');
                return;
            }
            el.classList.remove('typing');
            if (!conv) {
                el.textContent = '';
                return;
            }
            if (conv.type !== 'direct') {
                el.textContent = (conv.member_count || 0) + ' عضو';
                return;
            }
            el.textContent = conv.other_user_is_online
                ? 'آنلاین'
                : 'آخرین بازدید: ' + formatLastSeen(conv.other_user_last_seen_at);
        }

        function openGroupInfoIfApplicable() {
            var conv = conversations.find(c => c.conversation_id === activeConversationId);
            if (conv && conv.type !== 'direct') openGroupInfoModal();
        }

        // ─────────────── بی‌صداکردنِ گفتگو ───────────────
        function updateMuteButton(conv) {
            var btn = document.getElementById('chatMuteToggleBtn');
            var muted = !!(conv && conv.is_muted);
            btn.querySelector('i').className = muted ? 'bi bi-bell-slash-fill' : 'bi bi-bell';
            btn.title = muted ? 'باصداکردنِ این گفتگو' : 'بی‌صداکردنِ این گفتگو';
        }

        function toggleMuteActiveConversation() {
            if (!activeConversationId) return;
            fetch('../api/chat/toggle-mute.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ conversation_id: activeConversationId })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        var conv = conversations.find(c => c.conversation_id === activeConversationId);
                        if (conv) conv.is_muted = data.is_muted;
                        updateMuteButton(conv);
                        renderConversationList();
                    } else {
                        showToast(data.message || 'خطا در تغییرِ وضعیتِ صدا', 'error');
                    }
                });
        }

        // ─────────────── نشانگرِ «در حالِ تایپ» ───────────────
        var lastTypingPingAt = 0;
        var isOtherPartyTyping = false;

        function notifyTyping() {
            if (!activeConversationId) return;
            var now = Date.now();
            // throttle: حداکثر هر ۳ ثانیه یک‌بار درخواست فرستاده شود (نه به‌ازای هر کلید)
            if (now - lastTypingPingAt < 3000) return;
            lastTypingPingAt = now;
            fetch('../api/chat/typing.php', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + authToken,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ conversation_id: activeConversationId })
            }).catch(function () {});
        }

        function pollTypingStatus() {
            if (!activeConversationId) return;
            fetch('../api/chat/typing-status.php?conversation_id=' + activeConversationId, {
                    headers: { 'Authorization': 'Bearer ' + authToken },
                    cache: 'no-store'
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    isOtherPartyTyping = data.is_typing;
                    var conv = conversations.find(c => c.conversation_id === activeConversationId);
                    updateChatHeadLastSeen(conv);
                })
                .catch(function () {});
        }

        var searchDebounce = null;

        function searchChatUsers() {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(function() {
                var q = document.getElementById('newChatSearchInput').value.trim();
                fetch('../api/chat/search-users.php?q=' + encodeURIComponent(q), {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        var el = document.getElementById('newChatUserResults');
                        kbActiveIndex = -1;
                        if (!data.success || !data.users.length) {
                            currentUserResults = [];
                            el.innerHTML = '<div class="text-muted text-center py-3" style="font-size:.85rem;">کاربری یافت نشد</div>';
                            return;
                        }
                        // در حالتِ افزودنِ عضو، کسانی که از قبل عضوِ گروه‌اند از لیست حذف می‌شوند
                        var users = newChatMode === 'add-members'
                            ? data.users.filter(u => addMembersExistingIds.indexOf(u.id) === -1)
                            : data.users;
                        currentUserResults = users;
                        if (!users.length) {
                            el.innerHTML = '<div class="text-muted text-center py-3" style="font-size:.85rem;">همه‌ی نتایج از قبل عضوِ گروه‌اند</div>';
                            return;
                        }
                        var multi = newChatMode !== 'direct';
                        el.innerHTML = users.map((u, i) => {
                            var selected = multi && !!selectedGroupMembers[u.id];
                            return '<div class="chat-user-result' + (selected ? ' selected' : '') + '" data-idx="' + i + '" data-uid="' + u.id + '" onclick="' + (multi ? 'toggleGroupMember(' + u.id + ')' : 'startChatWith(' + u.id + ')') + '">' +
                                (multi ? '<input type="checkbox" class="chat-user-result-check" ' + (selected ? 'checked' : '') + ' onclick="event.stopPropagation(); toggleGroupMember(' + u.id + ')">' : '') +
                                avatarHtml(u.full_name, u.is_online, 'chat-avatar-sm', u.avatar_url) +
                                '<div class="chat-user-result-info">' +
                                '<span class="chat-user-result-name">' + esc(u.full_name) + '</span>' +
                                (u.section_label ? '<span class="chat-user-result-section">' + esc(u.section_label) + '</span>' : '') +
                                '</div>' +
                                '<span class="chat-user-result-lastseen' + (u.is_online ? ' online' : '') + '">' +
                                (u.is_online ? 'آنلاین' : esc(formatLastSeen(u.last_seen_at))) +
                                '</span>' +
                                '</div>';
                        }).join('');
                    });
            }, 250);
        }

        // ─────────────── جابه‌جایی با کیبورد در نتایج ───────────────
        function renderKbActive() {
            document.querySelectorAll('#newChatUserResults .chat-user-result').forEach(function(el, i) {
                el.classList.toggle('kb-active', i === kbActiveIndex);
                if (i === kbActiveIndex) el.scrollIntoView({
                    block: 'nearest'
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('newChatSearchInput').addEventListener('input', searchChatUsers);
            document.getElementById('newChatSearchInput').addEventListener('keydown', function(e) {
                if (!currentUserResults.length) return;
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    kbActiveIndex = Math.min(kbActiveIndex + 1, currentUserResults.length - 1);
                    renderKbActive();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    kbActiveIndex = Math.max(kbActiveIndex - 1, 0);
                    renderKbActive();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    var target = kbActiveIndex >= 0 ? currentUserResults[kbActiveIndex] : currentUserResults[0];
                    if (!target) return;
                    if (newChatMode === 'direct') startChatWith(target.id);
                    else toggleGroupMember(target.id);
                }
            });
        });

        function startChatWith(userId) {
            fetch('../api/chat/start.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: userId
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('newChatModal')).hide();
                        loadConversations(function() {
                            openConversation(data.conversation_id);
                        });
                    } else {
                        showToast(data.message || 'خطا در شروع گفتگو', 'error');
                    }
                });
        }
    </script>
    <?php include 'footer.php'; ?>
</body>

</html>