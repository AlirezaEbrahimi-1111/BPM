<?php
/**
 * API: افزودن روز تعطیل
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

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
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

    // بررسی نقش کاربر — تعطیلات یک تنظیمِ سراسریِ سازمان است، نه چیزی
    // که به زیرمجموعهٔ یک مدیر محدود شود؛ پس فقط supervisor/admin
    $stmt = $db->prepare("SELECT id, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!isOrgWideRole($user)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجوز این عملیات را ندارید']);
        exit;
    }

    // دریافت داده‌ها
    $input = json_decode(file_get_contents('php://input'), true);

    $holiday_date = $input['holiday_date'] ?? null;
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');

    if (!$holiday_date) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'تاریخ الزامی است']);
        exit;
    }

    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'عنوان الزامی است']);
        exit;
    }

    // بررسی تکراری نبودن
    $stmt = $db->prepare("SELECT id FROM holidays WHERE holiday_date = ?");
    $stmt->execute([$holiday_date]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این تاریخ قبلاً ثبت شده است']);
        exit;
    }

    // درج
    $stmt = $db->prepare("
        INSERT INTO holidays (holiday_date, title, description, created_by, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$holiday_date, $title, $description, $user_id]);

    $id = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'روز تعطیل با موفقیت اضافه شد',
        'id' => $id,
        'holiday_date' => $holiday_date,
        'title' => $title
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Holiday add error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>