<?php
/**
 * API: ارسال پاسخ به تیکت
 * POST /api/tickets/reply.php
 * 
 * از FormData ارسال شود (برای پشتیبانی فایل):
 *   ticket_id   - شناسه تیکت (الزامی)
 *   message     - متن پیام (الزامی)
 *   is_internal - یادداشت داخلی (0 یا 1، اختیاری)
 *   attachments[] - فایل‌ها (اختیاری)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
// ✅ بعد
try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    $user_id = $auth->getUserFromToken();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت']);
        exit;
    }

    // اطلاعات کاربر
    $stmt = $db->prepare("SELECT role, organization_id, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentUser) throw new Exception("User not found");

    $orgId = $currentUser['organization_id'];
    $role  = $currentUser['role'];
    $userName = $currentUser['first_name'] . ' ' . $currentUser['last_name'];

    // دریافت داده‌ها
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'multipart/form-data') !== false) {
        $ticketId    = (int)($_POST['ticket_id'] ?? 0);
        $message     = trim($_POST['message'] ?? '');
        $is_internal = (int)($_POST['is_internal'] ?? 0);
    } else {
        $input       = json_decode(file_get_contents('php://input'), true);
        $ticketId    = (int)($input['ticket_id'] ?? 0);
        $message     = trim($input['message'] ?? '');
        $is_internal = (int)($input['is_internal'] ?? 0);
    }

    if (!$ticketId) {
        echo json_encode(['success' => false, 'message' => 'شناسه تیکت الزامی است']);
        exit;
    }
    if (!$message) {
        echo json_encode(['success' => false, 'message' => 'متن پیام الزامی است']);
        exit;
    }

    // بررسی وجود تیکت
    $stmt = $db->prepare("SELECT id, created_by, assigned_to, ticket_number, subject FROM tickets
WHERE id = ? AND deleted_at IS NULL
        AND (organization_id = ? OR ? = 1)");
    $stmt->execute([$ticketId, $orgId, $user_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'تیکت یافت نشد']);
        exit;
    }

    // بررسی دسترسی
    $isCreator  = ((int)$ticket['created_by'] === $user_id);
$isSuperAdmin = ($user_id === 1);

    if (!$isCreator && !$isSuperAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // یادداشت داخلی فقط برای مدیران
    if ($is_internal && !$isSuperAdmin) {
        $is_internal = 0;
    }

    $db->beginTransaction();

    // ─── ثبت پیام ───
    $stmt = $db->prepare("
        INSERT INTO ticket_messages (ticket_id, user_id, message, is_internal)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$ticketId, $user_id, $message, $is_internal]);
    $messageId = (int)$db->lastInsertId();

    // ─── آپلود فایل‌ها ───
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/tickets/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    if (!empty($_FILES['attachments'])) {
        $files = $_FILES['attachments'];
        $allowedTypes = [
            'image/jpeg', 'image/png', 'image/jpg',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'audio/mpeg', 'audio/mp4', 'audio/ogg'
        ];
        $maxSize = 20 * 1024 * 1024;

        $fileCount = is_array($files['name']) ? count($files['name']) : 1;

        for ($i = 0; $i < $fileCount; $i++) {
            $name    = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
            $tmpName = is_array($files['tmp_name'])  ? $files['tmp_name'][$i] : $files['tmp_name'];
            $size    = is_array($files['size'])      ? $files['size'][$i]     : $files['size'];
            $type    = is_array($files['type'])      ? $files['type'][$i]     : $files['type'];
            $error   = is_array($files['error'])     ? $files['error'][$i]    : $files['error'];

            if ($error !== UPLOAD_ERR_OK || $size > $maxSize || !in_array($type, $allowedTypes)) continue;

            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $storedName = uniqid('tkt_') . '_' . time() . '.' . $ext;

            if (move_uploaded_file($tmpName, $uploadDir . $storedName)) {
                $stmt = $db->prepare("
                    INSERT INTO ticket_attachments (ticket_id, message_id, user_id, original_name, stored_name, mime_type, file_size)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$ticketId, $messageId, $user_id, $name, $storedName, $type, $size]);
            }
        }
    }

    // ─── ثبت تاریخچه ───
    $actionLabel = $is_internal ? 'internal_note' : 'replied';
    $stmt = $db->prepare("
        INSERT INTO ticket_history (ticket_id, user_id, action)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$ticketId, $user_id, $actionLabel]);

    // ─── نوتیفیکیشن ───
    if (!$is_internal) {
        // اگر پاسخ‌دهنده = assigned → نوتیف به creator
        // اگر پاسخ‌دهنده = creator → نوتیف به assigned
        $notifyUserId = ($user_id === (int)$ticket['assigned_to'])
            ? (int)$ticket['created_by']
            : (int)$ticket['assigned_to'];

        if ($notifyUserId && $notifyUserId !== $user_id) {
            $notif = new Notification($db);
            $notif->create([
                'to_user_id'   => $notifyUserId,
                'title'        => 'پاسخ جدید: ' . $ticket['ticket_number'],
                'message'      => $userName . ' به تیکت «' . $ticket['subject'] . '» پاسخ داد',
                'type'         => 'info',
                'link'         => 'ticket-detail.php?id=' . $ticketId,
                'related_type' => 'ticket',
                'related_id'   => $ticketId,
                'sms_pattern'  => 'ticket_replied',
                'sms_args'     => [$userName, $ticket['subject']],
            ]);
        }
    }

    $db->commit();

    echo json_encode([
        'success'    => true,
        'message'    => $is_internal ? 'یادداشت داخلی ثبت شد' : 'پاسخ ارسال شد',
        'message_id' => $messageId
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
    error_log("Ticket reply error: " . $e->getMessage());
}
