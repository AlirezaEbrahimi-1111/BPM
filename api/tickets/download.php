<?php
/**
 * API: دانلود فایل پیوست تیکت
 * GET /api/tickets/download.php?id=123
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // توکن از URL برای دانلود مستقیم
    if (empty($_SERVER['HTTP_AUTHORIZATION']) && !empty($_GET['token'])) {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['token'];
    }

    $auth = new Auth($db);
    $user_id = $auth->getUserFromToken();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت']);
        exit;
    }

    $attachmentId = (int)($_GET['id'] ?? 0);
    if (!$attachmentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه فایل الزامی است']);
        exit;
    }

    // اطلاعات کاربر
    $stmt = $db->prepare("SELECT role, organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    $orgId = $currentUser['organization_id'];
    $role  = $currentUser['role'];

    // اطلاعات فایل + تیکت
    $stmt = $db->prepare("
        SELECT ta.*, t.created_by, t.organization_id
        FROM ticket_attachments ta
        JOIN tickets t ON ta.ticket_id = t.id
WHERE ta.id = ? AND t.deleted_at IS NULL
        AND (t.organization_id = ? OR ? = 1)
    ");
    $stmt->execute([$attachmentId, $orgId, $user_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'فایل یافت نشد']);
        exit;
    }

    // بررسی دسترسی
if ($user_id !== 1 && !in_array($role, ['manager', 'supervisor']) && (int)$file['created_by'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/tickets/' . $file['stored_name'];

    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'فایل روی سرور یافت نشد']);
        exit;
    }

    // ارسال فایل
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
    header('Content-Length: ' . $file['file_size']);
    header('Cache-Control: no-cache');

    readfile($filePath);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Ticket download error: " . $e->getMessage());
}
