<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/WorkflowManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
$user_id = requireAuth();
    
    if (empty($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'شناسه الگو الزامی است']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // دریافت organization_id کاربر
    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['organization_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'کاربر یا سازمان یافت نشد']);
        exit;
    }
    
    $workflowManager = new WorkflowManager($db);
    $template = $workflowManager->getTemplateDetails($_GET['id'], $user['organization_id']);
    
    if ($template) {
        echo json_encode(['success' => true, 'template' => $template]);
    } else {
        echo json_encode(['success' => false, 'message' => 'الگو یافت نشد یا دسترسی ندارید']);
    }
} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
?>