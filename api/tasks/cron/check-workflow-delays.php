<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/WorkflowManager.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $workflowManager = new WorkflowManager($db);

    $result = $workflowManager->checkDelays();

    $log_message = date('Y-m-d H:i:s') . ' - Workflow delays checked. ';
    $log_message .= $result['success'] ? 'Delayed workflows: ' . ($result['delayed_count'] ?? 0) : 'Error occurred';

    error_log($log_message);

} catch (Exception $e) {
    error_log(date('Y-m-d H:i:s') . ' - Error checking workflow delays: ' . $e->getMessage());
}
?>