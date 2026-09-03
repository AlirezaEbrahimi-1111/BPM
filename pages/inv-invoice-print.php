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
            background: #eef0f3;
        }

        .toolbar {
            max-width: 900px;
            margin: 14px auto 0;
            display: flex;
            gap: 10px;
        }

        .sheet {
            max-width: 900px;
            margin: 14px auto 40px;
            background: #fff;
            color: #000;
            padding: 14px 16px 18px;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .12);
            font-size: 12px;
            line-height: 1.7;
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
            text-align: right;
        }

        table.items td.num,
        .tots .r span:last-child {
            text-align: left;
            white-space: nowrap;
            letter-spacing: 0;
            font-variant-numeric: proportional-nums;
            font-feature-settings: "pnum" 1, "tnum" 0;
            direction: ltr;
            unicode-bidi: isolate;
        }

        table.items tr.sum td {
            font-weight: 700;
            background: #f6f6f6;
        }

        .pay label {
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
        }

        .sign td {
            height: 54px;
            vertical-align: top;
            font-weight: 600;
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
                max-width: none;
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

            const itemRows = (inv.items || []).map((it, i) => {
                const gross = Math.round((Number(it.qty) || 0) * (Number(it.unit_price) || 0));
                const afterDisc = gross - (Number(it.discount) || 0);
                return `<tr>
                    <td>${faDigits(i + 1)}</td>
                    <td>${faDigits(esc(it.code)) || '—'}</td>
                    <td class="desc">${esc(it.title)}</td>
                    <td>${faDigits(it.qty)}</td>
                    <td class="num">${money(it.unit_price)}</td>
                    <td class="num">${money(gross)}</td>
                    <td class="num">${money(it.discount)}</td>
                    <td class="num">${money(afterDisc)}</td>
                    <td class="num">${it.is_tax_exempt ? '—' : money(it.tax_amount)}</td>
                    <td class="num">${money(it.line_total)}</td>
                </tr>`;
            }).join('');

            const afterDiscTotal = (Number(inv.subtotal_amount) || 0) - (Number(inv.discount_amount) || 0);

            document.getElementById('sheet').innerHTML = `
            <table class="inv-top">
                <tr>
                    <td style="width:150px" class="inv-nobox">
                        <table style="border:0">
                            <tr><td class="lbl" style="border:1px solid #000">شماره فاکتور</td>
                                <td style="border:1px solid #000">${inv.number ? faDigits(inv.number) : '—'}</td></tr>
                            <tr><td class="lbl" style="border:1px solid #000">تاریخ</td>
                                <td style="border:1px solid #000">${jDate(inv.issue_date)}</td></tr>
                        </table>
                    </td>
                    <td class="inv-title">${DT[inv.doc_type] || 'صورتحساب فروش کالا و خدمات'}</td>
                </tr>
            </table>

            <table style="margin-top:-1px">
                <tr><td colspan="6" class="band">مشخصات فروشنده</td></tr>
                <tr>
                    <td class="lbl">نام شخص حقیقی و حقوقی</td><td>${esc(seller.company_name)}</td>
                    <td class="lbl">کد اقتصادی</td><td>${boxed(seller.economic_code)}</td>
                    <td class="lbl">شناسه ملی</td><td>${boxed(seller.national_id)}</td>
                </tr>
                <tr>
                    <td class="lbl">استان</td><td>${esc(seller.province)}</td>
                    <td class="lbl">شهرستان</td><td>${esc(seller.shahrestan)}</td>
                    <td class="lbl">شهر</td><td>${esc(seller.city)}</td>
                </tr>
                <tr>
                    <td class="lbl">کد پستی ۱۰ رقمی</td><td>${boxed(seller.postal_code)}</td>
                    <td class="lbl">شماره تلفن / نمابر</td><td colspan="3">${faDigits(esc(seller.phone))}</td>
                </tr>
                <tr>
                    <td class="lbl">نشانی کامل</td><td colspan="5">${esc(seller.address)}</td>
                </tr>
            </table>

            <table style="margin-top:-1px">
                <tr><td colspan="6" class="band">مشخصات خریدار</td></tr>
                <tr>
                    <td class="lbl">نام شخص حقیقی و حقوقی</td><td>${esc(buyer.name)}</td>
                    <td class="lbl">شماره اقتصادی</td><td>${boxed(buyer.economic_code)}</td>
                    <td class="lbl">شناسه ملی</td><td>${boxed(buyer.national_id)}</td>
                </tr>
                <tr>
                    <td class="lbl">استان</td><td>${esc(buyer.province)}</td>
                    <td class="lbl">شهر</td><td>${esc(buyer.city)}</td>
                    <td class="lbl">کد پستی ۱۰ رقمی</td><td>${boxed(buyer.postal_code)}</td>
                </tr>
                <tr>
                    <td class="lbl">آدرس</td><td colspan="3">${esc(buyer.address)}</td>
                    <td class="lbl">شماره تلفن</td><td>${faDigits(esc(buyer.phone || buyer.mobile))}</td>
                </tr>
            </table>

            <table class="items" style="margin-top:-1px">
                <tr><td colspan="10" class="band">مشخصات کالا یا خدمات مورد معامله</td></tr>
                <tr>
                    <th style="width:26px">ردیف</th>
                    <th style="width:60px">کد کالا</th>
                    <th>شرح کالا یا خدمات</th>
                    <th style="width:64px">تعداد / مقدار</th>
                    <th style="width:78px">مبلغ واحد (ریال)</th>
                    <th style="width:88px">مبلغ کل (ریال)</th>
                    <th style="width:78px">مبلغ تخفیف (ریال)</th>
                    <th style="width:92px">مبلغ کل پس از تخفیف (ریال)</th>
                    <th style="width:86px">جمع مالیات و عوارض (ریال)</th>
                    <th style="width:100px">جمع مبلغ کل بعلاوه جمع مالیات و عوارض (ریال)</th>
                </tr>
                ${itemRows}
                <tr class="sum">
                    <td colspan="5">جمع کـل</td>
                    <td class="num">${money(inv.subtotal_amount)}</td>
                    <td class="num">${money(inv.discount_amount)}</td>
                    <td class="num">${money(afterDiscTotal)}</td>
                    <td class="num">${money(inv.tax_amount)}</td>
                    <td class="num">${money(inv.total_amount)}</td>
                </tr>
            </table>

            <table style="margin-top:-1px">
                <tr>
                    <td style="width:50%" class="pay">
                        <div>شرایط و نحوه فروش:
                            <label>نقدی <span class="chk">${cash ? '✕' : ''}</span></label>
                            <label>غیر نقدی <span class="chk">${credit ? '✕' : ''}</span></label>
                        </div>
                        <div style="margin-top:6px">توضیحات: ${esc(inv.note)}</div>
                        ${footer ? `<div style="margin-top:6px" class="muted">${esc(footer)}</div>` : ''}
                    </td>
                    <td class="seller-pay">
                        <div><b>${esc(seller.company_name)}</b></div>
                        ${seller.iban ? `<div>شماره شبا: ${faDigits(esc(seller.iban))}</div>` : ''}
                        ${seller.card_number ? `<div>شماره کارت: ${faDigits(esc(seller.card_number))}${seller.bank_name ? ' (' + esc(seller.bank_name) + ')' : ''}</div>` : ''}
                    </td>
                </tr>
                <tr class="sign">
                    <td>مهر و امضا فروشنده</td>
                    <td>مهر و امضا خریدار</td>
                </tr>
            </table>`;
        }

        load();
    </script>
</body>

</html>
