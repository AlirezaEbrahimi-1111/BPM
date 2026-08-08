<?php
/**
 * API دستیارِ هوش‌مصنوعی: جست‌وجویِ کاربرانِ هم‌سازمان (رفعِ ابهام)
 * GET /api/ai-assistant/data/org-user-lookup.php?query=
 *
 * طبقِ بندِ ۸ سندِ docs/ai-assistant/spec-v1.md — وقتی سؤال به یک نامِ
 * مبهم اشاره دارد (مثلاً «مرخصیِ علی»)، Orchestrator این اندپوینت را
 * صدا می‌زند تا ببیند چند کاندید در سازمان مطابقت دارند؛ اگر بیش از
 * یکی بود، قبل از فراخوانیِ داده، سؤالِ روشن‌کننده می‌پرسد.
 *
 * ⚠️ زیرساختی است، نه یک ماژولِ داده‌ایِ خاص — مستقل از اینکه کدام
 * ماژول‌هایِ داده‌ای (بندِ ۶) در هر فاز فعال‌اند.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $requester_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$requester_id]);
    $orgId = $stmt->fetchColumn();
    if (!$orgId) {
        echo json_encode(['success' => true, 'users' => []]);
        exit;
    }

    $query = trim((string) ($_GET['query'] ?? ''));
    if ($query === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'پارامترِ query الزامی است']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT id, first_name, last_name, role
        FROM users
        WHERE organization_id = ?
          AND is_active = 1
          AND is_deleted = 0
          AND CONCAT(first_name, ' ', last_name) LIKE ?
        ORDER BY first_name, last_name
        LIMIT 10
    ");
    $stmt->execute([$orgId, '%' . $query . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $users = array_map(function ($u) {
        return [
            'id'        => (int) $u['id'],
            'full_name' => trim($u['first_name'] . ' ' . $u['last_name']),
            'role'      => $u['role'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("AI org-user-lookup error: " . $e->getMessage());
}
