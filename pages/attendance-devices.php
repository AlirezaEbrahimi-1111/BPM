<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../config/config.php';
require_once '../includes/version.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت دستگاه‌های حضور و غیاب</title>
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">

    <style>
        /* ─── Layout ─── */
        .ad-container {
            max-width: 1140px;
            margin: 0 auto;
            padding: 1.5rem 1rem 3rem;
        }

        /* ─── Hero Header ─── */
        .ad-hero {
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            padding: 1.75rem 2rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 8px 32px rgba(99, 102, 241, 0.22);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .ad-hero-text h1 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #fff;
            margin: 0 0 .35rem;
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        .ad-hero-text p {
            color: rgba(255, 255, 255, .82);
            font-size: .85rem;
            margin: 0;
        }

        .ad-hero-stats {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .ad-stat-pill {
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 999px;
            padding: .4rem 1rem;
            display: flex;
            align-items: center;
            gap: .45rem;
            color: #fff;
            font-size: .82rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            white-space: nowrap;
        }

        .ad-stat-pill i { font-size: .95rem; opacity: .85; }

        /* ─── Tabs ─── */
        .ad-tabs-wrap {
            background: #fff;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 1.25rem;
        }

        .ad-tabs {
            display: flex;
            border-bottom: 2px solid var(--gray-100);
            padding: 0 1rem;
            gap: 0;
        }

        .ad-tab-btn {
            background: none;
            border: none;
            border-bottom: 2.5px solid transparent;
            margin-bottom: -2px;
            padding: .95rem 1.25rem;
            font-size: .875rem;
            font-weight: 600;
            color: var(--gray-500);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: .45rem;
            transition: all .18s ease;
            white-space: nowrap;
        }

        .ad-tab-btn:hover { color: var(--primary); }

        .ad-tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .ad-tab-btn .tab-count {
            background: var(--gray-100);
            color: var(--gray-500);
            font-size: .7rem;
            font-weight: 700;
            padding: .1rem .45rem;
            border-radius: 999px;
            min-width: 20px;
            text-align: center;
            transition: all .18s;
        }

        .ad-tab-btn.active .tab-count {
            background: rgba(99,102,241,.12);
            color: var(--primary);
        }

        /* ─── Tab Panels ─── */
        .ad-panel {
            background: #fff;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            display: none;
        }

        .ad-panel.active { display: block; }

        .ad-panel-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--gray-100);
            flex-wrap: wrap;
        }

        .ad-panel-title {
            font-size: .9rem;
            font-weight: 700;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .ad-panel-title i { color: var(--primary); font-size: 1rem; }

        /* ─── AG Grid container ─── */
        #devGrid, #logGrid {
            width: 100%;
            height: 460px;
        }

        /* ─── Badges ─── */
        .ad-badge {
            font-size: .72rem;
            font-weight: 700;
            padding: .22rem .7rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
        }

        .ad-pending  { color: #b45309;}
        .ad-approved { color: #15803d;}
        .ad-rejected { color: #b91c1c;}
        .ad-active   { color: #15803d;}
        .ad-inactive {color: var(--gray-500);}

        /* ─── Action icon buttons ─── */
        .ad-icon-btn {
            border: 1px solid var(--gray-200);
            background: #fff;
            width: 32px;
            height: 32px;
            border-radius: var(--radius-xs);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--gray-500);
            font-size: .88rem;
            transition: all .15s ease;
            vertical-align: middle;
        }

        .ad-icon-btn:hover         { transform: translateY(-1px); }
        .ad-icon-btn.ok:hover      { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
        .ad-icon-btn.no:hover      { background: #fef9ec; color: #b45309; border-color: #fde68a; }
        .ad-icon-btn.del:hover     { background: #fff1f2; color: #dc2626; border-color: #fecdd3; }
        .ad-icon-btn.edit:hover    { background: #eef2ff; color: var(--primary); border-color: #c7d2fe; }
        .ad-icon-btn.toggle:hover  { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }

        /* ─── IP Section ─── */
        .ip-add-card {
            background: var(--gray-50);
            border: 1px dashed var(--gray-300);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            margin: 1.25rem;
            margin-bottom: .75rem;
        }

        .ip-add-card label {
            font-size: .8rem;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: .35rem;
            display: block;
        }

        .ip-add-inputs {
            display: flex;
            gap: .6rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .ip-add-inputs .ip-field { flex: 1; min-width: 160px; }

        .ip-add-inputs input {
            width: 100%;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            padding: .55rem .85rem;
            font-size: .875rem;
            font-family: 'Vazirmatn', sans-serif;
            color: var(--gray-800);
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
            outline: none;
        }

        .ip-add-inputs input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99,102,241,.1);
        }

        .ip-add-inputs input[id="newIp"] { direction: ltr; font-family: monospace, 'Vazirmatn', sans-serif; }

        .ip-list-wrap { padding: .25rem 1.25rem 1.25rem; }

        .ip-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .75rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            margin-bottom: .5rem;
            background: #fff;
            transition: box-shadow .15s, border-color .15s;
        }

        .ip-row:hover {
            border-color: var(--gray-300);
            box-shadow: var(--shadow-sm);
        }

        .ip-row-left { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

        .ip-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--success); flex-shrink: 0;
        }

        .ip-dot.inactive { background: var(--gray-300); }

        .ip-main {
            font-weight: 700;
            color: var(--gray-800);
            direction: ltr;
            font-family: 'Courier New', monospace;
            font-size: .9rem;
            letter-spacing: .5px;
        }

        .ip-label-text {
            color: var(--gray-500);
            font-size: .82rem;
        }

        .ip-actions { display: flex; gap: .35rem; }

        /* ─── Log Toolbar ─── */
        .log-filter-wrap {
            display: flex;
            align-items: center;
            gap: .6rem;
            flex-wrap: wrap;
        }

        .log-filter-wrap select {
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            padding: .5rem .875rem;
            font-size: .875rem;
            font-family: 'Vazirmatn', sans-serif;
            color: var(--gray-700);
            background: #fff;
            outline: none;
            cursor: pointer;
            transition: border-color .15s;
        }

        .log-filter-wrap select:focus { border-color: var(--primary); }

        /* ─── Empty States ─── */
        .ad-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
            color: var(--gray-400);
            gap: .5rem;
        }

        .ad-empty i { font-size: 2.5rem; opacity: .4; }
        .ad-empty p { font-size: .875rem; margin: 0; }

        /* ─── Responsive ─── */
        @media (max-width: 640px) {
            .ad-hero { padding: 1.25rem; }
            .ad-hero-text h1 { font-size: 1.1rem; }
            .ad-stat-pill { font-size: .78rem; padding: .35rem .8rem; }
            .ad-tab-btn { padding: .75rem .85rem; font-size: .82rem; }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="ad-container">

        <!-- Hero Header -->
        <div class="ad-hero">
            <div class="ad-hero-text">
                <h1><i class="bi bi-shield-lock-fill"></i> مدیریت دستگاه‌های حضور و غیاب</h1>
                <p>تأیید دستگاه‌های مجاز، مدیریت آی‌پی‌های سازمان و پایش تلاش‌های ناموفق</p>
            </div>
            <div class="ad-hero-stats">
                <div class="ad-stat-pill" id="statDevices">
                    <i class="bi bi-display"></i>
                    <span id="statDeviceCount">—</span> دستگاه ثبت‌شده
                </div>
                <div class="ad-stat-pill" id="statPending">
                    <i class="bi bi-hourglass-split"></i>
                    <span id="statPendingCount">—</span> در انتظار تأیید
                </div>
                <div class="ad-stat-pill" id="statIps">
                    <i class="bi bi-ethernet"></i>
                    <span id="statIpCount">—</span> آی‌پی مجاز
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="ad-tabs-wrap">
            <div class="ad-tabs" id="adTabs">
                <button class="ad-tab-btn active" data-tab="devices" onclick="switchTab('devices')">
                    <i class="bi bi-display"></i>
                    دستگاه‌ها
                    <span class="tab-count" id="tabCountDevices">—</span>
                </button>
                <button class="ad-tab-btn" data-tab="ips" onclick="switchTab('ips')">
                    <i class="bi bi-ethernet"></i>
                    آی‌پی‌های مجاز
                    <span class="tab-count" id="tabCountIps">—</span>
                </button>
                <button class="ad-tab-btn" data-tab="log" onclick="switchTab('log')">
                    <i class="bi bi-exclamation-triangle"></i>
                    تلاش‌های ناموفق
                    <span class="tab-count" id="tabCountLog">—</span>
                </button>
            </div>
        </div>

        <!-- Panel: Devices -->
        <div class="ad-panel active" id="tab-devices">
            <div class="ad-panel-toolbar">
                <div class="ad-panel-title">
                    <i class="bi bi-display"></i>
                    لیست دستگاه‌های ثبت‌شده
                </div>
                <button class="btn btn-sm btn-outline-primary" onclick="loadDevices()">
                    <i class="bi bi-arrow-clockwise"></i>
                    بارگذاری مجدد
                </button>
            </div>
            <div id="devGrid" class="ag-theme-alpine"></div>
        </div>

        <!-- Panel: Allowed IPs -->
        <div class="ad-panel" id="tab-ips">
            <div class="ad-panel-toolbar">
                <div class="ad-panel-title">
                    <i class="bi bi-ethernet"></i>
                    آی‌پی‌های مجاز سازمان
                </div>
                <button class="btn btn-sm btn-outline-primary" onclick="loadIps()">
                    <i class="bi bi-arrow-clockwise"></i>
                    بارگذاری مجدد
                </button>
            </div>

            <div class="ip-add-card">
                <div class="ip-add-inputs">
                    <div class="ip-field">
                        <label>آدرس IP</label>
                        <input type="text" id="newIp" placeholder="مثلاً 192.168.1.1">
                    </div>
                    <div class="ip-field">
                        <label>برچسب (اختیاری)</label>
                        <input type="text" id="newIpLabel" placeholder="مثلاً فروشگاه مرکزی">
                    </div>
                    <div style="padding-bottom:0">
                        <label style="opacity:0;display:block">‌</label>
                        <button class="btn btn-primary btn-sm" onclick="addIp()">
                            <i class="bi bi-plus-circle"></i>
                            افزودن
                        </button>
                    </div>
                </div>
            </div>

            <div class="ip-list-wrap" id="ipList">
                <div class="ad-empty">
                    <i class="bi bi-ethernet"></i>
                    <p>در حال بارگذاری...</p>
                </div>
            </div>
        </div>

        <!-- Panel: Failed Logs -->
        <div class="ad-panel" id="tab-log">
            <div class="ad-panel-toolbar">
                <div class="ad-panel-title">
                    <i class="bi bi-exclamation-triangle"></i>
                    تلاش‌های ورود/خروج ناموفق
                </div>
                <div class="log-filter-wrap">
                    <select id="logRange" onchange="loadLog()">
                        <option value="today">امروز</option>
                        <option value="week">هفتهٔ اخیر</option>
                        <option value="all" selected>همه</option>
                    </select>
                    <button class="btn btn-sm btn-danger" onclick="clearLog()">
                        <i class="bi bi-trash"></i>
                        پاک‌کردن
                    </button>
                </div>
            </div>
            <div id="logGrid" class="ag-theme-alpine"></div>
        </div>

    </div><!-- /.ad-container -->

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>

    <script>
        if (!authToken) window.location.href = '../index.php'; 

        let devGridApi = null, logGridApi = null, logBuilt = false;

        /* ─── Helpers ─── */
        function faNum(x) {
            const fa = '۰۱۲۳۴۵۶۷۸۹';
            return String(x ?? '').replace(/[0-9]/g, d => fa[d]);
        }

        function esc(s) {
            return String(s ?? '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function pad(n) { return n < 10 ? '0' + n : '' + n; }

        function faTime(iso) {
            if (!iso) return '—';
            try {
                const d = new Date(iso.replace(' ', 'T'));
                if (isNaN(d)) return faNum(iso);
                return faNum(d.toLocaleDateString('fa-IR') + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()));
            } catch { return faNum(iso); }
        }

        function notify(msg, type) {
            if (typeof showToast === 'function') showToast(msg, type || 'info');
            else alert(msg);
        }

        async function api(url, opts) {
            opts = opts || {};
            opts.headers = Object.assign({ 'Authorization': 'Bearer ' + authToken }, opts.headers || {});
            const r = await fetch(url, opts);
            const t = await r.text();
            try { return JSON.parse(t); } catch { throw new Error('پاسخ نامعتبر سرور'); }
        }

        function updateStat(id, val) {
            const el = document.getElementById(id);
            if (el) el.textContent = faNum(val);
        }

        /* ─── Tab switching ─── */
        function switchTab(tab) {
            document.querySelectorAll('.ad-tab-btn').forEach(b =>
                b.classList.toggle('active', b.dataset.tab === tab));
            document.querySelectorAll('.ad-panel').forEach(p => p.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');

            if (tab === 'ips') loadIps();
            if (tab === 'log') {
                if (!logBuilt) { initLogGrid(); logBuilt = true; }
                loadLog();
            }
        }

        /* ════════════════════════════════
           DEVICES
        ════════════════════════════════ */
        function statusBadge(s) {
            if (s === 'approved') return '<span class="ad-badge ad-approved"><i class="bi bi-check-circle-fill"></i>تأییدشده</span>';
            if (s === 'rejected') return '<span class="ad-badge ad-rejected"><i class="bi bi-x-circle-fill"></i>ردشده</span>';
            return '<span class="ad-badge ad-pending"><i class="bi bi-hourglass-split"></i>در انتظار</span>';
        }

        function devActions(p) {
            const d = p.data;
            let h = '<div style="display:flex;gap:4px;align-items:center;height:100%">';
            if (d.status !== 'approved')
                h += `<span class="ad-icon-btn ok" title="تأیید" onclick="devAction(${d.id},'approve')"><i class="bi bi-check-lg"></i></span>`;
            if (d.status !== 'rejected')
                h += `<span class="ad-icon-btn no" title="رد" onclick="devAction(${d.id},'reject')"><i class="bi bi-slash-circle"></i></span>`;
            h += `<span class="ad-icon-btn edit" title="ویرایش برچسب" onclick="relabel(${d.id})"><i class="bi bi-pencil"></i></span>`;
            h += `<span class="ad-icon-btn del" title="حذف" onclick="devAction(${d.id},'delete')"><i class="bi bi-trash"></i></span>`;
            h += '</div>';
            return h;
        }

        function initDevGrid() {
            devGridApi = agGrid.createGrid(document.getElementById('devGrid'), {
                enableRtl: true,
                rowHeight: 52,
                headerHeight: 44,
                defaultColDef: {
                    sortable: true,
                    resizable: true,
                    cellStyle: { display: 'flex', alignItems: 'center' }
                },
                columnDefs: [
                    {
                        headerName: 'برچسب',
                        field: 'label',
                        flex: 1,
                        minWidth: 130,
                        cellRenderer: p => {
                            const lbl = (p.value || '').trim();
                            return lbl
                                ? `<span style="font-weight:600;color:var(--gray-800)">${esc(lbl)}</span>`
                                : `<span style="color:var(--gray-400);font-style:italic">بدون برچسب</span>`;
                        }
                    },
                    {
                        headerName: 'وضعیت',
                        field: 'status',
                        width: 135,
                        cellRenderer: p => statusBadge(p.value)
                    },
                    {
                        headerName: 'IP ثبت‌نام',
                        field: 'first_seen_ip',
                        width: 148,
                        cellRenderer: p => `<code style="direction:ltr;background:var(--gray-100);padding:.15rem .5rem;border-radius:6px;font-size:.8rem;color:var(--gray-700)">${esc(p.value || '—')}</code>`
                    },
                    {
                        headerName: 'اولین کاربر',
                        field: 'first_seen_user_name',
                        flex: 1,
                        minWidth: 120,
                        cellRenderer: p => {
                            const name = (p.value || '').trim();
                            return name
                                ? `<span style="display:flex;align-items:center;gap:.4rem"><i class="bi bi-person" style="color:var(--primary);font-size:.9rem"></i>${esc(name)}</span>`
                                : '—';
                        }
                    },
                    {
                        headerName: 'آخرین استفاده',
                        field: 'last_used_at',
                        width: 155,
                        cellRenderer: p => `<span style="color:var(--gray-600);font-size:.82rem">${faTime(p.value)}</span>`
                    },
                    {
                        headerName: 'تاریخ ثبت',
                        field: 'created_at',
                        width: 155,
                        cellRenderer: p => `<span style="color:var(--gray-500);font-size:.82rem">${faTime(p.value)}</span>`
                    },
                    {
                        headerName: 'عملیات',
                        width: 148,
                        sortable: false,
                        cellRenderer: devActions
                    }
                ],
                overlayNoRowsTemplate: `
                    <div style="display:flex;flex-direction:column;align-items:center;gap:.5rem;padding:3rem;color:var(--gray-400)">
                        <i class="bi bi-display" style="font-size:2.5rem;opacity:.35"></i>
                        <p style="margin:0;font-size:.875rem">دستگاهی ثبت نشده است</p>
                    </div>`,
                overlayLoadingTemplate: '<div style="display:flex;flex-direction:column;align-items:center;gap:10px;color:#744ca4;font-size:.85rem;"><div class="spinner-border" style="width:2.2rem;height:2.2rem;" role="status"></div><span>در حال بارگذاری...</span></div>'
            });
            devGridApi.showLoadingOverlay();
        }

        async function loadDevices() {
            try {
                const d = await api('../api/attendance/devices.php');
                if (d.success) {
                    const rows = d.devices || [];
                    devGridApi.setGridOption('rowData', rows);
                    const pending = rows.filter(r => r.status === 'pending').length;
                    updateStat('statDeviceCount', rows.length);
                    updateStat('statPendingCount', pending);
                    updateStat('tabCountDevices', rows.length);
                } else {
                    notify(d.message || 'خطا در بارگذاری دستگاه‌ها', 'error');
                }
            } catch (e) { notify('خطا: ' + e.message, 'error'); }
        }

        function devAction(id, action) {
            const run = async function () {
                try {
                    const d = await api('../api/attendance/devices.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action, id })
                    });
                    if (d.success) { notify(d.message || 'انجام شد', 'success'); loadDevices(); }
                    else notify(d.message || 'خطا', 'error');
                } catch (e) { notify('خطا: ' + e.message, 'error'); }
            };
            if (action === 'delete') {
                uiConfirm('این دستگاه حذف شود؟', run, { danger: true, yesText: 'بله، حذف', noText: 'انصراف' });
            } else {
                run();
            }
        }

        function relabel(id) {
            uiPrompt('برچسب جدید برای این دستگاه (مثلاً: صندوق ۱ - فروشگاه مرکزی)', async function (label) {
                try {
                    const d = await api('../api/attendance/devices.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'relabel', id, label })
                    });
                    if (d.success) { notify('برچسب ذخیره شد', 'success'); loadDevices(); }
                    else notify(d.message || 'خطا', 'error');
                } catch (e) { notify('خطا: ' + e.message, 'error'); }
            }, { placeholder: 'برچسب دستگاه...', okText: 'ذخیره' });
        }

        /* ════════════════════════════════
           ALLOWED IPs
        ════════════════════════════════ */
        async function loadIps() {
            const box = document.getElementById('ipList');
            box.innerHTML = `<div class="ad-empty"><i class="bi bi-ethernet"></i><p>در حال بارگذاری...</p></div>`;
            try {
                const d = await api('../api/attendance/allowed-ips.php');
                if (!d.success) { notify(d.message || 'خطا', 'error'); return; }
                const ips = d.ips || [];
                updateStat('statIpCount', ips.length);
                updateStat('tabCountIps', ips.length);
                if (!ips.length) {
                    box.innerHTML = `<div class="ad-empty"><i class="bi bi-ethernet"></i><p>هیچ آی‌پی مجازی ثبت نشده است</p></div>`;
                    return;
                }
                box.innerHTML = ips.map(ip => `
                    <div class="ip-row">
                        <div class="ip-row-left">
                            <span class="ip-dot${ip.is_active == 1 ? '' : ' inactive'}"></span>
                            <span class="ip-main">${esc(ip.ip_address)}</span>
                            ${ip.label ? `<span class="ip-label-text">${esc(ip.label)}</span>` : ''}
                            ${ip.is_active == 1
                                ? '<span class="ad-badge ad-active">فعال</span>'
                                : '<span class="ad-badge ad-inactive">غیرفعال</span>'}
                        </div>
                        <div class="ip-actions">
                            <span class="ad-icon-btn toggle" title="${ip.is_active == 1 ? 'غیرفعال‌کردن' : 'فعال‌کردن'}" onclick="toggleIp(${ip.id})">
                                <i class="bi bi-power"></i>
                            </span>
                            <span class="ad-icon-btn del" title="حذف" onclick="deleteIp(${ip.id})">
                                <i class="bi bi-trash"></i>
                            </span>
                        </div>
                    </div>`).join('');
            } catch (e) { notify('خطا: ' + e.message, 'error'); }
        }

        async function addIp() {
            const ip = document.getElementById('newIp').value.trim();
            const label = document.getElementById('newIpLabel').value.trim();
            if (!ip) { notify('آدرس IP را وارد کنید', 'warning'); return; }
            try {
                const d = await api('../api/attendance/allowed-ips.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'add', ip_address: ip, label })
                });
                if (d.success) {
                    notify(d.message || 'ثبت شد', 'success');
                    document.getElementById('newIp').value = '';
                    document.getElementById('newIpLabel').value = '';
                    loadIps();
                } else { notify(d.message || 'خطا', 'error'); }
            } catch (e) { notify('خطا: ' + e.message, 'error'); }
        }

        async function toggleIp(id) {
            try {
                const d = await api('../api/attendance/allowed-ips.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'toggle', id })
                });
                if (d.success) { notify(d.message, 'success'); loadIps(); }
                else notify(d.message || 'خطا', 'error');
            } catch (e) { notify('خطا: ' + e.message, 'error'); }
        }

        function deleteIp(id) {
            uiConfirm('این آی‌پی حذف شود؟', async function () {
                try {
                    const d = await api('../api/attendance/allowed-ips.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete', id })
                    });
                    if (d.success) { notify('حذف شد', 'success'); loadIps(); }
                    else notify(d.message || 'خطا', 'error');
                } catch (e) { notify('خطا: ' + e.message, 'error'); }
            }, { danger: true, yesText: 'بله، حذف', noText: 'انصراف' });
        }

        /* ════════════════════════════════
           FAILED LOGS
        ════════════════════════════════ */
        const reasonMap = {
            IP_NOT_ALLOWED:     'خارج از شبکهٔ مجاز',
            NO_FINGERPRINT:     'بدون شناسهٔ دستگاه',
            DEVICE_PENDING:     'دستگاه در انتظار تأیید',
            DEVICE_NOT_APPROVED:'دستگاه تأییدنشده',
            DEVICE_REJECTED:    'دستگاه ردشده'
        };

        const actionMap = { check_in: 'ورود', check_out: 'خروج' };

        function reasonBadge(r) {
            const label = reasonMap[r] || r || '—';
            const cls = r === 'IP_NOT_ALLOWED' ? 'ad-rejected' : 'ad-pending';
            return `<span class="ad-badge ${cls}">${esc(label)}</span>`;
        }

        function initLogGrid() {
            logGridApi = agGrid.createGrid(document.getElementById('logGrid'), {
                enableRtl: true,
                rowHeight: 52,
                headerHeight: 44,
                defaultColDef: {
                    sortable: true,
                    resizable: true,
                    cellStyle: { display: 'flex', alignItems: 'center' }
                },
                columnDefs: [
                    {
                        headerName: 'زمان',
                        field: 'created_at',
                        width: 165,
                        cellRenderer: p => `<span style="color:var(--gray-600);font-size:.82rem">${faTime(p.value)}</span>`
                    },
                    {
                        headerName: 'کاربر',
                        field: 'user_name',
                        flex: 1,
                        minWidth: 120,
                        cellRenderer: p => {
                            const name = (p.value || '').trim();
                            return name
                                ? `<span style="display:flex;align-items:center;gap:.4rem"><i class="bi bi-person" style="color:var(--primary)"></i>${esc(name)}</span>`
                                : '—';
                        }
                    },
                    {
                        headerName: 'نوع',
                        field: 'action',
                        width: 95,
                        cellRenderer: p => {
                            const lbl = actionMap[p.value] || esc(p.value || '—');
                            const cls = p.value === 'check_in' ? 'ad-approved' : 'ad-rejected';
                            return `<span class="ad-badge ${cls}">${lbl}</span>`;
                        }
                    },
                    {
                        headerName: 'علت رد',
                        field: 'reason',
                        flex: 1,
                        minWidth: 180,
                        cellRenderer: p => reasonBadge(p.value)
                    },
                    {
                        headerName: 'آدرس IP',
                        field: 'ip_address',
                        width: 148,
                        cellRenderer: p => `<code style="direction:ltr;background:var(--gray-100);padding:.15rem .5rem;border-radius:6px;font-size:.8rem;color:var(--gray-700)">${esc(p.value || '—')}</code>`
                    },
                    {
                        headerName: '',
                        width: 56,
                        sortable: false,
                        cellRenderer: p => `<span class="ad-icon-btn del" title="حذف" onclick="deleteLog(${p.data.id})"><i class="bi bi-trash"></i></span>`
                    }
                ],
                overlayNoRowsTemplate: `
                    <div style="display:flex;flex-direction:column;align-items:center;gap:.5rem;padding:3rem;color:var(--gray-400)">
                        <i class="bi bi-check-circle" style="font-size:2.5rem;opacity:.35;color:var(--success)"></i>
                        <p style="margin:0;font-size:.875rem">تلاش ناموفقی ثبت نشده است</p>
                    </div>`,
                overlayLoadingTemplate: '<div style="display:flex;flex-direction:column;align-items:center;gap:10px;color:#744ca4;font-size:.85rem;"><div class="spinner-border" style="width:2.2rem;height:2.2rem;" role="status"></div><span>در حال بارگذاری...</span></div>'
            });
            logGridApi.showLoadingOverlay();
        }

        async function loadLog() {
            const range = document.getElementById('logRange')?.value || 'all';
            try {
                const d = await api('../api/attendance/denied-log.php?range=' + range);
                if (d.success) {
                    const logs = d.logs || [];
                    logGridApi.setGridOption('rowData', logs);
                    updateStat('tabCountLog', logs.length);
                } else { notify(d.message || 'خطا', 'error'); }
            } catch (e) { notify('خطا: ' + e.message, 'error'); }
        }

        function deleteLog(id) {
            uiConfirm('این مورد حذف شود؟', async function () {
                try {
                    const d = await api('../api/attendance/denied-log.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete', id })
                    });
                    if (d.success) { notify('حذف شد', 'success'); loadLog(); }
                    else notify(d.message || 'خطا', 'error');
                } catch (e) { notify('خطا: ' + e.message, 'error'); }
            }, { danger: true, yesText: 'بله، حذف', noText: 'انصراف' });
        }

        function clearLog() {
            const range = document.getElementById('logRange').value;
            const lbl = { today: 'امروز', week: 'هفتهٔ اخیر', all: 'همه' }[range] || '';
            uiConfirm('همهٔ تلاش‌های ناموفقِ «' + lbl + '» پاک شوند؟', async function () {
                try {
                    const d = await api('../api/attendance/denied-log.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'clear', range })
                    });
                    if (d.success) { notify(d.message, 'success'); loadLog(); }
                    else notify(d.message || 'خطا', 'error');
                } catch (e) { notify('خطا: ' + e.message, 'error'); }
            }, { danger: true, yesText: 'بله، پاک کن', noText: 'انصراف' });
        }

        /* ─── Boot ─── */
        document.addEventListener('DOMContentLoaded', () => {
            initDevGrid();
            loadDevices();
        });
    </script>
</body>
</html>