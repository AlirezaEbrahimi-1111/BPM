<?php
/**
 * API: ایجاد تیکت جدید
 * POST /api/tickets/create.php
 * 
 * بدنه:
 *   subject     - عنوان (الزامی)
 *   message     - متن پیام (الزامی)
 *   priority_id - اولویت (اختیاری، پیش‌فرض 2=متوسط)
 *   category_id - دسته‌بندی (اختیاری)
 * 
 * فایل پیوست: از FormData ارسال شود (فیلد attachments[])
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/VoiceCall.php';

try {
    $user_id = requireAuth();
    $database = new Database();
    $db = $database->getConnection();

    // اطلاعات کاربر
    $stmt = $db->prepare("SELECT organization_id, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentUser) throw new Exception("User not found");

    $orgId = $currentUser['organization_id'];

    // دریافت داده‌ها (از FormData یا JSON)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'multipart/form-data') !== false) {
        $subject     = trim($_POST['subject'] ?? '');
        $message     = trim($_POST['message'] ?? '');
        $priority_id = (int)($_POST['priority_id'] ?? 2);
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $task_id     = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;
    } else {
        $input       = json_decode(file_get_contents('php://input'), true);
        $subject     = trim($input['subject'] ?? '');
        $message     = trim($input['message'] ?? '');
        $priority_id = (int)($input['priority_id'] ?? 2);
        $category_id = !empty($input['category_id']) ? (int)$input['category_id'] : null;
        $task_id     = !empty($input['task_id']) ? (int)$input['task_id'] : null;
    }

    // 🔒 اولویت «بحرانی» (4) فقط برای مدیران/سوپروایزرها مجاز است؛ کارمند عادی
    // حتی با ارسال دستی priority_id=4 نمی‌تواند آن را ثبت کند
    if ($priority_id === 4 && ($currentUser['role'] ?? 'employee') === 'employee') {
        $priority_id = 3;
    }

    // 🔒 اگر تسکی برای پیوست انتخاب شده، فقط وقتی معتبر است که واقعاً به همین کاربر
    // مرتبط باشد (سازنده یا مسئولش)، وگرنه نادیده گرفته می‌شود (بدون خطا)
    if ($task_id) {
        $tChk = $db->prepare("SELECT id FROM tasks WHERE id = ? AND is_deleted = 0 AND (creator_id = ? OR assignee_id = ?)");
        $tChk->execute([$task_id, $user_id, $user_id]);
        if (!$tChk->fetch()) {
            $task_id = null;
        }
    }

    // اعتبارسنجی
    if (!$subject) {
        echo json_encode(['success' => false, 'message' => 'عنوان تیکت الزامی است']);
        exit;
    }
    if (!$message) {
        echo json_encode(['success' => false, 'message' => 'متن پیام الزامی است']);
        exit;
    }

    $db->beginTransaction();

    // ─── تولید شماره تیکت ───
    $jalaliDate = date('ymd'); // فعلاً میلادی، اگه jdf دارید عوض کنید
    $stmt = $db->prepare("SELECT COUNT(*) + 1 as seq FROM tickets WHERE organization_id = ?");
    $stmt->execute([$orgId]);
    $seq = (int)$stmt->fetch(PDO::FETCH_ASSOC)['seq'];
    $ticketNumber = 'TKT-' . $jalaliDate . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);

    // ─── ایجاد تیکت ───
    // source_type/source_id: ارجاعِ اختیاری به یک تسکِ مرتبط (ستون‌های موجودِ عمومیِ «bpm integration»)
    $stmt = $db->prepare("
        INSERT INTO tickets (organization_id, ticket_number, subject, status_id, priority_id, category_id, created_by, assigned_to, source_type, source_id)
        VALUES (?, ?, ?, 1, ?, ?, ?, 1, ?, ?)
    ");
    $stmt->execute([$orgId, $ticketNumber, $subject, $priority_id, $category_id, $user_id, $task_id ? 'task' : null, $task_id]);
    $ticketId = (int)$db->lastInsertId();

    // ─── ثبت پیام اول ───
    $stmt = $db->prepare("
        INSERT INTO ticket_messages (ticket_id, user_id, message, is_internal)
        VALUES (?, ?, ?, 0)
    ");
    $stmt->execute([$ticketId, $user_id, $message]);
    $messageId = (int)$db->lastInsertId();

    // ─── آپلود فایل‌ها ───
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/tickets/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uploadedFiles = [];
    if (!empty($_FILES['attachments'])) {
        $files = $_FILES['attachments'];
        $allowedTypes = [
            'image/jpeg', 'image/png', 'image/jpg',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'audio/mpeg', 'audio/mp4', 'audio/ogg'
        ];
        $maxSize = 20 * 1024 * 1024; // 20MB

        $fileCount = is_array($files['name']) ? count($files['name']) : 1;

        for ($i = 0; $i < $fileCount; $i++) {
            $name    = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
            $tmpName = is_array($files['tmp_name'])  ? $files['tmp_name'][$i] : $files['tmp_name'];
            $size    = is_array($files['size'])      ? $files['size'][$i]     : $files['size'];
            $type    = is_array($files['type'])      ? $files['type'][$i]     : $files['type'];
            $error   = is_array($files['error'])     ? $files['error'][$i]    : $files['error'];

            if ($error !== UPLOAD_ERR_OK) continue;
            if ($size > $maxSize) continue;
            if (!in_array($type, $allowedTypes)) continue;

            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $storedName = uniqid('tkt_') . '_' . time() . '.' . $ext;
            $destPath = $uploadDir . $storedName;

            if (move_uploaded_file($tmpName, $destPath)) {
                $stmt = $db->prepare("
                    INSERT INTO ticket_attachments (ticket_id, message_id, user_id, original_name, stored_name, mime_type, file_size)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$ticketId, $messageId, $user_id, $name, $storedName, $type, $size]);
                $uploadedFiles[] = $name;
            }
        }
    }

    // ─── ثبت تاریخچه ───
    $stmt = $db->prepare("
        INSERT INTO ticket_history (ticket_id, user_id, action, new_value)
        VALUES (?, ?, 'created', ?)
    ");
    $stmt->execute([$ticketId, $user_id, $ticketNumber]);

    // ─── ارسال نوتیفیکیشن + پیامک به assigned_to (user_id=1) ───
    $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $creatorName = $stmt->fetch(PDO::FETCH_ASSOC)['name'] ?? 'کاربر';

    $notification = new Notification($db);
    $notification->create([
        'to_user_id'   => 1,
        'title'        => 'تیکت جدید: ' . $ticketNumber,
        'message'      => $creatorName . ' یک تیکت جدید ثبت کرد: ' . $subject,
        'type'         => 'info',
        'link'         => '/pages/ticket-detail.php?id=' . $ticketId,
        'related_type' => 'ticket',
        'related_id'   => $ticketId,
        'sms_pattern'  => 'ticket_created',
        'sms_args'     => [$ticketNumber, $creatorName],
    ]);

    $db->commit();

    // ─── تماسِ صوتیِ هشدار برایِ تیکتِ «فوری/بحرانی» یا «بالا» (زرین‌کال) ───
    // 🔴 شماره‌یِ هشدار — فعلاً فقط شماره‌یِ شخصیِ درخواست‌دهنده، هاردکد
    if (in_array($priority_id, [3, 4], true)) {
        VoiceCall::dispatchCriticalTicketCallAsync(['09105255090'], $ticketId);
    }

    echo json_encode([
        'success'       => true,
        'message'       => 'تیکت با موفقیت ثبت شد',
        'ticket_id'     => $ticketId,
        'ticket_number' => $ticketNumber,
        'uploaded_files' => $uploadedFiles
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
    error_log("Ticket create error: " . $e->getMessage());
}
