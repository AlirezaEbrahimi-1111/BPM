<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/crm_access.php';

// 🔒 دسترسی موقت: مثل گزارش‌های فاکتور رسمی — id=1/19 + تیم حسابداری سازمان ۱
// + مدیران/سوپروایزرهای سازمان ۱ (همان crmReportAllowed).
if (!crmReportAllowed($db, (int) $user_id)) {
    header('Location: dashboard-manager.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>بدهکاران و بستانکاران</title>
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('../assets/css/custom.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/xlsx.full.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/chart.js') ?>"></script>
    <style>
        :root {
            --fb-bg:      #f5f1ff;
            --fb-surface: #ffffff;
            --fb-border:  #e6e2f0;
            --fb-text:    #2d3748;
            --fb-muted:   #6b7490;
            --fb-accent:  #8e57fe;
            --fb-debit:   #2f855a;  /* بدهکار (سبز) */
            --fb-credit:  #c53030;  /* بستانکار (قرمز) */
        }
        :root[data-theme="dark"] {
            --fb-bg:      var(--bg-page, #141a24);
            --fb-surface: var(--surface, #1b2130);
            --fb-border:  var(--border-soft, #2b3447);
            --fb-text:    var(--text-strong, #e8ecf3);
            --fb-muted:   var(--text-muted, #9aa6bd);
        }
        body { background: var(--fb-bg); color: var(--fb-text); font-family: 'Vazirmatn', system-ui, sans-serif; }
        .fb-wrap { max-width: 1250px; margin: 86px auto 70px; padding: 0 16px; }
        .fb-title { color: var(--fb-accent); font-weight: 800; margin: 0 0 4px; }
        .fb-sub { color: var(--fb-muted); font-size: 13px; margin-bottom: 20px; }

        .fb-upload {
            background: var(--fb-surface); border: 1px solid var(--fb-border);
            border-radius: 16px; padding: 20px; margin-bottom: 22px;
            box-shadow: 0 2px 12px rgba(0,0,0,.04);
        }
        .fb-slots { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
        .fb-slot {
            border: 1.5px dashed var(--fb-border); border-radius: 12px; padding: 14px;
            transition: border-color .2s, background .2s; position: relative;
        }
        .fb-slot.ok { border-style: solid; border-color: var(--fb-debit); background: rgba(47,133,90,.06); }
        .fb-slot label { font-weight: 700; font-size: 13.5px; display: block; margin-bottom: 8px; }
        .fb-slot .hint { font-size: 11.5px; color: var(--fb-muted); margin-bottom: 8px; }
        .fb-slot input[type=file] { font-size: 12px; width: 100%; }
        .fb-slot .fname { font-size: 12px; color: var(--fb-debit); margin-top: 6px; word-break: break-all; }

        .fb-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .fb-card { background: var(--fb-surface); border: 1px solid var(--fb-border); border-radius: 14px; padding: 16px 18px; }
        .fb-card .lbl { font-size: 12.5px; color: var(--fb-muted); font-weight: 600; margin-bottom: 6px; }
        .fb-card .val { font-size: 20px; font-weight: 800; letter-spacing: .3px; }
        .fb-card .val.debit { color: var(--fb-debit); }
        .fb-card .val.credit { color: var(--fb-credit); }

        .fb-grid-box { background: var(--fb-surface); border: 1px solid var(--fb-border); border-radius: 14px; padding: 14px; margin-bottom: 20px; }
        .fb-grid-box h5 { font-weight: 700; font-size: 15px; margin: 2px 4px 12px; }
        .fb-chart-box { background: var(--fb-surface); border: 1px solid var(--fb-border); border-radius: 14px; padding: 16px; margin-bottom: 20px; }
        .fb-recon-ok { color: var(--fb-debit); } .fb-recon-bad { color: var(--fb-credit); }
        #results { display: none; }
        .alarm-cell { font-size: 15px; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="fb-wrap">
    <h3 class="fb-title"><i class="bi bi-cash-stack"></i> بدهکاران و بستانکاران</h3>
    <div class="fb-sub">سه گزارشِ خامِ شایگان را آپلود کن؛ سیستم خودش نام‌ها را پاک‌سازی و مانده/سررسید را محاسبه می‌کند. (نسخهٔ آزمایشی — فقط مدیر کل)</div>

    <div class="fb-upload">
        <div class="fb-slots">
            <div class="fb-slot" id="slot-trial">
                <label><i class="bi bi-1-circle"></i> تراز کلیه حسابها</label>
                <div class="hint">ستون‌ها: نام حساب، بدهکار، مانده/بستانکار</div>
                <input type="file" accept=".xls,.xlsx" data-kind="trial">
                <div class="fname"></div>
            </div>
            <div class="fb-slot" id="slot-detail">
                <label><i class="bi bi-2-circle"></i> تفصیل شناور</label>
                <div class="hint">ستون‌ها: نام تفصیلی، نتیجه (= سررسید)</div>
                <input type="file" accept=".xls,.xlsx" data-kind="detail">
                <div class="fname"></div>
            </div>
            <div class="fb-slot" id="slot-overdue">
                <label><i class="bi bi-3-circle"></i> فاکتورهای معوق</label>
                <div class="hint">ستون‌ها: نام مشتری، مبلغ، تاریخ سررسید</div>
                <input type="file" accept=".xls,.xlsx" data-kind="overdue">
                <div class="fname"></div>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2 align-items-center">
            <button class="btn btn-primary" id="btnCompute" disabled><i class="bi bi-calculator"></i> محاسبه</button>
            <button class="btn btn-outline-success" id="btnExport" hidden><i class="bi bi-file-earmark-excel"></i> خروجی اکسل</button>
            <span id="status" style="font-size:13px;color:var(--fb-muted)"></span>
        </div>
    </div>

    <div id="results">
        <div class="fb-cards" id="cards"></div>
        <div class="fb-chart-box">
            <h5 style="font-weight:700;font-size:15px;margin:0 0 10px">۱۰ بدهکارِ برتر</h5>
            <canvas id="topChart" height="120"></canvas>
        </div>
        <div class="fb-grid-box">
            <h5>بدهکاران <span id="debCount" style="color:var(--fb-muted);font-weight:500"></span></h5>
            <div id="gridDebtors" class="ag-theme-alpine" style="height:420px;width:100%"></div>
        </div>
        <div class="fb-grid-box">
            <h5>بستانکاران <span id="credCount" style="color:var(--fb-muted);font-weight:500"></span></h5>
            <div id="gridCreditors" class="ag-theme-alpine" style="height:420px;width:100%"></div>
        </div>
        <div class="fb-grid-box">
            <h5>سررسیدهای ۳۰ روزه (فاکتورهای معوق)</h5>
            <div id="gridDue" class="ag-theme-alpine" style="height:320px;width:100%"></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

<script>
// ابزارِ رقم فارسی + جداکنندهٔ هزارگان
function fbMoney(n) {
    if (n === null || n === undefined || n === '' || isNaN(n)) return '۰';
    return String(Math.round(Number(n)).toLocaleString('en-US')).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
}
function fbNum(s) { return String(s).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }

var files = { trial: null, detail: null, overdue: null };

document.querySelectorAll('.fb-slot input[type=file]').forEach(function (inp) {
    inp.addEventListener('change', function () {
        var kind = inp.dataset.kind;
        var slot = inp.closest('.fb-slot');
        if (inp.files && inp.files[0]) {
            files[kind] = inp.files[0];
            slot.classList.add('ok');
            slot.querySelector('.fname').textContent = '✓ ' + inp.files[0].name;
        } else {
            files[kind] = null;
            slot.classList.remove('ok');
            slot.querySelector('.fname').textContent = '';
        }
        // حداقل «تراز» لازم است تا دکمه فعال شود
        document.getElementById('btnCompute').disabled = !files.trial;
    });
});

// خواندن یک فایل اکسل → ماتریسِ سلول‌ها (SheetJS؛ هم xls هم xlsx)
async function readMatrix(file) {
    if (!file) return [];
    var buf = await file.arrayBuffer();
    var wb = XLSX.read(buf, { type: 'array' });
    var ws = wb.Sheets[wb.SheetNames[0]];
    return XLSX.utils.sheet_to_json(ws, { header: 1, raw: true, defval: '' });
}

var debtorsGrid, creditorsGrid, dueGrid, chart;

document.getElementById('btnCompute').addEventListener('click', async function () {
    var st = document.getElementById('status');
    st.textContent = 'در حال خواندن فایل‌ها…';
    this.disabled = true;
    try {
        var payload = {
            trial:   await readMatrix(files.trial),
            detail:  await readMatrix(files.detail),
            overdue: await readMatrix(files.overdue),
        };
        st.textContent = 'در حال محاسبه روی سرور…';
        var res = await fetch('/api/finance/compute-balances.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload),
        });
        var json = await res.json();
        if (!json.success) throw new Error(json.message || 'خطای سرور');
        render(json.data);
        st.textContent = 'انجام شد ✓';
    } catch (e) {
        st.textContent = 'خطا: ' + (e.message || e);
    } finally {
        this.disabled = false;
    }
});

function moneyFmt(p) { return fbMoney(p.value); }

function render(d) {
    document.getElementById('results').style.display = 'block';
    lastResult = d;                                            // برای «خروجی اکسل»
    document.getElementById('btnExport').hidden = false;

    // ── کارت‌های خلاصه ──
    var s = d.summary;
    var reconOk = Math.abs(s.reconciliation) < 1000;
    document.getElementById('cards').innerHTML =
        card('جمع بدهکاری', fbMoney(s.debtor_total), 'debit') +
        card('جمع بستانکاری', fbMoney(s.creditor_total), 'credit') +
        card('تعداد بدهکاران', fbNum(s.debtor_count), '') +
        card('تعداد بستانکاران', fbNum(s.creditor_count), '') +
        card('تطابق مانده', reconOk ? 'سالم ✓' : ('اختلاف: ' + fbMoney(s.reconciliation)),
             reconOk ? '' : 'credit', reconOk ? 'fb-recon-ok' : 'fb-recon-bad');

    // ── ردیف‌های یکدستِ بدهکاران/بستانکاران (سررسید شده + نشده) ──
    var debtorRows = flatten(d.debtors);
    var creditorRows = flatten(d.creditors);

    var colsBal = [
        { headerName: 'نام حساب', field: 'name', flex: 2, minWidth: 220, filter: true },
        { headerName: 'وضعیت', field: 'status', width: 130 },
        { headerName: 'مانده', field: 'mande', width: 160, valueFormatter: moneyFmt, type: 'rightAligned' },
        { headerName: 'سررسید', field: 'sarresid', width: 160, valueFormatter: moneyFmt, type: 'rightAligned' },
        { headerName: 'سررسید واقعی', field: 'actual', width: 160, valueFormatter: moneyFmt, type: 'rightAligned' },
        { headerName: 'هشدار', field: 'alarm', width: 90, cellClass: 'alarm-cell' },
    ];

    debtorsGrid = remakeGrid('gridDebtors', debtorsGrid, colsBal, debtorRows);
    creditorsGrid = remakeGrid('gridCreditors', creditorsGrid, colsBal, creditorRows);
    document.getElementById('debCount').textContent = '(' + fbNum(debtorRows.length) + ' مورد)';
    document.getElementById('credCount').textContent = '(' + fbNum(creditorRows.length) + ' مورد)';

    var colsDue = [
        { headerName: 'نام مشتری', field: 'name', flex: 2, minWidth: 220, filter: true },
        { headerName: 'مبلغ', field: 'amount', width: 180, valueFormatter: moneyFmt, type: 'rightAligned' },
        { headerName: 'تاریخ سررسید', field: 'due', width: 160, valueFormatter: p => fbNum(p.value || '') },
    ];
    dueGrid = remakeGrid('gridDue', dueGrid, colsDue, d.due30 || []);

    drawChart(d.debtors.matured.concat(d.debtors.unmatured));
}

function flatten(side) {
    var out = [];
    (side.matured || []).forEach(function (r) {
        out.push({ name: r.name, status: 'سررسید شده', mande: r.mande, sarresid: r.sarresid, actual: r.actual, alarm: r.alarm ? '🔔' : '' });
    });
    (side.unmatured || []).forEach(function (r) {
        out.push({ name: r.name, status: 'سررسید نشده', mande: r.mande, sarresid: null, actual: null, alarm: '' });
    });
    return out;
}

function card(lbl, val, cls, extra) {
    return '<div class="fb-card"><div class="lbl">' + lbl + '</div>' +
           '<div class="val ' + (cls || '') + ' ' + (extra || '') + '">' + val + '</div></div>';
}

function remakeGrid(elId, existing, cols, rows) {
    var el = document.getElementById(elId);
    if (existing) { existing.destroy(); el.innerHTML = ''; }
    return agGrid.createGrid(el, {
        enableRtl: true,
        defaultColDef: { sortable: true, resizable: true },
        columnDefs: cols,
        rowData: rows,
        rowHeight: 38,
        overlayNoRowsTemplate: '<div style="padding:1.5rem;color:#888">موردی نیست</div>',
    });
}

// ── خروجی اکسل ─────────────────────────────────────────────────────────
// یک فایل چهاربرگی: خلاصه + بدهکاران + بستانکاران + سررسیدهای ۳۰ روزه، با همان
// SheetJS که فایل‌های شایگان را می‌خواند. برگه‌ها همان چیزی‌اند که در جدول دیده
// می‌شود (فیلتر و مرتب‌سازی فعلی). مبلغ‌ها «عدد» با قالب هزارگان‌اند (نه متن) تا
// در اکسل جمع بخورند؛ آخر هر برگه یک ردیف «جمع». تاریخ از ساعت سرور (TimeSync).
var lastResult = null;

function xlsxDateParts() {
    var j = (window.TimeSync && TimeSync.serverJalali) ? TimeSync.serverJalali() : null;
    if (!j) return null;
    var p2 = function (n) { return (n < 10 ? '0' : '') + n; };
    return { slash: j[0] + '/' + p2(j[1]) + '/' + p2(j[2]), dash: j[0] + '-' + p2(j[1]) + '-' + p2(j[2]) };
}

// aoa = آرایهٔ سطرها؛ moneyCols = اندیس ستون‌های مبلغ؛ widths = عرض ستون‌ها (کاراکتر)
function xlsxSheet(aoa, moneyCols, widths) {
    var ws = XLSX.utils.aoa_to_sheet(aoa);
    var range = XLSX.utils.decode_range(ws['!ref']);
    for (var r = range.s.r; r <= range.e.r; r++) {
        moneyCols.forEach(function (c) {
            var cell = ws[XLSX.utils.encode_cell({ r: r, c: c })];
            if (cell && cell.t === 'n') cell.z = '#,##0';
        });
    }
    ws['!cols'] = widths.map(function (w) { return { wch: w }; });
    return ws;
}

// سطرهای یک جدول AG Grid به ترتیب و فیلترِ فعلی؛ cols = [{h: عنوان, v: data => مقدار, money: bool}]
function gridAoa(api, cols) {
    var aoa = [cols.map(function (c) { return c.h; })];
    var totals = cols.map(function () { return 0; });
    if (api) {
        api.forEachNodeAfterFilterAndSort(function (n) {
            aoa.push(cols.map(function (c, i) {
                var v = c.v(n.data);
                if (c.money) {
                    if (v === null || v === undefined || v === '' || isNaN(v)) return '';
                    v = Math.round(Number(v));
                    totals[i] += v;
                }
                return v;
            }));
        });
    }
    aoa.push(cols.map(function (c, i) { return i === 0 ? 'جمع' : (c.money ? totals[i] : ''); }));
    return aoa;
}

function exportExcel() {
    if (!lastResult || typeof XLSX === 'undefined') return;
    var s = lastResult.summary;
    var dp = xlsxDateParts();
    var wb = XLSX.utils.book_new();
    wb.Workbook = { Views: [{ RTL: true }] };              // برگه‌ها راست‌به‌چپ

    var summary = [
        ['گزارش بدهکاران و بستانکاران'],
        ['تاریخ تهیه', dp ? dp.slash : ''],
        [],
        ['جمع بدهکاری', Math.round(s.debtor_total)],
        ['جمع بستانکاری', Math.round(s.creditor_total)],
        ['تعداد بدهکاران', s.debtor_count],
        ['تعداد بستانکاران', s.creditor_count],
        ['اختلاف تطابق مانده', Math.round(s.reconciliation)],
        [],
        ['فایل تراز کلیه حساب‌ها', files.trial ? files.trial.name : ''],
        ['فایل تفصیل شناور', files.detail ? files.detail.name : ''],
        ['فایل فاکتورهای معوق', files.overdue ? files.overdue.name : ''],
    ];
    XLSX.utils.book_append_sheet(wb, xlsxSheet(summary, [1], [26, 30]), 'خلاصه');

    var balCols = [
        { h: 'نام حساب', v: function (d) { return d.name; } },
        { h: 'وضعیت', v: function (d) { return d.status; } },
        { h: 'مانده', v: function (d) { return d.mande; }, money: true },
        { h: 'سررسید', v: function (d) { return d.sarresid; }, money: true },
        { h: 'سررسید واقعی', v: function (d) { return d.actual; }, money: true },
        { h: 'هشدار', v: function (d) { return d.alarm ? 'دارد' : ''; } },
    ];
    var balWidths = [40, 14, 18, 18, 18, 8];
    XLSX.utils.book_append_sheet(wb, xlsxSheet(gridAoa(debtorsGrid, balCols), [2, 3, 4], balWidths), 'بدهکاران');
    XLSX.utils.book_append_sheet(wb, xlsxSheet(gridAoa(creditorsGrid, balCols), [2, 3, 4], balWidths), 'بستانکاران');

    var dueCols = [
        { h: 'نام مشتری', v: function (d) { return d.name; } },
        { h: 'مبلغ', v: function (d) { return d.amount; }, money: true },
        { h: 'تاریخ سررسید', v: function (d) { return d.due || ''; } },
    ];
    XLSX.utils.book_append_sheet(wb, xlsxSheet(gridAoa(dueGrid, dueCols), [1], [40, 18, 14]), 'سررسیدهای ۳۰ روزه');

    XLSX.writeFile(wb, 'بدهکاران-و-بستانکاران' + (dp ? '-' + dp.dash : '') + '.xlsx');
}

document.getElementById('btnExport').addEventListener('click', exportExcel);

function drawChart(debtors) {
    var top = debtors.filter(r => r.mande > 0).sort((a, b) => b.mande - a.mande).slice(0, 10);
    var labels = top.map(r => r.name);
    var data = top.map(r => Math.round(r.mande));
    if (chart) chart.destroy();
    var ctx = document.getElementById('topChart').getContext('2d');
    var dark = document.documentElement.getAttribute('data-theme') === 'dark';
    chart = new Chart(ctx, {
        type: 'bar',
        data: { labels: labels, datasets: [{ label: 'مانده', data: data, backgroundColor: 'rgba(142,87,254,.7)', borderRadius: 6 }] },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false },
                tooltip: { callbacks: { label: c => fbMoney(c.raw) } } },
            scales: {
                x: { ticks: { color: dark ? '#9aa6bd' : '#6b7490', callback: v => fbMoney(v) }, grid: { color: dark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.05)' } },
                y: { ticks: { color: dark ? '#e8ecf3' : '#2d3748', font: { size: 11 } }, grid: { display: false } },
            },
        },
    });
}
</script>
</body>
</html>
