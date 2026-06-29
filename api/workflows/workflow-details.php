<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/WorkflowManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();

    if (empty($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'شناسه workflow الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    $workflowManager = new WorkflowManager($db);

    // امنیت: سازمانِ کاربرِ جاری
    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $org_id = (int) $stmt->fetchColumn();

    $workflow = $workflowManager->getWorkflowDetails($_GET['id'], $org_id);

    if ($workflow) {
        echo json_encode(['success' => true, 'workflow' => $workflow]);
    } else {
        echo json_encode(['success' => false, 'message' => 'workflow یافت نشد']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
?>