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

    // ── بروزرسانیِ اطلاعاتِ شخصی ──
    // 🔒 نام/نام‌خانوادگی عمداً اینجا قابل‌تغییر نیستن — فقط ادمین از
    // pages/users.php (api/admin/update-user.php) می‌تونه اسمِ یک کاربر رو
    // عوض کنه. حتی اگه فرانت‌اند (که فیلدهاش readonly شدن) این مقادیر رو
    // بفرسته هم، اینجا نادیده گرفته می‌شن — طبقِ تصمیمِ صریح.
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
    $stmt = $db->prepare("
        SELECT
            id,
            username,
            phone,
            first_name,
            last_name,
            email,
            activity_section,
            can_create_routine,
            is_active,
            created_at,
            updated_at,
            role,
            avatar_path,
            official_code,
            last_login,
            daily_work_hours
        FROM users
        WHERE id = ? AND is_active = 1
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