<?php
/**
 * api/workflows/resolve-step.php
 * تصمیمِ یک «مرحلهٔ تصمیم»ِ روتین: تأیید یا رد (با پرشِ شرطی).
 * POST { task_id, decision: 'approve'|'reject', notes? }
 *
 * فقط تعریف‌کنندهٔ همان نمونهٔ روتین مجاز است (چکِ نهایی داخلِ
 * WorkflowManager::resolveStepDecision انجام می‌شود). این‌جا فقط
 * چکِ سازمان + معتبربودنِ تسکِ روتین.
 */

header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/WorkflowManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $user    = getUserInfo($user_id);
    $org_id  = $user['organization_id'] ?? null;

    $input    = json_decode(file_get_contents('php://input'), true) ?: [];
    $task_id  = (int) ($input['task_id'] ?? 0);
    $decision = ($input['decision'] ?? '') === 'reject' ? 'reject' : 'approve';
    $notes    = trim((string) ($input['notes'] ?? ''));

    if ($task_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }
    if ($decision === 'reject' && $notes === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'برای رد کردن، وارد کردن دلیل الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // 🔒 تسک باید یک تسکِ روتینِ همین سازمان باشد
    $stmt = $db->prepare("
        SELECT id FROM tasks
        WHERE id = ? AND is_workflow_task = 1 AND organization_id = ?
    ");
    $stmt->execute([$task_id, $org_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کار یافت نشد یا بخشی از روتین نیست']);
        exit;
    }

    $workflowManager = new WorkflowManager($db);
    $result = $workflowManager->resolveStepDecision($task_id, $user_id, $decision, $notes);

    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    error_log("resolve-step.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
