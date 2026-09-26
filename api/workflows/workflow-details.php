<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/WorkflowManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    $user_id = requireAuth();

    if (empty($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'شناسه workflow الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    $workflowManager = new WorkflowManager($db);

    // امنیت: سازمان کاربر جاری
    $me = loadUserForPermissions($db, $user_id);
    if (!$me) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }
    $org_id = (int) $me['organization_id'];

    $workflow = $workflowManager->getWorkflowDetails($_GET['id'], $org_id);

    if (!$workflow) {
        echo json_encode(['success' => false, 'message' => 'workflow یافت نشد']);
        exit;
    }

    // 🔒 دسترسی کامل (دیدن همهٔ مراحل): سوپرادمین، سازندهٔ workflow،
    // نقش کل‌بین سازمانی (supervisor/admin)، یا manager سازنده
    $hasFullAccess = isSuperAdmin($me)
        || (int) $workflow['created_by'] === (int) $user_id
        || (hasPermission($me, 'view_all_org_tasks') && isSameOrganization($me, $workflow['organization_id']))
        || canManageTargetUser($db, $me, (int) $workflow['created_by']);

    $user_section = $me['activity_section'] ?? '';

    if (!$hasFullAccess) {
        // آیا اصلا عضو واحد حداقل یک مرحله از این workflow هست؟
        $isParticipant = false;
        foreach ($workflow['steps'] as $s) {
            if (!empty($s['activity_section']) && $s['activity_section'] === $user_section) {
                $isParticipant = true;
                break;
            }
        }
        if (!$isParticipant) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'دسترسی به این workflow ندارید']);
            exit;
        }

        // فقط مرحله‌هایی که به واحد خودش مربوط است — نه کل مراحل
        $workflow['steps'] = filterWorkflowStepsForViewer($workflow['steps'], false, $user_section);
        $stats = computeWorkflowProgress($workflow['steps']);
        $workflow['total_steps']     = $stats['total_steps'];
        $workflow['completed_steps'] = $stats['completed_steps'];
        $workflow['progress']        = $stats['progress'];
    }

    echo json_encode(['success' => true, 'workflow' => $workflow]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
?>