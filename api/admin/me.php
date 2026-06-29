<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        SELECT id, username, first_name, last_name, phone, email,
               activity_section, activity_unit, organization_id,role
        FROM users
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'user'    => $user
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور', 'error' => $e->getMessage()]);
}
?>
