<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

// 🔒 فقط مدیر کل + کاربران مجاز: ورود یکباره‌ی فاکتورهای قدیمی (پیش از BPM) از اکسل.
// سرور هم مستقل همین را چک می‌کند (crm-service requireLegacyImport / legacyImportUserIDs).
// ۱۵۴ = رضا بادپر (سازمان ۱) — درخواست ۱۴۰۵/۰۸/۰۲
$legacyImportUserIds = [154];
if (!in_array((int) $user_id, getSuperAdminIds(), true) && !in_array((int) $user_id, $legacyImportUserIds, true)) {
    header('Location: dashboard-manager.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ورود فاکتورهای قدیمی</title>
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('../assets/css/custom.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/xlsx.full.min.js') ?>"></script>
    <style>
        :root {
            --il-bg: #f5f1ff; --il-surface: #ffffff; --il-border: #e6e2f0;
            --il-text: #2d3748; --il-muted: #6b7490; --il-accent: #8e57fe;
            --il-ok: #2f855a; --il-bad: #c53030; --il-warn: #b45309;
        }
        :root[data-theme="dark"] {
            --il-bg: var(--bg-page, #141a24); --il-surface: var(--surface, #1b2130);
            --il-border: var(--border-soft, #2b3447); --il-text: var(--text-strong, #e8ecf3);
            --il-muted: var(--text-muted, #9aa6bd); --il-ok: #68d391; --il-bad: #fc8181; --il-warn: #f6ad55;
        }
        body { background: var(--il-bg); color: var(--il-text); }
        .il-wrap { max-width: 1250px; margin: 86px auto 70px; padding: 0 16px; }
        .il-title { color: var(--il-accent); font-weight: 800; margin: 0 0 4px; }
        .il-sub { color: var(--il-muted); font-size: 13px; margin-bottom: 18px; line-height: 1.9; }
        .il-box { background: var(--il-surface); border: 1px solid var(--il-border); border-radius: 14px; padding: 16px 18px; margin-bottom: 18px; }
        .il-box h5 { font-weight: 700; font-size: 15px; margin: 0 0 12px; }
        .il-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 18px; }
        .il-card { background: var(--il-surface); border: 1px solid var(--il-border); border-radius: 12px; padding: 12px 14px; }
        .il-card .lbl { font-size: 12px; color: var(--il-muted); font-weight: 600; margin-bottom: 4px; }
        .il-card .val { font-size: 19px; font-weight: 800; }
        .il-ok { color: var(--il-ok); } .il-bad { color: var(--il-bad); } .il-warn { color: var(--il-warn); }
        .il-steps { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .il-table { width: 100%; font-size: 12.5px; border-collapse: collapse; }
        .il-table th, .il-table td { border-bottom: 1px solid var(--il-border); padding: 6px 8px; vertical-align: top; }
        .il-table th { color: var(--il-muted); font-weight: 700; white-space: nowrap; }
        .il-table td.num { direction: ltr; text-align: right; white-space: nowrap; }
        .il-scroll { max-height: 420px; overflow: auto; }
        .il-badge { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .k-official { background: rgba(47,133,90,.14); color: var(--il-ok); }
        .k-proforma { background: rgba(142,87,254,.14); color: var(--il-accent); }
        .k-cancelled, .k-reserve { background: rgba(107,114,128,.16); color: var(--il-muted); }
        .k-exclude { background: rgba(197,48,48,.12); color: var(--il-bad); }
        .il-danger { border-color: rgba(197,48,48,.35); }
        #preview, #server { display: none; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="il-wrap">
    <h3 class="il-title"><i class="bi bi-cloud-upload"></i> ورود فاکتورهای قدیمی از اکسل</h3>
    <div class="il-sub">
        فاکتورهایی که پیش از BPM در اکسل صادر شده‌اند (مثلا شماره‌های ۱ تا ۶۸۳ سال ۱۴۰۵) با همان شماره وارد می‌شوند.
        اقلام «شرح آزاد» ثبت می‌شوند: <b>روی موجودی انبار اثری ندارند</b> و کالای تازه‌ای در کاتالوگ ساخته نمی‌شود.
        مبلغ و مالیات عینا از اکسل می‌آید. فاکتوری که بین برگه‌ی خودش و برگه‌ی «گزارش» اختلاف دارد وارد نمی‌شود
        و در فهرست حسابداری می‌آید. هر مرحله تا «ورود نهایی» هیچ چیزی ذخیره نمی‌کند.
    </div>

    <div class="il-box">
        <h5>۱) انتخاب فایل</h5>
        <div class="il-steps">
            <input type="file" id="ilFile" accept=".xlsx,.xls" class="form-control" style="max-width:420px">
            <label class="d-flex align-items-center gap-2 m-0" style="font-size:13px">سال شماره‌گذاری
                <input type="text" id="ilYear" class="form-control form-control-sm" value="1405" inputmode="numeric" dir="ltr" style="width:80px;text-align:center">
            </label>
            <span id="ilStatus" style="font-size:13px;color:var(--il-muted)"></span>
        </div>
    </div>

    <div id="preview">
        <div class="il-cards" id="ilCards"></div>
        <div class="il-box">
            <h5>۲) بررسی روی سرور و ورود</h5>
            <div class="il-steps">
                <button class="btn btn-outline-primary" id="btnCheck"><i class="bi bi-search"></i> بررسی روی سرور (بدون ذخیره)</button>
                <button class="btn btn-success" id="btnImport" disabled><i class="bi bi-box-arrow-in-down"></i> ورود نهایی</button>
                <button class="btn btn-outline-secondary" id="btnExcl"><i class="bi bi-file-earmark-excel"></i> فهرست اختلافات برای حسابداری</button>
            </div>
            <div id="server" class="mt-3"></div>
        </div>
        <div class="il-box">
            <h5>واردنمی‌شوند — برای حسابداری <span id="exclCount" style="color:var(--il-muted);font-weight:500"></span></h5>
            <div class="il-scroll"><table class="il-table" id="exclTable"></table></div>
        </div>
        <div class="il-box">
            <h5>همه‌ی شماره‌ها <span id="allCount" style="color:var(--il-muted);font-weight:500"></span></h5>
            <div class="il-scroll"><table class="il-table" id="allTable"></table></div>
        </div>
    </div>

    <div class="il-box il-danger">
        <h5 class="il-bad"><i class="bi bi-exclamation-octagon"></i> برگرداندن ورود</h5>
        <div class="il-sub" style="margin-bottom:10px">
            همه‌ی فاکتورهایی که با این ابزار وارد شده‌اند (و مشتری‌هایی که خود ابزار ساخته و فاکتور دیگری ندارند) حذف می‌شوند.
            به فاکتورهای صادرشده در خود BPM دست نمی‌خورد.
        </div>
        <button class="btn btn-outline-danger" id="btnRollback"><i class="bi bi-arrow-counterclockwise"></i> حذف همه‌ی فاکتورهای واردشده</button>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
<script>
/* ═══════════════════════════════════════════════════════════════════
 * خواندن فایل — هم‌ارزِ دقیقِ پارسر مرجع (Python) که روی فایل ۱ تا ۲۵۹ امتحان شد.
 * شکل برگه‌ی فاکتور: سرستون اقلام در ردیف «ردیف»؛ ستون‌ها (از صفر):
 *   ۱ کد، ۲ شرح، ۵ تعداد، ۶ قیمت واحد، ۱۰ مبلغ کل، ۱۴ تخفیف، ۱۸ پس از تخفیف، ۲۱ مالیات، ۲۴ جمع
 * سربرگ: (ردیف ۱، ستون ۲۱) شماره، (ردیف ۲، ستون ۲۱) تاریخ، (ردیف ۱، ستون ۵) عنوان سند.
 * ═══════════════════════════════════════════════════════════════════ */
var IL = { parsed: null, payload: null, checked: false, fileName: '' };
var API = '/crm/api';

function ilTok() { return localStorage.getItem('auth_token'); }
function ilEsc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
function ilFa(v) { return (typeof faDigits === 'function') ? faDigits(String(v)) : String(v); }
function ilMoney(n) { return (n === null || n === undefined || n === '') ? '—' : ilFa(Math.round(Number(n)).toLocaleString('en-US')); }

function ilNorm(s) {
    // \x00-\x1F جز فاصله‌ها: نویسه‌های کنترلی نامرئی که با کپی در نام چند کالا افتاده
    // (مثل «کا\x1Fبل») و در فاکتور چاپی علامت عجیب نشان می‌دادند.
    return String(s == null ? '' : s).replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '')
        .replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/ـ/g, '').replace(/\s+/g, ' ').trim();
}
function ilNum(v) {
    if (typeof v === 'number') return isFinite(v) ? v : null;
    var s = ilNorm(v).replace(/,/g, '');
    return /^[-+]?(\d+\.?\d*|\.\d+)([eE][-+]?\d+)?$/.test(s) ? parseFloat(s) : null;
}
function ilCell(ws, row, col) {           // row از ۱ (مثل اکسل)، col از ۰
    var c = ws[XLSX.utils.encode_cell({ r: row - 1, c: col })];
    return c ? c.v : undefined;
}
// فقط سلول‌های دارای مقدار، گروه‌شده بر اساس ردیف: {row(از ۱): {col: value}}.
// عمدا روی کلیدهای خود برگه می‌چرخد نه روی محدوده‌ی !ref: برگه‌ی «گزارش» قالب‌بندی‌اش
// تا ردیف ۱٬۰۴۸٬۵۷۶ کشیده شده و پیمایش محدوده مرورگر را قفل می‌کرد.
function ilSheetRows(ws) {
    var out = {};
    Object.keys(ws).forEach(function (k) {
        if (k.charAt(0) === '!') return;
        var v = ws[k] ? ws[k].v : undefined;
        if (v === undefined || v === null) return;
        var a = XLSX.utils.decode_cell(k), r = a.r + 1;
        (out[r] || (out[r] = {}))[a.c] = v;
    });
    return out;
}
function ilSortedRows(rows) { return Object.keys(rows).map(Number).sort(function (a, b) { return a - b; }); }
function ilDigits(cells, c0, c1) {
    var s = '';
    for (var c = c0; c <= c1; c++) { var v = ilNorm(cells[c] === undefined ? '' : cells[c]); if (/^\d$/.test(v)) s += v; }
    return s;
}
// رقم کنترل شناسه/کد ملی: ۱۰ رقم = کد ملی اشخاص، ۱۱ رقم = شناسهٔ ملی شرکت.
// فقط برای تشخیص «جهت خانه‌های رقم» در برگه به کار می‌رود (نه رد کردن مقدار).
function ilNidValid(d) {
    if (/^\d{10}$/.test(d)) {
        if (/^(\d)\1{9}$/.test(d)) return false;
        var s = 0; for (var i = 0; i < 9; i++) s += (+d[i]) * (10 - i);
        var r = s % 11, c = +d[9];
        return r < 2 ? c === r : c === 11 - r;
    }
    if (/^\d{11}$/.test(d)) {
        var co = [29, 27, 23, 19, 17, 29, 27, 23, 19, 17], dec = (+d[9]) + 2, t = 0;
        for (var j = 0; j < 10; j++) t += ((+d[j]) + dec) * co[j];
        var rr = t % 11; if (rr === 10) rr = 0;
        return rr === +d[10];
    }
    return false;
}
function ilRev(s) { return String(s || '').split('').reverse().join(''); }

// جهت خانه‌های رقم (شناسهٔ ملی، شماره اقتصادی، کد پستی) در برگه‌ها — درخواست ۱۴۰۵/۰۸/۰۲:
// در بعضی فایل‌ها خانه‌ها برعکس چیده شده‌اند و عددها وارونه خوانده می‌شدند. برای هر برگه
// که شناسه‌اش فقط در یک جهت رقم کنترل درست دارد همان جهت؛ بقیه طبق جهت غالب کل فایل.
function ilFixDigitOrder(inv) {
    var fwd = 0, rev = 0, dir = {};
    Object.keys(inv).forEach(function (k) {
        var n = inv[k].buyer.national_id;
        if (!n) return;
        var a = ilNidValid(n), b = ilNidValid(ilRev(n));
        if (a && !b) { fwd++; dir[k] = 'f'; }
        else if (b && !a) { rev++; dir[k] = 'r'; }
    });
    var major = rev > fwd ? 'r' : 'f', flipped = 0;
    Object.keys(inv).forEach(function (k) {
        if ((dir[k] || major) !== 'r') return;
        var b = inv[k].buyer;
        b.national_id = ilRev(b.national_id); b.economic_code = ilRev(b.economic_code); b.postal_code = ilRev(b.postal_code);
        flipped++;
    });
    return flipped;
}

function ilAfterColon(s) { s = ilNorm(s); var i = s.indexOf(':'); return i >= 0 ? s.slice(i + 1).trim() : ''; }
function ilNDate(s) { var d = ilNorm(s).replace(/\D/g, ''); return d.length === 8 ? d.slice(0, 4) + '/' + d.slice(4, 6) + '/' + d.slice(6, 8) : ilNorm(s); }

function ilParseInvoiceSheet(ws, sheetName) {
    var rows = ilSheetRows(ws), order = ilSortedRows(rows);
    var findRow = function (label) {
        for (var i = 0; i < order.length; i++) for (var c in rows[order[i]]) if (ilNorm(rows[order[i]][c]) === label) return order[i];
        return null;
    };
    var hdr = findRow('ردیف'), buyerRow = findRow('مشخصات خریدار');
    if (!hdr || !buyerRow) return null;
    var b1 = rows[buyerRow + 1] || {}, b2 = rows[buyerRow + 2] || {}, b3 = rows[buyerRow + 3] || {};
    var buyer = {
        name: ilAfterColon(b1[0] || ''), economic_code: ilDigits(b1, 6, 17), national_id: ilDigits(b1, 21, 31),
        province: ilAfterColon(b2[0] || ''), shahrestan: ilAfterColon(b2[3] || ''), postal_code: ilDigits(b2, 8, 17),
        city: ilAfterColon(b2[18] || ''), address: ilAfterColon(b3[0] || ''), phone: ilAfterColon(b3[18] || '')
    };
    var items = [], total = null;
    var after = order.filter(function (r) { return r > hdr; });
    for (var i = 0; i < after.length; i++) {
        var row = rows[after[i]], isTotal = false;
        for (var c in row) if (typeof row[c] === 'string') { var t = ilNorm(row[c]); if (t.indexOf('جمع') >= 0 && t.indexOf('کل') >= 0) { isTotal = true; break; } }
        if (isTotal) {
            total = { gross: ilNum(row[10]), discount: ilNum(row[14]), after: ilNum(row[18]), tax: ilNum(row[21]), grand: ilNum(row[24]) };
            break;
        }
        var desc = ilNorm(row[2] === undefined ? '' : row[2]);
        if (!desc && !(ilNum(row[10]) || 0)) continue;
        items.push({
            code: ilNorm(row[1] === undefined ? '' : row[1]), title: desc, qty: ilNum(row[5]), unit_price: ilNum(row[6]),
            gross: ilNum(row[10]), discount: ilNum(row[14]) || 0, after: ilNum(row[18]), tax: ilNum(row[21]) || 0, grand: ilNum(row[24])
        });
    }
    var no = ilNum(ilCell(ws, 1, 21));
    no = no ? Math.trunc(no) : null;
    // نام برگه گاهی چند عدد دارد («شهرداری منطقه 5 502»، «… شعبه 0037»): همان عددی که با شماره‌ی
    // داخل برگه یکی است؛ وگرنه بزرگ‌ترین (و اختلاف شماره به فهرست حسابداری می‌رود).
    var runs = sheetName.match(/\d+/g).map(function (x) { return parseInt(x, 10); });
    var sheetNo = runs.indexOf(no) >= 0 ? no : Math.max.apply(null, runs);
    return {
        sheet: sheetName.trim(), sheet_no: sheetNo, no: no,
        date: ilNorm(ilCell(ws, 2, 21) || ''), title: ilNorm(ilCell(ws, 1, 5) || ''), buyer: buyer, items: items, total: total
    };
}

// ستون‌های «گزارش» با نام سرستون پیدا می‌شوند، نه جای ثابت: فایل سوم (برج ۷ به بعد)
// یک ستون اضافه در ابتدا دارد و همه یکی جابه‌جا شده‌اند.
var IL_REPORT_COLS = {
    no:      function (t) { return t === 'شماره'; },
    cust:    function (t) { return t === 'نام مشتری'; },
    date:    function (t) { return t.indexOf('تاریخ') === 0; },
    partner: function (t) { return t === 'همکار'; },            // «-» = خود شرکت
    total:   function (t) { return t === 'مبلغ کل'; },
    pct:     function (t) { return t === 'درصد'; },             // فقط برای مقایسه؛ درصد از سیستم خوانده می‌شود
    sanad:   function (t) { return t.indexOf('سند') === 0; },   // «سند / هزینه»: * = سند سود همکار ثبت شده
    settle:  function (t) { return t === 'تسویه'; },
    moadian: function (t) { return t.indexOf('ثبت م') === 0 && t.indexOf('دیان') >= 0; },
    code:    function (t) { return t.indexOf('کد سامانه') === 0; },
    note:    function (t) { return t === 'توضیحات'; }
};
function ilParseReport(ws) {
    var rows = ilSheetRows(ws), order = ilSortedRows(rows), rep = {};
    var hdr = null;
    for (var i = 0; i < order.length && hdr === null; i++)
        for (var c in rows[order[i]]) if (ilNorm(rows[order[i]][c]) === 'شماره') { hdr = order[i]; break; }
    if (hdr === null) throw new Error('سرستون «شماره» در برگه‌ی «گزارش» پیدا نشد');
    var cols = {}, missing = [];
    Object.keys(IL_REPORT_COLS).forEach(function (k) {
        var cs = Object.keys(rows[hdr]).map(Number).sort(function (a, b) { return a - b; });
        for (var j = 0; j < cs.length; j++) if (IL_REPORT_COLS[k](ilNorm(rows[hdr][cs[j]]))) { cols[k] = cs[j]; return; }
        missing.push(k);
    });
    if (cols.no === undefined || cols.total === undefined) throw new Error('ستون‌های «شماره» یا «مبلغ کل» در «گزارش» پیدا نشد');
    order.forEach(function (r) {
        if (r <= hdr) return;
        var row = rows[r], no = ilNum(row[cols.no]);
        if (!no) return;
        var g = function (k) { return cols[k] === undefined ? '' : ilNorm(row[cols[k]] === undefined ? '' : row[cols[k]]); };
        rep[Math.trunc(no)] = { cust: g('cust'), date: g('date'), partner: g('partner'), pct: cols.pct === undefined ? null : ilNum(row[cols.pct]),
                                sanad: g('sanad'), total: ilNum(row[cols.total]), settle: g('settle'), moadian: g('moadian'),
                                code: g('code'), note: g('note') };
    });
    return rep;
}

// کلمه‌هایی که در «تسویه» یا «توضیحات» یعنی فاکتور باطل شده (فایل‌ها هر کدام یک‌جور نوشته‌اند)
var IL_CANCEL_WORDS = ['کنسل', 'ابطال', 'باطل', 'حذف شد'];

// دسته‌بندی هر شماره — همان قواعدی که با کاربر توافق شد (۱۴۰۵/۰۷/۲۵)
function ilClassify(rep, inv) {
    var nos = {}, out = {};
    Object.keys(rep).forEach(function (k) { nos[k] = 1; });
    Object.keys(inv).forEach(function (k) { nos[k] = 1; });
    // شماره‌ای که بین کمترین و بیشترین شماره‌ی «گزارش» است ولی هیچ‌جا نیست (مثل ۶۶۰ تا ۶۶۹)
    var repNos = Object.keys(rep).map(Number);
    if (repNos.length) {
        for (var g = Math.min.apply(null, repNos); g <= Math.max.apply(null, repNos); g++) {
            if (!rep[g] && !inv[g]) out[g] = { kind: 'exclude', problems: ['نه در «گزارش» هست و نه برگه‌ی فاکتور دارد'], cust: '',
                                               rep_total: null, sheet_total: null, rep_date: '', sheet_date: '' };
        }
    }
    Object.keys(nos).map(Number).sort(function (a, b) { return a - b; }).forEach(function (no) {
        var r = rep[no], s = inv[no], problems = [];
        if (!r) {
            out[no] = { kind: 'exclude', problems: ['در برگه‌ی «گزارش» نیست'], cust: s ? s.buyer.name : '', rep_total: null,
                        sheet_total: s && s.total ? s.total.grand : null, rep_date: '', sheet_date: s ? s.date : '' };
            return;
        }
        if (!s) { out[no] = { kind: 'reserve', problems: [], cust: r.cust }; return; }
        var cancelled = IL_CANCEL_WORDS.some(function (w) { return r.settle.indexOf(w) >= 0 || r.note.indexOf(w) >= 0; });
        if (s.no === null) problems.push('داخل برگه شماره‌ای نوشته نشده (نام برگه ' + no + ' است)');
        else if (s.no !== no) problems.push('داخل برگه شماره‌ی ' + s.no + ' نوشته شده، ولی نام برگه ' + no + ' است');
        if (r.total === null && s.title.indexOf('پیش') < 0 && !cancelled) problems.push('در «گزارش» مبلغ ندارد ولی برگه «صورتحساب» (فاکتور رسمی) است');
        if (r.total !== null && s.total && Math.abs(r.total - s.total.grand) > 1) problems.push('مبلغ کل در «گزارش» با برگه‌ی فاکتور فرق دارد');
        if (r.date && ilNDate(r.date) !== s.date) problems.push('تاریخ در «گزارش» با برگه‌ی فاکتور فرق دارد');
        if (!s.buyer.name && s.items.length) problems.push('نام خریدار در برگه‌ی فاکتور خالی است');
        // BPM ریال را عدد صحیح نگه می‌دارد: قیمت واحد اعشاری (مثل ۴۱٬۵۸۶٬۰۷۳٫۵ در ۱۸۴) با گرد شدن
        // دیگر «تعداد × قیمت = مبلغ کل» را نگه نمی‌دارد → ناهماهنگی، برای حسابداری.
        s.items.forEach(function (it, i) {
            if (it.qty && it.unit_price !== null && it.gross !== null &&
                Math.abs(it.qty * Math.round(it.unit_price) - it.gross) > 1) {
                problems.push('قلم ' + (i + 1) + ': قیمت واحد اعشاری است (' + it.unit_price + ') و با گرد کردن، مبلغ کل جور درنمی‌آید');
            }
        });
        var hasCode = /^[A-Z0-9]{10,}$/.test(r.code);
        var proforma = s.title.indexOf('پیش') >= 0 && !hasCode;
        var kind = problems.length ? 'exclude' : (cancelled ? 'cancelled' : (proforma ? 'proforma' : 'official'));
        // باطلی که برگه‌اش خالی است (بی‌قلم، مثل ۱۱۳ با مشتری #N/A) = فقط شماره‌ی باطل → رزرو
        if (kind === 'cancelled' && !s.items.length) kind = 'reserve';
        out[no] = {
            kind: kind,
            problems: problems, cust: r.cust || s.buyer.name, rep_total: r.total, sheet_total: s.total ? s.total.grand : null,
            rep_date: r.date, sheet_date: s.date, has_code: hasCode, settled: r.settle === '*', moadian_star: r.moadian === '*'
        };
    });
    return out;
}

function ilBuildPayload(rep, inv, cls, year) {
    var invoices = [];
    Object.keys(cls).map(Number).sort(function (a, b) { return a - b; }).forEach(function (no) {
        var c = cls[no];
        if (c.kind === 'exclude') return;
        if (c.kind === 'reserve') {
            invoices.push({ no: no, kind: 'reserve', issue_date: '', customer: { name: '' }, items: [], total: 0,
                            note: c.cust ? ('در «گزارش» برای این شماره نام «' + c.cust + '» آمده بود.') : '' });
            return;
        }
        var s = inv[no], r = rep[no], R = function (v) { return Math.round(Number(v) || 0); };
        var partner = (r.partner && r.partner !== '-') ? r.partner : '';
        invoices.push({
            partner_name: partner,
            partner_profit_recorded: !!partner && r.sanad === '*',
            excel_percent: partner ? r.pct : null,
            no: no, kind: c.kind, issue_date: s.date,
            customer: { name: s.buyer.name, national_id: s.buyer.national_id, economic_code: s.buyer.economic_code,
                        postal_code: s.buyer.postal_code, province: s.buyer.province, city: s.buyer.city,
                        address: s.buyer.address, phone: s.buyer.phone },
            note: '', settled: !!c.settled, moadian_code: c.has_code ? r.code : '',
            items: s.items.map(function (it) {
                return { title: it.title, qty: Number(it.qty) || 0, unit_price: R(it.unit_price), discount: R(it.discount),
                         tax_amount: R(it.tax), line_total: R(it.grand) };
            }),
            total: s.total ? R(s.total.grand) : 0
        });
    });
    return { batch: IL.fileName, seq_year: year, invoices: invoices };
}

function ilParseWorkbook(wb) {
    var repName = wb.SheetNames.filter(function (n) { return ilNorm(n) === 'گزارش'; })[0];
    if (!repName) throw new Error('برگه‌ی «گزارش» در فایل پیدا نشد');
    var rep = ilParseReport(wb.Sheets[repName]), inv = {}, bad = [];
    wb.SheetNames.forEach(function (n) {
        var nn = ilNorm(n);
        if (nn === 'گزارش' || nn === 'دیتابیس' || !/\d/.test(n)) return;
        var p = ilParseInvoiceSheet(wb.Sheets[n], n);
        if (p) inv[p.sheet_no] = p; else bad.push(n.trim());
    });
    var flipped = ilFixDigitOrder(inv);
    return { rep: rep, inv: inv, bad: bad, flipped: flipped };
}

/* ── نمایش ── */
var KIND_LABEL = { official: 'فاکتور رسمی', proforma: 'پیش‌فاکتور', cancelled: 'باطل', reserve: 'رزرو (باطل)', exclude: 'وارد نمی‌شود' };
function ilBadge(k) { return '<span class="il-badge k-' + k + '">' + KIND_LABEL[k] + '</span>'; }
function ilCard(lbl, val, cls) { return '<div class="il-card"><div class="lbl">' + lbl + '</div><div class="val ' + (cls || '') + '">' + val + '</div></div>'; }

function ilRender() {
    var p = IL.parsed, cls = p.cls, cnt = { official: 0, proforma: 0, cancelled: 0, reserve: 0, exclude: 0 }, sum = 0, items = 0;
    Object.keys(cls).forEach(function (no) {
        var c = cls[no]; cnt[c.kind]++;
        if (c.kind === 'official') sum += Math.round(c.sheet_total || 0);
        if (c.kind !== 'exclude' && p.inv[no]) items += p.inv[no].items.length;
    });
    document.getElementById('ilCards').innerHTML =
        ilCard('فاکتور رسمی', ilFa(cnt.official), 'il-ok') + ilCard('پیش‌فاکتور', ilFa(cnt.proforma)) +
        ilCard('باطل', ilFa(cnt.cancelled)) + ilCard('رزرو (باطل)', ilFa(cnt.reserve)) +
        ilCard('وارد نمی‌شود', ilFa(cnt.exclude), cnt.exclude ? 'il-bad' : '') + ilCard('اقلام', ilFa(items)) +
        ilCard('جمع فاکتورهای رسمی (ریال)', ilMoney(sum)) +
        (p.flipped ? '<div class="alert alert-info py-2 mt-2 w-100" style="line-height:1.9">' +
            '<i class="bi bi-arrow-left-right ms-1"></i>در ' + ilFa(p.flipped) + ' برگه خانه‌های رقم شناسهٔ ملی، شماره اقتصادی و کد پستی ' +
            'برعکس چیده شده بودند و درست (از راست به چپ) خوانده شدند.</div>' : '');

    var ex = Object.keys(cls).map(Number).filter(function (n) { return cls[n].kind === 'exclude'; });
    document.getElementById('exclCount').textContent = '(' + ilFa(ex.length) + ' مورد)';
    document.getElementById('exclTable').innerHTML = '<tr><th>شماره</th><th>مشتری</th><th>مشکل</th><th>در «گزارش»</th><th>در برگه‌ی فاکتور</th></tr>' +
        ex.map(function (n) {
            var c = cls[n];
            return '<tr><td>' + ilFa(n) + '</td><td>' + ilEsc(c.cust) + '</td><td>' + ilEsc(c.problems.join('؛ ')) + '</td>' +
                '<td class="num">' + ilEsc(ilFa(c.rep_date || '')) + '<br>' + ilMoney(c.rep_total) + '</td>' +
                '<td class="num">' + ilEsc(ilFa(c.sheet_date || '')) + '<br>' + ilMoney(c.sheet_total) + '</td></tr>';
        }).join('');

    var all = Object.keys(cls).map(Number);
    document.getElementById('allCount').textContent = '(' + ilFa(all.length) + ' شماره)';
    document.getElementById('allTable').innerHTML = '<tr><th>شماره</th><th>نوع</th><th>تاریخ</th><th>مشتری</th><th>همکار</th><th>اقلام</th><th>جمع (ریال)</th><th>مؤدیان</th></tr>' +
        all.map(function (n) {
            var c = cls[n], s = p.inv[n], r = p.rep[n];
            var partner = (c.kind !== 'reserve' && r && r.partner && r.partner !== '-') ? r.partner : '—';
            return '<tr><td>' + ilFa(n) + '</td><td>' + ilBadge(c.kind) + '</td><td class="num">' + ilEsc(ilFa((s && s.date) || c.rep_date || '')) + '</td>' +
                '<td>' + ilEsc(c.kind === 'reserve' ? '—' : (s ? s.buyer.name : c.cust)) + '</td><td>' + ilEsc(partner) + '</td><td>' + (s ? ilFa(s.items.length) : '—') + '</td>' +
                '<td class="num">' + ilMoney(s && s.total ? s.total.grand : null) + '</td><td>' + (c.has_code ? 'ثبت‌شده' : '—') + '</td></tr>';
        }).join('');
    document.getElementById('preview').style.display = 'block';
}

function ilServerReport(d) {
    var box = document.getElementById('server');
    var errs = d.errors || [], conf = d.conflicts || [], nc = d.new_customers || [];
    var ok = !errs.length && !conf.length;
    var h = '<div class="il-cards">' +
        ilCard('فاکتور در درخواست', ilFa(d.invoice_count || 0)) + ilCard('مشتری پیداشده', ilFa(d.matched_customers || 0), 'il-ok') +
        ilCard('مشتری تازه', ilFa(nc.length), nc.length ? 'il-warn' : '') +
        ilCard('شماره‌ی تکراری در BPM', ilFa(conf.length), conf.length ? 'il-bad' : 'il-ok') +
        ilCard('خطا', ilFa(errs.length), errs.length ? 'il-bad' : 'il-ok') + '</div>';
    if (d.message) h += '<div class="alert ' + (d.success ? 'alert-success' : 'alert-danger') + ' py-2">' + ilEsc(d.message) + '</div>';
    if (conf.length) {
        h += '<div class="alert alert-danger py-2">این شماره‌ها در BPM از قبل وجود دارند: ' + ilEsc(ilFa(conf.join('، '))) + '</div>';
        if (d.dry_run) h += '<button class="btn btn-sm btn-outline-danger mb-2" id="btnSkipConf"><i class="bi bi-skip-forward"></i> کنار گذاشتن این ' +
            ilFa(conf.length) + ' شماره (به فهرست «وارد نمی‌شود») و بررسی دوباره</button>';
    }
    if (errs.length) h += '<div class="il-scroll mb-2"><table class="il-table"><tr><th>شماره</th><th>خطا</th></tr>' +
        errs.map(function (e) { return '<tr><td>' + ilFa(e.no) + '</td><td>' + ilEsc(e.message) + '</td></tr>'; }).join('') + '</table></div>';
    if (nc.length) h += '<h6 class="mt-2" style="font-weight:700">مشتری‌هایی که در BPM پیدا نشدند و ساخته می‌شوند</h6><div class="il-scroll"><table class="il-table">' +
        '<tr><th>نام</th><th>شناسه‌ی ملی</th><th>شماره‌ی اقتصادی</th><th>فاکتورها</th></tr>' +
        nc.map(function (c) { return '<tr><td>' + ilEsc(c.name) + '</td><td class="num">' + ilEsc(ilFa(c.national_id || '—')) + '</td><td class="num">' + ilEsc(ilFa(c.economic_code || '—')) + '</td><td>' + ilEsc(ilFa((c.invoices || []).join('، '))) + '</td></tr>'; }).join('') + '</table></div>';
    var pm = d.partners || [], pw = d.partner_warnings || [];
    if (pm.length) h += '<h6 class="mt-3" style="font-weight:700">همکارها: نام در اکسل ← همکار در BPM (درصد از سیستم، به‌ازای ماه)</h6><div class="il-scroll"><table class="il-table">' +
        '<tr><th>نام در اکسل</th><th>همکار در BPM</th><th>فاکتورها</th><th>درصد سیستم / اکسل در هر ماه</th></tr>' +
        pm.map(function (m) {
            var months = (m.months || []).map(function (x) {
                return 'ماه ' + ilFa(x.jm) + ': ' + (x.percent ? ilFa(x.percent) + '٪' : '<span class="il-bad">تعریف نشده</span>') +
                    (x.excel_percent != null ? ' <span style="color:var(--il-muted)">(اکسل ' + ilFa(x.excel_percent) + '٪)</span>' : '');
            }).join('<br>');
            return '<tr><td>' + ilEsc(m.excel_name) + '</td><td class="' + (m.partner_id ? 'il-ok' : 'il-bad') + '">' + ilEsc(m.partner_name || 'پیدا نشد') + '</td>' +
                '<td>' + ilFa((m.invoices || []).length) + '</td><td>' + (months || '—') + '</td></tr>';
        }).join('') + '</table></div>';
    if (pw.length) h += '<div class="alert alert-warning py-2 mt-2" style="line-height:1.9">' + pw.map(ilEsc).join('<br>') + '</div>';
    var cw = d.customer_warnings || [];
    if (cw.length) h += '<div class="alert alert-warning py-2 mt-2" style="line-height:1.9">' + cw.map(ilEsc).join('<br>') + '</div>';
    if (d.dry_run && ok) h += '<div class="alert alert-success py-2 mt-2">بررسی بی‌خطا بود. با «ورود نهایی» همه در یک مرحله ثبت می‌شوند.</div>';
    box.innerHTML = h;
    box.style.display = 'block';
    var skip = document.getElementById('btnSkipConf');
    if (skip) skip.addEventListener('click', function () { ilSkipConflicts(conf); });
    IL.checked = !!(d.dry_run && ok);
    document.getElementById('btnImport').disabled = !IL.checked;
}

// شماره‌هایی که در BPM از قبل هست (مثلا ۶۸۴ که پرسنل در خود BPM صادر کرده‌اند): از ورود
// کنار گذاشته و به فهرست «وارد نمی‌شود» برده می‌شوند، بعد بررسی سرور دوباره اجرا می‌شود.
function ilSkipConflicts(conf) {
    if (!IL.parsed) return;
    conf.forEach(function (no) {
        var c = IL.parsed.cls[no] || {};
        IL.parsed.cls[no] = { kind: 'exclude', problems: ['این شماره در BPM از قبل ثبت شده است'], cust: c.cust || '',
                              rep_total: c.rep_total == null ? null : c.rep_total, sheet_total: c.sheet_total == null ? null : c.sheet_total,
                              rep_date: c.rep_date || '', sheet_date: c.sheet_date || '' };
    });
    ilRender();
    document.getElementById('btnCheck').click();
}

async function ilPost(path, body) {
    var r = await fetch(API + path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + ilTok() }, body: JSON.stringify(body) });
    var d = await r.json().catch(function () { return { success: false, message: 'پاسخ نامعتبر از سرور (' + r.status + ')' }; });
    if (!r.ok && !d.errors && !d.conflicts) throw new Error(d.message || ('خطای ' + r.status));
    return d;
}

