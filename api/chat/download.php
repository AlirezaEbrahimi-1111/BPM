<?php
/**
 * API: دانلود/پیش‌نمایشِ فایلِ پیوستِ چت
 * GET /api/chat/download.php?id=123&token=...&view=1
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // توکن از URL برای نمایشِ مستقیم داخلِ <img>
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

    $attachmentId = (int) ($_GET['id'] ?? 0);
    if (!$attachmentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه فایل الزامی است']);
        exit;
    }

    // 🔒 فقط شرکت‌کننده‌های همون گفتگو اجازه‌ی دسترسی دارند
    $stmt = $db->prepare("
        SELECT ca.*
        FROM chat_attachments ca
        JOIN chat_participants cp ON cp.conversation_id = ca.conversation_id AND cp.user_id = ?
        WHERE ca.id = ?
    ");
    $stmt->execute([$user_id, $attachmentId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'فایل یافت نشد']);
        exit;
    }

    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/chat/' . $file['stored_name'];

    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'فایل روی سرور یافت نشد']);
        exit;
    }

    $isImage = strpos($file['mime_type'], 'image/') === 0;
    $disposition = ($isImage && !empty($_GET['view'])) ? 'inline' : 'attachment';

    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: ' . $disposition . '; filename="' . $file['original_name'] . '"');
    header('Content-Length: ' . $file['file_size']);
    header('Cache-Control: no-cache');

    readfile($filePath);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat download error: " . $e->getMessage());
}
