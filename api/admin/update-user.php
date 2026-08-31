<?php

header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user-sections.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

$user_id = requireAuth();

$database = new Database();
$db = $database->getConnection();

$me = loadUserForPermissions($db, $user_id);
requirePermission($me, 'manage_users');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'داده ناقص است']);
    exit;
}

$uid = (int)$input['user_id'];

// ─── اعتبارسنجی پایه ────────────────────────────────────
if (empty($input['first_name']) || empty($input['last_name'])) {
    echo json_encode(['success' => false, 'message' => 'نام و نام خانوادگی اجباری است']);
    exit;
}
// نام نباید کاراکترهای خطرناکِ HTML/کنترلی داشته باشد (رقم/نقطه/پرانتز مجاز)
if (preg_match('/[<>"\'`\x00-\x1F]/', $input['first_name'] . $input['last_name'])) {
    echo json_encode(['success' => false, 'message' => 'نام یا نام خانوادگی شامل کاراکترهای غیرمجاز است']);
    exit;
}
if (!preg_match('/^09[0-9]{9}$/', $input['phone'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'شماره موبایل نامعتبر است']);
    exit;
}

// 🔒 خط قرمز: supervisor/admin فقط در سازمانِ خودشان، manager فقط روی
// زیرمجموعهٔ خودش (زنجیرهٔ manager_id) — وگرنه اطلاعات (از جمله رمز
// عبور) کاربری خارج از دامنهٔ مجاز تغییر می‌کند
if (!canManageTargetUser($db, $me, $uid)) {
    echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
    exit;
}

// 🔒 جلوگیری از بالا بردنِ سطحِ دسترسی از راهِ فیلدهای درخواست:
//  - نقش فقط از لیستِ مجاز
//  - کسی نمی‌تواند نقشی بالاتر از نقشِ خودش به دیگری بدهد
//  - کسی نمی‌تواند نقش/فلگ‌های خودش را ارتقا دهد
//  - فلگ‌های is_manager/is_supervisor فقط با نقشِ سازمان‌گستر (supervisor/admin)
$roleRank = ['employee' => 0, 'manager' => 1, 'supervisor' => 2, 'admin' => 2];
$tgtStmt = $db->prepare('SELECT role, is_manager, is_supervisor FROM users WHERE id = ?');
$tgtStmt->execute([$uid]);
$tgt = $tgtStmt->fetch(PDO::FETCH_ASSOC) ?: ['role' => 'employee', 'is_manager' => 0, 'is_supervisor' => 0];

$actorRank = $roleRank[$me['role'] ?? 'employee'] ?? 0;
$isSelf    = ((int) $uid === (int) ($me['id'] ?? 0));
$orgWide   = isOrgWideRole($me);

$wantRole = $input['role'] ?? $tgt['role'];
if (!isset($roleRank[$wantRole])) {
    $wantRole = $tgt['role'];                          // نقشِ نامعتبر → بدون تغییر
} elseif ($isSelf || ($roleRank[$wantRole] > $actorRank)) {
    $wantRole = $tgt['role'];                          // ارتقاءِ خود / بالاتر از خود ممنوع
}
$input['role'] = $wantRole;

if ($isSelf || !$orgWide) {
    $input['is_manager']    = (int) $tgt['is_manager'];
    $input['is_supervisor'] = (int) $tgt['is_supervisor'];
}

