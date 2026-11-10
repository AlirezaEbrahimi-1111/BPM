<?php
/**
 * صفحهٔ نسخه‌های اپِ موبایل — https://bpm.itmalek.com/app-releases/ (۱۴۰۵/۰۸/۱۹)
 *
 * .htaccess آدرسِ پوشهٔ app-releases را به این فایل می‌فرستد. خودِ پوشه (بیرون از گیت) را
 * GitHub Actionsِ ریپوی اپ (build-apk.yml) پر می‌کند: APKها + latest.json + release-<build>.json.
 *
 *  - همه (حتی بدون ورود): آخرین نسخه + لینکِ دانلود.
 *  - فقط مدیر کلِ سامانه (id=1): همهٔ نسخه‌ها با تاریخ، تغییرات، اجباری/اختیاری، حجم و
 *    تعداد کاربرانِ هر نسخه (app_installs؛ اپ از build ۱۲۷ نسخه‌اش را گزارش می‌دهد —
 *    go-api/internal/appinfo).
 */
require_once __DIR__ . '/includes/session_start.php';
require_once __DIR__ . '/includes/JalaliHelper.php';

$dir = __DIR__ . '/app-releases';
$base = 'https://bpm.itmalek.com/app-releases/';
$abiLabels = [
    'arm64-v8a'   => 'گوشی‌های امروزی (۶۴ بیتی)',
    'armeabi-v7a' => 'گوشی‌های قدیمی (۳۲ بیتی)',
];

// ── نسخه‌ها: release-*.json + latest.json، و APKهایی که json ندارند ──
$releases = [];
$readJson = function ($path) {
    $j = @json_decode((string) @file_get_contents($path), true);
    return (is_array($j) && !empty($j['build'])) ? $j : null;
};
foreach (array_merge(glob($dir . '/release-*.json') ?: [], [$dir . '/latest.json']) as $f) {
    if (is_file($f) && ($j = $readJson($f))) {
        $releases[(int) $j['build']] = $j;
    }
}
foreach (glob($dir . '/bpm-*.apk') ?: [] as $f) {
    if (!preg_match('/^bpm-(.+)-(\d+)-(arm64-v8a|armeabi-v7a)\.apk$/', basename($f), $m)) continue;
    $b = (int) $m[2];
    if (!isset($releases[$b])) {
        $releases[$b] = ['version' => $m[1], 'build' => $b, 'min_build' => null, 'notes' => '',
                         'released_at' => date('Y-m-d H:i', filemtime($f)), 'files' => []];
    }
    if (empty($releases[$b]['files'][$m[3]])) {
        $releases[$b]['files'][$m[3]] = ['url' => $base . basename($f), 'size' => filesize($f)];
    }
}
krsort($releases);
$latest = $releases ? reset($releases) : null;

// ── کاربرِ واردشده (بدونِ اجبار به ورود) ──
// بازدیدکنندهٔ بی‌ورود اصلاً به پایگاه داده وصل نمی‌شود (Database در خطا die می‌کند؛ بخشِ دانلود
// نباید به آن وابسته باشد)
$isOwner = false;
$db = null;
$uid = $_SESSION['user_id'] ?? null;
if ($uid || !empty($_COOKIE['auth_token'])) {
    try {
        // config/database.php همان لحظهٔ include وصل می‌شود؛ پس فقط این‌جا
        require_once __DIR__ . '/config/database.php';
        require_once __DIR__ . '/includes/auth.php';
        if (!$db) {
            $database = new Database();
            $db = $database->getConnection();
        }
        if (!$uid) $uid = (new Auth($db))->validateToken($_COOKIE['auth_token']);
        $isOwner = ((int) $uid === 1);
    } catch (Throwable $e) {
        $db = null;
    }
}

