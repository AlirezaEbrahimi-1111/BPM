<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../includes/version.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تیکت‌ها - سیستم پشتیبانی</title>

    <!-- Bootstrap 5 RTL -->
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>" rel="stylesheet">
    <script src="<?= asset('../assets/js/jalali.js') ?>"></script>
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/jquery.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>

    <style>
        .overview-container {
            max-width: 1200px;
            margin: 78px auto 40px;
            padding: 0 16px;
        }

        /* ───── هدر دو ستونه مثل tasks.php ───── */
        .filters-wrapper.two-col {
            display: flex;
            gap: 24px;
            align-items: flex-start;
            margin-bottom: 0;
        }
        .filters-title-col h1 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 4px;
        }
        .filters-title-col h1 i { color: #744ca4; margin-left: 8px; }
        .filters-title-col p {
            font-size: .84rem;
            color: #888;
            margin: 0;
        }

        /* ───── هدر صفحه (عنوان + دکمه) — قاب‌دار مثل کارت‌های آماری ───── */
        .tickets-pagehead-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px 22px;
            box-shadow: 0 2px 12px rgba(0,0,0,.04);
            border: 1px solid #f0f0f0;
            margin-bottom: 16px;
        }
        .tickets-pagehead {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .tickets-pagehead h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1a1a1a;
            margin: 0 0 4px;
            display: flex;
            align-items: center;
        }
        .tickets-pagehead h1 i { color: #744ca4; margin-left: 8px; }
        .tickets-pagehead p { font-size: .86rem; color: #888; margin: 0; }

        /* ───── آمار — 6 ستون ───── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }
        .stat-card {
            padding: 14px 8px;
            border-radius: 12px;
            text-align: center;
            color: #fff;
            cursor: pointer;
            transition: transform .18s, box-shadow .18s;
            user-select: none;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,.12);
        }
        .stat-card .num { font-size: 1.45rem; font-weight: 700; line-height: 1.2; }
        .stat-card .lbl { font-size: .74rem; opacity: .92; margin-top: 2px; }

        /* ───── فیلتر — همه در یک خط ───── */
        .filters-row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }
        .filters-row .search-box {
            position: relative;
            flex: 1;
            min-width: 180px;
        }
        .filters-row .search-box i {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: .9rem;
        }
        .filters-row .search-box input {
            width: 100%;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 40px 10px 14px;
            font-size: .85rem;
            font-family: inherit;
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
        }
        .filters-row .search-box input:focus {
            outline: none;
            border-color: #744ca4;
            box-shadow: 0 0 0 3px rgba(116,76,164,.1);
        }
        .filter-item select {
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            padding: 9px 14px;
            font-size: .84rem;
            font-family: inherit;
            background: #fff;
            min-width: 130px;
            transition: border-color .2s;
        }
        .filter-item select:focus {
            outline: none;
            border-color: #744ca4;
        }

        /* ───── FAB مثل tasks.php ───── */
        .quick-actions {
            position: fixed;
            bottom: 28px;
            left: 28px;
            z-index: 100;
        }
        .fab {
            width: 52px; height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, #744ca4, #9b6dd7);
            color: #fff;
            border: none;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 16px rgba(116,76,164,.35);
            cursor: pointer;
            transition: transform .2s;
        }
        .fab:hover { transform: scale(1.08); }

        /* ───── دکمه هدر ───── */
        .btn-new-ticket {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #744ca4, #9b6dd7);
            color: #fff;
            border: none;
            padding: 9px 22px;
            border-radius: 10px;
            font-weight: 600;
            font-size: .85rem;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: opacity .2s;
        }
        .btn-new-ticket:hover { opacity: .88; color: #fff; }

        /* ───── ریسپانسیو ───── */
        @media (max-width: 992px) {
            .stats-row { grid-template-columns: repeat(3, 1fr); }
            .filters-wrapper.two-col { flex-direction: column; gap: 12px; }
        }
        @media (max-width: 576px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .overview-container { margin-top: 70px; }
        }

        .tkt-grid-title { color: #1a1a1a; }

        /* ═══ تم تاریک ═══ */
        :root[data-theme="dark"] .tickets-pagehead-card {
            background: var(--surface);
            border-color: var(--border-soft);
        }
        :root[data-theme="dark"] .tkt-grid-title {
            color: var(--text-strong);
        }
        :root[data-theme="dark"] .filters-title-col h1,
        :root[data-theme="dark"] .tickets-pagehead h1 {
            color: var(--text-strong);
        }
        :root[data-theme="dark"] .filters-title-col p,
        :root[data-theme="dark"] .tickets-pagehead p {
            color: var(--text-muted);
        }
        :root[data-theme="dark"] .filters-row .search-box input,
        :root[data-theme="dark"] .filter-item select {
            background: var(--surface);
            border-color: var(--border-soft);
            color: var(--text-strong);
        }
        :root[data-theme="dark"] .filters-row .search-box i {
            color: var(--text-muted);
        }
        :root[data-theme="dark"] #emptyState i {
            color: var(--border-soft) !important;
        }
        :root[data-theme="dark"] #emptyState h6 {
            color: var(--text-muted) !important;
        }
        :root[data-theme="dark"] #emptyState p {
            color: var(--text-muted) !important;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container">

        <div class="tickets-pagehead-card">
            <div class="tickets-pagehead">
                <div class="tickets-pagehead-text">
                    <h1><i class="bi bi-ticket-detailed"></i> تیکت‌های پشتیبانی</h1>
                    <p>مشاهده و مدیریت تیکت‌ها</p>
                </div>
                <a href="create-ticket.php" class="btn-new-ticket">
                    <i class="bi bi-plus-circle"></i>تیکت جدید
                </a>
            </div>
        </div>

        <div class="filters-wrapper">
                <!-- آمار — 6 ستون -->
                <div class="stats-row" id="statsRow">
                    <div class="stat-card" style="background:linear-gradient(135deg,#6366f1,#818cf8);" onclick="filterByStatus('')">
                        <div class="num" id="sTotal">–</div><div class="lbl">کل</div>
                    </div>
                    <div class="stat-card" style="background:linear-gradient(135deg,#3b82f6,#60a5fa);" onclick="filterByStatus('open')">
                        <div class="num" id="sOpen">–</div><div class="lbl">باز</div>
                    </div>
                    <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#fbbf24);" onclick="filterByStatus('in_progress')">
                        <div class="num" id="sProgress">–</div><div class="lbl">در حال بررسی</div>
                    </div>
                    <div class="stat-card" style="background:linear-gradient(135deg,#8b5cf6,#a78bfa);" onclick="filterByStatus('waiting_reply')">
                        <div class="num" id="sWaiting">–</div><div class="lbl">منتظر پاسخ</div>
                    </div>
                    <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#34d399);" onclick="filterByStatus('resolved')">
                        <div class="num" id="sResolved">–</div><div class="lbl">حل شده</div>
                    </div>
                    <div class="stat-card" style="background:linear-gradient(135deg,#6b7280,#9ca3af);" onclick="filterByStatus('closed')">
                        <div class="num" id="sClosed">–</div><div class="lbl">بسته شده</div>
                    </div>
                </div>

                <div class="filters-row">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="fSearch" placeholder="جستجو در عنوان یا شماره تیکت..." oninput="debounceSearch()">
                    </div>
                    <div class="filter-item">
                        <select id="fStatus" onchange="loadTickets()">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="open">باز</option>
                            <option value="in_progress">در حال بررسی</option>
                            <option value="waiting_reply">منتظر پاسخ</option>
                            <option value="resolved">حل شده</option>
                            <option value="closed">بسته شده</option>
                            <option value="cancelled">لغو شده</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <select id="fPriority" onchange="loadTickets()">
                            <option value="">همه اولویت‌ها</option>
                            <option value="low">کم</option>
                            <option value="medium">متوسط</option>
                            <option value="high">بالا</option>
                            <option value="critical">بحرانی</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <select id="fCategory" onchange="loadTickets()">
                            <option value="">همه دسته‌بندی‌ها</option>
                        </select>
                    </div>
                </div>
        </div>

        <!-- جدول AG Grid مثل tasks.php -->
        <div id="myGrid" class="ag-theme-alpine" style="height: 580px; width: 100%; padding-top: 1rem;"></div>

        <!-- حالت خالی -->
        <div id="emptyState" class="empty-state" style="display:none;">
            <i class="bi bi-ticket-detailed" style="font-size:2.6rem;color:#ddd;"></i>
            <h6 style="margin-top:12px;color:#777;">هیچ تیکتی یافت نشد</h6>
            <p style="font-size:.85rem;color:#999;">اولین تیکت خود را ایجاد کنید</p>
        </div>
    </div>

    <!-- FAB مثل tasks.php -->
    <div class="quick-actions">
        <button class="fab" onclick="location.href='create-ticket.php'" title="تیکت جدید">
            <i class="bi bi-plus"></i>
        </button>
    </div>

    <script src="<?= asset('../assets/js/alert.js') ?>"></script>
    <script src="<?= asset('/assets/js/undo-toast.js') ?>"></script>
    <script>
    (function(){
        'use strict';

        let curPage = 1;
        let searchTimer = null;
        let gridApi = null;
        let allTickets = [];
        let currentUserId = null;

        // شناسایی کاربر فعلی
        try {
            const userInfo = JSON.parse(localStorage.getItem('user_info') || '{}');
            currentUserId = userInfo.id || null;
        } catch(e) {}

        /* ── ستون‌های AG Grid ── */
        const columnDefs = [
            {
                field: 'ticket_number', headerName: 'شماره', width: 110, sortable: true, resizable: true,
                cellRenderer: p => '<span style="font-family:monospace;direction:ltr;display:inline-block;color:#888;">' + esc(p.value) + '</span>'
            },
            {
                field: 'subject', headerName: 'عنوان', flex: 2, sortable: true, resizable: true,
                cellRenderer: p => {
                    let html = '<span class="tkt-grid-title" style="font-weight:600;">' + esc(p.value) + '</span>';
                    const t = p.data;
                    if (t.message_count > 1 || t.attachment_count > 0) {
                        html += '<span style="display:inline-flex;gap:8px;margin-right:8px;">';
                        if (t.message_count > 1) html += '<small style="color:#999;font-size:.78rem;"><i class="bi bi-chat-dots"></i> ' + t.message_count + '</small>';
                        if (t.attachment_count > 0) html += '<small style="color:#999;font-size:.78rem;"><i class="bi bi-paperclip"></i> ' + t.attachment_count + '</small>';
                        html += '</span>';
                    }
                    return html;
                }
            },
            {
                field: 'status_label', headerName: 'وضعیت', width: 130, resizable: true,
                cellRenderer: p => {
                    const c = p.data.status_color || '#888';
                    return '<span style="padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:600;background:' + c + '18;color:' + c + ';border:1px solid ' + c + '35;">' + esc(p.value) + '</span>';
                }
            },
            {
                field: 'priority_label', headerName: 'اولویت', width: 100, resizable: true,
                cellRenderer: p => {
                    const c = p.data.priority_color || '#888';
                    return '<span style="padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:600;background:' + c + '18;color:' + c + ';border:1px solid ' + c + '35;">' + esc(p.value) + '</span>';
                }
            },
            { field: 'category_name', headerName: 'دسته‌بندی', flex: 1, sortable: true, resizable: true, cellRenderer: p => esc(p.value || '–') },
            { field: 'creator_name', headerName: 'ایجادکننده', flex: 1, sortable: true, resizable: true, cellRenderer: p => esc(p.value || '–') },
            {
                field: 'created_at', headerName: 'تاریخ', width: 130, sortable: true, resizable: true,
                cellRenderer: p => {
                    const d = p.value ? new Date(p.value).toLocaleDateString('fa-IR') : '–';
                    const rel = relTime(p.value);
                    return '<span style="font-size:.84rem;">' + d + '</span>' + (rel ? '<br><span style="font-size:.72rem;color:#aaa;">' + rel + '</span>' : '');
                }
            },
        ];

        // اگر کاربر id=1 هست، ستون حذف اضافه کن
        if (currentUserId == 1) {
            columnDefs.push({
                headerName: '', width: 60, sortable: false, resizable: false,
                cellRenderer: p => {
                    return '<button onclick="event.stopPropagation(); deleteTicket(' + p.data.id + ')" title="حذف" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:1rem;padding:4px;"><i class="bi bi-trash"></i></button>';
                }
            });
        }

        // 🆕 هماهنگ با تمِ فعلی — چون Theming API جدید AG Grid، متغیرهای CSS تمِ سراسری را نمی‌خواند
        const isDarkTheme = document.documentElement.getAttribute('data-theme') === 'dark';
        const gridOptions = {
            theme: agGrid.themeQuartz.withParams(isDarkTheme ? {
                fontFamily: 'Tahoma, Vazirmatn, sans-serif',
                fontSize: 13,
                backgroundColor: '#1b2130',
                foregroundColor: '#e8eaed',
                rowHoverColor: '#232a3a',
                headerBackgroundColor: '#232a3a',
                borderColor: '#2b3242',
                oddRowBackgroundColor: '#1b2130',
            } : {
                fontFamily: 'Tahoma, Vazirmatn, sans-serif',
                fontSize: 13,
                rowHoverColor: '#faf5ff',
                headerBackgroundColor: '#f8f9fa',
            }),
            columnDefs: columnDefs,
            rowData: [],
            enableRtl: true,
            animateRows: true,
            pagination: true,
            paginationPageSize: 20,
            paginationPageSizeSelector: [15, 20, 30, 50],
            defaultColDef: { sortable: true, resizable: true },
            overlayLoadingTemplate: '<div style="display:flex;flex-direction:column;align-items:center;gap:10px;color:#744ca4;font-size:.85rem;"><div class="spinner-border" style="width:2.2rem;height:2.2rem;" role="status"></div><span>در حال بارگذاری...</span></div>',
            onRowClicked: params => { location.href = 'ticket-detail.php?id=' + params.data.id; },
            onPaginationChanged: () => {
                setTimeout(() => {
                    document.querySelectorAll('.ag-paging-panel span, .ag-paging-panel button').forEach(el => {
                        if (el.childElementCount === 0 && !el.classList.contains('injected-az')) {
                            el.textContent = el.textContent
                                .replace(/Page/g, 'صفحه')
                                .replace(/\bof\b/g, 'از')
                                .replace(/\bto\b/g, 'تا')
                                .replace(/\d+/g, n => n.replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]));
                        }
                    });
                    document.querySelectorAll('.ag-paging-panel > span, .ag-paging-panel > div:not(.ag-paging-row-summary-panel):not(.ag-paging-page-size):not(.ag-paging-button-wrapper):not(.ag-paging-page-summary-panel)').forEach(el => {
                        if (el.textContent.trim() === 'از') el.remove();
                    });
                    const summary = document.querySelector('.ag-paging-row-summary-panel');
                    if (summary) {
                        summary.querySelectorAll('.injected-az').forEach(el => el.remove());
                        const azSpan = document.createElement('span');
                        azSpan.textContent = 'از ';
                        azSpan.className = 'injected-az';
                        summary.insertBefore(azSpan, summary.firstChild);
                    }
                }, 100);
            },
        };

        gridApi = agGrid.createGrid(document.getElementById('myGrid'), gridOptions);
        gridApi.showLoadingOverlay();

        document.addEventListener('DOMContentLoaded', function(){
            if (!authToken) { window.location.href = '../index.php'; return; }
            loadCategories();
            loadTickets();
        });

        /* ── دسته‌بندی‌ها ── */
        async function loadCategories(){
            try {
                const res = await fetch('../api/tickets/categories.php', {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });
                const data = await res.json();

                // ساپورت هر دو فرمت پاسخ
                var cats = [];
                if (Array.isArray(data)) {
                    cats = data;
                } else if (data.success && data.categories) {
                    cats = data.categories;
                } else if (data.data && Array.isArray(data.data)) {
                    cats = data.data;
                } else if (data.categories) {
                    cats = data.categories;
                }

                const sel = document.getElementById('fCategory');
                cats.forEach(function(c){
                    if (c.is_active === undefined || c.is_active == 1 || c.is_active === true){
                        var opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.name;
                        sel.appendChild(opt);
                    }
                });
            } catch(e){ console.error('loadCategories:', e); }
        }

        /* ── بارگذاری تیکت‌ها ── */
        window.loadTickets = async function(page){
            if (page) curPage = page;

            var qs = new URLSearchParams({
                page: curPage,
                limit: 200,
                status:   document.getElementById('fStatus').value,
                priority: document.getElementById('fPriority').value,
                category: document.getElementById('fCategory').value,
                search:   document.getElementById('fSearch').value
            });

            try {
                var res = await fetch('../api/tickets/list.php' + '?' + qs.toString(), {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });
                var data = await res.json();

                if (data.success){
                    renderStats(data.stats);
                    allTickets = data.tickets || [];

                    if (allTickets.length === 0) {
                        document.getElementById('myGrid').style.display = 'none';
                        document.getElementById('emptyState').style.display = 'block';
                    } else {
                        document.getElementById('myGrid').style.display = '';
                        document.getElementById('emptyState').style.display = 'none';
                        if (gridApi) gridApi.setGridOption('rowData', allTickets);
                    }
                } else {
                    showToast(data.message || 'خطا در بارگذاری', 'error');
                }
            } catch(e){
                console.error('loadTickets:', e);
            }
        };

        /* ── آمار ── */
        function renderStats(s){
            document.getElementById('sTotal').textContent    = s.total || 0;
            document.getElementById('sOpen').textContent     = s.open_count || 0;
            document.getElementById('sProgress').textContent = s.in_progress_count || 0;
            document.getElementById('sWaiting').textContent  = s.waiting_reply_count || 0;
            document.getElementById('sResolved').textContent = s.resolved_count || 0;
            document.getElementById('sClosed').textContent   = s.closed_count || 0;
        }

        /* ── فیلتر با وضعیت ── */
        window.filterByStatus = function(s){
            document.getElementById('fStatus').value = s;
            curPage = 1;
            loadTickets();
        };

        /* ── debounce جستجو ── */
        window.debounceSearch = function(){
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function(){ curPage = 1; loadTickets(); }, 400);
        };

        /* ── بازگرداندن تیکت ── */
        window.restoreTicket = async function(ticketId) {
            try {
                var res = await fetch('../api/tickets/restore.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                    body: JSON.stringify({ ticket_id: ticketId })
                });
                var data = await res.json();
                if (data.success) loadTickets();
                else showToast(data.message || 'بازگرداندن انجام نشد', 'error');
            } catch (e) {
                console.error('restoreTicket:', e);
                showToast('خطا در ارتباط با سرور', 'error');
            }
        };

        /* ── حذف تیکت (فقط user_id=1) ── */
        window.deleteTicket = function(ticketId) {
            uiConfirm('آیا از حذف این تیکت اطمینان دارید؟', async function () {
                try {
                    var res = await fetch('../api/tickets/delete.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({ ticket_id: ticketId })
                    });
                    var data = await res.json();

                    if (data.success) {
                        loadTickets();
                        showUndoToast({
                            title: 'حذف تیکت',
                            message: 'تیکت حذف شد',
                            duration: 6000,
                            onUndo: () => restoreTicket(ticketId)
                        });
                    } else {
                        showToast(data.message || 'خطا در حذف تیکت', 'error');
                    }
                } catch(e) {
                    console.error('deleteTicket:', e);
                    showToast('خطا در ارتباط با سرور', 'error');
                }
            }, { danger: true, yesText: 'بله، حذف', noText: 'انصراف' });
        };

        /* ── helpers ── */
        function relTime(d) {
            if (!d) return '';
            const ms = Date.now() - new Date(d);
            const m = Math.floor(ms / 6e4), h = Math.floor(ms / 36e5), dy = Math.floor(ms / 864e5);
            if (m < 60) return toPersian(m) + ' دقیقه پیش';
            if (h < 24) return toPersian(h) + ' ساعت پیش';
            if (dy < 7) return toPersian(dy) + ' روز پیش';
            if (dy < 30) return toPersian(Math.floor(dy / 7)) + ' هفته پیش';
            return toPersian(Math.floor(dy / 30)) + ' ماه پیش';
        }
        function toPersian(n) { return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }

        function esc(str){
            if (!str) return '';
            var d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

    })();
    </script>
</body>
</html>