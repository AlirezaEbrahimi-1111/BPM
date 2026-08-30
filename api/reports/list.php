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

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
    
    $user_id = requireAuth();
    
    $database = new Database();
    $db = $database->getConnection();
    
    // پارامترهای فیلتر
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $activity_unit = $_GET['unit'] ?? null;
    $search = $_GET['search'] ?? null;
    
    // ساخت کوئری
    $where_conditions = ["r.user_id = ?"];
    $params = [$user_id];
    
    if ($activity_unit) {
        $where_conditions[] = "r.activity_unit = ?";
        $params[] = $activity_unit;
    }
    
    if ($search) {
        $where_conditions[] = "(r.content LIKE ? OR r.unique_code LIKE ?)";
        $search_term = "%{$search}%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // دریافت گزارش‌ها - توجه: LIMIT و OFFSET را مستقیم در کوئری می‌نویسیم
    $sql = "SELECT r.id, r.unique_code, r.activity_unit, r.report_date, 
                   r.created_at,
                   SUBSTRING(r.content, 1, 4096) as content_preview
            FROM reports r 
            WHERE {$where_clause}
            ORDER BY r.report_date DESC, r.created_at DESC
            LIMIT {$limit} OFFSET {$offset}";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params); // فقط پارامترهای WHERE را پاس می‌دهیم
    $reports = $stmt->fetchAll();
    
    // نام‌های واحدها
    $unitNames = [
        'RS' => 'کامپیوتر',
        'ATM' => 'فضای مجازی + رسانه',
        'AM' => 'نوجوانان',
        'AC' => 'حسابداری',
        'PR' => 'روابط عمومی',
        'HE' => 'تربیتی'
    ];
    
    foreach ($reports as &$report) {
        $report['unit_name'] = $unitNames[$report['activity_unit']] ?? $report['activity_unit'];
        if (!empty($report['content_preview'])) {
            $report['content_preview'] .= '...';
        }
    }
    
    echo json_encode([
        'success' => true,
        'reports' => $reports,
        'total' => count($reports)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("reports/list.php failed | " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور',
        'error' => 'internal_error'
    ], JSON_UNESCAPED_UNICODE);
}
?>