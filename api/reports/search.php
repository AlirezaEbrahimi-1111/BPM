<?php
// api/reports/search.php - جستجو در گزارش‌ها
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    if (empty($_GET['q'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'کلمه کلیدی الزامی است']);
        exit;
    }
    
    $query = $_GET['q'];
    $unit = $_GET['unit'] ?? null;
    
    $database = new Database();
    $db = $database->getConnection();
    
    $sql = "SELECT r.id, r.unique_code, r.activity_unit, r.report_date,
                   SUBSTRING(r.content, 1, 200) as content_preview,
                   r.created_at,
                   u.first_name, u.last_name
            FROM reports r
            JOIN users u ON r.user_id = u.id
            WHERE (r.user_id = ? OR u.activity_section = 'management')
            AND MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE)";
    
    $params = [$user_id, $query];
    
    if ($unit) {
        $sql .= " AND r.activity_unit = ?";
        $params[] = $unit;
    }
    
    $sql .= " ORDER BY r.report_date DESC LIMIT 50";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'reports' => $reports, 'query' => $query]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Search reports error: " . $e->getMessage());
}
?>