// ── تعداد کاربرانِ هر نسخه (فقط مدیر کل) ──
$installs = [];       // build => [rows]
$installTotal = 0;
$installError = false;
if ($isOwner && $db) {
    try {
        $st = $db->query("
            SELECT ai.build, ai.version, ai.abi, ai.last_seen, ai.first_seen,
                   TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS name,
                   o.name AS org_name
            FROM app_installs ai
            JOIN users u ON u.id = ai.user_id
            LEFT JOIN organizations o ON o.id = ai.organization_id
            WHERE ai.platform = 'android'
            ORDER BY ai.build DESC, ai.last_seen DESC
        ");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $installs[(int) $r['build']][] = $r;
            $installTotal++;
            if (!isset($releases[(int) $r['build']])) {
                // نسخه‌ای که فایلش روی سرور نیست (مثلاً بیلدِ دستی) — فقط برای شمارش
                $releases[(int) $r['build']] = ['version' => $r['version'], 'build' => (int) $r['build'],
                    'min_build' => null, 'notes' => '', 'released_at' => '', 'files' => [], 'missing' => true];
            }
        }
        krsort($releases);
    } catch (Throwable $e) {
        $installError = true;
    }
}

function fa($s) { return strtr((string) $s, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); }
function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function mb($bytes) { return fa(number_format($bytes / 1048576, 1)) . ' مگابایت'; }
function jdt($dt) {
    if (!$dt) return '';
    $d = substr($dt, 0, 10); $t = substr($dt, 11, 5);
    return JalaliHelper::formatJalaliDate($d) . ($t ? ' ساعت ' . fa($t) : '');
}
function isForced($r) { return isset($r['min_build']) && (int) $r['min_build'] === (int) $r['build']; }
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>دانلود اپلیکیشن BPM</title>
<script>
  try { var t = localStorage.getItem('bpm_theme'); if (t) document.documentElement.setAttribute('data-theme', t); } catch (e) {}
