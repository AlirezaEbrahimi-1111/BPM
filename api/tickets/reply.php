<?php

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
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
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $message  = trim($_POST['message'] ?? '');
    } else {
        $input    = json_decode(file_get_contents('php://input'), true);
        $ticketId = (int)($input['ticket_id'] ?? 0);
        $message  = trim($input['message'] ?? '');
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
    $stmt = $db->prepare("SELECT t.id, t.created_by, t.assigned_to, t.ticket_number, t.subject,
               t.status_id, ts.name AS old_status_name, ts.label AS old_status_label
        FROM tickets t
        JOIN ticket_statuses ts ON t.status_id = ts.id
        WHERE t.id = ? AND t.deleted_at IS NULL
        AND (t.organization_id = ? OR ? = 1)");
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
        error_log("Ticket reply denied | user_id={$user_id} | ticket_id={$ticketId}");
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $db->beginTransaction();

    // ─── ثبت پیام ───
    $stmt = $db->prepare("
        INSERT INTO ticket_messages (ticket_id, user_id, message, is_internal)
        VALUES (?, ?, ?, 0)
    ");
    $stmt->execute([$ticketId, $user_id, $message]);
    $messageId = (int)$db->lastInsertId();

    // ─── تغییر خودکار وضعیت بر اساس فرستنده‌ی پیام ───
    // پاسخ سوپرادمین → منتظر پاسخ (کاربر)؛ پاسخ کاربر → باز (منتظر بررسی سوپرادمین)
    // تیکتِ لغوشده خودکار دوباره باز نمی‌شود؛ باید دستی تغییر وضعیت داده شود.
    if ($ticket['old_status_name'] !== 'cancelled') {
        $newStatusName = $isSuperAdmin ? 'waiting_reply' : 'open';
        $stmt = $db->prepare("SELECT id, label FROM ticket_statuses WHERE name = ?");
        $stmt->execute([$newStatusName]);
        $newStatus = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($newStatus && (int)$newStatus['id'] !== (int)$ticket['status_id']) {
            $db->prepare("UPDATE tickets SET status_id = ? WHERE id = ?")
                ->execute([$newStatus['id'], $ticketId]);

            // action جدا از 'status_changed' (تغییر دستی در change-status.php) تا در
            // تاریخچه مشخص باشد این تغییر خودکار و بر اثر پاسخ بوده، نه اقدام مستقیم مدیر.
            $db->prepare("
                INSERT INTO ticket_history (ticket_id, user_id, action, old_value, new_value)
                VALUES (?, ?, 'status_auto_changed', ?, ?)
            ")->execute([$ticketId, $user_id, $ticket['old_status_label'], $newStatus['label']]);
        }
    }

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

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            // فقط پسوندهایِ امن ذخیره شوند (نه php/phtml/svg/html/js/...)
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'mp3', 'm4a', 'ogg', 'txt'], true)) continue;
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
    $stmt = $db->prepare("
        INSERT INTO ticket_history (ticket_id, user_id, action)
        VALUES (?, ?, 'replied')
    ");
    $stmt->execute([$ticketId, $user_id]);

    // ─── نوتیفیکیشن ───
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
            'link'         => '/pages/ticket-detail.php?id=' . $ticketId,
            'related_type' => 'ticket',
            'related_id'   => $ticketId,
            'sms_pattern'  => 'ticket_replied',
            'sms_args'     => [$userName, $ticket['subject']],
        ]);
    }

    $db->commit();

    echo json_encode([
        'success'    => true,
        'message'    => 'پاسخ ارسال شد',
        'message_id' => $messageId
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Ticket reply error: " . $e->getMessage());
}
