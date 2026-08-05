<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  permissions.php — سیستم کنترل دسترسی (RBAC + ACL)
 *  محل: /includes/permissions.php
 * ───────────────────────────────────────────────────────────────────
 *
 *  ┌─ اصل بنیادین ───────────────────────────────────────────────┐
 *  │                                                              │
 *  │  role             = اختیار    (تنها مرجع کنترل دسترسی)      │
 *  │  activity_section = جایگاه    (فقط برای ارجاع کار و گزارش)  │
 *  │                                                              │
 *  │  ❌ هرگز activity_section را برای دسترسی چک نکنید.          │
 *  │     «واحد مدیریت» یک دپارتمان است، نه یک سطح اختیار.        │
 *  └──────────────────────────────────────────────────────────────┘
 *
 *  ┌─ سه لایهٔ تصمیم ─────────────────────────────────────────────┐
 *  │                                                              │
 *  │  ۱) سوپرادمین  →  دسترسی کامل                               │
 *  │  ۲) اختیار فردی →  مدیر سازمان به کاربر خاصی اجازهٔ         │
 *  │                    مشخصی داده (مثل ساخت روتین)              │
 *  │  ۳) نقش         →  اجازه‌های پایه بر اساس role              │
 *  │                                                              │
 *  └──────────────────────────────────────────────────────────────┘
 *
 *  استفاده در APIها:
 *      require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
 *
 *      $me = loadUserForPermissions($db, $user_id);
 *      requirePermission($me, 'manage_users');   // اگر مجاز نبود، 403 و exit
 *
 *  یا برای تصمیم شرطی (بدون قطع اجرا):
 *      if (hasPermission($me, 'view_all_org_tasks')) { ... }
 * ═══════════════════════════════════════════════════════════════════
 */


/* ═══════════════════════════════════════════════════════════════
   بخش ۱ — سوپرادمین‌ها
   ═══════════════════════════════════════════════════════════════ */

/**
 * شناسهٔ کاربرانی که دسترسی کامل به کل سامانه دارند.
 *
 * ⚠️ این تنها جایی است که شناسهٔ عددی مجاز است.
 *    قبلاً `$user_id === 1` در چند فایل پراکنده بود — همه حذف شد.
 */
function getSuperAdminIds(): array
{
    return [1, 19];
}


/* ═══════════════════════════════════════════════════════════════
   بخش ۲ — جدول اجازه‌های هر نقش
   ═══════════════════════════════════════════════════════════════ */

/**
 * اجازه‌های پایهٔ هر نقش.
 *
 * برای افزودن اجازهٔ جدید، فقط همین‌جا اضافه کنید.
 * برای افزودن نقش جدید، یک کلید جدید بسازید.
 */
function getPermissionMatrix(): array
{
    $supervisorPermissions = [
        'manage_users',
        'manage_activity_sections',
        'view_org_settings',
        'manage_task_groups',
        'view_all_org_tasks',
        'view_section_tasks',
        'create_task',
        'create_recurring_task',
        'create_workflow',
        'create_routine_template',
        'monitor_all_workflows',
        'approve_deadline_request',
        'approve_overdue_clear',
        'view_reports',
        'view_payroll',
        'send_org_announcement',
        'send_section_announcement',
        'grant_user_permissions',   // اجازهٔ دادنِ اختیار فردی به دیگران
    ];

    return [

        // ── سرپرست سازمان: کل سازمان ──────────────────────
        'supervisor' => $supervisorPermissions,

        // ── مقدار قدیمیِ role — همیشه معادلِ supervisor رفتار
        //    کرده (در delete-user.php/update-user.php/... چک می‌شد)
        //    و دیگر از رابط کاربری قابل‌انتخاب نیست؛ برای سازگاری
        //    با دادهٔ قدیمی، همان اجازه‌های supervisor را می‌گیرد.
        'admin' => $supervisorPermissions,

        // ── مدیر: فقط خودش + زیرمجموعه‌اش (زنجیرهٔ manager_id) ──
        // ⚠️ 'manage_users' اینجا فقط یک مجوزِ «درشت» است؛ محدودشدن
        //    به زیرمجموعه از طریق canManageTargetUser() انجام می‌شود،
        //    نه این جدول. هرجا این اجازه استفاده می‌شود، باید حتماً
        //    با canManageTargetUser() ترکیب شود، نه isSameOrganization().
        'manager' => [
            'manage_users',
            'view_section_tasks',
            'create_task',
            'create_recurring_task',
            'create_workflow',
            'approve_deadline_request',
            'approve_overdue_clear',
            'view_reports',
            'send_section_announcement',
        ],

        // ── کارمند ───────────────────────────────────────
        'employee' => [
            'create_task',
            'create_recurring_task',
        ],
    ];
}


/* ═══════════════════════════════════════════════════════════════
   بخش ۳ — اختیارات فردی (لایهٔ سوم)
   ═══════════════════════════════════════════════════════════════ */