function ilExportExclusions() {
    if (!IL.parsed) return;
    var cls = IL.parsed.cls;
    var aoa = [['شماره فاکتور', 'نام مشتری', 'مشکل', 'تاریخ در «گزارش»', 'مبلغ در «گزارش»', 'تاریخ در برگه‌ی فاکتور', 'مبلغ در برگه‌ی فاکتور']];
    Object.keys(cls).map(Number).filter(function (n) { return cls[n].kind === 'exclude'; }).forEach(function (n) {
        var c = cls[n], R = function (v) { return v === null || v === undefined ? '' : Math.round(v); };
        aoa.push([n, c.cust || '', c.problems.join('؛ '), c.rep_date || '', R(c.rep_total), c.sheet_date || '', R(c.sheet_total)]);
    });
    var ws = XLSX.utils.aoa_to_sheet(aoa);
    ws['!cols'] = [12, 30, 50, 14, 18, 14, 18].map(function (w) { return { wch: w }; });
    for (var r = 1; r < aoa.length; r++) [4, 6].forEach(function (c) {
        var cell = ws[XLSX.utils.encode_cell({ r: r, c: c })]; if (cell && cell.t === 'n') cell.z = '#,##0';
    });
    var wb = XLSX.utils.book_new();
    wb.Workbook = { Views: [{ RTL: true }] };
    XLSX.utils.book_append_sheet(wb, ws, 'اختلافات');
    XLSX.writeFile(wb, 'اختلافات - ' + IL.fileName.replace(/\.xlsx?$/i, '') + '.xlsx');
}

