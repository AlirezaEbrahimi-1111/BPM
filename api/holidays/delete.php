<?php
/**
 * API: حذف روز تعطیل
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

date_default_timezone_set('Asia/Tehran');

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

    try {
        $database = new Database();
        $db = $database->getConnection();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection error']);
        exit;
    }
    $auth = new Auth($db);

    // احراز هویت
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id)
        $user_id = $auth->getUserFromToken();

    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'احراز هویت نامعتبر']);
        exit;
    }

    // بررسی نقش کاربر (فقط مدیر)
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !in_array($user['role'], ['supervisor', 'manager'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجوز این عملیات را ندارید']);
        exit;
    }

    // دریافت داده‌ها
    $input = json_decode(file_get_contents('php://input'), true);

    $holiday_date = $input['holiday_date'] ?? null;
    $holiday_id = $input['id'] ?? null;

    if (!$holiday_date && !$holiday_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'تاریخ یا شناسه الزامی است']);
        exit;
    }

    // حذف
    if ($holiday_date) {
        $stmt = $db->prepare("DELETE FROM holidays WHERE holiday_date = ?");
        $stmt->execute([$holiday_date]);
    } else {
        $stmt = $db->prepare("DELETE FROM holidays WHERE id = ?");
        $stmt->execute([$holiday_id]);
    }

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'روز تعطیل با موفقیت حذف شد'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'تعطیلی یافت نشد'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>