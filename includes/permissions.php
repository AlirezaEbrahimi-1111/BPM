<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  سیستم کنترل دسترسی مبتنی بر نقش (RBAC)
 * ═══════════════════════════════════════════════════════════════
 *  این فایل، مرجعِ واحدِ همه‌ی قوانین دسترسی است.
 *  برای افزودن/حذف یک اجازه، فقط همین فایل را ویرایش کنید.
 * ═══════════════════════════════════════════════════════════════
 */

/**
 * جدول اجازه‌ها: هر اجازه به لیست نقش‌هایی که آن را دارند نگاشت می‌شود.
 * برای افزودن اجازه‌ی جدید: یک ردیف جدید اینجا اضافه کنید.
 * برای تغییر دسترسی یک نقش: نام نقش را به لیست اضافه/حذف کنید.
 */
function getPermissionMatrix() {
    return [
        // کلید اجازه            => [نقش‌هایی که این اجازه را دارند]
        'view_superadmin_panel'   => ['superadmin'],
        'manage_users'            => ['superadmin', 'supervisor'],
        'view_all_org_tasks'      => ['superadmin', 'supervisor'],
        'view_section_tasks'      => ['superadmin', 'supervisor', 'manager'],
        'create_routine_template' => ['superadmin', 'supervisor'],
        'create_recurring_task'   => ['superadmin', 'supervisor', 'manager', 'employee'],
        'create_workflow'         => ['superadmin', 'supervisor', 'manager', 'employee'],
        'monitor_all_workflows'   => ['superadmin', 'supervisor'],
        'view_reports'            => ['superadmin', 'supervisor', 'manager'],
        'send_org_announcement'   => ['superadmin', 'supervisor'],
        'send_section_announcement' => ['superadmin', 'supervisor', 'manager'],
    ];
}

/**
 * لیست کاربرانی که superadmin هستند (دسترسی به همه‌ی سازمان‌ها).
 * فعلاً بر اساس id ثابت است؛ بعداً می‌توان به یک ستون is_super_admin منتقل کرد.
 */
function getSuperAdminIds() {
    return [1, 22];
}

/**
 * نقشِ مؤثرِ یک کاربر را برمی‌گرداند.
 * اگر id کاربر در لیست superadminها باشد، نقش او 'superadmin' در نظر گرفته می‌شود،
 * صرف‌نظر از مقدار ستون role در دیتابیس.
 *
 * $user باید آرایه‌ای شامل 'id' و 'role' باشد.
 */
function getEffectiveRole($user) {
    $uid = (int)($user['id'] ?? 0);
    if (in_array($uid, getSuperAdminIds(), true)) {
        return 'superadmin';
    }
    return $user['role'] ?? 'employee';
}

/**
 * بررسی می‌کند که آیا کاربر، اجازه‌ی مشخصی را دارد یا نه.
 *
 * مثال استفاده:
 *   if (hasPermission($user, 'manage_users')) { ... }
 *
 * $user  : آرایه‌ی اطلاعات کاربر (شامل id و role)
 * $perm  : کلید اجازه (مثل 'manage_users')
 * خروجی  : true اگر اجازه داشته باشد، وگرنه false
 */
function hasPermission($user, $perm) {
    $role = getEffectiveRole($user);
    $matrix = getPermissionMatrix();

    // اگر این اجازه اصلاً تعریف نشده باشد، برای امنیت false برمی‌گردانیم
    if (!isset($matrix[$perm])) {
        error_log("hasPermission: اجازه‌ی ناشناخته '$perm' درخواست شد");
        return false;
    }

    return in_array($role, $matrix[$perm], true);
}

/**
 * مثل hasPermission، ولی اگر اجازه نداشت، مستقیماً با خطای 403 پاسخ می‌دهد و متوقف می‌شود.
 * برای استفاده در ابتدای فایل‌های API که نیاز به دسترسی خاص دارند.
 *
 * مثال:
 *   requirePermission($user, 'manage_users');
 *   // اگر به اینجا برسد، یعنی کاربر اجازه دارد
 */
function requirePermission($user, $perm) {
    if (!hasPermission($user, $perm)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'شما دسترسی لازم برای این عملیات را ندارید'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}