document.getElementById('ilFile').addEventListener('change', async function () {
    var f = this.files && this.files[0], st = document.getElementById('ilStatus');
    IL.parsed = null; IL.checked = false;
    document.getElementById('preview').style.display = 'none';
    document.getElementById('server').style.display = 'none';
    document.getElementById('btnImport').disabled = true;
    if (!f) return;
    IL.fileName = f.name;
    st.textContent = 'در حال خواندن فایل… (فایل‌های بزرگ چند ثانیه طول می‌کشند)';
    try {
        var wb = XLSX.read(await f.arrayBuffer(), { type: 'array' });
        var p = ilParseWorkbook(wb);
        p.cls = ilClassify(p.rep, p.inv);
        IL.parsed = p;
        ilRender();
        st.textContent = 'خوانده شد: ' + ilFa(Object.keys(p.inv).length) + ' برگه‌ی فاکتور' + (p.bad.length ? ' — برگه‌های ناخوانا: ' + p.bad.join('، ') : '');
    } catch (e) {
        st.textContent = 'خطا: ' + (e.message || e);
    }
});

function ilYear() {
    var y = parseInt(String(document.getElementById('ilYear').value).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }), 10);
    return (y >= 1390 && y <= 1500) ? y : null;
}

document.getElementById('btnCheck').addEventListener('click', async function () {
    var y = ilYear();
    if (!IL.parsed || !y) { showToast('فایل یا سال نامعتبر است', 'error'); return; }
    IL.payload = ilBuildPayload(IL.parsed.rep, IL.parsed.inv, IL.parsed.cls, y);
    this.disabled = true;
    try {
        ilServerReport(await ilPost('/inv/import-legacy', Object.assign({ dry_run: true }, IL.payload)));
    } catch (e) {
        showToast(e.message || 'خطا', 'error');
    } finally { this.disabled = false; }
});

