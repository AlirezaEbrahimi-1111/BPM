<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

// ✅ بعد
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$user_id = $auth->getUserFromToken();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'عدم احراز هویت'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmtUser = $db->prepare("SELECT id, organization_id FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

try {
    // کاربر 1 همه دسته‌بندی‌ها را می‌بیند
    if ($user['id'] == 1) {
        $stmt = $db->query("SELECT id, organization_id, name, description, is_active, created_at FROM ticket_categories ORDER BY name ASC");
    } else {
        $stmt = $db->prepare("SELECT id, organization_id, name, description, is_active, created_at FROM ticket_categories WHERE organization_id = ? ORDER BY name ASC");
        $stmt->execute([$user['organization_id']]);
    }

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'categories' => $categories
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Categories error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}