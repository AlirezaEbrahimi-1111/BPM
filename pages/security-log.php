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

// ── احراز هویت (هماهنگ با superAdmin.php) ──
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id)                                  $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token']))  $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    header('Location: ../index.php');
    exit;
}

// 🔒 فقط مدیر کل (superadmin) — دقیقاً همون معیاری که در کلِ پروژه
// برایِ تشخیصِ سوپرادمین استفاده می‌شه، نه یه چکِ جداگانه‌یِ id===1
if (!in_array((int) $user_id, getSuperAdminIds(), true)) {
    header('Location: dashboard-manager.php');
    exit;
}

/* ───────── فیلترها (از querystring) ───────── */
$actionFilter = $_GET['action'] ?? '';
$daysFilter   = isset($_GET['days']) ? max(1, min(90, (int) $_GET['days'])) : 7;
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 50;
$offset       = ($page - 1) * $perPage;

$validActions = [
    'login_success'   => 'ورود موفق',
    'login_failed'    => 'ورود ناموفق',
    'logout'          => 'خروج',
    'password_changed' => 'تغییرِ رمز عبور',
    'user_activated'  => 'فعال‌سازیِ کاربر',
    'user_deactivated' => 'غیرفعال‌سازیِ کاربر',
];

$where  = ["l.created_at > (NOW() - INTERVAL ? DAY)"];
$params = [$daysFilter];

if ($actionFilter !== '' && isset($validActions[$actionFilter])) {
    $where[] = "l.action = ?";
    $params[] = $actionFilter;
}
$whereSql = implode(' AND ', $where);

