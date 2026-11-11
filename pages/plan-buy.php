<?php
/**
 * pages/plan-buy.php — خرید و ارتقای اشتراک (نسخهٔ وب)
 *
 * عینا هم‌محتوا با اپ (lib/features/subscription/subscription_page.dart،
 * plan_detail_page.dart، free_plan_detail_page.dart، plan_feature_list.dart):
 * سه پلن طلایی/نقره‌ای/رایگان، انتخاب تعداد کاربر (فقط طلایی)، مدت ۱/۶/۱۲ ماهه با درصد
 * تخفیف، فهرست کامل امکانات و پرداخت زرین‌پال (api/payment/org-pay.php، return=web).
 *
 * 🔒 عمدا header.php را include نمی‌کند: سازمانِ قفل‌شده (پایان دورهٔ رایگان/پلن) هم باید
 * بتواند از وب پلن بخرد؛ گارد header.php او را به plan-required.php می‌فرستد. کاربر با
 * توکن localStorage و api/auth/profile.php (که برای سازمان قفل‌شده هم باز است) شناخته
 * می‌شود. خرید فقط برای سرپرست سازمان (سرور هم در org-pay.php چک می‌کند).
 *
 * ⚠️ قیمت‌ها از includes/plan-pricing.php (همان که سرور مبلغ را با آن حساب می‌کند).
 * ⚠️ اگر امکانات/متن‌ها در اپ عوض شد، اینجا هم عوض شود.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-pricing.php';
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/includes/version.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/version.php';
}
if (!function_exists('asset')) {
    function asset(string $p): string { return $p; }
}

// عینا plan_feature_list.dart (آیکن Material → نزدیک‌ترین Bootstrap Icon)
$FEATURES_BASE = [
    ['bi-plus-square', 'ایجاد کار (مقطعی، دوره‌ای)'],
    ['bi-check2-circle', 'مشاهده و مدیریت کارها'],
    ['bi-flag', 'تعیین اولویت برای کارها'],
    ['bi-play-circle', 'شروع و تکمیل کار'],
    ['bi-list-check', 'افزودن چک‌لیست گام‌به‌گام برای هر کار'],
    ['bi-card-text', 'درج توضیح برای کار'],
    ['bi-paperclip', 'پیوست‌کردن و ارسال فایل برای هر کار'],
    ['bi-folder2', 'گروه‌بندی کارها برای دسته‌بندی بهتر'],
    ['bi-funnel', 'فیلتر پیشرفته بر اساس وضعیت و اولویت'],
    ['bi-clock-history', 'نمایش جزئیات و تاریخچه‌ی کامل کارها'],
    ['bi-search', 'جستجوی سریع در میان همه‌ی کارها'],
];
$FEATURES_SILVER = array_merge($FEATURES_BASE, [
    ['bi-calendar-week', 'نمای برنامه‌ی روزانه، هفتگی، ماهانه'],
    ['bi-wifi', 'همگام‌سازی آنی اطلاعات به‌صورت آنلاین'],
    ['bi-person-plus', 'ایجاد کار برای همکاران'],
]);
$FEATURES_GOLD = array_merge($FEATURES_SILVER, [
    ['bi-bell', 'اعلان‌های لحظه‌ای برای کارها و رویدادها'],
    ['bi-grid-1x2', 'داشبورد مدیریتی'],
    ['bi-eye', 'نظارت بر کارهای واحدها'],
    ['bi-graph-up', 'تحلیل گلوگاه‌ها'],
    ['bi-activity', 'مانیتورینگ روتین‌ها'],
    ['bi-people', 'نمایش کاربران و کارهای دارای تأخیر'],
    ['bi-diagram-3', 'تعریف کار روتین چندمرحله‌ای (آبشاری، موازی)'],
    ['bi-send', 'ارجاع بی‌نهایت کار به فرد یا واحد'],
    ['bi-diagram-2', 'تعریف واحدهای درون‌سازمانی'],
    ['bi-chat-dots', 'پیام‌رسان درون‌سازمانی'],
    ['bi-file-text', 'لاگ کامل مستندات'],
    ['bi-display', 'دسترسی به پنل وب'],
]);

$PLANS = [
    'gold' => [
        'card_title' => 'پلن طلایی', 'card_sub' => 'سازمانی و پیشرفته',
        'card_features' => ['داشبورد و نظارت مدیریتی', 'تحلیل گلوگاه‌ها', 'روتین‌های چند مرحله‌ای', 'تعریف واحدها و ارجاع نامحدود', 'دسترسی پنل وب'],
        'title' => 'پلن طلایی (سازمانی)', 'sub' => 'مدیریت فرآیندهای کسب‌وکار',
        'desc' => 'کامل‌ترین سطح دسترسی مدیریتی، نظارتی و سازمانی',
        'icon' => '/assets/images/subscription/plan_gold.webp', 'features' => $FEATURES_GOLD,
    ],
    'silver' => [
        'card_title' => 'پلن نقره‌ای', 'card_sub' => 'تیمی و همکاران',
        'card_features' => ['نمای برنامه روزانه، هفتگی و ماهانه', 'کار تیمی و آنلاین', 'ایجاد کار برای همکاران'],
        'title' => 'پلن نقره‌ای (تیمی)', 'sub' => 'همکاری و هماهنگی تیمی',
        'desc' => 'دسترسی مشترک تیم به برنامه و کارها',
        'icon' => '/assets/images/subscription/plan_silver.webp', 'features' => $FEATURES_SILVER,
    ],
    'free' => [
        'card_title' => 'پلن رایگان', 'card_sub' => 'پایه شخصی',
        'card_features' => ['مدیریت کارهای شخصی', 'نمای ساده و پایه', 'دسترسی محدود'],
        'title' => 'پلن رایگان (شخصی)', 'sub' => 'پایه و مدیریت شخصی',
        'desc' => 'مدیریت ساده برنامه‌ها و کارهای فردی',
        'icon' => '/assets/images/subscription/plan_free.webp', 'features' => $FEATURES_BASE,
    ],
];
foreach ($PLANS as $k => $p) {
    if (!is_file($_SERVER['DOCUMENT_ROOT'] . $p['icon'])) $PLANS[$k]['icon'] = null;
}
$PRICING = ['gold' => PLAN_PRICING_GOLD, 'silver' => PLAN_PRICING_SILVER];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خرید و ارتقای اشتراک</title>
    <script>
        // تم: همان کلید هدر (bpm_theme) — پیش از رندر تا چشمک نزند
        try { var t = localStorage.getItem('bpm_theme'); if (t) document.documentElement.setAttribute('data-theme', t); } catch (e) {}
    </script>
    <link rel="stylesheet" href="<?= asset('/assets/fonts/Vazirmatn-font-face.css') ?>">
    <link rel="stylesheet" href="<?= asset('/assets/js/cdn/bootstrap-icons.css') ?>">
    <style>
        :root {
            --bg: #f5f6fa; --card: #ffffff; --text: #1f2430; --muted: #5b6272; --line: #e3e6ee;
            --primary: #8e57fe; --gold: #f5b94d; --success: #16a34a; --danger: #ef4444;
            --hover: rgba(142, 87, 254, .12); --chip: #f1edff;
        }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --bg: #14161d; --card: #1d2030; --text: #eceef5; --muted: #a6acbd; --line: #2e3348;
                --primary: #a983ff; --hover: rgba(142, 87, 254, .18); --chip: #2a2540;
            }
        }
        :root[data-theme="dark"] {
            --bg: #14161d; --card: #1d2030; --text: #eceef5; --muted: #a6acbd; --line: #2e3348;
            --primary: #a983ff; --hover: rgba(142, 87, 254, .18); --chip: #2a2540;
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: var(--bg); color: var(--text);
               font-family: Vazirmatn, Tahoma, sans-serif; padding: 20px 16px 120px; }
        .wrap { max-width: 720px; margin: 0 auto; }
        .topbar { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .topbar h1 { flex: 1; text-align: center; font-size: 1.05rem; margin: 0; }
        .icon-btn { width: 38px; height: 38px; border-radius: 50%; border: none; background: var(--card);
                    color: var(--text); cursor: pointer; display: flex; align-items: center; justify-content: center;
                    text-decoration: none; font-size: 1rem; }
        .icon-btn:hover { background: var(--hover); }
        .notice { border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; font-size: .88rem; line-height: 1.8;
                  background: var(--card); border-right: 4px solid var(--primary); }
        .notice.ok { border-color: var(--success); } .notice.err { border-color: var(--danger); }
        .notice.warn { border-color: var(--gold); }
        .card { background: var(--card); border-radius: 18px; padding: 18px; margin-bottom: 12px;
                border: 1px solid var(--line); position: relative; }
        .card.current { border-color: var(--primary); box-shadow: 0 0 0 2px var(--hover); }
        .plan-head { display: flex; align-items: center; gap: 12px; }
        .plan-icon { width: 54px; height: 54px; border-radius: 14px; object-fit: contain; background: var(--chip); padding: 6px; }
        .plan-icon.ph { display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .plan-head h2 { margin: 0; font-size: 1rem; }
        .plan-head .sub { color: var(--muted); font-size: .8rem; margin-top: 2px; }
        .status { font-size: .75rem; color: var(--success); margin-top: 4px; }
        .feat-mini { list-style: none; padding: 0; margin: 14px 0; display: grid; gap: 6px; }
        .feat-mini li { font-size: .84rem; display: flex; gap: 8px; align-items: center; }
        .feat-mini li i { color: var(--success); }
        .btn { display: inline-flex; width: 100%; align-items: center; justify-content: center; gap: 6px;
               border-radius: 12px; padding: 11px 14px; font-family: inherit; font-size: .9rem; font-weight: 700;
               cursor: pointer; border: 1.5px solid var(--primary); background: var(--primary); color: #fff; }
        .btn:hover { opacity: .92; }
        .btn.outline { background: transparent; color: var(--primary); }
        .btn.muted { border-color: var(--line); color: var(--muted); background: transparent; }
        .btn:disabled { opacity: .55; cursor: not-allowed; }
        .gold .plan-head h2 { color: var(--gold); }
        /* جزئیات پلن */
        .section-title { font-size: .9rem; font-weight: 700; margin: 18px 0 10px; }
        .chips { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .chip { border-radius: 12px; padding: 10px 6px; text-align: center; cursor: pointer; font-size: .84rem;
                border: 1.5px solid var(--line); background: var(--card); color: var(--text); font-family: inherit; }
        .chip:hover { background: var(--hover); }
        .chip.sel { border-color: var(--primary); background: var(--hover); color: var(--primary); font-weight: 700; }
        .months { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .month { position: relative; padding: 18px 6px 10px; }
        .month .price { display: block; font-size: .78rem; color: var(--muted); margin-top: 4px; font-weight: 400; }
        .month .badge { position: absolute; top: -9px; left: 50%; transform: translateX(-50%); white-space: nowrap;
                        font-size: .62rem; font-weight: 700; color: #fff; border-radius: 20px; padding: 2px 8px;
                        background: var(--success); }
        .month .badge.pop { background: var(--gold); }
        :root[data-theme="dark"] .month .badge { background: var(--primary); }
        .feat-full { list-style: none; padding: 0; margin: 0; }
        .feat-full li { display: flex; align-items: center; gap: 10px; padding: 7px 0; font-size: .84rem;
                        border-bottom: 1px dashed var(--line); }
        .feat-full li:last-child { border-bottom: none; }
        .feat-full li .fi { color: var(--primary); width: 20px; text-align: center; }
        .feat-full li span { flex: 1; }
        .feat-full li .ok { color: var(--success); }
        .checkout { position: fixed; bottom: 0; left: 0; right: 0; background: var(--card); border-top: 1px solid var(--line);
                    padding: 12px 16px; }
        .checkout .in { max-width: 720px; margin: 0 auto; display: flex; gap: 12px; align-items: center; }
        .checkout .amount { flex: 4; }
        .checkout .amount s { display: block; font-size: .78rem; color: var(--muted); }
        .checkout .amount b { font-size: 1.05rem; }
        .checkout .btn { flex: 6; }
        .hidden { display: none !important; }
        @media (max-width: 420px) { .chips { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <a class="icon-btn" id="backBtn" href="dashboard-manager.php" title="بازگشت" aria-label="بازگشت"><i class="bi bi-arrow-right"></i></a>
        <h1 id="pageTitle">خرید و ارتقای اشتراک</h1>
        <span style="width:38px"></span>
    </div>

    <div id="notice" class="notice hidden"></div>

    <!-- مرحلهٔ ۱: فهرست پلن‌ها (مثل subscription_page.dart) -->
    <div id="listView">
        <?php foreach ($PLANS as $key => $p) : ?>
        <div class="card <?= $key ?>" id="card-<?= $key ?>">
            <div class="plan-head">
                <?php if ($p['icon']) : ?>
                    <img class="plan-icon" src="<?= htmlspecialchars($p['icon']) ?>" alt="">
                <?php else : ?>
                    <div class="plan-icon ph"><i class="bi bi-gem"></i></div>
                <?php endif; ?>
                <div>
                    <h2><?= $p['card_title'] ?></h2>
                    <div class="sub"><?= $p['card_sub'] ?></div>
                    <div class="status" id="status-<?= $key ?>"></div>
                </div>
            </div>
            <ul class="feat-mini">
                <?php foreach ($p['card_features'] as $f) : ?>
                    <li><i class="bi bi-check-circle-fill"></i><?= $f ?></li>
                <?php endforeach; ?>
            </ul>
            <button class="btn" id="btn-<?= $key ?>" type="button" onclick="openPlan('<?= $key ?>')"></button>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- مرحلهٔ ۲: جزئیات و خرید (مثل plan_detail_page.dart) -->
    <div id="detailView" class="hidden">
        <div class="card">
            <div class="plan-head">
                <img class="plan-icon" id="dIcon" src="" alt="">
                <div>
                    <h2 id="dTitle"></h2>
                    <div class="sub" id="dSub"></div>
                </div>
            </div>
            <p style="color:var(--muted);font-size:.84rem;margin:12px 0 0" id="dDesc"></p>
        </div>

        <div id="buyBlock">
            <div id="usersBlock">
                <div class="section-title">تعداد کاربران</div>
                <div class="chips" id="usersChips"></div>
            </div>
            <div class="section-title">مدت زمان اشتراک</div>
            <div class="months" id="monthsBox"></div>
        </div>

        <div class="section-title">امکانات و قابلیت‌ها</div>
        <div class="card"><ul class="feat-full" id="dFeatures"></ul></div>
    </div>
</div>

<div class="checkout hidden" id="checkout">
    <div class="in">
        <div class="amount"><s id="fullPrice" class="hidden"></s><b id="price"></b></div>
        <button class="btn" type="button" id="payBtn" onclick="pay()">تأیید و رفتن به درگاه پرداخت</button>
    </div>
</div>

<script>
var PLANS   = <?= json_encode($PLANS, JSON_UNESCAPED_UNICODE) ?>;
var PRICING = <?= json_encode($PRICING) ?>;
var GOLD_RENEWAL_WINDOW_DAYS = 7; // مثل PlanAccess.goldRenewalWindowDays در اپ
var MONTHS = [{ m: 1, label: '۱ ماهه' }, { m: 6, label: '۶ ماهه' }, { m: 12, label: '۱۲ ماهه / سالانه' }];

var token = ''; try { token = localStorage.getItem('auth_token') || ''; } catch (e) {}
var me = null, sel = { plan: null, users: 10, months: 12 };

function fa(s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); }
function money(n) { return fa(Number(n).toLocaleString('en-US')) + ' تومان'; }
function faDate(ymd) {
    if (!ymd) return '';
    try { return new Date(ymd + 'T12:00:00').toLocaleDateString('fa-IR-u-ca-persian', { year: 'numeric', month: '2-digit', day: '2-digit' }); }
    catch (e) { return fa(ymd); }
}
function daysTo(ymd) {
    if (!ymd) return null;
    var t = new Date(); t.setHours(0, 0, 0, 0);
    return Math.round((new Date(ymd + 'T00:00:00') - t) / 86400000);
}
function notice(text, cls) {
    var n = document.getElementById('notice');
    n.className = 'notice ' + (cls || '');
    n.textContent = text;
}

/* ── نتیجهٔ بازگشت از درگاه (api/payment/callback.php?to=web) ── */
(function () {
    var p = new URLSearchParams(location.search).get('payment');
    var msgs = {
        success: ['پرداخت موفق بود و پلن سازمان شما به‌روزرسانی شد.', 'ok'],
        already: ['این پرداخت قبلا ثبت شده است و پلن سازمان شما فعال است.', 'ok'],
        cancel:  ['پرداخت لغو شد.', 'warn'],
        failed:  ['پرداخت ناموفق بود؛ مبلغی کسر نشده است.', 'err'],
        error:   ['در ثبت پرداخت خطایی رخ داد. اگر مبلغی کسر شده، با پشتیبانی تماس بگیرید.', 'err'],
        notfound:['پرداخت پیدا نشد.', 'err']
    };
    if (p && msgs[p]) notice(msgs[p][0], msgs[p][1]);
})();

/* ── کاربر و پلن فعلی ── */
async function loadMe() {
    if (!token) { location.href = '../index.php'; return; }
    try {
        var r = await fetch('/api/auth/profile.php', { headers: { 'Authorization': 'Bearer ' + token } });
        if (r.status === 401) { location.href = '../index.php'; return; }
        var d = await r.json();
        if (!d.success) throw new Error();
        me = d.user;
        try {
            var info = JSON.parse(localStorage.getItem('user_info') || '{}');
            ['plan', 'plan_users', 'plan_expires_at', 'web_trial_ends_at', 'web_access'].forEach(function (k) { info[k] = me[k]; });
            localStorage.setItem('user_info', JSON.stringify(info));
        } catch (e) {}
        // سازمانِ بدون دسترسی وب: بازگشت به صفحهٔ ورود، نه داشبورد (که دوباره قفل است)
        if (!me.web_access) document.getElementById('backBtn').href = 'plan-required.php';
        renderList();
    } catch (e) {
        notice('خطا در ارتباط با سرور. صفحه را دوباره باز کنید.', 'err');
    }
}

function canManage() { return me && ['supervisor', 'admin'].indexOf(me.role) !== -1; }

function renderList() {
    var cur = me.plan || 'free';
    var exp = me.plan_expires_at;
    ['gold', 'silver', 'free'].forEach(function (k) {
        var st = document.getElementById('status-' + k);
        var card = document.getElementById('card-' + k);
        var btn = document.getElementById('btn-' + k);
        var isCur = cur === k;
        card.classList.toggle('current', isCur);
        st.textContent = isCur ? (k === 'free' ? 'فعال' : (exp ? 'فعال تا ' + faDate(exp) : 'فعال')) : '';
        btn.className = 'btn';
        if (k === 'gold') {
            var d = daysTo(exp);
            var renewable = !isCur || (exp && d !== null && d <= GOLD_RENEWAL_WINDOW_DAYS);
            btn.textContent = isCur && !renewable ? 'پلن فعلی شما' : (isCur ? 'تمدید پلن طلایی' : 'انتخاب پلن طلایی');
            if (isCur && !renewable) btn.className = 'btn outline';
        } else if (k === 'silver') {
            btn.textContent = isCur ? 'پلن فعلی شما' : 'انتخاب پلن نقره‌ای';
            if (isCur) btn.className = 'btn outline';
        } else {
            btn.textContent = isCur ? 'پلن فعلی شما' : 'مشاهده پلن رایگان';
            btn.className = 'btn muted';
        }
    });
    if (!canManage()) {
        notice('خرید و تمدید پلن فقط با حساب سرپرست سازمان امکان‌پذیر است. لطفا به سرپرست سازمان اطلاع دهید.', 'warn');
    } else if (cur === 'free' && me.web_trial_ends_at) {
        var left = daysTo(me.web_trial_ends_at);
        if (left !== null && left <= 0 && !new URLSearchParams(location.search).get('payment')) {
            notice('دورهٔ رایگان ۱۴ روزهٔ سازمان شما تمام شده است. با خرید پلن، دسترسی همهٔ کارکنان و همهٔ اطلاعات بدون تغییر برمی‌گردد.', 'warn');
        }
    }
}

/* ── جزئیات پلن ── */
function openPlan(k) {
    var cur = me ? (me.plan || 'free') : 'free';
    if (k === 'gold' && cur === 'gold') {
        var d = daysTo(me.plan_expires_at);
        if (!me.plan_expires_at || d > GOLD_RENEWAL_WINDOW_DAYS) { showDetail(k, false); return; }
    }
    if (k === 'silver' && cur === 'silver') { showDetail(k, false); return; }
    showDetail(k, k !== 'free');
}

function showDetail(k, purchasable) {
    sel.plan = k;
    var p = PLANS[k];
    document.getElementById('listView').classList.add('hidden');
    document.getElementById('detailView').classList.remove('hidden');
    document.getElementById('pageTitle').textContent = p.title;
    var icon = document.getElementById('dIcon');
    if (p.icon) { icon.src = p.icon; icon.classList.remove('hidden'); } else icon.classList.add('hidden');
    document.getElementById('dTitle').textContent = p.title;
    document.getElementById('dSub').textContent = p.sub;
    document.getElementById('dDesc').textContent = p.desc;
    document.getElementById('dFeatures').innerHTML = p.features.map(function (f) {
        return '<li><i class="bi ' + f[0] + ' fi"></i><span>' + f[1] + '</span><i class="bi bi-check-circle-fill ok"></i></li>';
    }).join('');
    document.getElementById('buyBlock').classList.toggle('hidden', !purchasable);
    document.getElementById('usersBlock').classList.toggle('hidden', k !== 'gold');
    document.getElementById('checkout').classList.toggle('hidden', !purchasable);
    document.getElementById('backBtn').onclick = function (e) { e.preventDefault(); backToList(); };
    if (purchasable) { renderUsers(); renderMonths(); }
    window.scrollTo(0, 0);
}

function backToList() {
    document.getElementById('detailView').classList.add('hidden');
    document.getElementById('checkout').classList.add('hidden');
    document.getElementById('listView').classList.remove('hidden');
    document.getElementById('pageTitle').textContent = 'خرید و ارتقای اشتراک';
    document.getElementById('backBtn').onclick = null;
}

function priceOf(months) { return sel.plan === 'gold' ? PRICING.gold[sel.users][months] : PRICING.silver[months]; }
function monthlyRate() { return priceOf(1); }
function discountPct(m) {
    if (m === 1) return 0;
    var full = monthlyRate() * m, actual = priceOf(m);
    return full <= actual ? 0 : Math.round(100 - actual * 100 / full);
}

function renderUsers() {
    if (sel.plan !== 'gold') return;
    document.getElementById('usersChips').innerHTML = Object.keys(PRICING.gold).map(function (n) {
        n = Number(n);
        return '<button type="button" class="chip' + (n === sel.users ? ' sel' : '') + '" onclick="sel.users=' + n + ';renderUsers();renderMonths()">' + fa(n + ' کاربر') + '</button>';
    }).join('');
}

function renderMonths() {
    document.getElementById('monthsBox').innerHTML = MONTHS.map(function (o) {
        var pct = discountPct(o.m);
        var badge = o.m === 12 ? '<span class="badge pop"><i class="bi bi-star-fill"></i> محبوب‌ترین</span>'
                  : (pct > 0 ? '<span class="badge">' + fa('٪' + pct + ' تخفیف') + '</span>' : '');
        return '<button type="button" class="chip month' + (o.m === sel.months ? ' sel' : '') + '" onclick="sel.months=' + o.m + ';renderMonths()">'
             + badge + o.label + '<span class="price">' + money(priceOf(o.m)) + '</span></button>';
    }).join('');
    var full = monthlyRate() * sel.months, price = priceOf(sel.months);
    var fp = document.getElementById('fullPrice');
    fp.textContent = fa(Number(full).toLocaleString('en-US'));
    fp.classList.toggle('hidden', !(full > price));
    document.getElementById('price').textContent = money(price);
    document.getElementById('price').style.color = sel.plan === 'gold' ? 'var(--gold)' : 'var(--primary)';
    document.getElementById('payBtn').disabled = !canManage();
}

/* ── پرداخت ── */
async function pay() {
    if (!canManage()) return;
    var btn = document.getElementById('payBtn');
    btn.disabled = true;
    var label = btn.textContent;
    btn.textContent = 'در حال اتصال به درگاه...';
    try {
        var body = { plan: sel.plan, months: sel.months, return: 'web' };
        if (sel.plan === 'gold') body.plan_users = sel.users;
        var r = await fetch('/api/payment/org-pay.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
            body: JSON.stringify(body)
        });
        var d = await r.json();
        if (d.success && d.payment_url) { location.href = d.payment_url; return; }
        notice(d.message || 'خطا در اتصال به درگاه', 'err');
    } catch (e) {
        notice('خطا در اتصال به سرور', 'err');
    }
    btn.disabled = false;
    btn.textContent = label;
    window.scrollTo(0, 0);
}

loadMe();
</script>
</body>
</html>
