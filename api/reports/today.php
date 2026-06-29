<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $database = new Database();
    $db = $database->getConnection();
    
    $sql = "SELECT id, unique_code, activity_unit, report_date, created_at, content
            FROM reports
            WHERE user_id = ? AND report_date = CURDATE()
            ORDER BY created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
    $reports = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'reports' => $reports,
        'count' => count($reports)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور'
    ]);
    error_log("Get today reports error: " . $e->getMessage());
}
?>