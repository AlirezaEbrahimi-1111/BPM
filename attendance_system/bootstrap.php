<?php

// تعریف مسیرهای پایه
define('ROOT_DIR', dirname(dirname(__FILE__)));
define('ATTENDANCE_DIR', dirname(__FILE__));
define('CONFIG_DIR', ROOT_DIR . '/config');
define('INCLUDES_DIR', ROOT_DIR . '/includes');
define('API_DIR', ROOT_DIR . '/api');

// بررسی وجود فایل‌های ضروری
$required_files = [
    CONFIG_DIR . '/database.php',
    INCLUDES_DIR . '/auth.php'
];
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => "فایل ضروری یافت نشد: $file",
            'debug' => [
                'file' => $file,
                'expected' => realpath($file),
                'current_dir' => __DIR__,
                'root_dir' => ROOT_DIR
            ]
        ]);
        exit;
    }
}

// بارگذاری فایل‌های ضروری
require_once CONFIG_DIR . '/database.php';
require_once INCLUDES_DIR . '/auth.php';

// بارگذاری کلاس‌های attendance_system
require_once ATTENDANCE_DIR . '/includes/JalaliDate.php';
require_once ATTENDANCE_DIR . '/includes/AttendanceCalculator.php';
require_once ATTENDANCE_DIR . '/includes/AttendanceManager.php';
require_once ATTENDANCE_DIR . '/includes/RequestManager.php';

// تابع کمکی برای دریافت Database Connection
function getDB() {
    static $db = null;
    if ($db === null) {
        $database = new Database();
        $db = $database->getConnection();
    }
    return $db;
}

// تابع کمکی برای احراز هویت
function requireAuth() {
    $auth = new Auth();
    $user_id = $auth->getUserFromToken();
    
    if (!$user_id) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized'
        ]);
        exit;
    }
    
    return $user_id;
}

// تابع کمکی برای دریافت اطلاعات کاربر
function getCurrentUser($user_id = null) {
    if ($user_id === null) {
        $user_id = requireAuth();
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, username, phone, first_name, last_name, email, 
               activity_section, is_active, created_at, updated_at
        FROM users 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// CORS Headers
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
?>