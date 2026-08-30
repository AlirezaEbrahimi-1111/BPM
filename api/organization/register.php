<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/OrganizationHelper.php';

// ایجاد اتصال به دیتابیس
$database = new Database();
$db = $database->getConnection();
$helper = new OrganizationHelper($db);
$auth = new Auth($db);

header('Content-Type: application/json; charset=utf-8');

// فقط POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ── اعتبارسنجی ──────────────────────────────────────────
$errors = [];

if (empty($data['org_name']) || mb_strlen(trim($data['org_name'])) < 2) {
    $errors[] = 'نام سازمان الزامی است';
}

if (empty($data['phone']) || !preg_match('/^09[0-9]{9}$/', $data['phone'])) {
    $errors[] = 'شماره موبایل معتبر نیست';
}

if (empty($data['first_name'])) {
    $errors[] = 'نام الزامی است';
}

if (empty($data['last_name'])) {
    $errors[] = 'نام خانوادگی الزامی است';
}

if (empty($data['password']) || !$auth->validatePassword($data['password'])) {
    $errors[] = 'رمز عبور باید حداقل ۸ کاراکتر و شامل حداقل یک حرف و یک عدد باشد';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors],
                     JSON_UNESCAPED_UNICODE);
    exit;
}


// ── بررسی تکراری بودن شماره موبایل ────────────────────
$stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
$stmt->execute([$data['phone']]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'این شماره موبایل قبلاً ثبت شده است'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── ثبت سازمان ──────────────────────────────────────────
try {
    $result = $helper->registerOrganization($data);
    
    // تولید JWT برای ادمین جدید
    $auth = new Auth($db);
    $token = $auth->generateJWTToken($result['user_id'],null, $result['organization_id']);
    
    http_response_code(201);
    echo json_encode([
        'success'         => true,
        'message'         => 'سازمان با موفقیت ثبت شد',
        'token'           => $token,
        'organization_id' => $result['organization_id'],
        'trial_days'      => 14
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("organization/register.php signup failed | phone=" . ($data['phone'] ?? 'null') . " | org_name=" . ($data['org_name'] ?? 'null') . " | " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطا در ثبت سازمان'
    ], JSON_UNESCAPED_UNICODE);
}
