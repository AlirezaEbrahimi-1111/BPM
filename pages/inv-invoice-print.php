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
        /* کاغذِ چاپ: A4 افقی */
        @page { size: A4 landscape; margin: 8mm; }

        body {
            background: #eef0f3;
        }

        .toolbar {
            width: 1123px;
            max-width: 100%;
            margin: 14px auto 0;
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }

        /* پیش‌نمایشِ برگه با نسبتِ ۳:۲٫۱ (A4 افقی) */
        .sheet {
            width: 1123px;
            max-width: 100%;
            aspect-ratio: 3 / 2.1;
            margin: 14px auto 40px;
            background: #fff;
            color: #000;
            padding: 14px 16px 18px;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .12);
            font-size: 12px;
            line-height: 1.7;
            overflow: auto;
        }

        .sheet table {
            border-collapse: collapse;
            width: 100%;
        }

        .sheet td,
        .sheet th {
            border: 1px solid #000;
            padding: 3px 5px;
        }

        /* ── سربرگ ── */
        .inv-top {
            border: 1px solid #000;
        }

        .inv-top td {
            border: 1px solid #000;
        }

        .inv-title {
            text-align: center;
            font-size: 17px;
            font-weight: 800;
        }

        .inv-nobox td {
            padding: 2px 6px;
        }

        /* چسباندن جدول‌های پیاپی (ادغام خط مرزی) */
        .blk {
            margin-top: -1px;
        }

        .band {
            text-align: center;
            font-weight: 700;
            background: #efefef;
        }

        .lbl {
            background: #f6f6f6;
            font-weight: 600;
            white-space: nowrap;
        }

        /* خانه‌های تک‌رقمی برای شناسه/کد */
        .dboxes {
            display: inline-flex;
            gap: 2px;
            direction: ltr;
        }

        .dboxes span {
            display: inline-block;
            min-width: 13px;
            text-align: center;
            border: 1px solid #999;
            font-size: 11px;
            line-height: 15px;
        }

        table.items th {
            background: #efefef;
            text-align: center;
            font-size: 10.5px;
            vertical-align: middle;
        }

        table.items td {
            text-align: center;
        }

        table.items td.desc {
            text-align: center;
        }

        table.items td.num {
            text-align: center;
            white-space: nowrap;
            letter-spacing: 0;
            direction: ltr;
            unicode-bidi: isolate;
        }

        .tots .r span:last-child {
            text-align: left;
            white-space: nowrap;
            letter-spacing: 0;
            direction: ltr;
            unicode-bidi: isolate;
        }

        table.items tr.sum td {
            font-weight: 700;
            background: #f6f6f6;
        }

        /* سربرگ: ستون خالی | عنوان | (برچسب | مقدار) در دو ردیف — همه در همین جدول
           تا حاشیه‌ها هم‌تراز بمانند (جدولِ تودرتو خط‌های اضافه می‌ساخت). */
        .inv-top { table-layout: fixed; }
        .inv-top .hc-empty { width: 210px; }
        .inv-top .mlbl { width: 86px; }
        .inv-top .mval { width: 124px; }

        /* شبکهٔ ۶ ستونیِ یکسان برای «مشخصات فروشنده» و «مشخصات خریدار» */
        .grid6 { table-layout: fixed; }
        .grid6 col.c-lbl  { width: 124px; }
        .grid6 col.c-nlbl { width: 150px; }  /* برچسبِ «نام شخص حقیقی و حقوقی» ~۲۰٪ بزرگ‌تر */
        .grid6 col.c-nval { width: 204px; }  /* فضای مقابلِ نام ~۲۵٪ کوچک‌تر */
        .grid6 col.c-code { width: 133px; }  /* آزادشده به کد اقتصادی/شناسه ملی/کد پستی/شهر/تلفن اضافه شد */

        /* عرض ستون‌های جدول اقلام مطابق فرم رسمی */
        table.items { table-layout: fixed; }
        table.items td.desc { word-break: break-word; }
        /* عرض‌ها یک‌بار از پایهٔ اصلی کوچک شده‌اند (نه دو بار انباشته) */
        table.items col.w-row  { width: 16px; }   /* ردیف ~۴۰٪ کوچک‌تر */
        table.items col.w-code { width: 26px; }   /* کد کالا ~۴۰٪ کوچک‌تر */
        table.items col.w-qty  { width: 36px; }   /* تعداد/مقدار ~۴۰٪ کوچک‌تر */
        table.items col.w-unit { width: 78px; }   /* مبلغ واحد */
        table.items col.w-tot  { width: 48px; }   /* مبلغ کل ~۴۰٪ کوچک‌تر */
        table.items col.w-num  { width: 53px; }   /* مبلغ تخفیف ~۳۰٪ کوچک‌تر */
        table.items col.w-num2 { width: 90px; }
        table.items col.w-num3 { width: 124px; }  /* جمع کل بعلاوه مالیات و عوارض */
        table.items tr.blank td { height: 21px; }

        .pay-terms .pt-h { font-weight: 700; margin-inline-end: 16px; }
        .pay-note { font-size: 11px; }

        /* بخش پایین: دو ستونِ «شرایط و نحوه فروش» و «نام شرکت» هم‌اندازه */
        .pay-blk { table-layout: fixed; }
        .pay-blk td { width: 50%; }

        .pay-terms label {
            margin-inline-end: 14px;
            font-weight: 600;
        }

        .chk {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid #000;
            text-align: center;
            line-height: 12px;
            margin-inline-start: 4px;
        }

        .seller-pay {
            font-size: 11px;
            line-height: 1.9;
            text-align: center;
        }

        .sign td {
            height: 54px;
            vertical-align: top;
            font-weight: 600;
            text-align: center;
        }

        .muted {
            color: #666;
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
                width: auto;
                max-width: none;
                aspect-ratio: auto;
                overflow: visible;
                padding: 0;
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
        <p class="text-center muted">در حال بارگذاری…</p>
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
            return faDigits(Math.round(Number(n) || 0).toLocaleString('en-US'));
        }

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;'
            } [c]));
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

        // خانه‌های تک‌رقمی
        function boxed(s) {
            s = String(s == null ? '' : s).trim();
            if (!s) return '';
            return '<span class="dboxes">' +
                s.split('').map(c => `<span>${faDigits(esc(c))}</span>`).join('') +
                '</span>';
        }

        const DT = {
            official: 'صورتحساب فروش کالا و خدمات',
            proforma: 'پیش‌فاکتور فروش کالا و خدمات'
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
                document.getElementById('sheet').innerHTML =
                    '<p class="text-danger text-center">' + (d.message || 'خطا') + '</p>';
                return;
            }
            render(d.invoice, d.customer || {}, d.seller || {}, d.footer_note || '');
        }

        function render(inv, buyer, seller, footer) {
            const cash = inv.payment_type === 'cash';
            const credit = inv.payment_type === 'credit';

            const items = inv.items || [];
            const rows = [];
            items.forEach((it, i) => {
                const gross = Math.round((Number(it.qty) || 0) * (Number(it.unit_price) || 0));
                const afterDisc = gross - (Number(it.discount) || 0);
                rows.push(`<tr>
                    <td>${faDigits(i + 1)}</td>
                    <td>${it.code ? faDigits(esc(it.code)) : '—'}</td>
                    <td class="desc">${esc(it.title)}</td>
                    <td>${faDigits(it.qty)}</td>
                    <td class="num">${money(it.unit_price)}</td>
                    <td class="num">${money(gross)}</td>
                    <td class="num">${money(it.discount)}</td>
                    <td class="num">${money(afterDisc)}</td>
                    <td class="num">${it.is_tax_exempt ? '—' : money(it.tax_amount)}</td>
                    <td class="num">${money(it.line_total)}</td>
                </tr>`);
            });
            // پر کردن تا حداقل ۵ ردیف مثل فرم رسمی
            for (let k = items.length; k < 5; k++) {
                rows.push(`<tr class="blank">
                    <td>${faDigits(k + 1)}</td><td></td><td></td><td></td>
                    <td class="num">—</td><td class="num">—</td><td class="num">—</td>
                    <td class="num">—</td><td class="num">—</td><td class="num">—</td>
                </tr>`);
            }

            const afterDiscTotal = (Number(inv.subtotal_amount) || 0) - (Number(inv.discount_amount) || 0);

            const isProforma = inv.doc_type === 'proforma';
            const numLabel = isProforma ? 'شماره پیش‌فاکتور' : 'شماره فاکتور';
            const invDate = inv.issue_date || (inv.created_at || '').slice(0, 10);

            document.getElementById('sheet').innerHTML = `
            <table class="inv-top">
                <tr>
                    <td class="hc-empty" rowspan="2"></td>
                    <td class="inv-title" rowspan="2">${DT[inv.doc_type] || 'صورتحساب فروش کالا و خدمات'}</td>
                    <td class="lbl mlbl">${numLabel}</td>
                    <td class="mval">${inv.number ? faDigits(inv.number) : ''}</td>
                </tr>
                <tr>
                    <td class="lbl mlbl">تاریخ</td>
                    <td class="mval">${invDate ? jDate(invDate) : ''}</td>
                </tr>
            </table>

            <table class="blk grid6">
                <colgroup><col class="c-nlbl"><col class="c-nval"><col class="c-lbl"><col class="c-code"><col class="c-lbl"><col class="c-code"></colgroup>
                <tr><td colspan="6" class="band">مشخصات فروشنده</td></tr>
                <tr>
                    <td class="lbl">نام شخص حقیقی و حقوقی</td><td>${esc(seller.company_name)}</td>
                    <td class="lbl">کد اقتصادی</td><td>${faDigits(esc(seller.economic_code))}</td>
                    <td class="lbl">شناسه ملی</td><td>${faDigits(esc(seller.national_id))}</td>
                </tr>
                <tr>
                    <td class="lbl">استان/شهرستان</td>
                    <td>${esc(seller.province)}${seller.shahrestan ? ' / ' + esc(seller.shahrestan) : ''}</td>
                    <td class="lbl">کد پستی ۱۰ رقمی</td><td>${faDigits(esc(seller.postal_code))}</td>
                    <td class="lbl">شهر</td><td>${esc(seller.city)}</td>
                </tr>
                <tr>
                    <td class="lbl">نشانی کامل</td>
                    <td colspan="3">${esc(seller.address)}</td>
                    <td class="lbl">شماره تلفن / نمابر</td><td>${faDigits(esc(seller.phone))}</td>
                </tr>
            </table>

            <table class="blk grid6">
                <colgroup><col class="c-nlbl"><col class="c-nval"><col class="c-lbl"><col class="c-code"><col class="c-lbl"><col class="c-code"></colgroup>
                <tr><td colspan="6" class="band">مشخصات خریدار</td></tr>
                <tr>
                    <td class="lbl">نام شخص حقیقی و حقوقی</td><td>${esc(buyer.name)}</td>
                    <td class="lbl">شماره اقتصادی</td><td>${faDigits(esc(buyer.economic_code))}</td>
                    <td class="lbl">شناسه ملی</td><td>${faDigits(esc(buyer.national_id))}</td>
                </tr>
                <tr>
                    <td class="lbl">استان/شهرستان</td>
                    <td>${esc(buyer.province)}${buyer.shahrestan ? ' / ' + esc(buyer.shahrestan) : ''}</td>
                    <td class="lbl">کد پستی ۱۰ رقمی</td><td>${faDigits(esc(buyer.postal_code))}</td>
                    <td class="lbl">شهر</td><td>${esc(buyer.city)}</td>
                </tr>
                <tr>
                    <td class="lbl">آدرس</td>
                    <td colspan="3">${esc(buyer.address)}</td>
                    <td class="lbl">شماره تلفن</td><td>${faDigits(esc(buyer.phone || buyer.mobile))}</td>
                </tr>
            </table>

            <table class="items">
                <colgroup>
                    <col class="w-row"><col class="w-code"><col class="w-desc"><col class="w-qty">
                    <col class="w-unit"><col class="w-tot"><col class="w-num"><col class="w-num2">
                    <col class="w-num2"><col class="w-num3">
                </colgroup>
                <tr><td colspan="10" class="band">مشخصات کالا یا خدمات مورد معامله</td></tr>
                <tr>
                    <th class="w-row">ردیف</th>
                    <th class="w-code">کد کالا</th>
                    <th>شرح کالا یا خدمات</th>
                    <th class="w-qty">تعداد / مقدار</th>
                    <th class="w-unit">مبلغ واحد (ریال)</th>
                    <th class="w-tot">مبلغ کل (ریال)</th>
                    <th class="w-num">مبلغ تخفیف</th>
                    <th class="w-num2">مبلغ کل پس از تخفیف (ریال)</th>
                    <th class="w-num2">جمع مالیات و عوارض (ریال)</th>
                    <th class="w-num3">جمع مبلغ کل بعلاوه جمع مالیات و عوارض (ریال)</th>
                </tr>
                ${rows.join('')}
                <tr class="sum">
                    <td colspan="5">جـمع کـل</td>
                    <td class="num">${money(inv.subtotal_amount)}</td>
                    <td class="num">${money(inv.discount_amount)}</td>
                    <td class="num">${money(afterDiscTotal)}</td>
                    <td class="num">${money(inv.tax_amount)}</td>
                    <td class="num">${money(inv.total_amount)}</td>
                </tr>
            </table>

            <table class="blk pay-blk">
                <tr>
                    <td class="pay-terms">
                        <span class="pt-h">شرایط و نحوه فروش</span>
                        <label>نقدی <span class="chk">${cash ? '✕' : ''}</span></label>
                        <label>غیر نقدی <span class="chk">${credit ? '✕' : ''}</span></label>
                    </td>
                    <td class="seller-pay" rowspan="2">
                        <div><b>${esc(seller.company_name)}</b></div>
                        ${seller.iban ? `<div>شماره شبا : ${faDigits(esc(seller.iban))}</div>` : ''}
                        ${seller.card_number ? `<div>شماره کارت : ${faDigits(esc(seller.card_number))}${seller.bank_name ? ' (' + esc(seller.bank_name) + ')' : ''}</div>` : ''}
                    </td>
                </tr>
                <tr>
                    <td class="pay-note">توضیحات : ${esc(inv.note)}${footer ? ` <span class="muted">— ${esc(footer)}</span>` : ''}</td>
                </tr>
                <tr class="sign">
                    <td>مهر و امضاء فروشنده</td>
                    <td>مهر و امضاء خریدار</td>
                </tr>
            </table>`;
        }

        load();
    </script>
</body>

</html>