/**
 * نگاشت ستون‌های دیتابیس به اجازه‌ها.
 *
 * مدیر سازمان از صفحهٔ users می‌تواند این سوییچ‌ها را برای هر
 * کاربر روشن/خاموش کند — فارغ از نقش آن کاربر.
 *
 * مثال واقعی: کاربر «فاطمه وحدتی‌پور» نقشش employee است، اما مدیر
 * سازمان به او اجازهٔ ساخت روتین و فرآیند داده است.
 */
function getIndividualGrantMap(): array
{
    return [
        'can_create_routine'  => 'create_routine_template',
        'can_create_workflow' => 'create_workflow',
    ];
}


/* ═══════════════════════════════════════════════════════════════
   بخش ۴ — بارگذاری کاربر
   ═══════════════════════════════════════════════════════════════ */

/**
 * اطلاعات لازم برای تصمیم‌گیری دربارهٔ دسترسی را از دیتابیس می‌خواند.
 *
 * ⚠️ حتماً از این تابع استفاده کنید. اگر کوئری دستی بنویسید و
 *    ستونی (مثلاً can_create_workflow) را جا بیندازید، تصمیم اشتباه
 *    گرفته می‌شود.
 */
function loadUserForPermissions(PDO $db, int $userId): ?array
{
    $stmt = $db->prepare("
        SELECT id, role, organization_id, activity_section,
               can_create_routine, can_create_workflow,
               is_active, is_deleted
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user)                              return null;
    if ((int) $user['is_active'] !== 1)      return null;
    if ((int) ($user['is_deleted'] ?? 0) === 1) return null;

    return $user;
}


/* ═══════════════════════════════════════════════════════════════
   بخش ۵ — منطق تصمیم
   ═══════════════════════════════════════════════════════════════ */

/** آیا این کاربر سوپرادمین است؟ */
function isSuperAdmin(?array $user): bool
{
    if (!$user || !isset($user['id'])) return false;
    return in_array((int) $user['id'], getSuperAdminIds(), true);
}

/**
 * آیا این کاربر اجازهٔ مشخصی را دارد؟
 *
 * ترتیب بررسی:
 *   ۱) سوپرادمین؟           → بله
 *   ۲) اختیار فردی صریح؟    → همان مقدار
 *   ۳) نقش                  → طبق جدول
 */
function hasPermission(?array $user, string $permission): bool
{
    if (!$user) return false;

    // ── لایهٔ ۱: سوپرادمین ──────────────────────────────
    if (isSuperAdmin($user)) {
        return true;
    }

    // ── لایهٔ ۲: اختیار فردی ────────────────────────────
    // اگر مدیر سازمان صریحاً این اجازه را به کاربر داده باشد،
    // بر نقشش اولویت دارد.
    foreach (getIndividualGrantMap() as $column => $grantedPermission) {
        if ($grantedPermission === $permission
            && isset($user[$column])
            && (int) $user[$column] === 1) {
            return true;
        }
    }

    // ── لایهٔ ۳: نقش ────────────────────────────────────
    $role   = $user['role'] ?? 'employee';
    $matrix = getPermissionMatrix();

    if (!isset($matrix[$role])) {
        // نقش ناشناخته → هیچ اجازه‌ای نده (اصل احتیاط)
        error_log("permissions: نقش ناشناخته «{$role}» برای کاربر #{$user['id']}");
        return false;
    }

    return in_array($permission, $matrix[$role], true);
}

/**
 * اگر کاربر اجازه نداشته باشد، پاسخ ۴۰۳ می‌دهد و اجرا را قطع می‌کند.
 *
 * این تابع برای APIهاست. در صفحات HTML از hasPermission استفاده کنید.
 */
