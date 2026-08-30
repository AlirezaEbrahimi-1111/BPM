<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
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
    <title>جزئیات تیکت - سیستم مدیریت کار</title>

    <!-- Bootstrap 5 RTL -->
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/jalali.js') ?>"></script>
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/jquery.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../assets/css/custom.css') ?>">

    <style>
        .td-wrap {
            max-width: 1200px;
            margin: 80px auto 40px;
            padding: 0 16px;
        }

        /* ── هدر تیکت ── */
        .tkt-header {
            background: #8e57fe;
            color: #fff;
            border-radius: 16px;
            padding: 22px 26px;
            margin-bottom: 16px;
            position: relative;
        }
        .tkt-header h5 {
            font-weight: 700;
            margin: 0 0 8px;
            font-size: 1.08rem;
            padding-left: 80px;
            color: white;
        }
        .tkt-header-meta {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            font-size: .8rem;
            opacity: .9;
        }
        .tkt-header-meta span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .tkt-back-btn {
            position: absolute;
            top: 18px;
            left: 18px;
            background: rgba(255,255,255,.18);
            border: 1px solid rgba(255,255,255,.3);
            color: #fff;
            padding: 5px 14px;
            border-radius: 9px;
            font-size: .82rem;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s;
        }
        .tkt-back-btn:hover { background: rgba(255,255,255,.3); color: #fff; }

        /* ── لایه‌بندی دو ستونه ── */
        .tkt-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            align-items: start;
        }

        /* ── کارت عمومی ── */
        .tkt-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px 22px;
            box-shadow: 0 2px 12px rgba(0,0,0,.04);
            border: 1px solid #e9e9e9;
            margin-bottom: 16px;
        }
        .tkt-card h6 {
            font-weight: 700;
            color: #333;
            margin-bottom: 14px;
            font-size: .92rem;
        }
        .tkt-card h6 i { color: #8e57fe; }

        /* ── اطلاعات گرید ── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .info-row {
            display: flex;
            gap: 8px;
            font-size: .84rem;
        }
        .info-row .lbl {
            color: #888;
            min-width: 85px;
            flex-shrink: 0;
        }
        .info-row .val {
            font-weight: 600;
            color: #1a1a1a;
        }

        /* ── تغییر وضعیت ── */
        .status-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        .status-btns button {
            padding: 5px 14px;
            border-radius: 9px;
            font-size: .77rem;
            font-weight: 600;
            border: 1.5px solid;
            cursor: pointer;
            font-family: inherit;
            background: transparent;
            transition: all .15s;
        }
        .status-btns button:hover { opacity: .75; }
        .status-btns button.is-current {
            color: #fff !important;
        }

        /* ── پیام‌ها / چت (شبیه پیام‌رسان) ── */
        .msg-bubble {
            max-width: 78%;
            padding: 10px 14px;
            border-radius: 14px;
            margin-bottom: 10px;
            position: relative;
            font-size: .86rem;
            line-height: 1.7;
        }
        /* پیام کاربر (ایجادکنندهٔ تیکت) → سمت راست */
        .msg-user {
            background: rgba(142, 87, 254, 0.1) !important;
            border: 1px solid rgba(142, 87, 254, 0.25);
            margin-inline-start: 0;
            margin-inline-end: auto;
            border-radius: 14px 14px 4px 14px;
        }
        /* پیام پشتیبانی → سمت چپ */
        .msg-admin {
            background: rgba(27, 123, 57, 0.1) !important;
            border: 1px solid rgba(27, 123, 57, 0.25);
            margin-inline-start: auto;
            margin-inline-end: 0;
            border-radius: 14px 14px 14px 4px;
        }
        .msg-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }
        .msg-author {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            font-size: .82rem;
            color: #333;
            white-space: nowrap;
        }
        .msg-author i {
            font-size: .95rem;
            color: #999;
        }
        .msg-badge {
            font-size: .68rem;
            font-weight: 600;
            padding: 2px 9px;
            border-radius: 20px;
            color: #fff;
        }
        .msg-badge-support { background: #1b7b39; }
        .msg-badge-user { background: #8e57fe; }
        .msg-time {
            font-size: .74rem;
            color: #999;
            white-space: nowrap;
        }
        .msg-delete-btn {
            background: none;
            border: none;
            color: #999;
            font-size: .8rem;
            padding: 2px 4px;
            border-radius: 9px;
            cursor: pointer;
            margin-inline-start: 4px;
        }
        .msg-delete-btn:hover {
            color: #dc2626;
            background: rgba(220, 38, 38, .1);
        }
        .msg-text {
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        /* عکسِ داخلِ حبابِ پیام — بزرگ‌تر از تامب‌نیلِ پیوست‌ها، برای وضوح */
        .msg-images {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        .msg-image {
            max-width: 240px;
            max-height: 240px;
            border-radius: 10px;
            display: block;
            cursor: pointer;
            transition: opacity .15s;
        }
        .msg-image:hover { opacity: .9; }

        /* ── پیوست‌ها — تفکیک‌شده بر اساس فرستنده ── */
        .att-group { margin-bottom: 14px; }
        .att-group:last-child { margin-bottom: 0; }
        .att-group-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .78rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .att-group-user { color: #8e57fe; }
        .att-group-support { color: #1b7b39; }
        .att-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .att-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: #e9e9e9;
            border-radius: 9px;
            font-size: .8rem;
            cursor: pointer;
            border: 1px solid #e9e9e9;
            transition: background .15s;
        }
        .att-chip:hover { background: rgba(142, 87, 254, 0.1); }

        /* پیش‌نمایشِ مربعیِ تصاویرِ پیوست‌شده */
        .att-thumb {
            width: 84px;
            height: 84px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e9e9e9;
            cursor: pointer;
            flex-shrink: 0;
            transition: border-color .15s, transform .15s;
        }
        .att-thumb:hover { border-color: #8e57fe; transform: scale(1.03); }
        .att-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* ── مودالِ پیش‌نمایشِ تصویر ──
           نکته: قبلاً .modal-content عرضِ کاملِ .modal-lg را می‌گرفت (پیش‌فرضِ بوت‌استرپ:
           width:100%)، در حالی‌که تصویرِ کوچک فقط وسطِ آن یک‌جا می‌نشست — نتیجه: دکمه‌های
           گوشه (دانلود/بستن) که به‌همان .modal-content چسبیده بودند، از خودِ تصویر فاصله
           می‌گرفتند. با auto/inline-block کردنِ عرض، .modal-content دقیقاً هم‌اندازهٔ
           تصویرِ رندرشده می‌شود و دکمه‌ها همیشه (چه تصویر کوچک، چه بزرگ) به لبهٔ آن می‌چسبند. */
        #imgPreviewModal .modal-dialog {
            display: flex;
            align-items: center;
            justify-content: center;
            max-width: 92vw;
        }
        #imgPreviewModal .modal-content {
            background: transparent;
            border: none;
            box-shadow: none;
            width: auto;
            max-width: 100%;
            display: inline-block;
        }
        #imgPreviewImg {
            max-width: 88vw;
            max-height: 85vh;
            display: block;
            border-radius: 10px;
        }
        .img-preview-download,
        .img-preview-close {
            position: absolute;
            top: 8px;
            z-index: 5;
            width: 38px;
            height: 38px;
            border-radius: 9px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            cursor: pointer;
        }
        .img-preview-download {
            left: 8px;
            background: rgba(0,0,0,.55);
            color: #fff;
        }
        .img-preview-download:hover { background: #8e57fe; }
        .img-preview-close {
            right: 8px;
            background: rgba(255,255,255,.9);
            color: #333;
        }
        .img-preview-close:hover { background: #fff; }

        /* ── تسکِ پیوست‌شده ── */
        .linked-task-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(142, 87, 254, 0.1);
            border: 1px solid rgba(142, 87, 254, 0.25);
            border-radius: 10px;
            padding: 7px 12px;
            font-size: .84rem;
            margin-top: 12px;
        }
        .linked-task-chip a {
            color: #8e57fe;
            font-weight: 600;
            text-decoration: none;
        }
        .linked-task-chip a:hover { text-decoration: underline; }

        /* ── کارتِ گفتگو (تاریخچه + پاسخ در یک قاب، شبیه پیام‌رسان) ── */
        .tkt-chat-card {
            display: flex;
            flex-direction: column;
        }

        /* پیش‌نمایشِ فایل‌های در حالِ پیوست (چه با کلیکِ آیکن، چه با Ctrl+V) */
        .file-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 0 4px;
        }
        .file-preview:empty { display: none; }
        .file-chip {
            background: #f0f4ff;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: .8rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .file-chip .remove-file {
            cursor: pointer;
            color: #ef4444;
            font-weight: 700;
            font-size: .9rem;
        }
        .file-chip .remove-file:hover { color: #dc2626; }
        .file-thumb {
            position: relative;
            width: 60px;
            height: 60px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e9e9e9;
            flex-shrink: 0;
        }
        .file-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .file-thumb .thumb-remove {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 18px;
            height: 18px;
            border-radius: 9px;
            background: rgba(0,0,0,.55);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .65rem;
            cursor: pointer;
        }
        .file-thumb .thumb-remove:hover { background: rgba(220,38,38,.85); }

        .chat-composer {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            margin-top: 10px;
            padding: 6px;
            border: 1.5px solid #e9e9e9;
            border-radius: 22px;
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
        }
        .chat-composer:focus-within {
            border-color: #8e57fe;
            box-shadow: 0 0 0 3px rgba(142,87,254,.1);
        }
        .composer-textarea {
            flex: 1 1 auto;
            min-width: 0;
            border: none;
            outline: none;
            resize: none;
            background: transparent;
            font-family: inherit;
            font-size: .86rem;
            line-height: 1.5;
            max-height: 120px;
            overflow-y: auto;
            padding: 7px 6px;
            color: #1a1a1a;
        }
        .composer-attach-btn,
        .composer-send-btn {
            flex: 0 0 auto;
            width: 34px;
            height: 34px;
            border-radius: 9px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .95rem;
            transition: background .15s, opacity .15s;
            
        }
        .composer-attach-btn {
            background: transparent;
            color: #8e57fe;
        }
        .composer-attach-btn:hover { background: rgba(142, 87, 254, 0.1); }
        .composer-send-btn {
            background: #8e57fe;
            color: #fff;
            padding-top: 6px;
        }
        .composer-send-btn:disabled { opacity: .45; cursor: default; }

        /* ── دکمه حذف ── */
        .btn-delete-ticket {
            background: #fef2f2;
            color: #ef4444;
            border: 1px solid #fecaca;
            padding: 8px 18px;
            border-radius: 9px;
            font-weight: 600;
            font-size: .82rem;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all .15s;
        }
        .btn-delete-ticket:hover { background: #fee2e2; }

        .loading-center {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        /* ── messages scroll ── */
        .msg-scroll {
            max-height: 500px;
            overflow-y: auto;
            padding-left: 4px;
        }
        /* اسکرول‌بار — هم‌راستا با dashboard-manager.php */
        .msg-scroll::-webkit-scrollbar,
        .composer-textarea::-webkit-scrollbar {
            width: 7px;
        }
        .msg-scroll::-webkit-scrollbar-track,
        .composer-textarea::-webkit-scrollbar-track {
            background: var(--gray-50);
        }
        .msg-scroll::-webkit-scrollbar-thumb,
        .composer-textarea::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 99px;
        }
        .msg-scroll::-webkit-scrollbar-thumb:hover,
        .composer-textarea::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }
        :root[data-theme="dark"] .msg-scroll::-webkit-scrollbar-track,
        :root[data-theme="dark"] .composer-textarea::-webkit-scrollbar-track {
            background: var(--info-box-bg);
        }
        :root[data-theme="dark"] .msg-scroll::-webkit-scrollbar-thumb,
        :root[data-theme="dark"] .composer-textarea::-webkit-scrollbar-thumb {
            background: var(--border-soft);
        }
        :root[data-theme="dark"] .msg-scroll::-webkit-scrollbar-thumb:hover,
        :root[data-theme="dark"] .composer-textarea::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }

        /* ═══════════════════════════════════════════════
           تم تاریک
           ═══════════════════════════════════════════════ */
        :root[data-theme="dark"] .tkt-card {
            background: var(--surface);
            border-color: var(--border-soft);
            box-shadow: none;
        }
        :root[data-theme="dark"] .tkt-card h6 {
            color: var(--text-strong);
        }
        :root[data-theme="dark"] .info-row .lbl {
            color: var(--text-muted);
        }
        :root[data-theme="dark"] .info-row .val {
            color: var(--text-strong);
        }
        :root[data-theme="dark"] .loading-center {
            color: var(--text-muted);
        }
        :root[data-theme="dark"] .status-btns button {
            background: transparent;
        }
        :root[data-theme="dark"] .att-chip {
            background: var(--info-box-bg);
            border-color: var(--border-soft);
            color: var(--text-strong);
        }
        :root[data-theme="dark"] .att-chip:hover {
            background: #2b3242;
        }
        :root[data-theme="dark"] .att-thumb {
            border-color: var(--border-soft);
        }
        :root[data-theme="dark"] .linked-task-chip {
            background: rgba(116, 76, 164, .18);
            border-color: rgba(116, 76, 164, .4);
        }
        :root[data-theme="dark"] .linked-task-chip a {
            color: var(--icon-accent);
        }
        :root[data-theme="dark"] .msg-user {
            background: rgba(116, 76, 164, .22) !important;
            border-color: rgba(116, 76, 164, .4);
        }
        :root[data-theme="dark"] .msg-admin {
            background: rgba(46, 125, 50, .18) !important;
            border-color: rgba(46, 125, 50, .35);
        }
        :root[data-theme="dark"] .msg-author {
            color: var(--text-strong);
        }
        :root[data-theme="dark"] .msg-author i {
            color: var(--text-muted);
        }
        :root[data-theme="dark"] .msg-time {
            color: var(--text-muted);
        }
        :root[data-theme="dark"] .file-chip {
            background: #232a3a;
            color: var(--text-strong);
        }
        :root[data-theme="dark"] .file-thumb {
            border-color: var(--border-soft);
        }
        :root[data-theme="dark"] .chat-composer {
            background: var(--info-box-bg);
            border-color: var(--border-soft);
        }
        :root[data-theme="dark"] .composer-textarea {
            color: var(--text-strong);
        }
        :root[data-theme="dark"] .composer-attach-btn:hover {
            background: #2b3242;
        }
        :root[data-theme="dark"] .btn-delete-ticket {
            background: rgba(239, 68, 68, .12);
            border-color: rgba(239, 68, 68, .35);
        }
        :root[data-theme="dark"] .btn-delete-ticket:hover {
            background: rgba(239, 68, 68, .2);
        }

        @media (max-width: 992px) {
            .tkt-layout {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 576px) {
            .td-wrap { margin-top: 70px; }
            .info-grid { grid-template-columns: 1fr; }
            .tkt-header-meta { flex-direction: column; gap: 4px; }
            .tkt-header h5 { padding-left: 60px; color: white; }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="td-wrap">

        <!-- هدر تیکت -->
        <div class="tkt-header" id="tktHeader">
            <a href="tickets.php" class="tkt-back-btn"><i class="bi bi-arrow-right"></i> بازگشت</a>
            <h5 id="tktSubject">در حال بارگذاری...</h5>
            <div class="tkt-header-meta" id="tktMeta"></div>
        </div>

        <!-- لایه‌بندی دو ستونه -->
        <div class="tkt-layout">

            <!-- ═══ ستون اول (راست): تغییر وضعیت + اطلاعات + پیوست‌ها ═══ -->
            <div class="tkt-col-right">

                <!-- تغییر وضعیت -->
                <div class="tkt-card" id="statusCard" style="display:none;">
                    <h6><i class="bi bi-arrow-repeat ms-2"></i>تغییر وضعیت</h6>
                    <div class="status-btns" id="statusBtns"></div>
                </div>

                <!-- اطلاعات -->
                <div class="tkt-card" id="tktInfoCard">
                    <div class="loading-center"><div class="spinner-border spinner-border-sm"></div></div>
                </div>

                <!-- پیوست‌ها -->
                <div class="tkt-card" id="attCard" style="display:none;">
                    <h6><i class="bi bi-paperclip ms-2"></i>فایل‌های پیوست</h6>
                    <div id="attList"></div>
                </div>

                <!-- دکمه حذف (فقط admin) -->
                <div class="tkt-card" id="deleteCard" style="display:none;">
                    <h6><i class="bi bi-shield-exclamation ms-2"></i>عملیات مدیر</h6>
                    <button class="btn-delete-ticket" onclick="deleteTicket()">
                        <i class="bi bi-trash"></i>حذف تیکت
                    </button>
                </div>
            </div>

            <!-- ═══ ستون دوم (چپ): گفتگو (پیام‌ها + پاسخ) به‌شکل پیام‌رسان ═══ -->
            <div class="tkt-col-left">

                <div class="tkt-card tkt-chat-card">
                    <h6><i class="bi bi-chat-dots ms-2"></i>مکالمه</h6>

                    <div id="msgList" class="msg-scroll">
                        <div class="loading-center"><div class="spinner-border spinner-border-sm"></div></div>
                    </div>

                    <div id="replyFilePreview" class="file-preview"></div>
                    <div class="chat-composer">
                        <label class="composer-attach-btn" title="پیوست فایل">
                            <input type="file" id="replyFileInput" multiple hidden
                                   accept=".jpg,.jpeg,.png,.pdf,.docx,.doc,.xls,.xlsx,.mp3,.m4a,.ogg">
                            <i class="bi bi-paperclip"></i>
                        </label>
                        <textarea id="replyMsg" class="composer-textarea" rows="1"
                                  placeholder="پیام خود را بنویسید... (Ctrl+V برای چسباندنِ عکس)"></textarea>
                        <button class="composer-send-btn" id="btnReply" onclick="sendReply()" title="ارسال">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- مودالِ پیش‌نمایشِ تصویر پیوست -->
    <div class="modal fade" id="imgPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <button type="button" class="img-preview-download" id="imgPreviewDownloadBtn" title="دانلود">
                    <i class="bi bi-download"></i>
                </button>
                <button type="button" class="img-preview-close" data-bs-dismiss="modal" aria-label="بستن">
                    <i class="bi bi-x-lg"></i>
                </button>
                <img id="imgPreviewImg" src="" alt="">
            </div>
        </div>
    </div>

    <script src="<?= asset('/assets/js/undo-toast.js') ?>"></script>
    <script>
        async function markTicketNotificationsRead(ticketId) {
            try {
                await fetch('../api/notifications/mark-read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({ ticket_id: parseInt(ticketId) })
                });
            } catch (e) {
                // خطا مهم نیست، فقط لاگ کن
                console.error('خطا در mark-read نوتیفیکیشن:', e);
            }
        }

        var ticketId = null;
        var ticketData = null;
        var currentUserId = null;
        var currentUserRole = null;

        document.addEventListener('DOMContentLoaded', function() {
            authToken = localStorage.getItem('auth_token');

            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }

            var params = new URLSearchParams(window.location.search);
            ticketId = params.get('id');
            if (!ticketId) {
                showToast('شناسه تیکت نامعتبر است', 'error');
                return;
            }
            loadDetail();
            markTicketNotificationsRead(ticketId);

            // ── پیوستِ فایل — یک مسیرِ واحد برای هر دو راه: کلیکِ آیکن، و Ctrl+V ──
            document.getElementById('replyFileInput').addEventListener('change', function() {
                addReplyFiles(this.files);
                this.value = ''; // برای این‌که انتخابِ دوبارهٔ همان فایل هم رویداد change بدهد
            });

            document.getElementById('replyMsg').addEventListener('paste', function(e) {
                var items = (e.clipboardData || window.clipboardData).items;
                if (!items) return;
                var pasted = [];
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image/') === 0) {
                        var f = items[i].getAsFile();
                        if (f) {
                            var ext = (f.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
                            pasted.push(new File([f], 'clipboard-' + Date.now() + '.' + ext, { type: f.type }));
                        }
                    }
                }
                if (pasted.length > 0) {
                    e.preventDefault(); // از چسباندنِ نشانیِ فایل به‌عنوان متن جلوگیری می‌کند
                    addReplyFiles(pasted);
                    showToast('تصویر به پیوست‌ها اضافه شد', 'success');
                }
            });

            // ── کادرِ پیام: رشدِ خودکارِ ارتفاع + ارسال با Enter (Shift+Enter یا Alt+Enter = خط جدید) ──
            var replyMsgEl = document.getElementById('replyMsg');
            replyMsgEl.addEventListener('input', function() { autoGrowComposer(this); });
            replyMsgEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.altKey) {
                    // Alt+Enter برخلافِ Shift+Enter، به‌صورتِ پیش‌فرض توسطِ مرورگر
                    // به‌عنوانِ خطِ‌جدید در textarea شناخته نمی‌شه — دستی درج می‌کنیم
                    e.preventDefault();
                    var start = this.selectionStart, end = this.selectionEnd;
                    this.value = this.value.slice(0, start) + '\n' + this.value.slice(end);
                    this.selectionStart = this.selectionEnd = start + 1;
                    autoGrowComposer(this);
                    return;
                }
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendReply();
                }
            });
        });

        // رشدِ خودکارِ کادرِ پیام تا سقفِ max-height (در CSS)، بعد از آن اسکرول داخلی
        function autoGrowComposer(el) {
            el.style.height = 'auto';
            el.style.height = el.scrollHeight + 'px';
        }

        // ─── فایل‌های در حالِ پیوست به پاسخِ در حالِ نگارش ───
        // (چه از کلیکِ آیکنِ پیوست، چه از Ctrl+V — هر دو همین آرایه را پر می‌کنند
        //  و فقط لحظهٔ ارسال، همراهِ پیام واقعاً به سرور می‌روند)
        var selectedReplyFiles = [];
        var replyFilePreviewUrls = [];

        function addReplyFiles(fileList) {
            var maxSize = 20 * 1024 * 1024;
            for (var i = 0; i < fileList.length; i++) {
                var f = fileList[i];
                if (f.size > maxSize) {
                    showToast('فایل «' + f.name + '» بیش از 20MB است', 'warning');
                    continue;
                }
                var dup = selectedReplyFiles.some(function(s) { return s.name === f.name && s.size === f.size; });
                if (!dup) selectedReplyFiles.push(f);
            }
            renderReplyFilePreview();
        }

        function removeReplyFile(idx) {
            selectedReplyFiles.splice(idx, 1);
            renderReplyFilePreview();
        }

        function renderReplyFilePreview() {
            var el = document.getElementById('replyFilePreview');

            replyFilePreviewUrls.forEach(function(u) { URL.revokeObjectURL(u); });
            replyFilePreviewUrls = [];

            if (selectedReplyFiles.length === 0) { el.innerHTML = ''; return; }

            var html = '';
            selectedReplyFiles.forEach(function(f, idx) {
                var isImage = f.type && f.type.indexOf('image/') === 0;
                if (isImage) {
                    var url = URL.createObjectURL(f);
                    replyFilePreviewUrls.push(url);
                    html += '<div class="file-thumb" title="' + esc(f.name) + '">' +
                        '<img src="' + url + '" alt="' + esc(f.name) + '">' +
                        '<span class="thumb-remove" onclick="removeReplyFile(' + idx + ')">✕</span></div>';
                } else {
                    var kb = (f.size / 1024).toFixed(0);
                    html += '<div class="file-chip">' +
                        '<i class="bi bi-paperclip"></i>' +
                        '<span>' + esc(f.name) + ' (' + kb + ' KB)</span>' +
                        '<span class="remove-file" onclick="removeReplyFile(' + idx + ')">✕</span></div>';
                }
            });
            el.innerHTML = html;
        }

        // ─── بارگذاری جزئیات ───
        async function loadDetail() {
            try {
                var res = await fetch('../api/tickets/detail.php' + '?id=' + ticketId, {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });
                var data = await res.json();

                if (!data.success) {
                    showToast(data.message || 'خطا در بارگذاری', 'error');
                    return;
                }

                ticketData      = data.ticket;
                currentUserId   = data.current_user_id;
                currentUserRole = data.current_user_role;

                renderHeader(data.ticket);
                renderInfo(data.ticket);
                renderMessages(data.messages, data.attachments);
                renderAttachments(data.attachments);
                renderStatusButtons(data.statuses, data.ticket);
                setupPermissions(data.ticket);

            } catch(e) {
                console.error('loadDetail:', e);
                showToast('خطا در ارتباط با سرور', 'error');
            }
        }

        // ─── هدر ───
        function renderHeader(t) {
            document.getElementById('tktSubject').textContent = t.subject;
            document.title = t.ticket_number + ' — ' + t.subject;

            document.getElementById('tktMeta').innerHTML =
                '<span><i class="bi bi-hash"></i>' + esc(t.ticket_number) + '</span>' +
                '<span><i class="bi bi-circle-fill" style="font-size:8px;color:' + t.status_color + ';"></i>' + esc(t.status_label) + '</span>' +
                '<span><i class="bi bi-flag-fill" style="color:' + t.priority_color + ';"></i>' + esc(t.priority_label) + '</span>' +
                '<span><i class="bi bi-person"></i>' + esc(t.creator_name) + '</span>';
        }

        // ─── اطلاعات ───
        function renderInfo(t) {
            var created = window.TimeSync ? TimeSync.formatJalali(t.created_at) : new Date(t.created_at).toLocaleDateString('fa-IR');
            var updated = window.TimeSync ? TimeSync.formatJalali(t.updated_at) : new Date(t.updated_at).toLocaleDateString('fa-IR');

            var linkedTaskHtml = '';
            if (t.source_type === 'task' && t.source_id && t.linked_task_title) {
                linkedTaskHtml =
                    '<div class="linked-task-chip">' +
                        '<i class="bi bi-link-45deg"></i>' +
                        '<a href="task-detail.php?id=' + t.source_id + '" target="_blank">' + esc(t.linked_task_title) + '</a>' +
                    '</div>';
            }

            document.getElementById('tktInfoCard').innerHTML =
                '<h6><i class="bi bi-info-circle ms-2"></i>اطلاعات تیکت</h6>' +
                '<div class="info-grid">' +
                    infoRow('شماره', '<span style="font-family:monospace;direction:ltr;">' + esc(t.ticket_number) + '</span>') +
                    infoRow('وضعیت', '<span style="color:' + t.status_color + ';">' + esc(t.status_label) + '</span>') +
                    infoRow('اولویت', '<span style="color:' + t.priority_color + ';">' + esc(t.priority_label) + '</span>') +
                    infoRow('دسته‌بندی', esc(t.category_name || 'بدون دسته‌بندی')) +
                    infoRow('ایجادکننده', esc(t.creator_name)) +
                    infoRow('پاسخ‌دهنده', esc(t.assigned_name || 'تعیین نشده')) +
                    infoRow('تاریخ ایجاد', created) +
                    infoRow('آخرین بروزرسانی', updated) +
                '</div>' + linkedTaskHtml;
        }

        function infoRow(label, value) {
            return '<div class="info-row"><span class="lbl">' + label + ':</span><span class="val">' + value + '</span></div>';
        }

        // ─── پیام‌ها ───
        function renderMessages(messages, attachments) {
            var el = document.getElementById('msgList');
            if (!messages || messages.length === 0) {
                el.innerHTML = '<p class="text-muted text-center" style="font-size:.85rem;">بدون پیام</p>';
                return;
            }

            // ✅ عکس‌های هر پیام، همان‌جا داخل حبابِ خودش نمایش داده شوند (نه فقط در کارتِ پیوست‌ها)
            var token = localStorage.getItem('auth_token') || localStorage.getItem('authToken');
            var imagesByMessage = {};
            (attachments || []).forEach(function(a) {
                if (a.mime_type && a.mime_type.indexOf('image/') === 0) {
                    (imagesByMessage[a.message_id] = imagesByMessage[a.message_id] || []).push(a);
                }
            });

            var html = '';
            for (var i = 0; i < messages.length; i++) {
                var m = messages[i];
                // ✅ فرستنده = خودِ ایجادکنندهٔ تیکت → «کاربر»؛ هرکسِ دیگری که پاسخ داده → «پشتیبانی»
                // (نه بر اساس نقشِ سازمانیِ کلی، چون یک مدیر/سرپرست ممکن است خودش صاحبِ تیکت باشد)
                var isFromCreator = (ticketData && m.user_id == ticketData.created_by);
                var cls = isFromCreator ? 'msg-user' : 'msg-admin';

                // نشان‌دهنده فرستنده
                var senderBadge = isFromCreator
                    ? '<span class="msg-badge msg-badge-user">کاربر</span>'
                    : '<span class="msg-badge msg-badge-support">پشتیبانی</span>';
                var time = window.TimeSync ? TimeSync.formatJalaliTime(m.created_at) : new Date(m.created_at).toLocaleString('fa-IR');

                var imagesHtml = '';
                var msgImages = imagesByMessage[m.id] || [];
                if (msgImages.length) {
                    imagesHtml = '<div class="msg-images">' + msgImages.map(function(a) {
                        var url = '../api/tickets/download.php?id=' + a.id + '&view=1';
                        return '<img class="msg-image" src="' + url + '" alt="' + esc(a.original_name) +
                            '" loading="lazy" onclick="openImagePreview(' + a.id + ')">';
                    }).join('') + '</div>';
                }

                // 🔒 فقط کاربر id=1 — هم‌راستا با api/tickets/delete-message.php
                var deleteBtn = (Number(currentUserId) === 1)
                    ? '<button type="button" class="msg-delete-btn" title="حذفِ این پیام" onclick="deleteMessage(' + m.id + ')"><i class="bi bi-trash"></i></button>'
                    : '';

                html += '<div class="msg-bubble ' + cls + '">';
                html += '<div class="msg-head">';
                html += '<span class="msg-author"><i class="bi bi-person"></i><span>' + esc(m.user_name) + '</span>' + senderBadge + '</span>';
                html += '<span class="msg-time">' + time + '</span>' + deleteBtn;
                html += '</div>';
                if (m.message) html += '<div class="msg-text">' + esc(m.message) + '</div>';
                html += imagesHtml;
                html += '</div>';
            }
            el.innerHTML = html;
            el.scrollTop = el.scrollHeight; // ✅ همیشه آخرین پیام نمایش داده شود
        }

        // ─── حذفِ منطقیِ یک پیام (فقط کاربر id=1) ───
        function deleteMessage(messageId) {
            uiConfirm('این پیام برای همیشه حذف می‌شود. مطمئنید؟', function () {
                fetch('../api/tickets/delete-message.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ message_id: messageId })
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.success) {
                            showToast('پیام حذف شد', 'success');
                            loadDetail();
                        } else {
                            showToast(data.message || 'خطا در حذفِ پیام', 'error');
                        }
                    })
                    .catch(function () {
                        showToast('خطا در ارتباط با سرور', 'error');
                    });
            });
        }

        // ─── پیوست‌ها ───
        function renderAttItem(a, token) {
            var isImage = a.mime_type && a.mime_type.indexOf('image/') === 0;

            if (isImage) {
                var thumbUrl = '../api/tickets/download.php?id=' + a.id + '&view=1';
                return '<div class="att-thumb" title="' + esc(a.original_name) + '" onclick="openImagePreview(' + a.id + ')">' +
                    '<img src="' + thumbUrl + '" alt="' + esc(a.original_name) + '" loading="lazy"></div>';
            }

            var icon = 'bi-file-earmark';
            if (a.mime_type && a.mime_type.indexOf('pdf') !== -1) icon = 'bi-file-pdf';
            var sizeKB = (a.file_size / 1024).toFixed(0);
            return '<div class="att-chip" onclick="downloadFile(' + a.id + ')">' +
                '<i class="bi ' + icon + '"></i>' +
                '<span>' + esc(a.original_name) + '</span>' +
                '<small class="text-muted">(' + sizeKB + ' KB)</small>' +
                '<i class="bi bi-download" style="color:#8e57fe;"></i></div>';
        }

        // ✅ تفکیکِ پیوست‌ها بر اساس فرستنده — همان قاعده‌ی رنگ‌بندیِ پیام‌ها
        // (user_id پیوست == created_by تیکت → از طرفِ ارسال‌کننده، وگرنه پشتیبانی)
        function renderAttachments(atts) {
            if (!atts || atts.length === 0) return;
            document.getElementById('attCard').style.display = '';

            var token = localStorage.getItem('auth_token') || localStorage.getItem('authToken');
            var fromCreator = [], fromSupport = [];
            for (var i = 0; i < atts.length; i++) {
                var isCreatorAtt = (ticketData && atts[i].user_id == ticketData.created_by);
                (isCreatorAtt ? fromCreator : fromSupport).push(atts[i]);
            }

            var html = '';
            if (fromCreator.length) {
                html += '<div class="att-group"><div class="att-group-title att-group-user">' +
                    '<i class="bi bi-person"></i>پیوست‌های ' + esc(ticketData.creator_name || 'کاربر') + '</div>' +
                    '<div class="att-list">' + fromCreator.map(function(a) { return renderAttItem(a, token); }).join('') + '</div></div>';
            }
            if (fromSupport.length) {
                html += '<div class="att-group"><div class="att-group-title att-group-support">' +
                    '<i class="bi bi-headset"></i>پیوست‌های پشتیبانی</div>' +
                    '<div class="att-list">' + fromSupport.map(function(a) { return renderAttItem(a, token); }).join('') + '</div></div>';
            }
            document.getElementById('attList').innerHTML = html;
        }

        // ─── دکمه‌های وضعیت ───
        function renderStatusButtons(statuses, ticket) {
            if (currentUserId != 1) return;

            document.getElementById('statusCard').style.display = '';

            var html = '';
            for (var i = 0; i < statuses.length; i++) {
                var s = statuses[i];
                var isCurrent = (s.id == ticket.status_id);
                var style = 'color:' + s.color + ';border-color:' + s.color + ';';
                if (isCurrent) style += 'background:' + s.color + ';color:#fff;';
                html += '<button onclick="changeStatus(' + s.id + ')" style="' + style + '"';
                html += ' class="' + (isCurrent ? 'is-current' : '') + '">';
                html += esc(s.label) + '</button>';
            }
            document.getElementById('statusBtns').innerHTML = html;
        }

        // ─── مجوزها ───
        function setupPermissions(ticket) {
            if (currentUserId == 1) {
                document.getElementById('deleteCard').style.display = '';
            }
        }

        // ─── تغییر وضعیت ───
        async function changeStatus(statusId) {
            try {
                var res = await fetch('../api/tickets/change-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({ ticket_id: parseInt(ticketId), status_id: statusId })
                });
                var data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(loadDetail, 800);
                } else {
                    showToast(data.message || 'خطا', 'warning');
                }
            } catch(e) {
                showToast('خطا در ارتباط با سرور', 'error');
            }
        }

        // ─── ارسال پاسخ ───
        async function sendReply() {
            var message = document.getElementById('replyMsg').value.trim();
            if (!message) {
                showToast('متن پیام الزامی است', 'warning');
                document.getElementById('replyMsg').focus();
                return;
            }

            var btn = document.getElementById('btnReply');
            btn.disabled = true;

            try {
                var fd = new FormData();
                fd.append('ticket_id', ticketId);
                fd.append('message', message);
                selectedReplyFiles.forEach(function(f) { fd.append('attachments[]', f); });

                var res = await fetch('../api/tickets/reply.php', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + authToken },
                    body: fd
                });
                var data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    var msgEl = document.getElementById('replyMsg');
                    msgEl.value = '';
                    msgEl.style.height = 'auto';
                    selectedReplyFiles = [];
                    renderReplyFilePreview();
                    loadDetail();
                } else {
                    showToast(data.message || 'خطا', 'warning');
                }
            } catch(e) {
                showToast('خطا در ارتباط با سرور', 'error');
            } finally {
                btn.disabled = false;
            }
        }

        // ─── حذف تیکت ───
        async function restoreTicket(id) {
            try {
                var res = await fetch('../api/tickets/restore.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                    body: JSON.stringify({ ticket_id: parseInt(id) })
                });
                var d = await res.json();
                if (d.success) location.reload();
                else showToast(d.message || 'بازگرداندن انجام نشد', 'error');
            } catch (e) {
                showToast('خطا در ارتباط با سرور', 'error');
            }
        }

        function deleteTicket() {
            uiConfirm('آیا از حذف این تیکت اطمینان دارید؟ این عمل غیرقابل بازگشت است.', async function () {
                try {
                    var res = await fetch('../api/tickets/delete.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({ ticket_id: parseInt(ticketId) })
                    });
                    var data = await res.json();

                    if (data.success) {
                        let undone = false;
                        showUndoToast({
                            title: 'حذف تیکت',
                            message: 'تیکت حذف شد',
                            duration: 6000,
                            onUndo: async () => { undone = true; await restoreTicket(ticketId); }
                        });
                        setTimeout(function() { if (!undone) window.location.href = 'tickets.php'; }, 6200);
                    } else {
                        showToast(data.message || 'خطا در حذف تیکت', 'error');
                    }
                } catch(e) {
                    showToast('خطا در ارتباط با سرور', 'error');
                }
            }, { danger: true, yesText: 'بله، حذف', noText: 'انصراف' });
        }

        // ─── دانلود فایل ───
function downloadFile(id) {
    var token = localStorage.getItem('auth_token') || localStorage.getItem('authToken');
    window.open('../api/tickets/download.php?id=' + id, '_blank');
}

// ─── پیش‌نمایشِ تصویر در مودال ───
function openImagePreview(id) {
    var token = localStorage.getItem('auth_token') || localStorage.getItem('authToken');
    document.getElementById('imgPreviewImg').src = '../api/tickets/download.php?id=' + id + '&view=1';
    document.getElementById('imgPreviewDownloadBtn').onclick = function() { downloadFile(id); };
    new bootstrap.Modal(document.getElementById('imgPreviewModal')).show();
}

        // ─── escape HTML ───
        function esc(str) {
            if (!str) return '';
            var d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>