<?php
/**
 * API: دانلود فایل پیوست اطلاعیه
 * GET ?id=123 — احراز هویت: هدر Authorization یا سشن مرورگر (لینک مستقیم هدر نمی‌فرستد)،
 * مثل api/chat/download.php. فایل همیشه دانلود می‌شود، نه نمایش داخل مرورگر.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/announcement-attachments.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $auth = new Auth($db);
    $user_id = $auth->getUserFromToken();
    if (!$user_id) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
        if (!empty($_SESSION['user_id'])) {
            $user_id = (int) $_SESSION['user_id'];
        }
        if (function_exists('session_write_close')) {
            session_write_close();
        }
    }
    if (!$user_id) {
        http_response_code(401);
        echo 'برای دریافت این فایل ابتدا وارد شوید';
        exit;
    }

    $stmt = $db->prepare("SELECT announcement_id, stored_name, original_name FROM announcement_attachments WHERE id = ?");
    $stmt->execute([(int) ($_GET['id'] ?? 0)]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    $user = annAttLoadUser($db, (int) $user_id);
    $ann = $file ? annAttLoad($db, (int) $file['announcement_id']) : null;
    $path = $file ? annAttDir() . basename($file['stored_name']) : '';

    if (!$user || !$ann || !annAttCanView($db, $user, $ann) || !is_file($path)) {
        http_response_code(404);
        echo 'فایل یافت نشد';
        exit;
    }

    $rawName = (string) $file['original_name'];
    $asciiName = preg_replace('/[\r\n"\\\\]+/', '_', $rawName);
    $asciiName = preg_replace('/[^\x20-\x7E]/', '_', $asciiName) ?: 'file';

    header('X-Content-Type-Options: nosniff');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($rawName));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-cache');

    readfile($path);
    exit;

} catch (Throwable $e) {
    error_log("announcement download error: " . $e->getMessage());
    http_response_code(500);
    echo 'خطای سرور';
}
