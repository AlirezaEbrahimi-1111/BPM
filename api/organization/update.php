<?php
/**
 * API: ویرایش اطلاعات سازمان
 * POST /api/organization/update.php  (فقط supervisor)
 */
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);

    // ── احراز هویت (هماهنگ با بقیهٔ APIها) ──
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        $user_id = $auth->getUserFromToken();
    }
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'احراز هویت نامعتبر'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── نقش و سازمان کاربر ──
    $stmt = $db->prepare("SELECT role, organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$me || $me['role'] !== 'supervisor') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $orgId = $me['organization_id'];

    // وجود سازمان
    $stmt = $db->prepare("SELECT id FROM organizations WHERE id = ?");
    $stmt->execute([$orgId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'سازمان یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $logoPath = null;

    if (strpos($contentType, 'multipart/form-data') !== false) {
        $name        = trim($_POST['name'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $address     = trim($_POST['address'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // آپلود لوگو
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
            if (!in_array($_FILES['logo']['type'], $allowed)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'فرمت فایل مجاز نیست (jpg, png, webp, svg)'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'حجم فایل نباید بیشتر از ۲ مگابایت باشد'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/logos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $fileName = 'org_' . $orgId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $fileName)) {
                $logoPath = '/uploads/logos/' . $fileName;
            }
        }
    } else {
        $input       = json_decode(file_get_contents('php://input'), true);
        $name        = trim($input['name'] ?? '');
        $phone       = trim($input['phone'] ?? '');
        $address     = trim($input['address'] ?? '');
        $description = trim($input['description'] ?? '');
    }

    // اعتبارسنجی
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام سازمان الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!empty($phone) && !preg_match('/^0\d{10}$/', $phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شماره تلفن معتبر نیست'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ساخت کوئری آپدیت
    $fields = ['name = ?', 'phone = ?', 'address = ?', 'description = ?'];
    $params = [$name, $phone, $address, $description];

    if ($logoPath) {
        $fields[] = 'logo = ?';
        $params[] = $logoPath;
    }
    $params[] = $orgId;

    $sql = "UPDATE organizations SET " . implode(', ', $fields) . " WHERE id = ?";
    $db->prepare($sql)->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'اطلاعات سازمان با موفقیت بروزرسانی شد',
        'logo'    => $logoPath
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    error_log("organization/update.php failed | org_id=" . ($orgId ?? 'null') . " | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}