<?php
/**
 * API: دانلود فایل پیوست تیکت
 * GET /api/tickets/download.php?id=123
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

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

    // بررسی دسترسی: supervisor کل سازمان، manager فقط زیرمجموعهٔ خودش،
    // بقیه فقط فایلِ خودشان
    $me = ['id' => $user_id, 'role' => $role, 'organization_id' => $orgId];
    if (!canManageTargetUser($db, $me, (int) $file['created_by'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/tickets/' . basename((string) $file['stored_name']);

    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'فایل روی سرور یافت نشد']);
        exit;
    }

    // ارسال فایل — فقط تصاویرِ رَستِر می‌توانند inline پیش‌نمایش شوند (برای src=... تگ <img>).
    // SVG و بقیهٔ نوع‌ها همیشه به‌صورتِ دانلود (attachment) تا فایلِ حاویِ اسکریپت
    // (مثلِ SVG) روی دامنهٔ ما اجرا نشود.
    $SAFE_INLINE = [
        'image/png'  => 'image/png',
        'image/jpeg' => 'image/jpeg',
        'image/jpg'  => 'image/jpeg',
        'image/gif'  => 'image/gif',
        'image/webp' => 'image/webp',
    ];
    $storedMime = (string) ($file['mime_type'] ?? '');
    $wantInline = !empty($_GET['view']) && isset($SAFE_INLINE[$storedMime]);

    $rawName   = (string) ($file['original_name'] ?? 'file');
    $asciiName = preg_replace('/[\r\n"\\\\]+/', '_', $rawName);
    $asciiName = preg_replace('/[^\x20-\x7E]/', '_', $asciiName) ?: 'file';

    header('X-Content-Type-Options: nosniff');
    if ($wantInline) {
        header('Content-Type: ' . $SAFE_INLINE[$storedMime]);
        header('Content-Disposition: inline; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($rawName));
    } else {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($rawName));
    }
    header('Content-Length: ' . (int) $file['file_size']);
    header('Cache-Control: private, no-cache');

    readfile($filePath);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Ticket download error: " . $e->getMessage());
}
