<?php

header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';

$user_id = requireAuth();

$database = new Database();
$db = $database->getConnection();

$stmtMe = $db->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
$stmtMe->execute([$user_id]);
$user = $stmtMe->fetch(PDO::FETCH_ASSOC);

if (!$user || !in_array($user['role'], ['admin', 'supervisor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit;
}

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
if (!preg_match('/^09[0-9]{9}$/', $input['phone'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'شماره موبایل نامعتبر است']);
    exit;
}

// ─── Supervisor فقط می‌تواند کارمندان و مدیران عادی ویرایش کند
if ($user['role'] === 'manager') {
    $stmtCheck = $db->prepare('SELECT role FROM users WHERE id = ?');
    $stmtCheck->execute([$uid]);
    $targetRole = $stmtCheck->fetchColumn();
    if (in_array($targetRole, ['supervisor', 'admin'])) {
        echo json_encode(['success' => false, 'message' => 'دسترسی برای ویرایش این کاربر ندارید']);
        exit;
    }
}

try {
    $db->beginTransaction();

    // ─── ۱. بروزرسانی اطلاعات پایه ─────────────────────
    $fields = [
        'first_name'          => $input['first_name'],
        'last_name'           => $input['last_name'],
        'phone'               => $input['phone'],
        'email'               => $input['email'] ?? null,
        'username'            => $input['username'] ?? null,
        'official_code'       => $input['official_code'] ?? null,
        'activity_section'    => $input['activity_section'] ?? 'public',
        'role'                => $input['role'] ?? 'employee',
        'priority'            => $input['priority'] ?? 'medium',
        'is_active'           => isset($input['is_active']) ? (int)$input['is_active'] : 1,
        // شیفت
        'shift_type'          => $input['shift_type'] ?? 'single',
        'daily_work_hours'    => $input['daily_work_hours'] ?? 8.00,
        'shift_count'         => $input['shift_count'] ?? 1,
        'shift_1_start'       => $input['shift_1_start'] ?? '08:00:00',
        'shift_1_end'         => $input['shift_1_end']   ?? '18:00:00',
        'shift_2_start'       => $input['shift_2_start'] ?? null,
        'shift_2_end'         => $input['shift_2_end']   ?? null,
        'monthly_salary'      => $input['monthly_salary'] ?? 0,
        'daily_salary'        => $input['daily_salary']   ?? 0,
        // دسترسی‌ها
        'can_create_routine'  => (int)($input['can_create_routine']  ?? 0),
        'can_create_workflow' => (int)($input['can_create_workflow'] ?? 0),
        'is_manager'          => (int)($input['is_manager']          ?? 0),
        'is_supervisor'       => (int)($input['is_supervisor']       ?? 0),
        // سلسله مراتب
        'manager_id'          => $input['manager_id']       ?? null,
        'manager_code'        => $input['manager_code']     ?? null,
        'manager_name'        => $input['manager_name']     ?? null,
        'manager_lastname'    => $input['manager_lastname'] ?? null,
    ];

    // رمز عبور (اختیاری)
    if (!empty($input['password'])) {
        if (strlen($input['password']) < 4) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'رمز عبور حداقل ۴ کاراکتر باشد']);
            exit;
        }
        $fields['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
    }

    // خواندن مقادیر قبلی برای لاگ
    $stmtOld = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmtOld->execute([$uid]);
    $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);
    
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

    // ثبت لاگ تغییرات
    $logFields = ['first_name','last_name','phone','email','role','is_active',
                  'shift_type','monthly_salary','can_create_routine','can_create_workflow',
                  'manager_id','activity_section'];
    
    $stmtLog = $db->prepare('INSERT INTO user_change_logs (user_id, changed_by, field_name, old_value, new_value) VALUES (?,?,?,?,?)');
    
    foreach ($logFields as $f) {
        $oldVal = $oldData[$f] ?? null;
        $newVal = $fields[$f]  ?? null;
        if ((string)$oldVal !== (string)$newVal) {
            $stmtLog->execute([$uid, $user_id, $f, $oldVal, $newVal]);
        }
    }
        $db->commit();

echo json_encode(['success' => true, 'message' => 'کاربر با موفقیت بروزرسانی شد']);
} catch (PDOException $e) {
    $db->rollBack();
    // بررسی duplicate
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'message' => 'شماره موبایل یا نام کاربری تکراری است']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطای پایگاه داده: ' . $e->getMessage()]);
    }
}