</script>
<style>
@font-face { font-family: Vazirmatn; src: url('/assets/fonts/Vazirmatn-Variable.woff2') format('woff2'); font-weight: 100 900; font-display: swap; }
:root {
  --bg: #f5f3fb; --card: #ffffff; --text: #22212b; --muted: #6d6a7c; --line: #e6e2f2;
  --brand: #8E57FE; --brand-soft: rgba(142,87,254,.12); --brand-hover: rgba(142,87,254,.18);
  --warn: #c2410c; --warn-soft: #fff1e8; --ok: #15803d; --ok-soft: #e9f8ef;
}
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
    --bg: #15141c; --card: #1f1d29; --text: #ecebf3; --muted: #a29fb4; --line: #2f2c3d;
    --brand-soft: rgba(142,87,254,.18); --brand-hover: rgba(142,87,254,.26);
    --warn: #fb923c; --warn-soft: #3a2416; --ok: #4ade80; --ok-soft: #16301f;
  }
}
:root[data-theme="dark"] {
  --bg: #15141c; --card: #1f1d29; --text: #ecebf3; --muted: #a29fb4; --line: #2f2c3d;
  --brand-soft: rgba(142,87,254,.18); --brand-hover: rgba(142,87,254,.26);
  --warn: #fb923c; --warn-soft: #3a2416; --ok: #4ade80; --ok-soft: #16301f;
}
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--text); font-family: Vazirmatn, Tahoma, sans-serif; line-height: 1.8; }
.wrap { max-width: 860px; margin: 0 auto; padding: 32px 16px 48px; }
.card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 24px; margin-bottom: 20px; }
.hero { text-align: center; }
.hero h1 { margin: 0 0 4px; font-size: 1.5rem; }
.hero .ver { color: var(--muted); margin: 0 0 20px; }
.dl { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
.btn { display: inline-flex; flex-direction: column; align-items: center; gap: 2px; padding: 12px 22px; border-radius: 12px;
       text-decoration: none; font-weight: 700; border: 1px solid var(--brand); color: var(--brand); background: transparent; min-width: 220px; }
.btn small { font-weight: 400; color: var(--muted); }
.btn.primary { background: var(--brand); color: #fff; }
.btn.primary small { color: rgba(255,255,255,.85); }
.btn:hover { background: var(--brand-hover); }
.btn.primary:hover { background: #7c45ee; }
.hint { color: var(--muted); font-size: .9rem; margin: 18px 0 0; }
h2 { font-size: 1.15rem; margin: 0 0 14px; }
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
.stat { background: var(--brand-soft); border-radius: 12px; padding: 12px 14px; }
.stat b { display: block; font-size: 1.35rem; }
.stat span { color: var(--muted); font-size: .88rem; }
.rel { border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin-top: 12px; }
.rel.is-latest { border-color: var(--brand); }
.rel-head { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.rel-head .name { font-weight: 800; font-size: 1.05rem; }
.badge { font-size: .78rem; padding: 2px 10px; border-radius: 999px; background: var(--brand-soft); color: var(--brand); }
.badge.warn { background: var(--warn-soft); color: var(--warn); }
.badge.ok { background: var(--ok-soft); color: var(--ok); }
.badge.mute { color: var(--muted); background: transparent; border: 1px solid var(--line); }
.rel-head .count { margin-inline-start: auto; font-weight: 700; }
.meta { color: var(--muted); font-size: .9rem; margin: 6px 0 0; }
.notes { margin: 10px 0 0; white-space: pre-line; }
.files { margin: 8px 0 0; font-size: .9rem; }
.files a { color: var(--brand); }
details { margin-top: 10px; }
summary { cursor: pointer; color: var(--brand); font-size: .92rem; }
table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: .88rem; }
th, td { text-align: right; padding: 6px 8px; border-bottom: 1px solid var(--line); }
th { color: var(--muted); font-weight: 600; }
tr:hover td { background: var(--brand-soft); }
.empty { color: var(--muted); }
.table-scroll { overflow-x: auto; }
@media (max-width: 520px) { .btn { min-width: 100%; } .card { padding: 18px; } }
</style>
</head>
<body>
<div class="wrap">

  <div class="card hero">
    <h1>اپلیکیشن BPM برای اندروید</h1>
    <?php if ($latest && !empty($latest['files'])): ?>
      <p class="ver">آخرین نسخه: <?= h(fa($latest['version'])) ?> (شمارهٔ ساخت <?= h(fa($latest['build'])) ?>)<?php if (!empty($latest['released_at'])): ?> — منتشرشده در <?= h(jdt($latest['released_at'])) ?><?php endif; ?></p>
      <div class="dl">
        <?php $first = true; foreach ($abiLabels as $abi => $label): if (empty($latest['files'][$abi])) continue; $f = $latest['files'][$abi]; ?>
          <a class="btn<?= $first ? ' primary' : '' ?>" href="<?= h($f['url']) ?>" download>
            دانلود برای <?= h($label) ?>
            <small><?= !empty($f['size']) ? h(mb($f['size'])) : '' ?></small>
          </a>
        <?php $first = false; endforeach; ?>
      </div>
      <p class="hint">اگر نمی‌دانید گوشی‌تان کدام است، دکمهٔ اول را دانلود کنید؛ تقریباً همهٔ گوشی‌های چند سال اخیر ۶۴ بیتی هستند.
        هنگام نصب، اگر گوشی اجازهٔ «نصب از منابع ناشناس» خواست، آن را بدهید. بعد از نصب، به‌روزرسانی‌های بعدی از داخل خود برنامه انجام می‌شود.</p>
    <?php else: ?>
      <p class="ver">در حال حاضر نسخه‌ای برای دانلود منتشر نشده است.</p>
    <?php endif; ?>
  </div>

  <?php if ($isOwner): ?>
    <?php
      $latestBuild = $latest ? (int) $latest['build'] : 0;
      $onLatest = count($installs[$latestBuild] ?? []);
    ?>
    <div class="card">
      <h2>آمار نصب (فقط برای مدیر کل سامانه)</h2>
      <?php if ($installError): ?>
        <p class="empty">آمار نصب خوانده نشد؛ احتمالاً جدول آن هنوز روی سرور ساخته نشده است (بعد از دیپلوی ساخته می‌شود).</p>
      <?php else: ?>
        <div class="stats">
          <div class="stat"><b><?= fa($installTotal) ?> نفر</b><span>کاربرانی که نسخهٔ نصب‌شده‌شان را گزارش داده‌اند</span></div>
          <div class="stat"><b><?= fa($onLatest) ?> نفر</b><span>روی آخرین نسخه</span></div>
          <div class="stat"><b><?= fa($installTotal - $onLatest) ?> نفر</b><span>روی نسخه‌های قدیمی‌تر</span></div>
        </div>
        <p class="meta">اپ از نسخهٔ ۱۲۷ به بعد، هر بار که باز می‌شود نسخه‌اش را گزارش می‌دهد. کاربرانی که هنوز نسخه‌ای قدیمی‌تر دارند یا از آن زمان برنامه را باز نکرده‌اند، در این آمار نیستند.</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>همهٔ نسخه‌ها</h2>
      <?php if (!$releases): ?>
        <p class="empty">هنوز نسخه‌ای منتشر نشده است.</p>
      <?php endif; ?>
      <?php foreach ($releases as $b => $r): $users = $installs[$b] ?? []; ?>
        <div class="rel<?= $b === $latestBuild ? ' is-latest' : '' ?>">
          <div class="rel-head">
            <span class="name">نسخهٔ <?= h(fa($r['version'])) ?> (ساخت <?= h(fa($b)) ?>)</span>
            <?php if ($b === $latestBuild): ?><span class="badge ok">آخرین نسخه</span><?php endif; ?>
            <?php if (isForced($r)): ?><span class="badge warn">اجباری</span>
            <?php elseif (isset($r['min_build'])): ?><span class="badge mute">اختیاری</span><?php endif; ?>
            <?php if (!empty($r['missing'])): ?><span class="badge mute">فایلش روی سرور نیست</span><?php endif; ?>
            <span class="count"><?= $installError ? '' : fa(count($users)) . ' نفر نصب دارند' ?></span>
          </div>
          <p class="meta">
            <?= !empty($r['released_at']) ? 'منتشرشده در ' . h(jdt($r['released_at'])) : 'تاریخ انتشار ثبت نشده است' ?>
            <?php if (isset($r['min_build']) && (int) $r['min_build'] > 0 && !isForced($r)): ?>
              — در زمان انتشار، نسخه‌های پایین‌تر از ساخت <?= h(fa($r['min_build'])) ?> اجباری به‌روز می‌شدند
            <?php endif; ?>
          </p>
          <?php if (trim((string) ($r['notes'] ?? '')) !== ''): ?>
            <p class="notes"><b>تغییرات:</b> <?= h($r['notes']) ?></p>
          <?php endif; ?>
          <?php if (!empty($r['files'])): ?>
            <p class="files">
              <?php $parts = []; foreach ($abiLabels as $abi => $label) {
                  if (empty($r['files'][$abi])) continue;
                  $f = $r['files'][$abi];
                  $parts[] = '<a href="' . h($f['url']) . '" download>' . h($label) . '</a>' . (!empty($f['size']) ? ' (' . h(mb($f['size'])) . ')' : '');
              } echo implode(' · ', $parts); ?>
            </p>
          <?php endif; ?>
          <?php if ($users): ?>
            <details>
              <summary>فهرست <?= fa(count($users)) ?> کاربرِ این نسخه</summary>
              <div class="table-scroll">
                <table>
                  <thead><tr><th>کاربر</th><th>سازمان</th><th>پردازنده</th><th>اولین گزارش</th><th>آخرین باز کردن برنامه</th></tr></thead>
                  <tbody>
                  <?php foreach ($users as $u): ?>
                    <tr>
                      <td><?= h($u['name'] !== '' ? $u['name'] : 'بدون نام') ?></td>
                      <td><?= h($u['org_name'] ?? '—') ?></td>
                      <td><?= h($abiLabels[$u['abi']] ?? ($u['abi'] ?: '—')) ?></td>
                      <td><?= h(jdt($u['first_seen'])) ?></td>
                      <td><?= h(jdt($u['last_seen'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </details>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>
</body>
</html>
