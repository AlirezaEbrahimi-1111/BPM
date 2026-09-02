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
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات فاکتور - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">

    <style>
        .set-wrap {
            max-width: 820px;
            margin: 0 auto;
        }

        .set-card {
            border: 1px solid var(--border-soft, #e5e7eb);
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 18px;
            background: var(--surface, #fff);
        }

        .set-card h2 {
            font-size: 1rem;
            margin: 0 0 14px;
            color: #6d3ed6;
        }

        :root[data-theme="dark"] .set-card h2 {
            color: #b79bff;
        }

        .set-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container" style="margin-top:70px">
        <div class="set-wrap">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h1 style="font-size:1.3rem;margin:0"><i class="bi bi-gear ms-2"></i>تنظیمات فاکتور</h1>
                <a href="/pages/inv-invoices.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right ms-1"></i> فهرست فاکتورها</a>
            </div>

            <div id="alertBox"></div>

            <div class="set-card">
                <h2>سربرگِ فروشنده (روی چاپِ فاکتور)</h2>
                <div class="set-grid">
                    <div>
                        <label class="form-label">نام شرکت</label>
                        <input type="text" class="form-control" id="s_company_name">
                    </div>
                    <div>
                        <label class="form-label">شناسه ملی</label>
                        <input type="text" class="form-control" id="s_national_id">
                    </div>
                    <div>
                        <label class="form-label">کد اقتصادی</label>
                        <input type="text" class="form-control" id="s_economic_code">
                    </div>
                    <div>
                        <label class="form-label">شماره ثبت</label>
                        <input type="text" class="form-control" id="s_reg_number">
                    </div>
                    <div>
                        <label class="form-label">کد شعبه <span class="text-muted small">(سامانه مودیان — بعداً)</span></label>
                        <input type="text" class="form-control" id="s_branch_code">
                    </div>
                    <div>
                        <label class="form-label">استان</label>
                        <input type="text" class="form-control" id="s_province">
                    </div>
                    <div>
                        <label class="form-label">شهرستان</label>
                        <input type="text" class="form-control" id="s_shahrestan">
                    </div>
                    <div>
                        <label class="form-label">شهر</label>
                        <input type="text" class="form-control" id="s_city">
                    </div>
                    <div>
                        <label class="form-label">کدپستی</label>
                        <input type="text" class="form-control" id="s_postal_code">
                    </div>
                    <div>
                        <label class="form-label">تلفن / نمابر</label>
                        <input type="text" class="form-control" id="s_phone">
                    </div>
                    <div style="grid-column:1/-1">
                        <label class="form-label">نشانی کامل</label>
                        <textarea class="form-control" id="s_address" rows="2"></textarea>
                    </div>
                    <div>
                        <label class="form-label">شماره شبا</label>
                        <input type="text" class="form-control" id="s_iban" placeholder="IR...">
                    </div>
                    <div>
                        <label class="form-label">شماره کارت</label>
                        <input type="text" class="form-control" id="s_card_number">
                    </div>
                    <div>
                        <label class="form-label">نام بانک</label>
                        <input type="text" class="form-control" id="s_bank_name">
                    </div>
                </div>
            </div>

            <div class="set-card">
                <h2>پارامترهای فاکتور</h2>
                <div class="set-grid">
                    <div>
                        <label class="form-label">نرخِ مالیات بر ارزش افزوده (٪)</label>
                        <input type="number" step="0.01" min="0" max="100" class="form-control" id="s_vat_rate">
                    </div>
                    <div>
                        <label class="form-label">پیشوندِ شماره‌ی فاکتور</label>
                        <input type="text" class="form-control" id="s_number_prefix" placeholder="مثلاً F-">
                        <div class="form-text">شماره = پیشوند + سالِ شمسی + «/» + شماره‌ی ترتیبی</div>
                    </div>
                    <div style="grid-column:1/-1">
                        <label class="form-label">یادداشتِ پاورقیِ فاکتور</label>
                        <textarea class="form-control" id="s_footer_note" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-primary" id="btnSave"><i class="bi bi-check2 ms-1"></i> ذخیره‌ی تنظیمات</button>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        const API = '/crm/api';

        function tok() {
            return localStorage.getItem('auth_token');
        }

        function ahj() {
            return {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + tok()
            };
        }

        function alertBox(msg, kind = 'danger') {
            document.getElementById('alertBox').innerHTML =
                msg ? `<div class="alert alert-${kind} py-2">${msg}</div>` : '';
        }

        function val(id) {
            return document.getElementById(id).value.trim();
        }

        async function load() {
            if (!tok()) {
                location.href = '../index.php';
                return;
            }
            try {
                const r = await fetch(API + '/inv/settings', {
                    headers: {
                        'Authorization': 'Bearer ' + tok()
                    }
                });
                const d = await r.json();
                if (!r.ok) throw new Error(d.message || ('خطای ' + r.status));
                const se = d.seller || {};
                document.getElementById('s_company_name').value = se.company_name || '';
                document.getElementById('s_national_id').value = se.national_id || '';
                document.getElementById('s_economic_code').value = se.economic_code || '';
                document.getElementById('s_reg_number').value = se.reg_number || '';
                document.getElementById('s_branch_code').value = se.branch_code || '';
                document.getElementById('s_province').value = se.province || '';
                document.getElementById('s_shahrestan').value = se.shahrestan || '';
                document.getElementById('s_city').value = se.city || '';
                document.getElementById('s_postal_code').value = se.postal_code || '';
                document.getElementById('s_phone').value = se.phone || '';
                document.getElementById('s_address').value = se.address || '';
                document.getElementById('s_iban').value = se.iban || '';
                document.getElementById('s_card_number').value = se.card_number || '';
                document.getElementById('s_bank_name').value = se.bank_name || '';
                document.getElementById('s_vat_rate').value = d.vat_rate ?? 10;
                document.getElementById('s_number_prefix').value = d.number_prefix || '';
                document.getElementById('s_footer_note').value = d.invoice_footer_note || '';
            } catch (e) {
                alertBox('بارگذاری ناموفق بود: ' + (e.message || ''));
            }
        }

        async function save() {
            const rate = parseFloat(val('s_vat_rate'));
            if (isNaN(rate) || rate < 0 || rate > 100) {
                alertBox('نرخِ مالیات باید عددی بینِ ۰ تا ۱۰۰ باشد.');
                return;
            }
            const body = {
                vat_rate: rate,
                currency: 'IRR',
                number_prefix: val('s_number_prefix'),
                invoice_footer_note: val('s_footer_note'),
                seller: {
                    company_name: val('s_company_name'),
                    national_id: val('s_national_id'),
                    economic_code: val('s_economic_code'),
                    reg_number: val('s_reg_number'),
                    branch_code: val('s_branch_code'),
                    province: val('s_province'),
                    shahrestan: val('s_shahrestan'),
                    city: val('s_city'),
                    address: val('s_address'),
                    postal_code: val('s_postal_code'),
                    phone: val('s_phone'),
                    iban: val('s_iban'),
                    card_number: val('s_card_number'),
                    bank_name: val('s_bank_name'),
                }
            };
            try {
                const r = await fetch(API + '/inv/settings', {
                    method: 'PUT',
                    headers: ahj(),
                    body: JSON.stringify(body)
                });
                const d = await r.json();
                if (!r.ok || d.success === false) throw new Error(d.message || ('خطای ' + r.status));
                alertBox('');
                showToast('تنظیمات ذخیره شد', 'success');
            } catch (e) {
                alertBox(e.message || 'خطا در ذخیره');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('btnSave').addEventListener('click', save);
            load();
        });
    </script>
</body>

</html>
