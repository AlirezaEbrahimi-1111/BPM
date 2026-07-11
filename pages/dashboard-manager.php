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
</head>

<body>

    <?php include 'header.php'; ?>

    <style>
        /* ═══ چیدمان کلی — بدون اسکرول صفحه‌ای ═══ */
        html, body { overflow: hidden; }

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
            background: var(--gray-100);           /* هدر خاکستری */
            border-bottom: 1px solid var(--gray-200);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .dash-card-title {
            display: flex; align-items: center; gap: 8px;
            font-weight: 600; font-size: .95rem; color: var(--gray-700);
        }
        .dash-card-title i { font-size: 1rem; color: var(--gray-500); }

        .dash-see-all {
            font-size: .8rem; color: var(--primary); text-decoration: none;
            display: flex; align-items: center; gap: 4px; white-space: nowrap;
        }
        .dash-see-all:hover { text-decoration: underline; }

        /* بدنه‌ی اسکرول‌دار — اسکرول‌بار سمت راست */
        .dash-card-body {
            flex: 1; min-height: 0;
            overflow-y: auto;
            direction: ltr;
        }
        .dash-card-body > * { direction: rtl; }

        .dash-card-body::-webkit-scrollbar { width: 7px; }
        .dash-card-body::-webkit-scrollbar-track { background: var(--gray-50); }
        .dash-card-body::-webkit-scrollbar-thumb { background: var(--gray-300); border-radius: 99px; }
        .dash-card-body::-webkit-scrollbar-thumb:hover { background: var(--gray-400); }

        /* ═══ برنامه کاری ═══ */
        .plan-row { flex-shrink: 0; }
        .plan-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 14px; padding: 14px;
        }
        .plan-item {
            display: flex; align-items: center; gap: 12px;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            padding: 13px 16px;
        }
        .plan-icon {
            width: 44px; height: 44px; border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; flex-shrink: 0;
        }
        .plan-icon.month { background: #dcfce7; color: #16a34a; }
        .plan-icon.week  { background: #ffedd5; color: #ea580c; }
        .plan-icon.tomor { background: #dbeafe; color: #2563eb; }

        .plan-label {
            font-size: .82rem; font-weight: 600;
            color: var(--gray-500); margin-bottom: 2px;   /* هر سه هم‌رنگ */
        }
        .plan-value { font-size: 1.05rem; font-weight: 700; color: var(--gray-900); }

        /* ═══ بخش کارها ═══ */
        .tasks-row { flex: 1.15; min-height: 0; display: flex; }
        .tasks-row .dash-card { flex: 1; }

        .dash-tabs {
            display: flex; gap: 2px;
            border-bottom: 1px solid var(--gray-200);
            padding: 0 12px; flex-shrink: 0; background: #fff;
        }
        .dash-tab {
            background: none; border: none;
            border-bottom: 2px solid transparent;
            padding: 10px 14px 9px;
            font-size: .87rem; color: var(--gray-500);
            cursor: pointer; display: flex; align-items: center; gap: 6px;
        }
        .dash-tab:hover { color: var(--gray-700); }
        .dash-tab.active {
            color: var(--primary); border-bottom-color: var(--primary); font-weight: 600;
        }
        .tab-pin {
            font-size: .78rem; color: var(--gray-300);
            opacity: 0; transition: opacity .15s, color .15s;
            padding: 2px; border-radius: 4px;
        }
        .dash-tab:hover .tab-pin { opacity: 1; }
        .tab-pin:hover { color: var(--gray-600); background: var(--gray-100); }
        .tab-pin.pinned { opacity: 1; color: var(--warning); }

        .dash-filters { display: flex; gap: 6px; padding: 10px 14px; flex-shrink: 0; }
        .filter-chip {
            border: 1px solid var(--gray-200); background: #fff;
            border-radius: var(--radius-sm);
            padding: 5px 14px; font-size: .8rem;
            color: var(--gray-500); cursor: pointer; transition: all .15s;
        }
        .filter-chip:hover { background: var(--gray-50); }
        .filter-chip.active {
            background: var(--primary); border-color: var(--primary);
            color: #fff; font-weight: 600;
        }

        .task-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;                     /* عرض ستون‌ها را ثابت می‌کند */
        }
        .task-table th:nth-child(1), .task-table td:nth-child(1) { width: 42%; }  /* عنوان */
        .task-table th:nth-child(2), .task-table td:nth-child(2) { width: 26%; }  /* مهلت  */
        .task-table th:nth-child(3), .task-table td:nth-child(3) { width: 20%; }  /* وضعیت */
        .task-table th:nth-child(4), .task-table td:nth-child(4) { width: 12%; }  /* عملیات */
        .task-table thead th {
            position: sticky; top: 0; z-index: 2; background: #fff;
            font-size: .78rem; font-weight: 600; color: var(--gray-400);
            text-align: right; padding: 8px 14px;
            border-bottom: 1px solid var(--gray-100); white-space: nowrap;
        }
        .task-table thead th.col-status { text-align: center; width: 130px; }
        .task-table thead th.col-ops    { text-align: center; width: 90px; }

        .task-table tbody tr {
            border-bottom: 1px solid var(--gray-50);
            cursor: pointer; transition: background .12s;
        }
        .task-table tbody tr:hover { background: var(--gray-50); }
        .task-table td {
            padding: 10px 14px; font-size: .85rem;
            color: var(--gray-700); vertical-align: middle;
        }
        .td-title { display: flex; align-items: center; gap: 8px; }
        .td-title i.doc { color: #93c5fd; font-size: .95rem; flex-shrink: 0; }
        .td-title span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .td-deadline { color: var(--gray-500); white-space: nowrap;}
        .td-status { text-align: center;  }
        .td-ops    { text-align: center; }

        .st-badge {
            display: inline-block; padding: 3px 10px; border-radius: 999px;
            font-size: .74rem; font-weight: 600; white-space: nowrap;
        }
        .st-not_started { background: var(--gray-100); color: var(--gray-500); }
        .st-in_progress { background: #dbeafe; color: #1d4ed8; }
        .st-completed, .st-approved { background: #dcfce7; color: #15803d; }
        .st-pending_approval, .st-termination_requested { background: #fef3c7; color: #b45309; }
        .st-delegated { background: #e0e7ff; color: #4338ca; }
        .st-rejected  { background: #fee2e2; color: #b91c1c; }
        .st-period_done { background: #cffafe; color: #0e7490; }
        .st-overdue   { background: #fee2e2; color: #b91c1c; }

        .star-btn {
            background: none; border: none; cursor: pointer;
            font-size: 1rem; color: var(--gray-300); padding: 3px;
            transition: color .15s, transform .12s;
        }
        .star-btn:hover { color: #fbbf24; transform: scale(1.15); }
        .star-btn.on { color: var(--warning); }

        /* ═══ ردیف پایین ═══ */
        .bottom-row {
            flex: 1; min-height: 0;
            display: grid; grid-template-columns: 1fr 1fr; gap: 14px;
        }

        .routine-row {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 14px; cursor: pointer;
            border-bottom: 1px solid var(--gray-50);
            transition: background .12s;
        }
        .routine-row:hover { background: var(--gray-50); }
        .routine-name {
            flex: 1; font-size: .86rem; color: var(--gray-700); font-weight: 500;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .routine-bar-wrap {
            flex: 1.6; height: 9px; background: var(--gray-100);
            border-radius: 999px; overflow: hidden;
        }
        .routine-bar { height: 100%; border-radius: 999px; }
        .routine-count {
            font-size: .85rem; font-weight: 700; color: var(--gray-700);
            min-width: 26px; text-align: center;
        }

        .dlg-row {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 14px; cursor: pointer;
            border-bottom: 1px solid var(--gray-50);
            transition: background .12s;
        }
        .dlg-row:hover { background: var(--gray-50); }
        .dlg-main { flex: 1; min-width: 0; }
        .dlg-title {
            font-size: .85rem; color: var(--gray-700); font-weight: 500;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .dlg-sub {
            font-size: .74rem; color: var(--gray-400); margin-top: 2px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .dlg-days {
            background: #fee2e2; color: #b91c1c; border-radius: 999px;
            padding: 3px 10px; font-size: .74rem; font-weight: 600;
            white-space: nowrap; flex-shrink: 0;
        }

        .dash-empty {
            text-align: center; color: var(--gray-400);
            font-size: .85rem; padding: 30px 14px;
        }
        .dash-empty i { display: block; font-size: 1.6rem; margin-bottom: 6px; opacity: .5; }

        .inst-row {
            border: 1px solid var(--gray-100);
            border-radius: var(--radius-sm);
            padding: 12px 14px; margin-bottom: 8px;
        }
        .inst-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; margin-bottom: 8px;
        }
        .inst-title { font-weight: 600; font-size: .9rem; color: var(--gray-700); }
        .inst-meta  { font-size: .78rem; color: var(--gray-500); margin-bottom: 6px; }
        .inst-prog  { height: 7px; background: var(--gray-100); border-radius: 999px; overflow: hidden; }
        .inst-prog > div { height: 100%; border-radius: 999px; background: var(--primary); }

        @media (max-width: 992px) {
            html, body { overflow: auto; }
            .dash-wrap { height: auto; }
            .plan-grid { grid-template-columns: 1fr; }
            .bottom-row { grid-template-columns: 1fr; }
            .tasks-row, .bottom-row { min-height: 420px; }
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
                <div class="plan-item">
                    <div class="plan-icon month"><i class="bi bi-calendar-check"></i></div>
                    <div>
                        <div class="plan-label">این ماه</div>
                        <div class="plan-value" id="statMonth">— وظیفه</div>
                    </div>
                </div>
                <div class="plan-item">
                    <div class="plan-icon week"><i class="bi bi-calendar-week"></i></div>
                    <div>
                        <div class="plan-label">این هفته</div>
                        <div class="plan-value" id="statWeek">— وظیفه</div>
                    </div>
                </div>
                <div class="plan-item">
                    <div class="plan-icon tomor"><i class="bi bi-calendar-event"></i></div>
                    <div>
                        <div class="plan-label">فردا</div>
                        <div class="plan-value" id="statTomorrow">— وظیفه</div>
                    </div>
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
                            <tr><td colspan="4" class="dash-empty">در حال بارگذاری…</td></tr>
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
                    <div id="routineList"><div class="dash-empty">در حال بارگذاری…</div></div>
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
                    <div id="delayedList"><div class="dash-empty">در حال بارگذاری…</div></div>
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


    <script>
    /* متغیر authToken از header.php می‌آید */

    const LS_STARRED = 'mgrDash.starred';
    const LS_DEFTAB  = 'mgrDash.defaultTab';

    let currentTab    = 'mine';
    let currentFilter = 'all';

    const store = { mine: [], delegated: [], recent: [] };

    /* ───────── کمکی‌ها ───────── */
    function toFa(n) {
        if (n === null || n === undefined || n === '') return '—';
        return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
    }

    function toJalali(gy, gm, gd) {
        const g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
        let jy = (gy <= 1600) ? 0 : 979;
        gy -= (gy <= 1600) ? 621 : 1600;
        const gy2 = (gm > 2) ? gy + 1 : gy;
        let days = 365*gy + Math.floor((gy2+3)/4) - Math.floor((gy2+99)/100)
                 + Math.floor((gy2+399)/400) - 80 + gd + g_d_m[gm-1];
        jy += 33 * Math.floor(days/12053);
        days %= 12053;
        jy += 4 * Math.floor(days/1461);
        days %= 1461;
        jy += Math.floor((days-1)/365);
        if (days > 365) days = (days-1) % 365;
        const jm = (days < 186) ? 1 + Math.floor(days/31) : 7 + Math.floor((days-186)/30);
        const jd = 1 + ((days < 186) ? (days % 31) : ((days-186) % 30));
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

    const statusCfg = {
        not_started: 'شروع نشده',
        in_progress: 'در حال انجام',
        completed: 'تکمیل شده',
        pending_approval: 'در انتظار تایید',
        approved: 'تأیید شده',
        delegated: 'ارجاع شده',
        rejected: 'متوقف',
        period_done: 'دوره انجام شد',
        termination_requested: 'در انتظار اتمام'
    };

    function statusBadge(t) {
        if (isOverdue(t)) return `<span class="st-badge st-overdue">عقب افتاده</span>`;
        const s = t.status || 'not_started';
        return `<span class="st-badge st-${s}">${statusCfg[s] || s}</span>`;
    }

    function getDeadline(t) {
        return t.deadline || t.due_date || t.next_due_date || t.end_date || null;
    }

    function dateOnly(v) {
        if (!v) return null;
        const d = new Date(v);
        if (isNaN(d)) return null;
        d.setHours(0,0,0,0);
        return d;
    }

    function isOverdue(t) {
        if (t.status === 'completed' || t.status === 'approved') return false;
        if (Number(t.overdue_periods) > 0) return true;
        if (t.is_delayed == 1) return true;
        const d = dateOnly(getDeadline(t));
        if (!d) return false;
        const today = new Date(); today.setHours(0,0,0,0);
        return d < today;
    }

    function isToday(t) {
        const d = dateOnly(getDeadline(t));
        if (!d) return false;
        const today = new Date(); today.setHours(0,0,0,0);
        return d.getTime() === today.getTime();
    }

    /* ───────── منتخب ───────── */
    function getStarred() {
        try { return JSON.parse(localStorage.getItem(LS_STARRED) || '[]'); }
        catch { return []; }
    }
    function isStarred(k) { return getStarred().includes(k); }

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
    function getDefaultTab() { return localStorage.getItem(LS_DEFTAB) || 'mine'; }

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
            const on  = (def === key);
            p.className = `bi ${on ? 'bi-pin-angle-fill' : 'bi-pin-angle'} tab-pin${on ? ' pinned' : ''}`;
            p.dataset.pin = key;
            p.title = on ? 'تب پیش‌فرض (برای لغو کلیک کنید)' : 'تعیین به‌عنوان تب پیش‌فرض';
        });
    }

    /* ───────── دریافت داده ───────── */
    async function apiGet(url) {
        try {
            const res = await fetch(url, {
                headers: { 'Authorization': 'Bearer ' + authToken }
            });
            return await res.json();
        } catch (e) {
            console.error('API error:', url, e);
            return { success: false };
        }
    }

    function pickList(d) {
        if (!d) return [];
        if (Array.isArray(d)) return d;

        const keys = ['tasks', 'data', 'workflows', 'instances', 'items', 'result', 'rows'];
        for (const k of keys) if (Array.isArray(d[k])) return d[k];

        if (d.data && typeof d.data === 'object') {
            for (const k of keys) if (Array.isArray(d.data[k])) return d.data[k];
            for (const k of Object.keys(d.data)) if (Array.isArray(d.data[k])) return d.data[k];
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

        store.mine      = pickList(mine);
        store.delegated = pickList(delegated);
        store.recent    = pickList(recent);

        renderStats();
        renderTasks();
        renderRoutines(routines);
        renderDelayed();
    }

    /* ───────── کارت‌های آماری (بر پایه‌ی تقویم) ───────── */
    function renderStats() {
        const today = new Date(); today.setHours(0,0,0,0);
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

        let cMonth = 0, cWeek = 0, cTomorrow = 0;

        store.mine.forEach(t => {
            if (t.status === 'completed' || t.status === 'approved') return;
            const d = dateOnly(getDeadline(t));
            if (!d) return;

            // این ماه = همان ماهِ شمسیِ جاری (شامل روزهای گذشته‌ی همین ماه)
            const [jy, jm] = jalaliOf(d);
            if (jy === tjy && jm === tjm) cMonth++;

            if (d >= weekStart && d <= weekEnd) cWeek++;
            if (d.getTime() === tomorrow.getTime()) cTomorrow++;
        });

        document.getElementById('statMonth').textContent    = `${toFa(cMonth)} وظیفه`;
        document.getElementById('statWeek').textContent     = `${toFa(cWeek)} وظیفه`;
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
                ...store.mine.map(t      => ({ ...t, _src: 'mine' })),
                ...store.delegated.map(t => ({ ...t, _src: 'delegated' })),
                ...store.recent.map(t    => ({ ...t, _src: 'recent' }))
            ];
            const seen = new Set();
            return all.filter(t => {
                const k = starKey(t);
                if (!starred.includes(k) || seen.has(k)) return false;
                seen.add(k);
                return true;
            });
        }
        return (store[currentTab] || []).map(t => ({ ...t, _src: currentTab }));
    }

    function applyFilter(list) {
        if (currentFilter === 'today')   return list.filter(isToday);
        if (currentFilter === 'overdue') return list.filter(isOverdue);
        return list;
    }

    function renderTasks() {
        const tbody = document.getElementById('taskTbody');
        const list  = applyFilter(getTabList());

        if (!list.length) {
            tbody.innerHTML = `<tr><td colspan="4" class="dash-empty">
                <i class="bi bi-inbox"></i>کاری برای نمایش وجود ندارد</td></tr>`;
            return;
        }

        tbody.innerHTML = list.map(t => {
            const key  = starKey(t);
            const on   = isStarred(key);
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
                <td class="td-deadline">${faDate(getDeadline(t))}</td>
                <td class="td-status">${statusBadge(t)}</td>
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
        const box  = document.getElementById('routineList');
        const list = (data && data.success) ? (data.routines || []) : [];

        if (!list.length) {
            box.innerHTML = `<div class="dash-empty">
                <i class="bi bi-diagram-3"></i>فرآیند فعالی وجود ندارد</div>`;
            return;
        }

        const max = Math.max(...list.map(r => r.active_count), 1);
        const colors = ['#2563eb', '#0d9488', '#16a34a', '#ea580c', '#7c3aed'];

        box.innerHTML = list.map((r, i) => {
            const pct  = Math.round((r.active_count / max) * 100);
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
        const d = dateOnly(getDeadline(t));
        if (!d) return 0;
        const today = new Date(); today.setHours(0,0,0,0);
        const diff = Math.floor((today - d) / 86400000);
        return diff > 0 ? diff : 0;
    }

    function renderDelayed() {
        const box  = document.getElementById('delayedList');
        const list = store.delegated.filter(isOverdue);

        if (!list.length) {
            box.innerHTML = `<div class="dash-empty">
                <i class="bi bi-check2-circle"></i>کار واگذارشده‌ی تأخیرداری وجود ندارد</div>`;
            return;
        }

        box.innerHTML = list.map(t => {
            const who  = [t.assignee_first_name, t.assignee_last_name].filter(Boolean).join(' ');
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
            mine:      'my-tasks.php',
            delegated: 'delegated-tasks.php',
            recent:    'workflow-monitor.php',
            starred:   '#'
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
    </script>

</body>
</html>