<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $database = new Database();
    $db = $database->getConnection();
    
    $stats = [
        'total' => 0,
        'today' => 0,
        'week' => 0,
        'month' => 0
    ];
    
    // کل گزارش‌ها
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reports WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['total'] = $stmt->fetch()['count'];
    
    // گزارش‌های امروز
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reports WHERE user_id = ? AND DATE(report_date) = CURDATE()");
    $stmt->execute([$user_id]);
    $stats['today'] = $stmt->fetch()['count'];
    
    // گزارش‌های این هفته
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reports WHERE user_id = ? AND YEARWEEK(report_date) = YEARWEEK(NOW())");
    $stmt->execute([$user_id]);
    $stats['week'] = $stmt->fetch()['count'];
    
    // گزارش‌های این ماه
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reports WHERE user_id = ? AND MONTH(report_date) = MONTH(NOW()) AND YEAR(report_date) = YEAR(NOW())");
    $stmt->execute([$user_id]);
    $stats['month'] = $stmt->fetch()['count'];
    
    echo json_encode(['success' => true, 'stats' => $stats]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Reports stats error: " . $e->getMessage());
}
?>