<?php

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

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

    $ticketId = (int)($_GET['id'] ?? 0);
    if (!$ticketId) {
        echo json_encode(['success' => false, 'message' => 'شناسه تیکت الزامی است']);
        exit;
    }

    // اطلاعات کاربر
    $stmt = $db->prepare("SELECT role, organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentUser) throw new Exception("User not found");

    $orgId = $currentUser['organization_id'];
    $role  = $currentUser['role'];

    // ─── تیکت ───
    $sql = "
        SELECT 
            t.*,
            ts.name   AS status_name,
            ts.label  AS status_label,
            ts.color  AS status_color,
            tp.name   AS priority_name,
            tp.label  AS priority_label,
            tp.color  AS priority_color,
            tc.name   AS category_name,
            CONCAT(uc.first_name, ' ', uc.last_name) AS creator_name,
            CONCAT(ua.first_name, ' ', ua.last_name) AS assigned_name,
            lt.title AS linked_task_title,
            lt.status AS linked_task_status
        FROM tickets t
        JOIN ticket_statuses ts ON t.status_id = ts.id
        JOIN ticket_priorities tp ON t.priority_id = tp.id
        LEFT JOIN ticket_categories tc ON t.category_id = tc.id
        LEFT JOIN users uc ON t.created_by = uc.id
        LEFT JOIN users ua ON t.assigned_to = ua.id
        LEFT JOIN tasks lt ON (t.source_type = 'task' AND t.source_id = lt.id)
        WHERE t.id = ? AND t.deleted_at IS NULL
        AND (t.organization_id = ? OR ? = 1)
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$ticketId, $orgId, $user_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'تیکت یافت نشد']);
        exit;
    }

    // بررسی دسترسی: supervisor کل سازمان، manager فقط زیرمجموعهٔ خودش،
    // بقیه فقط تیکت خودشان
    $me = ['id' => $user_id, 'role' => $role, 'organization_id' => $orgId];
    if (!canManageTargetUser($db, $me, (int) $ticket['created_by'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // ─── ثبت «این کاربر تیکت را الان دید» — مبنای بج «پیام دیده‌نشده» در هدر.
    //     در try چون ممکن است جدول هنوز روی این محیط مایگریت نشده باشد. ───
    try {
        $db->prepare("
            INSERT INTO ticket_message_reads (ticket_id, user_id, last_read_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_read_at = NOW()
        ")->execute([$ticketId, $user_id]);
    } catch (Throwable $e) {
        error_log('ticket_message_reads upsert skipped: ' . $e->getMessage());
    }

    // ─── پیام‌ها ───
    $msgSql = "
        SELECT
            tm.id,
            tm.message,
            tm.created_at,
            tm.user_id,
            CONCAT(u.first_name, ' ', u.last_name) AS user_name,
            u.role AS user_role
        FROM ticket_messages tm
        LEFT JOIN users u ON tm.user_id = u.id
        WHERE tm.ticket_id = ? AND tm.deleted_at IS NULL
        ORDER BY tm.created_at ASC
    ";
    $stmt = $db->prepare($msgSql);
    $stmt->execute([$ticketId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─── پیوست‌ها ───
    $attSql = "
        SELECT
            ta.id,
            ta.message_id,
            ta.user_id,
            ta.original_name,
            ta.stored_name,
            ta.mime_type,
            ta.file_size,
            ta.created_at,
            CONCAT(u.first_name, ' ', u.last_name) AS uploader_name
        FROM ticket_attachments ta
        LEFT JOIN users u ON ta.user_id = u.id
        WHERE ta.ticket_id = ?
        ORDER BY ta.created_at ASC
    ";
    $stmt = $db->prepare($attSql);
    $stmt->execute([$ticketId]);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─── تاریخچه ───
    $histSql = "
        SELECT 
            th.action,
            th.old_value,
            th.new_value,
            th.created_at,
            CONCAT(u.first_name, ' ', u.last_name) AS user_name
        FROM ticket_history th
        LEFT JOIN users u ON th.user_id = u.id
        WHERE th.ticket_id = ?
        ORDER BY th.created_at ASC
    ";
    $stmt = $db->prepare($histSql);
    $stmt->execute([$ticketId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─── لیست وضعیت‌ها (برای dropdown تغییر وضعیت) ───
    $stmt = $db->query("SELECT id, name, label, color FROM ticket_statuses ORDER BY sort_order");
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'     => true,
        'ticket'      => $ticket,
        'messages'    => $messages,
        'attachments' => $attachments,
        'history'     => $history,
        'statuses'    => $statuses,
        'current_user_id'   => $user_id,
        'current_user_role' => $role
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Ticket detail error: " . $e->getMessage());
}
