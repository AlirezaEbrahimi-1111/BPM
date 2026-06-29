<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['organization_id']) {
        echo json_encode(['allowed' => false, 'message' => 'سازمان یافت نشد']);
        exit;
    }
    
    $org_id = $user['organization_id'];
    
    // تعداد فعلی
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE organization_id = ? AND is_deleted = 0");
    $countStmt->execute([$org_id]);
    $currentCount = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // سقف مجاز
    $subStmt = $db->prepare("
        SELECT max_users FROM subscriptions 
        WHERE organization_id = ? AND is_active = 1 AND end_date >= CURDATE()
        ORDER BY end_date DESC LIMIT 1
    ");
    $subStmt->execute([$org_id]);
    $subscription = $subStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subscription) {
        echo json_encode(['allowed' => false, 'message' => 'اشتراک فعالی یافت نشد']);
        exit;
    }
    
    $maxUsers = (int)$subscription['max_users'];
    
    if ($currentCount >= $maxUsers) {
        echo json_encode([
            'allowed' => false,
            'message' => "سقف تعداد کاربران ({$maxUsers} نفر) تکمیل شده. برای افزایش، اشتراک را ارتقا دهید.",
            'current' => $currentCount,
            'max' => $maxUsers
        ]);
    } else {
        echo json_encode([
            'allowed' => true,
            'current' => $currentCount,
            'max' => $maxUsers,
            'remaining' => $maxUsers - $currentCount
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['allowed' => false, 'message' => 'خطای سرور']);
}