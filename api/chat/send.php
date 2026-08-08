<?php
/**
 * API: ارسالِ پیام در یک گفتگو (متن و/یا پیوست)
 * POST /api/chat/send.php   (multipart/form-data یا JSON)
 *   conversation_id, message, attachments[]
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

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

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'multipart/form-data') !== false) {
        $conversationId  = (int) ($_POST['conversation_id'] ?? 0);
        $message         = trim($_POST['message'] ?? '');
        $replyToId       = !empty($_POST['reply_to_message_id']) ? (int) $_POST['reply_to_message_id'] : null;
    } else {
        $input           = json_decode(file_get_contents('php://input'), true);
        $conversationId  = (int) ($input['conversation_id'] ?? 0);
        $message         = trim($input['message'] ?? '');
        $replyToId       = !empty($input['reply_to_message_id']) ? (int) $input['reply_to_message_id'] : null;
    }

    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه گفتگو الزامی است']);
        exit;
    }
    if ($message === '' && empty($_FILES['attachments'])) {
        echo json_encode(['success' => false, 'message' => 'پیام نمی‌تواند خالی باشد']);
        exit;
    }

    // 🔒 فقط شرکت‌کننده‌های همین گفتگو اجازه‌ی ارسال دارند
    $stmt = $db->prepare("SELECT id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        error_log("Chat send denied | user_id={$user_id} | conversation_id={$conversationId}");
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // 🔒 پیامِ موردِ پاسخ باید واقعاً متعلق به همین گفتگو باشد، وگرنه نادیده گرفته می‌شود
    // (بدون خطا — تا اگر کاربر دیرتر ارسال کند و پیام از دیدش حذف/جابه‌جا شده باشد، پیام اصلی همچنان ارسال شود)
    if ($replyToId) {
        $stmt = $db->prepare("SELECT id FROM chat_messages WHERE id = ? AND conversation_id = ? AND is_deleted = 0");
        $stmt->execute([$replyToId, $conversationId]);
        if (!$stmt->fetch()) {
            $replyToId = null;
        }
    }

    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO chat_messages (conversation_id, user_id, message, reply_to_message_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$conversationId, $user_id, $message !== '' ? $message : null, $replyToId]);
    $messageId = (int) $db->lastInsertId();

    // ─── آپلود فایل‌ها ───
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/chat/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    if (!empty($_FILES['attachments'])) {
        $files = $_FILES['attachments'];
        $allowedTypes = [
            'image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp',
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
            $storedName = uniqid('chat_') . '_' . time() . '.' . $ext;

            if (move_uploaded_file($tmpName, $uploadDir . $storedName)) {
                $stmt = $db->prepare("
                    INSERT INTO chat_attachments (message_id, conversation_id, user_id, original_name, stored_name, mime_type, file_size)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$messageId, $conversationId, $user_id, $name, $storedName, $type, $size]);
            }
        }
    }

    // ✅ فرستنده، همین پیام را «خوانده» حساب می‌کند
    $db->prepare("UPDATE chat_participants SET last_read_message_id = ? WHERE conversation_id = ? AND user_id = ?")
        ->execute([$messageId, $conversationId, $user_id]);

    // برای مرتب‌سازیِ لیستِ گفتگوها بر اساسِ آخرین فعالیت
    $db->prepare("UPDATE chat_conversations SET updated_at = NOW() WHERE id = ?")->execute([$conversationId]);

    $db->commit();

    // ⚠️ عمداً بدونِ نوتیفیکیشن/پیامک: پیام‌های چت زنگوله‌ی اعلانِ خودشون رو دارن
    // (چک کردن هدر: chatUnreadBadge)، پس نیازی به ثبت در جدولِ notifications یا
    // ارسالِ پیامک ندارن — بر خلافِ تیکت که کم‌تعداد و رسمی‌تره.

    echo json_encode([
        'success'    => true,
        'message_id' => $messageId,
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat send error: " . $e->getMessage());
}
