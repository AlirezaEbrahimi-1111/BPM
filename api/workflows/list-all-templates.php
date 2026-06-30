<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/WorkflowManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();
    $workflowManager = new WorkflowManager($db);

    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['organization_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'کاربر یا سازمان یافت نشد']);
        exit;
    }

    $templates = $workflowManager->getAllTemplates($user['organization_id']);

    echo json_encode([
        'success' => true,
        'templates' => $templates
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
?>