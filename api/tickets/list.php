<?php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

// ✅ بعد
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$user_id = $auth->getUserFromToken();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'عدم احراز هویت'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmtUser = $db->prepare("SELECT id, organization_id FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

$page     = max(1, intval($_GET['page'] ?? 1));
$limit    = min(200, max(1, intval($_GET['limit'] ?? 20)));
$status   = trim($_GET['status']   ?? '');
$priority = trim($_GET['priority'] ?? '');
$category = trim($_GET['category'] ?? '');
$search   = trim($_GET['search']   ?? '');
$offset   = ($page - 1) * $limit;

try {
    // فیلترِ پایه (سازمان + حذف‌نشده) — برایِ کارت‌هایِ آماری استفاده می‌شه، چون
    // اون کارت‌ها باید همیشه شکستِ کلیِ وضعیت‌ها رو نشون بدن، نه فقط بینِ
    // نتایجِ فیلترِ فعلی (وگرنه با زدنِ یک کارت، بقیه‌ی کارت‌ها صفر می‌شدن)
    $baseWhere  = [];
    $baseParams = [];
    $baseWhere[] = 't.deleted_at IS NULL';
    if ($user['id'] != 1) {
        $baseWhere[]  = 't.organization_id = ?';
        $baseParams[] = $user['organization_id'];
    }
    $baseWhereSQL = 'WHERE ' . implode(' AND ', $baseWhere);

    // فیلترِ کامل (پایه + جستجو/وضعیت/اولویت/دسته) — برایِ خودِ لیستِ تیکت‌ها
    $where  = $baseWhere;
    $params = $baseParams;

    if ($status !== '') {
        $where[]  = 'ts.name = ?';
        $params[] = $status;
    }
    if ($priority !== '') {
        $where[]  = 'tp.name = ?';
        $params[] = $priority;
    }
    if ($category !== '') {
        $where[]  = 't.category_id = ?';
        $params[] = intval($category);
    }
    if ($search !== '') {
        $where[]  = '(t.subject LIKE ? OR t.ticket_number LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    // ── آمار (بر اساسِ فیلترِ پایه، نه فیلترِ فعلی) ──
    $statsSQL = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN ts.name = 'open' THEN 1 ELSE 0 END) as open_count,
            SUM(CASE WHEN ts.name = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
            SUM(CASE WHEN ts.name = 'waiting_reply' THEN 1 ELSE 0 END) as waiting_reply_count,
            SUM(CASE WHEN ts.name = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
            SUM(CASE WHEN ts.name = 'closed' THEN 1 ELSE 0 END) as closed_count
        FROM tickets t
        LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
        LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
        {$baseWhereSQL}
    ";
    $stmtStats = $db->prepare($statsSQL);
    $stmtStats->execute($baseParams);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // ── شمارش کل ──
    $total = intval($stats['total'] ?? 0);

    // ── لیست تیکت‌ها ──
    $listSQL = "
        SELECT
            t.id,
            t.ticket_number,
            t.subject,
            t.status_id,
            t.priority_id,
            t.category_id,
            t.created_by,
            t.assigned_to,
            t.created_at,
            t.updated_at,
            ts.label   as status_label,
            ts.name    as status,
            ts.color   as status_color,
            tp.label   as priority_label,
            tp.name    as priority,
            tp.color   as priority_color,
            tc.name    as category_name,
            CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) as creator_name,
            (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id) as message_count,
            (SELECT COUNT(*) FROM ticket_attachments ta WHERE ta.ticket_id = t.id) as attachment_count
        FROM tickets t
        LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
        LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
        LEFT JOIN ticket_categories tc ON t.category_id = tc.id
        LEFT JOIN users u ON t.created_by = u.id
        {$whereSQL}
        ORDER BY t.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ";
    $stmtList = $db->prepare($listSQL);
    $stmtList->execute($params);
    $tickets = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'    => true,
        'tickets'    => $tickets,
        'stats'      => $stats,
        'pagination' => [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $total,
            'total_pages' => $total > 0 ? ceil($total / $limit) : 1
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Ticket list error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}