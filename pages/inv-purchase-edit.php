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
$purId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $purId ? 'ویرایش فاکتور خرید' : 'فاکتور خرید جدید' ?></title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/persian-date-utils.js') ?>"></script>
    <script src="<?= asset('../../assets/js/persian-datepicker.js') ?>"></script>
    <script src="<?= asset('../../assets/js/entity-picker.js') ?>"></script>
    <script src="<?= asset('../../assets/js/quick-add.js') ?>"></script>

    <style>
        .inv-wrap {
            max-width: 1050px;
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

        table.inv-items .it-prod-pick {
            min-width: 230px;
        }

        .col-qty {
            width: 90px;
        }

        .col-price,
        .col-disc {
            width: 130px;
        }

        .col-stock {
            width: 110px;
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

        table.inv-items td.col-qty,
        table.inv-items td.col-price,
        table.inv-items td.col-disc,
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
                <h1 style="font-size:1.3rem;margin:0"><i class="bi bi-cart-plus ms-2"></i><span><?= $purId ? 'ویرایش فاکتور خرید' : 'فاکتور خرید جدید' ?></span></h1>
                <a href="/pages/inv-purchases.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right ms-1"></i> بازگشت به فهرست</a>
            </div>

            <div id="formAlert"></div>

            <div class="inv-head-grid">
                <div>
                    <label class="form-label">تأمین‌کننده <span class="text-danger">*</span></label>
                    <div id="supplierPicker"></div>
                </div>
                <div>
                    <label class="form-label">شماره‌ی فاکتورِ فروشنده</label>
                    <input type="text" class="form-control" id="f_ref">
                </div>
                <div>
                    <label class="form-label">تاریخ</label>
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
            </div>

            <table class="inv-items">
                <thead>
                    <tr>
                        <th style="width:34px">#</th>
                        <th>کالا <span class="text-danger">*</span></th>
                        <th class="col-qty">تعداد</th>
                        <th class="col-price">قیمت خرید واحد</th>
                        <th class="col-disc">تخفیف</th>
                        <th class="col-stock">موجودی فعلی</th>
                        <th class="col-total">جمع ردیف (با مالیات)</th>
                        <th class="col-del"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody"></tbody>
            </table>

            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="btnAddRow"><i class="bi bi-plus-lg ms-1"></i> افزودن ردیف</button>

            <div class="totals-box">
                <div class="tl"><span>جمعِ کل</span><span id="t_subtotal">۰</span></div>
                <div class="tl"><span>تخفیف</span><span id="t_discount">۰</span></div>
                <div class="tl"><span>مالیات بر ارزش افزوده (<span id="t_vatrate">۰</span>٪)</span><span id="t_tax">۰</span></div>
                <div class="tl grand"><span>جمعِ نهایی</span><span id="t_total">۰</span></div>
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
        const PUR_ID = <?= $purId ?>;

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

        function faDigits(s) {
            return String(s == null ? '' : s).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹' [+d]);
        }

        function num(v) {
            const n = parseFloat(toEn(v).replace(/[,٬\s]/g, '').replace(/[^\d.-]/g, ''));
            return isNaN(n) ? 0 : n;
        }

        function money(n) {
            return faDigits(String(Math.round(Number(n) || 0).toLocaleString('en-US')));
        }

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
            suppliers = [];
        let supplierPicker = null,
            prodItems = [];

        function alertBox(msg, kind = 'danger') {
            document.getElementById('formAlert').innerHTML =
                msg ? `<div class="alert alert-${kind} py-2">${msg}</div>` : '';
        }

        function firstWords(s, n) {
            const w = String(s || '').trim().split(/\s+/);
            return w.slice(0, n).join(' ') + (w.length > n ? '…' : '');
        }

        function buildProdItems() {
            prodItems = products.map(p => ({
                id: p.id,
                label: p.code ? (faDigits(p.code) + ' — ' + firstWords(p.name, 5)) : firstWords(p.name, 5),
                meta: 'موجودی ' + faDigits(Number(p.stock || 0)) + (p.unit ? ' • ' + p.unit : ''),
                search: (p.code || '') + ' ' + p.name,
            }));
        }

        function supItems() {
            return suppliers.map(sp => ({
                id: sp.id,
                label: sp.name,
                meta: [sp.mobile, sp.phone].filter(Boolean).map(faDigits).join(' • '),
                search: sp.name + ' ' + (sp.mobile || '') + ' ' + (sp.phone || '') + ' ' + (sp.national_id || ''),
            }));
        }

        function rowTemplate() {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="idx text-center"></td>
                <td><div class="it-prod-pick"></div></td>
                <td class="col-qty"><input class="it-qty" inputmode="decimal" value="۱"></td>
                <td class="col-price"><input class="it-price" inputmode="numeric" value="۰"></td>
                <td class="col-disc"><input class="it-disc" inputmode="numeric" value="۰"></td>
                <td class="col-stock it-stock small text-muted"></td>
                <td class="col-total it-linetotal">۰</td>
                <td class="col-del"><button type="button" class="btn btn-sm btn-link text-danger p-0 it-del">✕</button></td>`;
            tr._picker = EntityPicker.create({
                container: tr.querySelector('.it-prod-pick'),
                items: prodItems,
                placeholder: 'کد یا نامِ کالا…',
                addTitle: 'افزودن کالای جدید',
                onAdd: () => QuickAdd.product(p => {
                    products.push(p);
                    buildProdItems();
                    document.querySelectorAll('#itemsBody tr').forEach(r => {
                        if (r._picker) r._picker.updateItems(prodItems);
                    });
                    tr._picker.setValue(p.id);
                }),
                onSelect: it => onRowProduct(tr, it),
            });
            tr.querySelectorAll('.it-qty,.it-price,.it-disc').forEach(el => {
                el.addEventListener('input', recalc);
                el.addEventListener('blur', () => {
                    el.value = el.classList.contains('it-qty') ?
                        faDigits(toEn(el.value)) : faMoney(el.value);
                });
            });
            tr.querySelector('.it-del').addEventListener('click', () => {
                tr.remove();
                renumber();
                recalc();
            });
            return tr;
        }

        function addRow(data) {
            const tr = rowTemplate();
            document.getElementById('itemsBody').appendChild(tr);
            if (data) {
                if (data.product_id) tr._picker.setValue(data.product_id);
                tr.querySelector('.it-qty').value = faDigits(data.qty ?? 1);
                tr.querySelector('.it-price').value = faMoney(data.unit_price ?? 0);
                tr.querySelector('.it-disc').value = faMoney(data.discount ?? 0);
            }
            renumber();
            updateStockHint(tr);
            recalc();
        }

        function renumber() {
            document.querySelectorAll('#itemsBody tr').forEach((tr, i) => {
                tr.querySelector('.idx').textContent = faDigits(i + 1);
            });
        }

        function onRowProduct(tr, item) {
            if (item) {
                const p = products.find(x => x.id === item.id);
                tr.dataset.productId = item.id;
                const price = tr.querySelector('.it-price');
                if (p && num(price.value) === 0) price.value = faDigits(p.unit_price);
                const q = tr.querySelector('.it-qty');
                setTimeout(() => {
                    q.focus();
                    q.select();
                }, 0);
            } else {
                delete tr.dataset.productId;
            }
            updateStockHint(tr);
            recalc();
        }

        function updateStockHint(tr) {
            const cell = tr.querySelector('.it-stock');
            const pid = tr.dataset.productId ? +tr.dataset.productId : 0;
            const p = pid ? products.find(x => x.id === pid) : null;
            cell.textContent = p ? faDigits(Number(p.stock || 0)) : '';
        }

        function prodExempt(pid) {
            const p = products.find(x => x.id === pid);
            return p ? !!p.is_tax_exempt : false;
        }

        function recalc() {
            let subtotal = 0,
                discount = 0,
                tax = 0;
            document.querySelectorAll('#itemsBody tr').forEach(tr => {
                const qty = num(tr.querySelector('.it-qty').value);
                const price = num(tr.querySelector('.it-price').value);
                const disc = num(tr.querySelector('.it-disc').value);
                const pid = tr.dataset.productId ? +tr.dataset.productId : 0;
                const gross = Math.round(qty * price);
                let after = gross - disc;
                if (after < 0) after = 0;
                const t = prodExempt(pid) ? 0 : Math.round(after * VAT / 100);
                tr.querySelector('.it-linetotal').textContent = money(after + t);
                subtotal += gross;
                discount += disc;
                tax += t;
            });
            document.getElementById('t_subtotal').textContent = money(subtotal);
            document.getElementById('t_discount').textContent = money(discount);
            document.getElementById('t_tax').textContent = money(tax);
            document.getElementById('t_total').textContent = money(subtotal - discount + tax);
        }

        function collect() {
            const picked = supplierPicker ? supplierPicker.getValue() : null;
            const sup = picked ? suppliers.find(s => s.id === picked.id) : null;
            const items = [];
            document.querySelectorAll('#itemsBody tr').forEach(tr => {
                const pid = tr.dataset.productId ? +tr.dataset.productId : 0;
                const qty = num(tr.querySelector('.it-qty').value);
                if (!pid || qty <= 0) return;
                items.push({
                    product_id: pid,
                    qty,
                    unit_price: Math.round(num(tr.querySelector('.it-price').value)),
                    discount: Math.round(num(tr.querySelector('.it-disc').value)),
                });
            });
            const di = document.getElementById('f_issue_date');
            let issue = di.getAttribute('data-date') || '';
            if (!issue && di.value && typeof convertToGregorian === 'function') issue = convertToGregorian(di.value) || '';
            return {
                sup,
                body: {
                    supplier_id: sup ? sup.id : 0,
                    supplier_ref_number: document.getElementById('f_ref').value.trim(),
                    issue_date: issue,
                    note: document.getElementById('f_note').value.trim(),
                    items,
                }
            };
        }

        async function save(goBack) {
            const {
                sup,
                body
            } = collect();
            if (!sup) {
                alertBox('یک تأمین‌کننده از فهرست انتخاب کنید. اگر نیست، با دکمه‌ی + بسازیدش.');
                return;
            }
            if (!body.items.length) {
                alertBox('حداقل یک ردیف با کالا و تعدادِ معتبر لازم است.');
                return;
            }
            alertBox('');
            try {
                let id = PUR_ID;
                if (PUR_ID) await apiSend('PUT', '/inv/purchases/' + PUR_ID, body);
                else {
                    const d = await apiSend('POST', '/inv/purchases', body);
                    id = d.id;
                }
                showToast('ذخیره شد', 'success');
                if (goBack) location.href = '/pages/inv-purchases.php';
                else if (!PUR_ID) location.href = '/pages/inv-purchase-edit.php?id=' + id;
            } catch (e) {
                alertBox(e.message || 'خطا در ذخیره');
            }
        }

        async function loadPaged(path) {
            const out = [];
            let page = 1,
                total = Infinity;
            while (out.length < total) {
                const d = await apiGet(path + '?per=200&page=' + page);
                out.push(...(d.items || []));
                total = d.total || 0;
                if (!d.items || !d.items.length) break;
                page++;
            }
            return out;
        }

        async function reloadSuppliers() {
            try {
                suppliers = await loadPaged('/inv/suppliers');
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
            } catch (e) {}

            await reloadSuppliers();
            supplierPicker = EntityPicker.create({
                container: '#supplierPicker',
                items: supItems(),
                placeholder: 'کد/نام تأمین‌کننده…',
                addTitle: 'افزودن تأمین‌کننده‌ی جدید',
                onAdd: () => QuickAdd.supplier(s => {
                    suppliers.push(s);
                    supplierPicker.updateItems(supItems());
                    supplierPicker.setValue(s.id);
                }),
            });
            try {
                products = await loadPaged('/inv/products');
            } catch (e) {}
            buildProdItems();

            window.addEventListener('focus', async () => {
                await reloadSuppliers();
                if (supplierPicker) supplierPicker.updateItems(supItems());
                try {
                    products = await loadPaged('/inv/products');
                } catch (e) {}
                buildProdItems();
                document.querySelectorAll('#itemsBody tr').forEach(tr => {
                    if (tr._picker) tr._picker.updateItems(prodItems);
                });
            });

            if (PUR_ID) {
                try {
                    const d = await apiGet('/inv/purchases/' + PUR_ID);
                    const pur = d.purchase;
                    if (pur.status !== 'draft') {
                        location.href = '/pages/inv-purchases.php';
                        return;
                    }
                    if (supplierPicker) supplierPicker.setValue(pur.supplier_id);
                    document.getElementById('f_ref').value = pur.supplier_ref_number || '';
                    document.getElementById('f_note').value = pur.note || '';
                    if (pur.issue_date) {
                        const di = document.getElementById('f_issue_date');
                        di.setAttribute('data-date', pur.issue_date);
                        di.value = (typeof convertToJalali === 'function') ? convertToJalali(pur.issue_date) : pur.issue_date;
                    }
                    (pur.items || []).forEach(addRow);
                } catch (e) {
                    alertBox('بارگذاریِ فاکتور ناموفق بود: ' + (e.message || ''));
                }
            }
            if (!document.querySelectorAll('#itemsBody tr').length) {
                addRow();
            }
            recalc();
            if (typeof window.reinitPersianDatepickers === 'function') window.reinitPersianDatepickers();

            document.getElementById('btnAddRow').addEventListener('click', () => { const tr = addRow(); if (tr && tr._picker) tr._picker.focus(); });
            document.getElementById('btnSave').addEventListener('click', () => save(false));
            document.getElementById('btnSaveBack').addEventListener('click', () => save(true));
        }

        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>

</html>
