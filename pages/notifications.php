<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اعلان‌ها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <style>
        .nt-wrap { margin-bottom: 40px; }
        .nt-head {
            background: #8e57fe; color: #fff; border-radius: 16px;
            padding: 1.1rem 1.4rem; margin-bottom: 1.1rem;
            display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap;
        }
        .nt-head h1 { font-size: 1.3rem; font-weight: 800; margin: 0; display: flex; align-items: center; gap: .5rem; color: #fff; }
        .nt-head .nt-markall {
            background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.35);
            color: #fff; border-radius: 9px; padding: .45rem .9rem; font-size: .85rem; font-weight: 600; cursor: pointer;
        }
        .nt-head .nt-markall:hover { background: rgba(255,255,255,.3); }
        .nt-head .nt-markall:disabled { opacity: .5; cursor: default; }

        .nt-toolbar { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
        .nt-search { position: relative; flex: 1; min-width: 200px; }
        .nt-search i { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: .8rem; }
        .nt-search input {
            width: 100%; box-sizing: border-box; border: 1px solid #e5e9ee; border-radius: 9px;
            padding: 9px 34px 9px 12px; font-size: .85rem; font-family: inherit;
            background: var(--surface); color: var(--text-strong);
        }
        .nt-search input:focus { outline: none; border-color: #8e57fe; box-shadow: 0 0 0 3px rgba(142,87,254,.12); }
        .nt-chip {
            border: 1px solid #e5e9ee; background: var(--surface); color: #374151;
            border-radius: 9px; padding: 8px 14px; font-size: .8rem; font-weight: 600; cursor: pointer;
        }
        .nt-chip.active { background: #8e57fe; border-color: #8e57fe; color: #fff; }

        .nt-card { background: var(--surface); border: 1px solid var(--border-soft, #e9e9e9); border-radius: 16px; padding: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
        .nt-empty { text-align: center; color: #9ca3af; padding: 40px 0; }
        .nt-empty i { font-size: 2rem; display: block; margin-bottom: .5rem; }

        /* حالتِ تاریک */
        :root[data-theme="dark"] .nt-search input { border-color: var(--border-soft); }
        :root[data-theme="dark"] .nt-chip { border-color: var(--border-soft); color: var(--text-muted); }
        :root[data-theme="dark"] .nt-card { box-shadow: 0 1px 4px rgba(0,0,0,.35); }
    </style>

    <div class="overview-container nt-wrap">
        <div class="nt-head">
            <h1><i class="bi bi-bell"></i> اعلان‌ها</h1>
            <button type="button" class="nt-markall" id="ntMarkAll" onclick="ntMarkAllRead()" disabled>خواندن همه</button>
        </div>

        <div class="nt-toolbar">
            <div class="nt-search">
                <i class="bi bi-search"></i>
                <input type="text" id="ntSearch" placeholder="جستجو در اعلان‌ها..." oninput="ntQuery=this.value.trim();ntRender();">
            </div>
            <button type="button" class="nt-chip active" data-mode="all" onclick="ntSetMode('all')">همه</button>
            <button type="button" class="nt-chip" data-mode="unread" onclick="ntSetMode('unread')">خوانده‌نشده</button>
        </div>

        <div class="nt-card">
            <div class="notification-list-container" id="ntList" style="max-height:none;">
                <div class="notification-loading"><div class="spinner-border" role="status"></div></div>
            </div>
        </div>
    </div>

    <script>
        var ntAll = [];
        var ntMode = 'all';
        var ntQuery = '';
        var ntToken = localStorage.getItem('auth_token');

        function ntEsc(s) {
            if (s === null || s === undefined) return '';
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
        function ntFa(v) {
            return (typeof toFa === 'function') ? toFa(v) : String(v ?? '').replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
        }
        function ntIcon(type) {
            var m = { info: 'info-circle', success: 'check-circle', warning: 'exclamation-triangle', danger: 'x-circle' };
            return m[type] || 'bell';
        }
        function ntTime(s) {
            return (window.TimeSync && TimeSync.timeAgo) ? TimeSync.timeAgo(s) : '';
        }

        async function ntLoad() {
            var box = document.getElementById('ntList');
            if (!ntToken) { location.href = '../index.php'; return; }
            try {
                // ✅ از go-api سرو می‌شود؛ برگشت = این را به '/api/notifications/list.php?limit=200' برگردان.
                var r = await fetch('/go/api/notifications/list?limit=200', { headers: { 'Authorization': 'Bearer ' + ntToken } });
                var data = await r.json();
                if (!data.success) throw new Error(data.message || 'error');
                ntAll = data.notifications || [];
                document.getElementById('ntMarkAll').disabled = (data.unread_count || 0) <= 0;
                ntRender();
            } catch (e) {
                box.innerHTML = '<div class="nt-empty"><i class="bi bi-wifi-off"></i>خطا در بارگذاری</div>';
            }
        }

        function ntSetMode(m) {
            ntMode = m;
            document.querySelectorAll('.nt-chip').forEach(function (c) { c.classList.toggle('active', c.dataset.mode === m); });
            ntRender();
        }

        function ntRender() {
            var box = document.getElementById('ntList');
            var q = ntQuery.toLowerCase();
            var list = ntAll.filter(function (n) {
                if (ntMode === 'unread' && n.is_read) return false;
                if (q && !(((n.title || '') + (n.message || '')).toLowerCase().indexOf(q) > -1)) return false;
                return true;
            });
            if (!list.length) {
                box.innerHTML = '<div class="nt-empty"><i class="bi bi-bell-slash"></i>اعلانی وجود ندارد</div>';
                return;
            }
            box.innerHTML = list.map(function (n) {
                var unread = !n.is_read;
                return '<a class="notification-item ' + (unread ? 'unread' : '') + '" href="' + ntEsc(n.link || '#') + '" data-id="' + n.id + '" onclick="ntClick(event,' + n.id + ')">' +
                    '<div class="d-flex align-items-start">' +
                    '<div class="notification-icon ' + ntEsc(n.type) + '"><i class="bi bi-' + ntIcon(n.type) + '"></i></div>' +
                    '<div class="notification-content">' +
                    '<div class="notification-title">' + ntEsc(n.title) + '</div>' +
                    '<div class="notification-message">' + ntEsc(n.message) + '</div>' +
                    '<div class="notification-time">' + ntTime(n.created_at) + '</div>' +
                    '</div></div></a>';
            }).join('');
        }

        async function ntClick(ev, id) {
            var n = ntAll.find(function (x) { return String(x.id) === String(id); });
            if (n && !n.is_read) {
                n.is_read = 1;
                try {
                    await fetch('/api/notifications/mark-read.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + ntToken },
                        body: JSON.stringify({ id: id })
                    });
                } catch (e) { }
            }
            if (!n || !n.link || n.link === '#') { ev.preventDefault(); }
        }

        async function ntMarkAllRead() {
            var btn = document.getElementById('ntMarkAll');
            btn.disabled = true;
            try {
                var r = await fetch('/api/notifications/mark-all-read.php', {
                    method: 'POST', headers: { 'Authorization': 'Bearer ' + ntToken }
                });
                var data = await r.json();
                if (data.success) ntLoad();
                else btn.disabled = false;
            } catch (e) { btn.disabled = false; }
        }

        document.addEventListener('DOMContentLoaded', ntLoad);
    </script>
    <!-- Bootstrap JS — لازم برای منوهای کشویی نظارت/مدیریت/پروفایل در هدر -->
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <?php include 'footer.php'; ?>
</body>

</html>
