<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();

    // بررسی دسترسی ادمین
    $database = new Database();
    $db = $database->getConnection();

    $currentUser = loadUserForPermissions($db, $user_id);
    requirePermission($currentUser, 'manage_users');

    $current_org_id = $currentUser['organization_id'];

    // ── بررسی سقف تعداد کاربران مجاز ──
    if ($current_org_id) {
        // تعداد کاربران فعلی سازمان
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE organization_id = ?");
        $countStmt->execute([$current_org_id]);
        $currentCount = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // سقف مجاز از اشتراک فعال
        $subStmt = $db->prepare("
            SELECT max_users FROM subscriptions 
            WHERE organization_id = ? AND is_active = 1 AND end_date >= CURDATE()
            ORDER BY end_date DESC LIMIT 1
        ");
        $subStmt->execute([$current_org_id]);
        $subscription = $subStmt->fetch(PDO::FETCH_ASSOC);

        if ($subscription) {
            $maxUsers = (int)$subscription['max_users'];
            if ($currentCount >= $maxUsers) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => "سقف تعداد کاربران مجاز ({$maxUsers} نفر) تکمیل شده است. برای افزایش، اشتراک خود را ارتقا دهید."
                ]);
                exit;
            }
        }
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // اعتبارسنجی ورودی‌ها
    if (
        empty($input['username']) || empty($input['password']) || empty($input['phone']) ||
        empty($input['first_name']) || empty($input['last_name'])
    ) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'فیلدهای الزامی را پر کنید']);
        exit;
    }

    // نام نباید کاراکترهای خطرناکِ HTML/کنترلی داشته باشد (جلوگیری از تزریق
    // در جاهایی که بعداً نمایش داده می‌شود). رقم/نقطه/پرانتز مجاز می‌ماند.
    if (preg_match('/[<>"\'`\x00-\x1F]/', $input['first_name'] . $input['last_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام یا نام خانوادگی شامل کاراکترهای غیرمجاز است']);
        exit;
    }

    $auth = new Auth();

    // اعتبارسنجی نام کاربری
    if (!$auth->validateUsername($input['username'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام کاربری نامعتبر است']);
        exit;
    }

    // اعتبارسنجی رمز عبور
    if (!$auth->validatePassword($input['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'رمز عبور باید حداقل ۸ کاراکتر و شامل حداقل یک حرف و یک عدد باشد']);
        exit;
    }

    // ثبت‌نام کاربر
    $result = $auth->register(
        $input['username'],
        $input['password'],
        $input['phone'],
        $input['first_name'],
        $input['last_name'],
        $input['activity_section'] ?? 'public'
    );
    // validate: activity_section نباید supervisor باشه (reserved)
    $section = $input['activity_section'] ?? 'public';
    if ($section === 'supervisor') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این واحد رزرو شده است']);
        exit;
    }
    if (!$result['success']) {
        http_response_code(400);
        echo json_encode($result);
        exit;
    }

    // $new_user_id = $db->lastInsertId();
    $new_user_id = $result['user']['id'];  // به جای $db->lastInsertId()


    // ── تغییر: انتساب organization_id کاربر جاری به کاربر جدید ──
    if ($current_org_id) {
        $updateOrg = $db->prepare("UPDATE users SET organization_id = ? WHERE id = ?");
        $updateOrg->execute([$current_org_id, $new_user_id]);
    }

    // بروزرسانی ایمیل اگر ارسال شده
    if (!empty($input['email'])) {
        $updateEmail = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
        $updateEmail->execute([$input['email'], $new_user_id]);
    }

    // افزودن واحدهای فعالیت
    if (!empty($input['units']) && is_array($input['units'])) {
        $insertUnit = $db->prepare("INSERT INTO user_activity_units (user_id, activity_unit, is_primary) VALUES (?, ?, ?)");

        foreach ($input['units'] as $index => $unitCode) {
            $is_primary = ($index === 0) ? 1 : 0; // اولین واحد را اصلی در نظر بگیر
            $insertUnit->execute([$new_user_id, $unitCode, $is_primary]);
        }

        // بروزرسانی فیلد activity_unit در جدول users
        if (count($input['units']) > 0) {
            $updateUnit = $db->prepare("UPDATE users SET activity_unit = ? WHERE id = ?");
            $updateUnit->execute([$input['units'][0], $new_user_id]);
        }

        // ✅ همیشه activity_unit را 'all' قرار بده (صرف‌نظر از units)
        $updateUnit = $db->prepare("UPDATE users SET activity_unit = ? WHERE id = ?");
        $updateUnit->execute(['all', $new_user_id]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'کاربر با موفقیت ایجاد شد',
        'user_id' => $new_user_id
    ]);
} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور', 'error' => 'internal_error']);
}
