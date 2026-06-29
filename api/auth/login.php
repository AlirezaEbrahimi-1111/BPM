<?php


require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/auth.php';
    
function checkRateLimit($ip, $db) {
    // تعدادِ تلاش‌های ناموفقِ ۱۵ دقیقهٔ اخیر از این IP
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    $count = (int) $stmt->fetchColumn();

    // بیش از ۵ تلاش → مسدود تا پایانِ پنجرهٔ ۱۵ دقیقه‌ای
    if ($count >= 5) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'تعداد تلاش‌های ناموفق زیاد است. لطفاً ۱۵ دقیقه بعد دوباره تلاش کنید.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function recordFailedLogin($ip, $db) {
    $stmt = $db->prepare("INSERT INTO login_attempts (ip, attempted_at) VALUES (?, NOW())");
    $stmt->execute([$ip]);
}

function resetRateLimit($ip, $db) {
    // بعد از ورودِ موفق، تلاش‌های این IP پاک شوند
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip = ?");
    $stmt->execute([$ip]);
}

try {

    // ✅ چک rate limit
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    checkRateLimit($ip, $db);
    
    $data = json_decode(file_get_contents('php://input'), true);

    // ✅ اعتبارسنجی قوی‌تر
    if (!is_array($data) || 
        empty($data['username']) ||
        empty($data['password']) ||
        !is_string($data['username']) ||
        !is_string($data['password'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'اطلاعات ورود نامعتبر است'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ✅ Trim و چک طول
    $username = trim($data['username']);
    $password = $data['password'];  // password را trim نکنید

    if (!isset($data['username']) || !isset($data['password'])) {
        throw new Exception('نام کاربری و رمز عبور الزامی است');
    }

    $auth = new Auth();
    $result = $auth->login($data['username'], $data['password']);

    if ($result['success']) {
         resetRateLimit($ip, $db);
        session_regenerate_id(true);

        $_SESSION['user_id'] = $result['user']['id'];
        $_SESSION['user_name'] = $result['user']['first_name'];
        $_SESSION['organization_id'] = $result['user']['organization_id'];

        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("SELECT name FROM organizations WHERE id = ?");
            $stmt->execute([$result['user']['organization_id']]);
            $org = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $_SESSION['organization_name'] = $org ? $org['name'] : 'یکتا همراهان ملک';
        } catch (Exception $e) {
            $_SESSION['organization_name'] = 'یکتا همراهان ملک';
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    } else {
        recordFailedLogin($ip, $db);  // ورود ناموفق → ثبتِ تلاش
        http_response_code(401);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور'
    ], JSON_UNESCAPED_UNICODE);
}
