<?php
// جلوگیری از دسترسی مستقیم از مرورگر — هم‌راستا با بقیهٔ اسکریپت‌های کرون
// (مثل cron/auto_approval_check.php)؛ قبلا این چک اینجا نبود و هر کسی
// می‌تونست این اسکریپت رو از وب صدا بزنه
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

// نکته: مسیرهای نسبی قبلی («../») فقط یک سطح بالا می‌رفتن (تا api/tasks/)
// نه تا ریشهٔ سایت، پس این require_once ها همیشه با «failed to open stream»
// فیل می‌شدن و این اسکریپت هیچ‌وقت واقعا اجرا نمی‌شد
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/WorkflowManager.php';

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