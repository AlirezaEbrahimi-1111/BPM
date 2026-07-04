<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
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

    $input    = json_decode(file_get_contents('php://input'), true);
    $ticketId = (int)($input['ticket_id'] ?? 0);
    $newStatusId = (int)($input['status_id'] ?? 0);

    if (!$ticketId || !$newStatusId) {
        echo json_encode(['success' => false, 'message' => 'پارامترهای ناقص']);
        exit;
    }

    // اطلاعات کاربر
    $stmt = $db->prepare("SELECT role, organization_id, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    $orgId = $currentUser['organization_id'];
    $role  = $currentUser['role'];
    $userName = $currentUser['first_name'] . ' ' . $currentUser['last_name'];

    // بررسی تیکت
    $stmt = $db->prepare("
        SELECT t.id, t.status_id, t.created_by, t.assigned_to, t.ticket_number, t.subject,
               ts.label AS old_status_label
        FROM tickets t
        JOIN ticket_statuses ts ON t.status_id = ts.id
WHERE t.id = ? AND t.deleted_at IS NULL
        AND (t.organization_id = ? OR ? = 1)
    ");
    $stmt->execute([$ticketId, $orgId, $user_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'تیکت یافت نشد']);
        exit;
    }

    // دسترسی: فقط assigned_to (id=1) و مدیران
 // دسترسی: فقط user_id=1 (ادمین اصلی)
 $isSuperAdmin = ($user_id === 1);

    if (!$isSuperAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط مدیر اصلی سیستم می‌تواند وضعیت را تغییر دهد']);
        exit;
    }

    // بررسی وضعیت جدید
    $stmt = $db->prepare("SELECT id, label FROM ticket_statuses WHERE id = ?");
    $stmt->execute([$newStatusId]);
    $newStatus = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$newStatus) {
        echo json_encode(['success' => false, 'message' => 'وضعیت نامعتبر']);
        exit;
    }

    // آپدیت وضعیت
    $stmt = $db->prepare("UPDATE tickets SET status_id = ? WHERE id = ?");
    $stmt->execute([$newStatusId, $ticketId]);

    // ثبت تاریخچه
    $stmt = $db->prepare("
        INSERT INTO ticket_history (ticket_id, user_id, action, old_value, new_value)
        VALUES (?, ?, 'status_changed', ?, ?)
    ");
    $stmt->execute([$ticketId, $user_id, $ticket['old_status_label'], $newStatus['label']]);

    // نوتیفیکیشن به creator
    if ((int)$ticket['created_by'] !== $user_id) {
        $notifyId = (int)$ticket['created_by'];
        if ($notifyId && $notifyId !== $user_id) {
            $notif = new Notification($db);
            $notif->create([
                'to_user_id'   => $notifyId,
                'title'        => 'تغییر وضعیت تیکت: ' . $ticket['ticket_number'],
                'message'      => 'وضعیت تیکت «'.$ticket['subject'].'» به '.$newStatus['label'].' تغییر یافت',
                'type'         => 'info',
                'link'         => 'ticket-detail.php?id=' . $ticketId,
                'related_type' => 'ticket',
                'related_id'   => $ticketId,
                'sms_pattern'  => 'ticket_status_changed',
                'sms_args'     => [$ticket['subject'], $newStatus['label']],
            ]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'وضعیت تیکت به «' . $newStatus['label'] . '» تغییر یافت'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
    error_log("Ticket status change error: " . $e->getMessage());
}
