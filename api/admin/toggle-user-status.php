<?php
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    // بررسی دسترسی مدیریت
    $checkAdmin = $db->prepare("SELECT activity_section FROM users WHERE id = ?");
    $checkAdmin->execute([$user_id]);
    $currentUser = $checkAdmin->fetch();

    if (!$currentUser || $currentUser['activity_section'] !== 'management') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کاربر الزامی است']);
        exit;
    }

    $target_user_id = $input['user_id'];

    // خواندن وضعیت فعلی کاربر
    $getUser = $db->prepare("SELECT is_active FROM users WHERE id = ?");
    $getUser->execute([$target_user_id]);
    $targetUser = $getUser->fetch();

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit;
    }

    // معکوس کردن وضعیت
    $newStatus = $targetUser['is_active'] == 1 ? 0 : 1;

    $updateStmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $updateStmt->execute([$newStatus, $target_user_id]);

    echo json_encode([
        'success' => true,
        'is_active' => $newStatus,
        'message' => $newStatus == 1 ? 'کاربر فعال شد' : 'کاربر غیرفعال شد'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور', 'error' => $e->getMessage()]);
    error_log("Toggle user status error: " . $e->getMessage());
}
