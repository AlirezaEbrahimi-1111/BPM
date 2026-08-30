<?php
// /api/attendance/allowed-ips.php — مدیریت IPهای مجاز سازمان (supervisor)
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

function ip_out($a, $c = 200) { http_response_code($c); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

try {
    $user_id = requireAuth();
    $u = getUserInfo($user_id);
    $org  = $u['organization_id'];
    $role = $u['role'] ?? 'employee';
    $canManage = in_array((int)$user_id, getSuperAdminIds(), true) || in_array($role, ['supervisor', 'management']);
    if (!$canManage) ip_out(['success' => false, 'message' => 'دسترسی غیرمجاز'], 403);

    $db = (new Database())->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $st = $db->prepare("SELECT * FROM attendance_allowed_ips WHERE organization_id = ? ORDER BY created_at DESC");
        $st->execute([$org]);
        ip_out(['success' => true, 'ips' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $in['action'] ?? '';

    if ($action === 'add') {
        $ip = trim($in['ip_address'] ?? '');
        $label = trim($in['label'] ?? '');
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            ip_out(['success' => false, 'message' => 'آدرس IP نامعتبر است'], 400);
        }
        $st = $db->prepare("INSERT INTO attendance_allowed_ips (organization_id, ip_address, label, is_active, created_by, created_at)
                            VALUES (?, ?, ?, 1, ?, NOW())
                            ON DUPLICATE KEY UPDATE label = VALUES(label), is_active = 1");
        $st->execute([$org, $ip, $label, $user_id]);
        ip_out(['success' => true, 'message' => 'IP ثبت شد']);
    }

    $id = (int)($in['id'] ?? 0);
    if (!$id) ip_out(['success' => false, 'message' => 'شناسه الزامی است'], 400);
    $c = $db->prepare("SELECT is_active FROM attendance_allowed_ips WHERE id = ? AND organization_id = ?");
    $c->execute([$id, $org]);
    $row = $c->fetch(PDO::FETCH_ASSOC);
    if (!$row) ip_out(['success' => false, 'message' => 'IP یافت نشد'], 404);

    if ($action === 'toggle') {
        $new = $row['is_active'] ? 0 : 1;
        $db->prepare("UPDATE attendance_allowed_ips SET is_active = ? WHERE id = ?")->execute([$new, $id]);
        ip_out(['success' => true, 'message' => $new ? 'فعال شد' : 'غیرفعال شد', 'is_active' => $new]);
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM attendance_allowed_ips WHERE id = ?")->execute([$id]);
        ip_out(['success' => true, 'message' => 'حذف شد']);
    }

    ip_out(['success' => false, 'message' => 'عملیات نامعتبر'], 400);

} catch (Throwable $e) {
    error_log("Attendance allowed-ips operation failed | user_id=" . ($user_id ?? 'unknown') . " | ip=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " | error=" . $e->getMessage());
    ip_out(['success' => false, 'message' => 'خطای سرور'], 500);
}