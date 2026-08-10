<?php

if (session_status() === PHP_SESSION_NONE) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/JalaliHelper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/version.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

if (!isset($db)) {
    $database = new Database();
    $db = $database->getConnection();
}
$auth = new Auth($db);

// ── احراز هویت (هماهنگ با payroll-report) ──
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id)                            $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    header('Location: ../index.php');
    exit;
}

// 🔒 فقط مدیر کل (superadmin)
if (!in_array((int)$user_id, getSuperAdminIds(), true)) {
    header('Location: dashboard-manager.php');
    exit;
}

$stmt = $db->query("
    SELECT
      o.id, o.name, o.is_active, o.created_at,
      s.plan_type, s.end_date, s.is_active AS sub_active, s.max_users,
      COUNT(u.id) AS user_count,
      MAX(u.last_login) AS last_login
    FROM organizations o
    LEFT JOIN subscriptions s ON s.organization_id = o.id AND s.is_active = 1
    LEFT JOIN users u ON u.organization_id = o.id AND u.is_active = 1
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$orgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total       = count($orgs);
$active_subs = array_filter($orgs, fn($o) => $o['sub_active'] && strtotime($o['end_date']) > time());
$expired     = $total - count($active_subs);

$labels = ['trial' => 'آزمایشی', 'monthly' => 'ماهانه', 'yearly' => 'سالانه'];
$gridData = [];
foreach ($orgs as $org) {
    $is_expired = !$org['sub_active'] || ($org['end_date'] && strtotime($org['end_date']) < time());
    $days_left  = $org['end_date'] ? max(0, (int)ceil((strtotime($org['end_date']) - time()) / 86400)) : 0;
    $gridData[] = [
        'id'         => (int)$org['id'],
        'name'       => $org['name'],
        'first_char' => mb_substr($org['name'], 0, 1),
        'user_count' => (int)$org['user_count'],
        'max_users'  => (int)($org['max_users'] ?? 0),
        'plan_label' => $labels[$org['plan_type']] ?? '-',
        'created'    => JalaliHelper::formatJalaliDate(substr((string)$org['created_at'], 0, 10)),
        'end'        => $org['end_date'] ? JalaliHelper::formatJalaliDate($org['end_date']) : '',
        'days_left'  => $days_left,
        'is_expired' => $is_expired ? 1 : 0,
        'is_active'  => (int)$org['is_active'],
        'last_login' => $org['last_login']
            ? JalaliHelper::formatJalaliDate(substr((string)$org['last_login'], 0, 10)) . ' - ' . substr((string)$org['last_login'], 11, 5)
            : 'هرگز',
    ];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>پنل مدیریت سازمان‌ها</title>
  <script src="<?= asset('../assets/js/ag-grid-community.min.js') ?>"></script>
  <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
  <link rel="stylesheet" href="<?= asset('../assets/css/custom.css') ?>">
  <style>
    body { font-family: 'Vazirmatn', sans-serif; background: #F7F8FC; }

    .admin-wrap { max-width: 1200px; margin: 90px auto 40px; padding: 0 16px; }

    .admin-toolbar {
      display: flex; flex-wrap: wrap; gap: 12px;
      align-items: center; justify-content: space-between; margin-bottom: 18px;
    }
    .admin-toolbar h4 { margin: 0; color: #744CA4; font-weight: 700; }
    .admin-toolbar .sub { font-size: 13px; color: #718096; margin-top: 2px; }

    .admin-search { position: relative; }
    .admin-search input {
      border: 1px solid rgba(116,76,164,.2); border-radius: 10px;
      padding: 9px 14px 9px 38px; font-size: 13px; width: 260px; outline: none;
      font-family: inherit; background: #fff; color: #2D3748; transition: border .2s;
    }
    .admin-search input:focus { border-color: #744CA4; }
    .admin-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #A0AEC0; }

    /* کارت‌های آماری (سبک pcard از payroll-report) */
    .admin-cards {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 14px; margin-bottom: 18px;
    }
    .pcard {
      background: #fff; border-radius: 14px; padding: 18px 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,.04);
      display: flex; align-items: center; gap: 14px;
    }
    .pcard .ic { width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .pcard .lbl { font-size: 12.5px; color: #718096; font-weight: 600; }
    .pcard .val { font-size: 24px; font-weight: 800; line-height: 1.2; }

    #orgGrid { width: 100%; height: 600px; }
    .ag-theme-alpine {
      --ag-font-family: 'Vazirmatn', sans-serif;
      --ag-font-size: 13px;
      --ag-header-height: 46px;
      --ag-header-background-color: #FBFBFD;
      --ag-header-foreground-color: #718096;
      --ag-row-hover-color: #FAFAFB;
      --ag-border-color: #EDF0F4;
      --ag-cell-horizontal-padding: 18px;
      border-radius: 14px;
      box-shadow: 0 2px 10px rgba(0,0,0,.04);
      overflow: hidden;
    }
    .ag-theme-alpine .ag-header-cell-text { font-weight: 700; }

    /* محتوای سلول‌ها */
    .og { display: flex; align-items: center; gap: 11px; }
    .og .ava { width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0; background: #EDE9FE; color: #744CA4; font-size: 14px; font-weight: 800; display: flex; align-items: center; justify-content: center; }
    .og .nm { font-weight: 700; font-size: 13.5px; line-height: 1.3; color: #2D3748; }
    .og .mt { font-size: 11px; color: #A0AEC0; line-height: 1.2; margin-top: 1px; }

    .pill { display: inline-flex; align-items: center; gap: 4px; padding: 3px 11px; border-radius: 20px; font-size: 11.5px; font-weight: 700; }
    .pill.ok   { background: #ffffff; color: #027A48; }
    .pill.no   { background: #ffffff; color: #B42318; }
    .pill.plan { background: #ffffff; color: #744CA4; }

    .bar { width: 84px; height: 5px; background: #EDF0F4; border-radius: 4px; overflow: hidden; margin-top: 5px; }
    .bar > i { display: block; height: 100%; border-radius: 4px; background: #12B76A; }
    .bar > i.warn { background: #F79009; }
    .bar > i.crit { background: #F04438; }

    .act { width: 34px; height: 34px; border-radius: 9px; border: 1px solid #E2E8F0; background: #fff; display: inline-flex; align-items: center; justify-content: center; color: #718096; font-size: 14px; cursor: pointer; transition: all .15s; margin-left: 4px; }
    .act:hover { border-color: #744CA4; color: #744CA4; background: #F4F0FB; }
    .act.danger:hover { border-color: #F04438; color: #F04438; background: #FEE4E2; }

    /* مودال */
    .ov { position: fixed; inset: 0; background: rgba(45,55,72,.45); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; z-index: 1200; opacity: 0; pointer-events: none; transition: opacity .2s; }
    .ov.show { opacity: 1; pointer-events: all; }
    .md { background: #fff; border-radius: 16px; padding: 26px; width: 390px; max-width: 92vw; box-shadow: 0 14px 44px rgba(45,55,72,.2); transform: translateY(12px); transition: transform .2s; }
    .ov.show .md { transform: translateY(0); }
    .md h3 { font-size: 17px; font-weight: 800; margin: 0 0 6px; color: #2D3748; }
    .md .sb { font-size: 13px; color: #718096; margin: 0 0 18px; }
    .md label { font-size: 12.5px; color: #718096; display: block; margin-bottom: 6px; }
    .md input { width: 100%; border: 1px solid #E2E8F0; border-radius: 9px; padding: 11px 14px; font-size: 14px; outline: none; font-family: inherit; transition: border .2s; }
    .md input:focus { border-color: #744CA4; }
    .md-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 22px; direction: ltr; }

    :root[data-theme="dark"] .md { background: var(--surface); }
    :root[data-theme="dark"] .md h3 { color: var(--text-strong); }
    :root[data-theme="dark"] .md .sb,
    :root[data-theme="dark"] .md label { color: var(--text-muted); }
    :root[data-theme="dark"] .md input {
      background: var(--surface);
      color: var(--text-strong);
      border-color: var(--border-soft);
    }

    @media (max-width: 768px) {
      .admin-wrap { margin-top: 84px; }
      .admin-search input { width: 100%; }
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="admin-wrap">

  <div class="admin-toolbar">
    <div>
      <h4><i class="bi bi-buildings ms-2"></i>سازمان‌ها</h4>
      <div class="sub">مدیریت سازمان‌ها و اشتراک‌ها</div>
    </div>
    <div class="admin-search">
      <input type="text" id="orgSearch" placeholder="جستجو...">
      <i class="bi bi-search"></i>
    </div>
  </div>

  <!-- آمار -->
  <div class="admin-cards">
    <div class="pcard">
      <div class="ic" style="background:#FEE4E2;color:#F04438"><i class="bi bi-x-circle"></i></div>
      <div>
        <div class="val"><?= JalaliHelper::Persian($expired) ?></div>
        <div class="lbl">منقضی‌شده</div>
      </div>
    </div>
    <div class="pcard">
      <div class="ic" style="background:#D1FAE5;color:#12B76A"><i class="bi bi-check-circle"></i></div>
      <div>
        <div class="val"><?= JalaliHelper::Persian(count($active_subs)) ?></div>
        <div class="lbl">اشتراک فعال</div>
      </div>
    </div>
    <div class="pcard">
      <div class="ic" style="background:#EDE9FE;color:#744CA4"><i class="bi bi-collection"></i></div>
      <div>
        <div class="val"><?= JalaliHelper::Persian($total) ?></div>
        <div class="lbl">کل سازمان‌ها</div>
      </div>
    </div>
  </div>

  <!-- جدول -->
  <div id="orgGrid" class="ag-theme-alpine"></div>

</div>

<!-- مودال تمدید -->
<div class="ov" id="ovExtend">
  <div class="md">
    <h3>تمدید اشتراک</h3>
    <p class="sb" id="exName">نام سازمان</p>
    <label>تعداد ماه</label>
    <input type="number" id="exMonths" min="1" max="24" value="1">
    <div class="md-actions">
      <button class="btn btn-secondary" onclick="closeExtend()">انصراف</button>
      <button class="btn btn-primary" onclick="confirmExtend()">تمدید</button>
    </div>
  </div>
</div>

<!-- مودال سقف کاربران -->
<div class="ov" id="ovLimit">
  <div class="md">
    <h3>تغییر سقف کاربران</h3>
    <p class="sb" id="lmName">نام سازمان</p>
    <label>حداکثر تعداد کاربر</label>
    <input type="number" id="lmInput" min="1" max="100000" value="50">
    <div class="md-actions">
      <button class="btn btn-secondary" onclick="closeLimit()">انصراف</button>
      <button class="btn btn-primary" onclick="confirmLimit()">ذخیره</button>
    </div>
  </div>
</div>

<script>
const ORGS = <?= json_encode($gridData, JSON_UNESCAPED_UNICODE) ?>;
const byId = {};
ORGS.forEach(o => byId[o.id] = o);

const faNum = s => String(s).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
function esc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
let curId = null;

function cOrg(p) {
  const d = p.data;
  return `<div class="og">
      <div class="ava">${esc(d.id)}</div>
      <div>
        <div class="nm">${esc(d.name)}</div>
        <div class="mt"></div>
      </div>
    </div>`;
}
function cUsers(p) {
  const d = p.data;
  return `<span style="font-weight:700;color:#2D3748">${faNum(d.user_count)}</span>
          <span style="color:#A0AEC0"> / ${faNum(d.max_users || '∞')} نفر</span>`;
}
function cPlan(p) { return `<span class="pill plan">${esc(p.data.plan_label)}</span>`; }
function cExpiry(p) {
  const d = p.data;
  if (!d.is_expired && d.end) {
    const cls = d.days_left > 7 ? '' : (d.days_left > 3 ? 'warn' : 'crit');
    return `<div style="font-size:13px;color:#2D3748">${faNum(d.days_left)} روز مانده</div>
            <div class="bar"><i class="${cls}" style="width:${Math.min(100, (d.days_left / 14) * 100)}%"></i></div>`;
  }
  if (d.is_expired) return `<span style="font-size:12.5px;color:#F04438">منقضی شده</span>`;
  return '—';
}
function cStatus(p) {
  return p.data.is_expired
    ? `<span class="pill no">منقضی</span>`
    : `<span class="pill ok">فعال</span>`;
}
function cLastLogin(p) {
  const v = p.data.last_login;
  const muted = (v === 'هرگز');
  return `<span style="font-size:12.5px;color:${muted ? '#A0AEC0' : '#2D3748'}">${faNum(esc(v))}</span>`;
}
function cActions(p) {
  const d = p.data;
  return `
    <button class="act" title="تمدید اشتراک" onclick="openExtend(${d.id})"><i class="bi bi-arrow-clockwise"></i></button>
    <button class="act" title="سقف کاربران" onclick="openLimit(${d.id})"><i class="bi bi-people"></i></button>
    <button class="act danger" title="${d.is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی'}" onclick="toggleOrg(${d.id})">
      <i class="bi bi-${d.is_active ? 'slash-circle' : 'check-circle'}"></i>
    </button>`;
}

const gridApi = agGrid.createGrid(document.getElementById('orgGrid'), {
  enableRtl: true,
  rowHeight: 48,
  defaultColDef: { sortable: true, resizable: true, filter: false },
  columnDefs: [
    { headerName: 'سازمان', flex: 2, minWidth: 220, cellRenderer: cOrg, valueGetter: p => p.data.name, getQuickFilterText: p => p.data.name + ' ' + p.data.id },
    { headerName: 'کاربران', width: 140, cellRenderer: cUsers, valueGetter: p => p.data.user_count, getQuickFilterText: () => '' },
    { headerName: 'پلن', width: 110, cellRenderer: cPlan, valueGetter: p => p.data.plan_label, getQuickFilterText: () => '' },
    { headerName: 'انقضا', width: 150, cellRenderer: cExpiry, valueGetter: p => (p.data.is_expired ? -1 : p.data.days_left), getQuickFilterText: () => '' },
    { headerName: 'وضعیت', width: 120, cellRenderer: cStatus, valueGetter: p => p.data.is_active, getQuickFilterText: () => '' },
    { headerName: 'آخرین ورود کاربر', width: 185, cellRenderer: cLastLogin, valueGetter: p => p.data.last_login, getQuickFilterText: () => '' },
    { headerName: 'عملیات', width: 160, cellRenderer: cActions, sortable: false, getQuickFilterText: () => '' },
  ],
  rowData: ORGS,
  overlayNoRowsTemplate: '<div style="padding:2rem;color:#718096;font-weight:600;">سازمانی یافت نشد</div>'
});

document.getElementById('orgSearch').addEventListener('input', function () {
  gridApi.setGridOption('quickFilterText', this.value);
});

/* تمدید */
function openExtend(id) {
  const o = byId[id]; curId = id;
  document.getElementById('exName').textContent = o.name;
  document.getElementById('exMonths').value = 1;
  document.getElementById('ovExtend').classList.add('show');
}
function closeExtend() { document.getElementById('ovExtend').classList.remove('show'); }
document.getElementById('ovExtend').addEventListener('click', e => { if (e.target.id === 'ovExtend') closeExtend(); });

async function confirmExtend() {
  const months = parseInt(document.getElementById('exMonths').value);
  if (!months || months < 1) return;
  closeExtend();
  try {
    const res = await fetch('/api/organization/extend-subscription.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ org_id: curId, months })
    });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); }
    catch (e) { console.error('Raw:', text); showToast('خطا در پردازش پاسخ سرور', 'error'); return; }
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1200);
  } catch (err) { console.error(err); showToast('خطا در ارتباط با سرور', 'error'); }
}

/* سقف کاربران */
function openLimit(id) {
  const o = byId[id]; curId = id;
  document.getElementById('lmName').textContent = o.name + ' — فعلی: ' + (o.max_users ? faNum(o.max_users) : '—') + ' نفر';
  document.getElementById('lmInput').value = o.max_users || 50;
  document.getElementById('ovLimit').classList.add('show');
}
function closeLimit() { document.getElementById('ovLimit').classList.remove('show'); }
document.getElementById('ovLimit').addEventListener('click', e => { if (e.target.id === 'ovLimit') closeLimit(); });

async function confirmLimit() {
  const max = parseInt(document.getElementById('lmInput').value);
  if (!max || max < 1) return;
  closeLimit();
  try {
    const res = await fetch('/api/organization/set-user-limit.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ org_id: curId, max_users: max })
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1200);
  } catch (err) { console.error(err); showToast('خطا در ارتباط با سرور', 'error'); }
}

/* فعال/غیرفعال */
function toggleOrg(id) {
  const o = byId[id];
  const msg = o.is_active ? 'این سازمان غیرفعال شود؟' : 'این سازمان فعال شود؟';
  uiConfirm(msg, async function () {
    const res = await fetch('/admin/api/toggle-organization.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ org_id: id, status: o.is_active ? 0 : 1 })
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1200);
  }, { danger: !!o.is_active, yesText: o.is_active ? 'بله، غیرفعال کن' : 'بله، فعال کن', noText: 'انصراف' });
}
</script>
<script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
<?php include 'footer.php'; ?>
</body>
</html>