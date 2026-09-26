<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// فعلا فقط کاربر id=1 — ماژول فاکتور در حال ساخت است.
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/crm_access.php';
if (!crmModuleAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات فاکتور - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../assets/js/cdn/chart.js') ?>"></script>

    <style>
        .rpt-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            margin: 1rem 0 1.25rem;
        }

        .rpt-toolbar .rpt-dates {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rpt-dates .persian-datepicker-wrapper {
            width: 140px;
        }

        .rpt-presets {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .rpt-pill {
            border: 1px solid #e9e9e9;
            background: #fff;
            color: #718096;
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
        }

        .rpt-pill:hover {
            border-color: #8e57fe;
            color: #8e57fe;
        }

        .rpt-pill.active {
            background: #8e57fe;
            border-color: #8e57fe;
            color: #fff;
        }

        .rpt-spacer {
            flex: 1;
        }

        /* کارت‌های KPI */
        .rpt-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .rpt-kpi {
            background: #fff;
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .04);
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .rpt-kpi .ic {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .rpt-kpi .lbl {
            font-size: 12px;
            color: #718096;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .rpt-kpi .val {
            font-size: 19px;
            font-weight: 800;
            line-height: 1.25;
            color: #2D3748;
            direction: ltr;
            unicode-bidi: isolate;
            text-align: right;
        }

        .rpt-kpi .unit {
            font-size: 11px;
            font-weight: 600;
            color: #A0AEC0;
            margin-inline-start: 3px;
        }

        /* شبکهٔ نمودارها */
        .rpt-charts {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 16px;
        }

        .rpt-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .04);
            min-width: 0;
        }

        .rpt-card h6 {
            margin: 0 0 14px;
            font-weight: 700;
            color: #2D3748;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .rpt-card h6 i {
            color: #8e57fe;
        }

        .rpt-card .chart-wrap {
            position: relative;
            height: 260px;
        }

        .span-12 {
            grid-column: span 12;
        }

        .span-8 {
            grid-column: span 8;
        }

        .span-6 {
            grid-column: span 6;
        }

        .span-4 {
            grid-column: span 4;
        }

        @media (max-width: 992px) {
            .rpt-charts>div {
                grid-column: span 12 !important;
            }
        }

        .rpt-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #A0AEC0;
            font-size: 12.5px;
        }

        /* جدول ساده‌ی کالاهای رو به اتمام */
        .rpt-lowstock {
            list-style: none;
            margin: 0;
            padding: 0;
            max-height: 260px;
            overflow-y: auto;
        }

        .rpt-lowstock li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 4px;
            font-size: 12.5px;
            border-top: 1px dashed #eef0f2;
        }

        .rpt-lowstock li:first-child {
            border-top: 0;
        }

        .rpt-lowstock .pname {
            font-weight: 700;
            color: #2D3748;
        }

        .rpt-lowstock .pcode {
            color: #A0AEC0;
            font-size: 11px;
            margin-inline-start: 6px;
        }

        .rpt-lowstock .pqty {
            font-weight: 700;
            direction: ltr;
            unicode-bidi: isolate;
        }

        .rpt-lowstock .pqty.neg {
            color: #dc2626;
        }

        .rpt-lowstock .pqty.zero {
            color: #F79009;
        }

        :root[data-theme="dark"] .rpt-pill {
            background: var(--surface);
            border-color: var(--border-soft);
            color: var(--text-muted);
        }

        :root[data-theme="dark"] .rpt-pill.active {
            background: #8e57fe;
            border-color: #8e57fe;
            color: #fff;
        }

        :root[data-theme="dark"] .rpt-kpi,
        :root[data-theme="dark"] .rpt-card {
            background: var(--surface);
            box-shadow: none;
            border: 1px solid var(--border-soft);
        }

        :root[data-theme="dark"] .rpt-kpi .val,
        :root[data-theme="dark"] .rpt-card h6 {
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .rpt-lowstock li {
            border-top-color: rgba(255, 255, 255, .08);
        }

        :root[data-theme="dark"] .rpt-lowstock .pname {
            color: var(--text-strong);
        }

        @media (max-width: 768px) {
            .rpt-dates .persian-datepicker-wrapper {
                width: 110px;
            }
        }

        /* هاور استاندارد سایت (تینت بنفش نرم) — .btn-outline-secondary اصلا
           هاور اختصاصی نداشت (پیش‌فرض خاکستری بوت‌استرپ می‌ماند) */
        .btn-outline-primary:hover,
        .btn-outline-secondary:hover,
        .btn-outline-primary:active,
        .btn-outline-secondary:active,
        .btn-outline-primary.active,
        .btn-outline-secondary.active,
        .btn-outline-primary:active:focus,
        .btn-outline-secondary:active:focus {
            background: rgba(142, 87, 254, .12) !important;
            border-color: rgba(142, 87, 254, .12) !important;
            color: var(--primary, #8e57fe) !important;
            box-shadow: none !important;
        }

        :root[data-theme="dark"] .btn-outline-primary:hover,
        :root[data-theme="dark"] .btn-outline-secondary:hover,
        :root[data-theme="dark"] .btn-outline-primary:active,
        :root[data-theme="dark"] .btn-outline-secondary:active,
        :root[data-theme="dark"] .btn-outline-primary.active,
        :root[data-theme="dark"] .btn-outline-secondary.active {
            background: rgba(142, 87, 254, .18) !important;
            border-color: rgba(142, 87, 254, .18) !important;
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="overview-container" style="margin-top:70px">

        <div class="filters-wrapper two-col">
            <div class="filters-title-col">
                <h1><i class="bi bi-bar-chart-line"></i> گزارشات فاکتور</h1>
                <p>روند فروش و خرید، مشتریان و تأمین‌کنندگان برتر، وضعیت موجودی — سازمان ۱</p>
            </div>
        </div>

        <div class="rpt-toolbar">
            <div class="rpt-dates">
                <div class="persian-datepicker-wrapper" id="dateFromWrap" data-restrict-past="-1">
                    <input type="text" class="persian-datepicker-input form-control" id="dateFrom" placeholder="از تاریخ" readonly>
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
                <span class="text-muted">تا</span>
                <div class="persian-datepicker-wrapper" id="dateToWrap" data-restrict-past="-1">
                    <input type="text" class="persian-datepicker-input form-control" id="dateTo" placeholder="تا تاریخ" readonly>
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
            </div>
            <div class="rpt-presets" id="presets">
                <button class="rpt-pill active" data-preset="all">همه</button>
                <button class="rpt-pill" data-preset="year">امسال</button>
                <button class="rpt-pill" data-preset="q">۳ ماه اخیر</button>
                <button class="rpt-pill" data-preset="m">۳۰ روز اخیر</button>
            </div>
            <div class="rpt-spacer"></div>
            <button class="btn btn-outline-secondary btn-sm" id="btnRefresh"><i class="bi bi-arrow-clockwise ms-1"></i> به‌روزرسانی</button>
        </div>

        <div class="rpt-kpis" id="kpis">
            <p class="text-muted">در حال بارگذاری…</p>
        </div>

        <div class="rpt-charts">
            <div class="rpt-card span-8">
                <h6><i class="bi bi-graph-up-arrow"></i> روند ماهانهٔ فروش و خرید (ریال)</h6>
                <div class="chart-wrap"><canvas id="chTrend"></canvas></div>
            </div>
            <div class="rpt-card span-4">
                <h6><i class="bi bi-pie-chart"></i> وضعیت فاکتورهای فروش</h6>
                <div class="chart-wrap"><canvas id="chStatus"></canvas></div>
            </div>

            <div class="rpt-card span-4">
                <h6><i class="bi bi-cash-coin"></i> نحوهٔ فروش</h6>
                <div class="chart-wrap"><canvas id="chPayment"></canvas></div>
            </div>
            <div class="rpt-card span-4">
                <h6><i class="bi bi-people"></i> پرمعامله‌ترین مشتریان</h6>
                <div class="chart-wrap"><canvas id="chCustomers"></canvas></div>
            </div>
            <div class="rpt-card span-4">
                <h6><i class="bi bi-truck"></i> پرمعامله‌ترین تأمین‌کنندگان</h6>
                <div class="chart-wrap"><canvas id="chSuppliers"></canvas></div>
            </div>

            <div class="rpt-card span-4">
                <h6><i class="bi bi-box-seam"></i> ترکیب کاتالوگ کالا</h6>
                <div class="chart-wrap"><canvas id="chCatalog"></canvas></div>
            </div>
            <div class="rpt-card span-8">
                <h6><i class="bi bi-exclamation-triangle"></i> کالاهای رو به اتمام</h6>
                <ul class="rpt-lowstock" id="lowStockList"></ul>
            </div>
        </div>

    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../../assets/js/persian-datepicker.js') ?>"></script>
    <script>
        const API = '/crm/api';

        // پالت اعتبارسنجی‌شده (dataviz skill): جفت فروش/خرید و جفت وضعیت،
        // هر دو با اسکریپت validate_palette.js چک شدند (ΔE کافی برای کوررنگی).
        const COLOR = {
            sales: '#8e57fe',
            purchase: '#f59e0b',
            draft: '#94a3b8',
            approved: '#2563eb',
            cancelled: '#dc2626',
            cash: '#8e57fe',
            credit: '#f59e0b',
        };

        function tok() {
            return localStorage.getItem('auth_token');
        }

        function money(n) {
            return faDigits(String(Math.round(Number(n) || 0).toLocaleString('en-US')));
        }

        function shortMoney(n) {
            n = Number(n) || 0;
            const abs = Math.abs(n);
            if (abs >= 1e9) return faDigits((n / 1e9).toFixed(1)) + ' میلیارد';
            if (abs >= 1e6) return faDigits((n / 1e6).toFixed(1)) + ' میلیون';
            if (abs >= 1e3) return faDigits((n / 1e3).toFixed(0)) + ' هزار';
            return faDigits(n);
        }

        function isDark() {
            return document.documentElement.getAttribute('data-theme') === 'dark';
        }

        function textColor() {
            return isDark() ? '#cbd5e1' : '#475569';
        }

        function gridColor() {
            return isDark() ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)';
        }

        function surfaceColor() {
            return isDark() ? (getComputedStyle(document.documentElement).getPropertyValue('--surface').trim() || '#1e2230') : '#ffffff';
        }

        async function apiGet(path) {
            const r = await fetch(API + path, {
                headers: {
                    'Authorization': 'Bearer ' + tok()
                }
            });
            const d = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(d.message || ('خطای ' + r.status));
            return d;
        }

        async function fetchAll(path) {
            let page = 1,
                out = [],
                total = Infinity;
            while (out.length < total) {
                const sep = path.includes('?') ? '&' : '?';
                const d = await apiGet(path + sep + 'per=200&page=' + page);
                out = out.concat(d.items || []);
                total = d.total || 0;
                if (!d.items || !d.items.length) break;
                page++;
            }
            return out;
        }

        let invoices = [],
            purchases = [],
            products = [];
        let charts = {};
        let range = {
            from: null,
            to: null
        }; // میلادی YYYY-MM-DD یا null = بدون محدودیت

        function inRange(dateStr) {
            if (!dateStr) return false;
            if (range.from && dateStr < range.from) return false;
            if (range.to && dateStr > range.to) return false;
            return true;
        }

        // یک نمونه از تقویم شمسی خود سایت (assets/js/persian-datepicker.js) برای
        // تبدیل‌های میلادی↔شمسی؛ متدهایش نسبت به وضعیت آن ورودی خاص مستقل‌اند.
        function getDP() {
            const wrap = document.getElementById('dateFromWrap');
            return wrap && wrap.datepickerInstance;
        }

        function jKey(dateStr) {
            if (!dateStr) return null;
            const dp = getDP();
            if (!dp) return null;
            try {
                const j = dp.gregorianToJalali(new Date(dateStr + 'T00:00:00'));
                return j.year + '-' + String(j.month).padStart(2, '0');
            } catch (e) {
                return null;
            }
        }

        const J_MONTHS = FA_MONTHS; // مرجع یگانه در common-bundle.js

        function jLabel(key) {
            const [y, m] = key.split('-');
            return J_MONTHS[parseInt(m, 10) - 1] + ' ' + faDigits(y);
        }

        function destroyCharts() {
            Object.values(charts).forEach(c => c && c.destroy());
            charts = {};
        }

        function baseOptions(extra) {
            return Object.assign({
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: textColor(),
                            font: {
                                family: 'Vazirmatn',
                                size: 11.5
                            },
                            usePointStyle: true,
                            padding: 14
                        }
                    },
                    tooltip: {
                        rtl: true,
                        titleFont: {
                            family: 'Vazirmatn'
                        },
                        bodyFont: {
                            family: 'Vazirmatn'
                        }
                    }
                }
            }, extra || {});
        }

        function render() {
            const inv = invoices.filter(i => i.doc_type === 'official' && inRange(i._effDate));
            const invApproved = inv.filter(i => i.status === 'approved');
            const invDraft = inv.filter(i => i.status === 'draft');
            const proforma = invoices.filter(i => i.doc_type === 'proforma' && inRange(i._effDate));
            const pur = purchases.filter(p => inRange(p._effDate));
            const purConfirmed = pur.filter(p => p.status === 'confirmed');

            renderKpis(inv, invApproved, invDraft, proforma, pur, purConfirmed);
            destroyCharts();
            renderTrend(invApproved, purConfirmed);
            renderStatus(inv);
            renderPayment(invApproved);
            renderTopEntities('chCustomers', invApproved, 'customer_name', COLOR.sales);
            renderTopEntities('chSuppliers', purConfirmed, 'supplier_name', COLOR.purchase);
            renderCatalog();
            renderLowStock();
        }

        function renderKpis(inv, invApproved, invDraft, proforma, pur, purConfirmed) {
            const sumSales = invApproved.reduce((s, i) => s + (Number(i.total_amount) || 0), 0);
            const sumPurchase = purConfirmed.reduce((s, p) => s + (Number(p.total_amount) || 0), 0);
            const balance = sumSales - sumPurchase;

            const cards = [{
                    ic: 'bi-graph-up-arrow',
                    bg: 'rgba(142,87,254,.12)',
                    fg: '#8e57fe',
                    lbl: 'کل فروش (تأییدشده)',
                    val: money(sumSales)
                },
                {
                    ic: 'bi-cart-check',
                    bg: 'rgba(245,158,11,.14)',
                    fg: '#b45309',
                    lbl: 'کل خرید (تأییدشده)',
                    val: money(sumPurchase)
                },
                {
                    ic: balance >= 0 ? 'bi-arrow-up-circle' : 'bi-arrow-down-circle',
                    bg: balance >= 0 ? 'rgba(27,123,57,.12)' : 'rgba(220,38,38,.12)',
                    fg: balance >= 0 ? '#1b7b39' : '#dc2626',
                    lbl: 'تراز فروش / خرید',
                    val: money(balance)
                },
                {
                    ic: 'bi-receipt',
                    bg: 'rgba(37,99,235,.12)',
                    fg: '#2563eb',
                    lbl: 'تعداد فاکتور فروش',
                    val: faDigits(inv.length),
                    unit: ''
                },
                {
                    ic: 'bi-cart-plus',
                    bg: 'rgba(245,158,11,.14)',
                    fg: '#b45309',
                    lbl: 'تعداد خرید تأییدشده',
                    val: faDigits(purConfirmed.length),
                    unit: ''
                },
                {
                    ic: 'bi-file-earmark-text',
                    bg: 'rgba(148,163,184,.18)',
                    fg: '#64748b',
                    lbl: 'پیش‌فاکتور (خارج از این گزارش)',
                    val: faDigits(proforma.length),
                    unit: ''
                },
            ];

            document.getElementById('kpis').innerHTML = cards.map(c => `
                <div class="rpt-kpi">
                    <div class="ic" style="background:${c.bg};color:${c.fg}"><i class="bi ${c.ic}"></i></div>
                    <div>
                        <div class="lbl">${c.lbl}</div>
                        <div class="val">${c.val}${c.unit === '' ? '' : '<span class="unit">ریال</span>'}</div>
                    </div>
                </div>`).join('');
        }

        function renderTrend(invApproved, purConfirmed) {
            const map = {};
            invApproved.forEach(i => {
                const k = jKey(i._effDate);
                if (!k) return;
                (map[k] = map[k] || {
                    sales: 0,
                    purchase: 0
                }).sales += Number(i.total_amount) || 0;
            });
            purConfirmed.forEach(p => {
                const k = jKey(p._effDate);
                if (!k) return;
                (map[k] = map[k] || {
                    sales: 0,
                    purchase: 0
                }).purchase += Number(p.total_amount) || 0;
            });
            const keys = Object.keys(map).sort();
            const labels = keys.map(jLabel);
            const salesData = keys.map(k => map[k].sales);
            const purchaseData = keys.map(k => map[k].purchase);

            charts.trend = new Chart(document.getElementById('chTrend'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                            label: 'فروش',
                            data: salesData,
                            borderColor: COLOR.sales,
                            backgroundColor: COLOR.sales,
                            borderWidth: 2,
                            tension: 0.3,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                        },
                        {
                            label: 'خرید',
                            data: purchaseData,
                            borderColor: COLOR.purchase,
                            backgroundColor: COLOR.purchase,
                            borderWidth: 2,
                            tension: 0.3,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                        }
                    ]
                },
                options: baseOptions({
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: textColor(),
                                font: {
                                    family: 'Vazirmatn',
                                    size: 11.5
                                },
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            rtl: true,
                            callbacks: {
                                label: ctx => ctx.dataset.label + ': ' + money(ctx.parsed.y) + ' ریال'
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: textColor(),
                                font: {
                                    family: 'Vazirmatn',
                                    size: 11
                                }
                            },
                            grid: {
                                color: gridColor()
                            }
                        },
                        y: {
                            ticks: {
                                color: textColor(),
                                font: {
                                    family: 'Vazirmatn',
                                    size: 11
                                },
                                callback: v => shortMoney(v)
                            },
                            grid: {
                                color: gridColor()
                            }
                        }
                    }
                })
            });
        }

        function renderStatus(inv) {
            const labels = {
                draft: 'پیش‌نویس',
                approved: 'تأییدشده',
                cancelled: 'باطل'
            };
            const counts = {
                draft: 0,
                approved: 0,
                cancelled: 0
            };
            inv.forEach(i => {
                if (counts[i.status] != null) counts[i.status]++;
            });

            charts.status = new Chart(document.getElementById('chStatus'), {
                type: 'doughnut',
                data: {
                    labels: Object.keys(counts).map(k => labels[k]),
                    datasets: [{
                        data: Object.values(counts),
                        backgroundColor: [COLOR.draft, COLOR.approved, COLOR.cancelled],
                        borderColor: surfaceColor(),
                        borderWidth: 2,
                    }]
                },
                options: baseOptions({
                    cutout: '62%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: textColor(),
                                font: {
                                    family: 'Vazirmatn',
                                    size: 11.5
                                },
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            rtl: true,
                            callbacks: {
                                label: ctx => ctx.label + ': ' + faDigits(ctx.parsed) + ' فاکتور'
                            }
                        }
                    }
                })
            });
        }

        function renderPayment(invApproved) {
            let cash = 0,
                credit = 0;
            invApproved.forEach(i => {
                if (i.payment_type === 'cash') cash++;
                else if (i.payment_type === 'credit') credit++;
            });

            charts.payment = new Chart(document.getElementById('chPayment'), {
                type: 'doughnut',
                data: {
                    labels: ['نقدی', 'غیر نقدی'],
                    datasets: [{
                        data: [cash, credit],
                        backgroundColor: [COLOR.cash, COLOR.credit],
                        borderColor: surfaceColor(),
                        borderWidth: 2,
                    }]
                },
                options: baseOptions({
                    cutout: '62%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: textColor(),
                                font: {
                                    family: 'Vazirmatn',
                                    size: 11.5
                                },
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            rtl: true,
                            callbacks: {
                                label: ctx => ctx.label + ': ' + faDigits(ctx.parsed) + ' فاکتور'
                            }
                        }
                    }
                })
            });
        }

        function renderTopEntities(canvasId, list, field, color) {
            const map = {};
            list.forEach(x => {
                const name = x[field] || '—';
                map[name] = (map[name] || 0) + (Number(x.total_amount) || 0);
            });
            const sorted = Object.entries(map).sort((a, b) => b[1] - a[1]).slice(0, 6);
            const el = document.getElementById(canvasId);
            const wrap = el.closest('.chart-wrap');

            if (!sorted.length) {
                el.style.display = 'none';
                if (!wrap.querySelector('.rpt-empty')) {
                    const d = document.createElement('div');
                    d.className = 'rpt-empty';
                    d.textContent = 'داده‌ای در این بازه نیست';
                    wrap.appendChild(d);
                }
                return;
            }
            el.style.display = '';
            const emptyEl = wrap.querySelector('.rpt-empty');
            if (emptyEl) emptyEl.remove();

            charts[canvasId] = new Chart(el, {
                type: 'bar',
                data: {
                    labels: sorted.map(x => x[0]),
                    datasets: [{
                        data: sorted.map(x => x[1]),
                        backgroundColor: color,
                        borderRadius: 4,
                        maxBarThickness: 22,
                    }]
                },
                options: baseOptions({
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            rtl: true,
                            callbacks: {
                                label: ctx => money(ctx.parsed.x) + ' ریال'
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: textColor(),
                                font: {
                                    family: 'Vazirmatn',
                                    size: 10.5
                                },
                                callback: v => shortMoney(v)
                            },
                            grid: {
                                color: gridColor()
                            }
                        },
                        y: {
                            ticks: {
                                color: textColor(),
                                font: {
                                    family: 'Vazirmatn',
                                    size: 11.5
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                })
            });
        }

        function renderCatalog() {
            const physical = products.filter(p => !p.is_service).length;
            const service = products.filter(p => p.is_service).length;

            charts.catalog = new Chart(document.getElementById('chCatalog'), {
                type: 'doughnut',
                data: {
                    labels: ['کالای فیزیکی', 'خدمات'],
                    datasets: [{
                        data: [physical, service],
                        backgroundColor: [COLOR.sales, COLOR.purchase],
                        borderColor: surfaceColor(),
                        borderWidth: 2,
                    }]
                },
                options: baseOptions({
                    cutout: '62%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: textColor(),
                                font: {
                                    family: 'Vazirmatn',
                                    size: 11.5
                                },
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            rtl: true,
                            callbacks: {
                                label: ctx => ctx.label + ': ' + faDigits(ctx.parsed) + ' قلم'
                            }
                        }
                    }
                })
            });
        }

        function renderLowStock() {
            const low = products
                .filter(p => !p.is_service && (Number(p.available) || 0) <= 0)
                .sort((a, b) => (Number(a.available) || 0) - (Number(b.available) || 0))
                .slice(0, 30);

            const list = document.getElementById('lowStockList');
            if (!low.length) {
                list.innerHTML = '<li style="justify-content:center;color:#A0AEC0">موردی برای نمایش نیست — موجودی همهٔ کالاها مثبت است</li>';
                return;
            }
            list.innerHTML = low.map(p => {
                const av = Number(p.available) || 0;
                const cls = av < 0 ? 'neg' : 'zero';
                return `<li>
                    <span><span class="pname">${esc(p.name)}</span><span class="pcode">${p.code ? faDigits(esc(p.code)) : ''}</span></span>
                    <span class="pqty ${cls}">${faDigits(av)}</span>
                </li>`;
            }).join('');
        }

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;'
            } [c]));
        }

        /* ── بازهٔ تاریخ — همان انتخابگر شمسی استاندارد سایت ── */
        function fmtISO(o) {
            return o.year + '-' + String(o.month).padStart(2, '0') + '-' + String(o.day).padStart(2, '0');
        }

        // مقدار یک فیلد تاریخ را برنامه‌ای تنظیم می‌کند — با فراخوانی خود
        // selectDate روی نمونهٔ همان ویجت، دقیقا مثل کلیک کاربر روی یک روز
        // (skipConfirm=true تا تأییدیهٔ «جمعه/تعطیل» را نپرسد).
        function setDateField(inputId, wrapId, gregStr) {
            const input = document.getElementById(inputId);
            const wrap = document.getElementById(wrapId);
            const dp = wrap && wrap.datepickerInstance;
            if (!gregStr) {
                input.value = '';
                input.removeAttribute('data-date');
                return;
            }
            if (dp) {
                const j = dp.gregorianToJalali(new Date(gregStr + 'T00:00:00'));
                dp.selectDate(j.year, j.month, j.day, true);
            } else {
                input.value = gregStr;
                input.setAttribute('data-date', gregStr);
            }
        }

        function setPreset(name) {
            document.querySelectorAll('#presets .rpt-pill').forEach(b => b.classList.toggle('active', b.dataset.preset === name));
            const dp = getDP();
            if (!dp) return;
            if (name === 'all') {
                range = {
                    from: null,
                    to: null
                };
            } else if (name === 'year') {
                const y = dp.gregorianToJalali(new Date()).year;
                range.from = fmtISO(dp.jalaliToGregorian(y, 1, 1));
                range.to = fmtISO(dp.jalaliToGregorian(y + 1, 1, 1));
            } else if (name === 'q') {
                const d = new Date();
                d.setDate(d.getDate() - 90);
                range.from = fmtISO({
                    year: d.getFullYear(),
                    month: d.getMonth() + 1,
                    day: d.getDate()
                });
                range.to = null;
            } else if (name === 'm') {
                const d = new Date();
                d.setDate(d.getDate() - 30);
                range.from = fmtISO({
                    year: d.getFullYear(),
                    month: d.getMonth() + 1,
                    day: d.getDate()
                });
                range.to = null;
            }
            setDateField('dateFrom', 'dateFromWrap', range.from);
            setDateField('dateTo', 'dateToWrap', range.to);
            render();
        }

        document.getElementById('presets').addEventListener('click', e => {
            const btn = e.target.closest('.rpt-pill');
            if (btn) setPreset(btn.dataset.preset);
        });
        document.getElementById('btnRefresh').addEventListener('click', boot);

        // انتخاب دستی روز از تقویم: خود ویجت رویداد change را با data-date
        // (میلادی YYYY-MM-DD) روی ورودی می‌فرستد.
        document.getElementById('dateFrom').addEventListener('change', function () {
            document.querySelectorAll('#presets .rpt-pill').forEach(b => b.classList.remove('active'));
            range.from = this.dataset.date || null;
            render();
        });
        document.getElementById('dateTo').addEventListener('change', function () {
            document.querySelectorAll('#presets .rpt-pill').forEach(b => b.classList.remove('active'));
            range.to = this.dataset.date || null;
            render();
        });

        /* هماهنگی نمودارها با تغییر حالت روشن/تاریک، بدون رفرش صفحه */
        new MutationObserver(() => render()).observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme']
        });

        async function boot() {
            if (!tok()) {
                location.href = '../index.php';
                return;
            }
            document.getElementById('kpis').innerHTML = '<p class="text-muted">در حال بارگذاری…</p>';
            try {
                [invoices, purchases, products] = await Promise.all([
                    fetchAll('/inv/invoices'),
                    fetchAll('/inv/purchases'),
                    fetchAll('/inv/products'),
                ]);
                // برخی سندها (مثلا فاکتور رسمی ساخته‌شده از تبدیل پیش‌فاکتوری که
                // خودش تاریخ صدور نداشته) issue_date خالی دارند. اگر این را نادیده
                // می‌گرفتیم، آن سند از همهٔ گزارش‌ها به‌طور کامل ناپدید می‌شد. به‌جایش
                // تاریخ ثبت سند (created_at) را جایگزین می‌کنیم.
                invoices.forEach(i => {
                    i._effDate = i.issue_date || (i.created_at || '').slice(0, 10);
                });
                purchases.forEach(p => {
                    p._effDate = p.issue_date || (p.created_at || '').slice(0, 10);
                });
                render();
            } catch (e) {
                document.getElementById('kpis').innerHTML = '<p class="text-danger">' + (e.message || 'خطا در بارگذاری') + '</p>';
            }
        }

        boot();
    </script>
</body>

</html>
