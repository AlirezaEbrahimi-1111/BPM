<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../config/config.php';
require_once '../includes/version.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

if (!isset($db)) {
    $database = new Database();
    $db = $database->getConnection();
}
$auth = new Auth($db);
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    header('Location: ../index.php');
    exit;
}
$__me = loadUserForPermissions($db, (int) $user_id);
if (!$__me) {
    header('Location: ../index.php');
    exit;
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/crm_access.php';
if (!crmModuleAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}
$invId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چاپ فاکتور</title>
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <style>
        body {
            background: #f3f4f6;
        }

        .sheet {
            max-width: 820px;
            margin: 20px auto;
            background: #fff;
            color: #111;
            padding: 28px 32px;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .1);
        }

        .sheet h2 {
            text-align: center;
            margin: 0 0 4px;
            font-size: 1.35rem;
        }

        .sheet .sub {
            text-align: center;
            color: #555;
            font-size: .85rem;
            margin-bottom: 18px;
        }

        .party {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 14px;
            font-size: .84rem;
        }

        .party .card {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 12px;
        }

        .party .card h4 {
            font-size: .8rem;
            margin: 0 0 6px;
            color: #6b21a8;
        }

        .party .row2 {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 2px 0;
        }

        table.pi {
            width: 100%;
            border-collapse: collapse;
            font-size: .82rem;
            margin-top: 6px;
        }

        table.pi th,
        table.pi td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
        }

        table.pi th {
            background: #f1e9ff;
        }

        table.pi td.num,
        table.pi th.num {
            text-align: left;
            white-space: nowrap;
        }

        .tots {
            width: 300px;
            margin-inline-start: auto;
            margin-top: 10px;
            font-size: .86rem;
        }

        .tots .r {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            border-bottom: 1px dashed #cbd5e1;
        }

        .tots .r.g {
            font-weight: 700;
            font-size: 1rem;
            border-bottom: 0;
        }

        .foot-note {
            margin-top: 18px;
            font-size: .8rem;
            color: #444;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            white-space: pre-wrap;
        }

        .status-tag {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 700;
        }

        .status-tag.draft {
            background: #e5e7eb;
            color: #374151;
        }

        .status-tag.approved {
            background: #dcfce7;
            color: #15803d;
        }

        .status-tag.cancelled {
            background: #fee2e2;
            color: #b91c1c;
        }

        .toolbar {
            max-width: 820px;
            margin: 14px auto 0;
            display: flex;
            gap: 10px;
        }

        @media print {

            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                box-shadow: none;
                margin: 0;
                max-width: none;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer ms-1"></i> چاپ</button>
        <a class="btn btn-outline-secondary btn-sm" href="/pages/inv-invoices.php">بازگشت به فهرست</a>
    </div>

    <div class="sheet" id="sheet">
        <p class="text-center text-muted">در حال بارگذاری…</p>
    </div>

    <script>
        const API = '/crm/api';
        const INV_ID = <?= $invId ?>;

        function tok() {
            return localStorage.getItem('auth_token');
        }

        function faDigits(s) {
            return String(s == null ? '' : s).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹' [+d]);
        }

        function money(n) {
            return faDigits(Math.round(n || 0).toLocaleString('en-US'));
        }

        function jDate(g) {
            if (!g) return '—';
            try {
                return new Date(g).toLocaleDateString('fa-IR', {
                    timeZone: 'Asia/Tehran'
                });
            } catch (e) {
                return faDigits(g);
            }
        }

        // عددِ ریالی → حروفِ فارسی (تا مرتبه‌ی بیلیون)
        function numToFaWords(n) {
            n = Math.round(Math.abs(Number(n) || 0));
            if (n === 0) return 'صفر';
            const yek = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
            const dah = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
            const dahdah = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
            const sad = ['', 'صد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
            const scale = ['', ' هزار', ' میلیون', ' میلیارد', ' بیلیون'];

            function three(num) {
                const parts = [];
                const s = Math.floor(num / 100),
                    r = num % 100,
                    d = Math.floor(r / 10),
                    u = r % 10;
                if (s) parts.push(sad[s]);
                if (r >= 10 && r <= 19) parts.push(dahdah[r - 10]);
                else {
                    if (d) parts.push(dah[d]);
                    if (u) parts.push(yek[u]);
                }
                return parts.join(' و ');
            }
            const groups = [];
            let x = n;
            while (x > 0) {
                groups.push(x % 1000);
                x = Math.floor(x / 1000);
            }
            const out = [];
            for (let i = groups.length - 1; i >= 0; i--) {
                if (groups[i] === 0) continue;
                out.push(three(groups[i]) + scale[i]);
            }
            return out.join(' و ');
        }

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;'
            } [c]));
        }

        const ST = {
            draft: 'پیش‌نویس',
            approved: 'تأییدشده',
            cancelled: 'باطل‌شده'
        };
        const DT = {
            official: 'فاکتور فروش',
            proforma: 'پیش‌فاکتور'
        };

        async function load() {
            if (!tok()) {
                location.href = '../index.php';
                return;
            }
            const r = await fetch(API + '/inv/invoices/' + INV_ID, {
                headers: {
                    'Authorization': 'Bearer ' + tok()
                }
            });
            const d = await r.json().catch(() => ({}));
            if (!r.ok) {
                document.getElementById('sheet').innerHTML = '<p class="text-danger text-center">' + (d.message || 'خطا') + '</p>';
                return;
            }
            render(d.invoice, d.seller || {}, d.footer_note || '');
        }

        function kv(label, val) {
            if (!val) return '';
            return `<div class="row2"><span>${label}</span><span>${esc(val)}</span></div>`;
        }

        function render(inv, seller, footer) {
            const rows = (inv.items || []).map((it, i) => `
                <tr>
                    <td>${faDigits(i+1)}</td>
                    <td>${esc(it.title)}</td>
                    <td class="num">${faDigits(it.qty)}</td>
                    <td class="num">${money(it.unit_price)}</td>
                    <td class="num">${money(it.discount)}</td>
                    <td class="num">${it.is_tax_exempt ? '—' : money(it.tax_amount)}</td>
                    <td class="num">${money(it.line_total)}</td>
                </tr>`).join('');

            document.getElementById('sheet').innerHTML = `
                <h2>${DT[inv.doc_type]||'فاکتور'}</h2>
                <div class="sub">
                    شماره: <b>${inv.number ? faDigits(inv.number) : '—'}</b>
                    &nbsp;|&nbsp; تاریخ: ${jDate(inv.issue_date)}
                    &nbsp;|&nbsp; <span class="status-tag ${inv.status}">${ST[inv.status]||inv.status}</span>
                </div>
                <div class="party">
                    <div class="card">
                        <h4>فروشنده</h4>
                        ${kv('نام', seller.company_name)}
                        ${kv('شناسه ملی', seller.national_id)}
                        ${kv('کد اقتصادی', seller.economic_code)}
                        ${kv('شماره ثبت', seller.reg_number)}
                        ${kv('تلفن', seller.phone)}
                        ${kv('کدپستی', seller.postal_code)}
                        ${kv('نشانی', seller.address)}
                    </div>
                    <div class="card">
                        <h4>خریدار</h4>
                        ${kv('نام', inv.customer_name)}
                    </div>
                </div>
                <table class="pi">
                    <thead><tr>
                        <th style="width:34px">#</th><th>شرح کالا / خدمت</th>
                        <th class="num">تعداد</th><th class="num">قیمت واحد</th>
                        <th class="num">تخفیف</th><th class="num">مالیات</th><th class="num">جمع</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                </table>
                <div class="tots">
                    <div class="r"><span>جمعِ کل</span><span>${money(inv.subtotal_amount)}</span></div>
                    <div class="r"><span>تخفیف</span><span>${money(inv.discount_amount)}</span></div>
                    <div class="r"><span>مالیات بر ارزش افزوده</span><span>${money(inv.tax_amount)}</span></div>
                    <div class="r g"><span>مبلغِ قابل پرداخت (ریال)</span><span>${money(inv.total_amount)}</span></div>
                </div>
                <div class="foot-note"><b>مبلغِ فاکتور به حروف:</b> ${numToFaWords(inv.total_amount)} ریال</div>
                ${inv.note ? `<div class="foot-note"><b>توضیحات:</b> ${esc(inv.note)}</div>` : ''}
                ${footer ? `<div class="foot-note">${esc(footer)}</div>` : ''}`;
        }

        load();
    </script>
</body>

</html>
