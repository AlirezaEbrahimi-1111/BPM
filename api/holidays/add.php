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

    // بررسی نقش کاربر — دو مدلِ تعطیلی داریم:
    //   ۱) سراسری (organization_id = NULL) — فقط کاربرِ id=1
    //   ۲) مخصوصِ سازمان (organization_id = سازمانِ خودش) — هر supervisor/admin
    $stmt = $db->prepare("SELECT id, role, organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!isOrgWideRole($user)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجوز این عملیات را ندارید']);
        exit;
    }

    // دریافت داده‌ها
    $input = json_decode(file_get_contents('php://input'), true);

    $scope = ($input['scope'] ?? 'org') === 'global' ? 'global' : 'org';
    $type = ($input['type'] ?? 'date') === 'weekly' ? 'weekly' : 'date';
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');

    // فقط کاربرِ id=1 اجازهٔ ساختنِ تعطیلیِ سراسری داره؛ بقیه همیشه برایِ سازمانِ خودشونه
    if ($scope === 'global' && (int) $user_id !== 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط مدیرِ کلِ سامانه می‌تواند تعطیلیِ سراسری تعریف کند']);
        exit;
    }
    $organization_id = $scope === 'global' ? null : (int) ($user['organization_id'] ?? 0);
    if ($scope === 'org' && !$organization_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'سازمانِ کاربر مشخص نیست']);
        exit;
    }

    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'عنوان الزامی است']);
        exit;
    }

    if ($type === 'weekly') {
        $day_of_week = $input['day_of_week'] ?? null;
        if ($day_of_week === null || !is_numeric($day_of_week) || (int) $day_of_week < 0 || (int) $day_of_week > 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'روزِ هفته نامعتبر است']);
            exit;
        }
        $day_of_week = (int) $day_of_week;

        $stmt = $db->prepare("
            SELECT id FROM holidays
            WHERE type = 'weekly' AND day_of_week = ?
              AND ((organization_id IS NULL AND ? IS NULL) OR organization_id = ?)
        ");
        $stmt->execute([$day_of_week, $organization_id, $organization_id]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'این روزِ هفته قبلاً به‌عنوانِ تعطیل ثبت شده است']);
            exit;
        }

        $stmt = $db->prepare("
            INSERT INTO holidays (organization_id, type, day_of_week, title, description, created_by, created_at)
            VALUES (?, 'weekly', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$organization_id, $day_of_week, $title, $description, $user_id]);

        $id = $db->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'تعطیلیِ هفتگی با موفقیت اضافه شد',
            'id' => $id,
            'type' => 'weekly',
            'day_of_week' => $day_of_week,
            'title' => $title
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // type === 'date'
    $holiday_date = $input['holiday_date'] ?? null;
    if (!$holiday_date) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'تاریخ الزامی است']);
        exit;
    }

    // بررسی تکراری نبودن (فقط در همون scope؛ هم‌پوشانیِ سراسری/سازمانی مشکلی نداره)
    $stmt = $db->prepare("
        SELECT id FROM holidays
        WHERE type = 'date' AND holiday_date = ?
          AND ((organization_id IS NULL AND ? IS NULL) OR organization_id = ?)
    ");
    $stmt->execute([$holiday_date, $organization_id, $organization_id]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این تاریخ قبلاً ثبت شده است']);
        exit;
    }

    // درج
    $stmt = $db->prepare("
        INSERT INTO holidays (organization_id, type, holiday_date, title, description, created_by, created_at)
        VALUES (?, 'date', ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$organization_id, $holiday_date, $title, $description, $user_id]);

    $id = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'روز تعطیل با موفقیت اضافه شد',
        'id' => $id,
        'type' => 'date',
        'holiday_date' => $holiday_date,
        'title' => $title
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Holiday add error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>