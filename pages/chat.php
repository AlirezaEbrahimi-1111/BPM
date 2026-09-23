<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گفتگوها - سیستم مدیریت کار</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/jalali.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/jquery.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../assets/css/custom.css') ?>">
    <!-- تقویمِ شمسی — برایِ فیلدِ موعدِ مودالِ «تعریفِ کار از رویِ پیام»؛ دقیقاً
         همون ست‌ِ فایل‌هایی که task-detail.php/create-task.php استفاده می‌کنن
         (پیاده‌سازیِ سفارشیِ خودِ پروژه، نه یک کتابخانه‌یِ دیگه) -->
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/persian-datepicker.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <script src="<?= asset('../assets/js/cdn/persian-date.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/persian-datepicker.js') ?>"></script>

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
            /* این صفحه ثابت (overflow:hidden) و بدونِ اسکرولِ خودِ body است،
               پس ارتفاعِ فوترِ چسبان (site-footer) هم باید صریحاً از بودجهٔ
               ارتفاع کم بشه، وگرنه فوتر رویِ کادرِ تایپِ چت می‌افته */
            height: calc(100vh - 70px - 32px - 34px);
            /* 100vh در مرورگرهایِ موبایل نوارِ آدرس/دکمه‌هایِ گوشی رو حساب
               نمی‌کنه (بزرگ‌تر از فضایِ واقعاً دیده‌شده‌ست) — چون این صفحه
               اسکرول نداره، هرچی بیرون از فضایِ واقعی بیفته (فوتر، دکمه‌ی
               شناور) اصلاً دیده نمی‌شه. 100dvh فضایِ واقعیِ دیده‌شده رو
               می‌ده؛ خط بالا صرفاً fallbackِ مرورگرهایِ خیلی قدیمیه */
            height: calc(100dvh - 70px - 32px - 34px);
            margin: 16px auto;
            padding: 0 16px;
            display: flex;
        }

        .site-footer {
            margin-top: 0 !important;
        }

        .chat-shell {
            position: relative;
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
            border-radius: var(--radius-btn);
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

        .chat-conv-preview.draft {
            color: #c0392b;
        }

        .chat-conv-draft-label {
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

        .chat-unread-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 14px 0;
            text-align: center;
        }

        .chat-unread-divider span {
            background: rgba(142, 87, 254, .12);
            color: var(--primary, #8e57fe);
            font-size: .72rem;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 999px;
            white-space: nowrap;
        }

        /* جداکننده‌ی تاریخ — خنثی و کم‌رنگ‌تر از جداکننده‌ی «خوانده‌نشده»
           تا با اون اشتباه گرفته نشه */
        .chat-date-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 12px 0;
            text-align: center;
        }

        .chat-date-divider span {
            background: var(--surface-2, #f1f2f4);
            color: var(--text-muted, #6b7280);
            font-size: .7rem;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 999px;
            white-space: nowrap;
        }

        :root[data-theme="dark"] .chat-date-divider span {
            background: var(--surface-3, rgba(255, 255, 255, .08));
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
            border-radius: var(--radius-btn);
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

        .chat-search-toggle-btn.chat-notif-on {
            color: var(--icon-accent, #8e57fe);
        }

        .chat-search-toggle-btn.chat-notif-blocked {
            color: #c0392b;
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
            border-radius: var(--radius-btn);
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
            border-radius: var(--radius-btn);
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

        .chat-scroll-bottom-badge {
            position: absolute;
            top: -6px;
            left: -6px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 999px;
            background: var(--primary, #8e57fe);
            color: #fff;
            font-size: .65rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
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

        /* هاله‌ی سرتاسری (به‌اندازه‌ی کلِ عرضِ فضایِ چت) وقتی از روی بنرِ
           پیامِ سنجاق‌شده به این پیام می‌پریم — چون خودِ ردیف (نه حبابِ داخلش)
           تمامِ عرض رو می‌گیره، پس‌زمینه‌دادن به همین ردیف خودبه‌خود یک نوارِ
           سرتاسریِ چپ‌به‌راست می‌سازه، نه فقط دورِ حباب */
        .chat-bubble-row.pinned-jump-highlight {
            border-radius: 8px;
            animation: chat-pinned-jump-fade 3s ease-out;
        }

        @keyframes chat-pinned-jump-fade {
            0%   { background: rgba(142, 87, 254, .22); }
            70%  { background: rgba(142, 87, 254, .14); }
            100% { background: transparent; }
        }

        :root[data-theme="dark"] .chat-bubble-row.pinned-jump-highlight {
            animation-name: chat-pinned-jump-fade-dark;
        }

        @keyframes chat-pinned-jump-fade-dark {
            0%   { background: rgba(142, 87, 254, .3); }
            70%  { background: rgba(142, 87, 254, .18); }
            100% { background: transparent; }
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
            border-radius: 9px;
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
            border-radius: 9px;
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

        .chat-ctx-menu-divider {
            height: 1px;
            background: var(--border-soft, #eee);
            margin: 4px 2px;
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

        .chat-bubble-pin-icon {
            margin-inline-end: 3px;
            font-size: .72rem;
            /* رنگِ ثابتِ بنفش رویِ حبابِ خودم (که پس‌زمینه‌اش خودش بنفشه) اصلاً
               دیده نمی‌شد؛ inherit همیشه هم‌رنگِ متنِ همون حباب می‌شه — سفید
               رویِ حبابِ خودم، تیره رویِ حبابِ طرفِ مقابل — یعنی همیشه قابلِ‌دیدنه */
            color: inherit;
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

        /* ─── گالریِ فایل/عکسِ مشترکِ گفتگو ─── */
        .chat-media-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }

        /* پیامِ متنیِ «هنوز عکسی نیست» وقتی داخلِ گریدِ ۴ستونی می‌شینه، نباید
           فقط تویِ یک ستونِ باریک (۱/۴ عرض) فشرده بشه — باید کلِ عرض رو بگیره */
        .chat-media-grid .chat-empty-list {
            grid-column: 1 / -1;
        }

        .chat-media-grid-item {
            aspect-ratio: 1 / 1;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 1px solid var(--border-soft, #e5e0ee);
        }

        .chat-media-grid-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .chat-media-file-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .chat-media-file-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid var(--border-soft, #e5e0ee);
            cursor: pointer;
            color: var(--text-strong);
        }

        .chat-media-file-row:hover {
            background: var(--ink-050);
        }

        .chat-media-file-row i {
            font-size: 1.3rem;
            color: var(--text-muted);
        }

        .chat-media-file-name {
            font-size: .85rem;
            font-weight: 600;
        }

        .chat-media-file-meta {
            font-size: .74rem;
            color: var(--text-muted);
        }

        /* ─── دراورِ پروفایلِ طرفِ مقابل ─── */
        .chat-profile-drawer-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .35);
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .2s ease, visibility 0s linear .2s;
        }

        .chat-profile-drawer-overlay.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transition: opacity .2s ease, visibility 0s linear 0s;
        }

        .chat-profile-drawer {
            position: absolute;
            top: 0;
            inset-inline-end: 0;
            bottom: 0;
            width: 340px;
            max-width: 90vw;
            background: var(--surface);
            box-shadow: -4px 0 24px rgba(0, 0, 0, .15);
            z-index: 1041;
            display: flex;
            flex-direction: column;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transform: translateX(100%);
            transition: transform .25s ease, opacity .25s ease, visibility 0s linear .25s;
        }

        .chat-profile-drawer.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transform: translateX(0);
            transition: transform .25s ease, opacity .25s ease, visibility 0s linear 0s;
        }

        .chat-profile-drawer-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            border-bottom: 1px solid var(--border-soft, #e5e0ee);
            font-weight: 700;
            color: var(--ink-900);
        }

        .chat-profile-drawer-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 18px;
        }

        .chat-profile-drawer-avatar-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 12px;
        }

        .chat-profile-drawer-avatar {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            overflow: hidden;
        }

        .chat-profile-drawer-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .chat-profile-drawer-name {
            text-align: center;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--ink-900);
            margin-bottom: 18px;
        }

        .chat-profile-drawer-field {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 4px;
            border-bottom: 1px solid var(--border-soft, #eee);
            direction: ltr;
        }

        .chat-profile-drawer-field i {
            font-size: 1.1rem;
            color: var(--text-muted);
            width: 20px;
            text-align: center;
        }

        .chat-profile-drawer-value {
            font-size: .88rem;
            color: var(--ink-900);
            font-weight: 600;
        }

        .chat-profile-drawer-label {
            font-size: .72rem;
            color: var(--text-muted);
        }

        .chat-profile-drawer-media-heading {
            font-size: .78rem;
            font-weight: 700;
            color: var(--text-muted);
            margin: 18px 0 10px;
        }

        /* سوییچِ اعلان‌ها */
        .chat-toggle-switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 22px;
            flex-shrink: 0;
        }

        .chat-toggle-switch input {
            position: absolute;
            inset: 0;
            opacity: 0;
            margin: 0;
            cursor: pointer;
            z-index: 2;
        }

        .chat-toggle-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: var(--border-soft, #ccc);
            border-radius: 22px;
            transition: .2s;
        }

        .chat-toggle-slider::before {
            content: "";
            position: absolute;
            width: 16px;
            height: 16px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .3);
            transition: .2s;
        }

        .chat-toggle-switch input:checked + .chat-toggle-slider {
            background: var(--icon-accent, #8e57fe);
        }

        .chat-toggle-switch input:checked + .chat-toggle-slider::before {
            transform: translateX(18px);
        }

        /* ─── ارجاع به کار/تیکت (#task:ID / #ticket:ID) ─── */
        .chat-linkref {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(255, 255, 255, .18);
            border-radius: 20px;
            padding: 2px 9px;
            font-size: .75rem;
            font-weight: 600;
            text-decoration: none;
            color: inherit;
            vertical-align: middle;
        }

        .chat-bubble-row.other .chat-linkref {
            background: var(--ink-050);
            color: var(--ink-900);
        }

        .chat-linkref:hover {
            opacity: .85;
        }

        .chat-linkref-cards {
            margin-top: 6px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .chat-linkref-card {
            background: rgba(255, 255, 255, .15);
            border-radius: 10px;
            padding: 8px 10px;
            cursor: pointer;
            font-size: .78rem;
        }

        .chat-bubble-row.other .chat-linkref-card {
            background: var(--ink-050);
        }

        .chat-linkref-card.loading,
        .chat-linkref-card.error {
            opacity: .7;
            cursor: default;
        }

        .chat-linkref-card-head {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .chat-linkref-card-title {
            font-weight: 700;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .chat-linkref-card-status {
            font-size: .68rem;
            opacity: .85;
            background: rgba(0, 0, 0, .12);
            border-radius: 20px;
            padding: 1px 8px;
            flex-shrink: 0;
        }

        .chat-linkref-card-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 4px;
            font-size: .72rem;
            opacity: .85;
            flex-wrap: wrap;
        }

        .chat-linkref-card-meta i {
            margin-left: 4px;
        }

        .chat-linkref-card-attachments {
            display: flex;
            gap: 6px;
            margin-top: 6px;
            flex-wrap: wrap;
        }

        .chat-linkref-card-attachments img,
        .chat-linkref-file {
            width: 42px;
            height: 42px;
            border-radius: 6px;
            object-fit: cover;
            cursor: pointer;
        }

        .chat-linkref-file {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, .1);
            font-size: 1.1rem;
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
        .chat-emoji-btn,
        .chat-send-btn {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-btn);
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

        #chatEmojiBtn {
            background: transparent;
            color: var(--text-muted);
        }

        #chatEmojiBtn:hover,
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

        /* ─── پیکرِ ایموجی ─── */
        .chat-emoji-picker {
            position: absolute;
            bottom: 54px;
            right: 8px;
            width: 280px;
            max-height: 260px;
            overflow-y: auto;
            background: var(--surface);
            border: 1px solid var(--border-soft, #eee);
            border-radius: 12px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, .18);
            padding: 8px;
            display: none;
            z-index: 50;
            grid-template-columns: repeat(7, 1fr);
        }

        .chat-emoji-picker.show {
            display: grid;
        }

        .chat-emoji-picker span {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            padding: 4px;
            border-radius: 6px;
            cursor: pointer;
            line-height: 1;
        }

        .chat-emoji-picker span:hover {
            background: var(--ink-050);
        }

        /* ─── سوئیچِ دوگزینه‌ایِ «برایِ کیه؟» در مودالِ تعریفِ کار ─── */
        .qt-toggle {
            display: flex;
            gap: 4px;
            padding: 4px;
            background: var(--ink-050, #f1f2f6);
            border: 1px solid var(--border-soft, #e5e7eb);
            border-radius: 999px;
        }

        .qt-toggle input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .qt-toggle label {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin: 0;
            padding: 8px 10px;
            border-radius: 9px;
            font-size: .85rem;
            font-weight: 500;
            color: var(--text-muted, #6b7280);
            cursor: pointer;
            transition: background .2s ease, color .2s ease, box-shadow .2s ease;
        }

        .qt-toggle input:checked + label {
            background: var(--brand-gradient);
            color: #fff;
            box-shadow: 0 3px 10px rgba(99, 102, 241, .35);
        }

        .qt-toggle input:focus-visible + label {
            outline: 2px solid var(--icon-accent);
            outline-offset: 2px;
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

        /* ── مودالِ ارسالِ فایل/عکس همراه با توضیح ── */
        .chat-fc-modal-content {
            overflow: hidden;
        }

        .chat-fc-header {
            padding: 12px 16px;
        }

        .chat-fc-preview-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            max-height: 62vh;
            overflow-y: auto;
            background: var(--bg-page, #f4f2f9);
            padding: 10px;
        }

        .chat-fc-preview-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            background: var(--ink-050);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chat-fc-preview-item img {
            width: 100%;
            max-height: 62vh;
            object-fit: contain;
            display: block;
        }

        .chat-fc-preview-item.file {
            flex-direction: column;
            padding: 24px 10px;
            gap: 8px;
            font-size: .8rem;
            text-align: center;
        }

        .chat-fc-preview-item i.bi-file-earmark {
            font-size: 2.4rem;
            color: var(--text-muted);
        }

        .chat-fc-preview-item .chat-fc-file-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 100%;
        }

        .chat-fc-preview-item .chat-fc-remove {
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(0, 0, 0, .55);
            color: #fff;
            border-radius: 9px;
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: .85rem;
        }

        .chat-fc-composer {
            border-top: 1px solid var(--border-soft, #eee);
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
            border-radius: 9px;
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
            border-radius: 9px;
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

        .chat-group-member-role-btn {
            border: none;
            background: transparent;
            color: var(--primary, #8e57fe);
            cursor: pointer;
            font-size: .95rem;
            padding: 4px;
        }

        @media (max-width: 768px) {
            .chat-wrap {
                padding: 0;
                height: calc(100vh - 70px - 34px);
                height: calc(100dvh - 70px - 34px);
                margin: 0;
            }

            /* رويِ گوشی‌هایِ دارایِ نوارِ اشاره‌ای (gesture bar) پایینِ صفحه،
               ۱۸px پیش‌فرض کافی نیست و دکمه زیرِ اون نوار پنهان می‌شه */
            .chat-new-btn {
                bottom: calc(18px + env(safe-area-inset-bottom, 0px));
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
                    <div class="d-flex align-items-center gap-2">
                        <button class="chat-search-toggle-btn" id="chatDesktopNotifBtn" onclick="handleDesktopNotifClick()" title="اعلان دسکتاپ">
                            <i class="bi bi-bell" id="chatDesktopNotifIcon"></i>
                        </button>
                    </div>
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
                        <div class="chat-avatar" id="chatHeadAvatar" onclick="openGroupInfoIfApplicable()" style="cursor:pointer;">?<span class="chat-avatar-online-dot"></span></div>
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

                        <button class="chat-search-toggle-btn" id="chatMediaGalleryBtn" onclick="openMediaGallery()" title="فایل‌ها و عکس‌های این گفتگو">
                            <i class="bi bi-images"></i>
                        </button>

                        <button class="chat-search-toggle-btn" id="chatMuteToggleBtn" onclick="toggleMuteActiveConversation()" title="بی‌صداکردن این گفتگو">
                            <i class="bi bi-bell"></i>
                        </button>

                        <button class="chat-search-toggle-btn" id="chatSearchToggleBtn" onclick="toggleMsgSearch()" title="جستجو در گفتگو">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>

                    <div class="chat-pinned-banner" id="chatPinnedBanner" style="display:none;">
                        <i class="bi bi-pin-angle-fill"></i>
                        <div class="chat-pinned-banner-body" onclick="scrollToOriginalMessage(pinnedMessage && pinnedMessage.id, true)">
                            <div class="chat-pinned-banner-label">پیام سنجاق‌شده</div>
                            <div class="chat-pinned-banner-text" id="chatPinnedBannerText"></div>
                        </div>
                        <i class="bi bi-x-lg chat-pinned-banner-close" id="chatPinnedBannerClose" onclick="unpinCurrentMessage()" title="برداشتن سنجاق"></i>
                    </div>

                    <div class="chat-messages-wrap">
                        <div class="chat-messages" id="chatMessages"></div>
                        <button class="chat-scroll-bottom-btn" id="chatScrollBottomBtn" onclick="scrollChatToBottom()" title="برو به آخرین پیام">
                            <i class="bi bi-chevron-down"></i>
                            <span class="chat-scroll-bottom-badge" id="chatScrollBottomBadge" style="display:none;"></span>
                        </button>
                    </div>

                    <div class="chat-pending-files" id="pendingFiles" style="display:none;"></div>

                    <div class="chat-edit-banner" id="chatEditBanner">
                        <i class="bi bi-pencil-square"></i>
                        <span class="chat-edit-banner-text">در حال ویرایش پیام</span>
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
                        <div class="chat-mention-autocomplete" id="linkRefAutocomplete" style="display:none;"></div>
                        <div class="chat-emoji-picker" id="chatEmojiPicker"></div>
                        <button class="chat-attach-btn" id="chatAttachBtn" onclick="document.getElementById('chatFileInput').click()" title="پیوست فایل">
                            <i class="bi bi-paperclip"></i>
                        </button>
                        <input type="file" id="chatFileInput" multiple style="display:none;">
                        <button class="chat-emoji-btn" id="chatEmojiBtn" onclick="toggleEmojiPicker(event)" title="ایموجی">
                            <i class="bi bi-emoji-smile"></i>
                        </button>
                        <textarea class="chat-composer-input" id="chatComposerInput" rows="1" placeholder="پیامی بنویسید..."></textarea>
                        <button class="chat-send-btn" id="chatSendBtn" onclick="sendChatMessage()">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- دراورِ پروفایلِ طرفِ مقابل — عمداً داخلِ chat-shell، تا فقط رویِ
                 خودِ صفحه‌یِ چت باز بشه، نه رویِ کلِ مانیتور/صفحه -->
            <div class="chat-profile-drawer-overlay" id="chatProfileDrawerOverlay" onclick="closeChatProfileDrawer()"></div>
            <div class="chat-profile-drawer" id="chatProfileDrawer">
                <div class="chat-profile-drawer-head">
                    <span>اطلاعات پروفایل</span>
                    <button type="button" class="btn-close" onclick="closeChatProfileDrawer()"></button>
                </div>
                <div class="chat-profile-drawer-body">
                    <div class="chat-profile-drawer-avatar-wrap">
                        <div class="chat-profile-drawer-avatar" id="chatProfileDrawerAvatar"></div>
                    </div>
                    <div class="chat-profile-drawer-name" id="chatProfileDrawerName">—</div>

                    <div class="chat-profile-drawer-field">
                        <i class="bi bi-telephone"></i>
                        <div>
                            <div class="chat-profile-drawer-value" id="chatProfileDrawerPhone">—</div>
                            <div class="chat-profile-drawer-label">شماره تلفن</div>
                        </div>
                    </div>
                    <div class="chat-profile-drawer-field">
                        <i class="bi bi-diagram-3"></i>
                        <div>
                            <div class="chat-profile-drawer-value" id="chatProfileDrawerSection">—</div>
                            <div class="chat-profile-drawer-label">واحد فعالیت</div>
                        </div>
                    </div>
                    <div class="chat-profile-drawer-field">
                        <i class="bi bi-bell"></i>
                        <div class="chat-profile-drawer-label" style="flex:1;">اعلان‌ها</div>
                        <label class="chat-toggle-switch">
                            <input type="checkbox" id="chatProfileDrawerMuteToggle" onchange="toggleMuteActiveConversation()">
                            <span class="chat-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="chat-profile-drawer-media-heading">تصاویر ردوبدل‌شده</div>
                    <div class="chat-media-grid" id="chatProfileDrawerMediaGrid">
                        <div class="chat-empty-list">در حال بارگذاری...</div>
                    </div>
                </div>
            </div>

            <!-- دراورِ اطلاعاتِ گروه — دراورِ جدا از دراورِ پروفایلِ مستقیم،
                 چون محتوایِ خیلی متفاوتی داره (اعضا/آپلودِ عکس/افزودنِ عضو) -->
            <div class="chat-profile-drawer-overlay" id="groupInfoDrawerOverlay" onclick="closeGroupInfoDrawer()"></div>
            <div class="chat-profile-drawer" id="groupInfoDrawer">
                <div class="chat-profile-drawer-head">
                    <span id="groupInfoTitle">اطلاعات گروه</span>
                    <button type="button" class="btn-close" onclick="closeGroupInfoDrawer()"></button>
                </div>
                <div class="chat-profile-drawer-body">
                    <div class="chat-profile-drawer-avatar-wrap">
                        <div class="chat-group-avatar-wrap" id="groupInfoAvatarWrap" onclick="triggerGroupAvatarUpload()"></div>
                    </div>
                    <input type="file" id="groupAvatarFileInput" accept="image/*" style="display:none;" onchange="uploadGroupAvatar(this.files[0])">

                    <div class="chat-profile-drawer-field">
                        <i class="bi bi-bell"></i>
                        <div class="chat-profile-drawer-label" style="flex:1;">اعلان‌ها</div>
                        <label class="chat-toggle-switch">
                            <input type="checkbox" id="groupInfoMuteToggle" onchange="toggleMuteActiveConversation()">
                            <span class="chat-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="chat-profile-drawer-media-heading">اعضا</div>
                    <div id="groupInfoMemberList"></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2" id="groupInfoAddBtn" style="display:none;" onclick="openAddMembersMode()">
                        <i class="bi bi-person-plus"></i> افزودن عضو
                    </button>

                    <div class="chat-profile-drawer-media-heading">تصاویر ردوبدل‌شده</div>
                    <div class="chat-media-grid" id="groupInfoMediaGrid">
                        <div class="chat-empty-list">در حال بارگذاری...</div>
                    </div>

                    <button type="button" class="btn btn-outline-danger btn-sm w-100 mt-3" onclick="confirmLeaveGroup()">
                        <i class="bi bi-box-arrow-right"></i> خروج از گروه
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- مودالِ گفتگوی جدید -->
    <div class="modal fade" id="newChatModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="newChatModalTitle">شروع گفتگوی جدید</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="chat-modal-tabs" id="newChatModeTabs">
                        <button type="button" class="chat-modal-tab active" id="newChatTabDirect" onclick="switchNewChatMode('direct')">گفتگوی مستقیم</button>
                        <button type="button" class="chat-modal-tab" id="newChatTabGroup" onclick="switchNewChatMode('group')">گروه جدید</button>
                    </div>
                    <input type="text" class="form-control mb-2" id="newGroupTitleInput" placeholder="نام گروه..." autocomplete="off" style="display:none;" oninput="updateCreateGroupBtnState()">
                    <div class="chat-group-chips" id="groupSelectedChips" style="display:none;"></div>
                    <input type="text" class="form-control mb-3" id="newChatSearchInput" placeholder="جستجوی نام همکار..." autocomplete="off">
                    <div id="newChatUserResults"></div>
                </div>
                <div class="modal-footer" id="newGroupFooter" style="display:none;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary btn-sm" id="createGroupBtn" onclick="submitNewChatModalAction()" disabled>ایجاد گروه</button>
                </div>
            </div>
        </div>
    </div>

    <!-- مودالِ اطلاعاتِ گروه -->
    <!-- مودالِ فایل‌ها و عکس‌هایِ مشترکِ گفتگو -->
    <div class="modal fade" id="mediaGalleryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">فایل‌ها و عکس‌های این گفتگو</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="mediaGalleryBody">
                        <div class="chat-empty-list">در حال بارگذاری...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- دراورِ پروفایلِ طرفِ مقابل (فقط گفتگویِ مستقیم) -->
    <!-- مودالِ هدایت پیام -->
    <div class="modal fade" id="forwardModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">هدایت به...</h6>
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
                    <h6 class="modal-title">حذف پیام</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="chat-profile-drawer-field" style="border-bottom:none; padding:4px;">
                        <label class="chat-profile-drawer-label" for="deleteMsgForEveryoneCheck" id="deleteMsgForEveryoneLabel" style="flex:1; font-size:.85rem; color:var(--ink-900); cursor:pointer;"></label>
                        <label class="chat-toggle-switch">
                            <input type="checkbox" id="deleteMsgForEveryoneCheck" checked>
                            <span class="chat-toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteMessage()">حذف پیام</button>
                </div>
            </div>
        </div>
    </div>

    <!-- مودالِ تنظیمِ اختیاراتِ اختصاصیِ یک مدیرِ گروه (فقط سازنده) -->
    <div class="modal fade" id="groupMemberPermissionsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">اختیاراتِ <span id="gmpMemberName"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="gmpPermissionList"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary btn-sm" id="gmpSaveBtn" onclick="saveGroupMemberPermissions()">ذخیره</button>
                </div>
            </div>
        </div>
    </div>

    <!-- مودالِ تعریفِ کار از رویِ یک پیامِ چت -->
    <div class="modal fade" id="quickTaskModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-list-task me-2"></i>تعریف کار</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">عنوان کار *</label>
                        <input type="text" class="form-control" id="quickTaskTitle" placeholder="عنوان کار را وارد کنید...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">توضیحات</label>
                        <textarea class="form-control" id="quickTaskDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3" id="quickTaskAssigneeRow" style="display:none;">
                        <label class="form-label d-block">این کار برای کیه؟</label>
                        <div class="qt-toggle">
                            <input type="radio" name="quickTaskAssignee" id="quickTaskAssigneeMe" value="me" checked>
                            <label for="quickTaskAssigneeMe"><i class="bi bi-person-fill"></i>خودم</label>
                            <input type="radio" name="quickTaskAssignee" id="quickTaskAssigneeOther" value="other">
                            <label for="quickTaskAssigneeOther" id="quickTaskAssigneeOtherLabel"><i class="bi bi-people-fill"></i>مخاطب چت</label>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">موعد انجام</label>
                        <div class="persian-datepicker-wrapper" id="quickTaskDueDateWrap" data-restrict-past="0">
                            <input type="text" id="quickTaskDueDate" class="persian-datepicker-input form-control"
                                placeholder="انتخاب تاریخ..." readonly>
                            <div class="persian-datepicker">
                                <div class="datepicker-header">
                                    <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                    <span class="datepicker-current">-</span>
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
                    </div>
                    <a href="#" id="quickTaskCompleteLink" style="font-size:.82rem;">
                        <i class="bi bi-arrow-up-left-circle me-2"></i>تکمیل اطلاعات (فیلدهای بیشتر)
                    </a>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary btn-sm" id="quickTaskSubmitBtn" onclick="submitQuickTask()">ایجاد کار</button>
                </div>
            </div>
        </div>
    </div>

    <!-- مودالِ ارسالِ فایل/عکس همراه با توضیح -->
    <div class="modal fade" id="fileCaptionModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content chat-fc-modal-content">
                <div class="modal-header chat-fc-header">
                    <h6 class="modal-title">ارسال فایل</h6>
                    <button type="button" class="btn-close" onclick="cancelFileCaptionModal()"></button>
                </div>
                <div class="chat-fc-preview-list" id="fileCaptionPreviewList"></div>
                <div class="chat-composer chat-fc-composer">
                    <textarea class="chat-composer-input" id="fileCaptionInput" rows="1" placeholder="پیامی بنویسید..."></textarea>
                    <button class="chat-send-btn" id="fileCaptionSendBtn" onclick="sendFilesWithCaption()">
                        <i class="bi bi-send-fill"></i>
                    </button>
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
        <div class="chat-ctx-menu-item" id="chatCtxTaskItem" onclick="taskFromCtxMenu()">
            <i class="bi bi-list-task"></i>
            <span>تعریف کار</span>
        </div>
        <div class="chat-ctx-menu-item" onclick="replyFromCtxMenu()">
            <i class="bi bi-reply-fill"></i>
            <span>پاسخ</span>
        </div>
        <div class="chat-ctx-menu-item" onclick="forwardFromCtxMenu()">
            <i class="bi bi-arrow-return-right"></i>
            <span>هدایت</span>
        </div>
        <div class="chat-ctx-menu-item" id="chatCtxCopyItem" onclick="copyFromCtxMenu()">
            <i class="bi bi-clipboard"></i>
            <span>کپی متن</span>
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
            <i class="bi bi-trash"></i>
            <span>حذف پیام</span>
        </div>
    </div>

    <!-- راست‌کلیک روی یک ردیفِ گفتگو در لیست (فقط گفتگوهایِ مستقیم) -->
    <div class="chat-ctx-menu" id="chatConvCtxMenu">
        <div class="chat-ctx-menu-item danger" onclick="deleteConversationFromCtxMenu()">
            <i class="bi bi-trash"></i>
            <span>حذف گفتگو</span>
        </div>
    </div>

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
        // ── بارگذاریِ پیام‌های قدیمی‌تر با اسکرول به بالا ──
        var oldestMessageId = 0;   // کوچک‌ترین idِ نمایش‌داده‌شده
        var lastAppendedDateKey = null; // تاریخِ (میلادیِ خام) آخرین پیامِ اضافه‌شده به‌ته لیست — برایِ تشخیصِ نیازِ جداکننده‌ی تاریخ
        var hasMoreOlder = false;  // آیا در دیتابیس پیامِ قدیمی‌ترِ نمایش‌داده‌نشده هست؟
        var loadingOlder = false;  // گاردِ همزمانی — جلوی درخواستِ تکراری حینِ اسکرول
        var pendingFiles = [];
        var pollTimer = null;
        var readReceipts = {}; // user_id -> آخرین پیامِ‌خوانده‌شده‌یِ او، فقط برایِ گفتگویِ فعال
        var pinnedMessage = null; // { id, snippet, user_name, is_own } یا null
        var pinnedCanManage = false;
        var activeGroupMembers = []; // [{id, full_name}] — فقط برایِ گفتگویِ گروهیِ فعال، برایِ منشن
        var activeGroupIsCreator = false; // آیا کاربرِ جاری سازنده‌یِ همین گروهِ فعال است — برایِ حذفِ پیامِ دیگران
        var activeGroupCanManage = false; // سازنده یا مدیر — برایِ نمایشِ کنترل‌هایِ مدیریتی در دراورِ اطلاعاتِ گروه

        // ── پرش به اولین پیامِ خوانده‌نشده هنگامِ بازکردنِ گفتگو (مثلِ تلگرام/سروش) ──
        var unreadDividerBeforeId = 0;  // idِ پیامی که خطِ «پیام‌های خوانده‌نشده» باید درست بالایش قرار بگیرد؛ فقط یک‌بار مصرف می‌شود
        var readTrackMaxSeenId = 0;     // بزرگ‌ترین idِ پیامی که تاکنون واقعاً روی صفحه دیده شده (از IntersectionObserver)
        var readTrackSentUpToId = 0;    // آخرین idـی که با موفقیت به mark-read.php فرستاده شده — از تکرارِ بی‌جهت جلوگیری می‌کند
        var readTrackDebounce = null;
        var chatMsgObserver = (typeof IntersectionObserver !== 'undefined') ? new IntersectionObserver(onMessageRowVisible, {
            root: document.getElementById('chatMessages'), // باید نسبتِ به همین کادرِ اسکرول‌شونده حساب شود، نه کلِ ویوپورت صفحه
            threshold: 0.6
        }) : null;

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
            var __draftSaveTimer = null;
            composer.addEventListener('input', function() {
                autoGrowComposer(this);
                notifyTyping();
                checkMentionTrigger();
                checkLinkRefTrigger();
                clearTimeout(__draftSaveTimer);
                __draftSaveTimer = setTimeout(saveComposerDraft, 300);
            });
            window.addEventListener('beforeunload', saveComposerDraft);

            var fileCaptionInputEl = document.getElementById('fileCaptionInput');
            fileCaptionInputEl.addEventListener('input', function() {
                autoGrowComposer(this);
            });
            fileCaptionInputEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendFilesWithCaption();
                }
            });
            composer.addEventListener('blur', function() {
                // تأخیرِ کوتاه تا رویدادِ کلیک روی گزینه‌یِ اتوکامپلیت زودتر ثبت شود
                setTimeout(closeMentionAutocomplete, 150);
                setTimeout(closeLinkRefAutocomplete, 150);
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
                var linkRefOpen = document.getElementById('linkRefAutocomplete').style.display === 'block';
                if (linkRefOpen && linkRefCandidates.length) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        linkRefActiveIndex = Math.min(linkRefActiveIndex + 1, linkRefCandidates.length - 1);
                        renderLinkRefAutocomplete();
                        return;
                    }
                    if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        linkRefActiveIndex = Math.max(linkRefActiveIndex - 1, 0);
                        renderLinkRefAutocomplete();
                        return;
                    }
                    if (e.key === 'Enter' || e.key === 'Tab') {
                        e.preventDefault();
                        applyLinkRef(linkRefActiveIndex >= 0 ? linkRefActiveIndex : 0);
                        return;
                    }
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        closeLinkRefAutocomplete();
                        return;
                    }
                }
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendChatMessage();
                }
            });

            updateDesktopNotifIcon();

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
                // اگه کاربر خودش دوباره به پایین برگشت، بجِ «پیامِ جدید» بی‌مورد می‌شه
                if (distanceFromBottom <= 200) { chatNewMsgCount = 0; updateChatNewMsgBadge(); }
                // نزدیکِ بالای لیست → پیام‌های قدیمی‌ترِ بعدی را بیاور
                if (el.scrollTop < 120) prependOlderMessages();
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
            chatNewMsgCount = 0;
            updateChatNewMsgBadge();
        }

        // آیا کاربر همین الان نزدیکِ پایینِ لیستِ پیام‌هاست؟ (هم‌آستانه با
        // دکمه‌ی «برو به آخرین پیام») — برایِ تصمیم‌گیری که پیامِ تازه‌رسیده
        // خودکار اسکرول کنه یا فقط بج بخوره
        function isChatNearBottom() {
            var el = document.getElementById('chatMessages');
            if (!el) return true;
            return (el.scrollHeight - el.scrollTop - el.clientHeight) <= 200;
        }

        var chatNewMsgCount = 0;
        function updateChatNewMsgBadge() {
            var badge = document.getElementById('chatScrollBottomBadge');
            if (!badge) return;
            if (chatNewMsgCount > 0) {
                badge.textContent = chatNewMsgCount > 9 ? '۹+' : toFa(chatNewMsgCount);
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }
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
        // ─────────────── پیکرِ ایموجی (سبک، بدونِ کتابخانه‌یِ بیرونی) ───────────────
        var EMOJI_LIST = [
            '😀', '😁', '😂', '🤣', '😊', '😍', '😘', '😉', '😎', '🤩',
            '🥳', '😇', '🙂', '🙃', '😅', '😆', '😋', '😜', '🤗', '🤔',
            '🤨', '😐', '😑', '😴', '🥱', '😪', '🤤', '😷', '🤒', '🤕',
            '😭', '😢', '😔', '😞', '😟', '😕', '🙁', '😣', '😖', '😫',
            '😩', '🥺', '😤', '😠', '😡', '🤬', '😳', '😱', '😨', '😰',
            '👍', '👎', '👏', '🙏', '🤝', '💪', '✌️', '🤞', '👌', '🤙',
            '👋', '🖐️', '✋', '🤚', '👊', '✊', '🫡', '💅', '🤲', '🙌',
            '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '💔', '💕',
            '💯', '🔥', '✨', '🎉', '🎊', '🌟', '⭐', '⚡', '🌹', '🎁',
            '☕', '🍕', '🍰', '🍎', '⏰', '📌', '✅', '❌', '❗', '❓'
        ];
        var emojiPickerOpen = false;

        function renderEmojiPicker() {
            var box = document.getElementById('chatEmojiPicker');
            if (box.childElementCount) return; // فقط بارِ اول
            box.innerHTML = EMOJI_LIST.map(function (e) {
                return '<span onclick="insertEmoji(\'' + e + '\')">' + e + '</span>';
            }).join('');
        }

        function toggleEmojiPicker(ev) {
            if (ev) ev.stopPropagation();
            renderEmojiPicker();
            var box = document.getElementById('chatEmojiPicker');
            emojiPickerOpen = !emojiPickerOpen;
            box.classList.toggle('show', emojiPickerOpen);
        }

        function closeEmojiPicker() {
            emojiPickerOpen = false;
            var box = document.getElementById('chatEmojiPicker');
            if (box) box.classList.remove('show');
        }

        document.addEventListener('click', function (e) {
            var box = document.getElementById('chatEmojiPicker');
            var btn = document.getElementById('chatEmojiBtn');
            if (!box || !emojiPickerOpen) return;
            if (!box.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                closeEmojiPicker();
            }
        });

        // درجِ ایموجیِ انتخاب‌شده در محلِ نشانگرِ ماوس داخلِ کادرِ پیام (نه لزوماً انتهایِ متن)
        function insertEmoji(emoji) {
            var input = document.getElementById('chatComposerInput');
            var start = input.selectionStart ?? input.value.length;
            var end = input.selectionEnd ?? input.value.length;
            input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
            var newPos = start + emoji.length;
            input.focus();
            input.setSelectionRange(newPos, newPos);
            autoGrowComposer(input);
            saveComposerDraft();
        }

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
        // ✅ حالا با api/chat/search-messages.php (محدود به همین گفتگو، با
        // conversation_id) کلِ تاریخچه رو می‌گرده، نه فقط پیام‌هایی که تصادفاً
        // همین الان لود شدن — قبلاً فقط DOM رو می‌گشت (کدِ قدیمی، محدودیتش
        // مستندشده بود) و برایِ پیام‌هایِ قدیمی‌ترِ لودنشده هیچی پیدا نمی‌کرد
        var msgSearchMatches = []; // نتایجِ خامِ API: [{message_id, snippet, ...}]
        var msgSearchActiveIdx = -1;
        var msgSearchDebounce = null;
        var msgSearchReqSeq = 0; // نادیده‌گرفتنِ پاسخِ دیرکرده‌یِ یک جست‌وجویِ قدیمی‌تر

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
                ? toFa(msgSearchActiveIdx + 1) + ' از ' + toFa(msgSearchMatches.length)
                : (document.getElementById('chatMsgSearchInput').value ? 'موردی نیست' : '');
            document.getElementById('chatMsgSearchPrevBtn').disabled = msgSearchMatches.length === 0;
            document.getElementById('chatMsgSearchNextBtn').disabled = msgSearchMatches.length === 0;
        }

        function runMsgSearch() {
            clearTimeout(msgSearchDebounce);
            clearMsgSearchHighlights();

            var term = document.getElementById('chatMsgSearchInput').value.trim();
            if (!term) {
                msgSearchMatches = [];
                msgSearchActiveIdx = -1;
                updateMsgSearchCount();
                return;
            }

            var mySeq = ++msgSearchReqSeq;
            var convId = activeConversationId;
            msgSearchDebounce = setTimeout(function () {
                fetch('../api/chat/search-messages.php?q=' + encodeURIComponent(term) + '&conversation_id=' + convId, {
                        headers: { 'Authorization': 'Bearer ' + authToken }
                    })
                    .then(r => r.json())
                    .then(data => {
                        // گفتگو عوض شده یا جست‌وجویِ تازه‌تری در راهه — این پاسخِ کهنه رو نادیده بگیر
                        if (mySeq !== msgSearchReqSeq || convId !== activeConversationId) return;
                        msgSearchMatches = (data.success && data.results) ? data.results : [];
                        msgSearchActiveIdx = msgSearchMatches.length ? 0 : -1;
                        updateMsgSearchCount();
                        if (msgSearchMatches.length) focusMsgSearchMatch();
                    })
                    .catch(function () {
                        if (mySeq !== msgSearchReqSeq) return;
                        msgSearchMatches = [];
                        msgSearchActiveIdx = -1;
                        updateMsgSearchCount();
                    });
            }, 350);
        }

        function navMsgSearch(direction) {
            if (!msgSearchMatches.length) return;
            clearMsgSearchHighlights();
            msgSearchActiveIdx = (msgSearchActiveIdx + direction + msgSearchMatches.length) % msgSearchMatches.length;
            focusMsgSearchMatch();
            updateMsgSearchCount();
        }

        function focusMsgSearchMatch() {
            var match = msgSearchMatches[msgSearchActiveIdx];
            if (!match) return;
            var term = document.getElementById('chatMsgSearchInput').value.trim();

            var row = document.querySelector('.chat-bubble-row[data-message-id="' + match.message_id + '"]');
            if (row) {
                scrollToOriginalMessage(match.message_id, true);
                highlightSearchTermInRow(match.message_id, term);
                return;
            }

            // پیام هنوز در DOM لود نشده — گفتگو با تمرکز روی همین پیام دوباره
            // بارگذاری می‌شه (openConversation خودش closeMsgSearch رو صدا می‌زنه،
            // برایِ همین باید بعدِ اتمامِ لود، نوار و نتایجِ جست‌وجو رو خودمون
            // برگردونیم — afterLoad دقیقاً برایِ همین به openConversation اضافه شد)
            var savedResults = msgSearchMatches;
            var savedIdx = msgSearchActiveIdx;
            openConversation(activeConversationId, match.message_id, null, function () {
                document.getElementById('chatMsgSearchBar').classList.add('show');
                document.getElementById('chatMsgSearchInput').value = term;
                msgSearchMatches = savedResults;
                msgSearchActiveIdx = savedIdx;
                updateMsgSearchCount();
                highlightSearchTermInRow(match.message_id, term);
            });
        }

        // هایلایتِ زردِ خودِ کلمه (نه فقط چشمک‌زدنِ کلِ حباب) — فقط وقتی که
        // ردیفِ پیام قطعاً در DOM هست (بعد از اسکرول یا بعدِ لودشدن)
        function highlightSearchTermInRow(messageId, term) {
            if (!term) return;
            var row = document.querySelector('.chat-bubble-row[data-message-id="' + messageId + '"]');
            var textDiv = row && row.querySelector('.chat-bubble > div:not(.chat-bubble-quote)');
            if (!textDiv) return;
            var termLower = term.toLowerCase();
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
                break;
            }
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
                // avatarUrl مسیرِ فایلی است که سرور ساخته؛ باز هم برای اطمینان
                // کاراکترهای شکنندهٔ attribute را انکد می‌کنیم.
                var safeUrl = String(avatarUrl).replace(/[<>"'\s]/g, encodeURIComponent);
                el.innerHTML = '<img class="chat-avatar-img" src="../' + safeUrl + '" alt=""><span class="chat-avatar-online-dot"></span>';
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
                        checkNewMessagesForDesktopNotif(data.conversations);
                        conversations = data.conversations;
                        renderConversationList();
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
                        '<div class="chat-empty-list">خطا در ارتباط با سرور — لطفا صفحه را رفرش کنید</div>';
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
                var draftText = (localStorage.getItem(draftKey(c.conversation_id)) || '').trim();
                var isDraft = draftText !== '';
                if (isDraft) {
                    var draftWords = draftText.split(/\s+/).slice(0, 8).join(' ');
                    preview = esc(draftWords) + (draftText.split(/\s+/).length > 8 ? ' …' : '');
                }
                var active = c.conversation_id === activeConversationId ? ' active' : '';
                var badge = c.unread_count > 0
                    ? '<span class="chat-unread-badge' + (c.is_muted ? ' muted' : '') + '">' + (c.unread_count > 99 ? toFa(99) + '+' : toFa(c.unread_count)) + '</span>'
                    : '';
                var muteIcon = c.is_muted ? '<i class="bi bi-bell-slash-fill chat-conv-mute-icon"></i>' : '';
                var time = !c.last_message_at ? '' : (window.TimeSync
                    ? TimeSync.formatTimeOnly(c.last_message_at)
                    : new Date(c.last_message_at.replace(' ', 'T')).toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' }));
                var previewHtml = c.other_user_is_typing
                    ? '<div class="chat-conv-preview typing">در حال نوشتن...</div>'
                    : (isDraft
                        ? '<div class="chat-conv-preview draft"><span class="chat-conv-draft-label">پیش‌نویس:</span> ' + preview + '</div>'
                        : '<div class="chat-conv-preview">' + preview + '</div>');
                // راست‌کلیک برایِ حذفِ گفتگو فقط رویِ چت‌هایِ مستقیم (نه گروه —
                // برایِ گروه معادلش «ترک گروه» از داخلِ خودِ گفتگوست)
                var convCtxAttr = c.type === 'direct'
                    ? ' oncontextmenu="return openConvCtxMenu(event, ' + c.conversation_id + ')"'
                    : '';
                return '<div class="chat-conv-item' + active + '" onclick="openConversation(' + c.conversation_id + ')"' + convCtxAttr + '>' +
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
        // fallbackInfo اختیاریه: {title, avatar_url} — برایِ اولین‌بار که با
        // کسی چت می‌کنیم، گفتگویِ تازه‌ساخته‌شده هنوز پیامی نداره، پس توی
        // لیستِ conversations نیست (که عمداً چت‌هایِ بدونِ‌پیام رو نشون نمی‌ده)؛
        // بدونِ این fallback، هدر تا فرستادنِ اولین پیام و رفرش/سوییچ، خط‌تیره می‌موند
        // afterLoad اختیاریه: تابعی که درست بعدِ رندرشدنِ پیام‌ها (و اسکرولِ
        // jumpToMessageId، اگر بود) صدا زده می‌شه — مثلاً جست‌وجویِ داخلِ گفتگو
        // ازش استفاده می‌کنه تا بعدِ این ریست‌شدنِ کاملِ صفحه، نوارِ جست‌وجو رو
        // دوباره برگردونه (چون این تابع خودش closeMsgSearch رو صدا می‌زنه)
        function openConversation(id, jumpToMessageId, fallbackInfo, afterLoad) {
            saveComposerDraft(); // پیش‌نویسِ گفتگویِ قبلی (اگر بود) قبل از جابه‌جایی ذخیره بشه

            activeConversationId = id;
            var conv = conversations.find(c => c.conversation_id === id);
            activeConversationTitle = conv ? conv.title : (fallbackInfo ? fallbackInfo.title : '—');
            activeConversationType = conv ? conv.type : 'direct';
            cancelEditMessage();
            cancelReplyMessage();
            closeMsgSearch();
            isOtherPartyTyping = false;

            document.getElementById('chatPlaceholder').style.display = 'none';
            document.getElementById('chatActiveView').style.display = 'flex';
            document.getElementById('chatHeadName').textContent = activeConversationTitle;
            document.getElementById('chatHeadInfoWrap').classList.add('clickable');
            // چه مستقیم چه گروه، کنترلِ رسانه/اعلان الان از داخلِ دراورِ
            // پروفایل/اطلاعاتِ گروه انجام می‌شه — آیکن‌هایِ جداگانهٔ هدر دیگه لازم نیستن
            document.getElementById('chatMediaGalleryBtn').style.display = 'none';
            document.getElementById('chatMuteToggleBtn').style.display = 'none';
            var headAvatar = document.getElementById('chatHeadAvatar');
            headAvatar.classList.toggle('online', !!(conv && conv.other_user_is_online));
            setAvatarContent(headAvatar, activeConversationTitle, conv ? conv.avatar_url : (fallbackInfo ? fallbackInfo.avatar_url : null));
            updateMuteButton(conv);
            updateChatHeadLastSeen(conv);
            document.getElementById('chatMessages').innerHTML = '';
            chatNewMsgCount = 0;
            updateChatNewMsgBadge();
            restoreComposerDraft(id);
            document.getElementById('chatComposerInput').focus();
            lastMessageId = 0;
            oldestMessageId = 0;
            lastAppendedDateKey = null;
            hasMoreOlder = false;
            loadingOlder = false;
            readReceipts = {};
            pinnedMessage = null;
            pinnedCanManage = false;
            renderPinnedBanner();
            loadActiveGroupMembers();

            // ── پرش به اولین پیامِ خوانده‌نشده: فقط وقتی جایی برای پرش صراحتاً
            // مشخص نشده (جستجو/ریپلای/پین هرکدام jumpToMessageId خودشان را می‌دهند) ──
            var isUnreadJump = false;
            if (!jumpToMessageId && conv && conv.unread_count > 0 && conv.first_unread_id) {
                jumpToMessageId = conv.first_unread_id;
                isUnreadJump = true;
            }
            unreadDividerBeforeId = isUnreadJump ? jumpToMessageId : 0;
            readTrackMaxSeenId = 0;
            readTrackSentUpToId = 0;
            clearTimeout(readTrackDebounce);

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
                        hasMoreOlder = !!data.has_more;   // اگر ۴۰ پیام کامل آمد، یعنی قدیمی‌ترها هم هست
                        loadConversations();
                        pollReadReceipts();
                        loadPinnedMessage();
                        if (jumpToMessageId) scrollToOriginalMessage(jumpToMessageId);
                        if (typeof afterLoad === 'function') afterLoad();
                    } else {
                        document.getElementById('chatMessages').innerHTML =
                            '<div class="chat-empty-list">' + esc(data.message || 'خطا در بارگذاری پیام‌ها') + '</div>';
                    }
                })
                .catch(function() {
                    document.getElementById('chatMessages').innerHTML =
                        '<div class="chat-empty-list">خطا در ارتباط با سرور — لطفا صفحه را رفرش کنید</div>';
                });
        }

        function closeConversation() {
            document.getElementById('chatSidebar').classList.remove('hide-mobile');
            document.getElementById('chatMain').classList.add('hide-mobile');
        }

        // ─────────────── منشن (@نام) در گروه ───────────────
        function loadActiveGroupMembers() {
            activeGroupMembers = [];
            activeGroupIsCreator = false;
            activeGroupCanManage = false;
            if (!activeConversationId || activeConversationType === 'direct') return;
            var convId = activeConversationId;
            fetch('../api/chat/group-members.php?conversation_id=' + activeConversationId, {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                })
                .then(r => r.json())
                .then(data => {
                    if (convId !== activeConversationId) return; // گفتگو عوض شده — این پاسخِ کهنه نادیده گرفته می‌شود
                    if (data.success) {
                        activeGroupMembers = data.members;
                        activeGroupIsCreator = !!data.is_owner;
                        activeGroupCanManage = !!data.can_manage;
                    }
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

        // ─────────────── ارجاع به کار/تیکت (#) در پیام ───────────────
        // مشابهِ اتوکامپلیتِ منشن، ولی به‌جایِ لیستِ ثابتِ اعضایِ گروه، هر بار
        // با فاصله (debounce) از دو APIِ جستجویِ سبک (کار/تیکت) نتیجه می‌گیره
        var linkRefCandidates = [];
        var linkRefActiveIndex = -1;
        var linkRefRangeStart = -1;
        var linkRefSearchTimer = null;
        var linkPreviewCache = {}; // کلید: 'task:123' یا 'ticket:45'

        function extractLinkRefs(rawText) {
            var refs = [], seen = {}, re = /#(task|ticket):(\d+)/g, m;
            while ((m = re.exec(rawText)) !== null) {
                var key = m[1] + ':' + m[2];
                if (seen[key]) continue;
                seen[key] = true;
                refs.push({ type: m[1], id: parseInt(m[2], 10) });
            }
            return refs;
        }

        // ورودی از قبل با esc() امن شده — الگو روی کاراکترهایِ ساده (#, حروفِ
        // لاتین، اعداد) کار می‌کنه که esc() دست‌نخورده می‌ذارتشون
        function highlightLinkRefs(escapedText) {
            return escapedText.replace(/#(task|ticket):(\d+)/g, function(full, type, id) {
                var label = type === 'task' ? 'کار' : 'تیکت';
                var icon = type === 'task' ? 'bi-card-checklist' : 'bi-headset';
                var url = (type === 'task' ? '../pages/task-detail.php?id=' : '../pages/ticket-detail.php?id=') + id;
                return '<a class="chat-linkref" href="' + url + '" target="_blank"><i class="bi ' + icon + '"></i>' + label + ' #' + id + '</a>';
            });
        }

        function loadLinkRefPreviews(row, refs) {
            refs.forEach(function(ref) {
                var key = ref.type + ':' + ref.id;
                var card = row.querySelector('.chat-linkref-card[data-type="' + ref.type + '"][data-id="' + ref.id + '"]');
                if (!card) return;

                if (linkPreviewCache[key]) {
                    renderLinkRefCard(card, linkPreviewCache[key]);
                    return;
                }

                fetch('../api/chat/link-preview.php?type=' + ref.type + '&id=' + ref.id, {
                        headers: { 'Authorization': 'Bearer ' + authToken }
                    })
                    .then(r => r.json())
                    .then(function(data) {
                        linkPreviewCache[key] = data;
                        renderLinkRefCard(card, data);
                    })
                    .catch(function() {
                        renderLinkRefCard(card, { success: false });
                    });
            });
        }

        // 'YYYY-MM-DD' میلادی → 'YYYY/MM/DD' شمسی با اعدادِ فارسی
        function toJalaliDateStr(gregorianDate) {
            if (!gregorianDate || !window.jalaali) return '';
            var parts = gregorianDate.split('-').map(Number);
            var j = jalaali.toJalaali(parts[0], parts[1], parts[2]);
            return toFaDigits(j.jy + '/' + String(j.jm).padStart(2, '0') + '/' + String(j.jd).padStart(2, '0'));
        }

        function renderLinkRefCard(card, data) {
            card.classList.remove('loading');
            if (!data || !data.success) {
                card.innerHTML = '<i class="bi bi-exclamation-triangle"></i> در دسترس نیست یا حذف شده';
                card.classList.add('error');
                return;
            }
            var icon = data.type === 'task' ? 'bi-card-checklist' : 'bi-headset';

            var metaHtml = '';
            if (data.type === 'task') {
                var metaParts = [];
                if (data.assignee_name) metaParts.push('<i class="bi bi-person"></i>' + esc(data.assignee_name));
                if (data.next_due_date) metaParts.push('<i class="bi bi-calendar-event"></i>' + toJalaliDateStr(data.next_due_date));
                if (metaParts.length) metaHtml = '<div class="chat-linkref-card-meta">' + metaParts.join('') + '</div>';
            } else if (data.last_message) {
                metaHtml = '<div class="chat-linkref-card-meta"><i class="bi bi-chat-left-text"></i>' + esc(data.last_message) + '</div>';
            }
            // پیش‌نمایشِ پیوست‌ها فقط برایِ تیکت — کارتِ تسک به عنوان/مسئول/موعد
            // بسنده می‌کنه (بدونِ تصویر، تا وابسته به سالم‌بودنِ فایلِ روی دیسک نباشه)
            var thumbs = data.type === 'ticket' ? (data.attachments || []).map(function(a) {
                if (a.is_image) {
                    // اگه فایل روی دیسک وجود نداشت (رکوردِ یتیم در دیتابیس)، به‌جایِ
                    // آیکنِ شکسته + نامِ فایل (که مرورگر به‌عنوانِ alt نشون می‌ده)،
                    // با یک آیکنِ فایلِ ساده جایگزینش می‌کنیم
                    return '<img src="' + a.url + '" alt="" onclick="event.stopPropagation();window.open(\'' + a.url + '\',\'_blank\')" ' +
                        'onerror="this.outerHTML=\'<div class=&quot;chat-linkref-file&quot; title=&quot;' + esc(a.name) + '&quot;><i class=&quot;bi bi-file-earmark&quot;></i></div>\'">';
                }
                return '<div class="chat-linkref-file" onclick="event.stopPropagation();window.open(\'' + a.url + '\',\'_blank\')" title="' + esc(a.name) + '"><i class="bi bi-file-earmark"></i></div>';
            }).join('') : '';

            card.innerHTML =
                '<div class="chat-linkref-card-head"><i class="bi ' + icon + '"></i>' +
                '<span class="chat-linkref-card-title">' + esc(data.title) + '</span>' +
                '<span class="chat-linkref-card-status">' + esc(data.status_label) + '</span></div>' +
                metaHtml +
                (thumbs ? '<div class="chat-linkref-card-attachments">' + thumbs + '</div>' : '');
            card.onclick = function() { window.open(data.detail_url, '_blank'); };
        }

        function checkLinkRefTrigger() {
            var box = document.getElementById('linkRefAutocomplete');
            var input = document.getElementById('chatComposerInput');
            var pos = input.selectionStart;
            var textBefore = input.value.slice(0, pos);
            var hashIndex = textBefore.lastIndexOf('#');
            if (hashIndex === -1) {
                box.style.display = 'none';
                clearTimeout(linkRefSearchTimer);
                return;
            }
            var partial = textBefore.slice(hashIndex + 1);
            // برخلافِ @منشن (که معمولاً یک کلمه‌ست)، عنوانِ کار/تیکت اغلب چندکلمه‌ایه؛
            // قبلاً با اولین فاصله جستجو کاملاً بسته می‌شد و امکانِ جستجو با عنوانِ
            // چندکلمه‌ای اصلاً وجود نداشت — الان فقط با خطِ‌جدید یا طولانی‌شدنِ
            // بیش‌ازحد (که دیگه به‌وضوح یک جستجو نیست) می‌بندیم
            if (/[\n\r]/.test(partial) || partial.length > 60) {
                box.style.display = 'none';
                clearTimeout(linkRefSearchTimer);
                return;
            }
            if (hashIndex > 0 && !/\s/.test(textBefore[hashIndex - 1])) {
                box.style.display = 'none';
                return;
            }
            linkRefRangeStart = hashIndex;

            clearTimeout(linkRefSearchTimer);
            if (partial.length < 1) {
                box.style.display = 'none';
                return;
            }
            linkRefSearchTimer = setTimeout(function() {
                searchLinkRefCandidates(partial, hashIndex);
            }, 300);
        }

        function searchLinkRefCandidates(q, hashIndexAtSearchTime) {
            Promise.all([
                fetch('../api/tasks/quick-search.php?q=' + encodeURIComponent(q), { headers: { 'Authorization': 'Bearer ' + authToken } }).then(r => r.json()).catch(() => ({ success: false })),
                fetch('../api/tickets/quick-search.php?q=' + encodeURIComponent(q), { headers: { 'Authorization': 'Bearer ' + authToken } }).then(r => r.json()).catch(() => ({ success: false }))
            ]).then(function(results) {
                // اگه کاربر تا این لحظه تایپش عوض شده، نتیجه‌ی قدیمی رو نادیده بگیر
                var input = document.getElementById('chatComposerInput');
                var textBefore = input.value.slice(0, input.selectionStart);
                if (textBefore.lastIndexOf('#') !== hashIndexAtSearchTime) return;

                var tasks = (results[0].success ? results[0].tasks : []).map(t => ({ type: 'task', id: t.id, label: t.title }));
                var tickets = (results[1].success ? results[1].tickets : []).map(t => ({ type: 'ticket', id: t.id, label: t.subject }));
                linkRefCandidates = tasks.concat(tickets).slice(0, 10);

                var box = document.getElementById('linkRefAutocomplete');
                if (!linkRefCandidates.length) {
                    box.style.display = 'none';
                    return;
                }
                linkRefActiveIndex = 0;
                renderLinkRefAutocomplete();
            });
        }

        function renderLinkRefAutocomplete() {
            var box = document.getElementById('linkRefAutocomplete');
            box.innerHTML = linkRefCandidates.map((c, i) => {
                var icon = c.type === 'task' ? 'bi-card-checklist' : 'bi-headset';
                var typeLabel = c.type === 'task' ? 'کار' : 'تیکت';
                return '<div class="chat-mention-item' + (i === linkRefActiveIndex ? ' active' : '') + '" onclick="applyLinkRef(' + i + ')">' +
                    '<i class="bi ' + icon + '"></i> ' + typeLabel + ' #' + c.id + ' — ' + esc(c.label) + '</div>';
            }).join('');
            box.style.display = 'block';
        }

        function closeLinkRefAutocomplete() {
            document.getElementById('linkRefAutocomplete').style.display = 'none';
            linkRefCandidates = [];
            linkRefRangeStart = -1;
            clearTimeout(linkRefSearchTimer);
        }

        function applyLinkRef(index) {
            var cand = linkRefCandidates[index];
            if (!cand || linkRefRangeStart === -1) return;
            var input = document.getElementById('chatComposerInput');
            var pos = input.selectionStart;
            var before = input.value.slice(0, linkRefRangeStart);
            var after = input.value.slice(pos);
            var insertText = '#' + cand.type + ':' + cand.id + ' ';
            input.value = before + insertText + after;
            var newPos = before.length + insertText.length;
            input.setSelectionRange(newPos, newPos);
            input.focus();
            closeLinkRefAutocomplete();
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
                        showToast(data.message || 'خطا در ثبت ری‌اکشن', 'error');
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

        // ── جداکننده‌ی تاریخ (شمسی) بینِ گروه‌هایِ پیامِ روزهایِ مختلف ──
        function chatDateKey(m) {
            return window.TimeSync ? TimeSync.dateOnly(m.created_at) : String(m.created_at || '').slice(0, 10);
        }
        function chatDateDividerLabel(m) {
            if (!window.TimeSync) return chatDateKey(m);
            var diff = TimeSync.daysFromToday(m.created_at); // ۰=امروز، ‑۱=دیروز، ...
            if (diff === 0) return 'امروز';
            if (diff === -1) return 'دیروز';
            if (diff > -7) return TimeSync.weekdayName(m.created_at); // ۲ تا ۶ روزِ قبل: فقط اسمِ روز
            return TimeSync.formatJalaliWithWeekday(m.created_at); // ۷+ روزِ قبل: روز + تاریخِ کامل
        }
        function buildChatDateDivider(m) {
            var d = document.createElement('div');
            d.className = 'chat-date-divider';
            d.setAttribute('data-date-key', chatDateKey(m));
            d.innerHTML = '<span>' + esc(chatDateDividerLabel(m)) + '</span>';
            return d;
        }

        function appendMessages(msgs, scrollBottom, prepend) {
            var el = document.getElementById('chatMessages');
            var prevKeyInPrependBatch = null; // فقط برایِ حالتِ prepend استفاده می‌شود
            var frag = prepend ? document.createDocumentFragment() : null;
            var prependLinkRefs = [];
            msgs.forEach(m => {
                lastMessageId = Math.max(lastMessageId, m.id);
                if (!oldestMessageId || m.id < oldestMessageId) oldestMessageId = m.id;
                // 🔒 اگه این پیام از قبل رندر شده (مثلاً چون هم پول‌کردنِ دوره‌ایِ
                // ۴ثانیه‌ای و هم پول‌کردنِ فوریِ بعدِ ارسال/هدایت، هم‌زمان با یک
                // after_idِ یکسان به سرور رسیدن و هردو همون پیامِ تازه رو گرفتن)،
                // یک ردیفِ تکراری نساز — این دقیقاً همون چیزی بود که باعث می‌شد
                // یک پیام دوبار (یا یک هدایت، دوبار) روی صفحه دیده بشه
                if (el.querySelector('.chat-bubble-row[data-message-id="' + m.id + '"]')) return;
                // خطِ «پیام‌های خوانده‌نشده» — درست بالایِ اولین پیامِ خوانده‌نشده، فقط
                // یک‌بار (unreadDividerBeforeId بلافاصله صفر می‌شود تا در پیام‌های
                // بعدیِ همین دسته یا در after_id/prependِ بعدی دوباره درج نشود)
                var dividerRow = null;
                if (!prepend && unreadDividerBeforeId && m.id === unreadDividerBeforeId) {
                    dividerRow = document.createElement('div');
                    dividerRow.className = 'chat-unread-divider';
                    dividerRow.innerHTML = '<span>پیام‌های خوانده‌نشده</span>';
                    unreadDividerBeforeId = 0;
                }

                var row = document.createElement('div');
                row.className = 'chat-bubble-row ' + (m.is_own ? 'own' : 'other');
                row.setAttribute('data-message-id', m.id);

                var imagesHtml = '',
                    filesHtml = '';
                (m.attachments || []).forEach(a => {
                    var url = '../api/chat/download.php?id=' + a.id + '&view=1';
                    if (a.is_image) {
                        imagesHtml += '<img src="' + url + '" alt="' + esc(a.original_name) + '" onclick="window.open(\'' + url + '\',\'_blank\')">';
                    } else {
                        var dlUrl = '../api/chat/download.php?id=' + a.id;
                        filesHtml += '<div class="chat-bubble-file" onclick="window.open(\'' + dlUrl + '\',\'_blank\')">' +
                            '<i class="bi bi-file-earmark"></i><span>' + esc(a.original_name) + '</span></div>';
                    }
                });

                // ارجاع به کار/تیکت (#task:ID یا #ticket:ID داخلِ متنِ پیام) —
                // متن جایگزینِ یک تگِ کوچکِ قابل‌کلیک می‌شه، و زیرِ پیام یک
                // کارتِ پیش‌نمایش (عنوان/وضعیت/پیوست‌ها) به‌صورتِ async لود می‌شه
                var linkRefs = extractLinkRefs(m.message || '');
                var linkRefsHtml = '';
                if (linkRefs.length) {
                    linkRefsHtml = '<div class="chat-linkref-cards">' +
                        linkRefs.map(r => '<div class="chat-linkref-card loading" data-type="' + r.type + '" data-id="' + r.id + '"><i class="bi bi-hourglass-split"></i> در حال بارگذاری...</div>').join('') +
                        '</div>';
                }

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
                    ? '<div class="chat-bubble-forward-label"><i class="bi bi-arrow-return-right"></i> هدایت شده از ' + esc(m.forwarded_from) + '</div>'
                    : '';

                // رسیدِ خوانده‌شدن: فقط برایِ پیام‌هایِ خودم — با تیکِ ✓ (ارسال‌شده) شروع می‌شود
                // و با هر بار poll (پایینِ فایل، renderReadReceipts) به‌روز می‌شود
                var ticksHtml = m.is_own
                    ? '<span class="chat-bubble-ticks" data-mid="' + m.id + '"><i class="bi bi-check"></i></span>'
                    : '';

                // آیکنِ سنجاق کنارِ ساعت — فقط اگه pinnedMessage تا همین لحظه
                // لود شده باشه؛ اگه دیرتر لود بشه یا پین/آن‌پین حین بازبودنِ
                // چت اتفاق بیفته، updatePinnedIconInMessages() این رو sync می‌کنه
                var pinIconHtml = (pinnedMessage && pinnedMessage.id === m.id)
                    ? '<i class="bi bi-pin-angle-fill chat-bubble-pin-icon" title="پیامِ سنجاق‌شده"></i>'
                    : '';

                row.innerHTML =
                    '<div class="chat-bubble">' +
                    senderLabel +
                    forwardLabel +
                    quoteHtml +
                    (imagesHtml ? '<div class="chat-bubble-images">' + imagesHtml + '</div>' : '') +
                    filesHtml +
                    (m.message ? '<div class="chat-bubble-text">' + highlightLinkRefs(highlightMentions(esc(m.message), activeGroupMembers)).replace(/\n/g, '<br>') + '</div>' : '') +
                    linkRefsHtml +
                    '<div class="chat-bubble-time">' + pinIconHtml + esc(m.time_jalali) + editedTag + ticksHtml + '</div>' +
                    reactionsHtml(m.id, m.reactions) +
                    '</div>';

                // راست‌کلیک برای همه‌ی پیام‌ها فعال است (پاسخ برای هر پیامی ممکن است)؛
                // ویرایش/حذف فقط داخلِ منو برای پیام‌های خودم نمایش داده می‌شود
                row.setAttribute('data-message-text', m.message || '');
                row.setAttribute('data-can-edit', (m.is_own && m.message) ? '1' : '0');
                row.setAttribute('data-can-delete', m.is_own ? '1' : '0');
                row.setAttribute('data-sender-name', m.user_name);
                var dateKey = chatDateKey(m);
                row.setAttribute('data-date-key', dateKey);
                row.addEventListener('contextmenu', function (e) {
                    e.preventDefault();
                    openChatCtxMenu(e.clientX, e.clientY, row);
                });

                if (prepend) {
                    if (dateKey && dateKey !== prevKeyInPrependBatch) {
                        frag.appendChild(buildChatDateDivider(m));
                        prevKeyInPrependBatch = dateKey;
                    }
                    frag.appendChild(row);
                    if (linkRefs.length) prependLinkRefs.push([row, linkRefs]);
                } else {
                    if (dateKey && dateKey !== lastAppendedDateKey) {
                        el.appendChild(buildChatDateDivider(m));
                        lastAppendedDateKey = dateKey;
                    }
                    if (dividerRow) el.appendChild(dividerRow);
                    el.appendChild(row);
                    if (linkRefs.length) loadLinkRefPreviews(row, linkRefs);
                }
                if (chatMsgObserver) chatMsgObserver.observe(row);
            });
            if (prepend) {
                if (frag.childNodes.length) el.insertBefore(frag, el.firstChild);
                prependLinkRefs.forEach(function (x) { loadLinkRefPreviews(x[0], x[1]); });
            }
            if (scrollBottom) el.scrollTop = el.scrollHeight;
        }

        // ── خواندنِ تدریجی: وقتی یک ردیفِ پیام واقعاً روی صفحه دیده می‌شود (نه صرفاً
        // لود شده)، id‌اش کاندیدِ «تا اینجا خوانده شد» می‌شود. با debounce و مقایسه با
        // آخرین idِ ارسال‌شده، فقط وقتی واقعاً جلوتر رفته باشیم mark-read.php صدا زده می‌شود ──
        function onMessageRowVisible(entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var mid = parseInt(entry.target.getAttribute('data-message-id'), 10);
                if (mid > readTrackMaxSeenId) readTrackMaxSeenId = mid;
            });
            if (readTrackMaxSeenId > readTrackSentUpToId) scheduleReadTrackSend();
        }

        function scheduleReadTrackSend() {
            clearTimeout(readTrackDebounce);
            readTrackDebounce = setTimeout(function () {
                var convId = activeConversationId;
                var upToId = readTrackMaxSeenId;
                if (!convId || upToId <= readTrackSentUpToId) return;
                fetch('../api/chat/mark-read.php', {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + authToken,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ conversation_id: convId, up_to_id: upToId })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            readTrackSentUpToId = upToId;
                            loadConversations(); // بجِ گفتگو در سایدبار بر همین اساس کم می‌شود
                        }
                    })
                    .catch(function () {});
            }, 700);
        }

        // درخواستِ ۴۰ پیامِ قدیمی‌ترِ بعدی و افزودنِ آن‌ها به ابتدای لیست،
        // با حفظِ موقعیتِ اسکرول (کاربر همان‌جا که بود می‌ماند).
        function prependOlderMessages() {
            if (loadingOlder || !hasMoreOlder || !activeConversationId || !oldestMessageId) return;
            loadingOlder = true;
            var el = document.getElementById('chatMessages');
            var prevH = el.scrollHeight, prevTop = el.scrollTop;
            fetch('../api/chat/messages.php?conversation_id=' + activeConversationId +
                    '&before_id=' + oldestMessageId + '&limit=40', {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.messages && data.messages.length) {
                        appendMessages(data.messages, false, true);   // prepend
                        hasMoreOlder = !!data.has_more;
                        // لنگرِ اسکرول: به همان پیامی که کاربر می‌دید برگرد
                        el.scrollTop = prevTop + (el.scrollHeight - prevH);
                    } else {
                        hasMoreOlder = false;
                    }
                })
                .catch(function () { /* شبکه — دفعهٔ بعد دوباره تلاش می‌شود */ })
                .finally(function () { loadingOlder = false; });
        }

        // highlight=true فقط برایِ کلیک روی بنرِ پیامِ سنجاق‌شده صدا زده می‌شه —
        // ریپلای و جهشِ سرچِ بینِ‌گفتگویی (که از همین تابع استفاده می‌کنن) عمداً
        // بدونِ هاله می‌مونن، چون قبلاً صراحتاً درخواستِ حذفِ فلش برایِ اونا شده بود
        function scrollToOriginalMessage(messageId, highlight) {
            var row = document.querySelector('.chat-bubble-row[data-message-id="' + messageId + '"]');
            if (!row) return;
            row.scrollIntoView({ block: 'center', behavior: 'smooth' });
            if (highlight) {
                row.classList.remove('pinned-jump-highlight');
                void row.offsetWidth; // reflow — تا کلیکِ پشتِ‌سرِهم روی بنر، انیمیشن رو از اول اجرا کنه
                row.classList.add('pinned-jump-highlight');
                setTimeout(function () { row.classList.remove('pinned-jump-highlight'); }, 3000);
            }
        }

        // ─────────────── منویِ راست‌کلیک (ویرایش/حذف) ───────────────
        var ctxMenuTargetRow = null;

        function openChatCtxMenu(x, y, row) {
            ctxMenuTargetRow = row;
            var menu = document.getElementById('chatCtxMenu');
            var canEdit = row.getAttribute('data-can-edit') === '1';
            // خودِ پیام یا — در گروه — سازنده‌ی گروه که اجازه دارد پیامِ هرکسی را حذف کند.
            // این چک عمداً همینجا (زمانِ بازکردنِ منو) انجام می‌شود نه زمانِ رندرِ ردیف،
            // چون activeGroupIsCreator با یک fetch جداگانه (loadActiveGroupMembers) پر می‌شود
            // و ممکن است هنگامِ رندرِ اولین پیام‌ها هنوز آماده نباشد.
            var canDelete = row.getAttribute('data-can-delete') === '1' ||
                (activeConversationType !== 'direct' && activeGroupIsCreator);
            var hasText = !!(row.getAttribute('data-message-text') || '').trim();
            document.getElementById('chatCtxEditItem').style.display = canEdit ? 'flex' : 'none';
            document.getElementById('chatCtxDeleteItem').style.display = canDelete ? 'flex' : 'none';
            document.getElementById('chatCtxCopyItem').style.display = hasText ? 'flex' : 'none';
            document.getElementById('chatCtxTaskItem').style.display = hasText ? 'flex' : 'none';

            var messageId = parseInt(row.getAttribute('data-message-id'), 10);
            var isPinned = pinnedMessage && pinnedMessage.id === messageId;
            document.getElementById('chatCtxPinItem').style.display = pinnedCanManage ? 'flex' : 'none';
            document.getElementById('chatCtxPinLabel').textContent = isPinned ? 'برداشتن سنجاق' : 'سنجاق‌کردن';

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

        // ─────────────── منویِ راست‌کلیکِ ردیفِ گفتگو در لیست (حذفِ گفتگو) ───────────────
        var convCtxTargetId = null;

        function openConvCtxMenu(e, conversationId) {
            e.preventDefault();
            convCtxTargetId = conversationId;
            var menu = document.getElementById('chatConvCtxMenu');
            menu.classList.add('show');
            var menuW = menu.offsetWidth,
                menuH = menu.offsetHeight;
            var left = Math.min(e.clientX, window.innerWidth - menuW - 8);
            var top = Math.min(e.clientY, window.innerHeight - menuH - 8);
            menu.style.left = left + 'px';
            menu.style.top = top + 'px';
            return false;
        }

        function closeConvCtxMenu() {
            document.getElementById('chatConvCtxMenu').classList.remove('show');
            convCtxTargetId = null;
        }

        document.addEventListener('click', closeConvCtxMenu);
        document.addEventListener('scroll', closeConvCtxMenu, true);

        function deleteConversationFromCtxMenu() {
            var conversationId = convCtxTargetId;
            closeConvCtxMenu();
            if (!conversationId) return;
            uiConfirm(
                'این گفتگو فقط برایِ شما حذف می‌شود؛ طرفِ مقابل هیچ تغییری نمی‌بیند و اگه بعداً پیامِ جدیدی بفرسته، دوباره توی لیست ظاهر می‌شه. ادامه بدیم؟',
                function () {
                    fetch('../api/chat/delete-conversation.php', {
                            method: 'POST',
                            headers: { 'Authorization': 'Bearer ' + authToken, 'Content-Type': 'application/json' },
                            body: JSON.stringify({ conversation_id: conversationId })
                        })
                        .then(r => r.json())
                        .then(function (data) {
                            if (data.success) {
                                conversations = conversations.filter(c => c.conversation_id !== conversationId);
                                renderConversationList();
                                if (activeConversationId === conversationId) exitActiveConversation();
                                showToast('گفتگو حذف شد', 'success');
                            } else {
                                showToast(data.message || 'خطا در حذفِ گفتگو', 'error');
                            }
                        })
                        .catch(function () { showToast('خطا در ارتباط با سرور', 'error'); });
                },
                { danger: true, yesText: 'بله، حذف شود', noText: 'انصراف' }
            );
        }

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

        // ─────────────── کپیِ متنِ پیام ───────────────
        function copyFromCtxMenu() {
            if (!ctxMenuTargetRow) return;
            var text = ctxMenuTargetRow.getAttribute('data-message-text') || '';
            closeChatCtxMenu();
            if (!text) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text)
                    .then(() => showToast('متن کپی شد', 'success'))
                    .catch(() => showToast('کپی ناموفق بود', 'error'));
            } else {
                // راهِ‌فرار برای مرورگرهایِ بدونِ Clipboard API (مثلاً بافرِ non-HTTPS)
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try {
                    document.execCommand('copy');
                    showToast('متن کپی شد', 'success');
                } catch (e) {
                    showToast('کپی ناموفق بود', 'error');
                }
                document.body.removeChild(ta);
            }
        }

        // ─────────────── تعریفِ کار از رویِ یک پیام ───────────────
        var quickTaskModalInst = null;

        function taskFromCtxMenu() {
            if (!ctxMenuTargetRow) return;
            var text = ctxMenuTargetRow.getAttribute('data-message-text') || '';
            closeChatCtxMenu();

            document.getElementById('quickTaskTitle').value = '';
            document.getElementById('quickTaskDescription').value = text;
            document.getElementById('quickTaskDueDate').value = '';
            document.getElementById('quickTaskDueDate').removeAttribute('data-date');
            document.getElementById('quickTaskAssigneeMe').checked = true;

            // «مخاطبِ چت» فقط تویِ گفتگویِ مستقیم معنی داره (تویِ گروه یک نفرِ
            // مشخص به‌عنوانِ «طرفِ مقابل» وجود نداره)
            var conv = conversations.find(c => c.conversation_id === activeConversationId);
            var assigneeRow = document.getElementById('quickTaskAssigneeRow');
            if (activeConversationType === 'direct' && conv && conv.other_user_id) {
                assigneeRow.style.display = 'block';
                document.getElementById('quickTaskAssigneeOtherLabel').textContent = activeConversationTitle;
                document.getElementById('quickTaskAssigneeOther').setAttribute('data-user-id', conv.other_user_id);
            } else {
                assigneeRow.style.display = 'none';
            }

            if (!quickTaskModalInst) {
                quickTaskModalInst = new bootstrap.Modal(document.getElementById('quickTaskModal'));
                document.getElementById('quickTaskModal').addEventListener('shown.bs.modal', function () {
                    document.getElementById('quickTaskTitle').focus();
                    // پیش‌فرضِ موعد = امروز (بدونِ تأییدِ جمعه/تعطیلی که برایِ انتخابِ دستی هست)
                    var wrap = document.getElementById('quickTaskDueDateWrap');
                    if (wrap.datepickerInstance) {
                        var today = wrap.datepickerInstance.gregorianToJalali(new Date());
                        wrap.datepickerInstance.selectDate(today.year, today.month, today.day, true);
                    }
                });
            }
            quickTaskModalInst.show();
        }

        // لینکِ «تکمیلِ اطلاعات» — همون عنوان/توضیحات/موعدی که تا این لحظه تویِ
        // مودال وارد شده رو به‌عنوانِ پیش‌پرشده به create-task.php منتقل می‌کنه
        function selectedQuickTaskAssigneeId() {
            var otherRadio = document.getElementById('quickTaskAssigneeOther');
            if (otherRadio && otherRadio.checked) {
                return otherRadio.getAttribute('data-user-id');
            }
            return null; // یعنی خودم — سرور به‌طورِ پیش‌فرض همینو در نظر می‌گیره
        }

        function goToFullCreateTask(ev) {
            ev.preventDefault();
            var params = new URLSearchParams();
            var title = document.getElementById('quickTaskTitle').value.trim();
            var description = document.getElementById('quickTaskDescription').value.trim();
            var dueDate = document.getElementById('quickTaskDueDate').getAttribute('data-date');
            var assigneeId = selectedQuickTaskAssigneeId();
            if (title) params.set('title', title);
            if (description) params.set('description', description);
            if (dueDate) params.set('due_date', dueDate);
            if (assigneeId) params.set('assignee_id', assigneeId);
            window.location.href = 'create-task.php?' + params.toString();
        }
        document.getElementById('quickTaskCompleteLink').addEventListener('click', goToFullCreateTask);

        async function submitQuickTask() {
            var title = document.getElementById('quickTaskTitle').value.trim();
            if (!title) {
                showToast('عنوان کار الزامی است', 'warning');
                document.getElementById('quickTaskTitle').focus();
                return;
            }
            var description = document.getElementById('quickTaskDescription').value.trim();
            var dueDate = document.getElementById('quickTaskDueDate').getAttribute('data-date') || null;
            var assigneeId = selectedQuickTaskAssigneeId();

            var btn = document.getElementById('quickTaskSubmitBtn');
            btn.disabled = true;
            try {
                var payload = {
                    title: title,
                    description: description,
                    task_type: 'periodic',
                    due_date: dueDate
                };
                if (assigneeId) payload.assignee_id = parseInt(assigneeId, 10);
                var res = await fetch('../api/tasks/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                    body: JSON.stringify(payload)
                });
                var data = await res.json();
                btn.disabled = false;
                if (data.success) {
                    showToast('کار ایجاد شد', 'success');
                    quickTaskModalInst.hide();
                } else {
                    showToast(data.message || 'خطا در ایجاد کار', 'error');
                }
            } catch (e) {
                btn.disabled = false;
                showToast('خطا در ارتباط با سرور', 'error');
            }
        }

        // ─────────────── هدایت پیام ───────────────
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
                        showToast('پیام هدایت شد', 'success');
                        if (conversationId === activeConversationId) {
                            pollForUpdates(true);
                        }
                    } else {
                        showToast(data.message || 'خطا در هدایت پیام', 'error');
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
            var wasEditing = editingMessageId !== null;
            editingMessageId = null;
            var input = document.getElementById('chatComposerInput');
            // فقط وقتی واقعاً در حالِ ویرایش بودیم کادر را خالی کن؛ وگرنه متنی که
            // کاربر تازه تایپ کرده (و هنوز نفرستاده) با شروعِ «پاسخ» پاک می‌شد.
            if (wasEditing) {
                input.value = '';
                input.style.height = 'auto';
            }
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

        // ─────────────── پیوستِ فایل/عکس (مودالِ ارسال با توضیح) ───────────────
        var fileCaptionModalInst = null;

        function addPendingFiles(files) {
            for (var i = 0; i < files.length; i++) pendingFiles.push(files[i]);
            openFileCaptionModal();
        }

        function openFileCaptionModal() {
            if (!pendingFiles.length) return;
            if (!fileCaptionModalInst) {
                fileCaptionModalInst = new bootstrap.Modal(document.getElementById('fileCaptionModal'));
            }
            renderFileCaptionPreviews();
            var captionEl = document.getElementById('fileCaptionInput');
            captionEl.value = '';
            captionEl.style.height = 'auto';
            fileCaptionModalInst.show();
            setTimeout(function() {
                var el = document.getElementById('fileCaptionInput');
                if (el) el.focus();
            }, 300);
        }

        function renderFileCaptionPreviews() {
            var el = document.getElementById('fileCaptionPreviewList');
            el.innerHTML = pendingFiles.map(function(f, i) {
                var isImage = f.type && f.type.indexOf('image/') === 0;
                var inner = isImage
                    ? '<img src="' + URL.createObjectURL(f) + '" alt="">'
                    : '<i class="bi bi-file-earmark"></i><div class="chat-fc-file-name">' + esc(f.name) + '</div>';
                return '<div class="chat-fc-preview-item' + (isImage ? '' : ' file') + '">' + inner +
                    '<div class="chat-fc-remove" onclick="removeFileCaptionItem(' + i + ')"><i class="bi bi-x-lg"></i></div></div>';
            }).join('');
        }

        function removeFileCaptionItem(idx) {
            pendingFiles.splice(idx, 1);
            if (!pendingFiles.length) {
                cancelFileCaptionModal();
                return;
            }
            renderFileCaptionPreviews();
        }

        function cancelFileCaptionModal() {
            pendingFiles = [];
            if (fileCaptionModalInst) fileCaptionModalInst.hide();
        }

        function sendFilesWithCaption() {
            if (!activeConversationId || !pendingFiles.length) return;
            var caption = document.getElementById('fileCaptionInput').value.trim();
            var btn = document.getElementById('fileCaptionSendBtn');
            btn.disabled = true;

            var fd = new FormData();
            fd.append('conversation_id', activeConversationId);
            fd.append('message', caption);
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
                        pendingFiles = [];
                        cancelReplyMessage();
                        if (fileCaptionModalInst) fileCaptionModalInst.hide();
                        pollForUpdates(true);
                    } else {
                        showToast(data.message || 'خطا در ارسال فایل', 'error');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    showToast('خطا در ارتباط با سرور', 'error');
                });
        }

        // ─────────────── ارسالِ پیام (فقط متن — پیوست از مسیرِ مودالِ بالا می‌ره) ───────────────
        function sendChatMessage() {
            if (!activeConversationId) return;
            var input = document.getElementById('chatComposerInput');
            var text = input.value.trim();

            if (editingMessageId) {
                submitEditMessage(text);
                return;
            }

            if (!text) return;

            var btn = document.getElementById('chatSendBtn');
            // ⚠️ کلیک روی دکمه‌ی غیرفعال خودش رویداد نمی‌سازه، ولی کلیدِ Enter از
            // این چک عبور نمی‌کنه — اگه کاربر Enter رو دوبار پشتِ‌سرِهم بزنه (یا
            // به‌خاطرِ auto-repeatِ صفحه‌کلید کمی نگه‌داره)، قبل از این‌که پاسخِ
            // درخواستِ اول برسه و متن پاک بشه، همون متن دوباره ارسال می‌شد —
            // این گارد جلویِ ارسالِ تکراری رو می‌گیره
            if (btn.disabled) return;
            btn.disabled = true;

            var fd = new FormData();
            fd.append('conversation_id', activeConversationId);
            fd.append('message', text);
            if (replyingToMessageId) fd.append('reply_to_message_id', replyingToMessageId);

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
                        clearComposerDraft(activeConversationId);
                        cancelReplyMessage();
                        pollForUpdates(true);
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
                            // ‍‍`.chat-bubble-text` (نه `div:first-child`) — چون اولین
                            // فرزندِ حباب می‌تونست عکس/فایلِ پیوست‌شده باشه، نه متن؛
                            // با first-child، متنِ ویرایش‌شده جایِ عکس می‌نشست و عکس
                            // پاک می‌شد، درحالی‌که متنِ قدیمی هم دست‌نخورده می‌موند
                            var textEl = row.querySelector('.chat-bubble-text');
                            if (textEl) textEl.innerHTML = highlightLinkRefs(highlightMentions(esc(data.message), activeGroupMembers)).replace(/\n/g, '<br>');
                            var timeEl = row.querySelector('.chat-bubble-time');
                            if (timeEl && !timeEl.querySelector('.chat-bubble-edited-tag')) {
                                timeEl.insertAdjacentHTML('beforeend', '<span class="chat-bubble-edited-tag">(ویرایش‌شده)</span>');
                            }
                        }
                        cancelEditMessage();
                        // متنِ درحالِ‌ویرایش، به‌خاطرِ ذخیره‌ی خودکارِ پیش‌نویسِ کامپوزر
                        // (debounce ۳۰۰ms روی رویدادِ input که beginEditMessage هم
                        // شلیکش می‌کنه)، ممکنه قبلِ ارسال یک‌بار به‌عنوانِ پیش‌نویس در
                        // localStorage ذخیره شده باشه — بعدِ ارسالِ موفق باید پاک بشه،
                        // وگرنه توی لیستِ گفتگوها به‌اشتباه «پیش‌نویس» نشون داده می‌شه
                        clearComposerDraft(activeConversationId);
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
        // ─────────────── پیش‌نویسِ پیام (به‌ازایِ هر گفتگو، در localStorage) ───────────────
        function draftKey(convId) {
            return 'chat_draft_' + convId;
        }

        function saveComposerDraft() {
            if (!activeConversationId) return;
            var input = document.getElementById('chatComposerInput');
            if (!input) return;
            var val = input.value;
            if (val && val.trim() !== '') {
                localStorage.setItem(draftKey(activeConversationId), val);
            } else {
                localStorage.removeItem(draftKey(activeConversationId));
            }
        }

        function restoreComposerDraft(convId) {
            var input = document.getElementById('chatComposerInput');
            if (!input) return;
            input.value = localStorage.getItem(draftKey(convId)) || '';
            autoGrowComposer(input);
        }

        function clearComposerDraft(convId) {
            localStorage.removeItem(draftKey(convId));
        }

        // ─────────────── میانبرهایِ صفحه‌کلید (فقط دسکتاپ) ───────────────
        function navigateConversationList(direction) {
            var term = (document.getElementById('convSearchInput').value || '').trim().toLowerCase();
            var list = conversations.filter(c => !term || c.title.toLowerCase().includes(term));
            if (!list.length) return;
            var idx = list.findIndex(c => c.conversation_id === activeConversationId);
            var nextIdx = idx === -1 ? 0 : (idx + direction + list.length) % list.length;
            openConversation(list[nextIdx].conversation_id);
        }

        document.addEventListener('keydown', function(e) {
            // ⚠️ Ctrl/Cmd+K مالِ آدرس‌بارِ خودِ مرورگرهاست (کروم/اج/فایرفاکس) و
            // صفحه‌یِ وب هیچ‌وقت نمی‌تونه با preventDefault جلوشو بگیره — برایِ
            // همین از یه ترکیبِ آزادِ دیگه (Ctrl+/) استفاده می‌کنیم
            if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && e.key === '/') {
                e.preventDefault();
                var searchInput = document.getElementById('convSearchInput');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
                return;
            }

            // Alt+↑/↓ → حرکت بینِ گفتگوها بدونِ دست‌زدن به موس
            if (e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
                e.preventDefault();
                navigateConversationList(e.key === 'ArrowDown' ? 1 : -1);
                return;
            }

            if (e.key === 'Escape') {
                var msgSearchBar = document.getElementById('chatMsgSearchBar');
                if (msgSearchBar && msgSearchBar.classList.contains('show')) {
                    closeMsgSearch();
                    return;
                }
                if (typeof replyingToMessageId !== 'undefined' && replyingToMessageId) {
                    cancelReplyMessage();
                    return;
                }
                if (activeConversationId) {
                    exitActiveConversation();
                    return;
                }
            }
        });

        function exitActiveConversation() {
            if (!activeConversationId) return;
            saveComposerDraft();
            activeConversationId = null;
            document.getElementById('chatActiveView').style.display = 'none';
            document.getElementById('chatPlaceholder').style.display = 'flex';
            document.getElementById('chatSidebar').classList.remove('hide-mobile');
            document.getElementById('chatMain').classList.add('hide-mobile');
            renderConversationList();
        }

        // ─────────────── گالریِ فایل/عکسِ مشترکِ گفتگو ───────────────
        var mediaGalleryModalInst = null;

        function fmtFileSize(bytes) {
            bytes = Number(bytes) || 0;
            if (bytes < 1024) return toFa(bytes) + ' B';
            if (bytes < 1024 * 1024) return toFa((bytes / 1024).toFixed(1)) + ' KB';
            return toFa((bytes / (1024 * 1024)).toFixed(1)) + ' MB';
        }

        function openMediaGallery() {
            if (!activeConversationId) return;
            if (!mediaGalleryModalInst) {
                mediaGalleryModalInst = new bootstrap.Modal(document.getElementById('mediaGalleryModal'));
            }
            document.getElementById('mediaGalleryBody').innerHTML = '<div class="chat-empty-list">در حال بارگذاری...</div>';
            mediaGalleryModalInst.show();

            fetch('../api/chat/media.php?conversation_id=' + activeConversationId, {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                })
                .then(r => r.json())
                .then(data => renderMediaGallery(data))
                .catch(() => {
                    document.getElementById('mediaGalleryBody').innerHTML =
                        '<div class="chat-empty-list">خطا در ارتباط با سرور</div>';
                });
        }

        function renderMediaGallery(data) {
            var body = document.getElementById('mediaGalleryBody');
            if (!data.success) {
                body.innerHTML = '<div class="chat-empty-list">' + esc(data.message || 'خطا در بارگذاری') + '</div>';
                return;
            }
            if (!data.files.length) {
                body.innerHTML = '<div class="chat-empty-list">هنوز فایل/عکسی در این گفتگو ردوبدل نشده</div>';
                return;
            }

            var images = data.files.filter(f => f.is_image);
            var files = data.files.filter(f => !f.is_image);

            var html = '';
            if (images.length) {
                html += '<div class="chat-media-grid">';
                images.forEach(f => {
                    var url = '../api/chat/download.php?id=' + f.id + '&view=1';
                    html += '<div class="chat-media-grid-item" onclick="window.open(\'' + url + '\',\'_blank\')" title="' + esc(f.original_name) + '">' +
                        '<img src="' + url + '" alt="' + esc(f.original_name) + '"></div>';
                });
                html += '</div>';
            }
            if (files.length) {
                html += '<div class="chat-media-file-list">';
                files.forEach(f => {
                    var dlUrl = '../api/chat/download.php?id=' + f.id;
                    html += '<div class="chat-media-file-row" onclick="window.open(\'' + dlUrl + '\',\'_blank\')">' +
                        '<i class="bi bi-file-earmark"></i>' +
                        '<div class="chat-media-file-info">' +
                        '<div class="chat-media-file-name">' + esc(f.original_name) + '</div>' +
                        '<div class="chat-media-file-meta">' + esc(f.uploader_name) + ' — ' + fmtFileSize(f.file_size) + '</div>' +
                        '</div></div>';
                });
                html += '</div>';
            }
            body.innerHTML = html;
        }

        // ─────────────── اعلانِ دسکتاپ (Browser Notification API) ───────────────
        var __convSnapshot = {}; // conversation_id -> { unread_count, last_message }
        var __convSnapshotReady = false; // اولین بار نباید همه‌چیز «جدید» حساب بشه

        function updateDesktopNotifIcon() {
            var btn = document.getElementById('chatDesktopNotifBtn');
            var icon = document.getElementById('chatDesktopNotifIcon');
            if (!btn || !icon) return;
            if (!('Notification' in window)) {
                btn.style.display = 'none';
                return;
            }
            btn.classList.remove('chat-notif-on', 'chat-notif-blocked');
            if (Notification.permission === 'granted') {
                icon.className = 'bi bi-bell-fill';
                btn.classList.add('chat-notif-on');
                btn.title = 'اعلان دسکتاپ فعاله';
            } else if (Notification.permission === 'denied') {
                icon.className = 'bi bi-bell-slash';
                btn.classList.add('chat-notif-blocked');
                btn.title = 'اعلان دسکتاپ مسدود شده — برای راهنمایی فعال‌سازی کلیک کنید';
            } else {
                icon.className = 'bi bi-bell';
                btn.title = 'فعال‌سازی اعلان دسکتاپ';
            }
        }

        function handleDesktopNotifClick() {
            if (!('Notification' in window)) {
                showToast('مرورگر شما از اعلان دسکتاپ پشتیبانی نمی‌کند', 'warning');
                return;
            }
            if (Notification.permission === 'granted') {
                showToast('اعلان دسکتاپ از قبل فعاله', 'info');
                return;
            }
            if (Notification.permission === 'denied') {
                // ⚠️ مرورگرها به‌عمد اجازه نمی‌دن بعدِ ردکردنِ کاربر، دوباره از راهِ کد
                // این پرسش تکرار بشه — تنها راه، تنظیماتِ خودِ مرورگره؛ همینو
                // به‌طورِ واضح توضیح می‌دیم تا کاربر گیج نشه چرا اتفاقی نمی‌افته
                showToast(
                    'اعلان قبلا مسدود شده و مرورگر اجازه نمی‌ده دوباره از داخل سایت بپرسیم. برای فعال‌سازی دستی: روی آیکن قفل/اطلاعات کنار آدرس سایت (بالای مرورگر) بزنید ← «اعلان‌ها»/Notifications را Allow کنید ← صفحه را رفرش کنید.',
                    'warning',
                    { duration: 15000 }
                );
                return;
            }
            Notification.requestPermission().then(function(result) {
                updateDesktopNotifIcon();
                if (result === 'granted') {
                    showToast('اعلان دسکتاپ فعال شد', 'success');
                    new Notification('یکتا همراهان ملک', { body: 'اعلان دسکتاپ با موفقیت فعال شد ✅', silent: true });
                } else if (result === 'denied') {
                    showToast('اجازهٔ اعلان داده نشد', 'warning');
                }
            });
        }

        function checkNewMessagesForDesktopNotif(convs) {
            if (!('Notification' in window) || Notification.permission !== 'granted') {
                __convSnapshotReady = true;
                __convSnapshot = {};
                convs.forEach(function(c) { __convSnapshot[c.conversation_id] = { unread_count: c.unread_count, last_message: c.last_message }; });
                return;
            }
            // فقط وقتی تب/پنجره در پس‌زمینه‌ست اعلانِ دسکتاپ بده — وگرنه کاربر
            // همین الان داره خودِ صفحه رو می‌بینه و نیازی به دوبل نیست
            var tabHidden = document.hidden || !document.hasFocus();

            var nextSnapshot = {};
            convs.forEach(function(c) {
                nextSnapshot[c.conversation_id] = { unread_count: c.unread_count, last_message: c.last_message };
                if (!__convSnapshotReady) return;
                var prev = __convSnapshot[c.conversation_id];
                var prevUnread = prev ? prev.unread_count : 0;
                var isNew = c.unread_count > prevUnread;
                if (isNew && tabHidden) {
                    try {
                        var n = new Notification(c.title || 'پیام جدید', {
                            body: (c.last_message || '').slice(0, 120),
                            tag: 'chat-conv-' + c.conversation_id, // اعلان‌هایِ پشتِ‌سرِهمِ همون گفتگو، جایگزینِ هم بشن نه تلنبار
                        });
                        n.onclick = function() {
                            window.focus();
                            openConversation(c.conversation_id);
                            n.close();
                        };
                    } catch (e) {}
                }
            });
            __convSnapshot = nextSnapshot;
            __convSnapshotReady = true;
        }

        // forceScroll=true فقط برایِ اقدامِ خودِ کاربر (فرستادن/هدایت پیام) —
        // چرخه‌یِ معمولیِ poll (هر ۴ ثانیه) این رو نمی‌فرسته، پس اگه کاربر
        // بالایِ تاریخچه‌ست و یکیِ دیگه پیام بده، خودکار پرتاب نمی‌شه پایین؛
        // فقط بجِ عددیِ رویِ دکمه‌ی «برو به آخرین پیام» بالا می‌ره
        function pollForUpdates(forceScroll) {
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
                            var shouldScroll = forceScroll || isChatNearBottom();
                            appendMessages(data.messages, shouldScroll);
                            if (!shouldScroll) {
                                chatNewMsgCount += data.messages.length;
                                updateChatNewMsgBadge();
                            }
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
                    updatePinnedIconInMessages();
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

        // آیکنِ سنجاقِ کنارِ ساعتِ پیام رو با pinnedMessageِ فعلی هماهنگ می‌کنه —
        // برایِ ردیف‌هایی که از قبلِ لودشدنِ pinnedMessage روی صفحه بودن، یا
        // وقتی پین/آن‌پین حینِ بازبودنِ همین چت اتفاق می‌افته
        function updatePinnedIconInMessages() {
            document.querySelectorAll('.chat-bubble-pin-icon').forEach(function (el) { el.remove(); });
            if (!pinnedMessage) return;
            var row = document.querySelector('.chat-bubble-row[data-message-id="' + pinnedMessage.id + '"]');
            if (!row) return; // هنوز لود نشده (مثلاً تویِ تاریخچه‌ی قدیمی‌تر) — صرفاً بصریه، مشکلی نیست
            var timeEl = row.querySelector('.chat-bubble-time');
            if (timeEl && !timeEl.querySelector('.chat-bubble-pin-icon')) {
                var icon = document.createElement('i');
                icon.className = 'bi bi-pin-angle-fill chat-bubble-pin-icon';
                icon.title = 'پیامِ سنجاق‌شده';
                timeEl.insertBefore(icon, timeEl.firstChild);
            }
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
                        showToast(data.message || 'خطا در سنجاق‌کردن پیام', 'error');
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
                        updatePinnedIconInMessages();
                    } else {
                        showToast(data.message || 'خطا در برداشتن سنجاق', 'error');
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
            document.getElementById('newChatModalTitle').textContent = 'شروع گفتگوی جدید';
            document.getElementById('newGroupTitleInput').style.display = 'none';
            document.getElementById('newGroupTitleInput').value = '';
            document.getElementById('newGroupFooter').style.display = 'none';
            document.getElementById('createGroupBtn').textContent = 'ایجاد گروه';
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
            document.getElementById('newChatModalTitle').textContent = mode === 'group' ? 'ساخت گروه جدید' : 'شروع گفتگوی جدید';
            document.getElementById('newGroupTitleInput').style.display = mode === 'group' ? 'block' : 'none';
            document.getElementById('newGroupFooter').style.display = mode === 'group' ? 'flex' : 'none';
            renderGroupChips();
            updateCreateGroupBtnState();
            searchChatUsers();
        }

        // از مودالِ «اطلاعاتِ گروه» صدا زده می‌شود — همان مودالِ گفتگویِ جدید را
        // در حالتِ «افزودنِ عضو به گروهِ موجود» دوباره‌استفاده می‌کند
        function openAddMembersMode() {
            closeGroupInfoDrawer();

            addMembersTargetConvId = activeConversationId;
            newChatMode = 'add-members';
            selectedGroupMembers = {};

            document.getElementById('newChatModeTabs').style.display = 'none';
            document.getElementById('newChatModalTitle').textContent = 'افزودن عضو به گروه';
            document.getElementById('newGroupTitleInput').style.display = 'none';
            document.getElementById('newGroupFooter').style.display = 'flex';
            document.getElementById('createGroupBtn').textContent = 'افزودن اعضا';
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
                        showToast(data.message || 'خطا در ساخت گروه', 'error');
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
                        showToast('اعضای جدید اضافه شدند', 'success');
                    } else {
                        showToast(data.message || 'خطا در افزودن عضو', 'error');
                    }
                });
        }

        // ─────────────── دراورِ اطلاعاتِ گروه ───────────────
        var groupInfoIsOwner = false;   // فقط سازنده‌ی گروه (created_by)
        var groupInfoCanManage = false; // سازنده یا هر مدیرِ (admin) گروه
        var groupInfoMyPermissions = []; // اختیاراتِ اختصاصیِ من در همین گروه — از group-members.php (my_permissions)
        var groupInfoAllPermissions = []; // کلِ کلیدهایِ اختیاراتِ قابل‌واگذاری (از سرور، برایِ مودالِ تنظیمِ اختیارات)
        var groupInfoMembersCache = []; // آخرین لیستِ اعضا — تا مودالِ اختیارات بدونِ فراخوانیِ دوباره، اطلاعاتِ عضو رو پیدا کنه
        var GROUP_PERMISSION_LABELS = {
            pin: 'سنجاق‌کردنِ پیام',
            add_member: 'افزودنِ عضو',
            remove_member: 'حذفِ عضو',
            avatar: 'تغییرِ عکسِ گروه'
        };

        function openGroupInfoDrawer() {
            if (!activeConversationId) return;
            var convId = activeConversationId;
            var conv = conversations.find(c => c.conversation_id === convId);

            document.getElementById('groupInfoMemberList').innerHTML = '<div class="chat-empty-list">در حال بارگذاری...</div>';
            document.getElementById('groupInfoAddBtn').style.display = 'none';
            document.getElementById('groupInfoMuteToggle').checked = !(conv && conv.is_muted);
            document.getElementById('groupInfoMediaGrid').innerHTML = '<div class="chat-empty-list">در حال بارگذاری...</div>';

            document.getElementById('groupInfoDrawerOverlay').classList.add('show');
            document.getElementById('groupInfoDrawer').classList.add('show');

            fetch('../api/chat/media.php?conversation_id=' + convId, {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                })
                .then(r => r.json())
                .then(data => {
                    if (convId !== activeConversationId) return;
                    var grid = document.getElementById('groupInfoMediaGrid');
                    var images = (data.success ? data.files : []).filter(f => f.is_image);
                    if (!images.length) {
                        grid.innerHTML = '<div class="chat-empty-list">هنوز عکسی ردوبدل نشده</div>';
                        return;
                    }
                    grid.innerHTML = images.map(function(f) {
                        var url = '../api/chat/download.php?id=' + f.id + '&view=1';
                        return '<div class="chat-media-grid-item" onclick="window.open(\'' + url + '\',\'_blank\')" title="' + esc(f.original_name) + '">' +
                            '<img src="' + url + '" alt="' + esc(f.original_name) + '"></div>';
                    }).join('');
                })
                .catch(function(err) { console.error('group media fetch error:', err); });

            refreshGroupInfoMembers(true);
        }

        // بارگذاریِ لیستِ اعضایِ دراورِ گروه. brandNew=true فقط زمانِ بازکردنِ
        // تازه‌یِ دراور (لیست از قبل خالی/بارگذاری‌شده) — تویِ آپدیت‌هایِ بعدی
        // (ارتقا/عزلِ مدیر، افزودن/حذفِ عضو، تغییرِ عکس) عمداً falseه تا لیست
        // یک لحظه مخفی/«در حال بارگذاری» نشه و فقط جایگزینِ بی‌فِلَش انجام بشه
        function refreshGroupInfoMembers(brandNew) {
            if (!activeConversationId) return;
            var convId = activeConversationId;
            fetch('../api/chat/group-members.php?conversation_id=' + convId, {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                })
                .then(r => r.json())
                .then(data => {
                    if (convId !== activeConversationId) return;
                    if (!data.success) {
                        if (brandNew) {
                            document.getElementById('groupInfoMemberList').innerHTML =
                                '<div class="chat-empty-list">' + esc(data.message || 'خطا در بارگذاری اعضا') + '</div>';
                        }
                        return;
                    }
                    document.getElementById('groupInfoTitle').textContent = activeConversationTitle + ' — ' + toFa(data.members.length) + ' عضو';
                    groupInfoIsOwner = !!data.is_owner;
                    groupInfoCanManage = !!data.can_manage;
                    groupInfoMyPermissions = data.my_permissions || [];
                    groupInfoAllPermissions = data.all_permissions || [];
                    groupInfoMembersCache = data.members || [];
                    document.getElementById('groupInfoAddBtn').style.display = groupInfoMyPermissions.indexOf('add_member') !== -1 ? 'block' : 'none';
                    document.getElementById('groupInfoAvatarWrap').innerHTML =
                        avatarHtml(data.group_title || activeConversationTitle, false, 'chat-group-avatar-big', data.group_avatar_url) +
                        (groupInfoMyPermissions.indexOf('avatar') !== -1 ? '<div class="chat-group-avatar-edit-badge"><i class="bi bi-camera-fill"></i></div>' : '');
                    document.getElementById('groupInfoMemberList').innerHTML = data.members.map(m => {
                        var isMe = myUserId && Number(m.id) === Number(myUserId);
                        var nameAttrs = isMe ? '' : ' onclick="openMemberDirectChat(' + m.id + ')" style="cursor:pointer;"';
                        var roleTag = m.is_owner
                            ? '<span class="chat-group-owner-tag">سازنده‌ی گروه</span>'
                            : (m.is_admin ? '<span class="chat-group-owner-tag">مدیر</span>' : '');
                        // ارتقا به مدیر: کارِ هر مدیری. عزل از مدیریت: فقط سازنده (تا مدیرها نتونن همدیگه رو عزل کنن)
                        var roleBtn = '';
                        if (!isMe && !m.is_owner) {
                            if (!m.is_admin && data.can_manage) {
                                roleBtn = '<button class="chat-group-member-role-btn" title="ارتقا به مدیر" onclick="promoteGroupMember(' + m.id + ')"><i class="bi bi-shield-plus"></i></button>';
                            } else if (m.is_admin && data.is_owner) {
                                roleBtn = '<button class="chat-group-member-role-btn" title="عزل از مدیریت" onclick="demoteGroupMember(' + m.id + ')"><i class="bi bi-shield-minus"></i></button>';
                            }
                        }
                        // تنظیمِ اختیاراتِ اختصاصی: فقط سازنده، فقط رویِ مدیرهایِ دیگه
                        var permBtn = (data.is_owner && m.is_admin && !m.is_owner)
                            ? '<button class="chat-group-member-role-btn" title="تنظیمِ اختیارات" onclick="openGroupMemberPermissionsModal(' + m.id + ')"><i class="bi bi-gear-fill"></i></button>'
                            : '';
                        // حذفِ عضو: مدیرِ دارایِ اختیارِ remove_member برایِ اعضایِ عادی؛ حذفِ یک مدیرِ دیگه فقط دستِ سازنده‌ست
                        var canRemove = !m.is_owner && (groupInfoMyPermissions.indexOf('remove_member') !== -1) && (!m.is_admin || data.is_owner);
                        return '<div class="chat-group-member-row">' +
                        '<div' + nameAttrs + '>' + avatarHtml(m.full_name, false, null, m.avatar_url) + '</div>' +
                        '<span class="chat-group-member-name"' + nameAttrs + '>' + esc(m.full_name) + ' ' +
                        roleTag +
                        '</span>' +
                        roleBtn +
                        permBtn +
                        (canRemove
                            ? '<button class="chat-group-member-remove" title="حذف عضو" onclick="removeGroupMember(' + m.id + ')"><i class="bi bi-x-lg"></i></button>'
                            : '') +
                        '</div>';
                    }).join('');
                })
                .catch(function() {
                    if (brandNew) {
                        document.getElementById('groupInfoMemberList').innerHTML =
                            '<div class="chat-empty-list">خطا در ارتباط با سرور</div>';
                    }
                });
        }

        function closeGroupInfoDrawer() {
            document.getElementById('groupInfoDrawerOverlay').classList.remove('show');
            document.getElementById('groupInfoDrawer').classList.remove('show');
        }

        function triggerGroupAvatarUpload() {
            if (groupInfoMyPermissions.indexOf('avatar') === -1) return;
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
                        showToast('عکس گروه بروزرسانی شد', 'success');
                        refreshGroupInfoMembers(false);
                        loadConversations();
                    } else {
                        showToast(data.message || 'خطا در آپلود عکس', 'error');
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
                        refreshGroupInfoMembers(false);
                        loadConversations();
                    } else {
                        showToast(data.message || 'خطا در حذف عضو', 'error');
                    }
                });
        }

        function setGroupMemberRole(userId, role, errorMessage) {
            if (!activeConversationId) return;
            fetch('../api/chat/set-member-role.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ conversation_id: activeConversationId, user_id: userId, role: role })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        openGroupInfoDrawer();
                        loadActiveGroupMembers(); // برای منشن و data-can-delete هم به‌روز بشه
                    } else {
                        showToast(data.message || errorMessage, 'error');
                    }
                });
        }

        // ارتقا به مدیر: اگه من سازنده‌ام، همون اول مودالِ انتخابِ اختیارات باز
        // می‌شه (تا انتخابِ اختیارات جزوِ خودِ عملِ ارتقا باشه، نه یک قدمِ
        // جداگانه‌ی بعدی)؛ اگه فقط مدیرِ عادی‌ام (نه سازنده)، طبقِ همون قاعده‌یِ
        // «تنظیمِ اختیارات فقط دستِ سازنده‌ست»، نمی‌تونم انتخاب کنم — همون
        // ارتقایِ مستقیم با اختیاراتِ پیش‌فرض (همه) انجام می‌شه
        function promoteGroupMember(userId) {
            if (groupInfoIsOwner) {
                openGroupMemberPermissionsModal(userId, 'promote');
                return;
            }
            setGroupMemberRole(userId, 'admin', 'خطا در ارتقای عضو');
        }

        function demoteGroupMember(userId) {
            setGroupMemberRole(userId, 'member', 'خطا در عزلِ مدیر');
        }

        // ─────────────── اختیاراتِ اختصاصیِ یک مدیر (فقط سازنده تنظیم می‌کنه) ───────────────
        var gmpModalInstance = null;
        var gmpTargetUserId = null;
        var gmpMode = 'edit'; // 'edit' = تنظیمِ اختیاراتِ مدیرِ موجود | 'promote' = ارتقا+انتخابِ اختیارات هم‌زمان

        function openGroupMemberPermissionsModal(userId, mode) {
            var m = groupInfoMembersCache.find(function (x) { return Number(x.id) === Number(userId); });
            if (!m) return;
            gmpMode = mode || 'edit';
            gmpTargetUserId = userId;
            var isPromote = gmpMode === 'promote';
            document.querySelector('#groupMemberPermissionsModal .modal-title').innerHTML =
                (isPromote ? 'ارتقا به مدیر — انتخابِ اختیاراتِ ' : 'اختیاراتِ ') + esc(m.full_name);
            var saveBtn = document.getElementById('gmpSaveBtn');
            if (saveBtn) saveBtn.textContent = isPromote ? 'ارتقا به مدیر' : 'ذخیره';
            // پیش‌فرض برایِ ارتقا: همه‌ی اختیارات تیک‌خورده (هم‌راستا با پیش‌فرضِ
            // سرور — NULL یعنی همه)؛ سازنده هرکدوم رو نخواد، خودش برمی‌داره
            var currentPermissions = isPromote ? groupInfoAllPermissions : m.permissions;
            document.getElementById('gmpPermissionList').innerHTML = groupInfoAllPermissions.map(function (key) {
                var checked = currentPermissions.indexOf(key) !== -1;
                var label = GROUP_PERMISSION_LABELS[key] || key;
                return '<div class="chat-profile-drawer-field" style="border-bottom:none; padding:6px 4px;">' +
                    '<label class="chat-profile-drawer-label" for="gmp-perm-' + key + '" style="flex:1; font-size:.85rem; color:var(--ink-900); cursor:pointer;">' + esc(label) + '</label>' +
                    '<label class="chat-toggle-switch">' +
                        '<input type="checkbox" id="gmp-perm-' + key + '" data-perm="' + key + '"' + (checked ? ' checked' : '') + '>' +
                        '<span class="chat-toggle-slider"></span>' +
                    '</label>' +
                '</div>';
            }).join('');
            if (!gmpModalInstance) {
                gmpModalInstance = new bootstrap.Modal(document.getElementById('groupMemberPermissionsModal'));
            }
            gmpModalInstance.show();
        }

        function saveGroupMemberPermissions() {
            if (!gmpTargetUserId || !activeConversationId) return;
            var granted = Array.prototype.slice.call(document.querySelectorAll('#gmpPermissionList input[type=checkbox]:checked'))
                .map(function (el) { return el.getAttribute('data-perm'); });

            var isPromote = gmpMode === 'promote';
            var url = isPromote ? '../api/chat/set-member-role.php' : '../api/chat/set-member-permissions.php';
            var body = isPromote
                ? { conversation_id: activeConversationId, user_id: gmpTargetUserId, role: 'admin', permissions: granted }
                : { conversation_id: activeConversationId, user_id: gmpTargetUserId, permissions: granted };

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
                        gmpModalInstance.hide();
                        refreshGroupInfoMembers(false);
                        if (isPromote) loadActiveGroupMembers(); // برای منشن و data-can-delete هم به‌روز بشه
                        showToast(isPromote ? 'عضو مدیر شد' : 'اختیارات به‌روزرسانی شد', 'success');
                    } else {
                        showToast(data.message || (isPromote ? 'خطا در ارتقای عضو' : 'خطا در ذخیره‌ی اختیارات'), 'error');
                    }
                });
        }

        function confirmLeaveGroup() {
            if (!activeConversationId) return;
            uiConfirm('آیا مطمئنید می‌خواهید از این گروه خارج شوید؟', function () {
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
                            closeGroupInfoDrawer();
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
            }, { danger: true, yesText: 'بله، خروج', noText: 'انصراف' });
        }

        // «آخرین بازدید از صفحه‌ی چت» به‌صورتِ نسبی — منبعِ یگانه (ساعتِ سرور)
        function formatLastSeen(dateStr) {
            if (!dateStr) return 'هیچ‌وقت';
            return window.TimeSync ? TimeSync.timeAgo(dateStr) : '';
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
            if (!conv) return;
            if (conv.type !== 'direct') {
                openGroupInfoDrawer();
            } else {
                openChatProfileDrawer();
            }
        }

        // ─────────────── دراورِ پروفایلِ طرفِ مقابل (گفتگویِ مستقیم) ───────────────
        function openChatProfileDrawer() {
            if (!activeConversationId) return;
            var convId = activeConversationId;
            var conv = conversations.find(c => c.conversation_id === convId);

            document.getElementById('chatProfileDrawerOverlay').classList.add('show');
            document.getElementById('chatProfileDrawer').classList.add('show');
            document.getElementById('chatProfileDrawerName').textContent = conv ? conv.title : '—';
            setAvatarContent(document.getElementById('chatProfileDrawerAvatar'), conv ? conv.title : '', conv && conv.avatar_url);
            document.getElementById('chatProfileDrawerPhone').textContent = '—';
            document.getElementById('chatProfileDrawerSection').textContent = '—';
            document.getElementById('chatProfileDrawerMuteToggle').checked = !(conv && conv.is_muted);
            document.getElementById('chatProfileDrawerMediaGrid').innerHTML = '<div class="chat-empty-list">در حال بارگذاری...</div>';

            fetch('../api/chat/user-profile.php?conversation_id=' + convId, {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                })
                .then(r => r.json())
                .then(data => {
                    if (convId !== activeConversationId || !data.success) return;
                    document.getElementById('chatProfileDrawerPhone').textContent = data.user.phone || '—';
                    document.getElementById('chatProfileDrawerSection').textContent = data.user.section_label || '—';
                })
                .catch(function(err) { console.error('chat user-profile fetch error:', err); });

            fetch('../api/chat/media.php?conversation_id=' + convId, {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                })
                .then(r => r.json())
                .then(data => {
                    if (convId !== activeConversationId) return;
                    var grid = document.getElementById('chatProfileDrawerMediaGrid');
                    if (!data.success || !data.files.length) {
                        grid.innerHTML = '<div class="chat-empty-list">هنوز عکسی ردوبدل نشده</div>';
                        return;
                    }
                    var images = data.files.filter(f => f.is_image);
                    if (!images.length) {
                        grid.innerHTML = '<div class="chat-empty-list">هنوز عکسی ردوبدل نشده</div>';
                        return;
                    }
                    grid.innerHTML = images.map(function(f) {
                        var url = '../api/chat/download.php?id=' + f.id + '&view=1';
                        return '<div class="chat-media-grid-item" onclick="window.open(\'' + url + '\',\'_blank\')" title="' + esc(f.original_name) + '">' +
                            '<img src="' + url + '" alt="' + esc(f.original_name) + '"></div>';
                    }).join('');
                })
                .catch(function(err) { console.error('chat media fetch error:', err); });
        }

        function closeChatProfileDrawer() {
            document.getElementById('chatProfileDrawerOverlay').classList.remove('show');
            document.getElementById('chatProfileDrawer').classList.remove('show');
        }

        // ─────────────── بی‌صداکردنِ گفتگو ───────────────
        function updateMuteButton(conv) {
            var btn = document.getElementById('chatMuteToggleBtn');
            var muted = !!(conv && conv.is_muted);
            btn.querySelector('i').className = muted ? 'bi bi-bell-slash-fill' : 'bi bi-bell';
            btn.title = muted ? 'باصداکردن این گفتگو' : 'بی‌صداکردن این گفتگو';

            var drawerToggle = document.getElementById('chatProfileDrawerMuteToggle');
            if (drawerToggle) drawerToggle.checked = !muted;

            var groupDrawerToggle = document.getElementById('groupInfoMuteToggle');
            if (groupDrawerToggle) groupDrawerToggle.checked = !muted;
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
                        showToast(data.message || 'خطا در تغییر وضعیت صدا', 'error');
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
                            el.innerHTML = '<div class="text-muted text-center py-3" style="font-size:.85rem;">همه‌ی نتایج از قبل عضو گروه‌اند</div>';
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

        // از دراورِ اطلاعاتِ گروه صدا زده می‌شه — کلیک روی نامِ یک عضو،
        // گفتگویِ مستقیم با همون فرد رو باز می‌کنه
        function openMemberDirectChat(userId) {
            closeGroupInfoDrawer();
            startChatWith(userId);
        }

        function startChatWith(userId) {
            // برایِ fallbackِ نام/عکسِ هدر، قبل از اینکه اولین پیام فرستاده بشه
            // (وقتی گفتگوی تازه هنوز توی لیستِ conversations نیست)
            var userInfo = currentUserResults.find(u => u.id === userId);
            var fallbackInfo = userInfo ? { title: userInfo.full_name, avatar_url: userInfo.avatar_url } : null;

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
                        var newChatInst = bootstrap.Modal.getInstance(document.getElementById('newChatModal'));
                        if (newChatInst) newChatInst.hide();
                        loadConversations(function() {
                            openConversation(data.conversation_id, null, fallbackInfo);
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