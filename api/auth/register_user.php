<?php

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';

try {
    // بررسی وجود فایل‌های مورد نیاز
    $database_path = '../../config/database.php';
    $auth_path = '../../includes/auth.php';
    
    if (!file_exists($database_path)) {
        throw new Exception('فایل database.php یافت نشد در: ' . realpath(dirname(__FILE__) . '/../..'));
    }
    
    if (!file_exists($auth_path)) {
        throw new Exception('فایل auth.php یافت نشد');
    }
    
    require_once $database_path;
    require_once $auth_path;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('خطا در پردازش JSON: ' . json_last_error_msg());
    }
    
    // اعتبارسنجی ورودی‌ها
    if (empty($input['phone'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شماره موبایل الزامی است']);
        exit;
    }
    
    if (empty($input['first_name']) || empty($input['last_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام و نام خانوادگی الزامی است']);
        exit;
    }
    
    $phone = trim($input['phone']);
    $first_name = trim($input['first_name']);
    $last_name = trim($input['last_name']);
    $activity_section = isset($input['activity_section']) ? $input['activity_section'] : 'public';
    if ($activity_section === 'supervisor') {
        $activity_section = 'public'; // محافظت از کلیدهای رزرو
    }    
    // اعتبارسنجی شماره موبایل
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شماره موبایل نامعتبر است']);
        exit;
    }
    
    // اعتبارسنجی طول نام
    if (mb_strlen($first_name) < 2 || mb_strlen($first_name) > 50) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام باید بین 2 تا 50 کاراکتر باشد']);
        exit;
    }
    
    if (mb_strlen($last_name) < 2 || mb_strlen($last_name) > 50) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام خانوادگی باید بین 2 تا 50 کاراکتر باشد']);
        exit;
    }
    
    // فقط حروف (فارسی/عربی/لاتین)، فاصله، و خط‌تیره مجازن — هر چیز دیگه‌ای
    // (از جمله کاراکترهای HTML مثل < > " ' که قبلا بدون این چک مستقیم
    // توی دیتابیس ذخیره و بعدا بدون escape جاهایی نمایش داده می‌شدن) رد می‌شه
    if (!preg_match('/^[\p{L}\s\-]+$/u', $first_name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام فقط می‌تواند شامل حروف باشد']);
        exit;
    }
    if (!preg_match('/^[\p{L}\s\-]+$/u', $last_name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام خانوادگی فقط می‌تواند شامل حروف باشد']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    
    // بررسی وجود کاربر
    $stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_user) {
        // اگر کاربر قبلا ثبت‌نام کرده
        if (!empty($existing_user['first_name']) && !empty($existing_user['last_name'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'این شماره موبایل قبلا ثبت شده است. لطفا وارد شوید.'
            ]);
            exit;
        }
        
        // اگر کاربر وجود دارد اما پروفایل کامل نشده - بروزرسانی
        $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, activity_section = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $activity_section, $existing_user['id']]);
        $user_id = $existing_user['id'];
    } else {
        // ایجاد کاربر جدید
        $stmt = $db->prepare("INSERT INTO users (phone, first_name, last_name, activity_section, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
        
        if (!$stmt->execute([$phone, $first_name, $last_name, $activity_section])) {
            throw new Exception('خطا در ذخیره اطلاعات کاربر');
        }
        
        $user_id = $db->lastInsertId();
    }
    
    // دریافت اطلاعات کاربر
    $stmt = $db->prepare("SELECT id, phone, first_name, last_name, activity_section, activity_unit, is_active FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('خطا در دریافت اطلاعات کاربر');
    }
    
    // تولید JWT Token
    $auth = new Auth();
    $token = $auth->generateJWTToken($user_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'ثبت‌نام موفقیت‌آمیز بود',
        'token' => $token,
        'user' => $user
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in register: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطا در پایگاه داده'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Register error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور — لطفا دوباره تلاش کنید'
    ]);
}
?>