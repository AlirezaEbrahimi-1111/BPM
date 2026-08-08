<?php

if (session_status() === PHP_SESSION_NONE) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

if (!isset($db)) {
    $database = new Database();
    $db = $database->getConnection();
}
$auth = new Auth($db);

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id)
    $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token']))
    $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    header('Location: ../../pages/index.php');
    exit;
}

// اطلاعاتِ کاربر + گیتِ دسترسی — قبلاً اینجا activity_section هم چک می‌شد
// («واحدِ مدیریت») که طبقِ اصلِ permissions.php هرگز نباید برایِ دسترسی
// چک بشه؛ الان فقط بر اساسِ role (از طریقِ اجازهٔ view_payroll که فقط
// supervisor داره) + سوپرادمین تصمیم گرفته می‌شه
$me = loadUserForPermissions($db, (int) $user_id);

if (!$me || !hasPermission($me, 'view_payroll')) {
    header('Location: ../../pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش حقوق پرسنل</title>
    <script src="../../assets/js/ag-grid-community.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/js/cdn/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/custom.css">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: #F7F8FC;
        }

        .payroll-wrap {
            max-width: 1200px;
            margin: 90px auto 40px;
            padding: 0 16px;
        }

        .payroll-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .payroll-toolbar h4 {
            margin: 0;
            color: #744CA4;
            font-weight: 700;
        }

        .payroll-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .payroll-controls select {
            padding: 9px 14px;
            border: 1px solid rgba(116, 76, 164, 0.25);
            border-radius: 10px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            color: #744CA4;
            background: #fff;
            cursor: pointer;
        }

        .btn-excel {
            padding: 9px 16px;
            border: none;
            border-radius: 10px;
            background: #16A34A;
            color: #fff;
            font-family: inherit;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-excel:hover {
            background: #15803D;
        }

        .payroll-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .pcard {
            background: #fff;
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }

        .pcard .lbl {
            font-size: 12px;
            color: #718096;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .pcard .val {
            font-size: 18px;
            font-weight: 800;
        }

        #payrollGrid {
            width: 100%;
            height: 620px;
        }

        .neg {
            color: #EF4444;
            font-weight: 700;
        }

        .pos {
            color: #16A34A;
            font-weight: 700;
        }

        /* مودال جزئیات کاربر */
        .ud-card { background:#F8FAFC; border:1px solid #EEF2F7; border-radius:12px; padding:10px 12px; }
        .ud-card .ud-lbl { font-size:11px; color:#718096; font-weight:600; margin-bottom:4px; }
        .ud-card .ud-val { font-size:15px; font-weight:800; }
        .ud-table { width:100%; border-collapse:collapse; font-size:13px; }
        .ud-table th { background:#744CA4; color:#fff; font-weight:700; padding:9px 8px; text-align:right; white-space:nowrap; position:sticky; top:0; z-index:2; }
        .ud-table td { padding:8px; border-bottom:1px solid #EEF2F7; vertical-align:middle; }
        .ud-table tbody tr:hover { background:#FAF7FF; }
        .ud-badge { display:inline-block; color:#fff; font-size:11px; font-weight:700; padding:2px 7px; border-radius:8px; margin:1px 0; white-space:nowrap; }
    </style>
    </style>
</head>

<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/pages/header.php'; ?>

    <div class="payroll-wrap">
        <div class="payroll-toolbar">
            <h4><i class="bi bi-cash-stack ms-2"></i>گزارش حقوق پرسنل</h4>
            <div class="payroll-controls">
                <select id="monthSelect"></select>
                <button class="btn-excel" id="btnExcel"><i class="bi bi-file-earmark-excel"></i> خروجی اکسل</button>
            </div>
        </div>

        <div class="payroll-cards">
            <div class="pcard">
                <div class="lbl">جمع حقوق پایه</div>
                <div class="val" id="sumBase" style="color:#744CA4;">—</div>
            </div>
            <div class="pcard">
                <div class="lbl">جمع جریمهٔ کسری</div>
                <div class="val" id="sumPenalty" style="color:#EF4444;">—</div>
            </div>
            <div class="pcard">
                <div class="lbl">جمع حقوق دریافتی تا دیروز</div>
                <div class="val" id="sumReceived" style="color:#8B5CF6;">—</div>
            </div>
            <div class="pcard">
                <div class="lbl">جمع کسری (ساعت:دقیقه)</div>
                <div class="val" id="sumShortage" style="color:#F59E0B;">—</div>
            </div>
        </div>

        <div id="payrollGrid" class="ag-theme-alpine"></div>
    </div>

    <!-- مودال جزئیات کارکرد کاربر -->
    <div class="modal fade" id="userDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px;overflow:hidden;max-width:1000px !important;">
                <div class="modal-header" style="background:#744CA4;color:#fff;border:none;">
                    <h5 class="modal-title" id="udTitle">جزئیات کارکرد</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body" id="udBody" style="background:#fff;"></div>
            </div>
        </div>
    </div>

    <script>
        const MONTH_NAMES = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        const faNum = s => String(s).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
        const toToman = rial => Math.round((rial || 0) / 10);
        function fmtToman(rial) {
            const t = toToman(rial);
            const neg = t < 0;
            const s = Math.abs(t).toLocaleString('en-US');
            return faNum((neg ? '-' : '') + s) + ' تومان';
        }

        let gridApi = null;
        let lastRows = [];
        let lastTotals = null;
        let lastLabel = '';

        function buildMonthSelector(jy, jm) {
            const sel = document.getElementById('monthSelect');
            sel.innerHTML = '';
            let y = jy, m = jm;
            for (let i = 0; i < 12; i++) {
                const opt = document.createElement('option');
                opt.value = y + '-' + m;
                opt.textContent = faNum(MONTH_NAMES[m - 1] + ' ' + y);
                sel.appendChild(opt);
                m--;
                if (m < 1) { m = 12; y--; }
            }
            sel.value = jy + '-' + jm;
        }

        function buildGrid(rows) {
            const el = document.getElementById('payrollGrid');
            const cols = [
                { headerName: 'نام', field: 'name', flex: 1, minWidth: 150 },
                { headerName: 'واحد', field: 'section', width: 130 },
                { headerName: 'جمع مرخصی/پاس', field: 'leave_pass_hms', width: 130, valueFormatter: p => faNum(p.value || '0:00') },
                {
                    headerName: 'سهمیه مانده', field: 'leave_pass_remaining_hms', width: 100,
                    valueFormatter: p => faNum(p.value || '0:00'),
                    cellClass: p => (p.data.leave_pass_remaining_minutes < 0 ? 'neg' : 'pos')
                },
                { headerName: 'حقوق پایه (تومان)', width: 160, valueGetter: p => p.data.base_salary, valueFormatter: p => fmtToman(p.value) },
                { headerName: 'کسری ×۲', field: 'final_hms', width: 110, valueFormatter: p => faNum(p.value || '0:00') },
                { headerName: 'جریمهٔ کسری (تومان)', width: 170, valueGetter: p => p.data.shortage_money, valueFormatter: p => fmtToman(p.value) },
                {
                    headerName: 'حقوق تا دیروز', flex: 1, minWidth: 170,
                    valueGetter: p => p.data.salary_received,
                    valueFormatter: p => fmtToman(p.value),
                    cellClass: p => (p.value < 0 ? 'neg' : 'pos')
                }
            ];

            if (!gridApi) {
               gridApi = agGrid.createGrid(el, {
                    enableRtl: true,
                    rowHeight: 44,
                    headerHeight: 46,
                    pagination: true,
                    paginationPageSize: 20,
                    onPaginationChanged: () => persianizePaging(),
                    onRowClicked: (e) => openUserDetail(e.data),
                    rowStyle: { cursor: 'pointer' },
                    columnDefs: cols,
                    rowData: rows,
                    overlayNoRowsTemplate: '<div style="padding:2rem;color:#718096;font-weight:600;">داده‌ای برای این ماه نیست</div>'
                });
            } else {
                gridApi.setGridOption('rowData', rows);
            }
        }

        function fillCards(t) {
            document.getElementById('sumBase').textContent = fmtToman(t.base_salary);
            document.getElementById('sumPenalty').textContent = fmtToman(t.shortage_money);
            document.getElementById('sumReceived').textContent = fmtToman(t.salary_received);
            document.getElementById('sumShortage').textContent = faNum(t.final_hms || '0:00');
        }

        async function loadData(jy, jm) {
            try {
                const url = '../api/attendance/payroll-report.php' + ((jy && jm) ? ('?jy=' + jy + '&jm=' + jm) : '');
                const res = await fetch(url);
                const data = await res.json();
                if (!data || !data.success) {
                    alert((data && data.message) ? data.message : 'خطا در دریافت داده');
                    return;
                }
                lastRows = data.rows || [];
                lastTotals = data.totals || {};
                lastLabel = MONTH_NAMES[(data.jm - 1)] + ' ' + data.jy;

                if (document.getElementById('monthSelect').options.length === 0) {
                    buildMonthSelector(data.jy, data.jm);
                }
                buildGrid(lastRows);
                fillCards(lastTotals);
            } catch (e) {
                console.error(e);
                alert('خطا در ارتباط با سرور');
            }
        }

        function exportExcel() {
            if (!lastRows.length) { alert('داده‌ای برای خروجی نیست'); return; }
            const aoa = [['نام', 'واحد', 'جمع مرخصی/پاس (ساعت:دقیقه)', 'سهمیهٔ باقی‌مانده/تجاوز (ساعت:دقیقه)', 'حقوق پایه (تومان)', 'کسری ×۲ (ساعت:دقیقه)', 'جریمهٔ کسری (تومان)', 'حقوق دریافتی تا دیروز (تومان)']];
            lastRows.forEach(r => {
                aoa.push([
                    r.name,
                    r.section,
                    r.leave_pass_hms,
                    r.leave_pass_remaining_hms,
                    toToman(r.base_salary),
                    r.final_hms,
                    toToman(r.shortage_money),
                    toToman(r.salary_received)
                ]);
            });
            // ردیف جمع
            aoa.push([
                'جمع کل', '',
                lastTotals.leave_pass_hms,
                lastTotals.leave_pass_remaining_hms,
                toToman(lastTotals.base_salary),
                lastTotals.final_hms,
                toToman(lastTotals.shortage_money),
                toToman(lastTotals.salary_received)
            ]);

            const ws = XLSX.utils.aoa_to_sheet(aoa);
            ws['!cols'] = [{ wch: 22 }, { wch: 14 }, { wch: 20 }, { wch: 22 }, { wch: 18 }, { wch: 16 }, { wch: 18 }, { wch: 24 }];
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'حقوق');
            XLSX.writeFile(wb, 'payroll-' + lastLabel.replace(/\s/g, '-') + '.xlsx');
        }

        const minToHM = m => {
            m = Math.round(m || 0);
            if (m <= 0) return '0:00';
            return Math.floor(m / 60) + ':' + String(m % 60).padStart(2, '0');
        };
        const REQ_LABELS = { mission: 'مأموریت', leave: 'مرخصی', pass: 'پاس', technical: 'مشکل فنی', forget: 'فراموشی ثبت' };
        const REQ_COLORS = { mission: '#0EA5E9', leave: '#8B5CF6', pass: '#F59E0B', technical: '#EF4444', forget: '#64748B' };
        let udModal = null;

        async function openUserDetail(row) {
            if (!row || !row.user_id) return;
            const [jy, jm] = (document.getElementById('monthSelect').value || '').split('-').map(Number);
            if (!jy || !jm) return;

            if (!udModal) udModal = new bootstrap.Modal(document.getElementById('userDetailModal'));
            document.getElementById('udTitle').textContent = 'جزئیات کارکرد — ' + (row.name || '');
            document.getElementById('udBody').innerHTML = '<div style="padding:2.5rem;text-align:center;color:#718096;font-weight:600;">در حال بارگذاری…</div>';
            udModal.show();

            try {
                const url = '../api/attendance/payroll-user-detail.php?user_id=' + row.user_id + '&jy=' + jy + '&jm=' + jm;
                const res = await fetch(url);
                const data = await res.json();
                if (!data || !data.success) {
                    document.getElementById('udBody').innerHTML = '<div style="padding:2.5rem;text-align:center;color:#EF4444;font-weight:600;">' + ((data && data.message) ? data.message : 'خطا در دریافت داده') + '</div>';
                    return;
                }
                renderUserDetail(data);
            } catch (e) {
                console.error(e);
                document.getElementById('udBody').innerHTML = '<div style="padding:2.5rem;text-align:center;color:#EF4444;font-weight:600;">خطا در ارتباط با سرور</div>';
            }
        }

        function renderUserDetail(data) {
            const s = data.summary || {};
            const u = data.user || {};
            const days = data.days || [];
            const dash = '—';
            const tt = v => v ? faNum(v) : dash;

            const cards =
                '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px;">' +
                '<div class="ud-card"><div class="ud-lbl">واحد</div><div class="ud-val" style="color:#744CA4;">' + (u.section || dash) + '</div></div>' +
                '<div class="ud-card"><div class="ud-lbl">حقوق پایه</div><div class="ud-val" style="color:#744CA4;">' + fmtToman(s.monthly_salary) + '</div></div>' +
                '<div class="ud-card"><div class="ud-lbl">کسری ×۲ (تا دیروز)</div><div class="ud-val" style="color:#F59E0B;">' + faNum(s.final_hms || '0:00') + '</div></div>' +
                '<div class="ud-card"><div class="ud-lbl">جریمهٔ کسری</div><div class="ud-val" style="color:#EF4444;">' + fmtToman(s.shortage_money) + '</div></div>' +
                '<div class="ud-card"><div class="ud-lbl">حقوق دریافتی تا دیروز</div><div class="ud-val" style="color:' + ((s.salary_received < 0) ? '#EF4444' : '#16A34A') + ';">' + fmtToman(s.salary_received) + '</div></div>' +
                '</div>';

            let tInit = 0, tCov = 0;
            let body = '';
            days.forEach(d => {
                const today_ymd = data.today_ymd || '';
                const isPast = d.is_counted || (!d.is_holiday && d.date < (data.end_of_month || '') && d.initial_minutes !== undefined && d.date <= today_ymd);
                const muted = !d.is_counted;
                const dayLabel = faNum(d.jalali_date || '') + ' <span style="color:#94A3B8;">' + (d.day_name || '') + '</span>';
                let c2, c3, c4, c5, c6, c7;

                if (d.is_holiday) {
                    c2 = '<span style="color:#94A3B8;">' + (d.holiday_title || 'تعطیل') + '</span>';
                    c3 = c4 = c5 = c6 = c7 = dash;
                } else if (!d.is_counted && !(d.shift1_in || d.shift1_out || d.shift2_in || d.shift2_out)) {
                    c2 = '<span style="color:#94A3B8;">امروز / آینده</span>';
                    c3 = c4 = c5 = c6 = c7 = dash;
                } else {
                    const sh1 = (d.shift1_in || d.shift1_out) ? (tt(d.shift1_in) + ' / ' + tt(d.shift1_out)) : dash;
                    const sh2 = (u.shift_count >= 2) ? ((d.shift2_in || d.shift2_out) ? (tt(d.shift2_in) + ' / ' + tt(d.shift2_out)) : dash) : '';
                    c2 = sh1 + (sh2 ? '<br>' + sh2 : '');
                    c3 = faNum(d.initial_hms || '0:00');
                    const badges = (d.requests || []).map(r =>
                        '<span class="ud-badge" style="background:' + (REQ_COLORS[r.type] || '#64748B') + ';">' + (REQ_LABELS[r.type] || r.type) + ' ' + faNum(r.start_time) + '–' + faNum(r.end_time) + '</span>'
                    ).join(' ');
                    c4 = (badges ? badges + '<br>' : '') + '<span style="color:#16A34A;font-weight:700;">پوشش: ' + faNum(d.covered_hms || '0:00') + '</span>';
                    c5 = faNum(d.uncovered_hms || '0:00');
                    c6 = '<span style="color:#EF4444;font-weight:700;">' + faNum(d.final_hms || '0:00') + '</span>';
                    c7 = (d.money > 0) ? '<span style="color:#EF4444;">' + fmtToman(d.money) + '</span>' : dash;
                    tInit += (d.initial_minutes || 0);
                    tCov += (d.covered_minutes || 0);
                }

                body += '<tr style="' + (muted ? 'opacity:.5;' : '') + '">' +
                    '<td style="white-space:nowrap;">' + dayLabel + '</td>' +
                    '<td>' + c2 + '</td>' +
                    '<td style="text-align:center;">' + c3 + '</td>' +
                    '<td>' + c4 + '</td>' +
                    '<td style="text-align:center;">' + c5 + '</td>' +
                    '<td style="text-align:center;">' + c6 + '</td>' +
                    '<td style="text-align:left;white-space:nowrap;">' + c7 + '</td>' +
                    '</tr>';
            });

            const totalRow =
                '<tr style="background:#F1F5F9;font-weight:800;">' +
                '<td>جمع کل</td>' +
                '<td></td>' +
                '<td style="text-align:center;">' + faNum(minToHM(tInit)) + '</td>' +
                '<td style="color:#16A34A;">پوشش: ' + faNum(minToHM(tCov)) + '</td>' +
                '<td style="text-align:center;">' + faNum(s.before_hms || '0:00') + '</td>' +
                '<td style="text-align:center;color:#EF4444;">' + faNum(s.final_hms || '0:00') + '</td>' +
                '<td style="text-align:left;color:#EF4444;white-space:nowrap;">' + fmtToman(s.shortage_money) + '</td>' +
                '</tr>';

            const table =
                '<div style="overflow:auto;max-height:58vh;">' +
                '<table class="ud-table"><thead><tr>' +
                '<th>تاریخ / روز</th>' +
                '<th>ورود / خروج' + (u.shift_count >= 2 ? ' (دو شیفت)' : '') + '</th>' +
                '<th>کسری اولیه</th>' +
                '<th>درخواست‌ها / پوشش</th>' +
                '<th>کسری مانده</th>' +
                '<th>کسری نهایی ×۲</th>' +
                '<th>کسری ریالی</th>' +
                '</tr></thead><tbody>' + body + totalRow + '</tbody></table></div>';

            const note = '<div style="margin-top:10px;font-size:11px;color:#94A3B8;line-height:1.9;">' +
                '• فقط روزهای کاری (غیرتعطیل) و «تا دیروز» در جمع لحاظ می‌شوند.<br>' +
                '• «کسری مانده» = کسری اولیه منهای پوشش درخواست‌ها &nbsp;|&nbsp; «کسری نهایی» = کسری مانده × ضریب جریمه.<br>' +
                '• نرخ هر ساعت: ' + fmtToman(s.hourly_rate) + ' (حقوق پایه ÷ ' + faNum(s.divisor_days) + ' روزِ کاری ÷ ساعت کاری روزانه).' +
                '</div>';

            document.getElementById('udBody').innerHTML = cards + table + note;
        }

        document.getElementById('monthSelect').addEventListener('change', function () {
            const [y, m] = this.value.split('-').map(Number);
            loadData(y, m);
        });
        document.getElementById('btnExcel').addEventListener('click', exportExcel);

        // بارگذاری اولیه (ماه جاری)
        loadData();
    </script>
    <script>
window.persianizePaging = function () {
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
};
</script>
    <script src="../../assets/js/cdn/bootstrap.bundle.min.js"></script>
</body>

</html>