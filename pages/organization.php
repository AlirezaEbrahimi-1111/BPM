<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';
if (!hasPermission($__me, 'view_org_settings')) {
    header('Location: dashboard-manager.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>مشخصات سازمان</title>
  <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
  <script src="<?= asset('../assets/js/config.js') ?>"></script>
  <link rel="stylesheet" href="<?= asset('../assets/css/custom.css') ?>">
  <link rel="stylesheet" href="<?= asset('../assets/css/responsive/dashboard-responsive.css') ?>">
  <style>
    /* کلاس‌های یکتا با پیشوند co- تا با custom.css تداخل نکنند */
    body { font-family: 'Vazirmatn', sans-serif; background: #F7F8FC; }

    .co-wrap { max-width: 1100px; margin: 90px auto 40px; padding: 0 16px; }

    .co-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
    .co-head h4 { margin: 0; color: #8e57fe; font-weight: 700; }
    .co-head .sub { font-size: 13px; color: #718096; margin-top: 2px; }

    .co-loading, .co-error { text-align: center; padding: 60px 16px; color: #718096; }
    .co-spin { width: 36px; height: 36px; border: 3px solid #e9e9e9; border-top-color: #8e57fe; border-radius: 50%; animation: cospin .8s linear infinite; margin: 0 auto 14px; }
    @keyframes cospin { to { transform: rotate(360deg); } }

    /* هدر سازمان */
    .co-org { display: flex; align-items: center; gap: 18px; background: #fff; border-radius: 14px; padding: 22px; box-shadow: 0 2px 10px rgba(0,0,0,.04); margin-bottom: 18px; }
    .co-logo { width: 64px; height: 64px; border-radius: 16px; background: rgba(142, 87, 254, 0.12); color: #8e57fe; display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 700; flex-shrink: 0; overflow: hidden; }
    .co-logo img { width: 100%; height: 100%; object-fit: cover; }
    .co-org .nm { font-size: 20px; font-weight: 700; color: #2D3748; }
    .co-org .mt { font-size: 13px; color: #718096; margin-top: 5px; display: flex; gap: 16px; flex-wrap: wrap; }

    /* کارت‌های آماری (سبک pcard) */
    .co-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 18px; }
    .co-pcard { background: #fff; border-radius: 14px; padding: 18px 20px; box-shadow: 0 2px 10px rgba(0,0,0,.04); display: flex; align-items: center; gap: 14px; }
    .co-pcard .ic { width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .co-pcard .val { font-size: 24px; font-weight: 800; line-height: 1.1; }
    .co-pcard .lbl { font-size: 12.5px; color: #718096; font-weight: 600; margin-top: 3px; }

    /* کارت بخش */
    .co-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,.04); margin-bottom: 18px; overflow: hidden; }
    .co-card-h { padding: 16px 22px; border-bottom: 1px solid #e9e9e9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
    .co-card-h h6 { font-size: 15px; font-weight: 700; margin: 0; color: #2D3748; }
    .co-card-b { padding: 22px; }

    /* اشتراک */
    .co-sub-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 18px; }
    .co-sub-item .l { font-size: 12px; color: #718096; }
    .co-sub-item .v { font-size: 15px; font-weight: 700; margin-top: 3px; color: #2D3748; }
    .co-badge { display: inline-flex; align-items: center; gap: 4px; padding: 7px 10px; border-radius: var(--badge-radius); font-size: 11.5px; font-weight: var(--badge-font-weight); }
    .co-badge.ok { background: rgba(27, 123, 57, 0.12); color: #1b7b39; }
    .co-badge.no { background: #FEE4E2; color: #B42318; }
    .co-badge.gray { background: #e9e9e9; color: #718096; }
    .co-alert { margin-top: 16px; padding: 12px 16px; border-radius: 9px; font-size: 13px; display: flex; align-items: center; gap: 8px; }
    .co-alert.warn { background: #FEF3C7; color: #B54708; }
    .co-alert.crit { background: #FEE4E2; color: #B42318; }

    /* لوگو */
    .co-logo-box { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; padding: 16px; background: #e9e9e9; border-radius: 10px; border: 1px dashed #e9e9e9; }
    .co-logo-prev { width: 80px; height: 80px; border-radius: 10px; object-fit: contain; background: #fff; border: 1px solid #e9e9e9; padding: 4px; }
    .co-logo-ph { width: 80px; height: 80px; border-radius: 10px; background: #e9e9e9; border: 1px solid #e9e9e9; display: flex; align-items: center; justify-content: center; color: #A0AEC0; font-size: 30px; }
    .co-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .co-form-grid .full { grid-column: 1 / -1; }

    /* جدول پرسنل */
    .co-tbl { width: 100%; border-collapse: collapse; }
    .co-tbl thead th { padding: 11px 20px; font-size: 11.5px; font-weight: 700; color: #718096; background: #e9e9e9; border-bottom: 1px solid #e9e9e9; text-align: right; white-space: nowrap; }
    .co-tbl tbody tr { border-bottom: 1px solid #e9e9e9; }
    .co-tbl tbody tr:last-child { border-bottom: none; }
    .co-tbl tbody td { padding: 13px 20px; font-size: 13.5px; color: #2D3748; }

    .co-search { position: relative; }
    .co-search input { border: 1px solid #e9e9e9; border-radius: 9px; padding: 8px 12px 8px 36px; font-size: 13px; outline: none; width: 220px; background: #fff; font-family: inherit; }
    .co-search input:focus { border-color: #8e57fe; }
    .co-search i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #A0AEC0; }

    /* مودال */
    .co-ov { position: fixed; inset: 0; background: rgba(45,55,72,.45); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; z-index: 1200; opacity: 0; pointer-events: none; transition: opacity .2s; }
    .co-ov.show { opacity: 1; pointer-events: all; }
    .co-md { background: #fff; border-radius: 16px; padding: 26px; width: 390px; max-width: 92vw; box-shadow: 0 14px 44px rgba(45,55,72,.2); }
    .co-md h3 { font-size: 17px; font-weight: 800; margin: 0 0 6px; color: #2D3748; }
    .co-md .sb { font-size: 13px; color: #718096; margin: 0 0 16px; }
    .co-md input { width: 100%; border: 1px solid #e9e9e9; border-radius: 9px; padding: 11px 14px; font-size: 14px; outline: none; font-family: inherit; }
    .co-md input:focus { border-color: #8e57fe; }
    .co-md .hint { font-size: 12.5px; color: #718096; margin-top: 8px; }
    .co-md-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; direction: ltr; }

    :root[data-theme="dark"] .co-md { background: var(--surface); }
    :root[data-theme="dark"] .co-md h3 { color: var(--text-strong); }
    :root[data-theme="dark"] .co-md .sb,
    :root[data-theme="dark"] .co-md .hint { color: var(--text-muted); }
    :root[data-theme="dark"] .co-md input {
      background: var(--surface);
      color: var(--text-strong);
      border-color: var(--border-soft);
    }

    @media (max-width: 768px) {
      .co-wrap { margin-top: 84px; }
      .co-form-grid { grid-template-columns: 1fr; }
      .co-search input { width: 100%; }
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="co-wrap">

  <div class="co-head">
    <div>
      <h4><i class="bi bi-building ms-2"></i>مشخصات سازمان</h4>
      <div class="sub">مشاهده و ویرایش اطلاعات و اشتراک سازمان</div>
    </div>
    <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-right ms-1"></i>بازگشت
    </a>
  </div>

  <!-- بارگذاری -->
  <div id="coLoading" class="co-loading"><div class="co-spin"></div>در حال بارگذاری اطلاعات سازمان…</div>

  <!-- خطا / عدم دسترسی -->
  <div id="coError" class="co-error" style="display:none"></div>

  <!-- محتوا -->
  <div id="coContent" style="display:none">

    <!-- هدر سازمان -->
    <div class="co-org">
      <div class="co-logo" id="coOrgLogo"></div>
      <div>
        <div class="nm" id="coOrgName"></div>
        <div class="mt" id="coOrgMeta"></div>
      </div>
    </div>

    <!-- آمار -->
    <div class="co-stats">
      <div class="co-pcard">
        <div class="ic" style="background:rgba(142, 87, 254, 0.12);color:#8e57fe"><i class="bi bi-people"></i></div>
        <div><div class="val" id="coStatTotal">۰</div><div class="lbl">کل پرسنل</div></div>
      </div>
      <div class="co-pcard">
        <div class="ic" style="background:rgba(27, 123, 57, 0.12);color:#1b7b39"><i class="bi bi-person-check"></i></div>
        <div><div class="val" id="coStatActive">۰</div><div class="lbl">پرسنل فعال</div></div>
      </div>
      <div class="co-pcard">
        <div class="ic" style="background:#FEF3C7;color:#F79009"><i class="bi bi-bar-chart"></i></div>
        <div><div class="val" id="coStatCap">۰٪</div><div class="lbl">ظرفیت تکمیل‌شده</div></div>
      </div>
    </div>

    <!-- اشتراک -->
    <div class="co-card">
      <div class="co-card-h">
        <h6>وضعیت اشتراک</h6>
        <button class="btn btn-primary btn-sm" onclick="openExtend()"><i class="bi bi-arrow-clockwise ms-1"></i>تمدید اشتراک</button>
      </div>
      <div class="co-card-b">
        <div class="co-sub-grid">
          <div class="co-sub-item"><div class="l">نوع پلن</div><div class="v" id="coSubPlan">—</div></div>
          <div class="co-sub-item"><div class="l">تاریخ شروع</div><div class="v" id="coSubStart">—</div></div>
          <div class="co-sub-item"><div class="l">تاریخ پایان</div><div class="v" id="coSubEnd">—</div></div>
          <div class="co-sub-item"><div class="l">روزهای باقی‌مانده</div><div class="v" id="coSubRemain">—</div></div>
          <div class="co-sub-item"><div class="l">سقف کاربران</div><div class="v" id="coSubMaxUsers">—</div></div>
          <div class="co-sub-item"><div class="l">وضعیت</div><div class="v"><span id="coSubBadge" class="co-badge gray">—</span></div></div>
        </div>
        <div id="coExpireAlert"></div>
      </div>
    </div>

    <!-- اطلاعات و لوگو -->
    <div class="co-card">
      <div class="co-card-h"><h6>اطلاعات سازمان</h6></div>
      <div class="co-card-b">
        <div class="co-logo-box">
          <div id="coLogoContainer">
            <div class="co-logo-ph" id="coLogoPlaceholder"><i class="bi bi-building"></i></div>
            <img id="coLogoPreview" class="co-logo-prev" src="" alt="لوگو" style="display:none;">
          </div>
          <div class="d-flex flex-column gap-2">
            <label class="btn btn-outline-primary btn-sm mb-0">
              <i class="bi bi-upload ms-1"></i>انتخاب لوگو
              <input type="file" id="coLogoInput" accept="image/png,image/jpeg,image/svg+xml" style="display:none;" onchange="previewLogo(this)">
            </label>
            <button type="button" class="btn btn-outline-danger btn-sm" id="coRemoveLogo" style="display:none;" onclick="clearLogo()">
              <i class="bi bi-trash ms-1"></i>حذف لوگو
            </button>
            <small class="text-muted">PNG, JPG یا SVG — حداکثر ۲ مگابایت</small>
          </div>
        </div>

        <form id="coForm" onsubmit="saveOrgInfo(event)">
          <div class="co-form-grid">
            <div>
              <label class="form-label">نام شرکت <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="coName" maxlength="255" required>
            </div>
            <div>
              <label class="form-label">شماره تلفن</label>
              <input type="text" class="form-control" id="coPhone" maxlength="20" dir="ltr">
            </div>
            <div>
              <label class="form-label">آدرس</label>
              <input type="text" class="form-control" id="coAddress" maxlength="500">
            </div>
            <div>
              <label class="form-label">توضیحات</label>
              <textarea class="form-control" id="coDescription" rows="3" maxlength="1000"></textarea>
            </div>
          </div>
          <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadAll()"><i class="bi bi-arrow-clockwise ms-1"></i>بازنشانی</button>
            <button type="submit" class="btn btn-primary btn-sm" id="coSaveBtn"><i class="bi bi-check-lg ms-1"></i>ذخیره تغییرات</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<!-- مودال تمدید (درگاه پرداخت) -->
<div class="co-ov" id="coExtendModal">
  <div class="co-md">
    <h3>تمدید اشتراک</h3>
    <p class="sb">تعداد ماه‌هایی که می‌خواهید تمدید کنید را وارد کنید.</p>
    <input type="number" id="coMonths" min="1" max="24" value="1" placeholder="تعداد ماه">
    <div class="hint" id="coPriceHint"></div>
    <div class="co-md-actions">
      <button class="btn btn-secondary" onclick="closeExtend()">انصراف</button>
      <button class="btn btn-primary" onclick="confirmExtend()"><i class="bi bi-credit-card ms-1"></i>پرداخت</button>
    </div>
  </div>
</div>

<script>
const PRICE_PER_MONTH = 200000;
const token = localStorage.getItem('auth_token') || (typeof authToken !== 'undefined' ? authToken : null);
if (!token) { window.location.href = '/index.php'; }

const faNum = s => String(s == null ? '—' : s).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
function esc(s) {
  return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

/* تبدیل میلادی به شمسی */
function gToJalali(gy, gm, gd) {
  const gdm = [0,31,59,90,120,151,181,212,243,273,304,334];
  let jy = (gy <= 1600) ? 0 : 979;
  gy -= (gy <= 1600) ? 621 : 1600;
  const gy2 = (gm > 2) ? (gy + 1) : gy;
  let days = (365*gy) + Math.floor((gy2+3)/4) - Math.floor((gy2+99)/100) + Math.floor((gy2+399)/400) - 80 + gd + gdm[gm-1];
  jy += 33*Math.floor(days/12053); days %= 12053;
  jy += 4*Math.floor(days/1461);   days %= 1461;
  jy += Math.floor((days-1)/365);  if (days > 365) days = (days-1)%365;
  const jm = (days < 186) ? 1 + Math.floor(days/31) : 7 + Math.floor((days-186)/30);
  const jd = 1 + ((days < 186) ? (days%31) : ((days-186)%30));
  return [jy, jm, jd];
}

/* هر تاریخی را شمسی + عددِ فارسی می‌کند (اگر میلادی بود تبدیل می‌کند) */
function faDate(s) {
  if (s === null || s === undefined || s === '') return '—';
  s = String(s);
  const m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
  if (m) {
    const [jy, jm, jd] = gToJalali(+m[1], +m[2], +m[3]);
    s = jy + '/' + String(jm).padStart(2,'0') + '/' + String(jd).padStart(2,'0');
  }
  return faNum(s);
}

/* معادلِ فارسیِ نوع پلن */
function planFa(p) {
  if (!p) return '—';
  const map = { trial:'آزمایشی', monthly:'ماهانه', yearly:'سالانه', free:'رایگان', basic:'پایه', pro:'حرفه‌ای', enterprise:'سازمانی' };
  return map[String(p).toLowerCase()] || p;
}

/* ── بارگذاری همه (هم دادهٔ نمایشی، هم فرم) ── */
async function loadAll() {
  document.getElementById('coLoading').style.display = 'block';
  document.getElementById('coError').style.display = 'none';
  try {
    const [dispRes, infoRes] = await Promise.all([
      fetch('/api/organization/my-org-data.php', { headers: { 'Authorization': 'Bearer ' + token } }),
      fetch('/api/organization/info.php',        { headers: { 'Authorization': 'Bearer ' + token } })
    ]);

    if (dispRes.status === 401 || infoRes.status === 401) { localStorage.removeItem('auth_token'); window.location.href = '/index.php'; return; }
    if (dispRes.status === 403 || infoRes.status === 403) { showError('شما اجازهٔ دسترسی به این صفحه را ندارید.'); return; }

    const disp = await dispRes.json();
    const info = await infoRes.json();
    if (!disp.success) { showError(disp.message || 'خطا در دریافت اطلاعات'); return; }

    renderDisplay(disp);
    if (info.success) {
      fillForm(info.organization || {});
      fillSubscription(info.subscription || null);
    }

    document.getElementById('coLoading').style.display = 'none';
    document.getElementById('coContent').style.display = 'block';
  } catch (err) {
    console.error(err);
    showError('خطا در ارتباط با سرور');
  }
}

// showError از showInlineError مشترک (assets/js/alert.js) استفاده می‌کنه —
// خودِ آن تابع پیغام رو escape می‌کنه، پس نیازی به esc() دستی اینجا نیست
function showError(msg) {
  document.getElementById('coLoading').style.display = 'none';
  document.getElementById('coError').style.display = 'block';
  showInlineError('coError', msg);
}

/* ── هدر + آمار + پرسنل (از my-org-data) ── */
function renderDisplay(d) {
  const logo = document.getElementById('coOrgLogo');
  if (d.org.logo) logo.innerHTML = '<img src="' + esc(d.org.logo) + '" alt="logo">';
  else logo.textContent = (d.org.name || '?').charAt(0);
  document.getElementById('coOrgName').textContent = d.org.name || '—';

  let meta = '<span><i class="bi bi-award"></i> پلن ' + esc(planFa(d.org.plan_label)) + '</span>';
  meta += '<span><i class="bi bi-calendar3"></i> عضویت: ' + esc(faNum(d.org.created_jalali || '—')) + '</span>';
  if (d.org.phone) meta += '<span><i class="bi bi-telephone"></i> ' + faNum(d.org.phone) + '</span>';
  document.getElementById('coOrgMeta').innerHTML = meta;

  document.getElementById('coStatTotal').textContent = faNum(d.stats.total);
  document.getElementById('coStatActive').textContent = faNum(d.stats.active);
  document.getElementById('coStatCap').textContent = faNum(d.stats.capacity_pct) + '٪';
}

/* ── اشتراک (از info) ── */
function fillSubscription(sub) {
  const badge = document.getElementById('coSubBadge');
  const alertBox = document.getElementById('coExpireAlert');
  if (!sub) {
    badge.textContent = 'بدون اشتراک'; badge.className = 'co-badge gray';
    alertBox.innerHTML = ''; return;
  }
  document.getElementById('coSubPlan').textContent = planFa(sub.plan_name || sub.plan_type);
  document.getElementById('coSubStart').textContent = faDate(sub.start_date);
  document.getElementById('coSubEnd').textContent = faDate(sub.end_date);
  document.getElementById('coSubMaxUsers').textContent = faNum(sub.max_users);

  const days = (sub.days_remaining !== undefined && sub.days_remaining !== null) ? sub.days_remaining : null;
  document.getElementById('coSubRemain').textContent = (days !== null) ? (days > 0 ? faNum(days) + ' روز' : 'منقضی شده') : '—';

  if (sub.status === 'active') { badge.innerHTML = '<i class="bi bi-dot"></i> فعال'; badge.className = 'co-badge ok'; }
  else if (sub.status === 'trial') { badge.innerHTML = '<i class="bi bi-hourglass-split"></i> آزمایشی'; badge.className = 'co-badge ok'; }
  else { badge.innerHTML = '<i class="bi bi-dot"></i> منقضی'; badge.className = 'co-badge no'; }

  if (sub.status !== 'expired' && days !== null && days <= 7 && days > 0) {
    alertBox.innerHTML = '<div class="co-alert ' + (days <= 3 ? 'crit' : 'warn') + '"><i class="bi bi-exclamation-triangle"></i> اشتراک شما تا ' + faNum(days) + ' روز دیگر منقضی می‌شود. لطفا تمدید کنید.</div>';
  } else if (sub.status === 'expired') {
    alertBox.innerHTML = '<div class="co-alert crit"><i class="bi bi-x-octagon"></i> اشتراک شما منقضی شده است. برای ادامهٔ استفاده تمدید کنید.</div>';
  } else { alertBox.innerHTML = ''; }
}

/* ── فرم اطلاعات (از info) ── */
let selectedLogoFile = null;
function fillForm(org) {
  document.getElementById('coName').value = org.name || '';
  document.getElementById('coPhone').value = org.phone || '';
  document.getElementById('coAddress').value = org.address || '';
  document.getElementById('coDescription').value = org.description || '';
  if (org.logo_url) {
    document.getElementById('coLogoPreview').src = org.logo_url;
    document.getElementById('coLogoPreview').style.display = 'block';
    document.getElementById('coLogoPlaceholder').style.display = 'none';
    document.getElementById('coRemoveLogo').style.display = 'inline-flex';
  } else {
    document.getElementById('coLogoPreview').style.display = 'none';
    document.getElementById('coLogoPlaceholder').style.display = 'flex';
    document.getElementById('coRemoveLogo').style.display = 'none';
  }
  selectedLogoFile = null;
}

function previewLogo(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  if (file.size > 2 * 1024 * 1024) { showToast('حجم فایل نباید بیشتر از ۲ مگابایت باشد', 'warning'); input.value = ''; return; }
  selectedLogoFile = file;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('coLogoPreview').src = e.target.result;
    document.getElementById('coLogoPreview').style.display = 'block';
    document.getElementById('coLogoPlaceholder').style.display = 'none';
    document.getElementById('coRemoveLogo').style.display = 'inline-flex';
  };
  reader.readAsDataURL(file);
}
function clearLogo() {
  selectedLogoFile = null;
  document.getElementById('coLogoInput').value = '';
  document.getElementById('coLogoPreview').style.display = 'none';
  document.getElementById('coLogoPlaceholder').style.display = 'flex';
  document.getElementById('coRemoveLogo').style.display = 'none';
}

async function saveOrgInfo(event) {
  event.preventDefault();
  const btn = document.getElementById('coSaveBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm ms-1"></span>در حال ذخیره...';

  const fd = new FormData();
  fd.append('name', document.getElementById('coName').value.trim());
  fd.append('phone', document.getElementById('coPhone').value.trim());
  fd.append('address', document.getElementById('coAddress').value.trim());
  fd.append('description', document.getElementById('coDescription').value.trim());
  if (selectedLogoFile) fd.append('logo', selectedLogoFile);

  try {
    const res = await fetch('/api/organization/update.php', {
      method: 'POST', headers: { 'Authorization': 'Bearer ' + token }, body: fd
    });
    const data = await res.json();
    if (data.success) { showToast('اطلاعات سازمان ذخیره شد', 'success'); loadAll(); }
    else showToast(data.message || 'خطا در ذخیره اطلاعات', 'warning');
  } catch (err) {
    console.error(err);
    showToast('خطا در ارتباط با سرور', 'warning');
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check-lg ms-1"></i>ذخیره تغییرات';
}

/* ── مودال تمدید (درگاه پرداخت) ── */
function openExtend() {
  document.getElementById('coMonths').value = 1;
  updatePriceHint();
  document.getElementById('coExtendModal').classList.add('show');
}
function closeExtend() { document.getElementById('coExtendModal').classList.remove('show'); }
document.getElementById('coExtendModal').addEventListener('click', e => { if (e.target.id === 'coExtendModal') closeExtend(); });
document.getElementById('coMonths').addEventListener('input', updatePriceHint);
function updatePriceHint() {
  const m = parseInt(document.getElementById('coMonths').value) || 0;
  const total = (m * PRICE_PER_MONTH).toLocaleString('fa-IR');
  document.getElementById('coPriceHint').textContent = m > 0 ? ('مبلغ قابل پرداخت: ' + total + ' تومان') : '';
}

async function confirmExtend() {
  const months = parseInt(document.getElementById('coMonths').value);
  if (!months || months < 1) return;
  closeExtend();
  try {
    const res = await fetch('/api/payment/org-pay.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
      body: JSON.stringify({ months })
    });
    const data = await res.json();
    if (data.success && data.payment_url) { window.location.href = data.payment_url; }
    else showToast(data.message || 'خطا در اتصال به درگاه', 'warning');
  } catch (err) {
    console.error(err);
    showToast('خطا در ارتباط با سرور', 'warning');
  }
}

/* ── نتیجهٔ پرداخت بعد از بازگشت از درگاه ── */
(function () {
  const result = new URLSearchParams(window.location.search).get('payment');
  if (!result) return;
  const map = {
    success: ['پرداخت موفق بود و اشتراک تمدید شد', 'success'],
    cancel:  ['پرداخت لغو شد', 'warning'],
    failed:  ['پرداخت ناموفق بود', 'warning'],
    already: ['این پرداخت قبلا ثبت شده بود', 'warning'],
    error:   ['خطا در ثبت اشتراک', 'warning'],
    notfound:['پرداخت یافت نشد', 'warning'],
  };
  const m = map[result];
  if (m) { setTimeout(() => showToast(m[0], m[1]), 700); window.history.replaceState({}, '', window.location.pathname); }
})();

loadAll();
</script>
<script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
<?php include 'footer.php'; ?>
</body>
</html>