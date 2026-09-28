<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// 🔒 طبق درخواست صریح — فقط id=1، همون الگوی error-log.php/hekmat-broadcast.php
if ((int) $__me['id'] !== 1) {
    header('Location: /pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مانیتورینگ سرور - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('../../assets/fonts/Vazirmatn-font-face.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">

    <style>
        /* توضیح مبتدی: کارت‌ها و رنگ‌ها از custom.css می‌آن، تم تاریک‌شون
           خودکاره. اینجا فقط چیدمانِ مخصوصِ همین صفحه است. */
        .sm-stat-card .card-body {
            padding: 1.1rem 1.25rem;
        }

        .sm-stat-label {
            font-size: .8rem;
            color: var(--text-muted, #6b7280);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
        }

        .sm-stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--ink-900, #1f2937);
        }

        .sm-stat-sub {
            font-size: .78rem;
            color: var(--text-muted, #6b7280);
            margin-top: 2px;
        }

        .sm-bar-wrap {
            height: 8px;
            border-radius: 20px;
            background: rgba(142, 87, 254, .12);
            margin-top: 10px;
            overflow: hidden;
        }

        :root[data-theme="dark"] .sm-bar-wrap {
            background: rgba(142, 87, 254, .18);
        }

        .sm-bar-fill {
            height: 100%;
            border-radius: 20px;
            background: #8e57fe;
            transition: width .4s ease;
        }

        .sm-bar-fill.sm-warn { background: #f59e0b; }
        .sm-bar-fill.sm-danger { background: #dc2626; }

        .sm-status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 6px;
        }

        .sm-status-dot.ok { background: #16a34a; }
        .sm-status-dot.bad { background: #dc2626; }

        .sm-last-update {
            font-size: .78rem;
            color: var(--text-muted, #6b7280);
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="bi bi-hdd-network"></i>
                مانیتورینگ سرور
            </h1>
            <span class="sm-last-update" id="smLastUpdate"></span>
        </div>

        <div id="smErrorBox" class="alert alert-danger" style="display:none;"></div>

        <div class="row g-4">
            <!-- CPU -->
            <div class="col-md-6 col-xl-3">
                <div class="card sm-stat-card">
                    <div class="card-body">
                        <div class="sm-stat-label"><i class="bi bi-cpu"></i>پردازنده (CPU)</div>
                        <div class="sm-stat-value" id="smCpuPct">—</div>
                        <div class="sm-bar-wrap"><div class="sm-bar-fill" id="smCpuBar" style="width:0%"></div></div>
                    </div>
                </div>
            </div>

            <!-- RAM -->
            <div class="col-md-6 col-xl-3">
                <div class="card sm-stat-card">
                    <div class="card-body">
                        <div class="sm-stat-label"><i class="bi bi-memory"></i>حافظه (RAM)</div>
                        <div class="sm-stat-value" id="smRamPct">—</div>
                        <div class="sm-stat-sub" id="smRamDetail"></div>
                        <div class="sm-bar-wrap"><div class="sm-bar-fill" id="smRamBar" style="width:0%"></div></div>
                    </div>
                </div>
            </div>

            <!-- Swap -->
            <div class="col-md-6 col-xl-3">
                <div class="card sm-stat-card">
                    <div class="card-body">
                        <div class="sm-stat-label"><i class="bi bi-hdd-stack"></i>Swap</div>
                        <div class="sm-stat-value" id="smSwapPct">—</div>
                        <div class="sm-stat-sub" id="smSwapDetail"></div>
                        <div class="sm-bar-wrap"><div class="sm-bar-fill" id="smSwapBar" style="width:0%"></div></div>
                    </div>
                </div>
            </div>

            <!-- دیسک -->
            <div class="col-md-6 col-xl-3">
                <div class="card sm-stat-card">
                    <div class="card-body">
                        <div class="sm-stat-label"><i class="bi bi-device-hdd"></i>فضای دیسک</div>
                        <div class="sm-stat-value" id="smDiskPct">—</div>
                        <div class="sm-stat-sub" id="smDiskDetail"></div>
                        <div class="sm-bar-wrap"><div class="sm-bar-fill" id="smDiskBar" style="width:0%"></div></div>
                    </div>
                </div>
            </div>

            <!-- شبکه -->
            <div class="col-md-6 col-xl-3">
                <div class="card sm-stat-card">
                    <div class="card-body">
                        <div class="sm-stat-label"><i class="bi bi-arrow-down-up"></i>ترافیک شبکه (لحظه‌ای)</div>
                        <div class="sm-stat-value" style="font-size:1.1rem;" id="smNetIn">—</div>
                        <div class="sm-stat-sub" id="smNetOut"></div>
                    </div>
                </div>
            </div>

            <!-- میانگین بار -->
            <div class="col-md-6 col-xl-3">
                <div class="card sm-stat-card">
                    <div class="card-body">
                        <div class="sm-stat-label"><i class="bi bi-speedometer2"></i>میانگین بار (Load Average)</div>
                        <div class="sm-stat-value" style="font-size:1.1rem;" id="smLoad">—</div>
                        <div class="sm-stat-sub">۱ دقیقه / ۵ دقیقه / ۱۵ دقیقه</div>
                    </div>
                </div>
            </div>

            <!-- آپ‌تایم -->
            <div class="col-md-6 col-xl-3">
                <div class="card sm-stat-card">
                    <div class="card-body">
                        <div class="sm-stat-label"><i class="bi bi-clock-history"></i>مدت روشن‌بودن سرور</div>
                        <div class="sm-stat-value" style="font-size:1.1rem;" id="smUptime">—</div>
                    </div>
                </div>
            </div>

            <!-- وضعیت دیتابیس -->
            <div class="col-md-6 col-xl-3">
                <div class="card sm-stat-card">
                    <div class="card-body">
                        <div class="sm-stat-label"><i class="bi bi-database"></i>اتصال به دیتابیس</div>
                        <div class="sm-stat-value" style="font-size:1.1rem;">
                            <span class="sm-status-dot" id="smDbDot"></span>
                            <span id="smDbLabel">—</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- روندِ تاریخی — از یک نمونه‌گیرِ پس‌زمینه‌ی هر ۲دقیقه‌ای پر می‌شه؛
             بلافاصله بعدِ دیپلوی تقریبا خالیه، کم‌کم پر می‌شه -->
        <div class="card mt-4">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-graph-up ms-2"></i>روند مصرف (CPU / RAM / دیسک)</h5>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary sm-range-btn active" data-range="1h" onclick="smSelectRange('1h', this)">۱ ساعت اخیر</button>
                    <button type="button" class="btn btn-outline-primary sm-range-btn" data-range="24h" onclick="smSelectRange('24h', this)">۲۴ ساعت اخیر</button>
                    <button type="button" class="btn btn-outline-primary sm-range-btn" data-range="7d" onclick="smSelectRange('7d', this)">۷ روز اخیر</button>
                </div>
            </div>
            <div class="card-body">
                <div id="smHistoryEmpty" class="text-muted text-center py-4" style="display:none;">
                    هنوز داده‌ی تاریخی کافی جمع نشده — کمی صبر کنید (هر ۲ دقیقه یک نمونه ذخیره می‌شود).
                </div>
                <canvas id="smHistoryChart" height="90"></canvas>
            </div>
        </div>
    </div>

    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/chart.js') ?>"></script>
    <script>
        // 🔒 هر ۳ ثانیه رفرش — کافیه برای یک نگاه سریع، بدون بار اضافه روی سرور
        const SM_POLL_MS = 3000;
        let smTimer = null;
        let smCurrentRange = '1h';
        let smChart = null;

        function smBarClass(pct) {
            if (pct >= 90) return 'sm-bar-fill sm-danger';
            if (pct >= 75) return 'sm-bar-fill sm-warn';
            return 'sm-bar-fill';
        }

        function smFormatBps(kbps) {
            if (kbps >= 1024) return toFa((kbps / 1024).toFixed(1)) + ' مگابیت/ثانیه';
            return toFa(kbps.toFixed(1)) + ' کیلوبیت/ثانیه';
        }

        async function smLoadOnce() {
            try {
                const res = await fetch('/go/api/system/monitor', {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });
                const data = await res.json();
                if (!data.success) {
                    document.getElementById('smErrorBox').style.display = 'block';
                    document.getElementById('smErrorBox').textContent = data.message || 'خطا در دریافت اطلاعات سرور';
                    return;
                }
                document.getElementById('smErrorBox').style.display = 'none';

                document.getElementById('smCpuPct').textContent = toFa(data.cpu.percent) + '٪';
                const cpuBar = document.getElementById('smCpuBar');
                cpuBar.style.width = data.cpu.percent + '%';
                cpuBar.className = smBarClass(data.cpu.percent);

                document.getElementById('smRamPct').textContent = toFa(data.ram.percent) + '٪';
                document.getElementById('smRamDetail').textContent =
                    toFa(data.ram.used_mb) + ' از ' + toFa(data.ram.total_mb) + ' مگابایت';
                const ramBar = document.getElementById('smRamBar');
                ramBar.style.width = data.ram.percent + '%';
                ramBar.className = smBarClass(data.ram.percent);

                document.getElementById('smSwapPct').textContent = toFa(data.swap.percent) + '٪';
                document.getElementById('smSwapDetail').textContent = data.swap.total_mb > 0
                    ? (toFa(data.swap.used_mb) + ' از ' + toFa(data.swap.total_mb) + ' مگابایت')
                    : 'Swap تعریف نشده';
                const swapBar = document.getElementById('smSwapBar');
                swapBar.style.width = data.swap.percent + '%';
                swapBar.className = smBarClass(data.swap.percent);

                document.getElementById('smDiskPct').textContent = toFa(data.disk.percent) + '٪';
                document.getElementById('smDiskDetail').textContent =
                    toFa(data.disk.used_gb) + ' از ' + toFa(data.disk.total_gb) + ' گیگابایت';
                const diskBar = document.getElementById('smDiskBar');
                diskBar.style.width = data.disk.percent + '%';
                diskBar.className = smBarClass(data.disk.percent);

                document.getElementById('smNetIn').textContent = 'دریافت: ' + smFormatBps(data.network.rx_kbps);
                document.getElementById('smNetOut').textContent = 'ارسال: ' + smFormatBps(data.network.tx_kbps);

                document.getElementById('smLoad').textContent =
                    toFa(data.load.l1.toFixed(2)) + ' / ' + toFa(data.load.l5.toFixed(2)) + ' / ' + toFa(data.load.l15.toFixed(2));

                document.getElementById('smUptime').textContent = data.uptime_label;

                document.getElementById('smDbDot').className = 'sm-status-dot ' + (data.db_ok ? 'ok' : 'bad');
                document.getElementById('smDbLabel').textContent = data.db_ok ? 'متصل' : 'قطع';

                document.getElementById('smLastUpdate').textContent =
                    'آخرین به‌روزرسانی: ' + new Date().toLocaleTimeString('fa-IR');
            } catch (e) {
                console.error('smLoadOnce:', e);
                document.getElementById('smErrorBox').style.display = 'block';
                document.getElementById('smErrorBox').textContent = 'خطا در ارتباط با سرور';
            }
        }

        // برچسبِ محور افقی — برایِ بازه‌ی کوتاه فقط ساعت، برایِ ۷ روز
        // تاریخِ شمسی هم بیاد؛ از همون TimeSync ای که کل پروژه استفاده
        // می‌کنه، نه یک تبدیلِ شمسیِ جداگانه
        function smFormatChartLabel(raw) {
            if (!window.TimeSync) return raw;
            return smCurrentRange === '7d' ? TimeSync.formatJalaliTime(raw) : TimeSync.formatTimeOnly(raw);
        }

        async function smLoadHistory(range) {
            try {
                const res = await fetch('/go/api/system/monitor/history?range=' + encodeURIComponent(range), {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });
                const data = await res.json();
                if (!data.success) return;

                const empty = document.getElementById('smHistoryEmpty');
                const canvas = document.getElementById('smHistoryChart');
                if (!data.labels || !data.labels.length) {
                    empty.style.display = 'block';
                    canvas.style.display = 'none';
                    return;
                }
                empty.style.display = 'none';
                canvas.style.display = 'block';

                const labels = data.labels.map(smFormatChartLabel);

                if (smChart) {
                    smChart.data.labels = labels;
                    smChart.data.datasets[0].data = data.cpu;
                    smChart.data.datasets[1].data = data.ram;
                    smChart.data.datasets[2].data = data.disk;
                    smChart.update();
                    return;
                }

                smChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'CPU٪', data: data.cpu, borderColor: '#8e57fe', backgroundColor: 'rgba(142,87,254,.08)', tension: .3, pointRadius: 0, fill: true },
                            { label: 'RAM٪', data: data.ram, borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,.08)', tension: .3, pointRadius: 0, fill: true },
                            { label: 'دیسک٪', data: data.disk, borderColor: '#ea580c', backgroundColor: 'rgba(234,88,12,.06)', tension: .3, pointRadius: 0, fill: true }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: { y: { min: 0, max: 100, ticks: { callback: v => toFa(v) + '٪' } } },
                        plugins: { legend: { position: 'bottom', rtl: true } }
                    }
                });
            } catch (e) {
                console.error('smLoadHistory:', e);
            }
        }

        function smSelectRange(range, btn) {
            smCurrentRange = range;
            document.querySelectorAll('.sm-range-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            smLoadHistory(range);
        }

        document.addEventListener('DOMContentLoaded', function () {
            smLoadOnce();
            smTimer = setInterval(smLoadOnce, SM_POLL_MS);
            smLoadHistory(smCurrentRange);
            // 🔒 تاریخچه نیازی به رفرشِ هر ۳ثانیه نداره — هر ۲ دقیقه (هم‌زمان
            // با نمونه‌گیرِ پس‌زمینه‌ی go-api) برایِ تازه‌موندنِ نمودار کافیه
            setInterval(function () { smLoadHistory(smCurrentRange); }, 120000);
        });
    </script>
    <?php include 'footer.php'; ?>
</body>

</html>
