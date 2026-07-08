<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../includes/version.php';
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
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>" rel="stylesheet">
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
            background: linear-gradient(135deg, #744ca4, #9b6dd7);
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
            border-radius: 8px;
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
            border: 1px solid #f0f0f0;
            margin-bottom: 16px;
        }
        .tkt-card h6 {
            font-weight: 700;
            color: #333;
            margin-bottom: 14px;
            font-size: .92rem;
        }
        .tkt-card h6 i { color: #744ca4; }

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
            border-radius: 20px;
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

        /* ── پیام‌ها / چت ── */
        .msg-bubble {
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 12px;
            position: relative;
            font-size: .86rem;
            line-height: 1.75;
        }
/* ✅ بعد */
.msg-user  { 
    background: #f0e6ff !important; 
    border: 1px solid #e0d0f5;
    border-right: 4px solid #744ca4;
}
.msg-admin { 
    background: #e8f5e9 !important; 
    border: 1px solid #c8e6c9;
    border-right: 4px solid #2e7d32;
}
        .msg-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .msg-author {
            font-weight: 700;
            font-size: .82rem;
            color: #333;
        }
        .msg-time {
            font-size: .74rem;
            color: #999;
        }
        .msg-text {
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        /* ── پیوست‌ها ── */
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
            background: #f8f9fa;
            border-radius: 8px;
            font-size: .8rem;
            cursor: pointer;
            border: 1px solid #e8e8e8;
            transition: background .15s;
        }
        .att-chip:hover { background: #ede5f7; }

        /* ── پاسخ ── */
        .reply-textarea {
            width: 100%;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: .86rem;
            min-height: 100px;
            resize: vertical;
            font-family: inherit;
            transition: border-color .2s, box-shadow .2s;
        }
        .reply-textarea:focus {
            border-color: #744ca4;
            outline: none;
            box-shadow: 0 0 0 3px rgba(116,76,164,.1);
        }
        .reply-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .btn-reply {
            background: linear-gradient(135deg, #744ca4, #9b6dd7);
            color: #fff;
            border: none;
            padding: 9px 22px;
            border-radius: 10px;
            font-weight: 600;
            font-size: .84rem;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-reply:disabled { opacity: .45; }

        .reply-file-label {
            cursor: pointer;
            font-size: .82rem;
            color: #744ca4;
            margin-right: auto;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .reply-file-label:hover { text-decoration: underline; }

        /* ── دکمه حذف ── */
        .btn-delete-ticket {
            background: #fef2f2;
            color: #ef4444;
            border: 1px solid #fecaca;
            padding: 8px 18px;
            border-radius: 10px;
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

        @media (max-width: 992px) {
            .tkt-layout {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 576px) {
            .td-wrap { margin-top: 70px; }
            .info-grid { grid-template-columns: 1fr; }
            .tkt-header-meta { flex-direction: column; gap: 4px; }
            .tkt-header h5 { padding-left: 60px; }
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

            <!-- ═══ ستون اول: اطلاعات + پیوست‌ها ═══ -->
            <div class="tkt-col-right">

                <!-- اطلاعات -->
                <div class="tkt-card" id="tktInfoCard">
                    <div class="loading-center"><div class="spinner-border spinner-border-sm"></div></div>
                </div>

                <!-- پیوست‌ها -->
                <div class="tkt-card" id="attCard" style="display:none;">
                    <h6><i class="bi bi-paperclip ms-2"></i>فایل‌های پیوست</h6>
                    <div class="att-list" id="attList"></div>
                </div>

                <!-- دکمه حذف (فقط admin) -->
                <div class="tkt-card" id="deleteCard" style="display:none;">
                    <h6><i class="bi bi-shield-exclamation ms-2"></i>عملیات مدیر</h6>
                    <button class="btn-delete-ticket" onclick="deleteTicket()">
                        <i class="bi bi-trash"></i>حذف تیکت
                    </button>
                </div>
            </div>

            <!-- ═══ ستون دوم: تغییر وضعیت + پاسخ + تاریخچه پیام‌ها ═══ -->
            <div class="tkt-col-left">

                <!-- تغییر وضعیت -->
                <div class="tkt-card" id="statusCard" style="display:none;">
                    <h6><i class="bi bi-arrow-repeat ms-2"></i>تغییر وضعیت</h6>
                    <div class="status-btns" id="statusBtns"></div>
                </div>

                <!-- فرم پاسخ -->
                <div class="tkt-card" id="replyCard">
                    <h6><i class="bi bi-reply ms-2"></i>ارسال پاسخ</h6>
                    <textarea class="reply-textarea" id="replyMsg" placeholder="پاسخ خود را بنویسید..."></textarea>
                    <div class="reply-actions">
                        <button class="btn-reply" id="btnReply" onclick="sendReply()">
                            <i class="bi bi-send"></i>ارسال
                        </button>
                        <label class="reply-file-label">
                            <input type="file" id="replyFileInput" multiple hidden
                                   accept=".jpg,.jpeg,.png,.pdf,.docx,.doc,.xls,.xlsx,.mp3,.m4a,.ogg">
                            <i class="bi bi-paperclip"></i> پیوست فایل
                        </label>
                        <span id="replyFileNames" style="font-size:.78rem; color:#666;"></span>
                    </div>
                </div>

                <!-- مکالمات (تاریخچه پیام‌ها) -->
                <div class="tkt-card">
                    <h6><i class="bi bi-chat-dots ms-2"></i>تاریخچه پیام‌ها</h6>
                    <div id="msgList" class="msg-scroll">
                        <div class="loading-center"><div class="spinner-border spinner-border-sm"></div></div>
                    </div>
                </div>


            </div>
        </div>

    </div>

    <script src="<?= asset('../assets/js/alert.js') ?>"></script>
    <script src="<?= asset('/assets/js/undo-toast.js') ?>"></script>
    <script>
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

            document.getElementById('replyFileInput').addEventListener('change', function() {
                var names = Array.from(this.files).map(function(f) { return f.name; }).join('، ');
                document.getElementById('replyFileNames').textContent = names ? '📎 ' + names : '';
            });
        });

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
                renderMessages(data.messages);
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
            var created = new Date(t.created_at).toLocaleDateString('fa-IR');
            var updated = new Date(t.updated_at).toLocaleDateString('fa-IR');

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
                '</div>';
        }

        function infoRow(label, value) {
            return '<div class="info-row"><span class="lbl">' + label + ':</span><span class="val">' + value + '</span></div>';
        }

        // ─── پیام‌ها ───
        function renderMessages(messages) {
            var el = document.getElementById('msgList');
            if (!messages || messages.length === 0) {
                el.innerHTML = '<p class="text-muted text-center" style="font-size:.85rem;">بدون پیام</p>';
                return;
            }

            var html = '';
            for (var i = 0; i < messages.length; i++) {
                var m = messages[i];
            // ✅ بعد
                var isAdminUser = (m.user_role === 'supervisor' || m.user_role === 'manager' || m.user_id == 1);
                var cls = isAdminUser ? 'msg-admin' : 'msg-user';

                // نشان‌دهنده فرستنده
                var senderBadge = isAdminUser
                    ? '<span style="font-size:.7rem;padding:2px 8px;border-radius:20px;background:#2e7d32;color:#fff;margin-right:6px;">پشتیبانی</span>'
                    : '<span style="font-size:.7rem;padding:2px 8px;border-radius:20px;background:#744ca4;color:#fff;margin-right:6px;">کاربر</span>';
                var time = new Date(m.created_at).toLocaleString('fa-IR');

                html += '<div class="msg-bubble ' + cls + '">';
                html += '<div class="msg-head">';
                html += '<span class="msg-author"><i class="bi bi-person-circle me-1"></i>' + esc(m.user_name) + senderBadge + '</span>';
                html += '<span class="msg-time">' + time + '</span>';
                html += '</div>';
                html += '<div class="msg-text">' + esc(m.message) + '</div>';
                html += '</div>';
            }
            el.innerHTML = html;
        }

        // ─── پیوست‌ها ───
        function renderAttachments(atts) {
            if (!atts || atts.length === 0) return;
            document.getElementById('attCard').style.display = '';

            var html = '';
            for (var i = 0; i < atts.length; i++) {
                var a = atts[i];
                var icon = 'bi-file-earmark';
                if (a.mime_type && a.mime_type.indexOf('image/') === 0) icon = 'bi-file-image';
                else if (a.mime_type && a.mime_type.indexOf('pdf') !== -1) icon = 'bi-file-pdf';

                var sizeKB = (a.file_size / 1024).toFixed(0);
                html += '<div class="att-chip" onclick="downloadFile(' + a.id + ')">';
                html += '<i class="bi ' + icon + '"></i>';
                html += '<span>' + esc(a.original_name) + '</span>';
                html += '<small class="text-muted">(' + sizeKB + ' KB)</small>';
                html += '<i class="bi bi-download" style="color:#744ca4;"></i>';
                html += '</div>';
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

                var fileInput = document.getElementById('replyFileInput');
                if (fileInput.files.length > 0) {
                    for (var i = 0; i < fileInput.files.length; i++) {
                        fd.append('attachments[]', fileInput.files[i]);
                    }
                }

                var res = await fetch('../api/tickets/reply.php', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + authToken },
                    body: fd
                });
                var data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    document.getElementById('replyMsg').value = '';
                    fileInput.value = '';
                    document.getElementById('replyFileNames').textContent = '';
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
    window.open('../api/tickets/download.php?id=' + id + '&token=' + token, '_blank');
}

        // ─── escape HTML ───
        function esc(str) {
            if (!str) return '';
            var d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }
    </script>
</body>
</html>