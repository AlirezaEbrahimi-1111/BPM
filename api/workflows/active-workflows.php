<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/WorkflowManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $filters = [];
    if (!empty($_GET['template_id'])) $filters['template_id'] = $_GET['template_id'];
    if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
    
    $database = new Database();
    $db = $database->getConnection();

    // امنیت: سازمانِ کاربرِ جاری (از دیتابیس، مطمئن‌تر از توکن)
    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $org_id = (int) $stmt->fetchColumn();

    $workflowManager = new WorkflowManager($db);
    
    $workflows = $workflowManager->getActiveWorkflows($filters, $org_id);
    echo json_encode(['success' => true, 'workflows' => $workflows]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
?>