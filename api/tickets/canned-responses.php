<?php
/**
 * API: پاسخ‌هایِ آماده‌ی تیکت (canned responses)
 *
 *   GET  /api/tickets/canned-responses.php            → لیستِ سازمانِ کاربر
 *   POST /api/tickets/canned-responses.php
 *        body: { action: 'create'|'update'|'delete', id?, title?, body? }
 *
 * دسترسی:
 *   - خواندن: هر کاربرِ احرازشده (چون هرکسی که به تیکت پاسخ می‌ده لازمش داره)
 *   - نوشتن (ساخت/ویرایش/حذف): فقط supervisor/management یا سوپرادمین —
 *     همون قاعده‌ی ad-hoc که بقیه‌ی بخش‌هایِ مدیریتیِ این اپ استفاده می‌کنن
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT role, organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$me) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit;
    }

    $orgId = (int) $me['organization_id'];
    $canManage = in_array($me['role'], ['supervisor', 'management'], true) || (int) $user_id === 1;

    // ─────────── خواندن ───────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare("
            SELECT id, title, body, sort_order
            FROM ticket_canned_responses
            WHERE organization_id = ?
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute([$orgId]);
        echo json_encode([
            'success'    => true,
            'responses'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'can_manage' => $canManage,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─────────── نوشتن ───────────
    if (!$canManage) {
        http_response_code(403);
        error_log("canned-responses write denied | user_id={$user_id}");
        echo json_encode(['success' => false, 'message' => 'فقط مدیران می‌توانند پاسخ‌های آماده را تغییر دهند']);
        exit;
    }

    $input  = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $input['action'] ?? '';
    $id     = (int) ($input['id'] ?? 0);
    $title  = trim((string) ($input['title'] ?? ''));
    $body   = trim((string) ($input['body'] ?? ''));

    if ($action === 'create') {
        if ($title === '' || $body === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'عنوان و متن الزامی است']);
            exit;
        }
        $next = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM ticket_canned_responses WHERE organization_id = ?");
        $next->execute([$orgId]);
        $sortOrder = (int) $next->fetchColumn();

        $db->prepare("
            INSERT INTO ticket_canned_responses (organization_id, title, body, sort_order, created_by)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$orgId, $title, $body, $sortOrder, $user_id]);

        echo json_encode(['success' => true, 'message' => 'پاسخ آماده ذخیره شد', 'id' => (int) $db->lastInsertId()]);
        exit;
    }

    if ($action === 'update') {
        if (!$id || $title === '' || $body === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ورودی نامعتبر است']);
            exit;
        }
        // 🔒 فقط داخلِ سازمانِ خودِ کاربر — تا با حدسِ id نشه پاسخِ سازمانِ دیگه رو ویرایش کرد
        $db->prepare("UPDATE ticket_canned_responses SET title = ?, body = ? WHERE id = ? AND organization_id = ?")
            ->execute([$title, $body, $id, $orgId]);

        echo json_encode(['success' => true, 'message' => 'پاسخ آماده به‌روزرسانی شد']);
        exit;
    }

    if ($action === 'delete') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'شناسه الزامی است']);
            exit;
        }
        $db->prepare("DELETE FROM ticket_canned_responses WHERE id = ? AND organization_id = ?")
            ->execute([$id, $orgId]);

        echo json_encode(['success' => true, 'message' => 'پاسخ آماده حذف شد']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'عملیات نامعتبر است']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("canned-responses error: " . $e->getMessage());
}
