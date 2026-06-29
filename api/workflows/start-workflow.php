<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/WorkflowManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    $user = getUserInfo($user_id);

    if (!$user['can_create_workflow']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما دسترسی ایجاد کار روتین را ندارید']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['template_id'], $input['title']) || $input['title'] === '') {
        echo json_encode(['success' => false, 'message' => 'الگو و عنوان الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    $workflowManager = new WorkflowManager($db);

// حالت اجرا دیگر کلی نیست؛ per-step از قالب خوانده می‌شود. مقدار پیش‌فرض فقط برای سازگاری.
    $result = $workflowManager->startWorkflow($input['template_id'], $input['title'], $user_id, $user['organization_id'], 'cascade');

    // اطمینان از اینکه instance_id در خروجی وجود دارد
    if ($result['success'] && empty($result['instance_id'])) {
        // اگر instance_id وجود نداشت، سعی کنیم از دیتابیس بگیریم
        $stmt = $db->prepare("
            SELECT id FROM workflow_instances 
            WHERE template_id = ? AND created_by = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$input['template_id'], $user_id]);
        $lastInstance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lastInstance) {
            $result['instance_id'] = $lastInstance['id'];
        }
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
}
?>