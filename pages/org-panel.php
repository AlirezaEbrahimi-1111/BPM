<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';
if (!hasPermission($__me, 'view_org_settings')) {
    header('Location: dashboard-manager.php');
    exit;
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>پنل مدیر سازمان</title>
  <link rel="stylesheet" href="../assets/js/cdn/bootstrap-icons.css">
  <style>
    :root {
      --bg:#F7F8FA; --surface:#FFFFFF; --border:#e9e9e9;
      --text-main:#101828; --text-sub:#667085;
      --primary:#8e57fe; --primary-light:rgba(142, 87, 254, 0.12);
      --success:#1b7b39; --success-light:rgba(27, 123, 57, 0.12);
      --danger:#F04438;  --danger-light:#FEE4E2;
      --warning:#F79009; --warning-light:#FEF3C7;
      --radius:14px; --radius-sm:8px;
      --shadow:0 1px 3px rgba(16,24,40,.08),0 1px 2px rgba(16,24,40,.06);
      --shadow-md:0 4px 16px rgba(16,24,40,.08);
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text-main);min-height:100vh}
    .main{max-width:70%;margin:auto;padding:32px}

    /* Loading / message */
    #loading,#errorBox{text-align:center;padding:64px 16px;color:var(--text-sub)}
    .spinner{width:36px;height:36px;border:3px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 16px}
    @keyframes spin{to{transform:rotate(360deg)}}

    /* Org header */
    .org-header{display:flex;align-items:center;gap:18px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-bottom:24px}
    .org-logo{width:64px;height:64px;border-radius:16px;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700;flex-shrink:0;overflow:hidden}
    .org-logo img{width:100%;height:100%;object-fit:cover}
    .org-h-name{font-size:20px;font-weight:700}
    .org-h-meta{font-size:13px;color:var(--text-sub);margin-top:4px;display:flex;gap:14px;flex-wrap:wrap}

    /* Stat cards */
    .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px}
    .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);display:flex;align-items:center;gap:14px}
    .stat-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
    .stat-value{font-size:26px;font-weight:700;line-height:1}
    .stat-label{font-size:13px;color:var(--text-sub);margin-top:4px}

    /* Section card */
    .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:24px;overflow:hidden}
    .card-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
    .card-header h6{font-size:15px;font-weight:600}
    .card-body{padding:24px}

    /* Subscription */
    .sub-row{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px}
    .sub-info{display:flex;gap:32px;flex-wrap:wrap}
    .sub-item .lbl{font-size:12px;color:var(--text-sub)}
    .sub-item .val{font-size:16px;font-weight:700;margin-top:2px}
    .days-bar{width:180px;height:6px;background:var(--border);border-radius:6px;overflow:hidden;margin-top:8px}
    .days-bar-fill{height:100%;border-radius:6px;background:var(--success)}
    .days-bar-fill.warn{background:var(--warning)}
    .days-bar-fill.crit{background:var(--danger)}
    .alert-expire{margin-top:16px;padding:12px 16px;border-radius:var(--radius-sm);font-size:13px;display:flex;align-items:center;gap:8px}
    .alert-expire.warn{background:var(--warning-light);color:#B54708}
    .alert-expire.crit{background:var(--danger-light);color:#B42318}

    /* Badges */
    /* این صفحه custom.css را لود نمی‌کند → با fallback تا اگر متغیرِ سراسری نبود، همان مقادیرِ استاندارد */
    .badge{display:inline-flex;align-items:center;gap:4px;padding:7px 10px;border-radius:var(--badge-radius,7px);font-size:11.5px;font-weight:var(--badge-font-weight,500)}
    .badge-success{background:var(--success-light);color:#1b7b39}
    .badge-danger{background:var(--danger-light);color:#B42318}
    .badge-gray{background:#F2F4F7;color:var(--text-sub)}
    .badge-purple{background:var(--primary-light);color:var(--primary)}

    /* Table */
    table{width:100%;border-collapse:collapse}
    thead th{padding:10px 20px;font-size:11px;font-weight:600;color:var(--text-sub);text-transform:uppercase;letter-spacing:.5px;background:var(--bg);border-bottom:1px solid var(--border);white-space:nowrap;text-align:right}
    tbody tr{border-bottom:1px solid var(--border)}
    tbody tr:last-child{border-bottom:none}
    tbody td{padding:13px 20px;font-size:13.5px}

    /* Buttons */
    .btn{border:none;border-radius:9px;padding:10px 18px;font-size:13.5px;font-weight:600;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:6px}
    .btn-primary{background:var(--primary);color:#fff}
    .btn-primary:hover{opacity:.92}
    .btn-ghost{background:var(--bg);color:var(--text-sub);border:1px solid var(--border)}

    /* Search */
    .search-box{position:relative}
    .search-box input{border:1px solid var(--border);border-radius:var(--radius-sm);padding:8px 12px 8px 36px;font-size:13px;outline:none;width:220px;background:var(--bg);font-family:inherit}
    .search-box input:focus{border-color:var(--primary)}
    .search-box i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-sub)}

    /* Modal */
    .modal-overlay{position:fixed;inset:0;background:rgba(16,24,40,.4);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;z-index:999;opacity:0;pointer-events:none;transition:opacity .2s}
    .modal-overlay.show{opacity:1;pointer-events:all}
    .modal-box{background:var(--surface);border-radius:var(--radius);padding:28px;width:380px;max-width:92vw;box-shadow:var(--shadow-md)}
    .modal-title{font-size:17px;font-weight:700}
    .modal-sub{font-size:13px;color:var(--text-sub);margin:4px 0 18px}
    .modal-input{width:100%;border:1px solid var(--border);border-radius:var(--radius-sm);padding:11px 14px;font-size:14px;font-family:inherit;outline:none}
    .modal-input:focus{border-color:var(--primary)}
    .modal-hint{font-size:12px;color:var(--text-sub);margin-top:8px}
    .modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:22px}


    @media(max-width:768px){.main{padding:16px}.stats-grid{grid-template-columns:1fr}.days-bar{width:120px}}
  </style>
</head>
<body>

<main class="main">
  <!-- وضعیت بارگذاری -->
  <div id="loading"><div class="spinner"></div>در حال بارگذاری اطلاعات سازمان…</div>

  <!-- پیام خطا -->
  <div id="errorBox" style="display:none"></div>

  <!-- محتوای اصلی (بعد از بارگذاری نمایش داده می‌شود) -->
  <div id="content" style="display:none">

    <!-- هدر سازمان -->
    <div class="org-header">
      <div class="org-logo" id="orgLogo"></div>
      <div>
        <div class="org-h-name" id="orgName"></div>
        <div class="org-h-meta" id="orgMeta"></div>
      </div>
    </div>

    <!-- کارت‌های آماری -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(142, 87, 254, 0.12);color:#8e57fe"><i class="bi bi-people"></i></div>
        <div><div class="stat-value" id="statTotal">0</div><div class="stat-label">کل پرسنل</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(27, 123, 57, 0.12);color:#1b7b39"><i class="bi bi-person-check"></i></div>
        <div><div class="stat-value" id="statActive">0</div><div class="stat-label">پرسنل فعال</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#FEF3C7;color:#F79009"><i class="bi bi-bar-chart"></i></div>
        <div><div class="stat-value" id="statCapacity">0</div><div class="stat-label">ظرفیت تکمیل‌شده</div></div>
      </div>
    </div>

    <!-- وضعیت اشتراک -->
    <div class="card">
      <div class="card-header"><h6>وضعیت اشتراک</h6></div>
      <div class="card-body">
        <div class="sub-row">
          <div class="sub-info">
            <div class="sub-item"><div class="lbl">پلن</div><div class="val" id="subPlan">—</div></div>
            <div class="sub-item"><div class="lbl">تاریخ پایان</div><div class="val" id="subEnd">—</div></div>
            <div class="sub-item">
              <div class="lbl">وضعیت</div>
              <div class="val" id="subStatus">—</div>
              <div class="days-bar"><div class="days-bar-fill" id="subBar" style="width:0%"></div></div>
            </div>
          </div>
          <button class="btn btn-primary" onclick="openExtend()"><i class="bi bi-arrow-clockwise"></i> تمدید اشتراک</button>
        </div>
        <div id="expireAlert"></div>
      </div>
    </div>

    <!-- عملکرد / پرسنل -->
    <div class="card">
      <div class="card-header">
        <h6>پرسنل سازمان</h6>
        <div class="search-box"><input type="text" id="searchInput" placeholder="جستجو…"><i class="bi bi-search"></i></div>
      </div>
      <table id="personnelTable">
        <thead>
          <tr><th>نام</th><th>نقش</th><th>واحد</th><th>وضعیت</th><th>آخرین ورود</th></tr>
        </thead>
        <tbody id="personnelBody"></tbody>
      </table>
    </div>

  </div>
</main>

<!-- مودال تمدید -->
<div class="modal-overlay" id="extendModal">
  <div class="modal-box">
    <div class="modal-title">تمدید اشتراک</div>
    <div class="modal-sub">تعداد ماه‌هایی که می‌خواهید تمدید کنید را وارد کنید.</div>
    <input class="modal-input" type="number" id="monthsInput" min="1" max="24" value="1" placeholder="تعداد ماه">
    <div class="modal-hint" id="priceHint"></div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal()">انصراف</button>
      <button class="btn btn-primary" onclick="confirmExtend()"><i class="bi bi-credit-card"></i> پرداخت</button>
    </div>
  </div>
</div>

<script src="<?= asset('/assets/js/common.js') ?>"></script>
<script src="<?= asset('/assets/js/alert.js') ?>"></script>
<script>
  // قیمت هر ماه (فقط برای نمایش؛ قیمت واقعی را سرور تعیین می‌کند)
  const PRICE_PER_MONTH = 200000;

  const token = localStorage.getItem('auth_token');
  if (!token) { window.location.href = '/index.php'; }

  function toFa(n) {
    return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
  }

  // ── دریافت و نمایش اطلاعات ──
  async function loadData() {
    try {
      const res = await fetch('/api/organization/my-org-data.php', {
        headers: { 'Authorization': 'Bearer ' + token }
      });

      if (res.status === 401) { localStorage.removeItem('auth_token'); window.location.href = '/index.php'; return; }
      if (res.status === 403) { showError('شما اجازه‌ی دسترسی به این صفحه را ندارید.'); return; }

      const data = await res.json();
      if (!data.success) { showError(data.message || 'خطا در دریافت اطلاعات'); return; }

      render(data);
      document.getElementById('loading').style.display = 'none';
      document.getElementById('content').style.display = 'block';
    } catch (err) {
      console.error(err);
      showError('خطا در ارتباط با سرور');
    }
  }

  // showError از showInlineError مشترک (assets/js/alert.js) استفاده می‌کنه —
  // قبلاً msg بدونِ escape مستقیم در innerHTML می‌رفت؛ الان امنه
  function showError(msg) {
    document.getElementById('loading').style.display = 'none';
    document.getElementById('errorBox').style.display = 'block';
    showInlineError('errorBox', msg);
  }

  function render(d) {
    // هدر سازمان
    const logo = document.getElementById('orgLogo');
    if (d.org.logo) logo.innerHTML = '<img src="' + esc(d.org.logo) + '" alt="logo">';
    else logo.textContent = (d.org.name || '?').charAt(0);
    document.getElementById('orgName').textContent = d.org.name || '—';
    let meta = '<span><i class="bi bi-award"></i> پلن ' + esc(d.org.plan_label) + '</span>';
    meta += '<span><i class="bi bi-calendar3"></i> عضویت: ' + esc(d.org.created_jalali) + '</span>';
    if (d.org.phone) meta += '<span><i class="bi bi-telephone"></i> ' + esc(d.org.phone) + '</span>';
    document.getElementById('orgMeta').innerHTML = meta;

    // آمار
    document.getElementById('statTotal').textContent = toFa(d.stats.total);
    document.getElementById('statActive').textContent = toFa(d.stats.active);
    document.getElementById('statCapacity').textContent = toFa(d.stats.capacity_pct) + '٪';

    // اشتراک
    document.getElementById('subPlan').textContent = d.subscription.plan_label;
    document.getElementById('subEnd').textContent = d.subscription.end_jalali;
    const st = document.getElementById('subStatus');
    if (d.subscription.status === 'active') st.innerHTML = '<span class="badge badge-success"><i class="bi bi-dot"></i> فعال (' + toFa(d.subscription.days_left) + ' روز مانده)</span>';
    else if (d.subscription.status === 'expired') st.innerHTML = '<span class="badge badge-danger"><i class="bi bi-dot"></i> منقضی</span>';
    else st.innerHTML = '<span class="badge badge-gray">بدون اشتراک</span>';

    const bar = document.getElementById('subBar');
    bar.style.width = d.subscription.bar_pct + '%';
    bar.className = 'days-bar-fill ' + d.subscription.bar_class;

    // هشدار انقضا
    const alertBox = document.getElementById('expireAlert');
    if (d.subscription.status === 'active' && d.subscription.days_left <= 7) {
      alertBox.innerHTML = '<div class="alert-expire ' + (d.subscription.days_left <= 3 ? 'crit' : 'warn') + '"><i class="bi bi-exclamation-triangle"></i> اشتراک شما تا ' + toFa(d.subscription.days_left) + ' روز دیگر منقضی می‌شود. لطفاً تمدید کنید.</div>';
    } else if (d.subscription.status === 'expired') {
      alertBox.innerHTML = '<div class="alert-expire crit"><i class="bi bi-x-octagon"></i> اشتراک شما منقضی شده است. برای ادامه‌ی استفاده تمدید کنید.</div>';
    } else { alertBox.innerHTML = ''; }

    // پرسنل
    const tbody = document.getElementById('personnelBody');
    tbody.innerHTML = d.personnel.map(p => `
      <tr>
        <td style="font-weight:600">${esc(p.name)}</td>
        <td><span class="badge badge-purple">${esc(p.role_label)}</span></td>
        <td style="color:var(--text-sub)">${esc(p.unit_label)}</td>
        <td>${p.is_active ? '<span class="badge badge-success">فعال</span>' : '<span class="badge badge-gray">غیرفعال</span>'}</td>
        <td style="color:var(--text-sub);font-size:12.5px">${esc(p.last_login_jalali)}</td>
      </tr>`).join('');
  }

  // ── جستجو ──
  document.getElementById('searchInput').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#personnelTable tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });

  // ── مودال ──
  function openExtend() {
    document.getElementById('monthsInput').value = 1;
    updatePriceHint();
    document.getElementById('extendModal').classList.add('show');
  }
  function closeModal() { document.getElementById('extendModal').classList.remove('show'); }
  document.getElementById('extendModal').addEventListener('click', function (e) { if (e.target === this) closeModal(); });
  document.getElementById('monthsInput').addEventListener('input', updatePriceHint);
  function updatePriceHint() {
    const m = parseInt(document.getElementById('monthsInput').value) || 0;
    const total = (m * PRICE_PER_MONTH).toLocaleString('fa-IR');
    document.getElementById('priceHint').textContent = m > 0 ? ('مبلغ قابل پرداخت: ' + total + ' تومان') : '';
  }

  // ── تمدید → درگاه پرداخت ──
  async function confirmExtend() {
    const months = parseInt(document.getElementById('monthsInput').value);
    if (!months || months < 1) return;
    closeModal();
    try {
      const res = await fetch('/api/payment/org-pay.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
        body: JSON.stringify({ months })
      });
      const data = await res.json();
      if (data.success && data.payment_url) {
        window.location.href = data.payment_url;  // برو به درگاه زرین‌پال
      } else {
        showToast(data.message || 'خطا در اتصال به درگاه', 'error');
      }
    } catch (err) {
      console.error(err);
      showToast('خطا در ارتباط با سرور', 'error');
    }
  }

  // ── Toast ──
  // showToast از assets/js/alert.js استفاده می‌شود — قبلاً اینجا یک نسخهٔ
  // محلیِ جداگانه (وابسته به یک div ثابت) بازتعریف می‌شد

  // ── نمایش نتیجه‌ی پرداخت بعد از بازگشت از درگاه ──
  (function () {
    const params = new URLSearchParams(window.location.search);
    const result = params.get('payment');
    if (!result) return;
    const messages = {
      success:  ['پرداخت موفق بود و اشتراک تمدید شد', 'success'],
      cancel:   ['پرداخت لغو شد', 'error'],
      failed:   ['پرداخت ناموفق بود', 'error'],
      already:  ['این پرداخت قبلاً ثبت شده بود', 'error'],
      error:    ['خطا در ثبت اشتراک', 'error'],
      notfound: ['پرداخت یافت نشد', 'error'],
    };
    const [msg, type] = messages[result] || ['', ''];
    if (msg) { setTimeout(() => showToast(msg, type), 600); window.history.replaceState({}, '', window.location.pathname); }
  })();

  loadData();
</script>
</body>
</html>
