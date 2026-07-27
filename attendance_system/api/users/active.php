<?php
/**
 * API: دریافت لیست کاربران فعال (برای انتخاب جانشین)
 * مسیر: /attendance_system/api/users/active.php
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'];

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

try {
    // 🔒 خط قرمز: فقط کاربرانِ همین سازمان می‌توانند جانشین انتخاب شوند
    $orgStmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $orgStmt->execute([$current_user_id]);
    $org_id = $orgStmt->fetchColumn();

    // دریافت کاربران فعال همین سازمان (به جز کاربر جاری)
    $stmt = $db->prepare("
        SELECT id, first_name, last_name
        FROM users
        WHERE id != ? AND is_active = 1 AND organization_id = ?
        ORDER BY first_name ASC
    ");
    $stmt->execute([$current_user_id, $org_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>