function requirePermission(?array $user, string $permission): void
{
    if (hasPermission($user, $permission)) {
        return;
    }

    error_log('Permission denied | user_id=' . ($user['id'] ?? 'guest') . ' | role=' . ($user['role'] ?? 'unknown') . ' | permission=' . $permission . ' | uri=' . ($_SERVER['REQUEST_URI'] ?? 'unknown') . ' | ip=' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'دسترسی غیرمجاز'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * فهرست کامل اجازه‌های یک کاربر.
 * مفید برای ارسال به فرانت‌اند (تا دکمه‌های غیرمجاز نمایش داده نشوند).
 */
function getUserPermissions(?array $user): array
{
    if (!$user) return [];

    if (isSuperAdmin($user)) {
        // سوپرادمین همهٔ اجازه‌ها را دارد
        $all = [];
        foreach (getPermissionMatrix() as $perms) {
            $all = array_merge($all, $perms);
        }
        return array_values(array_unique($all));
    }

    $role   = $user['role'] ?? 'employee';
    $matrix = getPermissionMatrix();
    $perms  = $matrix[$role] ?? [];

    // افزودن اختیارات فردی
    foreach (getIndividualGrantMap() as $column => $permission) {
        if (isset($user[$column]) && (int) $user[$column] === 1) {
            $perms[] = $permission;
        }
    }

    return array_values(array_unique($perms));
}


/* ═══════════════════════════════════════════════════════════════
   بخش ۶ — کمکی: هم‌سازمان بودن
   ═══════════════════════════════════════════════════════════════ */

/**
 * آیا کاربر و منبع (کار، گروه، …) در یک سازمان‌اند؟
 *
 * سوپرادمین از این قید مستثناست.
 */
function isSameOrganization(?array $user, $resourceOrgId): bool
{
    if (!$user) return false;
    if (isSuperAdmin($user)) return true;

    return (int) ($user['organization_id'] ?? 0) === (int) $resourceOrgId;
}

/**
 * آیا این کاربر در سطحِ سازمانی (supervisor/admin/سوپرادمین) است؟
 *
 * برای اقداماتی که عمداً به «manager» داده نمی‌شود (مثلاً بازگردانیِ
 * کاربرِ حذف‌شده — یک عملیاتِ حساس‌تر از مدیریتِ روزمرهٔ زیرمجموعه).
 * برخلاف manage_users در جدولِ اجازه‌ها (که manager هم دارد)، این
 * تابع صرفاً «همان‌قدیمیِ admin/supervisor» را برمی‌گرداند.
 */
function isOrgWideRole(?array $user): bool
{
    if (!$user) return false;
    if (isSuperAdmin($user)) return true;
    return in_array($user['role'] ?? '', ['supervisor', 'admin'], true);
}


/* ═══════════════════════════════════════════════════════════════
   بخش ۷ — کمکی: دامنهٔ «مدیر» (زنجیرهٔ manager_id)
   ───────────────────────────────────────────────────────────────
   مدل سه‌نقشی:
     • supervisor/admin → کل سازمان (isSameOrganization)
     • manager          → فقط خودش + زیرمجموعه‌اش (این بخش)
     • employee         → فقط خودش
   ═══════════════════════════════════════════════════════════════ */

/**
 * شناسهٔ همهٔ زیردستانِ یک مدیر — با هر عمقی (زنجیرهٔ کاملِ manager_id)،
 * نه فقط زیردستانِ مستقیم.
 *
 * ⚠️ محافظِ حلقه: اگر دادهٔ manager_id به‌اشتباه حلقه بسازد
 *    (مثلاً A مدیرِ B و B مدیرِ A)، با مجموعهٔ visited و سقفِ ۵۰۰
 *    مرحله، هرگز در حلقهٔ بی‌نهایت گیر نمی‌افتد.
 *
 * ⚠️ مرزِ سازمان: حتی اگر دادهٔ manager_id به‌اشتباه به کاربرِ
 *    سازمانِ دیگری اشاره کند، این تابع هرگز از مرزِ سازمانِ خودِ
 *    مدیر عبور نمی‌کند (شرطِ organization_id در هر کوئری).
 *
 * @return int[] شناسه‌های زیردستان (بدون خودِ مدیر)
 */
function getSubordinateIds(PDO $db, int $managerId): array
{
    $orgStmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $orgStmt->execute([$managerId]);
    $orgId = $orgStmt->fetchColumn();
    if (!$orgId) return [];

    $subordinates = [];
    $visited      = [$managerId => true];
    $frontier     = [$managerId];
    $guard        = 0;

    while (!empty($frontier) && $guard++ < 500) {
        $placeholders = implode(',', array_fill(0, count($frontier), '?'));
        $stmt = $db->prepare("
            SELECT id FROM users
            WHERE manager_id IN ($placeholders)
              AND organization_id = ?
              AND (is_deleted = 0 OR is_deleted IS NULL)
        ");
        $stmt->execute(array_merge($frontier, [$orgId]));

        $next = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $id = (int) $id;
            if (isset($visited[$id])) continue;   // جلوگیری از حلقه
            $visited[$id]   = true;
            $subordinates[] = $id;
            $next[]         = $id;
        }
        $frontier = $next;
    }

    return $subordinates;
}

/**
 * آیا کاربرِ جاری اجازه دارد روی کاربرِ هدف عملیاتِ مدیریتی
 * (ویرایش/حذف/فعال‌سازی/تغییرِ واحد و ...) انجام دهد؟
 *
 * قاعده:
 *   ۱) سوپرادمین                    → همیشه بله
 *   ۲) خودش                         → همیشه بله
 *   ۳) supervisor/admin هم‌سازمان   → بله (کل سازمان)
 *   ۴) manager                      → فقط اگر targetUserId در
 *                                      زیرمجموعه‌اش باشد
 *   ۵) در غیر این صورت              → خیر
 */
function canManageTargetUser(PDO $db, ?array $actingUser, int $targetUserId): bool
{
    if (!$actingUser) return false;
    if (isSuperAdmin($actingUser)) return true;
    if ((int) ($actingUser['id'] ?? 0) === $targetUserId) return true;

    $role = $actingUser['role'] ?? 'employee';

    if (in_array($role, ['supervisor', 'admin'], true)) {
        $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        $targetOrg = $stmt->fetchColumn();
        return $targetOrg !== false && isSameOrganization($actingUser, $targetOrg);
    }

    if ($role === 'manager') {
        $subordinates = getSubordinateIds($db, (int) $actingUser['id']);
        return in_array($targetUserId, $subordinates, true);
    }

    return false;
}