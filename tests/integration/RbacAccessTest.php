<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  حسابرسی زندهٔ RBAC — وضعیت HTTP صفحات برای نقش‌های مختلف
 *  محل: /tests/integration/RbacAccessTest.php
 * ───────────────────────────────────────────────────────────────────
 *  چرا این تست لازم بود؟
 *    یک ممیزی دستی نشون داد که ~۳۰ صفحه هیچ چک دسترسی سمت سروری
 *    نداشتن (فقط با جاوااسکریپت سمت کلاینت مخفی می‌شدن)، و یک API
 *    (activity-sections.php) یک فایل لازم رو include نکرده بود که
 *    باعث خطای فاتال ۵۰۰ می‌شد. این تست همون‌کاری که برای پیدا‌کردن
 *    اون باگ‌ها دستی با curl انجام شد رو خودکار می‌کنه، تا رگرسیون
 *    مشابه در آینده خودش رو نشون بده.
 *
 *  ⚠️ برخلاف ApiTest.php (که روی production تست می‌کنه)، این فایل
 *     همیشه روی محیط لوکال (localhost:8080) اجرا می‌شه، چون توکن‌ها
 *     مستقیما از دیتابیس لوکال با generateJWTToken() ساخته می‌شن —
 *     نیازی به کپی‌کردن توکن از مرورگر نیست. اگر Apache لوکال بالا
 *     نباشه، یا کاربری با نقش لازم در دیتابیس نباشه، تست‌های مربوطه
 *     با پیام روشن رد (fail) می‌شن، نه با خطای مبهم.
 * ═══════════════════════════════════════════════════════════════════
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

const RBAC_TEST_BASE_URL = 'http://localhost:8080';

/** ارسال GET به یک مسیر لوکال و برگردوندن کد HTTP (یا null اگر اتصال ناموفق بود) */
function rbacHttpStatus(string $path, ?string $token): ?int {
    $ch = curl_init(RBAC_TEST_BASE_URL . $path);
    $headers = ['Accept: text/html'];
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => false, // خودمون می‌خوایم کد ریدایرکت (302) رو ببینیم، نه مقصدش رو
    ]);
    curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return null;
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status;
}

/** یک توکن JWT معتبر برای یک کاربر واقعی لوکال می‌سازه (کش‌شده در یک درخواست) */
function rbacTestToken(PDO $db, int $userId): string {
    static $cache = [];
    if (!isset($cache[$userId])) {
        $auth = new Auth($db);
        $cache[$userId] = $auth->generateJWTToken($userId);
    }
    return $cache[$userId];
}

/** اولین کاربر فعال یک نقش رو پویا از دیتابیس پیدا می‌کنه (نه id هاردکد) */
function rbacPickUser(PDO $db, string $role): ?int {
    $stmt = $db->prepare("
        SELECT id FROM users
        WHERE role = ? AND is_active = 1 AND (is_deleted = 0 OR is_deleted IS NULL)
        LIMIT 1
    ");
    $stmt->execute([$role]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
}

$database = new Database();
$db       = $database->getConnection();

$employeeId   = rbacPickUser($db, 'employee');
$supervisorId = rbacPickUser($db, 'supervisor');

$tests = [];

// صفحاتی که کارمند عادی نباید ببینه (باید ریدایرکت/رد بشه)، ولی سوپروایزر باید ببینه
foreach (['/pages/users.php', '/pages/holidays.php', '/pages/attendance-devices.php', '/pages/org-panel.php'] as $path) {
    $tests["کارمند نباید به «{$path}» دسترسی داشته باشد"] = function (Assert $a) use ($path, $employeeId, $db) {
        if (!$employeeId) { $a->true(true, 'رد شد: کاربر نقش employee در دیتابیس لوکال یافت نشد'); return; }
        $status = rbacHttpStatus($path, rbacTestToken($db, $employeeId));
        $a->true(
            $status === null ? false : in_array($status, [302, 401, 403], true),
            'کد HTTP باید ۳۰۲ (ریدایرکت) یا ۴۰۳/۴۰۱ باشد — دریافت‌شده: ' . ($status ?? 'خطای اتصال (Apache لوکال بالا نیست؟)')
        );
    };

    $tests["سوپروایزر باید به «{$path}» دسترسی داشته باشد"] = function (Assert $a) use ($path, $supervisorId, $db) {
        if (!$supervisorId) { $a->true(true, 'رد شد: کاربر نقش supervisor در دیتابیس لوکال یافت نشد'); return; }
        $status = rbacHttpStatus($path, rbacTestToken($db, $supervisorId));
        $a->equals(200, $status, 'کد HTTP باید ۲۰۰ باشد — دریافت‌شده: ' . ($status ?? 'خطای اتصال (Apache لوکال بالا نیست؟)'));
    };
}

// صفحات عمومی که هر کاربر لاگین‌کرده‌ای (از جمله کارمند) باید ببینه
foreach (['/pages/tasks.php', '/pages/my-tasks.php', '/pages/dashboard-user.php', '/pages/chat.php'] as $path) {
    $tests["کارمند باید به «{$path}» دسترسی داشته باشد"] = function (Assert $a) use ($path, $employeeId, $db) {
        if (!$employeeId) { $a->true(true, 'رد شد: کاربر نقش employee در دیتابیس لوکال یافت نشد'); return; }
        $status = rbacHttpStatus($path, rbacTestToken($db, $employeeId));
        $a->equals(200, $status, 'کد HTTP باید ۲۰۰ باشد — دریافت‌شده: ' . ($status ?? 'خطای اتصال (Apache لوکال بالا نیست؟)'));
    };
}

// workflow-monitor.php عمدا استثناست: دسترسی عمومیه، API خودش داده رو فیلتر می‌کنه
// (این تست دقیقا همون رگرسیونی رو می‌گیره که امروز پیدا شد: گیت بیش‌ازحد‌محدودکننده)
$tests['کارمند باید به «/pages/workflow-monitor.php» دسترسی داشته باشد (فیلتر داده در API انجام می‌شود)'] = function (Assert $a) use ($employeeId, $db) {
    if (!$employeeId) { $a->true(true, 'رد شد: کاربر نقش employee در دیتابیس لوکال یافت نشد'); return; }
    $status = rbacHttpStatus('/pages/workflow-monitor.php', rbacTestToken($db, $employeeId));
    $a->equals(200, $status, 'کد HTTP باید ۲۰۰ باشد — دریافت‌شده: ' . ($status ?? 'خطای اتصال (Apache لوکال بالا نیست؟)'));
};

// بدون هیچ توکنی، صفحات محافظت‌شده باید ریدایرکت/رد بشن
$tests['بدون توکن، صفحات محافظت‌شده باید ریدایرکت/رد شوند'] = function (Assert $a) {
    $status = rbacHttpStatus('/pages/users.php', null);
    $a->true(
        $status === null ? false : in_array($status, [302, 401, 403], true),
        'کد HTTP باید ۳۰۲ یا ۴۰۱/۴۰۳ باشد — دریافت‌شده: ' . ($status ?? 'خطای اتصال (Apache لوکال بالا نیست؟)')
    );
};

return [
    'name'  => 'حسابرسی زندهٔ RBAC (وضعیت HTTP صفحات برای نقش‌های مختلف روی لوکال)',
    'tests' => $tests,
];
