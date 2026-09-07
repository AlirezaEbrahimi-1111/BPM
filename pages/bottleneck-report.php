<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';
if (!hasPermission($__me, 'view_reports')) {
    header('Location: dashboard-manager.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحلیل گلوگاه‌ها - سیستم مدیریت کار</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">

    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/sections-helper.js') ?>"></script>

    <style>
        :root {
            --bn-red: #dc2626;
            --bn-red-soft: #fef2f2;
            --bn-orange: #ea580c;
            --bn-orange-soft: #fff7ed;
        }

        .bn-container {
            max-width: 70%;
            margin: 0 auto;
            padding: 24px 20px;
        }

        .bn-page-head {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 6px;
        }

        .bn-page-head h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }

        .bn-page-head i {
            font-size: 1.6rem;
            color: var(--bn-orange);
        }

        .bn-page-sub {
            color: #6b7280;
            font-size: .9rem;
            margin-bottom: 24px;
        }

        /* کارت‌های خلاصه */
        .bn-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .bn-stat {
            background: #fff;
            border: 1px solid #eef0f3;
            border-radius: 14px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .bn-stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .bn-stat-icon.stages {
            background: rgba(142, 87, 254, 0.12);
            color: #8e57fe;
        }

        .bn-stat-icon.stuck {
            background: var(--bn-red-soft);
            color: var(--bn-red);
        }

        .bn-stat-icon.worst {
            background: var(--bn-orange-soft);
            color: var(--bn-orange);
        }

        .bn-stat-num {
            font-size: 1.7rem;
            font-weight: 700;
            color: #1f2937;
        }

        .bn-stat-label {
            font-size: .82rem;
            color: #6b7280;
            margin-top: 2px;
        }

        /* جدول گلوگاه */
        .bn-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .bn-item {
            background: #fff; border: 1px solid #eef0f3; border-radius: 14px;
            overflow: hidden;
            transition: border-color .2s, box-shadow .2s, transform .15s;
        }
        .bn-item:hover { border-color: #fecaca; box-shadow: 0 3px 14px rgba(220,38,38,.06); }

        /* حالت باز — واضح و برجسته */
        .bn-item.open {
            border-color: var(--bn-orange);
            box-shadow: 0 6px 24px rgba(234, 88, 12, .14);
            transform: translateY(-1px);
        }
        .bn-item.open .bn-item-head {
            background: linear-gradient(90deg, var(--bn-orange-soft), #fff);
        }
        .bn-item.open .bn-stage-name {
            color: var(--bn-orange);
        }

        .bn-item-head {
            display: flex; align-items: center; gap: 14px;
            padding: 16px 18px; cursor: pointer;
            transition: background .2s;
        }

        .bn-rank {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #f3f4f6;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: .9rem;
            flex-shrink: 0;
        }

        .bn-item:first-child .bn-rank {
            background: var(--bn-red);
            color: #fff;
        }

        .bn-info {
            flex: 1;
            min-width: 0;
        }

        .bn-stage-name {
            font-size: 1rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .bn-template {
            font-size: .8rem;
            color: #9ca3af;
            margin-top: 3px;
        }

        .bn-unit-badge {
            font-size: .7rem;
            background: #f3f4f6;
            color: #6b7280;
            padding: 2px 8px;
            border-radius: 6px;
            font-weight: 600;
        }

        .bn-metrics {
            display: flex;
            gap: 22px;
            align-items: center;
        }

        .bn-metric {
            text-align: center;
        }

        .bn-metric-num {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .bn-metric-num.count {
            color: var(--bn-red);
        }

        .bn-metric-num.avg {
            color: var(--bn-orange);
        }

        .bn-metric-label {
            font-size: .68rem;
            color: #9ca3af;
            margin-top: 2px;
        }

        .bn-chevron {
            color: #d1d5db;
            font-size: 1rem;
            transition: transform .2s;
        }

        .bn-item.open .bn-chevron {
            transform: rotate(-90deg);
        }

        /* نوار شدت */
        .bn-bar-wrap {
            height: 5px;
            background: #f3f4f6;
            border-radius: 0;
            overflow: hidden;
        }

        .bn-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--bn-orange), var(--bn-red));
        }

        /* جزئیات (روتین‌های درگیر) */
        .bn-details {
            display: none;
            border-top: 1px solid #f3f4f6;
            padding: 8px;
            background: #fafafa;
        }

        .bn-item.open .bn-details {
            display: block;
        }

        .bn-instance {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            margin: 4px;
            background: #fff;
            border: 1px solid #f0f0f3;
            border-radius: 10px;
            cursor: pointer;
            transition: background .12s;
        }

        .bn-instance:hover {
            background: #fff8f0;
        }

        .bn-instance-title {
            flex: 1;
            min-width: 0;
            font-size: .86rem;
            color: #374151;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .bn-instance-delay {
            font-size: .76rem;
            font-weight: 600;
            color: var(--bn-red);
            background: var(--bn-red-soft);
            padding: 7px 10px;
            border-radius: 6px;
            flex-shrink: 0;
        }

        .bn-instance-started {
            font-size: .72rem;
            color: #9ca3af;
            flex-shrink: 0;
        }

        .bn-empty {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        .bn-empty i {
            font-size: 3rem;
            display: block;
            margin-bottom: 14px;
            color: #1b7b39;
        }

        .bn-loading {
            text-align: center;
            padding: 50px;
            color: #9ca3af;
        }

        @media (max-width: 768px) {
            .bn-summary {
                grid-template-columns: 1fr;
            }

            .bn-metrics {
                gap: 12px;
            }
        }

        /* ─── دارک‌مود ─── */
        :root[data-theme="dark"] .bn-page-head h1,
        :root[data-theme="dark"] .bn-stat-num,
        :root[data-theme="dark"] .bn-stage-name {
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .bn-page-sub,
        :root[data-theme="dark"] .bn-stat-label,
        :root[data-theme="dark"] .bn-template,
        :root[data-theme="dark"] .bn-metric-label,
        :root[data-theme="dark"] .bn-instance-started,
        :root[data-theme="dark"] .bn-empty,
        :root[data-theme="dark"] .bn-loading {
            color: var(--text-muted);
        }

        :root[data-theme="dark"] .bn-stat,
        :root[data-theme="dark"] .bn-item,
        :root[data-theme="dark"] .bn-instance {
            background: var(--surface);
            border-color: var(--border-soft);
        }

        :root[data-theme="dark"] .bn-item.open .bn-item-head {
            background: linear-gradient(90deg, var(--bn-orange-soft), var(--surface));
        }

        :root[data-theme="dark"] .bn-rank,
        :root[data-theme="dark"] .bn-unit-badge,
        :root[data-theme="dark"] .bn-bar-wrap {
            background: var(--border-soft);
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .bn-details {
            background: var(--bg-page);
            border-top-color: var(--border-soft);
        }

        :root[data-theme="dark"] .bn-instance:hover {
            background: var(--border-soft);
        }

        :root[data-theme="dark"] .bn-instance-title {
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .bn-chevron {
            color: var(--text-muted);
        }
    </style>
</head>

<body>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/pages/header.php'; ?>

    <div class="bn-container">

        <div class="bn-page-head">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <h1>تحلیل گلوگاه‌ها</h1>
        </div>
        <p class="bn-page-sub">
            مراحلی از فرآیندهای سازمان که هم‌اکنون معطل مانده‌اند، مرتب بر اساس شدت
        </p>

        <!-- کارت‌های خلاصه -->
        <div class="bn-summary" id="bnSummary">
            <div class="bn-stat">
                <div class="bn-stat-icon stages"><i class="bi bi-diagram-3"></i></div>
                <div>
                    <div class="bn-stat-num" id="sumStages">—</div>
                    <div class="bn-stat-label">مرحله دارای گلوگاه</div>
                </div>
            </div>
            <div class="bn-stat">
                <div class="bn-stat-icon stuck"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="bn-stat-num" id="sumStuck">—</div>
                    <div class="bn-stat-label">روتین معطل‌مانده</div>
                </div>
            </div>
            <div class="bn-stat">
                <div class="bn-stat-icon worst"><i class="bi bi-fire"></i></div>
                <div>
                    <div class="bn-stat-num" id="sumWorst" style="font-size:1.1rem;">—</div>
                    <div class="bn-stat-label">بحرانی‌ترین مرحله</div>
                </div>
            </div>
        </div>

        <!-- لیست گلوگاه‌ها -->
        <div class="bn-list" id="bnList">
            <div class="bn-loading">
                <div class="spinner-border text-secondary"></div>
                <div style="margin-top:12px;">در حال تحلیل…</div>
            </div>
        </div>

    </div>

    <script>
        // const authToken = localStorage.getItem('auth_token');
        if (!authToken) location.href = '/index.php';

        let maxSeverity = 1;

        /* اعداد فارسی */
        function toFa(n) {
            return String(n).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹' [d]);
        }

        /* ساعت → متن خوانا */
        function humanDelay(hours) {
            if (hours < 24) return `${toFa(hours)} ساعت`;
            const days = Math.floor(hours / 24);
            const rem = hours % 24;
            return rem > 0 ?
                `${toFa(days)} روز و ${toFa(rem)} ساعت` :
                `${toFa(days)} روز`;
        }

        /* میلادی → شمسی */
        function toJalali(gy, gm, gd) {
            const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
            let jy = (gy <= 1600) ? 0 : 979;
            gy -= (gy <= 1600) ? 621 : 1600;
            const gy2 = (gm > 2) ? (gy + 1) : gy;
            let days = (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) +
                Math.floor((gy2 + 399) / 400) - 80 + gd + g_d_m[gm - 1];
            jy += 33 * Math.floor(days / 12053);
            days %= 12053;
            jy += 4 * Math.floor(days / 1461);
            days %= 1461;
            jy += Math.floor((days - 1) / 365);
            if (days > 365) days = (days - 1) % 365;
            const jm = (days < 186) ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
            const jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
            return [jy, jm, jd];
        }

        /* تاریخ شروع → شمسی با ساعت */
        function fmtStarted(str) {
            if (!str) return '';
            const d = new Date(str.replace(' ', 'T'));
            if (isNaN(d)) return '';
            const [jy, jm, jd] = toJalali(d.getFullYear(), d.getMonth() + 1, d.getDate());
            const p = n => String(n).padStart(2, '0');
            const date = `${jy}/${p(jm)}/${p(jd)}`;
            const time = `${p(d.getHours())}:${p(d.getMinutes())}`;
            return toFa(`${date} - ${time}`);
        }

        /* نام فارسی واحد */
        function unitFa(sec) {
            if (!sec) return '';
            if (typeof sectionMap !== 'undefined' && sectionMap[sec]) return sectionMap[sec];
            return sec;
        }

        async function load() {
            // نام واحدها
            if (typeof loadSectionMap === 'function') {
                try {
                    await loadSectionMap();
                } catch (e) {}
            }

            try {
                const res = await fetch('../api/reports/bottleneck-report.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();

                if (!data.success) throw new Error(data.message || 'خطا');

                renderSummary(data.summary);
                renderList(data.bottlenecks || []);

            } catch (err) {
                document.getElementById('bnList').innerHTML =
                    `<div class="bn-empty"><i class="bi bi-exclamation-circle" style="color:#fca5a5;"></i>خطا در دریافت اطلاعات: ${err.message}</div>`;
            }
        }

        function renderSummary(s) {
            document.getElementById('sumStages').textContent = toFa(s.bottleneck_stages || 0);
            document.getElementById('sumStuck').textContent = toFa(s.total_stuck || 0);
            document.getElementById('sumWorst').textContent = s.worst_stage || '—';
        }

        function renderList(list) {
            const box = document.getElementById('bnList');

            if (!list.length) {
                box.innerHTML = `
                    <div class="bn-empty">
                        <i class="bi bi-check2-circle"></i>
                        هیچ گلوگاهی وجود ندارد — همهٔ فرآیندها روان پیش می‌روند
                    </div>`;
                return;
            }

            maxSeverity = list[0].severity || 1;

            box.innerHTML = list.map((b, idx) => {
                const barPct = Math.max(6, Math.round((b.severity / maxSeverity) * 100));

                const instances = b.instances.map(ins => `
                    <div class="bn-instance" onclick="location.href='workflow-monitor.php?instance=${ins.instance_id}'">
                        <i class="bi bi-arrow-repeat" style="color:#9ca3af;"></i>
                        <span class="bn-instance-title" title="${esc(ins.instance_title || '')}">${esc(ins.instance_title) || '—'}</span>
                        <span class="bn-instance-started">شروع: ${fmtStarted(ins.started_at)}</span>
                        <span class="bn-instance-delay">${humanDelay(ins.delay_hours)} تأخیر</span>
                    </div>
                `).join('');

                const stageKey = `${b.template_id || 0}::${b.step_name}`;
                return `
                <div class="bn-item" id="bnItem-${idx}" data-key="${stageKey.replace(/"/g, '&quot;')}">
                    <div class="bn-item-head" onclick="toggleItem(${idx})">
                        <div class="bn-rank">${toFa(idx + 1)}</div>
                        <div class="bn-info">
                            <div class="bn-stage-name">
                                ${esc(b.step_name) || 'نامشخص'}
                                ${b.activity_section ? `<span class="bn-unit-badge">${unitFa(b.activity_section)}</span>` : ''}
                            </div>
                            <div class="bn-template">${esc(b.template_name) || 'نامشخص'}</div>
                        </div>
                        <div class="bn-metrics">
                            <div class="bn-metric">
                                <div class="bn-metric-num count">${toFa(b.count)}</div>
                                <div class="bn-metric-label">روتین درگیر</div>
                            </div>
                            <div class="bn-metric">
                                <div class="bn-metric-num avg">${humanDelay(Math.round(b.avg_delay_hours))}</div>
                                <div class="bn-metric-label">میانگین تأخیر</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-left bn-chevron"></i>
                    </div>
                    <div class="bn-bar-wrap"><div class="bn-bar" style="width:${barPct}%;"></div></div>
                    <div class="bn-details">${instances}</div>
                </div>`;
            }).join('');
            // 🆕 اگر از داشبورد با ?stage آمده‌ایم، همان آکاردئون را باز کن
            const targetKey = new URLSearchParams(location.search).get('stage');
            if (targetKey) {
                const item = document.querySelector(`.bn-item[data-key="${targetKey}"]`);
                if (item) {
                    item.classList.add('open');
                    item.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            }
        }

        function toggleItem(idx) {
            document.getElementById('bnItem-' + idx).classList.toggle('open');
        }

        load();
    </script>
    <?php include 'footer.php'; ?>
</body>

</html>