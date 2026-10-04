<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/path.php';   // یا اگر path.php لازم نیست، این خط را حذف کن

require_once  $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once  $_SERVER['DOCUMENT_ROOT'] .  '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
try {
    $database = new Database();
    $db = $database->getConnection();

    $auth = new Auth($db);
    $user_id = $auth->getUserFromToken();

    if (!$user_id) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized'
        ]);
        exit;
    }

    // ── بروزرسانی اطلاعات شخصی ──
    // 🔒 نام/نام‌خانوادگی عمدا اینجا قابل‌تغییر نیستن — فقط ادمین از
    // pages/users.php (api/admin/update-user.php) می‌تونه اسم یک کاربر رو
    // عوض کنه. حتی اگه فرانت‌اند (که فیلدهاش readonly شدن) این مقادیر رو
    // بفرسته هم، اینجا نادیده گرفته می‌شن — طبق تصمیم صریح.
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $email = isset($input['email']) ? trim($input['email']) : null;

        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ایمیل واردشده معتبر نیست']);
            exit;
        }

        $stmt = $db->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([($email !== '' ? $email : null), $user_id]);

        echo json_encode(['success' => true, 'message' => 'اطلاعات بروزرسانی شد']);
        exit;
    }

    // دریافت اطلاعات کاربر
    // 🔒 organization_name هم اضافه شد — طبق گزارش کاربر، دقیقا همون
    // مشکلِ اسمِ کاربر (نمایشِ نامِ قدیمی توی هدر تا خروج/ورودِ مجدد) برای
    // نامِ سازمان هم پیش میومد، چون هدر اون رو هم از همون localStorage.
    // user_info می‌خونه. این فیلدِ اضافه، بدونِ اثر روی بقیه‌ی مصرف‌کننده‌های
    // این endpoint (فقط یک کلیدِ جدید به خروجیِ JSON اضافه می‌شه).
    $stmt = $db->prepare("
        SELECT
            u.id,
            u.username,
            u.phone,
            u.first_name,
            u.last_name,
            u.email,
            u.activity_section,
            u.can_create_routine,
            u.is_active,
            u.created_at,
            u.updated_at,
            u.role,
            u.avatar_path,
            u.official_code,
            u.last_login,
            u.daily_work_hours,
            o.name AS organization_name,
            u.organization_id AS __org_id
        FROM users u
        LEFT JOIN organizations o ON o.id = u.organization_id
        WHERE u.id = ? AND u.is_active = 1
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }

    // پلن سازمان؛ برای رایگان، نام سازمان و واحد پنهان می‌شود (مشخصات بخش ۱۱)
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-access.php';
    $planFields = planPayload($db, (int) $user['__org_id']);
    unset($user['__org_id']);
    if ($planFields['plan'] === 'free') {
        unset($user['organization_name'], $user['activity_unit']);
    }
    $user = array_merge($user, $planFields);

    // Return user data
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Profile error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error'
    ]);
}
?>