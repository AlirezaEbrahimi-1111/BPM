<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

$user_id = requireAuth();

$stmtMe = $db->prepare('SELECT role FROM users WHERE id = ? AND is_active = 1');
$stmtMe->execute([$user_id]);
$me = $stmtMe->fetch(PDO::FETCH_ASSOC);

if (!$me || !in_array($me['role'], ['admin', 'supervisor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'شناسه کاربر الزامی است']);
    exit;
}

$uid = (int)$input['user_id'];

if ($uid === (int)$user_id) {
    echo json_encode(['success' => false, 'message' => 'نمی‌توانید خودتان را حذف کنید']);
    exit;
}

try {
    $phoneStmt = $db->prepare('SELECT phone FROM users WHERE id = ?');
    $phoneStmt->execute([$uid]);
    $oldPhone = $phoneStmt->fetchColumn();
    
    $suffix = 'deleted_' . $oldPhone . '_' . time();
    $stmt = $db->prepare('UPDATE users SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, phone = ?, username = ? WHERE id = ?');
    $stmt->execute([$user_id, $suffix, $suffix, $uid]);
    echo json_encode(['success' => true, 'message' => 'کاربر حذف شد']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطای پایگاه داده']);
}