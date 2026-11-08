<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// CRM جدید — نسخهٔ چاپی سادهٔ پیش‌فاکتور فروش / سفارش فروش / سفارش خرید (۱۴۰۵/۰۸/۱۷؛ طرح نهایی بعدا از کاربر).
// داده از crm-service (GET /ucrm/proforma-docs/{id})؛ بدون هدر سایت تا فقط خود سند چاپ شود.
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ucrm_access.php';
if (!ucrmAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}
$orgName = (string) $db->query("SELECT name FROM organizations WHERE id = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چاپ پیش‌فاکتور</title>
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../../assets/js/common.js') ?>"></script>
    <script src="<?= asset('../../assets/js/persian-date-utils.js') ?>"></script>
    <script src="<?= asset('../../assets/js/ucrm-common.js') ?>"></script>
    <style>
        /* سند چاپی همیشه روشن است (کاغذ) — حالت تاریک عمدا اعمال نمی‌شود */
        body { background: #f3f4f6; color: #111827; font-family: 'Vazirmatn', sans-serif; }
        .sheet { background: #fff; max-width: 210mm; margin: 16px auto; padding: 14mm; box-shadow: 0 2px 12px rgba(0, 0, 0, .08); }
        .head { display: flex; align-items: center; gap: 16px; border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 14px; }
        .head img { height: 54px; }
        .head h1 { font-size: 1.2rem; font-weight: 800; margin: 0; }
        .head .org { font-size: .9rem; color: #4b5563; }
        .head .meta { margin-inline-start: auto; font-size: .85rem; line-height: 1.9; text-align: left; direction: rtl; }
        .party { border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 12px; font-size: .9rem; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        th, td { border: 1px solid #9ca3af; padding: 6px; text-align: center; }
        th { background: #f3f4f6; }
        td.name { text-align: right; }
        .totals { width: 300px; margin-inline-start: auto; margin-top: 10px; font-size: .9rem; }
        .totals div { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px dashed #d1d5db; }
        .totals .grand { font-weight: 800; border-bottom: 0; }
        .note { margin-top: 12px; font-size: .85rem; white-space: pre-wrap; }
        .sign { display: flex; justify-content: space-between; margin-top: 40px; font-size: .85rem; }
        .toolbar { max-width: 210mm; margin: 12px auto 0; display: flex; gap: 8px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; }
            @page { size: A4; margin: 12mm; }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer ms-1"></i>چاپ</button>
        <button class="btn btn-light btn-sm" onclick="window.close()">بستن</button>
    </div>
    <div class="sheet" id="sheet"><div class="text-muted p-4 text-center">در حال بارگذاری…</div></div>

    <script>
        // esc/faDigits از assets/js/common.js
        const ORG = <?= json_encode($orgName, JSON_UNESCAPED_UNICODE) ?>;
        (async () => {
            const id = parseInt(new URLSearchParams(location.search).get('id'), 10) || 0;
            const box = document.getElementById('sheet');
            try {
                const d = await UCRM.get('/proforma-docs/' + id);
                const T = { proforma: ['پیش‌فاکتور فروش', 'خریدار'], sales_order: ['سفارش فروش', 'خریدار'], purchase_order: ['سفارش خرید', 'فروشنده (تأمین‌کننده)'] }[d.doc_type] || ['پیش‌فاکتور فروش', 'خریدار'];
                const party = d.doc_type === 'purchase_order' ? d.party_name : d.customer_name;
                const date2 = d.doc_type === 'proforma' ? '' : (d.expected_date ? 'تحویل مورد انتظار: <b>' + faDigits(convertToJalali(d.expected_date)) + '</b>' : '');
                document.title = T[0] + ' ' + d.number;
                const rows = (d.items || []).map((it, i) => `<tr>
                    <td>${faDigits(i + 1)}</td><td>${faDigits(esc(it.item_code))}</td><td class="name">${esc(it.item_name)}</td>
                    <td>${esc(it.unit)}</td><td>${faDigits(it.qty)}</td><td>${UCRM.money(it.unit_price)}</td>
                    <td>${UCRM.money(it.discount)}</td><td>${UCRM.money(it.line_total)}</td></tr>`).join('');
                box.innerHTML = `
                    <div class="head">
                        <img src="/assets/images/invoice-logo-print.png" alt="">
                        <div><h1>${T[0]}</h1><div class="org">${esc(ORG)}</div></div>
                        <div class="meta">شماره: <b>${faDigits(esc(d.number))}</b><br>تاریخ: <b>${faDigits(convertToJalali(d.issue_date))}</b>${date2 ? '<br>' + date2 : ''}</div>
                    </div>
                    <div class="party">${T[1]}: <b>${esc(party)}</b>${d.rep_name ? ' — کارشناس فروش: ' + esc(d.rep_name) : ''}</div>
                    <table>
                        <thead><tr><th>#</th><th>کد کالا</th><th>شرح کالا</th><th>واحد</th><th>تعداد</th><th>فی (تومان)</th><th>تخفیف (تومان)</th><th>مبلغ (تومان)</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                    <div class="totals">
                        <div><span>جمع کل</span><span>${UCRM.money(d.subtotal)}</span></div>
                        <div><span>تخفیف</span><span>${UCRM.money(d.discount)}</span></div>
                        <div class="grand"><span>مبلغ قابل پرداخت (تومان)</span><span>${UCRM.money(d.total)}</span></div>
                    </div>
                    ${d.note ? `<div class="note"><b>توضیحات:</b> ${esc(d.note)}</div>` : ''}
                    <div class="sign"><span>مهر و امضای ${d.doc_type === 'purchase_order' ? 'خریدار' : 'فروشنده'}</span><span>مهر و امضای ${d.doc_type === 'purchase_order' ? 'فروشنده' : 'خریدار'}</span></div>`;
            } catch (e) {
                box.innerHTML = `<div class="alert alert-danger m-0">${esc(e.message || 'پیش‌فاکتور یافت نشد')}</div>`;
            }
        })();
    </script>
</body>

</html>