try {
    $db->beginTransaction();

    // مقادیرِ فعلیِ کاربر — پایه‌یِ «هر ستونی که در درخواست نیامده، دست‌نخورده بماند».
    $stmtOld = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmtOld->execute([$uid]);
    $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC) ?: [];

    // 🐞 رفعِ باگِ داده‌رفت: قبلاً این آرایه برای هر فیلد از الگوی
    // «$input['x'] ?? <پیش‌فرضِ ثابت>» استفاده می‌کرد و کلِ ردیفِ users را
    // بازنویسی می‌کرد. فرمِ «ویرایش کاربر» بعضی ستون‌ها را اصلاً نمی‌فرستد
    // (shift_count, daily_salary, priority, official_code, manager_code)،
    // پس هر بار که فقط نام/موبایل عوض می‌شد، shift_count به ۱ و
    // daily_salary به ۰ و ... ری‌ست می‌شد — کاربرِ دوشیفته یک‌شیفته می‌شد.
    // حالا: اگر کلید در $input نبود، مقدارِ فعلیِ دیتابیس نگه داشته می‌شود؛
    // اگر بود (حتی null، مثلِ پاک‌کردنِ عمدیِ شیفتِ ۲) همان اعمال می‌شود.
    $keep = fn(string $k, $default = null) =>
        array_key_exists($k, $input) ? $input[$k] : ($oldData[$k] ?? $default);

    // ─── ۱. بروزرسانی اطلاعات پایه ─────────────────────
    $fields = [
        'first_name'          => $input['first_name'],
        'last_name'           => $input['last_name'],
        'phone'               => $input['phone'],
        'email'               => $keep('email'),
        'username'            => $keep('username'),
        'official_code'       => $keep('official_code'),
        'activity_section'    => $keep('activity_section', 'public'),
        'role'                => $input['role'] ?? 'employee',
        'priority'            => $keep('priority', 'medium'),
        'is_active'           => isset($input['is_active']) ? (int)$input['is_active'] : (int)($oldData['is_active'] ?? 1),
        // شیفت
        'shift_type'          => $keep('shift_type', 'single'),
        'daily_work_hours'    => $keep('daily_work_hours', 8.00),
        'shift_count'         => $keep('shift_count', 1),
        'shift_1_start'       => $keep('shift_1_start', '08:00:00'),
        'shift_1_end'         => $keep('shift_1_end', '18:00:00'),
        'shift_2_start'       => $keep('shift_2_start'),
        'shift_2_end'         => $keep('shift_2_end'),
        'monthly_salary'      => $keep('monthly_salary', 0),
        'daily_salary'        => $keep('daily_salary', 0),
        // دسترسی‌ها
        'can_create_routine'  => (int) $keep('can_create_routine', 0),
        'can_create_workflow' => (int) $keep('can_create_workflow', 0),
        'is_manager'          => (int) $keep('is_manager', 0),
        'is_supervisor'       => (int) $keep('is_supervisor', 0),
        // سلسله مراتب
        'manager_id'          => $keep('manager_id'),
        'manager_code'        => $keep('manager_code'),
        'manager_name'        => $keep('manager_name'),
        'manager_lastname'    => $keep('manager_lastname'),
    ];

    // shift_count همیشه از shift_type مشتق شود تا این دو ستون هیچ‌وقت واگرا
    // نشوند. فرمِ ویرایشِ کاربر فقط shift_type (single/double) را می‌فرستد؛
    // سیستمِ حضور و غیاب اما از shift_count می‌خواند. پس هر بار روی همین یک
    // منبعِ حقیقت هم‌ترازشان می‌کنیم.
    $fields['shift_count'] = ($fields['shift_type'] === 'double') ? 2 : 1;

    // رمز عبور (اختیاری)
    if (!empty($input['password'])) {
        $auth = new Auth($db);
        if (!$auth->validatePassword($input['password'])) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'رمز عبور باید حداقل ۸ کاراکتر و شامل حداقل یک حرف و یک عدد باشد']);
            exit;
        }
        $fields['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
    }

    // $oldData بالاتر (قبل از ساختِ $fields) خوانده شد و برای لاگِ تغییرات هم همان به‌کار می‌رود.

    $setParts = array_map(fn($k) => "`$k` = :$k", array_keys($fields));
    $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = :__id';
    $fields['__id'] = $uid;

    $stmt = $db->prepare($sql);
    $stmt->execute($fields);

    // ─── ۲. واحدهای فعالیت ──────────────────────────────
    if (isset($input['units']) && is_array($input['units'])) {
        // حذف قبلی
        $db->prepare('DELETE FROM user_activity_units WHERE user_id = ?')->execute([$uid]);

        if (count($input['units']) > 0) {
            $stmtU = $db->prepare(
                'INSERT INTO user_activity_units (user_id, activity_unit, is_primary) VALUES (?, ?, ?)'
            );
            foreach ($input['units'] as $unit) {
                $stmtU->execute([
                    $uid,
                    $unit['activity_unit'],
                    (int)($unit['is_primary'] ?? 0),
                ]);
            }
        }
    }

    // ─── ۲.۵ واحدهای فعالیتِ BPM (جدا از units گزارش‌ها) ─────
    // (بلوک sections به بعد از commit منتقل شد)

    // ثبت لاگ تغییرات
    $logFields = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'role',
        'is_active',
        'shift_type',
        'monthly_salary',
        'can_create_routine',
        'can_create_workflow',
        'manager_id',
        'activity_section'
    ];

    $stmtLog = $db->prepare('INSERT INTO user_change_logs (user_id, changed_by, field_name, old_value, new_value) VALUES (?,?,?,?,?)');

    foreach ($logFields as $f) {
        $oldVal = $oldData[$f] ?? null;
        $newVal = $fields[$f]  ?? null;
        if ((string)$oldVal !== (string)$newVal) {
            $stmtLog->execute([$uid, $user_id, $f, $oldVal, $newVal]);
        }
    }
    $db->commit();

    // ─── واحدهای BPM (بعد از commit، چون us_setUserSections تراکنش خودش را دارد) ───
    if (isset($input['sections']) && is_array($input['sections']) && count($input['sections']) > 0) {
        $primary = $input['primary_section'] ?? ($input['activity_section'] ?? $input['sections'][0]);
        us_setUserSections($db, $uid, $input['sections'], $primary);
    }

    echo json_encode(['success' => true, 'message' => 'کاربر با موفقیت بروزرسانی شد']);
} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
    if ($db->inTransaction()) $db->rollBack();
    // بررسی duplicate
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'message' => 'شماره موبایل یا نام کاربری تکراری است']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطای پایگاه داده']);
    }
}
