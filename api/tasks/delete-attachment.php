<?php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
try {
    $user_id = requireAuth();

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (empty($data['attachment_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه فایل الزامی است']);
        exit;
    }

    $attachment_id = intval($data['attachment_id']);

    $database = new Database();
    $db = $database->getConnection();

    // دریافت اطلاعات فایل
    $stmt = $db->prepare("
        SELECT 
            ta.*,
            t.creator_id,
            t.assignee_id
        FROM task_attachments ta
        JOIN tasks t ON ta.task_id = t.id
        WHERE ta.id = ?
    ");
    $stmt->execute([$attachment_id]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attachment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'فایل یافت نشد']);
        exit;
    }

    // ✅ چک دسترسی حذف:
    // 1. فقط آپلودکننده فایل
    if ($attachment['uploaded_by'] != $user_id) {
        error_log("delete-attachment.php denied (not uploader) | user_id={$user_id} | attachment_id={$attachment_id} | task_id={$attachment['task_id']}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط آپلودکننده فایل می‌تواند آن را حذف کند']);
        exit;
    }

    // 2. فقط assignee فعلی کار
    if ($attachment['assignee_id'] != $user_id) {
        error_log("delete-attachment.php denied (not assignee) | user_id={$user_id} | attachment_id={$attachment_id} | task_id={$attachment['task_id']}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط مسئول فعلی کار می‌تواند فایل حذف کند']);
        exit;
    }

    // 3. بعد از ارجاع، حذف ممنوع
    $stmt = $db->prepare("
        SELECT COUNT(*) as cnt 
        FROM task_history 
        WHERE task_id = ? 
        AND action = 'delegated' 
        AND created_at >= ?
    ");
    $stmt->execute([$attachment['task_id'], $attachment['created_at']]);
    if ($stmt->fetch()['cnt'] > 0) {
        error_log("delete-attachment.php denied (post-delegation) | user_id={$user_id} | attachment_id={$attachment_id} | task_id={$attachment['task_id']}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'پس از ارجاع کار، امکان حذف فایل وجود ندارد']);
        exit;
    }

    // حذف فایل فیزیکی
    $filePath = $_SERVER['DOCUMENT_ROOT'] . $attachment['file_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // حذف از دیتابیس
    $stmt = $db->prepare("DELETE FROM task_attachments WHERE id = ?");
    $stmt->execute([$attachment_id]);

    // ثبت در تاریخچهٔ کار
    try {
        $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes) VALUES (?, ?, NULL, 'attachment_removed', ?)")
            ->execute([$attachment['task_id'], $user_id, 'فایل «' . $attachment['file_original_name'] . '» را حذف کرد']);
    } catch (Exception $e) {
        error_log("delete-attachment history insert failed | attachment_id={$attachment_id} | " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'فایل با موفقیت حذف شد'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Delete attachment error: " . $e->getMessage());
}