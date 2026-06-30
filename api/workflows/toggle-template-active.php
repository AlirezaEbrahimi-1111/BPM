<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/WorkflowManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    $user = getUserInfo($user_id);

    if (!$user['can_create_workflow']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'عدم دسترسی']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['template_id']) || !isset($input['is_active'])) {
        echo json_encode(['success' => false, 'message' => 'پارامترهای الزامی ناقص است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    $workflowManager = new WorkflowManager($db);

    $result = $workflowManager->setTemplateActive(
        (int)$input['template_id'],
        $user['organization_id'],
        !empty($input['is_active']) ? 1 : 0
    );

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
?>