<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/version.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-access.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-prices.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/JalaliHelper.php';

$db = (new Database())->getConnection();
$auth = new Auth($db);
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);
if (!$user_id) {
    header('Location: ../index.php');
    exit;
}
if (!in_array((int) $user_id, getSuperAdminIds(), true)) {
    header('Location: dashboard-manager.php');
    exit;
}

$orgId = (int) ($_GET['org_id'] ?? 0);
$orgStmt = $db->prepare("SELECT id, name FROM organizations WHERE id = ?");
$orgStmt->execute([$orgId]);
$org = $orgStmt->fetch(PDO::FETCH_ASSOC);
if (!$org) {
    header('Location: superAdmin.php');
    exit;
}

$st = planState($db, $orgId);
$usersStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE organization_id = ? AND COALESCE(is_deleted, 0) = 0");
$usersStmt->execute([$orgId]);
$userCount = (int) $usersStmt->fetchColumn();

$planLabels = ['free' => 'رایگان', 'gold' => 'طلایی'];
$allowedUsers = ['free' => [1], 'gold' => [5, 10, 20, 40]];

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>پلن و پرداخت سازمان</title>
  <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
  <link rel="stylesheet" href="<?= asset('../assets/css/custom.css') ?>">
  <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
</head>
<body>
<?php include 'header.php'; ?>

<div class="overview-container">
    <div class="page-header">
        <div class="page-header-left">
            <h1><i class="bi bi-credit-card"></i> پلن و پرداخت سازمان</h1>
            <p><?= htmlspecialchars($org['name']) ?></p>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="superAdmin.php"><i class="bi bi-arrow-right"></i> بازگشت به سازمان‌ها</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card-block p-3" style="background:var(--surface);border:1px solid var(--border);border-radius:12px;">
                <h2 style="font-size:1rem;margin-bottom:12px;">ثبت پلن جدید</h2>
                <form id="planForm" onsubmit="return false;">
                    <input type="hidden" id="orgId" value="<?= (int) $org['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label" for="planSelect">پلن</label>
                        <select class="form-select" id="planSelect">
                            <?php foreach ($planLabels as $key => $label) : ?>
                                <option value="<?= $key ?>" <?= $st['plan'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="usersSelect">تعداد کاربر</label>
                        <select class="form-select" id="usersSelect"></select>
                    </div>

                    <div class="mb-3" id="expiryWrap">
                        <label class="form-label">تاریخ انقضا</label>
                        <div class="persian-datepicker-wrapper">
                            <input type="text" class="persian-datepicker-input form-control" id="expiryInput"
                                placeholder="انتخاب تاریخ انقضا..." readonly>
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

                    <button type="button" class="btn btn-primary" id="savePlanBtn"><i class="bi bi-check2"></i> ثبت پلن</button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-block p-3" style="background:var(--surface);border:1px solid var(--border);border-radius:12px;margin-bottom:12px;">
                <h2 style="font-size:1rem;margin-bottom:10px;">وضعیت فعلی</h2>
                <div>پلن: <b><?= $planLabels[$st['plan']] ?? $st['plan'] ?></b></div>
                <div>سقف کاربر: <b><?= JalaliHelper::Persian($st['plan_users']) ?></b> (فعلی: <?= JalaliHelper::Persian($userCount) ?>)</div>
                <div>انقضا: <b><?= $st['plan_expires_at'] ? JalaliHelper::formatJalaliDate($st['plan_expires_at']) : '—' ?></b></div>
                <div>تست وب تا: <b><?= $st['web_trial_ends_at'] ? JalaliHelper::formatJalaliDate($st['web_trial_ends_at']) : '—' ?></b></div>
                <div>دسترسی وب: <b><?= $st['web_access'] ? 'دارد' : 'ندارد' ?></b></div>
            </div>
            <div class="card-block p-3" style="background:var(--surface);border:1px dashed var(--border);border-radius:12px;">
                <h2 style="font-size:1rem;margin-bottom:8px;">پرداخت آنلاین</h2>
                <p style="color:var(--text-2);font-size:.9rem;margin:0;">درگاه پرداخت هنوز متصل نشده است. فعلاً پلن‌ها از طریق همین فرم و پس از تماس با مشتری ثبت می‌شوند.</p>
            </div>
        </div>
    </div>
</div>

<script>
const PLAN_USERS = <?= json_encode($allowedUsers) ?>;
const CURRENT = { plan: <?= json_encode($st['plan']) ?>, users: <?= (int) $st['plan_users'] ?> };

function toFa(n) { return String(n).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }

const planSel = document.getElementById('planSelect');
const usersSel = document.getElementById('usersSelect');
const expiryWrap = document.getElementById('expiryWrap');
const expiryInput = document.getElementById('expiryInput');

function fillUsers() {
    const plan = planSel.value;
    const list = PLAN_USERS[plan] || [];
    usersSel.innerHTML = list.map(n => `<option value="${n}">${toFa(n)}</option>`).join('');
    if (plan === CURRENT.plan && list.includes(CURRENT.users)) usersSel.value = String(CURRENT.users);
    expiryWrap.style.display = plan === 'free' ? 'none' : '';
}
planSel.addEventListener('change', fillUsers);
fillUsers();

document.getElementById('savePlanBtn').addEventListener('click', async () => {
    const plan = planSel.value;
    const payload = {
        org_id: parseInt(document.getElementById('orgId').value, 10),
        plan: plan,
        plan_users: parseInt(usersSel.value, 10),
        plan_expires_at: plan === 'free' ? null : (expiryInput.getAttribute('data-date') || '')
    };
    if (plan !== 'free' && !payload.plan_expires_at) {
        showToast('تاریخ انقضا را انتخاب کنید', 'warning');
        return;
    }
    try {
        const res = await fetch('../api/organization/set-plan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => { window.location.href = 'superAdmin.php'; }, 900);
        } else {
            showToast(data.message || 'ثبت پلن انجام نشد', 'warning');
        }
    } catch (e) {
        showToast('خطا در ارتباط با سرور', 'warning');
    }
});
</script>
<script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('../assets/js/persian-datepicker.js') ?>"></script>
<?php include 'footer.php'; ?>
</body>
</html>
