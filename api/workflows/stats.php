<?php
// api/workflows/stats.php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));

error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

    $database = new Database();
    $db = $database->getConnection();

    $user_id = requireAuth();
    $user = getUserInfo($user_id);
    $org_id = $user['organization_id'];

    $stats = [];

    // کارهای در حال اجرا
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM workflow_instances WHERE status = 'in_progress' AND organization_id = :org_id AND is_deleted = 0");
    $stmt->execute(['org_id' => $org_id]);
    $stats['active'] = (int)$stmt->fetch()['count'];

    // کارهای با تأخیر
    $stmt = $db->prepare("SELECT COUNT(DISTINCT wis.instance_id) as count FROM workflow_instance_steps wis
                          JOIN workflow_instances wi ON wis.instance_id = wi.id
                          WHERE wi.status = 'in_progress' 
                          AND wi.organization_id = :org_id
                          AND wi.is_deleted = 0
                          AND wis.deadline < NOW()
                          AND wis.status IN ('active', 'pending')");
    $stmt->execute(['org_id' => $org_id]);
    $stats['delayed'] = (int)$stmt->fetch()['count'];

    // کارهای تکمیل شده این ماه
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM workflow_instances 
                          WHERE status = 'completed' 
                          AND organization_id = :org_id
                          AND is_deleted = 0
                          AND MONTH(completed_at) = MONTH(CURDATE()) 
                          AND YEAR(completed_at) = YEAR(CURDATE())");
    $stmt->execute(['org_id' => $org_id]);
    $stats['completed'] = (int)$stmt->fetch()['count'];

    // مراحل فعال
    $stmt = $db->prepare("SELECT COUNT(*) as count 
                          FROM workflow_instance_steps wis
                          JOIN workflow_instances wi ON wis.instance_id = wi.id
                          WHERE wis.status = 'active' AND wi.organization_id = :org_id AND wi.is_deleted = 0");
    $stmt->execute(['org_id' => $org_id]);
    $stats['active_steps'] = (int)$stmt->fetch()['count'];

    echo json_encode(['success' => true, 'stats' => $stats], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
