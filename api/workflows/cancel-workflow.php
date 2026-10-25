<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/WorkflowManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-access.php';
    requirePlanFeature($user_id, 'routine');
    $user    = getUserInfo($user_id);
    $org_id  = $user['organization_id'];

    $input = json_decode(file_get_contents('php://input'), true);
    $instance_id = (int)($input['instance_id'] ?? 0);
    if ($instance_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه روتین الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // 🔒 خط قرمز: روتین باید متعلق به همین سازمان باشد، وگرنه کاربر یک
    // سازمان می‌تواند روتین در حال اجرای سازمان دیگر را لغو کند
    $stmt = $db->prepare("SELECT id, created_by FROM workflow_instances WHERE id = ? AND organization_id = ?");
    $stmt->execute([$instance_id, $org_id]);
    $instance = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$instance) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'روتین یافت نشد']);
        exit;
    }

    // 🔒 لغو مستقیم فقط برای تعریف‌کنندهٔ روتین یا کسی که نظارت بر همهٔ روتین‌ها را دارد؛
    // بقیه باید از مسیر «درخواست توقف روتین» (api/workflows/request-stop.php) بروند
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
    $me = loadUserForPermissions($db, $user_id);
    if ((int) $instance['created_by'] !== (int) $user_id && !hasPermission($me, 'monitor_all_workflows')) {
        error_log("cancel-workflow denied (not creator/monitor) | user_id={$user_id} | instance_id={$instance_id}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط تعریف‌کنندهٔ روتین می‌تواند آن را مستقیم لغو کند']);
        exit;
    }

    $workflowManager = new WorkflowManager($db);

    $result = $workflowManager->cancelWorkflow($instance_id, $user_id, $input['reason'] ?? '');
    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
?>