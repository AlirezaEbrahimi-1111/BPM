<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
header('Content-Type: application/json; charset=utf-8');

$database = new Database();
$db = $database->getConnection();
$user_id = requireAuth();

// گرفتن organization_id از توکن
$stmt = $db->prepare("SELECT organization_id, activity_section FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$org_id = $user['organization_id'];

$method = $_SERVER['REQUEST_METHOD'];

// GET — لیست واحدهای این سازمان
if ($method === 'GET') {
    $stmt = $db->prepare("
        SELECT section_key, section_label, sort_order, is_active 
        FROM organization_activity_sections 
        WHERE organization_id = ? 
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$org_id]);
    echo json_encode(['success' => true, 'sections' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// فقط supervisor یا management می‌تونه ویرایش کنه
if (!in_array($user['activity_section'], ['management', 'supervisor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی ندارید']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// POST — اضافه کردن واحد جدید
if ($method === 'POST') {
    $key   = trim($input['section_key'] ?? '');
    $label = trim($input['section_label'] ?? '');
    $order = intval($input['sort_order'] ?? 99);

    if (empty($key) || empty($label)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'کلید و نام واحد الزامی است']);
        exit;
    }
    // کلید نباید management یا supervisor باشه
    if (in_array($key, ['management', 'supervisor'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این کلید رزرو شده است']);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO organization_activity_sections 
          (organization_id, section_key, section_label, sort_order)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE section_label = VALUES(section_label), sort_order = VALUES(sort_order)
    ");
    $stmt->execute([$org_id, $key, $label, $order]);
    echo json_encode(['success' => true, 'message' => 'واحد فعالیت ذخیره شد']);
    exit;
}

// DELETE — حذف واحد + انتقال کاربران
if ($method === 'DELETE') {
    $key         = trim($input['section_key'] ?? '');
    $transfer_to = trim($input['transfer_to'] ?? '');

    if (empty($key) || empty($transfer_to)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'کلید واحد و واحد جایگزین الزامی است']);
        exit;
    }

    // بررسی اینکه کاربری با این واحد وجود داره
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE organization_id = ? AND activity_section = ?");
    $stmt->execute([$org_id, $key]);
    $count = $stmt->fetchColumn();

    $db->beginTransaction();
    try {
        // انتقال کاربران
        if ($count > 0) {
            $stmt = $db->prepare("UPDATE users SET activity_section = ? WHERE organization_id = ? AND activity_section = ?");
            $stmt->execute([$transfer_to, $org_id, $key]);
        }
        // حذف از جدول واحدها
        $stmt = $db->prepare("DELETE FROM organization_activity_sections WHERE organization_id = ? AND section_key = ?");
        $stmt->execute([$org_id, $key]);
        
        $db->commit();
        echo json_encode([
            'success' => true, 
            'message' => "واحد حذف شد" . ($count > 0 ? " و $count کاربر منتقل شدند" : "")
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در حذف واحد']);
    }
    exit;
}