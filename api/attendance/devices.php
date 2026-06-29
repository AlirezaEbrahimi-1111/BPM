<?php
// /api/attendance/devices.php — مدیریت دستگاه‌های حضور/غیاب (supervisor)
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/AttendanceNotify.php';

function dev_out($a, $c = 200) { http_response_code($c); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

try {
    $user_id = requireAuth();
    $u = getUserInfo($user_id);
    $org  = $u['organization_id'];
    $role = $u['role'] ?? 'employee';
    $canManage = ((int)$user_id === 1) || in_array($role, ['supervisor', 'management']);
    if (!$canManage) dev_out(['success' => false, 'message' => 'دسترسی غیرمجاز'], 403);

    $db = (new Database())->getConnection();

    // ── لیست دستگاه‌ها ──
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $st = $db->prepare("
            SELECT d.*,
                   CONCAT(COALESCE(fu.first_name,''),' ',COALESCE(fu.last_name,'')) AS first_seen_user_name,
                   CONCAT(COALESCE(au.first_name,''),' ',COALESCE(au.last_name,'')) AS approved_by_name
            FROM attendance_devices d
            LEFT JOIN users fu ON d.first_seen_user_id = fu.id
            LEFT JOIN users au ON d.approved_by = au.id
            WHERE d.organization_id = ?
            ORDER BY (d.status = 'pending') DESC, d.created_at DESC
        ");
        $st->execute([$org]);
        dev_out(['success' => true, 'devices' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // ── عملیات ──
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $in['action'] ?? '';
    $id = (int)($in['id'] ?? 0);
    if (!$id) dev_out(['success' => false, 'message' => 'شناسه الزامی است'], 400);

    $c = $db->prepare("SELECT id FROM attendance_devices WHERE id = ? AND organization_id = ?");
    $c->execute([$id, $org]);
    if (!$c->fetch()) dev_out(['success' => false, 'message' => 'دستگاه یافت نشد'], 404);

    if ($action === 'approve') {
        $db->prepare("UPDATE attendance_devices SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?")
           ->execute([$user_id, $id]);
        attendance_notify_requester_review($db, $org, $id, true);   // اطلاع به درخواست‌دهنده
        dev_out(['success' => true, 'message' => 'دستگاه تأیید شد']);
    } elseif ($action === 'reject') {
        $db->prepare("UPDATE attendance_devices SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=?")
           ->execute([$user_id, $id]);
        attendance_notify_requester_review($db, $org, $id, false);  // اطلاع به درخواست‌دهنده
        dev_out(['success' => true, 'message' => 'دستگاه رد شد']);
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM attendance_devices WHERE id=?")->execute([$id]);
        dev_out(['success' => true, 'message' => 'دستگاه حذف شد']);
    } elseif ($action === 'relabel') {
        $label = trim($in['label'] ?? '');
        $db->prepare("UPDATE attendance_devices SET label=? WHERE id=?")->execute([$label, $id]);
        dev_out(['success' => true, 'message' => 'برچسب ذخیره شد']);
    }

    dev_out(['success' => false, 'message' => 'عملیات نامعتبر'], 400);

} catch (Throwable $e) {
    dev_out(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()], 500);
}