/* ───────── کارت‌های آماری (۲۴ ساعتِ اخیر) ───────── */
$stat = $db->query("
    SELECT
        SUM(action = 'login_success') AS logins_ok,
        SUM(action = 'login_failed')  AS logins_bad,
        SUM(action = 'user_deactivated') AS deactivations
    FROM security_audit_log
    WHERE created_at > (NOW() - INTERVAL 24 HOUR)
")->fetch(PDO::FETCH_ASSOC);

$blockedNow = (int) $db->query("
    SELECT COUNT(DISTINCT ip) FROM login_attempts
    WHERE attempted_at > (NOW() - INTERVAL 15 MINUTE)
    GROUP BY ip HAVING COUNT(*) >= 5
")->rowCount();

/* ───────── ردیف‌ها ───────── */
$countStmt = $db->prepare("SELECT COUNT(*) FROM security_audit_log l WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

$stmt = $db->prepare("
    SELECT
        l.id, l.action, l.ip_address, l.details, l.created_at,
        CONCAT(u.first_name, ' ', u.last_name) AS actor_name,
        CONCAT(tu.first_name, ' ', tu.last_name) AS target_name
    FROM security_audit_log l
    LEFT JOIN users u  ON u.id  = l.user_id
    LEFT JOIN users tu ON tu.id = l.target_user_id
    WHERE $whereSql
    ORDER BY l.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>رصدِ امنیتی</title>
  <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
  <link rel="stylesheet" href="<?= asset('../assets/css/custom.css') ?>">
  <style>
    body { font-family: 'Vazirmatn', sans-serif; background: #F7F8FC; }

    .admin-wrap { max-width: 70%; margin: 90px auto 40px; padding: 0 16px; }

    .admin-toolbar {
      display: flex; flex-wrap: wrap; gap: 12px;
      align-items: center; justify-content: space-between; margin-bottom: 18px;
    }
    .admin-toolbar h4 { margin: 0; color: #8e57fe; font-weight: 700; }
    .admin-toolbar .sub { font-size: 13px; color: #718096; margin-top: 2px; }

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

    .filter-bar {
      background: #fff; border-radius: 14px; padding: 14px 18px;
      box-shadow: 0 2px 10px rgba(0,0,0,.04);
      display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
      margin-bottom: 16px;
    }
    .filter-bar select {
      border: 1px solid #e9e9e9; border-radius: 9px; padding: 8px 12px;
      font-size: 13px; font-family: inherit; outline: none; background: #fff;
    }
    .filter-bar select:focus { border-color: #8e57fe; }

    .log-table-wrap {
      background: #fff; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,.04);
      overflow-x: auto;
    }
    table.log-table { width: 100%; border-collapse: collapse; font-size: 13px; white-space: nowrap; }
    table.log-table th {
      text-align: right; padding: 12px 16px; color: #718096; font-weight: 700;
      border-bottom: 1px solid #e9e9e9; background: #e9e9e9; font-size: 12px;
    }
    table.log-table td { padding: 11px 16px; border-bottom: 1px solid #e9e9e9; color: #2D3748; }
    table.log-table tr:last-child td { border-bottom: none; }
    table.log-table tr:hover td { background: #e9e9e9; }

    .pill { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
    .pill.ok   { background: rgba(27, 123, 57, 0.12); color: #1b7b39; }
    .pill.bad  { background: #FEE4E2; color: #B42318; }
    .pill.info { background: rgba(142, 87, 254, 0.12); color: #8e57fe; }
    .pill.warn { background: #FEF3E0; color: #B25E09; }

    .empty-row { text-align: center; padding: 40px 16px; color: #A0AEC0; }

    .pagination { display: flex; gap: 6px; justify-content: center; padding: 16px; }
    .pagination a, .pagination span {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 32px; height: 32px; border-radius: 8px; font-size: 13px;
      text-decoration: none; color: #2D3748; border: 1px solid #e9e9e9;
    }
    .pagination a:hover { border-color: #8e57fe; color: #8e57fe; }
    .pagination .active { background: #8e57fe; color: #fff; border-color: #8e57fe; }

    @media (max-width: 768px) {
      .admin-wrap { margin-top: 84px; padding: 0 10px; }
      .filter-bar select { flex: 1; min-width: 120px; }
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="admin-wrap">
  <div class="admin-toolbar">
    <div>
      <h4><i class="bi bi-shield-lock"></i> رصدِ امنیتی</h4>
      <div class="sub">تاریخچه‌یِ ورود، خروج، و رویدادهایِ حساسِ سیستم</div>
    </div>
  </div>

  <div class="admin-cards">
    <div class="pcard">
      <div class="ic" style="background:rgba(27, 123, 57, 0.12);color:#1b7b39;"><i class="bi bi-box-arrow-in-left"></i></div>
      <div><div class="lbl">ورودِ موفق (۲۴ ساعتِ اخیر)</div><div class="val"><?= (int) ($stat['logins_ok'] ?? 0) ?></div></div>
    </div>
    <div class="pcard">
      <div class="ic" style="background:#FEE4E2;color:#B42318;"><i class="bi bi-exclamation-octagon"></i></div>
      <div><div class="lbl">ورودِ ناموفق (۲۴ ساعتِ اخیر)</div><div class="val"><?= (int) ($stat['logins_bad'] ?? 0) ?></div></div>
    </div>
    <div class="pcard">
      <div class="ic" style="background:#FEF3E0;color:#B25E09;"><i class="bi bi-shield-x"></i></div>
      <div><div class="lbl">آی‌پیِ مسدودِ لحظه‌ای</div><div class="val"><?= $blockedNow ?></div></div>
    </div>
    <div class="pcard">
      <div class="ic" style="background:rgba(142, 87, 254, 0.12);color:#8e57fe;"><i class="bi bi-person-x"></i></div>
      <div><div class="lbl">غیرفعال‌سازیِ کاربر (۲۴ ساعتِ اخیر)</div><div class="val"><?= (int) ($stat['deactivations'] ?? 0) ?></div></div>
    </div>
  </div>

  <form class="filter-bar" method="get">
    <select name="action" onchange="this.form.submit()">
      <option value="">همه‌یِ رویدادها</option>
      <?php foreach ($validActions as $key => $label): ?>
        <option value="<?= htmlspecialchars($key) ?>" <?= $actionFilter === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="days" onchange="this.form.submit()">
      <?php foreach ([1 => 'امروز', 7 => '۷ روزِ اخیر', 30 => '۳۰ روزِ اخیر', 90 => '۹۰ روزِ اخیر'] as $d => $label): ?>
        <option value="<?= $d ?>" <?= $daysFilter === $d ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
      <?php endforeach; ?>
    </select>
    <span class="sub" style="color:#718096;font-size:12.5px;"><?= number_format($totalRows) ?> رویداد</span>
  </form>

  <div class="log-table-wrap">
    <table class="log-table">
      <thead>
        <tr>
          <th>زمان</th>
          <th>رویداد</th>
          <th>کاربر</th>
          <th>هدف</th>
          <th>IP</th>
          <th>جزئیات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="empty-row"><i class="bi bi-inbox"></i> رویدادی یافت نشد</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <?php
            // 🔧 match() فقط PHP 8+ هست ولی سرور PHP 7.4 داره — به‌جاش
            // یه نگاشتِ ساده استفاده می‌کنیم
            $action = $r['action'];
            $pillClassMap = [
                'login_success'    => 'ok',
                'login_failed'     => 'bad',
                'user_deactivated' => 'bad',
                'user_activated'   => 'ok',
                'password_changed' => 'warn',
            ];
            $pillClass = $pillClassMap[$action] ?? 'info';
            $label = $validActions[$action] ?? $action;
            $details = $r['details'] ? json_decode($r['details'], true) : null;
            $detailsText = '';
            if (is_array($details)) {
                $parts = [];
                foreach ($details as $k => $v) {
                    $parts[] = htmlspecialchars($k) . ': ' . htmlspecialchars((string) $v);
                }
                $detailsText = implode(' — ', $parts);
            }
            $jTime = JalaliHelper::formatJalaliDate(substr((string) $r['created_at'], 0, 10)) . ' ' . substr((string) $r['created_at'], 11, 5);
          ?>
          <tr>
            <td><?= htmlspecialchars($jTime) ?></td>
            <td><span class="pill <?= $pillClass ?>"><?= htmlspecialchars($label) ?></span></td>
            <td><?= $r['actor_name'] && trim($r['actor_name'], ' ') !== '' ? htmlspecialchars($r['actor_name']) : '—' ?></td>
            <td><?= $r['target_name'] && trim($r['target_name'], ' ') !== '' ? htmlspecialchars($r['target_name']) : '—' ?></td>
            <td dir="ltr" style="text-align:left;"><?= htmlspecialchars($r['ip_address'] ?? '—') ?></td>
            <td style="white-space:normal;max-width:280px;color:#718096;font-size:12px;"><?= $detailsText !== '' ? htmlspecialchars($detailsText) : '—' ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php
          $qs = $_GET;
          for ($p = 1; $p <= $totalPages; $p++):
            $qs['page'] = $p;
            $url = '?' . http_build_query($qs);
        ?>
          <?php if ($p === $page): ?>
            <span class="active"><?= $p ?></span>
          <?php else: ?>
            <a href="<?= htmlspecialchars($url) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
