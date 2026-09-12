<?php
/**
 * API: حذف روز تعطیل
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
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
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

    try {
        $database = new Database();
        $db = $database->getConnection();
    } catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
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

    // بررسی نقش کاربر — دو مدلِ تعطیلی داریم، پس مجوزِ حذف بستگی به مالکِ
    // همون ردیف داره (سراسری فقط id=1، مخصوصِ سازمان فقط supervisor/admin
    // همون سازمان)، نه یک قاعدهٔ ثابتِ کلی
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

    $holiday_date = $input['holiday_date'] ?? null;
    $holiday_id = $input['id'] ?? null;

    if (!$holiday_date && !$holiday_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'تاریخ یا شناسه الزامی است']);
        exit;
    }

    // پیدا کردنِ ردیف(های) هدف تا مالکیتش قبل از حذف بررسی بشه
    if ($holiday_id) {
        $stmt = $db->prepare("SELECT id, organization_id FROM holidays WHERE id = ?");
        $stmt->execute([$holiday_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("SELECT id, organization_id FROM holidays WHERE type = 'date' AND holiday_date = ?");
        $stmt->execute([$holiday_date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($rows)) {
        echo json_encode(['success' => false, 'message' => 'تعطیلی یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // مجوزِ هر ردیف رو جدا چک می‌کنیم: سراسری فقط id=1، مخصوصِ سازمان فقط
    // همون سازمان (نه سازمانِ دیگه، حتی اگه supervisor باشه)
    $userOrgId = (int) ($user['organization_id'] ?? 0);
    foreach ($rows as $row) {
        $isGlobal = ($row['organization_id'] === null);
        if ($isGlobal && (int) $user_id !== 1) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'فقط مدیر کل سامانه می‌تواند تعطیلی سراسری را حذف کند']);
            exit;
        }
        if (!$isGlobal && (int) $row['organization_id'] !== $userOrgId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'این تعطیلی مربوط به سازمان شما نیست']);
            exit;
        }
    }

    // حذف
    if ($holiday_id) {
        $stmt = $db->prepare("DELETE FROM holidays WHERE id = ?");
        $stmt->execute([$holiday_id]);
    } else {
        $stmt = $db->prepare("DELETE FROM holidays WHERE type = 'date' AND holiday_date = ? AND (organization_id IS NULL OR organization_id = ?)");
        $stmt->execute([$holiday_date, $userOrgId]);
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
    echo json_encode(['success' => false, 'message' => 'خطا در پردازش درخواست'], JSON_UNESCAPED_UNICODE);
}
?>