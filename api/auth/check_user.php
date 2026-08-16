<?php
// api/auth/check_user.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['phone']) || empty($input['phone'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شماره موبایل الزامی است']);
        exit;
    }
    
    $phone = $input['phone'];
    
    // اعتبارسنجی شماره موبایل
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شماره موبایل نامعتبر است']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();

    // 🔒 قبلاً این endpoint بدونِ هیچ محدودیتی بود — یعنی می‌شد با امتحان‌کردنِ
    // پی‌درپیِ شماره‌موبایل‌ها، فهرستِ کاربرانِ ثبت‌شده رو استخراج کرد
    // (user enumeration). حداکثر ۲۰ درخواست در ۱۵ دقیقه به‌ازایِ هر IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateStmt = $db->prepare("SELECT COUNT(*) FROM check_user_attempts WHERE ip = ? AND checked_at > (NOW() - INTERVAL 15 MINUTE)");
    $rateStmt->execute([$ip]);
    if ((int) $rateStmt->fetchColumn() >= 20) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'تعداد درخواست‌ها زیاد است. لطفاً کمی بعد دوباره تلاش کنید.']);
        exit;
    }
    $db->prepare("INSERT INTO check_user_attempts (ip, checked_at) VALUES (?, NOW())")->execute([$ip]);

    // بررسی وجود کاربر
    $stmt = $db->prepare("SELECT id, first_name, last_name, is_active FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    
    if ($user) {
        // کاربر وجود دارد
        if ($user['is_active'] == 0) {
            echo json_encode([
                'success' => false,
                'user_exists' => true,
                'is_active' => false,
                'message' => 'حساب کاربری شما غیرفعال شده است. لطفاً با مدیر سیستم تماس بگیرید.'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'user_exists' => true,
                'has_profile' => !empty($user['first_name']) && !empty($user['last_name']),
                'message' => 'کاربر موجود است'
            ]);
        }
    } else {
        // کاربر وجود ندارد - نیاز به ثبت‌نام
        echo json_encode([
            'success' => true,
            'user_exists' => false,
            'message' => 'کاربر یافت نشد - نیاز به ثبت‌نام'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Check user error: " . $e->getMessage());
}
?>