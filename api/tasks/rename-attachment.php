<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (empty($data['attachment_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه فایل الزامی است']);
        exit;
    }

    if (empty($data['new_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام جدید الزامی است']);
        exit;
    }

    $attachment_id = intval($data['attachment_id']);
    $new_name = trim($data['new_name']);

    // حذف کاراکترهای غیرمجاز
    $new_name = preg_replace('/[<>:\"\/\\|?*]/', '', $new_name);

    if (strlen($new_name) < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام فایل نامعتبر است']);
        exit;
    }

    if (strlen($new_name) > 255) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام فایل نباید بیشتر از 255 کاراکتر باشد']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // دریافت اطلاعات فایل
    $stmt = $db->prepare("
        SELECT 
            ta.id,
            ta.uploaded_by,
            ta.file_original_name,
            ta.file_type
        FROM task_attachments ta
        WHERE ta.id = ?
    ");
    $stmt->execute([$attachment_id]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attachment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'فایل یافت نشد']);
        exit;
    }

    // چک دسترسی - فقط کسی که آپلود کرده
    if ($attachment['uploaded_by'] != $user_id) {
        error_log("rename-attachment.php denied | user_id={$user_id} | attachment_id={$attachment_id}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط کسی که فایل را آپلود کرده می‌تواند نام آن را ویرایش کند']);
        exit;
    }

    // اگر نام قبلی extension داشته، به نام جدید هم اضافه کنیم
    $old_extension = pathinfo($attachment['file_original_name'], PATHINFO_EXTENSION);
    $new_name_without_ext = pathinfo($new_name, PATHINFO_FILENAME);
    
    if ($old_extension) {
        $final_name = $new_name_without_ext . '.' . $old_extension;
    } else {
        $final_name = $new_name;
    }

    // بروزرسانی نام در دیتابیس
    $stmt = $db->prepare("
        UPDATE task_attachments 
        SET file_original_name = ?
        WHERE id = ?
    ");
    $stmt->execute([$final_name, $attachment_id]);

    echo json_encode([
        'success' => true,
        'message' => 'نام فایل با موفقیت تغییر یافت',
        'new_name' => $final_name
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Rename attachment error: " . $e->getMessage());
}