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

        table.inv-items input {
            width: 100%;
            border: 0;
            background: transparent;
            padding: 4px;
            color: inherit;
        }

        table.inv-items input:focus {
            outline: 2px solid rgba(142, 87, 254, .35);
            border-radius: 4px;
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

        .inv-actions {
            display: flex;
            gap: 10px;
            margin-top: 1.5rem;
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
                    <input type="text" class="form-control" id="f_supplier" list="supList" placeholder="نامِ تأمین‌کننده">
                    <datalist id="supList"></datalist>
                    <div class="form-text"><a href="/pages/inv-suppliers.php" target="_blank">افزودن تأمین‌کننده‌ی جدید</a></div>
                </div>
                <div>
                    <label class="form-label">شماره‌ی فاکتورِ فروشنده</label>
                    <input type="text" class="form-control" id="f_ref">
                </div>
                <div>
                    <label class="form-label">تاریخ</label>
                    <div class="persian-datepicker-wrapper">
                        <input type="text" class="persian-datepicker-input form-control" id="f_issue_date" placeholder="۱۴۰۵/۰۶/۱۱" readonly>
                        <div class="persian-datepicker"></div>
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
            <datalist id="prodList"></datalist>

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
                <button type="button" class="btn btn-primary" id="btnSaveBack">ذخیره و بازگشت</button>
                <button type="button" class="btn btn-outline-primary" id="btnSave">ذخیره‌ی پیش‌نویس</button>
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
            return faDigits(Math.round(n || 0).toLocaleString('en-US'));
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
            prodByName = new Map();
        let suppliers = [],
            supByName = new Map();

        function alertBox(msg, kind = 'danger') {
            document.getElementById('formAlert').innerHTML =
                msg ? `<div class="alert alert-${kind} py-2">${msg}</div>` : '';
        }

        function rowTemplate() {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="idx text-center"></td>
                <td><input class="it-prod" list="prodList" placeholder="انتخاب کالا"></td>
                <td class="col-qty"><input class="it-qty" inputmode="decimal" value="1"></td>
                <td class="col-price"><input class="it-price" inputmode="numeric" value="0"></td>
                <td class="col-disc"><input class="it-disc" inputmode="numeric" value="0"></td>
                <td class="col-stock it-stock small text-muted"></td>
                <td class="col-total it-linetotal">۰</td>
                <td class="col-del"><button type="button" class="btn btn-sm btn-link text-danger p-0 it-del">✕</button></td>`;
            tr.querySelector('.it-prod').addEventListener('input', () => onProdPick(tr));
            tr.querySelectorAll('.it-qty,.it-price,.it-disc').forEach(el => el.addEventListener('input', recalc));
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
                tr.querySelector('.it-qty').value = data.qty ?? 1;
                tr.querySelector('.it-price').value = data.unit_price ?? 0;
                tr.querySelector('.it-disc').value = data.discount ?? 0;
                if (data.product_id) {
                    const p = products.find(x => x.id === data.product_id);
                    if (p) {
                        tr.querySelector('.it-prod').value = p.name;
                        tr.dataset.productId = p.id;
                    }
                }
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

        function onProdPick(tr) {
            const p = prodByName.get(tr.querySelector('.it-prod').value.trim());
            if (p) {
                tr.dataset.productId = p.id;
                const price = tr.querySelector('.it-price');
                if (num(price.value) === 0) price.value = p.unit_price;
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
            const supName = document.getElementById('f_supplier').value.trim();
            const sup = supByName.get(supName);
            const items = [];
            const unresolved = [];
            document.querySelectorAll('#itemsBody tr').forEach((tr, i) => {
                const pid = tr.dataset.productId ? +tr.dataset.productId : 0;
                const typed = tr.querySelector('.it-prod').value.trim();
                const qty = num(tr.querySelector('.it-qty').value);
                if (!pid) {
                    if (typed) unresolved.push({
                        row: i + 1,
                        name: typed
                    });
                    return;
                }
                if (qty <= 0) return;
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
                supName,
                unresolved,
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
                supName,
                unresolved,
                body
            } = collect();
            if (!supName) {
                alertBox('نامِ تأمین‌کننده را وارد کنید.');
                return;
            }
            if (!sup) {
                alertBox('تأمین‌کننده «' + supName + '» در فهرست نیست. از لینکِ بالا ثبتش کنید و دوباره انتخاب کنید.');
                return;
            }
            if (unresolved.length) {
                alertBox('این کالاها در «کاتالوگ کالا» نیستند و باید اول آن‌جا ثبت شوند: ' +
                    unresolved.map(u => 'ردیف ' + faDigits(u.row) + ' («' + u.name + '»)').join('، ') +
                    '. در فاکتورِ خرید فقط کالای موجود در کاتالوگ قابل انتخاب است.');
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

        async function loadPaged(path, into) {
            let page = 1,
                total = Infinity;
            while (into.length < total) {
                const d = await apiGet(path + '?per=200&page=' + page);
                into.push(...(d.items || []));
                total = d.total || 0;
                if (!d.items || !d.items.length) break;
                page++;
            }
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

            try {
                await loadPaged('/inv/suppliers', suppliers);
            } catch (e) {}
            const sl = document.getElementById('supList');
            suppliers.forEach(sp => {
                supByName.set(sp.name, sp);
                const o = document.createElement('option');
                o.value = sp.name;
                sl.appendChild(o);
            });

            try {
                await loadPaged('/inv/products', products);
            } catch (e) {}
            const pl = document.getElementById('prodList');
            products.forEach(p => {
                prodByName.set(p.name, p);
                const o = document.createElement('option');
                o.value = p.name;
                o.label = (p.code ? p.code + ' — ' : '') + 'موجودی ' + faDigits(p.stock);
                pl.appendChild(o);
            });

            if (PUR_ID) {
                try {
                    const d = await apiGet('/inv/purchases/' + PUR_ID);
                    const pur = d.purchase;
                    if (pur.status !== 'draft') {
                        location.href = '/pages/inv-purchases.php';
                        return;
                    }
                    const sp = suppliers.find(x => x.id === pur.supplier_id);
                    document.getElementById('f_supplier').value = sp ? sp.name : ('#' + pur.supplier_id);
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
                addRow();
            }
            recalc();
            if (typeof window.reinitPersianDatepickers === 'function') window.reinitPersianDatepickers();

            document.getElementById('btnAddRow').addEventListener('click', () => addRow());
            document.getElementById('btnSave').addEventListener('click', () => save(false));
            document.getElementById('btnSaveBack').addEventListener('click', () => save(true));
        }

        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>

</html>
