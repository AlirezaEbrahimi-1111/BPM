<?php
// /api/attendance/denied-log.php — لیست/حذف نرمِ تلاش‌های ناموفق (supervisor)
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

function dl_out($a, $c = 200) { http_response_code($c); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

// بازهٔ زمانی → شرط SQL
function dl_range_cond($range) {
    switch ($range) {
        case 'today': return "AND DATE(l.created_at) = CURDATE()";
        case 'week':  return "AND l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        default:      return ""; // all
    }
}

try {
    $user_id = requireAuth();
    $u = getUserInfo($user_id);
    $org  = $u['organization_id'];
    $role = $u['role'] ?? 'employee';
    $canManage = in_array((int)$user_id, getSuperAdminIds(), true) || in_array($role, ['supervisor', 'management']);
    if (!$canManage) dl_out(['success' => false, 'message' => 'دسترسی غیرمجاز'], 403);

    $db = (new Database())->getConnection();

    // ── لیست ──
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $range = $_GET['range'] ?? 'today';
        $cond  = dl_range_cond($range);
        $st = $db->prepare("
            SELECT l.id, l.action, l.reason, l.ip_address, l.user_agent, l.created_at,
                   CONCAT(COALESCE(uu.first_name,''),' ',COALESCE(uu.last_name,'')) AS user_name
            FROM attendance_denied_log l
            LEFT JOIN users uu ON l.user_id = uu.id
            WHERE l.organization_id = ? AND l.is_deleted = 0 $cond
            ORDER BY l.created_at DESC
            LIMIT 500
        ");
        $st->execute([$org]);
        dl_out(['success' => true, 'logs' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // ── حذف نرم ──
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $in['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($in['id'] ?? 0);
        if (!$id) dl_out(['success' => false, 'message' => 'شناسه الزامی است'], 400);
        $db->prepare("UPDATE attendance_denied_log SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                      WHERE id = ? AND organization_id = ?")->execute([$user_id, $id, $org]);
        dl_out(['success' => true, 'message' => 'حذف شد']);
    }

    if ($action === 'delete_filtered' || $action === 'clear') {
        $range = $in['range'] ?? 'today';
        $cond  = dl_range_cond($range);
        $db->prepare("UPDATE attendance_denied_log l
                      SET l.is_deleted = 1, l.deleted_at = NOW(), l.deleted_by = ?
                      WHERE l.organization_id = ? AND l.is_deleted = 0 $cond")
           ->execute([$user_id, $org]);
        dl_out(['success' => true, 'message' => 'موارد فیلترشده حذف شدند']);
    }

    dl_out(['success' => false, 'message' => 'عملیات نامعتبر'], 400);

} catch (Throwable $e) {
    error_log("Attendance denied-log operation failed | user_id=" . ($user_id ?? 'unknown') . " | ip=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " | error=" . $e->getMessage());
    dl_out(['success' => false, 'message' => 'خطای سرور'], 500);
}
