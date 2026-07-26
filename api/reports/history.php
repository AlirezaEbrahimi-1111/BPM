<?php
// api/reports/history.php - دریافت تاریخچه گزارش‌ها
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $database = new Database();
    $db = $database->getConnection();
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $limit = max(1, min(100, $limit));
    
    $sql = "SELECT id, unique_code, activity_unit, report_date, 
                   SUBSTRING(content, 1, 100) as content_preview,
                   created_at
            FROM reports 
            WHERE user_id = ?
            ORDER BY report_date DESC, created_at DESC
            LIMIT ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $limit]);
    $reports = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'reports' => $reports]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
}
?>