document.getElementById('btnImport').addEventListener('click', function () {
    if (!IL.checked || !IL.payload) return;
    var n = IL.payload.invoices.length;
    uiConfirm(ilFa(n) + ' شماره‌ی فاکتور در BPM ثبت شود؟ (اگر لازم شد، با «حذف همه‌ی فاکتورهای واردشده» قابل برگشت است)', async function () {
        var btn = document.getElementById('btnImport');
        btn.disabled = true;
        try {
            var d = await ilPost('/inv/import-legacy', Object.assign({ dry_run: false }, IL.payload));
            ilServerReport(d);
            showToast(d.message || (d.success ? 'ثبت شد' : 'ثبت نشد'), d.success ? 'success' : 'error');
            IL.checked = false; btn.disabled = true;
        } catch (e) {
            showToast(e.message || 'خطا', 'error');
            btn.disabled = false;
        }
    }, { yesText: 'بله، ثبت کن', noText: 'انصراف' });
});

document.getElementById('btnExcl').addEventListener('click', ilExportExclusions);

document.getElementById('btnRollback').addEventListener('click', async function () {
    // اول شمارش: چه چیزهایی که بعد از ورود روی این فاکتورها ثبت شده با حذف از بین می‌رود
    var st;
    try { st = await ilPost('/inv/import-legacy/rollback', { preview: true }); }
    catch (e) { showToast(e.message || 'خطا', 'error'); return; }
    if (!st.invoices) { showToast('فاکتور واردشده‌ای برای حذف نیست.', 'info'); return; }
    var lost = [];
    if (st.with_payments) lost.push(ilFa(st.with_payments) + ' فاکتور واریز ثبت‌شده دارد (' + ilFa(st.payments) + ' واریز)');
    if (st.with_moadian_code) lost.push(ilFa(st.with_moadian_code) + ' فاکتور کد مؤدیان دارد');
    if (st.with_profit_recorded) lost.push(ilFa(st.with_profit_recorded) + ' فاکتور تیک «سند سود همکار» دارد');
    if (st.with_partner_percent) lost.push(ilFa(st.with_partner_percent) + ' فاکتور درصد اختصاصی همکار دارد');
    if (st.with_settlement) lost.push(ilFa(st.with_settlement) + ' فاکتور وضعیت تسویه دارد');
    var msg = ilFa(st.invoices) + ' فاکتوری که از اکسل وارد شده‌اند حذف شوند؟ این کار قابل برگشت نیست (ولی می‌توانید فایل را دوباره وارد کنید).' +
        (lost.length ? '<br><br><b>این‌ها هم با حذف از بین می‌روند</b> (و فقط اگر در فایل جدید باشند برمی‌گردند):<br>• ' + lost.join('<br>• ') : '');
    uiConfirm(msg, async function () {
        try {
            var d = await ilPost('/inv/import-legacy/rollback', {});
            showToast(d.message || 'انجام شد', d.success ? 'success' : 'error');
        } catch (e) { showToast(e.message || 'خطا', 'error'); }
    }, { danger: true, yesText: 'بله، حذف کن', noText: 'انصراف' });
});
</script>
</body>
</html>
