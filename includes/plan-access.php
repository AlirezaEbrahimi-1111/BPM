<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  دسترسی بر اساس پلن سازمان (free | silver | gold) و تست وب.
 *  منبع حقیقت: organizations.plan / plan_expires_at / web_trial_ends_at.
 *
 *  کلاینت: هدر X-Client — «web» از صفحات وب، «app» از اپ موبایل.
 *  نبودنِ هدر = app (نسخه‌های قدیمیِ اپ هدر نمی‌فرستند).
 *
 *  قاعده‌ی زمانی: دسترسی وقتی معتبره که امروز < plan_expires_at باشد
 *  (روزِ انقضا خودش بی‌دسترسی است). NULL = دائمی.
 * ═══════════════════════════════════════════════════════════════════
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';

const PLAN_RANK = ['free' => 0, 'silver' => 1, 'gold' => 2];

// حداقلِ سطحِ لازم برای هر امکان (طبق ماتریس تأییدشده)
const PLAN_FEATURE_MIN = [
    'create_for_others' => 'silver',   // ساخت کار برای دیگران
    'delegated_tasks'   => 'silver',   // کارهای واگذارشده
    'daily_tasks'       => 'silver',   // برنامه کاری
    'user_management'   => 'silver',   // مدیریت کاربران
    'delegate'          => 'gold',     // ارجاع
    'routine'           => 'gold',     // کار روتین
    'history_share'     => 'gold',     // نمایش تاریخچه برای ارجاع‌شونده
    'viewers'           => 'gold',     // بینندگان در جزئیات کار
    'chat'              => 'gold',     // گفتگو
    'notifications'     => 'gold',     // اعلان‌ها
    'attendance_checkin'=> 'gold',     // ورود و خروج
    'task_overview'     => 'gold',     // نظارت بر کارها (نقره‌ای مجاز نیست)
    'monitoring'        => 'gold',     // سایر نظارت (گزارش‌ها)
    'admin_other'       => 'gold',     // سایر مدیریت (تعطیلات، دستگاه، حضور…)
];

function planTehranToday(): string
{
    return (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
}

/**
 * وضعیت واقعیِ پلن یک سازمان در همین لحظه (با درنظرگرفتنِ انقضا).
 * @return array{plan:string, plan_users:int, plan_expires_at:?string, web_trial_ends_at:?string, web_access:bool, effective_plan:string}
 */
function planState(PDO $db, int $orgId): array
{
    $stmt = $db->prepare("SELECT plan, plan_users, plan_expires_at, web_trial_ends_at FROM organizations WHERE id = ?");
    $stmt->execute([$orgId]);
    $org = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$org) {
        return [
            'plan' => 'free', 'plan_users' => 1, 'plan_expires_at' => null,
            'web_trial_ends_at' => null, 'web_access' => false, 'effective_plan' => 'free',
        ];
    }

    $today = planTehranToday();
    $expires = $org['plan_expires_at'] ? substr($org['plan_expires_at'], 0, 10) : null;
    $paidActive = in_array($org['plan'], ['silver', 'gold'], true) && ($expires === null || $today < $expires);
    $effective = $paidActive ? $org['plan'] : 'free';

    $trialEnds = $org['web_trial_ends_at'] ? substr($org['web_trial_ends_at'], 0, 10) : null;
    $trialActive = $trialEnds !== null && $today < $trialEnds;

    return [
        'plan' => $effective,
        'plan_users' => (int) $org['plan_users'],
        'plan_expires_at' => $expires,
        'web_trial_ends_at' => $trialEnds,
        'web_access' => $trialActive || ($effective === 'gold'),
        'effective_plan' => $effective,
    ];
}

/** تصمیمِ خالص (بدون DB): آیا این کلاینت به این امکان دسترسی دارد؟ */
function planAllows(array $state, string $client, string $feature): bool
{
    if ($client === 'web') {
        if (!$state['web_access']) return false;
        $level = 'gold';
    } else {
        $level = $state['effective_plan'];
    }
    return PLAN_RANK[$level] >= PLAN_RANK[PLAN_FEATURE_MIN[$feature]];
}

/** کلاینتِ فعلی: 'web' یا 'app' */
function planClient(): string
{
    $h = strtolower(trim($_SERVER['HTTP_X_CLIENT'] ?? ''));
    return $h === 'web' ? 'web' : 'app';
}

/**
 * اگر کاربر به امکانی دسترسی ندارد، 403 با کد PLAN_RESTRICTED می‌دهد و خارج می‌شود.
 * - اپ: سطحِ پلنِ سازمان.
 * - وب: اگر web_access نداشته باشد، بسته؛ اگر داشته باشد، سطحِ طلایی (تست وب و طلایی).
 */
function requirePlanFeature(int $userId, string $feature): void
{
    if (!isset(PLAN_FEATURE_MIN[$feature])) {
        throw new InvalidArgumentException("ناشناخته: {$feature}");
    }
    if ($userId <= 0) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $orgId = (int) $stmt->fetchColumn();

    if (!planAllows(planState($db, $orgId), planClient(), $feature)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'code' => 'PLAN_RESTRICTED',
            'message' => 'این امکان در پلن سازمان شما فعال نیست',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/** آیا سازمان به سقف کاربر رسیده؟ (کاربران موجود حذف نمی‌شوند؛ فقط ساختِ جدید بسته می‌شود) */
function planUserLimitReached(PDO $db, int $orgId): bool
{
    $state = planState($db, $orgId);
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE organization_id = ? AND COALESCE(is_deleted, 0) = 0");
    $stmt->execute([$orgId]);
    return (int) $stmt->fetchColumn() >= $state['plan_users'];
}

/** آیا کاربرِ وارد‌شده (نشست وب) به نسخه‌ی وب دسترسی دارد؟ */
function planWebAccessForUser(PDO $db, int $userId): bool
{
    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $orgId = (int) $stmt->fetchColumn();
    return planState($db, $orgId)['web_access'];
}

/** فیلدهای پلن برای پاسخ لاگین و پروفایل */
function planPayload(PDO $db, int $orgId): array
{
    $st = planState($db, $orgId);
    $usedStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE organization_id = ? AND is_active = 1 AND COALESCE(is_deleted, 0) = 0");
    $usedStmt->execute([$orgId]);
    return [
        'plan' => $st['plan'],
        'plan_users' => $st['plan_users'],
        'plan_users_used' => (int) $usedStmt->fetchColumn(),
        'plan_expires_at' => $st['plan_expires_at'],
        'web_trial_ends_at' => $st['web_trial_ends_at'],
        'web_access' => $st['web_access'],
    ];
}
