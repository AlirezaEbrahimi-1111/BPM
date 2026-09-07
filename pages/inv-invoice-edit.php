<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

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
    <title><?= $invId ? 'ویرایش فاکتور' : 'فاکتور جدید' ?> - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/persian-date-utils.js') ?>"></script>
    <script src="<?= asset('../../assets/js/persian-datepicker.js') ?>"></script>

    <style>
        .inv-wrap {
            max-width: 1100px;
            margin: 0 auto;
        }

        .inv-head-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
            margin-bottom: 1rem;
        }

        .inv-head-grid > div {
            min-width: 0;
        }

        table.inv-items {
            width: 100%;
            border-collapse: collapse;
            font-size: .85rem;
        }

        table.inv-items th,
        table.inv-items td {
            border: 1px solid var(--border-soft, #e5e7eb);
            padding: 4px 6px;
            vertical-align: middle;
        }

        table.inv-items th {
            background: rgba(142, 87, 254, .08);
            font-weight: 600;
            white-space: nowrap;
        }

        table.inv-items input:not(.ep-input) {
            width: 100%;
            border: 0;
            background: transparent;
            padding: 4px;
            color: inherit;
        }

        table.inv-items input:not(.ep-input):focus {
            outline: 2px solid rgba(142, 87, 254, .35);
            border-radius: 4px;
        }

        table.inv-items td:nth-child(2) input {
            min-width: 200px;
        }

        .col-qty {
            width: 90px;
        }

        .col-price,
        .col-disc {
            width: 130px;
        }

        .col-exempt {
            width: 60px;
            text-align: center;
        }

        .col-stock {
            width: 120px;
        }

        .col-total {
            width: 130px;
            text-align: left;
            white-space: nowrap;
        }

        .col-del {
            width: 40px;
            text-align: center;
        }

        tr.row-lowstock td {
            background: rgba(220, 38, 38, .09);
        }

        .stock-warn {
            color: #dc2626;
            font-weight: 600;
        }

        .totals-box {
            margin-top: 1rem;
            margin-inline-start: auto;
            width: 320px;
            font-size: .9rem;
        }

        .totals-box .tl {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px dashed var(--border-soft, #e5e7eb);
        }

        .totals-box .tl.grand {
            font-weight: 700;
            font-size: 1.05rem;
            border-bottom: 0;
        }

        .totals-box .tl > span:last-child {
            letter-spacing: 0;
            direction: ltr;
            unicode-bidi: isolate;
            display: inline-block;
        }

        table.inv-items td.it-linetotal {
            letter-spacing: 0;
        }

        /* از ستونِ «تعداد» به بعد، وسط‌چین */
        table.inv-items td.col-qty,
        table.inv-items td.col-price,
        table.inv-items td.col-disc,
        table.inv-items td.col-exempt,
        table.inv-items td.col-stock,
        table.inv-items td.col-total {
            text-align: center;
        }

        table.inv-items td.col-qty input,
        table.inv-items td.col-price input,
        table.inv-items td.col-disc input {
            text-align: center;
        }

        table.inv-items .it-del {
            text-decoration: none !important;
        }

        .inv-actions {
            display: flex;
            gap: 10px;
            margin-top: 1.5rem;
            justify-content: flex-end;
        }

        /* همه‌ی فیلدهای سربرگ یک ارتفاع مشترک داشته باشند */
        .inv-head-grid .form-control,
        .inv-head-grid .ep-input,
        .inv-head-grid .sb-trigger,
        .set-grid .form-control,
        .set-grid .sb-trigger {
            height: 38px !important;
            min-height: 38px !important;
            font-size: 13px !important;
            box-sizing: border-box;
        }

        .inv-head-grid textarea.form-control,
        .set-grid textarea.form-control {
            height: auto !important;
            min-height: 0 !important;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container" style="margin-top:70px">
        <div class="inv-wrap">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h1 style="font-size:1.3rem;margin:0"><i class="bi bi-receipt ms-2"></i><span id="pageTitle"><?= $invId ? 'ویرایش فاکتور' : 'فاکتور جدید' ?></span></h1>
                <a href="/pages/inv-invoices.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right ms-1"></i> بازگشت به فهرست</a>
            </div>

            <div id="formAlert"></div>

            <div class="inv-head-grid">
                <div>
                    <label class="form-label">نوعِ سند</label>
                    <select class="form-select" id="f_doc_type">
                        <option value="official">فاکتور رسمی</option>
                        <option value="proforma">پیش‌فاکتور</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">مشتری <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="f_customer_name" list="custNameList"
                        autocomplete="off" placeholder="نامِ مشتری را تایپ کنید">
                    <datalist id="custNameList"></datalist>
                    <div class="form-text">نیازی به ثبتِ قبلیِ مشتری نیست؛ بعد از ذخیره، همین نام در فهرستِ مشتریان ثبت می‌شود.</div>
                </div>
                <div>
                    <label class="form-label">تاریخِ صدور</label>
                    <div class="persian-datepicker-wrapper">
                        <input type="text" class="persian-datepicker-input form-control" id="f_issue_date" placeholder="۱۴۰۵/۰۶/۱۱" readonly>
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
                <div>
                    <label class="form-label">نحوه‌ی فروش</label>
                    <select class="form-select" id="f_payment_type">
                        <option value="cash" selected>نقدی</option>
                        <option value="credit">غیرنقدی</option>
                    </select>
                </div>
            </div>

            <table class="inv-items">
                <thead>
                    <tr>
                        <th style="width:34px">#</th>
                        <th style="min-width:220px">کالا (کد / نام)</th>
                        <th>شرح</th>
                        <th class="col-qty">تعداد</th>
                        <th class="col-price">قیمت واحد</th>
                        <th class="col-disc">تخفیف</th>
                        <th class="col-exempt">معاف</th>
                        <th class="col-stock">قابل‌فروش</th>
                        <th class="col-total">جمع ردیف (با مالیات)</th>
                        <th class="col-del"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody"></tbody>
            </table>

            <datalist id="prodNameList"></datalist>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="btnAddRow"><i class="bi bi-plus-lg ms-1"></i> افزودن ردیف</button>

            <div class="totals-box">
                <div class="tl"><span>جمعِ کل</span><span id="t_subtotal">۰</span></div>
                <div class="tl"><span>تخفیف</span><span id="t_discount">۰</span></div>
                <div class="tl"><span>مالیات بر ارزش افزوده (<span id="t_vatrate">۰</span>٪)</span><span id="t_tax">۰</span></div>
                <div class="tl grand"><span>مبلغِ قابل پرداخت</span><span id="t_total">۰</span></div>
            </div>

            <div class="mt-3">
                <label class="form-label">توضیحات</label>
                <textarea class="form-control" id="f_note" rows="2"></textarea>
            </div>

            <div class="inv-actions">
                <button type="button" class="btn btn-outline-primary" id="btnSave">ذخیره‌ی پیش‌نویس</button>
                <button type="button" class="btn btn-primary btn-lg px-4" id="btnSaveBack">ذخیره و بازگشت</button>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        const API = '/crm/api';
        const INV_ID = <?= $invId ?>;

        function tok() {
            return localStorage.getItem('auth_token');
        }

        function ahj() {
            return {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + tok()
            };
        }

        function toEn(s) {
            return String(s == null ? '' : s)
                .replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
                .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
        }

        function num(v) {
            const n = parseFloat(toEn(v).replace(/[,٬\s]/g, '').replace(/[^\d.-]/g, ''));
            return isNaN(n) ? 0 : n;
        }

        function money(n) {
            return faDigits(String(Math.round(Number(n) || 0).toLocaleString('en-US')));
        }

        // مقدارِ نمایشیِ فیلدِ قیمت/تخفیف: عددِ صحیح با جداکننده‌ی هزارگان و رقمِ فارسی
        function faMoney(v) {
            const n = parseInt(toEn(String(v == null ? '' : v)).replace(/[^\d-]/g, ''), 10);
            return isNaN(n) ? '' : faDigits(n.toLocaleString('en-US'));
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

        async function apiSend(method, path, body) {
            const r = await fetch(API + path, {
                method,
                headers: ahj(),
                body: JSON.stringify(body)
            });
            const d = await r.json().catch(() => ({}));
            if (!r.ok || d.success === false) throw new Error(d.message || ('خطای ' + r.status));
            return d;
        }

        let VAT = 0;
        let products = [],
            customers = [];
        let loadedCustomerId = 0; // در حالتِ ویرایش: مشتریِ فعلیِ همین فاکتور

        function fillCustDatalist() {
            const dl = document.getElementById('custNameList');
            if (dl) dl.innerHTML = customers.map(c =>
                `<option value="${String(c.name || '').replace(/"/g, '&quot;')}">`).join('');
        }

        function fillProdDatalist() {
            const dl = document.getElementById('prodNameList');
            if (dl) dl.innerHTML = products.map(p =>
                `<option value="${String(p.name || '').replace(/"/g, '&quot;')}">`).join('');
        }


        function alertBox(msg, kind = 'danger') {
            document.getElementById('formAlert').innerHTML =
                msg ? `<div class="alert alert-${kind} py-2">${msg}</div>` : '';
        }

        function rowTemplate() {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="idx text-center"></td>
                <td><input class="it-prod-name" list="prodNameList" autocomplete="off" placeholder="نام کالا را تایپ کنید"></td>
                <td><input class="it-title" placeholder="شرحِ اختیاری"></td>
                <td class="col-qty"><input class="it-qty" inputmode="decimal" value="۱"></td>
                <td class="col-price"><input class="it-price" inputmode="numeric" value="۰"></td>
                <td class="col-disc"><input class="it-disc" inputmode="numeric" value="۰"></td>
                <td class="col-exempt"><input type="checkbox" class="it-exempt"></td>
                <td class="col-stock it-stock small text-muted"></td>
                <td class="col-total it-linetotal">۰</td>
                <td class="col-del"><button type="button" class="btn btn-sm btn-link text-danger p-0 it-del">✕</button></td>`;
            // نامِ کالا: فیلدِ متنِ آزاد (مثلِ نامِ مشتری). اگر متنِ تایپ‌شده دقیقاً
            // با یک کالای کاتالوگ یکی بود، قیمت/معافیت خودکار پر می‌شود و ردیف به
            // آن کالا گره می‌خورد؛ وگرنه هنگامِ ذخیره یک کالای تازه ساخته می‌شود.
            tr.querySelector('.it-prod-name').addEventListener('change', () => syncProdName(tr));
            tr.querySelectorAll('.it-qty,.it-price,.it-disc').forEach(el => {
                el.addEventListener('input', recalc);
                el.addEventListener('blur', () => {
                    el.value = el.classList.contains('it-qty') ?
                        faDigits(toEn(el.value)) : faMoney(el.value);
                });
            });
            tr.querySelector('.it-exempt').addEventListener('change', recalc);
            tr.querySelector('.it-del').addEventListener('click', () => {
                tr.remove();
                renumber();
                recalc();
            });
            // پیمایش با Enter: نام کالا → تعداد → قیمت واحد
            focusOnEnter(tr.querySelector('.it-prod-name'), () => tr.querySelector('.it-qty'));
            focusOnEnter(tr.querySelector('.it-qty'), () => tr.querySelector('.it-price'));
            return tr;
        }

        // فوکوسِ فیلدِ بعدی با زدنِ Enter
        function focusOnEnter(el, nextFn) {
            if (!el) return;
            el.addEventListener('keydown', e => {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                const n = (typeof nextFn === 'function') ? nextFn() : nextFn;
                if (n && typeof n.focus === 'function') {
                    n.focus();
                    if (typeof n.select === 'function') n.select();
                }
            });
        }

        // متنِ نامِ کالا با کاتالوگ هماهنگ شود (تطبیقِ دقیقِ نام).
        function syncProdName(tr) {
            const v = tr.querySelector('.it-prod-name').value.trim();
            const p = v ? products.find(x => String(x.name || '').trim() === v) : null;
            if (p) {
                tr.dataset.productId = p.id;
                const priceEl = tr.querySelector('.it-price');
                if (!num(priceEl.value)) priceEl.value = faMoney(p.unit_price);
                if (!tr.querySelector('.it-exempt').checked) tr.querySelector('.it-exempt').checked = !!p.is_tax_exempt;
            } else {
                delete tr.dataset.productId;
            }
            updateStockHint(tr);
            recalc();
        }

        function addRow(data) {
            const tr = rowTemplate();
            document.getElementById('itemsBody').appendChild(tr);
            if (data) {
                const p = data.product_id ? products.find(x => x.id === +data.product_id) : null;
                if (data.product_id) {
                    tr.dataset.productId = data.product_id;
                    tr.querySelector('.it-prod-name').value = p ? (p.name || '') : (data.title || '');
                    // «شرح» فقط اگر با نامِ کالا فرق داشت (توضیحِ سفارشیِ ردیف)
                    tr.querySelector('.it-title').value =
                        (data.title && (!p || data.title.trim() !== (p.name || '').trim())) ? data.title : '';
                } else {
                    // قلمِ متنیِ آزاد → نام در فیلدِ نامِ کالا
                    tr.querySelector('.it-prod-name').value = data.title || '';
                }
                tr.querySelector('.it-qty').value = faDigits(data.qty ?? 1);
                tr.querySelector('.it-price').value = faMoney(data.unit_price ?? 0);
                tr.querySelector('.it-disc').value = faMoney(data.discount ?? 0);
                tr.querySelector('.it-exempt').checked = !!data.is_tax_exempt;
            }
            renumber();
            updateStockHint(tr);
            recalc();
            return tr;
        }

        function renumber() {
            document.querySelectorAll('#itemsBody tr').forEach((tr, i) => {
                tr.querySelector('.idx').textContent = faDigits(i + 1);
            });
        }

        function updateStockHint(tr) {
            const cell = tr.querySelector('.it-stock');
            const pid = tr.dataset.productId ? +tr.dataset.productId : 0;
            if (!pid) {
                cell.textContent = '';
                tr.classList.remove('row-lowstock');
                return;
            }
            const p = products.find(x => x.id === pid);
            const qty = num(tr.querySelector('.it-qty').value);
            const avail = p ? Number(p.available != null ? p.available : (p.stock || 0)) : 0;
            if (qty > avail) {
                cell.innerHTML = `<span class="stock-warn">${faDigits(avail)} (کمبود)</span>`;
                tr.classList.add('row-lowstock');
            } else {
                cell.textContent = faDigits(avail);
                tr.classList.remove('row-lowstock');
            }
        }

        function recalc() {
            let subtotal = 0,
                discount = 0,
                tax = 0;
            document.querySelectorAll('#itemsBody tr').forEach(tr => {
                const qty = num(tr.querySelector('.it-qty').value);
                const price = num(tr.querySelector('.it-price').value);
                const disc = num(tr.querySelector('.it-disc').value);
                const exempt = tr.querySelector('.it-exempt').checked;
                const gross = Math.round(qty * price);
                let after = gross - disc;
                if (after < 0) after = 0;
                const t = exempt ? 0 : Math.round(after * VAT / 100);
                tr.querySelector('.it-linetotal').textContent = money(after + t);
                subtotal += gross;
                discount += disc;
                tax += t;
                updateStockHint(tr);
            });
            document.getElementById('t_subtotal').textContent = money(subtotal);
            document.getElementById('t_discount').textContent = money(discount);
            document.getElementById('t_tax').textContent = money(tax);
            document.getElementById('t_total').textContent = money(subtotal - discount + tax);
        }

        function collect() {
            const customerName = document.getElementById('f_customer_name').value.trim();
            const items = [];
            document.querySelectorAll('#itemsBody tr').forEach(tr => {
                const name = tr.querySelector('.it-prod-name').value.trim();
                const desc = tr.querySelector('.it-title').value.trim();
                // عنوانِ ردیف: «شرح» اگر پر باشد، وگرنه نامِ کالا.
                const title = desc || name;
                if (!title) return;
                items.push({
                    product_id: tr.dataset.productId ? +tr.dataset.productId : null,
                    title,
                    qty: num(tr.querySelector('.it-qty').value),
                    unit_price: Math.round(num(tr.querySelector('.it-price').value)),
                    discount: Math.round(num(tr.querySelector('.it-disc').value)),
                    is_tax_exempt: tr.querySelector('.it-exempt').checked,
                });
            });
            const dateInput = document.getElementById('f_issue_date');
            let issue = dateInput.getAttribute('data-date') || '';
            if (!issue && dateInput.value && typeof convertToGregorian === 'function') {
                issue = convertToGregorian(dateInput.value) || '';
            }
            return {
                customerName,
                body: {
                    doc_type: document.getElementById('f_doc_type').value,
                    customer_id: loadedCustomerId || 0,
                    customer_name: customerName,
                    issue_date: issue,
                    payment_type: document.getElementById('f_payment_type').value,
                    note: document.getElementById('f_note').value.trim(),
                    items,
                }
            };
        }

        async function save(goBack) {
            const {
                customerName,
                body
            } = collect();
            if (!customerName) {
                alertBox('نامِ مشتری را وارد کنید.');
                return;
            }
            if (!body.items.length) {
                alertBox('حداقل یک ردیف با نامِ کالا لازم است.');
                return;
            }
            alertBox('');
            try {
                let id = INV_ID;
                if (INV_ID) await apiSend('PUT', '/inv/invoices/' + INV_ID, body);
                else {
                    const d = await apiSend('POST', '/inv/invoices', body);
                    id = d.id;
                }
                showToast('ذخیره شد', 'success');
                if (goBack) location.href = '/pages/inv-invoices.php';
                else if (!INV_ID) location.href = '/pages/inv-invoice-edit.php?id=' + id;
            } catch (e) {
                alertBox(e.message || 'خطا در ذخیره');
            }
        }

        async function reloadCustomers() {
            const out = [];
            try {
                let page = 1,
                    total = Infinity;
                while (out.length < total) {
                    const d = await apiGet('/customers?per=200&page=' + page);
                    out.push(...(d.items || []));
                    total = d.total || 0;
                    if (!d.items || !d.items.length) break;
                    page++;
                }
                customers = out;
            } catch (e) {}
        }

        async function reloadProducts(exParam) {
            const out = [];
            try {
                let page = 1,
                    total = Infinity;
                while (out.length < total) {
                    const d = await apiGet('/inv/products?per=200&page=' + page + (exParam || ''));
                    out.push(...(d.items || []));
                    total = d.total || 0;
                    if (!d.items || !d.items.length) break;
                    page++;
                }
                products = out;
            } catch (e) {}
        }

        async function init() {
            if (!tok()) {
                location.href = '../index.php';
                return;
            }
            try {
                const st = await apiGet('/inv/settings');
                VAT = Number(st.vat_rate || 0);
                document.getElementById('t_vatrate').textContent = faDigits(VAT);
            } catch (e) {
                /* ادامه با VAT=0 */
            }

            // مشتری‌ها — فقط برای پیشنهادِ خودکارِ نام (datalist)؛ ثبتِ قبلی لازم نیست.
            await reloadCustomers();
            fillCustDatalist();
            // کالاها — در حالتِ ویرایش، خودِ این فاکتور از محاسبه‌ی رزرو کنار می‌رود.
            const exParam = INV_ID ? '&exclude_invoice=' + INV_ID : '';
            await reloadProducts(exParam);
            fillProdDatalist();

            // وقتی از تبِ «مشتریان» یا «کاتالوگ کالا» برگشتی، هر دو فهرست تازه شوند
            window.addEventListener('focus', async () => {
                await reloadCustomers();
                fillCustDatalist();
                await reloadProducts(exParam);
                fillProdDatalist();
            });

            if (INV_ID) {
                try {
                    const d = await apiGet('/inv/invoices/' + INV_ID);
                    const inv = d.invoice;
                    if (inv.status !== 'draft') {
                        location.href = '/pages/inv-invoice-print.php?id=' + INV_ID;
                        return;
                    }
                    document.getElementById('f_doc_type').value = inv.doc_type;
                    document.getElementById('f_payment_type').value = inv.payment_type || '';
                    loadedCustomerId = inv.customer_id || 0;
                    document.getElementById('f_customer_name').value =
                        (inv.customer_name && inv.customer_name !== '—') ? inv.customer_name : '';
                    document.getElementById('f_note').value = inv.note || '';
                    if (inv.issue_date) {
                        const di = document.getElementById('f_issue_date');
                        di.setAttribute('data-date', inv.issue_date);
                        di.value = (typeof convertToJalali === 'function') ? convertToJalali(inv.issue_date) : inv.issue_date;
                    }
                    (inv.items || []).forEach(addRow);
                } catch (e) {
                    alertBox('بارگذاریِ فاکتور ناموفق بود: ' + (e.message || ''));
                }
            }
            if (!document.querySelectorAll('#itemsBody tr').length) {
                addRow();
            }
            recalc();
            if (typeof window.reinitPersianDatepickers === 'function') window.reinitPersianDatepickers();

            // فاکتورِ جدید: تاریخِ صدور پیش‌فرض = امروز
            if (!INV_ID) {
                const di = document.getElementById('f_issue_date');
                if (di && !di.getAttribute('data-date')) {
                    const t = new Date();
                    const g = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0');
                    di.setAttribute('data-date', g);
                    di.value = (typeof convertToJalali === 'function') ? convertToJalali(g) : g;
                }
            }

            document.getElementById('btnAddRow').addEventListener('click', () => {
                const tr = addRow();
                if (tr) tr.querySelector('.it-prod-name').focus();
            });
            document.getElementById('btnSave').addEventListener('click', () => save(false));
            document.getElementById('btnSaveBack').addEventListener('click', () => save(true));

            // پیمایش با Enter در سربرگ: نام مشتری → تاریخ صدور → نام کالای ردیفِ اول
            focusOnEnter(document.getElementById('f_customer_name'), () => document.getElementById('f_issue_date'));
            focusOnEnter(document.getElementById('f_issue_date'), () => document.querySelector('#itemsBody .it-prod-name'));

            // هنگامِ لود، فوکوس روی نامِ مشتری
            document.getElementById('f_customer_name').focus();
        }

        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>

</html>
